<?php
/**
 * The Template for mini wallet
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/mini-wallet.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.0.8
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<li class="menu-item">
	<a class="woo-wallet-menu-contents menu-link" title="<?php echo esc_html_e( 'Current wallet balance', 'woo-wallet' ); ?>" href="<?php echo esc_url( wc_get_account_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'woo-wallet' ) ) ); ?>">
		<span dir="rtl" class="woo-wallet-icon-wallet"></span>&nbsp;
		<?php echo woo_wallet()->wallet->get_wallet_balance( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</a>
</li>
