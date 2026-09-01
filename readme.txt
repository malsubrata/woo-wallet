=== Wallet for WooCommerce ===
Contributors: standalonetech, subratamal, moumitaadak
Tags: woocommerce wallet, cashback, store credit, partial payment, digital wallet
Requires PHP: 7.4
Requires at least: 6.4
Tested up to: 7.1
Stable tag: 1.6.14
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
*   🎂 **Milestone & Birthday Bonuses:** Credit the wallet when a customer's lifetime spend crosses a threshold you set, and once a year on their birthday.
*   📊 **Breakage & Aging Reports:** See how much wallet credit is about to expire, how much never will be spent, plus withdrawal and coupon reporting on the Wallet Dashboard.

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

= v1.6.14 (September 1, 2026) =
* New - New Statement tab replaces Transactions: pick a date range, see opening and closing balance with a running balance per row, and download or print it as CSV.
* Fix - Statement and wallet amounts now convert correctly on multi-currency stores; the closing balance again matches the wallet balance shown elsewhere.
* Fix - The wallet balance on Blocks checkout no longer breaks the payment method when it can't be read as a number.
* Fix - The settings screen reopens on the tab you left, on browsers where storage is unavailable.
* Tweak - The Transactions tab is gone; old /my-wallet/transactions/ links now open the wallet dashboard. Add-ons overriding templates/wc-endpoint-wallet.php should refresh their copy.
* Tweak - Developers: the `woo_wallet_transactons_datatable_columns` and `woo_wallet_transactons_datatable_row_data` filters are removed; use `woo_wallet_statement_columns`, `woo_wallet_statement_row_cells` and `woo_wallet_statement_row_columns` instead.
* Tweak - Developers: new `woo_wallet_statement_adjustments` and `woo_wallet_statement_opening_adjustment` filters let an add-on place a non-ledger balance movement on the statement as a dated line.
* Tweak - Redesigned the Upgrade to Pro page; pricing now reads "from $79" and links to the 5-site and 25-site licences.
* Performance - The wallet's frontend script is 445 KB smaller now that the old transaction table's charting library no longer loads on any store page.

[See changelog for all versions](https://raw.githubusercontent.com/malsubrata/woo-wallet/master/changelog.txt).

== Upgrade Notice ==

= 1.6.14 =
New Statement tab replaces Transactions with running balances; multi-currency amounts now display correctly. If your add-on overrides templates/wc-endpoint-wallet.php or uses the old transaction table filters, update it before upgrading.
