=== Wallet for WooCommerce ===
Contributors: standalonetech, subratamal, moumitaadak
Tags: woocommerce wallet, cashback, store credit, partial payment, digital wallet
Requires PHP: 7.4
Requires at least: 6.4
Tested up to: 7.0
Stable tag: 1.6.3
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

✨ WooCommerce wallet with cashback rewards, store credit, partial payment & top-ups. Boost customer loyalty effortlessly.

== Description ==

Maximize convenience and savings for your customers with **Wallet for WooCommerce** (TeraWallet). This all-in-one digital wallet and store credit system is specifically designed to streamline the checkout process and boost customer loyalty.

TeraWallet empowers your customers to deposit funds into their personal accounts, transfer money to other users, and make purchases effortlessly using their wallet balance. By reducing the need for repeated payment detail entries, you provide a frictionless shopping experience that encourages repeat business.

Beyond core wallet functionality, TeraWallet features a robust **Cashback Rewards System**. Incentivize purchases by offering rewards based on cart totals, specific products, or categories. You can even convert WooCommerce coupons into wallet rewards, providing a unique way to drive engagement.

👉 **Try the live demo:** [https://demo.standalonetech.com/](https://demo.standalonetech.com/)
👉 **Read full documentation:** [https://docs.standalonetech.com/](https://docs.standalonetech.com/)
👉 **[Upgrade to Pro](https://standalonetech.com/product/woocommerce-wallet-pro/?utm_source=wordpress&utm_medium=plugin_page&utm_campaign=upgrade)** — unlock withdrawals, expiry, coupons, importer & AffiliateWP integration.

== ✨ Why choose Wallet for WooCommerce? ==

*   🚀 **Frictionless Checkout:** One-click payments via wallet balance reduce cart abandonment.
*   💰 **Automated Cashback:** Automated rewards keep customers coming back for more.
*   🏦 **Store Credit System:** Easily handle refunds by crediting the user's wallet instantly.
*   🔄 **Wallet Transfers:** Allow customers to share funds with friends and family.

== 🛠 Features ==

*   🏦 **Core Wallet Management:** A centralized ledger system that tracks every credit and debit with 100% accuracy using SQL-level locking to prevent race conditions.
*   💰 **Dynamic Cashback System:**
    *   **Cart-Wise:** Rewards based on the total order value.
    *   **Product-Wise:** Granular control over rewards for individual items.
    *   **Category-Wise:** Rewards based on product taxonomies.
*   💳 **Smart Checkout Options:**
    *   **Full Payment:** Pay for the entire order using the wallet gateway.
    *   **Partial Payment:** Use wallet balance for part of the total and pay the rest via other gateways (Stripe, PayPal, etc.).
    *   **Auto-Deduct:** Automatically apply available balance as a discount at checkout.
*   🔄 **User Empowerment:**
    *   **Wallet Top-ups:** Customers can add funds via their dashboard using any supported payment method.
    *   **Peer-to-Peer Transfers:** Securely send wallet balance to other registered users via email.
*   🎁 **Engagement Rewards:** Credit users for specific actions:
    *   New user registration bonus.
    *   Daily login rewards.
    *   Product review rewards.
*   🛠 **Admin Control Center:**
    *   View all user balances and transaction history.
    *   Manually adjust (credit/debit) any user's balance with detailed notes.
    *   Lock/Unlock user wallets for security and fraud prevention.
*   🔗 **Seamless Integrations:**
    *   Full support for WooCommerce Blocks checkout.
    *   Compatible with WPML and WooCommerce Subscriptions.
    *   Built-in support for Dokan, WCFM, and WCMarketplace.

*   🌍 **Multi-Currency Support:** First-class integrations with the most-used WooCommerce currency switchers. Wallet balances, top-ups, transfers, and cashback are all converted through the active provider's live rates.
    *   [YayCurrency – Multi-Currency Switcher](https://wordpress.org/plugins/yaycurrency/)
    *   [WOOCS – WooCommerce Currency Switcher (FOX)](https://wordpress.org/plugins/woocommerce-currency-switcher/)
    *   [WPML Multilingual & Multi-Currency](https://wpml.org/) (WCML)
    *   [CURCY – Multi Currency for WooCommerce](https://wordpress.org/plugins/woo-multi-currency/) (VillaTheme)
    *   [Aelia Currency Switcher](https://aelia.co/shop/currency-switcher-woocommerce/)
    *   **Generic fallback** for any other plugin that filters `woocommerce_currency` — active-currency detection still works, conversion falls open to the stored amount with an audit-log warning.

== 🚀 Pro Features ==

**[⭐ Upgrade to Pro](https://standalonetech.com/product/woocommerce-wallet-pro/?utm_source=wordpress&utm_medium=plugin_page&utm_campaign=upgrade)** to unlock advanced wallet features and specialized integrations:

*   💸 **Wallet Withdrawal:** Allow customers to request withdrawals from their wallet balance to their bank or other payment methods.
*   ⌛ **Wallet Expiry:** Set expiration dates for wallet balance or cashback to encourage timely spending.
*   🎟️ **Wallet Coupons:** Create exclusive coupons that can only be redeemed into the user's wallet.
*   📥 **Wallet Importer:** Easily bulk import wallet balances and transaction history from CSV files.
*   🤝 **AffiliateWP Integration:** Automatically credit affiliate commissions directly to the user's wallet.

== Installation ==

= Minimum Requirements =

* PHP 7.4 or greater is required (PHP 8.0 or greater is recommended)
* MySQL 5.6 or greater, OR MariaDB version 10.1 or greater, is required
* WordPress 6.0 or greater is required
* WooCommerce 7.2 or greater is required

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don't need to leave your web browser. To do an automatic install of WooCommerce Wallet Payment, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type "Wallet for WooCommerce" and click Search Plugins. Once you've found the plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

If on the off-chance you do encounter issues with the wallet endpoints pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.

= Important =

A hidden "Wallet Topup" product is automatically created upon activation. Ensure it remains **Published** and **Private**.

== Frequently Asked Questions ==

= How does wallet payment work? =
Wallet payment acts as a native WooCommerce gateway. Customers with sufficient balance can select "Wallet" at checkout to pay for their order instantly.

= Does it support partial payment? =
Yes! If enabled in settings, customers can use their wallet balance to pay for a portion of the order and cover the remainder with another gateway like Stripe or PayPal.

= When is cashback applied? =
Cashback is triggered by order status changes. You can configure which status (e.g., 'Completed' or 'Processing') triggers the reward in the plugin settings.

= Why is the wallet not visible at checkout? =
Ensure the Wallet gateway is enabled in **WooCommerce > Settings > Payments**. Also, check if "Hide if empty" is enabled in TeraWallet settings if the user has a zero balance.

= Where can I get support? =
You can ask for help in the [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-wallet) or email us at support@standalonetech.com.

= Where is the REST API documentation? =
You can find the documentation for our [Wallet REST API here](https://github.com/malsubrata/woo-wallet/wiki/API-V3).

== Screenshots ==

1. User wallet dashboard page.
2. Wallet topup page.
3. Transfer wallet balance.
4. Transaction details page.
5. Admin wallet details page.
6. Admin adjust wallet balance.
7. Admin wallet transaction details page.
8. Wallet payment gateway.
9. WooCommerce refund.
10. Wallet actions.

== Changelog ==

= v1.6.3 (May 30, 2026) =
– **New:-** Transaction category is now a first-class indexed column on `woo_wallet_transactions` (was previously only on transaction meta). Adds `(user_id, category, deleted)` index for cheap admin filters/aggregations.
– **New:-** Filterable PHP registry of canonical categories (`woo_wallet_get_transaction_types`, filter `woo_wallet_transaction_types`) so marketplace and addon plugins can register their own kinds.
– **New:-** Admin "Transaction descriptions" settings tab with per-category description templates (tokens: `{order_id}`, `{amount}`, `{user_name}`, `{currency}`, `{original_details}`). When a template is set, it replaces the system-generated description on new transactions.
– **New:-** Transaction CSV export now includes the `category` column.
– **Tweak:-** Ledger writers (`credit`, `debit`, `transfer`) accept `$args['category']`; legacy `$args['for']` continues to work and is normalised (`credit_purchase` → `topup`, `purchase` → `partial_payment`). Transfer legs are now correctly tagged `transfer`.
– **Tweak:-** Admin balance-details columns and the customer/admin REST `category` filter now read the column directly instead of joining the meta table.
– **Tweak:-** The Wallet > Users bulk Credit / Debit actions now collect amount and description in a WCBackboneModal popup (consistent with the existing Delete Log dialog) instead of inline form fields. Bulk admin adjustments are tagged `category='adjustment'`.
– **Fix:-** Ledger amounts are now quantized to the store's price decimals on write, and the spendable balance is floored (never rounded up) on read — closing a rounding loophole where sub-cent "dust" from multicurrency conversion (e.g. a raw balance of 124.12511111 shown as 124.13) could not be debited, blocking wallet-gateway payments and silently breaking partial payments.
– **Security:-** Per-category description templates are now stripped of HTML before the rendered text is stored on a transaction, so a template using the `{user_name}` or `{original_details}` token can no longer become a stored-XSS vector via an attacker-controlled display name or details string.
– **Security:-** Transaction category slugs passed to the ledger writers are now validated against the registered category set; an unregistered slug collapses to `other` instead of being written verbatim, preventing third-party code from polluting the `category` column with unfilterable values.

= v1.6.2 (May 25, 2026) =
– **Security:-** Admin bulk credit/debit (`POST /terawallet/v1/admin/transactions/bulk`) now records a per-user idempotency sub-key. A retry after a mid-loop process death no longer re-credits users who already received the credit on the first attempt.
– **Security:-** Admin bulk credit/debit now forwards the request `currency` argument, fixing a multi-currency bug where the stored amount depended on the admin's active currency switcher state.
– **Security:-** Cancelled-order partial-payment refund is now wrapped in a per-order `GET_LOCK`, so two concurrent cancel webhooks for the same order can no longer double-refund the wallet.
– **Security:-** Section-heading admin settings rows are now rendered with the label as a plain text node, and both label and hint are sanitised with `wp_kses_post()` on the REST response — closing a stored-XSS vector exploitable by a third-party plugin hooking the `woo_wallet_action_*_form_fields` filter chain.
– **Security:-** `WooWallet_Referral_Service::record_signup()` now serialises its existence check and INSERT under a per-referred-user `GET_LOCK`, preventing two concurrent signup-drain hooks from writing duplicate pending sign-up rows that could later be credited twice.
– **Fix:-** New-user-registration and referral signup bonuses are now credited for users created via SSO / SAML, social login, the REST API, WP-CLI or any programmatic `wp_insert_user()`. A new early `user_register` capture (`Woo_Wallet_Signup_Handler`) defers crediting until the earning-action registry is loaded, so signups created before `woocommerce_init` are no longer missed.
– **Fix:-** Referral visit and signup bonuses are now credited in the store base currency, matching the amount entered in settings — no more unwanted active-currency conversion in multi-currency stores.
– **Fix:-** The referral "Signups" limit now counts credited signups instead of registrations, so a referred customer who never completes the minimum spend no longer consumes a limit slot.
– **Fix:-** Crediting a referral signup whose referrer account was deleted no longer credits user ID 0.
– **Tweak:-** Referral "Minimum Order Amount" setting renamed to "Minimum Spend" with a clearer description — it gates on the referred customer's total lifetime spend.
– **Tweak:-** Loader for action/REST classes now hooks `woocommerce_init` instead of `init`, removing the WooCommerce-existence guard while keeping both the WC-inactive fatal and the WP 6.7 translation notice fixed.
– **Tweak:-** Redesigned the Referrals action settings for clarity — labelled fields, section headings, inline help text, and side-by-side limit controls with the cap hidden until a limit period is chosen.
– **New:-** Referral activity is now recorded in a dedicated `woo_wallet_referrals` database table — one row per visitor or sign-up referral, with status, reward amount and the currency it was credited in. Replaces the scattered `_woo_wallet_referring_*` user meta and gives referrals a full audit trail.
– **New:-** The customer Referrals page now shows a referral history — who was referred, the reward type, amount, status (pending / credited / rejected) and date — alongside a converted earnings summary.
– **New:-** New admin Referral Report screen (TeraWallet → Referral Report) listing every referral with referrer / type / status / date-range filters, a store-wide summary header and a filtered CSV export.
– **Fix:-** Referral earnings shown to customers now carry their currency and reconvert to the active storefront currency on a currency switch — previously the total was a raw untagged number that could display incorrectly and never reconverted.

= v1.6.1 (May 20, 2026) =
– **Security:-** Wrapped `wallet_cashback()` in a per-order `GET_LOCK` mirroring the 1.6.0 `wallet_credit_purchase` fix, so duplicate `processing`/`completed` status transitions or replayed gateway webhooks can no longer double-credit cashback. Order meta now stores an array of credited transaction ids so historical doubles are recoverable.
– **Security:-** Cashback clawback on cancellation no longer fails silently when the customer has spent the credit. Default policy: debit whatever balance remains and log the gap to a new `_cashback_unreversed_amount` order meta + order note. Opt-in setting `cashback_clawback_allow_negative` allows sites to drive the wallet negative for exact reversal.
– **Security:-** The Delete Logs bulk operation is now wrapped in `GET_LOCK('woo_wallet_lock_user_<id>')` + `START TRANSACTION`, matching `recode_transaction()` and `transfer()`. Closes a race where a concurrent top-up landing between the pre-delete `SUM` and the post-delete re-credit was silently lost.
– **New:-** New refund handler on `woocommerce_order_refunded` clawing back cashback prorated against the refunded fraction. Off by default for upgrade safety; enable in Settings → Wallet Credit → Refund clawback. New filter `woo_wallet_cashback_refund_clawback_amount` for marketplace overrides.
– **New:-** New `max_cashback_scope` setting (`per_item` | `per_order`). Defaults to `per_order` on fresh installs so the global cap applies once per cart; existing sites are migrated to `per_item` to preserve current behaviour.
– **New:-** REST transactions endpoints (`/terawallet/v1/me/transactions` and `/wc/v3/wallet/transactions`) now expose a typed `category` field (`topup`, `cashback`, `cashback_adjustment`, `cashback_refund`, `partial_payment`, `transfer`, `refund`, `adjustment`, `other`) and accept a `category=` query argument.
– **New:-** Cashback expiry seam: new filter `woo_wallet_cashback_expiry_timestamp` lets Pro and addons mark a cashback row as expiring on a given timestamp; the value is stored in transaction meta and projected as `cashback_expires_at` in the REST response. Core does not enforce expiry.
– **New:-** Unified the React Actions tab with the standard settings flow. Each earning action (daily visits, new registration, product review, referrals, sell-content) is now rendered as a grouped collapsible card inside the same Panel component that powers General and Credit Options, and saves through `POST /wc/v3/wallet/settings/section` instead of a dedicated `/action` endpoint.
– **New:-** Action settings are now persisted in a single `_wallet_settings_actions` option with namespaced keys (`{action_id}__{field}`) — readable via `woo_wallet_get_setting( '_wallet_settings_actions', 'daily_visits__amount' )`. An idempotent 1.6.1 migration copies pre-existing per-action options (`woo_wallet_daily_visits_settings`, etc.) into the merged row; the legacy rows are kept in place as a rollback safety net.
– **New:-** Delete Logs bulk action on the TeraWallet → Wallet admin screen now opens a modal that lets the admin pick the **delete mode** (Soft — recoverable, sets `deleted=1`; Hard — permanent `DELETE FROM`) and the **balance handling** (Keep — insert a single balancing credit/debit so the user's balance is unchanged; Wipe — let the balance settle to 0). Previously the action was hard-wired to "hard-delete everything + re-credit positive balance," with no admin choice.
– **New:-** New helper `woo_wallet_purge_user_transactions( $user_id, $delete_mode, $balance_handling )` exposes the same flow to extensions. New action `woo_wallet_user_transactions_purged` fires on completion. Legacy filter `woo_wallet_credit_user_after_delete_log` is still honored when `$balance_handling === 'keep'` for back-compat.
– **Fix:-** Order-side cashback recompute (`recalculate_order_cashback`) now writes a compensating `cashback_adjustment` ledger row instead of mutating the original cashback row's `amount` in place. Restores the append-only ledger invariant and keeps the `_current_woo_wallet_balance` cache in sync. Removed the noisy `woocommerce_order_after_calculate_totals` recompute hook.
– **Fix:-** Multi-currency parity for order-side cashback: `min_cart_amount` and `max_cashback_amount` are now converted from base to the order's currency on `woo_wallet_form_order_cashback_amount` (matches the existing cart-side filter). Non-base orders no longer compute against raw base-currency settings.
– **Fix:-** Coupon cashback amount is now recomputed at credit time from the live order's coupons rather than trusting the checkout-time meta. Order edits no longer desync stored coupon cashback. The legacy `discount_total`/`total` rewrite is replaced with a non-discount fee item; gated by `woo_wallet_legacy_coupon_cashback_total_mutation` so existing reports are not affected on upgrade.
– **Fix:-** Negative balances are now preserved symmetrically when "Keep balance" is chosen for Delete Logs — a debt of `-25` inserts a balancing **debit** of `25` instead of being silently zeroed out by the old `if ( $current_balance && ... )` + positive-only `credit()` path.
– **Tweak:-** New filter `woo_wallet_cashback_clawback_strategy` lets sites override the partial / full-or-skip / force-negative reversal policy.
– **Tweak:-** Custom `WooWalletAction` subclasses keep working unchanged: `init_settings()` now reads the merged option first and falls back to the legacy per-action option, so third-party actions that have not migrated still load their settings correctly.
– **Tweak:-** `POST /wc/v3/wallet/settings/action` is kept as a thin deprecated shim that delegates to `/section` for one minor cycle; the React UI no longer calls it.
– **Tweak:-** Removed dead pre-React rendering code: legacy server-rendered form callbacks (`show_navigation`, `show_forms`, all `callback_*` field renderers) in the settings API helper, plus the legacy `display_action_settings` / `display_actions_table` handlers and the orphan `WooWalletAction::admin_options()` form renderer. The `?page=woo-wallet-actions` redirect shim remains for old bookmarks.
– **Tweak:-** Database migration `1.6.1` is idempotent — fresh installs default to per-order cap scope; upgraded installs preserve per-item cap scope and legacy coupon-cashback total mutation behaviour, and per-action option rows are merged into `_wallet_settings_actions` without removing the legacy rows.

= v1.6.0 (May 04, 2026) =
– **New:-** add new settings fields and hooks for Woo Wallet
– **New:-** Implemented various input fields including AttachmentField, CheckboxField, ColorField, HtmlField, MultiSelectField, MulticheckField, NumberField, PasswordField, RadioField, SelectField, TextField, and TextareaField.
– **New:-** Created a custom hook `useSettings` for managing settings state, loading, and saving.
– **New:-** Added a field types registry to manage different input types dynamically.
– **New:-** Introduced CSS styles for the new settings interface, ensuring compatibility with light and dark themes.
– **New:-** Integrated REST API calls for fetching and saving settings data.
– **New:-** Multi-currency provider abstraction with first-class adapters for WOOCS/FOX, WPML/WCML, CURCY, Aelia, and YayCurrency, plus a generic fallback for any other plugin that filters `woocommerce_currency`.
– **New:-** Per-row currency audit columns (`original_amount`, `original_currency`, `original_rate`, `mode`) and a `(user_id, currency, deleted)` index on the wallet transactions table for accurate historical reporting.
– **New:-** Additive REST surface: `/terawallet/v1/me/balance` now returns `base_currency`, `base_amount`, `mode`, and a `balances[]` array; `/me/transfer` and `/me/topup` accept an optional `currency` argument; `/wc/v3/wallet` exposes the new audit fields and a `currency` query filter.
– **New:-** Admin endpoint `GET /wc/v3/wallet/multicurrency` and a Currency Mode panel in the React settings app that surfaces the active provider, base/active currencies, and the effective ledger mode.
– **Security:-** Hardened the debit balance gate in `recode_transaction()` to read the raw ledger SUM directly instead of the filtered `get_wallet_balance()` value. Closes an overdraft window where any third-party hook on `woo_wallet_current_balance` (credit-expiry, redeemed-totals plugins) could inflate the perceived balance and let a user debit into negative territory.
– **Security:-** Wrapped `wallet_credit_purchase()` in a per-order `GET_LOCK` with re-fetch inside the lock so duplicate gateway IPN deliveries (PayPal/Stripe webhook retries) can no longer both pass the `_wc_wallet_purchase_credited` meta guard and double-credit the wallet.
– **Fix:-** Partial-payment debit now records the order currency, matching the cancellation refund — no more debit/refund pairs landing in different currencies.
– **Fix:-** Cashback debit on order cancellation now passes the order currency, eliminating a second source of mixed-currency rows.
– **Fix:-** Mode-aware balance reads — single-base sites continue to sum normalized rows; per-currency sites filter by the active currency, so a user with EUR and USD activity no longer sees an undefined-currency total.
– **Tweak:-** Top-up orders honour the requested currency end-to-end: `WooWallet_Topup_Service::create_order()` calls `$order->set_currency()` before totals are calculated, so the gateway charges in the requested currency.
– **Tweak:-** `woo_wallet_wc_price_args()` is now mode-aware; in per-currency mode it defaults to the active provider's currency while explicit per-row currency overrides still win.
– **Tweak:-** Database migration `1.6.0` is idempotent — fresh installs and upgrades both land on the new schema; pre-1.6 rows keep working with `original_*` NULL and `mode=0`.

= v1.5.18 (April 23, 2026) =
– **New:-** Added Go Pro admin page showcasing Pro features with a Free vs Pro comparison and license activation UI, replacing the legacy Extensions page.
– **Security:-** Implement idempotency key for wallet transfers to prevent duplicate submissions and TOCTOU race condition vulnerabilities.
– **Tweak:-** Enhanced partial payment tooltip to provide a clearer breakdown of amounts debited from the wallet and paid via other gateways.
– **Tweak:-** Enhance database schema and optimize wallet transaction queries for improved performance.
– **Tweak:-** Improved CSV exporter for wallet transactions with better query handling.
– **Tweak:-** Update Pro upgrade URLs with UTM parameters for better tracking.

= v1.5.17 (March 12, 2026) =
– **Fix:-** Remove space in limit parameter for wallet transactions query.
– **Fix:-** Simplify wallet transactions query preparation by removing redundant parameter checks.

= v1.5.16 (February 12, 2026) =
– **Tweak:-** Enhance SQL query construction for wallet transactions with improved safety and readability.
– **Tweak:-** Remove return type declarations for compatibility and enhance permission checks in content handling.
– **Tweak:-** Update version retrieval for script and style assets.
– **Tweak:-** Enhance partial payment validation in frontend.
– **Tweak:-** Add checks for zero currency rates in multi-currency conversion methods
– **Tweak:-** Database Lock to serialize requests for the same user.
– **Tweak:-** Adjust wallet transfer logic to debit before crediting, ensuring proper transaction flow.

= v1.5.15 (December 10, 2025) =
– **New:-** User wallet dashboard design.
– **Tweak:-** Replace thickbox with wc backbone modal.
– **Fix:-** Removed moment js and used WordPress core momentjs Library.
– **Added:-** WordPress 6.9 support.

= v1.5.14 (October 08, 2025) =
– **Fix:-** RTL CSS issue.

= v1.5.13 (August 21, 2025) =
– **Fix:-** PHP warning.

= v1.5.12 (August 21, 2025) =
– **New:-** Date range filter in wallet transaction page.
– **New:-** Settings panel design.
– **New:-** Now site admin can enable/disable wallet topup.
– **Fix:-** Partial payment issue.
– **Fix:-** Cashback display issue on cart and checkout page.

= v1.5.11 ( May 08, 2025) =
– **Fix:-** Text Domain loading issue.

= v1.5.10 ( December 12, 2024) =
– **Fix:-** Refund issue.

== Upgrade Notice ==

= 1.6.1 =
Security: closes a race in the Delete Logs bulk action and prevents double-credit of cashback on duplicate order-status transitions / replayed webhooks — recommended upgrade for all sites. Two new opt-in cashback settings added: enable *Refund clawback* (Settings → Wallet Credit) to claw back cashback when orders are refunded; enable *Allow negative clawback* to permit exact reversal when the customer has already spent the credit. The `max_cashback_scope` setting defaults to `per_order` on fresh installs; upgraded sites are automatically migrated to `per_item` to preserve existing behaviour. The React Actions tab is now part of the standard settings flow and persists to a single `_wallet_settings_actions` option (legacy per-action option rows are kept as a rollback safety net). The Delete Logs bulk action now opens a modal so admins can pick delete mode (soft / hard) and balance handling (keep / wipe). Schema migration is automatic and idempotent — back up before upgrading.

= 1.6.0 =
Security: closes an overdraft window in the debit balance gate and a duplicate-IPN double-credit window in the top-up callback — recommended upgrade for all sites. Also adds multi-currency provider adapters (WOOCS, WCML, CURCY, Aelia, YayCurrency + generic fallback), fixes ledger currency bugs in partial-payment and cashback flows, and extends the REST API with per-currency fields. Schema migration is automatic and idempotent — back up before upgrading.

= 1.5.18 =
Security fix for wallet transfer race conditions, new Go Pro admin page, and database query optimizations.
