<?php
/**
 * The Template for displaying partial payment html at checkout page
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/woo-wallet-partial-payment.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.1.4
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$parial_payment_amount = apply_filters( 'woo_wallet_partial_payment_amount', wc()->session->get( 'partial_payment_amount', 0 ) && woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) >= wc()->session->get( 'partial_payment_amount', 0 ) ? wc()->session->get( 'partial_payment_amount', 0 ) : woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) );
if ( $parial_payment_amount <= 0 ) {
	return;
}
$rest_amount = get_woowallet_cart_total() - $parial_payment_amount;
if ( 'on' === woo_wallet()->settings_api->get_option( 'is_auto_deduct_for_partial_payment', '_wallet_settings_general' ) ) {
	?>
	<tr class="wallet-pay-partial">
		<th colspan="2">
			<label>
				<?php
				/* translators: wallet amount */
				printf( __( '%1$s will be debited from your wallet and %2$s will be paid through other payment method', 'woo-wallet' ), wc_price( $parial_payment_amount, woo_wallet_wc_price_args() ), wc_price( $rest_amount, woo_wallet_wc_price_args() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped 
				?>
			</label>
		</th>
	</tr>

<?php } else { ?>
	<tr class="wallet-pay-partial">
		<th>
			<?php
			esc_html_e( 'Pay by wallet', 'woo-wallet' );
			?>
			<span id="partial_wallet_payment_tooltip" class="woo-wallet-tooltip-wrapper">
				<span class="dashicons dashicons-info" style="vertical-align: middle;"></span>
				<div class="woo-wallet-tooltip-content">
					<div class="woo-wallet-tooltip-header">
						<strong><?php esc_html_e( 'Payment Split', 'woo-wallet' ); ?></strong>
					</div>
					<div class="woo-wallet-tooltip-body">
						<div class="woo-wallet-tooltip-row">
							<span class="woo-wallet-tooltip-label"><?php esc_html_e( 'Wallet:', 'woo-wallet' ); ?></span>
							<span class="woo-wallet-tooltip-value wallet-amount">
								<?php echo wc_price( $parial_payment_amount, woo_wallet_wc_price_args() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
						</div>
						<div class="woo-wallet-tooltip-row">
							<span class="woo-wallet-tooltip-label"><?php esc_html_e( 'Other methods:', 'woo-wallet' ); ?></span>
							<span class="woo-wallet-tooltip-value other-amount">
								<?php echo wc_price( $rest_amount, woo_wallet_wc_price_args() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</span>
						</div>
					</div>
				</div>
				<div class="woo-wallet-tooltip-arrow"></div>
			</span>
		</th>
		<td data-title="<?php esc_attr_e( 'Pay by wallet', 'woo-wallet' ); ?>"><input type="checkbox" <?php checked( is_enable_wallet_partial_payment(), true, true ); ?> style="vertical-align: middle;" name="partial_pay_through_wallet" class="partial_pay_through_wallet" /></td>
	</tr>

	<style type="text/css">
		.woo-wallet-tooltip-wrapper {
			position: relative;
			display: inline-block;
			cursor: help;
		}

		.woo-wallet-tooltip-wrapper .dashicons-info {
			width: 18px;
			height: 18px;
			font-size: 18px;
			line-height: 18px;
			color: #6c7175;
			transition: color 0.2s ease;
		}

		.woo-wallet-tooltip-wrapper:hover .dashicons-info {
			color: #1e1e1e;
		}

		.woo-wallet-tooltip-content {
			display: none;
			position: absolute;
			bottom: 140%;
			left: 50%;
			transform: translateX(-50%);
			background-color: #23282d;
			color: #fff;
			border-radius: 4px;
			font-size: 13px;
			white-space: nowrap;
			z-index: 1000;
			box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
			pointer-events: none;
			min-width: 280px;
		}

		.woo-wallet-tooltip-header {
			padding: 12px 14px 10px;
			border-bottom: 1px solid rgba(255, 255, 255, 0.15);
			font-weight: 600;
			font-size: 11px;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			opacity: 0.85;
		}

		.woo-wallet-tooltip-body {
			padding: 12px 14px;
		}

		.woo-wallet-tooltip-row {
			display: flex;
			justify-content: space-between;
			align-items: center;
			margin-bottom: 8px;
			line-height: 1.6;
		}

		.woo-wallet-tooltip-row:last-child {
			margin-bottom: 0;
		}

		.woo-wallet-tooltip-label {
			display: flex;
			align-items: center;
			font-weight: 500;
			opacity: 0.8;
			margin-right: 12px;
			font-size: 12px;
		}

		.woo-wallet-tooltip-value {
			display: flex;
			align-items: center;
			font-weight: 600;
			white-space: nowrap;
			font-size: 13px;
		}

		.woo-wallet-tooltip-value.wallet-amount {
			color: #5ec45e;
		}

		.woo-wallet-tooltip-value.other-amount {
			color: #ffb81c;
		}

		.woo-wallet-tooltip-arrow {
			display: none;
			position: absolute;
			bottom: 126%;
			left: 50%;
			transform: translateX(-50%);
			width: 0;
			height: 0;
			border-left: 6px solid transparent;
			border-right: 6px solid transparent;
			border-top: 6px solid #23282d;
			z-index: 1000;
			pointer-events: none;
		}

		.woo-wallet-tooltip-wrapper:hover .woo-wallet-tooltip-content,
		.woo-wallet-tooltip-wrapper:hover .woo-wallet-tooltip-arrow {
			display: block;
			animation: woo-wallet-tooltip-fade-in 0.15s ease forwards;
		}

		@keyframes woo-wallet-tooltip-fade-in {
			from {
				opacity: 0;
				transform: translateX(-50%) translateY(4px);
			}
			to {
				opacity: 1;
				transform: translateX(-50%) translateY(0);
			}
		}

		.woo-wallet-tooltip-wrapper:hover .woo-wallet-tooltip-arrow {
			animation: none;
			display: block;
		}

		@media (max-width: 480px) {
			.woo-wallet-tooltip-content {
				min-width: 240px;
				font-size: 12px;
			}

			.woo-wallet-tooltip-row {
				flex-direction: column;
				align-items: flex-start;
				margin-bottom: 8px;
			}

			.woo-wallet-tooltip-label {
				margin-right: 0;
				margin-bottom: 4px;
			}
		}
	</style>

	<script type="text/javascript">
		jQuery(function ($) {
			var $tooltip = $('#partial_wallet_payment_tooltip');
			var $content = $tooltip.find('.woo-wallet-tooltip-content');

			$tooltip.on('mouseenter', function() {
				$content.stop(true, true).fadeIn(150);
			}).on('mouseleave', function() {
				$content.stop(true, true).fadeOut(150);
			});
		});
	</script>

	<script type="text/javascript">
		jQuery(function ($) {
			$(document).on('change', '.partial_pay_through_wallet', function (event) {
				event.stopImmediatePropagation();
				var data = {
					action: 'woo_wallet_partial_payment_update_session',
					checked: $(this).is(':checked')
				};
				$.post('<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', data, function () {
					$(document.body).trigger('update_checkout');
				});
			});
		});
	</script>
	<?php
}
