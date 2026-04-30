<?php
/**
 * CURCY provider (WPClever's "WPC Multi Currency for WooCommerce").
 *
 * CURCY ships its settings (including the currency list and per-currency
 * rates) under one option blob and exposes its data layer via the
 * `WOOMULTI_CURRENCY_Data` class. This provider is conservative: it
 * reads what is reliably available across CURCY versions (option blob
 * + the `woocommerce_currency` filter for the active code) and refuses
 * to convert when it does not have a positive rate for both sides.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Provider_CURCY' ) ) {
	/**
	 * CURCY provider.
	 */
	class Woo_Wallet_Currency_Provider_CURCY extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * Cached settings array (per-request).
		 *
		 * @var array|null
		 */
		private $settings_cache = null;

		/**
		 * {@inheritDoc}
		 */
		public function get_id() {
			return 'curcy';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label() {
			return __( 'CURCY — WPC Multi Currency', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_available() {
			return class_exists( 'WOOMULTI_CURRENCY_Data' ) || class_exists( 'WOOMULTI_CURRENCY_F' );
		}

		/**
		 * Lazy-load CURCY settings.
		 *
		 * Tries the data class first (the public, supported entry point),
		 * then falls back to reading the raw option used by all CURCY
		 * versions to date.
		 *
		 * @return array
		 */
		private function get_settings() {
			if ( null !== $this->settings_cache ) {
				return $this->settings_cache;
			}
			$settings = array();
			if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) && method_exists( 'WOOMULTI_CURRENCY_Data', 'get_instance' ) ) {
				$data = WOOMULTI_CURRENCY_Data::get_instance();
				if ( $data && method_exists( $data, 'get_settings' ) ) {
					$settings = $data->get_settings();
				}
			}
			if ( empty( $settings ) ) {
				$settings = get_option( 'woocommerce_multi_currency_params', array() );
			}
			$this->settings_cache = is_array( $settings ) ? $settings : array();
			return $this->settings_cache;
		}

		/**
		 * Map of currency code -> rate (relative to the shop base currency).
		 *
		 * @return array<string, float>
		 */
		private function get_currencies_map() {
			$settings = $this->get_settings();
			$map      = array();
			if ( isset( $settings['currency'] ) && is_array( $settings['currency'] ) ) {
				foreach ( $settings['currency'] as $code ) {
					$code = strtoupper( (string) $code );
					if ( '' === $code ) {
						continue;
					}
					$rate         = isset( $settings['rate'][ $code ] ) ? (float) $settings['rate'][ $code ] : 0;
					$map[ $code ] = $rate;
				}
			} elseif ( isset( $settings['currencies'] ) && is_array( $settings['currencies'] ) ) {
				foreach ( $settings['currencies'] as $code => $row ) {
					$code = strtoupper( (string) $code );
					if ( '' === $code ) {
						continue;
					}
					$rate         = is_array( $row ) && isset( $row['rate'] ) ? (float) $row['rate'] : 0;
					$map[ $code ] = $rate;
				}
			}
			$base = $this->get_base_currency();
			if ( ! isset( $map[ $base ] ) ) {
				$map[ $base ] = 1.0;
			}
			return $map;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_supported_currencies() {
			$map = $this->get_currencies_map();
			$out = array_keys( $map );
			$out[] = $this->get_base_currency();
			return array_values( array_unique( $out ) );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Rates are relative to the shop base, so the conversion path
		 * matches WOOCS's: divide by from-rate, multiply by to-rate.
		 */
		public function convert( $amount, $from, $to ) {
			$from = strtoupper( (string) $from );
			$to   = strtoupper( (string) $to );
			if ( $from === $to ) {
				return (float) $amount;
			}
			if ( ! $this->is_available() ) {
				return null;
			}
			$map = $this->get_currencies_map();
			if ( ! isset( $map[ $from ], $map[ $to ] ) ) {
				return null;
			}
			$from_rate = (float) $map[ $from ];
			$to_rate   = (float) $map[ $to ];
			if ( $from_rate <= 0 || $to_rate <= 0 ) {
				return null;
			}
			return (float) $amount * ( 1 / $from_rate ) * $to_rate;
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_rate( $from, $to ) {
			$from = strtoupper( (string) $from );
			$to   = strtoupper( (string) $to );
			if ( $from === $to ) {
				return 1.0;
			}
			$converted = $this->convert( 1.0, $from, $to );
			return null === $converted ? null : (float) $converted;
		}
	}
}
