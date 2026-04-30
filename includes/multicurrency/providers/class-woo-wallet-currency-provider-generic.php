<?php
/**
 * Generic currency provider.
 *
 * Always available. Reports the storefront's active currency by reading
 * `apply_filters('woocommerce_currency', $base)` — the one filter every
 * multi-currency plugin in the wild hooks. Cannot convert (returns null),
 * which means the manager fails open: amounts pass through unchanged.
 *
 * Use this when no first-class adapter is installed but the user still
 * wants the wallet to at least display in the visitor's currency.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Provider_Generic' ) ) {
	/**
	 * Generic fallback provider.
	 */
	class Woo_Wallet_Currency_Provider_Generic extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * {@inheritDoc}
		 */
		public function get_id() {
			return 'generic';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label() {
			return __( 'Generic (woocommerce_currency filter)', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Generic is the always-on fallback.
		 */
		public function is_available() {
			return true;
		}

		/**
		 * {@inheritDoc}
		 *
		 * Conversion is a noop. Returning null tells the manager to fail
		 * open (amount passes through unchanged) rather than zero anything.
		 */
		public function convert( $amount, $from, $to ) {
			$from = strtoupper( (string) $from );
			$to   = strtoupper( (string) $to );
			if ( $from === $to ) {
				return (float) $amount;
			}
			return null;
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
			return null;
		}
	}
}
