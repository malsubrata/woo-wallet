<?php
/**
 * Base class for currency providers.
 *
 * Concrete providers extend this class so they only have to implement the
 * pieces that are genuinely plugin-specific (rate lookup, active currency).
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Abstract_Currency_Provider' ) ) {
	/**
	 * Abstract provider with sensible defaults.
	 */
	abstract class Woo_Wallet_Abstract_Currency_Provider implements Woo_Wallet_Currency_Provider {

		/**
		 * {@inheritDoc}
		 */
		abstract public function get_id();

		/**
		 * {@inheritDoc}
		 */
		abstract public function get_label();

		/**
		 * {@inheritDoc}
		 */
		abstract public function is_available();

		/**
		 * {@inheritDoc}
		 *
		 * Default: ask WooCommerce. Most multi-currency plugins filter this,
		 * so the result already reflects the storefront-visible currency.
		 */
		public function get_active_currency() {
			$currency = apply_filters( 'woocommerce_currency', $this->get_base_currency() );
			return is_string( $currency ) && '' !== $currency ? strtoupper( $currency ) : $this->get_base_currency();
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_base_currency() {
			$base = get_option( 'woocommerce_currency' );
			return is_string( $base ) && '' !== $base ? strtoupper( $base ) : 'USD';
		}

		/**
		 * {@inheritDoc}
		 *
		 * Default: just the base currency. Concrete providers should override
		 * to return the list configured in their plugin.
		 */
		public function get_supported_currencies() {
			return array( $this->get_base_currency() );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Default conversion path: noop on identity, otherwise compose via
		 * `get_rate`. A null rate means "unknown" and propagates up.
		 */
		public function convert( $amount, $from, $to ) {
			$from = strtoupper( (string) $from );
			$to   = strtoupper( (string) $to );

			if ( $from === $to ) {
				return (float) $amount;
			}

			$rate = $this->get_rate( $from, $to );
			if ( null === $rate || ! is_numeric( $rate ) || (float) $rate <= 0 ) {
				return null;
			}

			return (float) $amount * (float) $rate;
		}

		/**
		 * {@inheritDoc}
		 *
		 * Default: unknown. Concrete providers override.
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
