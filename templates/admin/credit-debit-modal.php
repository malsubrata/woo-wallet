<?php
/**
 * Admin View: Credit / Debit bulk-action modal.
 *
 * Captures amount and description for the bulk Credit / Debit actions on the
 * Wallet > Users list. The same template renders both actions; the JS in
 * `Woo_Wallet_Balance_Details::add_js_scripts()` updates the title and
 * confirm-button label based on which action is selected before the modal
 * is opened.
 *
 * @package StandaloneTech
 * @since 1.6.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script type="text/template" id="tmpl-woo-wallet-modal-credit-debit">
	<div class="wc-backbone-modal woo-wallet-credit-debit">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1 id="woo-wallet-credit-debit-title"><?php esc_html_e( 'Adjust wallet balance', 'woo-wallet' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woo-wallet' ); ?></span>
					</button>
				</header>
				<article>
					<p id="woo-wallet-credit-debit-intro"><?php esc_html_e( 'Enter the amount and an optional description. The adjustment will be applied to every selected user.', 'woo-wallet' ); ?></p>
					<div class="woo-wallet-modal-form">
						<div class="woo-wallet-modal-field">
							<label for="woo-wallet-bulk-amount">
								<?php
								/* translators: %s: WooCommerce currency symbol */
								echo esc_html( sprintf( __( 'Amount (%s)', 'woo-wallet' ), get_woocommerce_currency_symbol() ) );
								?>
							</label>
							<input type="number" step="0.01" min="0" id="woo-wallet-bulk-amount" name="woo_wallet_bulk_amount" required />
						</div>
						<div class="woo-wallet-modal-field">
							<label for="woo-wallet-bulk-description"><?php esc_html_e( 'Description', 'woo-wallet' ); ?></label>
							<textarea id="woo-wallet-bulk-description" name="woo_wallet_bulk_description" rows="3"></textarea>
							<p class="woo-wallet-modal-help"><?php esc_html_e( 'Shown on each transaction record. Leave empty to use the default description.', 'woo-wallet' ); ?></p>
						</div>
					</div>
				</article>
				<footer>
					<div class="inner">
						<button type="button" class="button button-primary" id="woo-wallet-confirm-credit-debit"><?php esc_html_e( 'Apply', 'woo-wallet' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
		<div class="wc-backbone-modal-backdrop modal-close"></div>
	</div>
</script>
<style>
	.woo-wallet-credit-debit .woo-wallet-modal-form {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}
	.woo-wallet-credit-debit .woo-wallet-modal-field {
		display: flex;
		flex-direction: column;
		gap: 6px;
	}
	.woo-wallet-credit-debit .woo-wallet-modal-field label {
		font-weight: 600;
	}
	.woo-wallet-credit-debit .woo-wallet-modal-field input[type="number"],
	.woo-wallet-credit-debit .woo-wallet-modal-field textarea {
		width: 100%;
		max-width: 100%;
		box-sizing: border-box;
		padding: 6px 10px;
		font-size: 14px;
		line-height: 1.4;
	}
	.woo-wallet-credit-debit .woo-wallet-modal-field textarea {
		resize: vertical;
		min-height: 80px;
	}
	.woo-wallet-credit-debit .woo-wallet-modal-help {
		margin: 4px 0 0;
		font-size: 12px;
		color: #646970;
	}
	@media screen and (max-width: 600px) {
		.woo-wallet-credit-debit .wc-backbone-modal-content {
			width: 95vw;
			max-width: 95vw;
		}
		.woo-wallet-credit-debit .wc-backbone-modal-main article {
			padding: 16px;
		}
	}
</style>
