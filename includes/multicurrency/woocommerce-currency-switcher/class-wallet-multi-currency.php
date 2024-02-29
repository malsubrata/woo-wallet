<?php
/**
 * Multicurrency support for FOX - Currency Switcher Professional for WooCommerce
 * By realmag777
 *
 * @package StandaloneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Wallet_Multi_currency' ) ) {
	/**
	 * Multi currency class
	 */
	class Wallet_Multi_Currency {
		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_filter( 'woo_wallet_amount', array( __CLASS__, 'woo_wallet_amount' ), 10, 3 );
			add_filter( 'woo_wallet_rechargeable_amount', array( $this, 'woo_wallet_rechargeable_amount' ) );
			add_filter( 'woo_wallet_current_balance', array( $this, 'woo_wallet_current_balance' ), 10, 2 );
			add_filter( 'woo_wallet_get_option__wallet_settings_general_max_topup_amount', array( $this, 'convert_settings_option' ) );
			add_filter( 'woo_wallet_get_option__wallet_settings_general_min_topup_amount', array( $this, 'convert_settings_option' ) );
			add_filter( 'woo_wallet_get_option__wallet_settings_general_min_transfer_amount', array( $this, 'convert_settings_option' ) );

			add_filter( 'woo_wallet_form_cart_cashback_amount', array( $this, 'woo_wallet_form_cart_cashback_amount' ) );
		}
		/**
		 * Get converted amount
		 *
		 * @param float  $amount amount.
		 * @param string $from_currency from_currency.
		 * @param string $to_currency to_currency.
		 * @return float
		 */
		public static function get_converted_amount( $amount, $from_currency, $to_currency ) {
			global $WOOCS;
			$currencies = $WOOCS->get_currencies();
			if ( isset( $currencies[ $from_currency ] ) && isset( $currencies[ $to_currency ] ) && $currencies[ $from_currency ] != null && $currencies[ $to_currency ] != null ) {
				$amount = $amount * ( 1 / $currencies[ $from_currency ]['rate'] ) * $currencies[ $to_currency ]['rate'];
			}
			return $amount;
		}
		/**
		 * Convert wallet amount
		 *
		 * @param float  $amount amount.
		 * @param string $currency currency.
		 * @param int    $user_id user_id.
		 * @return float
		 */
		public static function woo_wallet_amount( $amount, $currency, $user_id = null ) {
			global $WOOCS;
			$currencies = $WOOCS->get_currencies();
			if ( isset( $currencies[ $currency ] ) && isset( $currencies[ $WOOCS->current_currency ] ) ) {
				$amount = $amount * ( 1 / $currencies[ $currency ]['rate'] ) * $currencies[ $WOOCS->current_currency ]['rate'];
			}
			return $amount;
		}
		/**
		 * Convert wallet recharge amount.
		 *
		 * @param float $amount amount.
		 * @return float
		 */
		public function woo_wallet_rechargeable_amount( $amount ) {
			return self::get_converted_amount( $amount, get_woocommerce_currency(), get_option( 'woocommerce_currency' ) );
		}
		/**
		 * Recalculate wallet balance
		 *
		 * @param float $balance balance.
		 * @param int   $user_id user_id.
		 * @return float
		 */
		public function woo_wallet_current_balance( $balance, $user_id ) {
			$credit_amount = 0;
			$debit_amount  = 0;
			$credit_array  = get_wallet_transactions(
				array(
					'user_id' => $user_id,
					'where'   => array(
						array(
							'key'   => 'type',
							'value' => 'credit',
						),
					),
				)
			);
			foreach ( $credit_array as $credit ) {
				$credit_amount += self::woo_wallet_amount( $credit->amount, $credit->currency );
			}
			$debit_array = get_wallet_transactions(
				array(
					'user_id' => $user_id,
					'where'   => array(
						array(
							'key'   => 'type',
							'value' => 'debit',
						),
					),
				)
			);
			foreach ( $debit_array as $debit ) {
				$debit_amount += self::woo_wallet_amount( $debit->amount, $debit->currency );
			}
			return $credit_amount - $debit_amount;
		}
		/**
		 * Convert plugin settings options.
		 *
		 * @param array $option_value option_value.
		 * @return array
		 */
		public function convert_settings_option( $option_value ) {
			if ( ! is_admin() && is_numeric( $option_value ) ) {
				$option_value = self::get_converted_amount( $option_value, get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
			}
			return $option_value;
		}
		/**
		 * Recalculate cart wise cashback amount
		 *
		 * @param float $cashback_amount cashback_amount.
		 * @return float
		 */
		public function woo_wallet_form_cart_cashback_amount( $cashback_amount ) {
			$cashback_rule         = woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' );
			$global_cashbak_type   = woo_wallet()->settings_api->get_option( 'cashback_type', '_wallet_settings_credit', 'percent' );
			$global_cashbak_amount = woo_wallet()->settings_api->get_option( 'cashback_amount', '_wallet_settings_credit', 0 );
			$max_cashbak_amount    = self::get_converted_amount( floatval( woo_wallet()->settings_api->get_option( 'max_cashback_amount', '_wallet_settings_credit', 0 ) ), get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
			if ( 'cart' === $cashback_rule ) {
				$cashback_amount = 0;
				if ( woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 10 ) != 0 && self::get_converted_amount( WC()->cart->get_subtotal( 'edit' ), get_option( 'woocommerce_currency' ), get_woocommerce_currency() ) >= woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 0 ) ) {
					if ( 'percent' === $global_cashbak_type ) {
						$percent_cashback_amount = wc()->cart->get_subtotal( 'edit' ) * ( $global_cashbak_amount / 100 );
						if ( $max_cashbak_amount && $percent_cashback_amount > $max_cashbak_amount ) {
							$cashback_amount += $max_cashbak_amount;
						} else {
							$cashback_amount += $percent_cashback_amount;
						}
					} else {
						$cashback_amount += self::get_converted_amount( floatval( $global_cashbak_amount ), get_option( 'woocommerce_currency' ), get_woocommerce_currency() );
					}
				}
			}
			return $cashback_amount;
		}
	}
}

new Wallet_Multi_currency();
