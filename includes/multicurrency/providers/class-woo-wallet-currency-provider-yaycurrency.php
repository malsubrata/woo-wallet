<?php
/**
 * YayCurrency provider.
 *
 * Wraps "YayCurrency – WooCommerce Multi-Currency Switcher" by YayCommerce.
 * YayCurrency exposes its conversion via two filters that operate between
 * the shop base currency and whichever currency is currently active:
 *   - `yay_currency_convert_price`  (base -> active)
 *   - `yay_currency_revert_price`   (active -> base)
 * It does NOT expose an arbitrary-pair conversion API publicly. We compose
 * cross-currency conversions through base when both sides are known
 * (`A -> base -> B`) and otherwise return null so the manager fails open.
 *
 * Active-currency detection piggy-backs on the abstract default
 * (`apply_filters('woocommerce_currency', $base)`) — YayCurrency hooks
 * that filter on the storefront, so the result already reflects what the
 * customer sees.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Provider_YayCurrency' ) ) {
	/**
	 * YayCurrency provider.
	 */
	class Woo_Wallet_Currency_Provider_YayCurrency extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * Cached supported-currency list (per-request).
		 *
		 * @var array|null
		 */
		private $supported_cache = null;

		/**
		 * {@inheritDoc}
		 */
		public function get_id() {
			return 'yaycurrency';
		}

		/**
		 * {@inheritDoc}
		 */
		public function get_label() {
			return __( 'YayCurrency', 'woo-wallet' );
		}

		/**
		 * {@inheritDoc}
		 *
		 * The bootstrap class is loaded on `plugins_loaded`, so this is
		 * reliable from `init` onward (which is when the wallet wires up
		 * its multicurrency support).
		 */
		public function is_available() {
			return class_exists( '\Yay_Currency\Initialize' );
		}

		/**
		 * {@inheritDoc}
		 *
		 * Best-effort: enumerate the `yay-currency-manage` custom post type
		 * to get the configured currency codes. Falls back to the base
		 * currency on any unexpected shape.
		 */
		public function get_supported_currencies() {
			if ( null !== $this->supported_cache ) {
				return $this->supported_cache;
			}
			$base = $this->get_base_currency();
			$out  = array( $base );

			if ( $this->is_available() ) {
				$posts = get_posts(
					array(
						'post_type'      => 'yay-currency-manage',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'no_found_rows'  => true,
					)
				);
				if ( is_array( $posts ) ) {
					foreach ( $posts as $post_id ) {
						$code = $this->extract_currency_code_from_post( (int) $post_id );
						if ( '' !== $code ) {
							$out[] = $code;
						}
					}
				}
			}

			$this->supported_cache = array_values( array_unique( $out ) );
			return $this->supported_cache;
		}

		/**
		 * {@inheritDoc}
		 *
		 * Conversion strategy:
		 *   1. identity (from === to) — return $amount.
		 *   2. base -> active — `yay_currency_convert_price` filter.
		 *   3. active -> base — `yay_currency_revert_price` filter.
		 *   4. base -> non-active or non-active -> base — read the per-currency
		 *      rate from the YayCurrency CPT and apply WOOCS-style math
		 *      (rate is "units of currency per 1 unit of base").
		 *   5. non-base -> non-base — compose via base in two steps.
		 *   6. any case where a rate is unknown — return null (manager fails open).
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

			$base   = $this->get_base_currency();
			$active = $this->get_active_currency();

			// Cheap path: published filters cover the base <-> active cases.
			if ( $from === $base && $to === $active && has_filter( 'yay_currency_convert_price' ) ) {
				$converted = apply_filters( 'yay_currency_convert_price', (float) $amount, array() );
				return is_numeric( $converted ) ? (float) $converted : null;
			}
			if ( $from === $active && $to === $base && has_filter( 'yay_currency_revert_price' ) ) {
				$reverted = apply_filters( 'yay_currency_revert_price', (float) $amount, array() );
				return is_numeric( $reverted ) ? (float) $reverted : null;
			}

			// General path: per-currency rate lookup via the CPT.
			$rates = $this->get_rate_map();
			if ( ! isset( $rates[ $base ] ) ) {
				$rates[ $base ] = 1.0;
			}

			if ( $from === $base && isset( $rates[ $to ] ) && $rates[ $to ] > 0 ) {
				return (float) $amount * (float) $rates[ $to ];
			}
			if ( $to === $base && isset( $rates[ $from ] ) && $rates[ $from ] > 0 ) {
				return (float) $amount / (float) $rates[ $from ];
			}
			if ( isset( $rates[ $from ], $rates[ $to ] ) && $rates[ $from ] > 0 && $rates[ $to ] > 0 ) {
				$in_base = (float) $amount / (float) $rates[ $from ];
				return $in_base * (float) $rates[ $to ];
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
			$converted = $this->convert( 1.0, $from, $to );
			return null === $converted ? null : (float) $converted;
		}

		/**
		 * Build a "code => rate-relative-to-base" map from the YayCurrency CPT.
		 *
		 * Rate meta on each currency post is stored as either a numeric
		 * value or a `[ 'type' => 'auto'|'manual', 'value' => '<float>' ]`
		 * structure depending on YayCurrency version. Both shapes are
		 * handled; anything else is skipped.
		 *
		 * @return array<string, float>
		 */
		private function get_rate_map() {
			$base = $this->get_base_currency();
			$map  = array( $base => 1.0 );

			$posts = get_posts(
				array(
					'post_type'      => 'yay-currency-manage',
					'post_status'    => 'publish',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
				)
			);
			if ( ! is_array( $posts ) ) {
				return $map;
			}
			foreach ( $posts as $post_id ) {
				$code = $this->extract_currency_code_from_post( (int) $post_id );
				if ( '' === $code ) {
					continue;
				}
				$rate = $this->extract_rate_from_post( (int) $post_id );
				if ( null !== $rate && $rate > 0 ) {
					$map[ $code ] = $rate;
				}
			}
			return $map;
		}

		/**
		 * Pull the ISO currency code out of a YayCurrency CPT post.
		 *
		 * Tries the meta keys YayCurrency has used across versions, in
		 * order. Returns '' on miss.
		 *
		 * @param int $post_id Post id.
		 * @return string Uppercase ISO code, or '' if not found.
		 */
		private function extract_currency_code_from_post( $post_id ) {
			foreach ( array( 'currency', 'currency_code', 'yay_currency_code' ) as $key ) {
				$value = get_post_meta( $post_id, $key, true );
				if ( is_array( $value ) ) {
					$value = isset( $value[0] ) ? $value[0] : ( isset( $value['code'] ) ? $value['code'] : '' );
				}
				if ( is_string( $value ) && '' !== $value ) {
					return strtoupper( $value );
				}
			}
			return '';
		}

		/**
		 * Pull the per-base rate out of a YayCurrency CPT post.
		 *
		 * Returns null when the meta is missing / unparseable.
		 *
		 * @param int $post_id Post id.
		 * @return float|null
		 */
		private function extract_rate_from_post( $post_id ) {
			$value = get_post_meta( $post_id, 'rate', true );
			if ( is_array( $value ) ) {
				if ( isset( $value['value'] ) && is_numeric( $value['value'] ) ) {
					return (float) $value['value'];
				}
				if ( isset( $value[0] ) ) {
					$inner = $value[0];
					if ( is_array( $inner ) && isset( $inner['value'] ) && is_numeric( $inner['value'] ) ) {
						return (float) $inner['value'];
					}
					if ( is_numeric( $inner ) ) {
						return (float) $inner;
					}
				}
			}
			if ( is_numeric( $value ) ) {
				return (float) $value;
			}
			return null;
		}
	}
}
