=== TeraWallet – Best WooCommerce Wallet System With Cashback Rewards, Partial Payment, Wallet Refunds ===
Contributors: standalonetech, subratamal, moumitaadak
Tags: woo wallet, woocommerce wallet, digital wallet, user wallet, refund, cashback, partial payment, wallet, wc wallet, woocommerce credits
Requires PHP: 7.2
Requires at least: 5.8
Tested up to: 6.2
Stable tag: 1.4.7
Donate link: https://donate.stripe.com/fZeaFydax6NNfjWeVc
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A powerful, extendable WooCommerce wallet system which support payment, partial payment, cashback reward program as well as refund for your WooCommerce store.

== Description ==

TeraWallet is a powerful digital wallet and credit system designed for WooCommerce. With TeraWallet, you can allow your customers to deposit funds into their accounts, transfer funds to other users, and pay for purchases using their wallet balance.

In addition to its core wallet functionality, TeraWallet also includes a cashback rewards system that lets you offer discounts and rewards to your customers for their purchases. You can set cashback rates for individual products or categories, and even offer bonus cashback for specific promotions or events.

TeraWallet is fully customizable, with TeraWallet, you can create a seamless and convenient payment experience for your customers while also boosting loyalty and repeat purchases through cashback rewards. Try TeraWallet today and streamline your WooCommerce payment system!

[youtube https://www.youtube.com/watch?v=Fnpp8qxAWBw]

= Use case of TeraWallet =
With this extension, the customers won't have to fill in the payment details every time. They can simply log in and pay for products using the wallet money. The customers will also get the advantage for earning cashback using the wallet money. The admin can process refund to the customer wallet. 

= Features of TeraWallet =
- Wallet system works just like any other payment method.
- Set wallet system payment method title for the front-end.
- The customers can use various payment methods to add money.
- The admin can process refund using the wallet money.
- Customers will earn cashback according to cart price, product or product category wise.
- Customers can made partial payment.
- Set cashback amount calculation using fixed or percent method.
- Admin can export users wallet transactions.
- Admin can setup low wallet balance notification email.
- Admin can lock / unlock any user wallet.
- From the backend, the admin can view the transaction history.
- Customers receive notification emails for every wallet transaction.
- The admin can adjust the wallet amount of any customer from the backend.
- Users can transfer wallet amount to other user.
- Shortcode `woo-wallet` which will display user wallet page.
- Built with a REST API
- Convert WooCommerce coupon into cashback.
- Support WordPress Multisite Network
- Supports multiple languages translations.
- Supports WooCommerce Subscriptions.
- Supports WooCommerce Multivendor Marketplace by WC Lovers.
- Supports WC Marketplace.
- Supports Dokan Multivendor Marketplace.

> Take a step forward and try our [demo](https://standalonetech.com/).

= Workflow of TeraWallet =
After the plugin installation, the admin needs to do the payment method configuration. Set the title and select allowed payments for adding money.
Now for enable cashback rules, navigate to WooWallet > Settings >  Credit. Now setup cashback rule according to your requirement. If cashback rule set to product wise then admin will have an option to add cashback rule for each product.
On the front-end, the customers can log in to the store and go to wallet page from My Account. Enter the amount to add and then complete the checkout process just like any other product purchase.

= Premium extensions =

- [Wallet Coupons](https://standalonetech.com/product/wallet-coupons/)
- [Wallet Withdrawal](https://standalonetech.com/product/wallet-withdrawal/)
- [Wallet Importer](https://standalonetech.com/product/wallet-importer/)
- [Wallet AffiliateWP](https://standalonetech.com/product/wallet-affiliatewp/)

= Translator Contributors =
- [#fa_IR](https://translate.wordpress.org/locale/fa/default/wp-plugins/woo-wallet) - [@rahimvaziri](https://wordpress.org/support/users/rahimvaziri/)
- [#es_ES](https://translate.wordpress.org/locale/es/default/wp-plugins/woo-wallet) - [@chipweb](https://wordpress.org/support/users/chipweb/)

== Installation ==

= Minimum Requirements =

* PHP version 5.2.4 or greater (PHP 5.6 or greater is recommended)
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)
* WordPress 5.8+
* WooCommerce 6.0+

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To do an automatic install of WooCommerce Wallet Payment, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce Wallet Payment” and click Search Plugins. Once you’ve found our WooCommerce Wallet Payment plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking “Install Now”.

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your webserver via your favourite FTP application. The WordPress codex contains [instructions on how to do this here](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

If on the off-chance you do encounter issues with the wallet endpoints pages after an update you simply need to flush the permalinks by going to WordPress > Settings > Permalinks and hitting 'save'. That should return things to normal.

== Frequently Asked Questions ==

= Does this plugin work with newest WP version and also older versions? =

Yes, this plugin works fine with WordPress 4.9, It is also compatible for older WordPress versions upto 4.4.

= Up to which version of WooCommerce this plugin compatible with? =

This plugin is compatible with the latest version of WooCommerce.

= Will WooCommerce Wallet work with WordPress multisite network? =

Yes, WooCommerce Wallet plugin is fully compatible with Wordpress multisite.

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-wallet) or just email us at support@standalonetech.com.

= Where can I report bugs or contribute to the project? =

Bugs can be reported either in our support forum or preferably on the [GitHub repository](https://github.com/malsubrata/woo-wallet/issues).

= Where can I find the REST API documentation? =

You can find the documentation of our [Wallet REST API Docs](https://github.com/malsubrata/woo-wallet/wiki/API-V3).

= This plugin is awesome! Can I contribute? =

Yes you can! Join in on our [GitHub repository](https://github.com/malsubrata/woo-wallet) :)

== Screenshots ==

1. User wallet page.
2. Transfer wallet balance.
3. View transaction details.
4. All user balance details.
5. Admin view transaction details.
6. Admin adjust wallet balance.
7. WooCommerce wallet payment gateway.
8. WooCommerce refund.
9. Wallet actions.

== Changelog ==
= 1.4.7 - 2023-04-05 =
* Fix - Refund issue.
* Fix - WooCommerce add to cart notice.
* Added - WP 6.2 support.

= 1.4.6 - 2023-01-19 =
* Fix - Duplicate order issue and negative wallet balance.
* Fix - Partial payment issue for draft orders.

= 1.4.5 - 2022-12-24 =
* Add - Hooks in referral action.
* Fix - Transaction exporter.

= 1.4.4 - 2022-11-14 =
* Fix - Security issue on the function lock_unlock_terawallet.

= 1.4.3 - 2022-11-11 =
* Fix - Datatable ajax issue.

= 1.4.2 - 2022-11-11 =
* Fix - Mini wallet nav menu location.

= 1.4.1 - 2022-11-11 =
* Fix - Fix Cannot uncheck checkbox issue in plugin settings page.

= 1.4.0 - 2022-11-4 =
* Fix - Plugin CSRF issue ( Thanks Muhammad Daffa ).
* Add - Compatibility with WP 6.1

[See changelog for all versions](https://raw.githubusercontent.com/malsubrata/woo-wallet/master/changelog.txt).

== Upgrade Notice ==

= 1.3 =