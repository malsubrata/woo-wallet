# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

TeraWallet (slug `woo-wallet`, formerly WooWallet) is a WooCommerce wallet plugin: digital ledger, top-ups via WooCommerce gateways, partial payments, cashback, peer-to-peer transfers, marketplace integrations. WordPress 6.4+, **PHP 7.4+**, requires WooCommerce.

The PHP floor is **7.4** (`readme.txt` `Requires PHP`, `composer.json`). Write modern PHP 7.4:
return types, scalar type hints, `??`, short array syntax. Match the surrounding file's style,
but do not hand-cripple new code to an older floor.

The plugin entry point is `woo-wallet.php`, which boots `Woo_Wallet::instance()` (singleton in `includes/class-woo-wallet.php`). Access the global instance with `woo_wallet()`.

## Build / dev commands

JS/CSS toolchain is `@wordpress/scripts` + a custom `webpack.config.js` that emits **two** webpack configs (must run together):

```bash
npm run build       # production build → build/
npm run start       # watch mode
npm run lint:js     # lint src/
npm run lint:css    # lint src/scss
npm run make-pot    # regenerate languages/woo-wallet.pot (requires WP-CLI)
```

There **is** a PHP integration test suite (PHPUnit + the WordPress test
framework), run locally and in CI (`.github/workflows/ci.yml`). Codex is no longer supported
on this repo — `.claude/` is the only agent configuration.

```bash
composer install                  # install dev dependencies (PHPUnit, wp-phpunit)
bash bin/install-wp-tests.sh      # create the wp_terawallet_tests DB + wp-tests-config.php (one-time)
composer test                     # run the suite
```

Tests live in `tests/` (`tests/bootstrap.php` boots real WP + WooCommerce
against a dedicated test database; cases are `tests/test-*.php`). Each test
runs inside a rolled-back DB transaction, so the live site database is never
mutated. The suite (run `composer test` for the live count) covers the ledger core
(credit/debit/balance, transfer, precision), idempotency, partial-payment
debit timing / locked wallet / refunds / fee-tax blocking, the referral
service, transaction categories, reports data and legacy currency
normalisation. Extend it when you touch money-moving code. Don't claim "tests
pass" without running `composer test` and reading the output.

`build/` is gitignored but is the only thing WordPress loads at runtime. After editing anything in `src/`, run `npm run build` or the change won't show up.

Webpack has two entries:
- `wcBuildConfig` — bundles that integrate with WooCommerce Blocks (`payment-method/`, `partial-payment/`) and the React settings app (`admin/settings/`). Uses `WooCommerceDependencyExtractionWebpackPlugin` to externalize `@woocommerce/*` and `@wordpress/*`.
- `vanillaAssetsConfig` — non-block legacy admin/frontend bundles (`admin/{actions,order,product,export,main}`, `frontend/main`). Generates RTL CSS + `.asset.php` files.

If you add a new entry, add it to the correct config — block-aware code goes in `wcBuildConfig`, plain WP admin/frontend JS goes in `vanillaAssetsConfig`.

## Architecture

### Boot sequence (`includes/class-woo-wallet.php`)

`Woo_Wallet::__construct()` runs `includes()` then `init_hooks()`. `includes()` is request-aware via `is_request()` — admin/frontend/ajax files are conditionally loaded. The order matters: helpers → settings API → `$this->wallet` (`Woo_Wallet_Wallet`) → `$this->cashback` → `Woo_Wallet_Signup_Handler` → admin/frontend classes → on `init` (priority 5) the payment gateway, marketplace shims, multicurrency shims, WooCommerce filters. The earning-actions registry and REST API load on `woocommerce_init` (fires inside `init` only when WooCommerce is fully loaded) via `woocommerce_loaded_callback()`. WooCommerce-Blocks integrations register on `woocommerce_blocks_loaded`.

`Woo_Wallet_Signup_Handler` (`includes/class-woo-wallet-signup-handler.php`) is loaded unconditionally and early — its `user_register` listener is registered at plugin-load time so it catches users created *before* `woocommerce_init` (SSO/SAML, social login, REST, WP-CLI, programmatic `wp_insert_user()`). It stamps a `_woo_wallet_signup_pending` user meta marker and drains it — running the new-registration and referral signup-bonus handlers — once the earning-action registry is available (`woocommerce_init` priority 99, plus `wp`/`admin_init`/`wp_login` as a cross-request safety net). The earning-action classes therefore no longer register `user_register` themselves.

Hot extension points: `woo_wallet_loaded`, `woo_wallet_init`, `woo_wallet_activated`, `woo_wallet_deactivated`, `woo_wallet_transaction_recorded`.

### Ledger and concurrency (critical)

