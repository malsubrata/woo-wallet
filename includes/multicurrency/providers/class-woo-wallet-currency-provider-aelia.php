<?php
/**
 * Aelia Currency Switcher provider.
 *
 * Aelia exposes a stable filter-based API:
 *   - `wc_aelia_cs_selected_currency` — currently selected currency.
 *   - `wc_aelia_cs_convert` — convert an amount between two currencies.
 * We use the filter API exclusively so we do not depend on Aelia's
 * internal class layout (which has shifted across major versions).
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Provider_Aelia' ) ) {
	/**
	 * Aelia provider.
	 */
	class Woo_Wallet_Currency_Provider_Aelia extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * {@inheritDoc}
		 */
		public function get_id() {
			return 'aelia';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label() {
			return __( 'Aelia Currency Switcher', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Aelia registers its filters synchronously when the plugin loads,
		 * so `has_filter()` is the most reliable presence check.
		 */
		public function is_available() {
			return class_exists( 'WC_Aelia_CurrencySwitcher' )
				|| has_filter( 'wc_aelia_cs_convert' )
				|| has_filter( 'wc_aelia_cs_selected_currency' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_active_currency() {
			if ( has_filter( 'wc_aelia_cs_selected_currency' ) ) {
				$current = apply_filters( 'wc_aelia_cs_selected_currency', $this->get_base_currency() );
				if ( is_string( $current ) && '' !== $current ) {
					return strtoupper( $current );
				}
			}
			return parent::get_active_currency();
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_supported_currencies() {
			$base = $this->get_base_currency();
			if ( has_filter( 'wc_aelia_cs_enabled_currencies' ) ) {
				$codes = apply_filters( 'wc_aelia_cs_enabled_currencies', array( $base ) );
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
		 * Delegates to Aelia's `wc_aelia_cs_convert` filter. Aelia returns
		 * the original amount unchanged when it can't convert; we cannot
		 * distinguish that from a real noop, so when from==to we shortcut,
		 * and otherwise we trust the filter result.
		 */
		public function convert( $amount, $from, $to ) {
			$from = strtoupper( (string) $from );
			$to   = strtoupper( (string) $to );
			if ( $from === $to ) {
				return (float) $amount;
			}
			if ( ! has_filter( 'wc_aelia_cs_convert' ) ) {
				return null;
			}
			$converted = apply_filters( 'wc_aelia_cs_convert', (float) $amount, $from, $to );
			return is_numeric( $converted ) ? (float) $converted : null;
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
