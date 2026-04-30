<?php
/**
 * WOOCS / FOX Currency Switcher provider.
 *
 * Wraps the global `$WOOCS` API used by realmag777's "FOX - Currency
 * Switcher Professional for WooCommerce" plugin. Conversion math matches
 * what the previous WOOCS-specific adapter did (rates relative to the
 * shop base, divide by from-rate then multiply by to-rate); the wallet's
 * currency hooks now live in `Woo_Wallet_Multicurrency_Integration`.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Provider_WOOCS' ) ) {
	/**
	 * WOOCS provider.
	 */
	class Woo_Wallet_Currency_Provider_WOOCS extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * {@inheritDoc}
		 */
		public function get_id() {
			return 'woocs';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label() {
			return __( 'WOOCS / FOX Currency Switcher', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_available() {
			return class_exists( 'WOOCS' ) && isset( $GLOBALS['WOOCS'] );
		}

		/**
		 * {@inheritDoc}
		 *
		 * WOOCS exposes its currently-selected currency on the global as
		 * `current_currency`. Fall back to the abstract default if the
		 * global isn't ready yet (very early init).
		 */
		public function get_active_currency() {
			if ( $this->is_available() && ! empty( $GLOBALS['WOOCS']->current_currency ) ) {
				return strtoupper( (string) $GLOBALS['WOOCS']->current_currency );
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
			$currencies = $GLOBALS['WOOCS']->get_currencies();
			if ( ! is_array( $currencies ) || empty( $currencies ) ) {
				return array( $base );
			}
			$out = array();
			foreach ( array_keys( $currencies ) as $code ) {
				$code = strtoupper( (string) $code );
				if ( '' !== $code ) {
					$out[] = $code;
				}
			}
			$out[] = $base;
			return array_values( array_unique( $out ) );
		}

		/**
		 * {@inheritDoc}
		 *
		 * WOOCS rates are stored relative to the shop base. To go $from -> $to
		 * we divide out the from-rate and multiply by the to-rate.
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

			$currencies = $GLOBALS['WOOCS']->get_currencies();
			if ( ! is_array( $currencies ) ) {
				return null;
			}
			if ( ! isset( $currencies[ $from ], $currencies[ $to ] ) ) {
				return null;
			}
			if ( null === $currencies[ $from ] || null === $currencies[ $to ] ) {
				return null;
			}
			$from_rate = isset( $currencies[ $from ]['rate'] ) ? (float) $currencies[ $from ]['rate'] : 0;
			$to_rate   = isset( $currencies[ $to ]['rate'] ) ? (float) $currencies[ $to ]['rate'] : 0;
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