**Three** custom tables, created/updated by `Woo_Wallet_Install` (`includes/class-woo-wallet-install.php`):
- `{prefix}woo_wallet_transactions` — append-only credit/debit rows, balance is `SUM(credit − debit) WHERE deleted=0`. The `_current_woo_wallet_balance` user meta is a cache.
- `{prefix}woo_wallet_transaction_meta`
- `{prefix}woo_wallet_referrals` (`Woo_Wallet_Install::get_referrals_schema()`)

If you add or remove a table, update `uninstall.php` in the same change — it must drop
every table the installer creates, and CI enforces that.

Schema migrations are version-keyed in the `$db_updates` array (top of `class-woo-wallet-install.php`); the callbacks live in `includes/helper/woo-wallet-update-functions.php`. **When you bump `WOO_WALLET_PLUGIN_VERSION` and need a schema change, register both the version key and the callback.**

Money-moving paths (`Woo_Wallet_Wallet::recode_transaction`, `::transfer`) serialize concurrent writers using MySQL `GET_LOCK('woo_wallet_lock_user_<id>', timeout)`. Only `transfer()` wraps `START TRANSACTION` (two rows must commit together); `recode_transaction()` holds the user lock around a single `INSERT` with no explicit transaction. `transfer()` acquires both user locks in **deterministic min/max id order** to avoid the A→B / B→A deadlock. The pre-debit balance check uses the raw `SUM(...)` query, **not** `apply_filters( 'woo_wallet_current_balance', ... )` — third-party balance filters mutate state via the `woo_wallet_transaction_recorded` hook, which `recode_transaction()` fires *inside* the user lock but `transfer()` fires *after* releasing both locks, so a filtered balance read inside the lock can be stale on the transfer path (a TOCTOU window). Keep that distinction when editing concurrency-sensitive code; the inline comment above the balance gate in `recode_transaction()` documents the reasoning.

### Money-path rules (non-negotiable)

This is real money. These rules exist because they were broken: in 1.7.4 a
customer on a multi-currency store could transfer ~3× their balance (reported
by a researcher, fixed in 1.7.5). The same bug class had been fixed for REST
top-up in that very release; nobody checked `transfer()`.

1. **Currency contract.** Every amount handed to `credit()`, `debit()` or
   `transfer()` is in `$args['currency']`, else the **active** storefront
   currency. In `single_base` mode the ledger (`SUM(credit − debit)`) is in
   **base**. Never compare, add or subtract two amounts — balance vs amount,
   limit vs amount, charge + amount — until both are in the same currency.
   Settings (limits, fixed charges, earning-action amounts) are stored in base;
   the multicurrency integration converts some of them to active on read, so
   check `Woo_Wallet_Multicurrency_Integration::__construct()` before
   assuming either. A base-denominated amount passed without `currency` is a bug.
   **Precision too:** round ledger/base amounts with
   `WOO_Wallet_Helper::get_ledger_decimals()`, never `wc_get_price_decimals()` —
   switchers (WOOCS `pre_option_`, YayCurrency filter) rewrite that to the
   shopper's currency, so the same refund rounded differently per session.
   `wc_get_price_decimals()` is for display and active-currency figures only.
2. **Fix the class, not the instance.** When a money bug is found, grep every
   function that shares the pattern (every caller of the writer, every gate,
   every limit/charge) and list them in `spec/<version>/progress.md` as fixed or
   cleared, with a one-line reason each. A fix with no sibling sweep is not done.
3. **Comments are not guarantees.** "X is not supported" must be enforced in
   code (reject it) or pinned by a test. If it isn't, assume X happens.
4. **Multi-currency tests are mandatory** for any money-path change: active ≠
   base, with both a *stronger* currency (overdraw direction) and a *weaker* one
   (false-refusal direction), plus an explicit `currency` arg ≠ active. Assert
   the raw ledger SUM, not the filtered balance. Template:
   `tests/test-transfer-multicurrency.php`. A test suite that only ever runs
   active = base proves nothing about multi-currency stores.
5. **Verify live with a real provider** (WOOCS, YayCurrency — installed on the
   local site) using a wp-cli script against the real service, including the
   attacker's numbers from the report. Back up and restore every option/post you
   change; delete every test user and ledger row you create.
6. **Release gate.** Never tell the developer a version is ready to release on
   a money path until: `composer test` was run and read; `wallet-ledger-auditor`
   reviewed the whole touched path (callers and callees, not only the diff);
   rules 2, 4 and 5 are done. State plainly what was *not* verified. If unsure,
   say "not ready" — a delayed release costs less than a ledger defect.

Idempotency for state-changing REST endpoints lives in `WooWallet_Idempotency` (`includes/services/class-woo-wallet-idempotency.php`) — replays the cached `WP_REST_Response`/`WP_Error` for a `(user_id, Idempotency-Key)` tuple within a 24h TTL. Form-side single-use claims (transient prefix `wwxfer_`) live in `Woo_Wallet_Frontend` and have different semantics (consumed on first use).

