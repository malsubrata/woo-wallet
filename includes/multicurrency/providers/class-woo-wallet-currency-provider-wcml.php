<?php
/**
 * WCML / WPML Multi-Currency provider.
 *
 * Wraps OnTheGoSystems's "WooCommerce Multilingual & Multicurrency".
 * Conversion goes through `WCML_Multi_Currency_Prices`, the public
 * pricing class WPML exposes for amount conversion between any two
 * configured currencies.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Provider_WCML' ) ) {
	/**
	 * WCML provider.
	 */
	class Woo_Wallet_Currency_Provider_WCML extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * {@inheritDoc}
		 */
		public function get_id() {
			return 'wcml';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label() {
			return __( 'WPML Multi-Currency', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_available() {
			return class_exists( 'WCML_Multi_Currency' )
				&& class_exists( 'WCML_Multi_Currency_Prices' )
				&& isset( $GLOBALS['woocommerce_wpml'] );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_active_currency() {
			if ( $this->is_available() ) {
				$mc = $GLOBALS['woocommerce_wpml']->get_multi_currency();
				if ( $mc && method_exists( $mc, 'get_client_currency' ) ) {
					$current = $mc->get_client_currency();
					if ( is_string( $current ) && '' !== $current ) {
						return strtoupper( $current );
					}
				}
			}
			return parent::get_active_currency();
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_supported_currencies() {
			$base = $this->get_base_currency();
			if ( ! $this->is_available() ) {
				return array( $base );
			}
			$wpml = $GLOBALS['woocommerce_wpml'];
			$mc   = $wpml->get_multi_currency();
			if ( $mc && method_exists( $mc, 'get_currency_codes' ) ) {
				$codes = $mc->get_currency_codes();
				if ( is_array( $codes ) && ! empty( $codes ) ) {
					$out = array();
					foreach ( $codes as $code ) {
						$code = strtoupper( (string) $code );
						if ( '' !== $code ) {
							$out[] = $code;
						}
					}
					$out[] = $base;
					return array_values( array_unique( $out ) );
				}
			}
			return array( $base );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Delegates to `WCML_Multi_Currency_Prices::convert_price_amount_by_currencies`.
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
			$wpml = $GLOBALS['woocommerce_wpml'];
			$mc   = $wpml->get_multi_currency();
			if ( ! $mc ) {
				return null;
			}
			$prices_settings = method_exists( $wpml, 'get_setting' )
				? $wpml->get_setting( 'currency_options' )
				: array();
			$prices = new WCML_Multi_Currency_Prices( $mc, $prices_settings );
			$result = $prices->convert_price_amount_by_currencies( (float) $amount, $from, $to );
			return is_numeric( $result ) ? (float) $result : null;
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
