=== Wallet for WooCommerce ===
Contributors: standalonetech, subratamal, moumitaadak
Tags: woocommerce wallet, cashback, store credit, partial payment, digital wallet
Requires PHP: 7.4
Requires at least: 6.4
Tested up to: 6.9
Stable tag: 1.6.0
Donate link: https://donate.stripe.com/fZeaFydax6NNfjWeVc
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
    *   [WOOCS – WooCommerce Currency Switcher (FOX)](https://wordpress.org/plugins/woocommerce-currency-switcher/)
    *   [WPML Multilingual & Multi-Currency](https://wpml.org/) (WCML)
    *   [CURCY – Multi Currency for WooCommerce](https://wordpress.org/plugins/woo-multi-currency/) (VillaTheme)
    *   [Aelia Currency Switcher](https://aelia.co/shop/currency-switcher-woocommerce/)
    *   [YayCurrency – Multi-Currency Switcher](https://wordpress.org/plugins/yaycurrency/)
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

= 1.6.0 =
Security: closes an overdraft window in the debit balance gate and a duplicate-IPN double-credit window in the top-up callback — recommended upgrade for all sites. Also adds multi-currency provider adapters (WOOCS, WCML, CURCY, Aelia, YayCurrency + generic fallback), fixes ledger currency bugs in partial-payment and cashback flows, and extends the REST API with per-currency fields. Schema migration is automatic and idempotent — back up before upgrading.

= 1.5.18 =
Security fix for wallet transfer race conditions, new Go Pro admin page, and database query optimizations.
