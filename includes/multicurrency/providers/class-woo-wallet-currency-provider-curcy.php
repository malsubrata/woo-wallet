<?php
/**
 * CURCY provider — wraps VillaTheme's "CURCY - Multi Currency for WooCommerce".
 *
 * The plugin's WordPress.org slug is `woo-multi-currency`. Its public data
 * surface is the `WOOMULTI_CURRENCY_F_Data` class (NOT
 * `WOOMULTI_CURRENCY_Data` — that name was used in earlier scaffold drafts
 * and never shipped). Singleton accessor is `::get_ins()`. The methods we
 * rely on:
 *
 *   - `get_default_currency()`   — shop base.
 *   - `get_current_currency()`   — customer's currently-selected currency.
 *                                  Reads the `wmc_current_currency` cookie
 *                                  directly, so it's context-independent
 *                                  (works on storefront, AJAX, REST, admin
 *                                  order-completion).
 *   - `get_list_currencies()`    — `[code => ['rate' => float, ...]]`. The
 *                                  rate is stored as "units of currency
 *                                  per 1 unit of base", same shape WOOCS
 *                                  uses.
 *
 * Conversion math is identical to the WOOCS provider's: divide by the
 * from-rate, multiply by the to-rate. Reading the rate map from the Data
 * class instead of the option blob means we automatically pick up any
 * runtime overrides VillaTheme applies (currency-rate-fee, etc).
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
		 * Cached "code => rate" map (per-request).
		 *
		 * @var array<string, float>|null
		 */
		private $rate_cache = null;

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
			return __( 'CURCY — Multi Currency for WooCommerce', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 */
		public function is_available() {
			return class_exists( 'WOOMULTI_CURRENCY_F_Data' );
		}

		/**
		 * Lazy CURCY data-class accessor.
		 *
		 * @return WOOMULTI_CURRENCY_F_Data|null
		 */
		private function data() {
			if ( ! $this->is_available() || ! method_exists( 'WOOMULTI_CURRENCY_F_Data', 'get_ins' ) ) {
				return null;
			}
			$ins = WOOMULTI_CURRENCY_F_Data::get_ins();
			return is_object( $ins ) ? $ins : null;
		}

		/**
		 * {@inheritDoc}
		 *
		 * Read base from CURCY's data class so we agree with whatever
		 * CURCY itself considers "default" — usually the same as
		 * `get_option('woocommerce_currency')` but kept consistent with
		 * the plugin's source of truth.
		 */
		public function get_base_currency() {
			$data = $this->data();
			if ( $data && method_exists( $data, 'get_default_currency' ) ) {
				$code = $data->get_default_currency();
				if ( is_string( $code ) && '' !== $code ) {
					return strtoupper( $code );
				}
			}
			return parent::get_base_currency();
		}

		/**
		 * {@inheritDoc}
		 *
		 * Reading directly from the data class is context-independent —
		 * `get_current_currency()` consults the `wmc_current_currency`
		 * cookie, so AJAX / admin-order-completion / REST cookie-auth
		 * all see the customer's actual selection. The inherited
		 * `apply_filters('woocommerce_currency', $base)` default is a
		 * trap on storefronts that gate that filter behind a context
		 * check (which CURCY does for a few request paths).
		 */
		public function get_active_currency() {
			$data = $this->data();
			if ( $data && method_exists( $data, 'get_current_currency' ) ) {
				$code = $data->get_current_currency();
				if ( is_string( $code ) && '' !== $code ) {
					return strtoupper( $code );
				}
			}
			return parent::get_active_currency();
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_supported_currencies() {
			$out  = array_keys( $this->get_rate_map() );
			$base = $this->get_base_currency();
			if ( ! in_array( $base, $out, true ) ) {
				$out[] = $base;
			}
			return array_values( array_unique( $out ) );
		}

		/**
		 * Build a "code => rate-relative-to-base" map from CURCY.
		 *
		 * @return array<string, float>
		 */
		private function get_rate_map() {
			if ( null !== $this->rate_cache ) {
				return $this->rate_cache;
			}
			$map  = array();
			$data = $this->data();
			if ( $data && method_exists( $data, 'get_list_currencies' ) ) {
				$list = $data->get_list_currencies();
				if ( is_array( $list ) ) {
					foreach ( $list as $code => $row ) {
						$code = strtoupper( (string) $code );
						if ( '' === $code ) {
							continue;
						}
						$rate = is_array( $row ) && isset( $row['rate'] ) ? (float) $row['rate'] : 0;
						if ( $rate > 0 ) {
							$map[ $code ] = $rate;
						}
					}
				}
			}
			$base = $this->get_base_currency();
			if ( ! isset( $map[ $base ] ) ) {
				$map[ $base ] = 1.0;
			}
			$this->rate_cache = $map;
			return $this->rate_cache;
		}

		/**
		 * {@inheritDoc}
		 *
		 * Same WOOCS-style math the wallet has used since 1.0: divide by
		 * the from-rate, multiply by the to-rate. Returns null on any
		 * unknown rate so the manager can fail open.
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
			$map = $this->get_rate_map();
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
