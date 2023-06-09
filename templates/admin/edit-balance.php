<?php
/**
 * Admin View: Edit wallet balance popup.
 *
 * @package StandaloneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="woo-wallet-edit-balance-tb-content">
	<section class="woo-wallet-edit-balance-tb-main">
		<form method="post">
			<header>
				<strong class="current-balance"><?php /* translators: 1: user balance */ echo sprintf( __( 'Current balance: %s', 'woo-wallet' ), woo_wallet()->wallet->get_wallet_balance( $user_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></strong>
				<h1><?php echo esc_html( __( 'Edit balance', 'woo-wallet' ) ); ?></h1>
				<button class="modal-close modal-close-link dashicons dashicons-no-alt" onclick="javascript:tb_remove()" type="button">
					<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woo-wallet' ); ?></span>
				</button>
			</header>
			<fieldset>
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="balance_amount"><?php /* translators: 1: WooCommerce currency symbol. */ echo esc_html( sprintf( __( 'Amount (%s)', 'woo-wallet' ), get_woocommerce_currency_symbol() ) ); ?></label></th>
							<td>
								<input type="number" step="any" name="balance_amount" class="regular-text" placeholder="<?php esc_html_e( 'Enter amount', 'woo-wallet' ); ?>" />
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="payment_type"><?php esc_html_e( 'Type', 'woo-wallet' ); ?></label></th>
							<td>
								<?php
								$payment_types = apply_filters(
									'woo_wallet_adjust_balance_payment_type',
									array(
										'credit' => __( 'Credit', 'woo-wallet' ),
										'debit'  => __( 'Debit', 'woo-wallet' ),
									)
								);
								?>
								<select class="regular-text" name="payment_type" id="payment_type">
									<?php foreach ( $payment_types as $key => $value ) : ?>
									<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<?php do_action( 'woo_wallet_after_payment_type_field' ); ?>
						<tr>
							<th scope="row"><label for="payment_description"><?php esc_html_e( 'Description', 'woo-wallet' ); ?></label></th>
							<td>
								<textarea name="payment_description" class="regular-text" placeholder="<?php esc_html_e( 'Enter description', 'woo-wallet' ); ?>"></textarea>
							</td>
						</tr>
					</tbody>
				</table>
			</fieldset>
			<footer>
				<div class="inner">
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />
					<?php wp_nonce_field( 'woo-wallet-admin-adjust-balance', 'woo-wallet-admin-adjust-balance' ); ?>
					<?php submit_button( __( 'Update balance', 'woo-wallet' ), 'primary', 'submit', false ); ?>
				</div>
			</footer>
		</form>
	</section>
</div>