### Transactional services

`includes/services/`:
- `class-woo-wallet-topup-service.php` — top-up flow.
- `class-woo-wallet-transfer-service.php` — peer-to-peer transfer (uses `transfer()`).
- `class-woo-wallet-idempotency.php` — request-replay cache.

Prefer routing new state-changing logic through these services rather than calling `$wallet->credit()/debit()` directly from REST controllers.

### REST API

`terawallet/v1` is the canonical namespace. `wc/v3/wallet/*` is a deprecated legacy layer (thin proxy shims, retained for third-party back-compat until plugin 2.0).

Registered by `WooWallet_API` (`includes/api/class-woo-wallet-api.php`) via a central registry (`includes/api/class-terawallet-rest-route-registry.php`). Folder layout:

```
includes/api/
  abstracts/          ← base classes shared by all controllers
  v1/
    me/               ← customer endpoints (cookie+nonce auth)
    admin/            ← admin DataView endpoints (manage_woocommerce cap)
    public/           ← unauthenticated endpoints
    settings/         ← GET /settings + POST section/js-section/action
    system/           ← GET /multicurrency
  legacy/wc-v3/       ← deprecated wc/v3 proxy shims (do not add logic here)
```

To add a new `terawallet/v1` controller: drop its file in the appropriate `v1/` subfolder, add the filename to the `foreach` loop in `WooWallet_API::rest_api_includes()`, and add the class name to `TeraWallet_REST_Route_Registry::register_all()`.

### Earning actions

`includes/actions/` defines `WooWalletAction` subclasses (`abstracts/abstract-woo-wallet-actions.php` extends `WC_Settings_API`) — daily visits, registration, product review, referrals, sell-content. Loaded by `WOO_Wallet_Actions::load_actions()` which fires on the `woocommerce_loaded` hook chain. Add a new action by dropping a file in `includes/actions/` and ensuring it gets registered in the loader.

Signup-triggered actions (new registration, referral signup bonus) must **not** register `user_register` directly — that hook fires before this registry loads for SSO/programmatic signups. Instead `Woo_Wallet_Signup_Handler::process()` calls their handler methods. Earning-action amount settings are entered in the store base currency; pass `array( 'currency' => $this->get_base_currency() )` to `credit()` so the ledger skips active-currency conversion.

### Marketplace and multicurrency shims

`includes/marketplace/` and `includes/multicurrency/` contain conditional integrations gated on `class_exists` (Dokan, WCFM, WCMp, WOOCS, WCML). These are loaded from `add_marketplace_support()` / `add_multicurrency_support()` on the `init` hook. New integrations should follow the same conditional-load pattern; do not hard-require the integration class.

### Templates

`templates/` holds overridable PHP UI partials. Theme overrides go in `<theme>/woo-wallet/<template>.php`; the resolver is `Woo_Wallet::locate_template()`. Two emails (`emails/low-wallet-balance.php`, `emails/user-new-transaction.php`) are mapped through `woocommerce_template_directory` so they live alongside WooCommerce email overrides. **Don't move or rename templates** — themes in the wild override them.

### Admin settings UI

The admin settings page is a React app at `src/admin/settings/` (entry `index.js` → `App.jsx`). Field components live in `fields/`, layout in `components/`, registry in `registry/fieldTypes.js`, data hook in `hooks/useSettings.js`. It's wired to REST via `apiFetch` + a localized `restNonce`. The legacy admin scripts (`actions.js`, `order.js`, `product.js`, `export.js`, `main.js`) are plain JS and are not React.

**Extension API.** Third-party plugins extend the settings page via the JS-first registry at `src/admin/settings/registry/index.js`, exposed on `window.wooWallet.settings` (`registerTab`, `registerField`, `registerFieldType`, `registerIcon`). Saves for JS-registered tabs go to `POST /terawallet/v1/settings/js-section`, which sanitizes per a server-side whitelist of hints — keep the JS `SANITIZE_HINTS` array in `registry/index.js` and the PHP whitelist in `TeraWallet_REST_Settings_Js_Section_Controller::validate_sanitize_hint()` in sync. The legacy PHP filters `woo_wallet_settings_sections` / `woo_wallet_settings_fields` remain wired for back-compat but are not the recommended path for new integrations. See `docs/EXTENDING_SETTINGS.md` for the full developer guide. The canonical PHP read helper for any tab's persisted values is `woo_wallet_get_setting( $tab_id, $field_name, $default )`.

## Conventions

