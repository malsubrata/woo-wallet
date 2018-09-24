=== WooCommerce Wallet - credit, cashback, refund system ===
Contributors: subratamal, eruditiontec
Tags: woo wallet, woocommerce wallet, wp wallet, user wallet, refund, cashback, partial payment, wallet, woocommerce wallet, wc wallet, woocommerce credits
Requires PHP: 5.6
Requires at least: 4.4
Tested up to: 4.9
Stable tag: 1.2.4
Donate link: https://www.paypal.me/SubrataMal941
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A powerful, extendable WooCommerce wallet system which support payment, partial payment, cashback reward program as well as refund for your WooCommerce store.

== Description ==

WooCommerce Wallet plugin allows customers to store their money in a digital wallet. The customers can use the wallet money for purchasing products from the store. The customers can add money to their wallet using various payment methods set by the admin. The admin can set cashback rules according to cart price or product. The customers will receive their cashback amount in their wallet account. The admin can process refund to customer wallet.

[youtube https://www.youtube.com/watch?v=WBCBfdGe6OI]

= Use case of WooCommerce Wallet plugin =
With this extension, the customers won't have to fill in the payment details every time. They can simply log in and pay for products using the wallet money. The customers will also get the advantage for earning cashback using the wallet money. The admin can process refund to the customer wallet. 

= Features of WooCommerce Wallet plugin =
- Wallet system works just like any other payment method.
- Set wallet system payment method title for the front-end.
- The customers can use various payment methods to add money.
- The admin can process refund using the wallet money.
- Customers will earn cashback according to cart price, product or product category wise.
- Customers can made partial payment.
- Set cashback amount calculation using fixed or percent method.
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
- Supports WC Marketplace.
- Supports Dokan Multivendor Marketplace.

> Take a step forward and try our [demo](http://demo.woowallet.in/my-account/).

= Workflow of WooCommerce Wallet plugin =
After the plugin installation, the admin needs to do the payment method configuration. Set the title and select allowed payments for adding money.
Now for enable cashback rules, navigate to WooWallet > Settings >  Credit. Now setup cashback rule according to your requirement. If cashback rule set to product wise then admin will have an option to add cashback rule for each product.
On the front-end, the customers can log in to the store and go to wallet page from My Account. Enter the amount to add and then complete the checkout process just like any other product purchase.

= Premium extensions =

- [WooWallet Withdrawal](https://woowallet.in/product/woo-wallet-withdrawal/)
- [WooWallet Importer](https://woowallet.in/product/woo-wallet-importer/)

= Translator Contributors =
- [#fa_IR](https://translate.wordpress.org/locale/fa/default/wp-plugins/woo-wallet) - [@rahimvaziri](https://wordpress.org/support/users/rahimvaziri/)
- [#es_ES](https://translate.wordpress.org/locale/es/default/wp-plugins/woo-wallet) - [@chipweb](https://wordpress.org/support/users/chipweb/)

== Installation ==

= Minimum Requirements =

* PHP version 5.2.4 or greater (PHP 5.6 or greater is recommended)
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)
* Some payment gateways require fsockopen support (for IPN access)
* WordPress 4.4+
* WooCommerce 3.0+

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

If you get stuck, you can ask for help in the [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-wallet) or just email us at m.subrata1991@gmail.com.

= Where can I report bugs or contribute to the project? =

Bugs can be reported either in our support forum or preferably on the [GitHub repository](https://github.com/malsubrata/woo-wallet/issues).

= Where can I find the REST API documentation? =

You can find the documentation of our [Wallet REST API Docs](https://github.com/malsubrata/woo-wallet/wiki/WooCommerce-Wallet-REST-API).

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

== Changelog ==
= 1.2.4 - 2018-09-24 =
* Fix - Fatal error on WordPress Menu page.
* Fix - Hide partial payment option for guest user.
* Fix - Remaining balance display at email template.

= 1.2.3 -2018-09-24 =
* Fix - Partial payment.
* Fix - Dokan withdrawal.
* Add - Dokan auto withdrawal.
* Add - Wallet icon file.
* Add - Wallet actions (Admin can now set action for wallet credit).

= 1.2.2 - 2018-08-17 =
* Fix - Partial payment issue.

= 1.2.1 - 2018-08-15 =
* Add - Screen option at transaction details page.
* Tweak - Partial payment using WooCommerce fee.

= 1.2.0 - 2018-07-26 =
* Add - Extension page.
* Fix - Refund using default WooCommerce refund function.
* Dev - details variable in email.
* Dev - Added filter `woo_wallet_rest_check_permissions`, `woo_wallet_process_cashback_status`, `woo_wallet_wc_price_args`.

[See changelog for all versions](https://raw.githubusercontent.com/malsubrata/woo-wallet/master/changelog.txt).

== Upgrade Notice ==

= 1.2 =