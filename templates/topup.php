<?php
/**
 * The Template for displaying wallet dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/topup.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.5.15
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!-- Top Up Form -->
<div class="woo-wallet-form-wrapper">
	<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Wallet Topup', 'woo-wallet' ); ?></h3>
	<form method="post" action="">
		<div class="woo-wallet-add-amount">
			<label for="woo_wallet_balance_to_add"><?php esc_html_e( 'Enter amount', 'woo-wallet' ); ?></label>
			<?php
			$min_amount = woo_wallet()->settings_api->get_option( 'min_topup_amount', '_wallet_settings_general', 0 );
			$max_amount = woo_wallet()->settings_api->get_option( 'max_topup_amount', '_wallet_settings_general', '' );
			?>
			<input type="number" step="0.01" min="<?php echo esc_attr( $min_amount ); ?>" max="<?php echo esc_attr( $max_amount ); ?>" name="woo_wallet_balance_to_add" id="woo_wallet_balance_to_add" class="woo-wallet-balance-to-add" required="" placeholder="0.00" />
			<?php wp_nonce_field( 'woo_wallet_topup', 'woo_wallet_topup' ); ?>
			<input type="submit" name="woo_add_to_wallet" class="woo-add-to-wallet" value="<?php esc_html_e( 'Add Funds', 'woo-wallet' ); ?>" />
		</div>
	</form>
</div>