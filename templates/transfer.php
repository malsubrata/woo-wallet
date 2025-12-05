<?php
/**
 * The Template for displaying wallet dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/transfer.php.
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
<!-- Transfer Form -->
<div class="woo-wallet-form-wrapper">
	<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Wallet Transfer', 'woo-wallet' ); ?></h3>
	<form method="post" action="" id="woo_wallet_transfer_form">
		<p class="woo-wallet-field-container form-row form-row-wide">
			<label for="woo_wallet_transfer_user_id"><?php esc_html_e( 'Select Recipient', 'woo-wallet' ); ?>
			<?php
			if ( apply_filters( 'woo_wallet_user_search_exact_match', true ) ) {
				esc_html_e( '(Email)', 'woo-wallet' );
			}
			?>
				</label>
			<select name="woo_wallet_transfer_user_id" id="woo_wallet_transfer_user_id" class="woo-wallet-select2" required=""></select>
		</p>
		<p class="woo-wallet-field-container form-row form-row-wide">
			<label for="woo_wallet_transfer_amount"><?php esc_html_e( 'Amount', 'woo-wallet' ); ?></label>
			<input id="woo_wallet_transfer_amount" type="number" step="0.01" min="<?php echo woo_wallet()->settings_api->get_option( 'min_transfer_amount', '_wallet_settings_general', 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" name="woo_wallet_transfer_amount" required="" placeholder="0.00"/>
		</p>
		<p class="woo-wallet-field-container form-row form-row-wide">
			<label for="woo_wallet_transfer_note"><?php esc_html_e( 'What\'s this for?', 'woo-wallet' ); ?></label>
			<textarea id="woo_wallet_transfer_note" name="woo_wallet_transfer_note" placeholder="<?php esc_attr_e( 'Optional note...', 'woo-wallet' ); ?>"></textarea>
		</p>
		<p class="woo-wallet-field-container form-row">
			<?php wp_nonce_field( 'woo_wallet_transfer', 'woo_wallet_transfer' ); ?>
			<input type="submit" class="button" name="woo_wallet_transfer_fund" value="<?php esc_html_e( 'Proceed to Transfer', 'woo-wallet' ); ?>" />
		</p>
	</form>
</div>