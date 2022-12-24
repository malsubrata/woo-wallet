<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( class_exists( 'WC_Payment_Gateway' ) ) {

	class Woo_Gateway_Wallet_payment extends WC_Payment_Gateway {

		/**
		 * Class constructor
		 */
		public function __construct() {
			$this->setup_properties();
			$this->supports = array(
				'products',
				'refunds',
				'subscriptions',
				'multiple_subscriptions',
				'subscription_cancellation',
				'subscription_suspension',
				'subscription_reactivation',
				'subscription_amount_changes',
				'subscription_date_changes',
				'subscription_payment_method_change',
				'subscription_payment_method_change_customer',
				'subscription_payment_method_change_admin',
			);
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			// Get settings.
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions' );

			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			/* support for woocommerce subscription plugin */
			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array( $this, 'scheduled_subscription_payment' ), 10, 2 );

			add_action( 'woocommerce_pre_payment_complete', array( $this, 'woocommerce_pre_payment_complete' ) );
		}

		/**
		 * Setup general properties for the gateway.
		 */
		protected function setup_properties() {
			$this->id                 = 'wallet';
			$this->method_title       = __( 'Wallet', 'woo-wallet' );
			$this->method_description = __( 'Have your customers pay with wallet.', 'woo-wallet' );
			$this->has_fields         = false;
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'      => array(
					'title'       => __( 'Enable/Disable', 'woo-wallet' ),
					'label'       => __( 'Enable wallet payments', 'woo-wallet' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'yes',
				),
				'title'        => array(
					'title'       => __( 'Title', 'woo-wallet' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woo-wallet' ),
					'default'     => __( 'Wallet payment', 'woo-wallet' ),
					'desc_tip'    => true,
				),
				'description'  => array(
					'title'       => __( 'Description', 'woo-wallet' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woo-wallet' ),
					'default'     => __( 'Pay with wallet.', 'woo-wallet' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'woo-wallet' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page.', 'woo-wallet' ),
					'default'     => __( 'Pay with wallet.', 'woo-wallet' ),
					'desc_tip'    => true,
				),
			);
		}

		/**
		 * Is gateway available
		 *
		 * @return boolean
		 */
		public function is_available() {
			if ( is_checkout() ) {
				return apply_filters( 'woo_wallet_payment_is_available', ( parent::is_available() && is_full_payment_through_wallet() && is_user_logged_in() && ! is_enable_wallet_partial_payment() && ! is_wallet_account_locked() ) );
			}
			return parent::is_available();
		}
		/**
		 * Display wallet balance as Icon.
		 *
		 * @return string
		 */
		public function get_icon() {
			$current_balance = woo_wallet()->wallet->get_wallet_balance( get_current_user_id() );
			return apply_filters( 'woocommerce_gateway_icon', sprintf( __( ' | Current Balance: <strong>%s</strong>', 'woo-wallet' ), $current_balance ), $this->id );
		}

		/**
		 * Is $order_id a subscription?
		 *
		 * @param  int $order_id order_id.
		 * @return boolean
		 */
		protected function is_subscription( $order_id ) {
			return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
		}

		/**
		 * Process wallet payment
		 *
		 * @param int $order_id order_id.
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ( $order->get_total( 'edit' ) > woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) && apply_filters( 'woo_wallet_disallow_negative_transaction', ( woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) <= 0 || $order->get_total( 'edit' ) > woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ), $order->get_total( 'edit' ), woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) ) {
				wc_add_notice( __( 'Payment error: ', 'woo-wallet' ) . sprintf( __( 'Your wallet balance is low. Please add %s to proceed with this transaction.', 'woo-wallet' ), wc_price( $order->get_total( 'edit' ) - woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ), woo_wallet_wc_price_args( $order->get_customer_id() ) ) ), 'error' );
				return;
			}

			// Reduce stock levels.
			wc_reduce_stock_levels( $order_id );

			// Remove cart.
			WC()->cart->empty_cart();

			// Complete order payment.
			$order->payment_complete();

			// Return thankyou redirect.
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			);
		}
		/**
		 * Debit user wallet on WooCommerce payment complete.
		 *
		 * @param WC_Order $order_id order_id.
		 * @throws Exception WooCommerce expeptions.
		 */
		public function woocommerce_pre_payment_complete( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( 'wallet' === $order->get_payment_method( 'edit' ) && ! $order->get_transaction_id( 'edit' ) && $order->has_status( apply_filters( 'woocommerce_valid_order_statuses_for_payment_complete', array( 'on-hold', 'pending', 'failed', 'cancelled' ), $order ) ) ) {
				if ( woo_wallet()->wallet->get_wallet_balance( $order->get_customer_id( 'edit' ), 'edit' ) >= $order->get_total( 'edit' ) ) {
					$wallet_response = woo_wallet()->wallet->debit( $order->get_customer_id( 'edit' ), $order->get_total( 'edit' ), apply_filters( 'woo_wallet_order_payment_description', __( 'For order payment #', 'woo-wallet' ) . $order->get_order_number(), $order ) );
					if ( $wallet_response ) {
						$order->set_transaction_id( $wallet_response );
						do_action( 'woo_wallet_payment_processed', $order_id, $wallet_response );
						$order->save();
					} else {
						throw new Exception( __( 'Something went wrong with processing payment please try again.', 'woo-wallet' ) );
					}
				} else {
					throw new Exception( __( 'Insufficient wallet balance', 'woo-wallet' ) );
				}
			}
		}

		/**
		 * Process a refund if supported.
		 *
		 * @param  int    $order_id Order ID.
		 * @param  float  $amount Refund amount.
		 * @param  string $reason Refund reason.
		 * @return bool|WP_Error
		 * @throws Exception WP_Error Exceptions.
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order          = wc_get_order( $order_id );
			$refund_reason  = $reason ? $reason : __( 'Wallet refund #', 'woo-wallet' ) . $order->get_order_number();
			$transaction_id = woo_wallet()->wallet->credit( $order->get_customer_id(), $amount, $refund_reason );
			if ( ! $transaction_id ) {
				throw new Exception( __( 'Refund not credited to customer', 'woo-wallet' ) );
			}
			do_action( 'woo_wallet_order_refunded', $order, $amount, $transaction_id );
			return true;
		}

		/**
		 * Process renewal payment for subscription order
		 *
		 * @param int      $amount_to_charge amount_to_charge.
		 * @param WC_Order $order order.
		 * @return void
		 */
		public function scheduled_subscription_payment( $amount_to_charge, $order ) {
			if ( get_post_meta( $order->get_id(), '_wallet_scheduled_subscription_payment_processed', true ) ) {
				return;
			}
			$order->payment_complete();
			update_post_meta( $order->get_id(), '_wallet_scheduled_subscription_payment_processed', true );
		}
	}
}
