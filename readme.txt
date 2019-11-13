=== TeraWallet - For WooCommerce ===
Contributors: wcbeginner, subratamal, moumitaadak
Tags: woo wallet, woocommerce wallet, wp wallet, user wallet, refund, cashback, partial payment, wallet, wc wallet, woocommerce credits
Requires PHP: 5.6
Requires at least: 4.4
Tested up to: 5.3
Stable tag: 1.3.11
Donate link: https://www.paypal.me/SubrataMal941
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.0.html

A powerful, extendable WooCommerce wallet system which support payment, partial payment, cashback reward program as well as refund for your WooCommerce store.

== Description ==
> We are pleased to inform you that we have changed the plugin name, WooWallet is now TeraWallet.

TeraWallet allows customers to store their money in a digital wallet. The customers can use the wallet money for purchasing products from the store. The customers can add money to their wallet using various payment methods set by the admin. The admin can set cashback rules according to cart price or product. The customers will receive their cashback amount in their wallet account. The admin can process refund to customer wallet.

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

> Take a step forward and try our [demo](https://demo.woowallet.in/my-account/).

= Workflow of TeraWallet =
After the plugin installation, the admin needs to do the payment method configuration. Set the title and select allowed payments for adding money.
Now for enable cashback rules, navigate to WooWallet > Settings >  Credit. Now setup cashback rule according to your requirement. If cashback rule set to product wise then admin will have an option to add cashback rule for each product.
On the front-end, the customers can log in to the store and go to wallet page from My Account. Enter the amount to add and then complete the checkout process just like any other product purchase.

= Premium extensions =

- [Wallet Coupons](https://woowallet.in/product/woo-wallet-coupons/)
- [Wallet Withdrawal](https://woowallet.in/product/woo-wallet-withdrawal/)
- [Wallet Importer](https://woowallet.in/product/woo-wallet-importer/)
- [Wallet AffiliateWP](https://woowallet.in/product/woowallet-affiliatewp/)

= Translator Contributors =
- [#fa_IR](https://translate.wordpress.org/locale/fa/default/wp-plugins/woo-wallet) - [@rahimvaziri](https://wordpress.org/support/users/rahimvaziri/)
- [#es_ES](https://translate.wordpress.org/locale/es/default/wp-plugins/woo-wallet) - [@chipweb](https://wordpress.org/support/users/chipweb/)

== Installation ==

= Minimum Requirements =

* PHP version 5.2.4 or greater (PHP 5.6 or greater is recommended)
* MySQL version 5.0 or greater (MySQL 5.6 or greater is recommended)
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

If you get stuck, you can ask for help in the [WordPress Plugin Forum](https://wordpress.org/support/plugin/woo-wallet) or just email us at support@woowallet.in.

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
9. Wallet actions.

== Changelog ==
= 1.3.11 - 2019-11-13 =
* Add - Mini wallet shortcode.
* Fix - Cashback amount for product cart rule.
* Fix - Wallet endpoint issue for shortcode.

= 1.3.10 - 2019-09-11 =
* Add - Submit button at Wallet Top-Up widget.
* Fix - Cashback calculation issue for product category.
* Fix - Partial payment template.
* Fix - WooCommerce endpoint save issue.
* Tweak - Alter database column amount and balance.

= 1.3.9 - 2019-06-18 =
* New - Now admin can configure cashback for variable products.

= 1.3.8 - 2019-06-07 =
* Add - Role wise filter in wallet transaction page.
* Add - Hooks and Filters.

= 1.3.7 - 2019-05-05 =
* Add - Minimum transfer limit.
* Fix - Dokan withdrawal issue.

= 1.3.6 - 2019-04-19 =
* Updated - Plugin name change.

= 1.3.5 - 2019-04-18 =
* Add - Support for WC version 3.6.
* Add - Referral action.
* Fix - `wc_format_decimal` function use in partial payment.
* Remove - Deprecated WC functions.

= 1.3.4 - 2019-03-23 =
* Added - Compatibility with WooCommerce Germanized plugin.
* Add - Empty datatable info translation string
* Add - Fee amount in woowallet cart total function.
* Fix - Wallet transfer menu issue.

= 1.3.3 - 2019-03-04 =
* Fix - Plugin dependencies file.

= 1.3.2 - 2019-02-27 =
* Add - Now cart items will be restored after successful wallet top-up.
* Fix - Partial payment issues.
* Fix - Cashback calculation for variable product.
* Fix - Transaction date issue.
* Fix - Order by balance column in WooWallet balance details table.
* Tweak - Cashback logic.
* Tweak - Wallet funds transfer description.

= 1.3.1 - 2019-02-04 =
* Fix - Cashback issue.

= 1.3.0 - 2019-02-02 = 
* Add - Now cashback will be credited if admin create order for customer.
* Add - Added wallet top-up widget.
* Fix - Disable partial payment if user balance is zero.
* Fix - Multiple ajax call for partial payment.
* Fix - Delete transaction records upon deletion of user.
* Tweak - Daily visit.

[See changelog for all versions](https://raw.githubusercontent.com/malsubrata/woo-wallet/master/changelog.txt).

== Upgrade Notice ==

= 1.3 =