- Direct DB access uses `$wpdb->prepare()` and is annotated with `phpcs:ignore WordPress.DB.DirectDatabaseQuery.*` — keep the pattern.
- Class file naming: `class-woo-wallet-<feature>.php` for legacy classes, `class-terawallet-<feature>.php` under `includes/api/`. New REST code follows the latter.
- Text domain is `woo-wallet`. Don't introduce a different domain.
- Ledger writes must go through `Woo_Wallet_Wallet::credit/debit/transfer`. Don't write directly to `woo_wallet_transactions`.
- `WOO_WALLET_PLUGIN_VERSION` lives in **both** `woo-wallet.php` (header + `define`) and is referenced by `Woo_Wallet_Install`. Bump together.
- The version string lives in **four** places and they must always agree: `woo-wallet.php` header `Version:`, `woo-wallet.php` `WOO_WALLET_PLUGIN_VERSION`, `readme.txt` `Stable tag:`, `package.json` `version`. `/start-release` bumps all four; `/finish-release` verifies them.

## UI work

Before writing or reshaping **any** customer- or admin-facing UI — a template, a
React settings field, SCSS, an email — load the `frontend-design` skill first and
design against it. That applies to redesigns and to small additions, not just new
screens.

Every UI change must be responsive. Non-negotiable:

- No horizontal page overflow at **375px**. Check 375 / 768 / 1180 before calling
  it done.
- Wide content (tables, code, diagrams) scrolls inside its own
  `overflow-x: auto` container — the page body never scrolls sideways.
- Below ~720px, data tables either stack or scroll in their own box. A table that
  forces the page wide is a bug.
- Never let colour alone carry meaning. A credit and a debit must differ by sign
  or label as well as by red/green, especially where a stacked layout has dropped
  the column headers that used to say which was which.
- Text meets 4.5:1 contrast (3:1 for large text). Measure the muted greys against
  their actual background, not against white.
- Visible keyboard focus; honour `prefers-reduced-motion`.

Frontend templates render inside **unknown themes**. Themes routinely set
`display: block` on labels and `width: 100%` on inputs, which silently collapses
inline filter rows into full-width stacks — size such controls explicitly.
Don't load a webfont from a template; inherit the theme's faces.

Verify by looking, not by reasoning: render the template, screenshot it at a
desktop and a mobile width, and fix what you see.

## Agents and commands

Subagents live in `.claude/agents/`. Dispatch them by the **exact `name:` in their
frontmatter** — the filename and the name always match.

**Review agents — all strictly read-only.** They investigate and report; the main session
makes every edit, so the diff stays visible to the developer.

- `security-auditor` — attacker-perspective audit: caps, nonces, IDOR, SQLi, XSS, REST/AJAX
  authorization, privilege escalation.
- `wallet-ledger-auditor` — money correctness only, invariants L1–L14 (locks, TOCTOU,
  precision, currency, reversal symmetry, idempotency, migration safety).
- `wc-platform-reviewer` — WooCommerce platform (HPOS, order CRUD, status transitions,
  Action Scheduler, Blocks, gateway/refund/coupon/tax) and existing installations (existing
  rows and settings, hook/REST/template back-compat, upgrade vs fresh install, feature on/off).

**Non-review agents.**

- `terawallet-feature-architect` — design docs for non-trivial features. Read-only, no code.
- `terawallet-changelog-writer` — user-facing changelog copy + Upgrade Notice. Read-only.
- `wallet-qa-engineer` — **the one agent that writes files.** It authors adversarial PHPUnit
  tests into `tests/` and has its own live-environment safety gate. It is not a reviewer;
  invoke it deliberately, after a review has named the coverage gap.

**Reviewing a change.** Run `/review-plugin` — it resolves the diff, routes to only the auditors
the changed files actually need, dispatches them in parallel, dedupes the findings and
prints a gate verdict. It never edits, commits or merges. Use it on a feature branch while
the change is still small; `/finish-release` runs the same auditors again at the release
gate, but finding a blocking ledger defect there costs a release.

Routing, if you are dispatching by hand instead: REST/AJAX/caps/SQL → `security-auditor`;
money paths → `wallet-ledger-auditor`; order lifecycle, Blocks, migrations, settings keys,
templates, or any changed public hook signature or REST response shape →
`wc-platform-reviewer`. Always dispatch in parallel.

Release workflow: `/start-release` → `/finish-release` → `/build-dist`.
Per-release working notes live in `spec/<version>/` (git- and dist-ignored). At the start of a session on a release branch, read `spec/<version>/progress.md` first if it exists.

Deterministic checks live in CI (`.github/workflows/ci.yml`), not in the review prompts:
version agreement across all four locations, no direct writes to `woo_wallet_transactions`
outside the wallet class, `uninstall.php` dropping every table `install` creates, required
plugin headers, and the PHP floor agreeing across three files. Don't re-implement those as
review instructions — extend the `invariants` job instead.
