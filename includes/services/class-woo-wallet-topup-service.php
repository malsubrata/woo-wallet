<?php
/**
 * Wallet topup service.
 *
 * The form-side topup flow (`Woo_Wallet_Frontend::woo_wallet_frontend_loaded`)
 * works by adding the rechargeable product to the cart and redirecting to
 * checkout — that's the right shape for a server-rendered form, but it's
 * useless for a React SPA which needs to hand the customer a URL to redirect
 * to without going through cart UI.
 *
 * This service creates the WC_Order directly (skipping the cart), assigns the
 * rechargeable product line item with the requested top-up amount, sets the
 * billing customer, and returns `{ order_id, payment_url }`. The SPA navigates
 * to `payment_url` so the chosen gateway handles the actual payment exactly
 * as it would for a cart-flow checkout.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooWallet_Topup_Service' ) ) {

	/**
	 * Topup service.
	 */
	class WooWallet_Topup_Service {

		/**
		 * Create a top-up order for the given user.
		 *
		 * @param int    $user_id        Customer user id.
		 * @param float  $amount         Top-up amount (gross — gateway fee handled by WC at checkout).
		 * @param string $payment_method Optional payment method id (set on the order; gateway handles redirect).
		 * @param string $currency       Optional ISO 4217 code. When supplied, the WC order is created
		 *                               in this currency (`$order->set_currency()` before
		 *                               `calculate_totals()`) so the chosen gateway charges in the
		 *                               requested currency and the eventual `wallet_credit_purchase`
		 *                               callback writes a row whose `original_currency` reflects what
		 *                               the customer actually paid.
		 * @return array { is_valid, code?, message?, status?, order_id?, payment_url?, currency? }
		 */
		public static function create_order( $user_id, $amount, $payment_method = '', $currency = '' ) {
			$user_id        = (int) $user_id;
			$amount         = (float) $amount;
			$payment_method = sanitize_key( (string) $payment_method );
			$currency       = is_string( $currency ) ? strtoupper( trim( $currency ) ) : '';
			if ( '' !== $currency && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				return self::fail( 'rest_invalid_currency', __( 'Invalid currency code.', 'woo-wallet' ) );
			}

			if ( ! $user_id ) {
				return self::fail( 'rest_not_logged_in', __( 'You must be logged in to top up.', 'woo-wallet' ), 401 );
			}

			// Min/max validation mirrors the form-side `is_valid_wallet_recharge_amount`.
			$min_topup = (float) woo_wallet()->settings_api->get_option( 'min_topup_amount', '_wallet_settings_general', 0 );
			$max_topup = (float) woo_wallet()->settings_api->get_option( 'max_topup_amount', '_wallet_settings_general', 0 );
			if ( $amount <= 0 ) {
				return self::fail( 'rest_invalid_amount', __( 'Top-up amount must be greater than zero.', 'woo-wallet' ) );
			}
			if ( $min_topup && $amount < $min_topup ) {
				return self::fail(
					'rest_amount_below_minimum',
					/* translators: %s: minimum top-up amount */
					sprintf( __( 'The minimum amount needed for wallet top up is %s', 'woo-wallet' ), wc_price( $min_topup, woo_wallet_wc_price_args( $user_id ) ) )
				);
			}
			if ( $max_topup && $amount > $max_topup ) {
				return self::fail(
					'rest_amount_above_maximum',
					/* translators: %s: maximum top-up amount */
					sprintf( __( 'Wallet top up amount should be less than %s', 'woo-wallet' ), wc_price( $max_topup, woo_wallet_wc_price_args( $user_id ) ) )
				);
			}

			$product = get_wallet_rechargeable_product();
			if ( ! $product ) {
				return self::fail( 'rest_no_rechargeable_product', __( 'Wallet top-up product is not available.', 'woo-wallet' ), 503 );
			}

			$rechargeable_amount = (float) apply_filters( 'woo_wallet_rechargeable_amount', round( $amount, 2 ) );

			$order_args = array(
				'customer_id' => $user_id,
				'created_via' => 'terawallet_rest',
			);
			$order      = wc_create_order( $order_args );
			if ( is_wp_error( $order ) || ! $order ) {
				return self::fail( 'rest_order_create_failed', __( 'Could not create top-up order.', 'woo-wallet' ), 500 );
			}

			// Pin the order's currency BEFORE add_product / calculate_totals so the
			// rechargeable line item is priced in the customer-selected currency
			// rather than store base. Set on the order object directly because
			// wc_create_order() does not accept a `currency` arg.
			if ( '' !== $currency && method_exists( $order, 'set_currency' ) ) {
				$order->set_currency( $currency );
			}

			$item_id = $order->add_product(
				$product,
				1,
				array(
					'subtotal' => $rechargeable_amount,
					'total'    => $rechargeable_amount,
				)
			);
			if ( ! $item_id ) {
				$order->delete( true );
				return self::fail( 'rest_order_add_product_failed', __( 'Could not attach top-up amount to the order.', 'woo-wallet' ), 500 );
			}
			wc_update_order_item_meta( $item_id, 'recharge_amount', $rechargeable_amount );

			// Copy billing details from the customer's profile so payment gateways
			// have the identity fields they require.
			$user = get_userdata( $user_id );
			if ( $user ) {
				$order->set_billing_email( $user->user_email );
				$first = (string) get_user_meta( $user_id, 'billing_first_name', true );
				$last  = (string) get_user_meta( $user_id, 'billing_last_name', true );
				if ( '' !== $first ) {
					$order->set_billing_first_name( $first );
				}
				if ( '' !== $last ) {
					$order->set_billing_last_name( $last );
				}
			}

			if ( '' !== $payment_method ) {
				$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
				if ( isset( $gateways[ $payment_method ] ) && 'yes' === $gateways[ $payment_method ]->enabled ) {
					$order->set_payment_method( $gateways[ $payment_method ] );
				}
			}

			$order->calculate_totals();
			$order->update_status( 'pending', __( 'Top-up order created via REST.', 'woo-wallet' ) );

			do_action( 'terawallet_topup_order_created', $order, $user_id, $rechargeable_amount );

			return array(
				'is_valid'    => true,
				'order_id'    => (int) $order->get_id(),
				'amount'      => $rechargeable_amount,
				'currency'    => method_exists( $order, 'get_currency' ) ? $order->get_currency() : $currency,
				'payment_url' => $order->get_checkout_payment_url(),
			);
		}

		/**
		 * Failure tuple builder.
		 *
		 * @param string $code    Machine code.
		 * @param string $message Human message.
		 * @param int    $status  HTTP status.
		 * @return array
		 */
		private static function fail( $code, $message, $status = 400 ) {
			return array(
				'is_valid' => false,
				'code'     => $code,
				'message'  => $message,
				'status'   => $status,
			);
		}
	}
}
