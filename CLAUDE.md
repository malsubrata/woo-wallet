# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

TeraWallet (slug `woo-wallet`, formerly WooWallet) is a WooCommerce wallet plugin: digital ledger, top-ups via WooCommerce gateways, partial payments, cashback, peer-to-peer transfers, marketplace integrations. WordPress 6.4+, PHP ≥5.6, requires WooCommerce.

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
framework), but **no CI config in-tree** — tests run locally only.

```bash
composer install                  # install dev dependencies (PHPUnit, wp-phpunit)
bash bin/install-wp-tests.sh      # create the wp_terawallet_tests DB + wp-tests-config.php (one-time)
composer test                     # run the suite
```

Tests live in `tests/` (`tests/bootstrap.php` boots real WP + WooCommerce
against a dedicated test database; cases are `tests/test-*.php`). Each test
runs inside a rolled-back DB transaction, so the live site database is never
mutated. The suite currently covers the ledger core (credit/debit/balance,
transfer) and the earning-actions base-currency behaviour; extend it when you
touch money-moving code. Don't claim "tests pass" without running `composer
test` and reading the output.

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

Two custom tables, created/updated by `Woo_Wallet_Install` (`includes/class-woo-wallet-install.php`):
- `{prefix}woo_wallet_transactions` — append-only credit/debit rows, balance is `SUM(credit − debit) WHERE deleted=0`. The `_current_woo_wallet_balance` user meta is a cache.
- `{prefix}woo_wallet_transaction_meta`

Schema migrations are version-keyed in the `$db_updates` array (`class-woo-wallet-install.php:20`); the callbacks live in `includes/helper/woo-wallet-update-functions.php`. **When you bump `WOO_WALLET_PLUGIN_VERSION` and need a schema change, register both the version key and the callback.**

Money-moving paths (`Woo_Wallet_Wallet::recode_transaction`, `::transfer`) wrap `START TRANSACTION` and serialize concurrent writers using MySQL `GET_LOCK('woo_wallet_lock_user_<id>', timeout)`. `transfer()` acquires both user locks in **deterministic min/max id order** to avoid the A→B / B→A deadlock. The pre-debit balance check uses the raw `SUM(...)` query, **not** `apply_filters( 'woo_wallet_current_balance', ... )` — third-party balance filters mutate state via the post-commit `woo_wallet_transaction_recorded` hook (which fires after locks release), so filtering inside the lock would reintroduce a TOCTOU window. Keep that distinction when editing concurrency-sensitive code; the inline comment in `class-woo-wallet-wallet.php:308` documents the reasoning.

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
- `package.json` `version` is independent of the PHP plugin version — historically lags behind. The PHP version is the source of truth for releases.
