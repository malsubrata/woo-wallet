<?php
/**
 * Wallet cashback file.
 *
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Woo_Wallet_Cashback' ) ) {
	/**
	 * Wallet cashback class.
	 */
	class Woo_Wallet_Cashback {

		/**
		 * Cashback rule (cart wise, product wise, product category wise)
		 *
		 * @var string
		 */
		public static $cashback_rule;

		/**
		 * Cashback tyep (percentage or fixed)
		 *
		 * @var string
		 */
		public static $global_cashbak_type;

		/**
		 * Global cashback amount.
		 *
		 * @var float
		 */
		public static $global_cashbak_amount;

		/**
		 * Max cashback amount.
		 *
		 * @var float
		 */
		public static $max_cashbak_amount;

		/**
		 * Class constructor.
		 */
		public function __construct() {
		}

		/**
		 * Init cashback settings.
		 */
		private static function init_cashback_settings() {
			self::$cashback_rule         = woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' );
			self::$global_cashbak_type   = woo_wallet()->settings_api->get_option( 'cashback_type', '_wallet_settings_credit', 'percent' );
			self::$global_cashbak_amount = floatval( woo_wallet()->settings_api->get_option( 'cashback_amount', '_wallet_settings_credit', 0 ) );
			self::$max_cashbak_amount    = floatval( woo_wallet()->settings_api->get_option( 'max_cashback_amount', '_wallet_settings_credit', 0 ) );
		}

		/**
		 * Calculate wallet cashback.
		 *
		 * @param bool $form_cart form_cart.
		 * @param int  $order_id order_id.
		 * @param bool $force force.
		 * @return float
		 */
		public static function calculate_cashback( $form_cart = true, $order_id = 0, $force = false ) {
			self::init_cashback_settings();
			$order   = wc_get_order( $order_id );
			$user_id = get_current_user_id();
			if ( $order ) {
				$user_id = $order->get_customer_id();
			}
			$user         = new WP_User( $user_id );
			$exclude_role = woo_wallet()->settings_api->get_option( 'exclude_role', '_wallet_settings_credit', array() );
			if ( 'on' !== woo_wallet()->settings_api->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit' ) ) {
				return 0;
			}
			if ( $user && ! empty( $user->roles ) && ! array_diff( $user->roles, $exclude_role ) ) {
				return 0;
			}
			if ( ! $form_cart && ! $order_id ) {
				return 0;
			}
			if ( $form_cart && is_admin() ) {
				return 0;
			}
			if ( ! $form_cart && ! $force ) {
				return $order->get_meta( '_wallet_cashback' ) ? $order->get_meta( '_wallet_cashback' ) : self::calculate_cashback_form_order( $order_id );
			}
			if ( $form_cart ) {
				return self::calculate_cashback_form_cart();
			}
			if ( $force ) {
				return self::calculate_cashback_form_order( $order_id );
			}
			return 0;
		}

		/**
		 * Calculate cashback form cart.
		 *
		 * When `max_cashback_scope` is `per_order` the global maximum cap is applied
		 * once after all line amounts have been summed (post-loop cap). When it is
		 * `per_item` (the pre-1.6.1 default for upgraded sites) the cap was already
		 * applied per line inside `get_product_cashback_amount` /
		 * `get_product_category_wise_cashback_amount`, so no second cap is needed.
		 *
		 * @return float
		 *
		 * @since 1.6.1 Added post-loop cap logic for per_order scope (R6).
		 */
		private static function calculate_cashback_form_cart() {
			$cashback_amount   = 0;
			$max_cashback_scope = woo_wallet()->settings_api->get_option( 'max_cashback_scope', '_wallet_settings_credit', 'per_order' );

			if ( is_wallet_rechargeable_cart() ) {
				return $cashback_amount;
			}
			switch ( self::$cashback_rule ) {
				case 'product':
					if ( count( wc()->cart->get_cart() ) > 0 ) {
						foreach ( wc()->cart->get_cart() as $key => $cart_item ) {
							$product_id       = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
							$product          = wc_get_product( $product_id );
							$qty              = $cart_item['quantity'];
							$cashback_amount += self::get_product_cashback_amount( $product, $qty, $cart_item['line_subtotal'] / $qty, $max_cashback_scope );
						}
					}
					break;
				case 'product_cat':
					if ( count( wc()->cart->get_cart() ) > 0 ) {
						foreach ( wc()->cart->get_cart() as $key => $cart_item ) {
							$product_id       = $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];
							$product          = wc_get_product( $product_id );
							$qty              = $cart_item['quantity'];
							$cashback_amount += self::get_product_category_wise_cashback_amount( $product, $qty, $cart_item['line_subtotal'] / $qty, $max_cashback_scope );
						}
					}
					break;
				case 'cart':
					if ( 0 !== woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 10 ) && WC()->cart->get_total( 'edit' ) >= woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 0 ) ) {
						if ( 'percent' === self::$global_cashbak_type ) {
							$total                   = apply_filters( 'woo_wallet_calculate_cashback_on_total', true ) ? wc()->cart->get_total( 'edit' ) : wc()->cart->get_subtotal();
							$percent_cashback_amount = $total * ( self::$global_cashbak_amount / 100 );
							if ( self::$max_cashbak_amount && $percent_cashback_amount > self::$max_cashbak_amount ) {
								$cashback_amount += self::$max_cashbak_amount;
							} else {
								$cashback_amount += $percent_cashback_amount;
							}
						} else {
							$cashback_amount += self::$global_cashbak_amount;
						}
					}
					break;
			}

			// Post-loop per_order cap: apply the global maximum once across the full cart sum.
			// `per_item` scope already applied the cap inside each per-line helper.
			if ( 'per_order' === $max_cashback_scope && 'cart' !== self::$cashback_rule && self::$max_cashbak_amount && $cashback_amount > self::$max_cashbak_amount ) {
				$cashback_amount = self::$max_cashbak_amount;
			}

			return apply_filters( 'woo_wallet_form_cart_cashback_amount', $cashback_amount );
		}

		/**
		 * Calculate cashback form order.
		 *
		 * When `max_cashback_scope` is `per_order` the global cap is applied once
		 * after all line amounts have been summed (post-loop). For `per_item` scope
		 * the cap was already applied per line inside the per-product/category
		 * helpers, so no second cap is applied here.
		 *
		 * @param int $order_id order_id.
		 * @return float
		 *
		 * @since 1.6.1 Added post-loop cap (R6) and max_cashback_scope awareness.
		 */
		private static function calculate_cashback_form_order( $order_id = 0 ) {
			$cashback_amount   = 0;
			$order             = wc_get_order( $order_id );
			$max_cashback_scope = woo_wallet()->settings_api->get_option( 'max_cashback_scope', '_wallet_settings_credit', 'per_order' );

			if ( ! $order || is_wallet_rechargeable_order( $order ) ) {
				return $cashback_amount;
			}
			switch ( self::$cashback_rule ) {
				case 'product':
					if ( count( $order->get_items() ) > 0 ) {
						foreach ( $order->get_items() as $item_id => $item ) {
							$product_id       = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
							$product          = wc_get_product( $product_id );
							$qty              = $item->get_quantity();
							$cashback_amount += self::get_product_cashback_amount( $product, $qty, (float) $order->get_item_subtotal( $item, false, true ), $max_cashback_scope );
						}
					}
					break;
				case 'product_cat':
					if ( count( $order->get_items() ) > 0 ) {
						foreach ( $order->get_items() as $item_id => $item ) {
							$product_id       = $item->get_variation_id() ? $item->get_variation_id() : $item->get_product_id();
							$product          = wc_get_product( $product_id );
							$qty              = $item->get_quantity();
							$cashback_amount += self::get_product_category_wise_cashback_amount( $product, $qty, (float) $order->get_item_subtotal( $item, false, true ), $max_cashback_scope );
						}
					}
					break;
				case 'cart':
					if ( 0 !== woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 10 ) && $order->get_total( 'edit' ) >= woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 0 ) ) {
						if ( 'percent' === self::$global_cashbak_type ) {
							$total                   = apply_filters( 'woo_wallet_calculate_cashback_on_total', true ) ? $order->get_total( 'edit' ) : $order->get_subtotal();
							$percent_cashback_amount = ( $total * self::$global_cashbak_amount ) / 100;
							if ( self::$max_cashbak_amount && $percent_cashback_amount > self::$max_cashbak_amount ) {
								$cashback_amount += self::$max_cashbak_amount;
							} else {
								$cashback_amount += $percent_cashback_amount;
							}
						} else {
							$cashback_amount += self::$global_cashbak_amount;
						}
					}
					break;
			}

			// Post-loop per_order cap (R6): apply the global maximum once across the full order sum.
			if ( 'per_order' === $max_cashback_scope && 'cart' !== self::$cashback_rule && self::$max_cashbak_amount && $cashback_amount > self::$max_cashbak_amount ) {
				$cashback_amount = self::$max_cashbak_amount;
			}

			$cashback_amount = apply_filters( 'woo_wallet_form_order_cashback_amount', $cashback_amount, $order_id );
			WOO_Wallet_Helper::update_order_meta_data( $order, '_wallet_cashback', $cashback_amount );
			return $cashback_amount;
		}

		/**
		 * Get cashback from a specific product.
		 *
		 * When `$scope` is `per_item` the per-line maximum cap is applied here
		 * (pre-1.6.1 behaviour, preserved for upgraded sites). When `$scope` is
		 * `per_order` the cap is skipped here and applied once in the calling
		 * loop after all lines are summed.
		 *
		 * @param WC_Product $product        product.
		 * @param int        $qty            qty.
		 * @param float      $product_price  product_price.
		 * @param string     $scope          'per_item' | 'per_order'. Defaults to 'per_order'.
		 * @return float
		 *
		 * @since 1.6.1 Added $scope param (R6). Existing callers without the param get 'per_order'.
		 */
		public static function get_product_cashback_amount( $product, $qty = 1, $product_price = 0, $scope = 'per_order' ) {
			self::init_cashback_settings();
			$cashback_amount = 0;
			if ( ! $product ) {
				return $cashback_amount;
			}
			$apply_per_line_cap = ( 'per_item' === $scope );

			$product_wise_cashback_type   = get_post_meta( $product->get_id(), '_cashback_type', true );
			$product_wise_cashback_amount = get_post_meta( $product->get_id(), '_cashback_amount', true ) ? floatval( get_post_meta( $product->get_id(), '_cashback_amount', true ) ) : 0;
			if ( ! $product_price ) {
				if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
					$product_price = wc_get_price_including_tax( $product );
				} else {
					$product_price = wc_get_price_excluding_tax( $product );
				}
			}
			if ( $product_price ) {
				if ( $product_wise_cashback_type && $product_wise_cashback_amount ) {
					if ( 'percent' === $product_wise_cashback_type ) {
						$product_wise_percent_cashback_amount = $product_price * $qty * ( $product_wise_cashback_amount / 100 );
						if ( $apply_per_line_cap && self::$max_cashbak_amount && $product_wise_percent_cashback_amount > self::$max_cashbak_amount ) {
							$cashback_amount += self::$max_cashbak_amount;
						} else {
							$cashback_amount += $product_wise_percent_cashback_amount;
						}
					} else {
						$cashback_amount += $product_wise_cashback_amount * $qty;
					}
				} elseif ( 'percent' === self::$global_cashbak_type ) {
						$product_wise_percent_cashback_amount = $product_price * $qty * ( self::$global_cashbak_amount / 100 );
					if ( $apply_per_line_cap && self::$max_cashbak_amount && $product_wise_percent_cashback_amount > self::$max_cashbak_amount ) {
						$cashback_amount += self::$max_cashbak_amount;
					} else {
						$cashback_amount += $product_wise_percent_cashback_amount;
					}
				} else {
					$cashback_amount += self::$global_cashbak_amount * $qty;
				}
			}
			return apply_filters( 'woo_wallet_product_wise_cashback_amount', $cashback_amount, $product, $qty, $product_price );
		}

		/**
		 * Calculate cashback of a product depending on product category.
		 *
		 * When `$scope` is `per_item` the per-line maximum cap is applied here
		 * (pre-1.6.1 behaviour). When `$scope` is `per_order` the cap is skipped
		 * here and applied once after the full loop in the calling method.
		 *
		 * @param WC_Product $product       product.
		 * @param int        $qty           qty.
		 * @param float      $product_price product_price.
		 * @param string     $scope         'per_item' | 'per_order'. Defaults to 'per_order'.
		 * @return float
		 *
		 * @since 1.6.1 Added $scope param (R6).
		 */
		public static function get_product_category_wise_cashback_amount( $product, $qty = 1, $product_price = 0, $scope = 'per_order' ) {
			self::init_cashback_settings();
			$cashback_amount    = 0;
			$apply_per_line_cap = ( 'per_item' === $scope );

			if ( $product->get_parent_id( 'edit' ) ) {
				$term_ids = wc_get_product( $product->get_parent_id( 'edit' ) )->get_category_ids( 'edit' );
			} else {
				$term_ids = $product->get_category_ids( 'edit' );
			}
			$category_wise_cashback_amounts = array();
			if ( ! $product_price ) {
				if ( 'incl' === get_option( 'woocommerce_tax_display_cart' ) ) {
					$product_price = wc_get_price_including_tax( $product );
				} else {
					$product_price = wc_get_price_excluding_tax( $product );
				}
			}
			if ( $product_price ) {
				if ( ! empty( $term_ids ) ) {
					foreach ( $term_ids as $term_id ) {
						$category_wise_cashback_type   = get_term_meta( $term_id, '_woo_cashback_type', true );
						$category_wise_cashback_amount = get_term_meta( $term_id, '_woo_cashback_amount', true );
						if ( $category_wise_cashback_type && $category_wise_cashback_amount ) {
							if ( 'percent' === $category_wise_cashback_type ) {
								$category_wise_cashback_amount = $product_price * $qty * ( $category_wise_cashback_amount / 100 );
								if ( $apply_per_line_cap && self::$max_cashbak_amount && $category_wise_cashback_amount > self::$max_cashbak_amount ) {
									$category_wise_cashback_amount = self::$max_cashbak_amount;
								}
							}
							$category_wise_cashback_amounts[] = $category_wise_cashback_amount;
						}
					}
				}
				if ( ! empty( $category_wise_cashback_amounts ) ) {
					$cashback_amount += ( 'on' === woo_wallet()->settings_api->get_option( 'allow_min_cashback', '_wallet_settings_credit', 'on' ) ) ? min( $category_wise_cashback_amounts ) : max( $category_wise_cashback_amounts );
				} elseif ( 'percent' === self::$global_cashbak_type ) {
						$category_wise_cashback_amount = $product_price * $qty * ( self::$global_cashbak_amount / 100 );
					if ( $apply_per_line_cap && self::$max_cashbak_amount && $category_wise_cashback_amount > self::$max_cashbak_amount ) {
						$cashback_amount += self::$max_cashbak_amount;
					} else {
						$cashback_amount += $category_wise_cashback_amount;
					}
				} else {
					$cashback_amount += self::$global_cashbak_amount;
				}
			}
			return apply_filters( 'woo_wallet_product_category_wise_cashback_amount', $cashback_amount, $product, $qty, $product_price );
		}
	}

}
