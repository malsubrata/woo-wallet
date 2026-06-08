<?php
/**
 * Utility file for this plugin.
 *
 * @package StandaleneTech
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! function_exists( 'terawallet_pro_page_callback' ) ) {

	/**
	 * Render the TeraWallet "Go Pro" admin page.
	 *
	 * Convenience alias that instantiates Woo_Wallet_Go_Pro_Page and renders
	 * its `plugin_page()` output. Useful when external code needs to call the
	 * renderer directly (e.g. a top-level callback).
	 *
	 * @return void
	 */
	function terawallet_pro_page_callback() {
		if ( class_exists( 'Woo_Wallet_Go_Pro_Page' ) ) {
			$page = new Woo_Wallet_Go_Pro_Page();
			$page->plugin_page();
		}
	}
}

if ( ! function_exists( 'is_wallet_rechargeable_order' ) ) {

	/**
	 * Check if order contains rechargeable product
	 *
	 * @param WC_Order $order order.
	 * @return boolean
	 */
	function is_wallet_rechargeable_order( $order ) {
		$is_wallet_rechargeable_order = false;
		if ( $order instanceof WC_Order ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$product_id = $item['product_id'];
				if ( get_wallet_rechargeable_product()->get_id() === $product_id ) {
					$is_wallet_rechargeable_order = true;
					break;
				}
			}
		}
		return apply_filters( 'woo_wallet_is_wallet_rechargeable_order', $is_wallet_rechargeable_order, $order );
	}
}

if ( ! function_exists( 'is_wallet_rechargeable_cart' ) ) {

	/**
	 * Check if cart contains rechargeable product
	 *
	 * @return boolean
	 */
	function is_wallet_rechargeable_cart() {
		$is_wallet_rechargeable_cart = false;
		if ( did_action( 'wp_loaded' ) && ! is_null( wc()->cart ) && count( wc()->cart->get_cart() ) > 0 && get_wallet_rechargeable_product() ) {
			foreach ( wc()->cart->get_cart() as $key => $cart_item ) {
				if ( get_wallet_rechargeable_product()->get_id() === $cart_item['product_id'] ) {
					$is_wallet_rechargeable_cart = true;
					break;
				}
			}
		}
		return apply_filters( 'woo_wallet_is_wallet_rechargeable_cart', $is_wallet_rechargeable_cart );
	}
}

if ( ! function_exists( 'get_woowallet_coupon_cashback_amount' ) ) {
	/**
	 * Get coupon cash-back amount from cart.
	 *
	 * @return Float
	 */
	function get_woowallet_coupon_cashback_amount() {
		$coupon_cashback_amount = 0;
		foreach ( WC()->cart->get_applied_coupons() as $code ) {
			$coupon              = new WC_Coupon( $code );
			$_is_coupon_cashback = get_post_meta( $coupon->get_id(), '_is_coupon_cashback', true );
			if ( 'yes' === $_is_coupon_cashback ) {
				$coupon_cashback_amount += WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax );
			}
		}
		return $coupon_cashback_amount;
	}
}

if ( ! function_exists( 'get_woo_wallet_cart_fee_total' ) ) {
	/**
	 * Get total fee amount from cart.
	 *
	 * @return float
	 */
	function get_woo_wallet_cart_fee_total() {
		$fee_amount = 0;
		$fees       = wc()->cart->get_fees();
		if ( $fees ) {
			foreach ( $fees as $fee_key => $fee ) {
				if ( '_via_wallet_partial_payment' !== $fee_key ) {
					$fee_amount += $fee->amount;
				}
			}
		}
		return $fee_amount;
	}
}

if ( ! function_exists( 'woo_wallet_get_partial_payment_tax_mode' ) ) {
	/**
	 * Resolve the partial-payment tax-treatment mode.
	 *
	 * `payment`              — wallet pays goods only; the negative fee is non-taxable
	 *                          and WooCommerce clamps it to the ex-tax subtotal, so the
	 *                          tax portion is charged to the secondary gateway. Correct
	 *                          when top-ups are NOT taxed.
	 * `tax_inclusive_wallet` — wallet pays the full gross including tax; the negative fee
	 *                          is taxable so its own negative tax lets the wallet cover
	 *                          the tax line. Use only when top-ups ARE taxed at load.
	 *
	 * @since 1.6.4
	 * @return string `payment` | `tax_inclusive_wallet`.
	 */
	function woo_wallet_get_partial_payment_tax_mode() {
		$mode = woo_wallet()->settings_api->get_option( 'partial_payment_tax_treatment', '_wallet_settings_general', 'payment' );
		if ( ! in_array( $mode, array( 'payment', 'tax_inclusive_wallet' ), true ) ) {
			$mode = 'payment';
		}
		return apply_filters( 'woo_wallet_partial_payment_tax_mode', $mode );
	}
}

if ( ! function_exists( 'woo_wallet_get_cart_effective_tax_rate' ) ) {
	/**
	 * Effective cart tax rate as a fraction (e.g. 0.10 for 10%).
	 *
	 * Derived from the standard-class rates for the current customer location rather
	 * than the cart tax totals, because this runs inside `woocommerce_cart_calculate_fees`
	 * — before WooCommerce finalises the tax totals for the current pass. Exact for a
	 * single standard rate (the common case); an approximation for mixed-rate carts.
	 *
	 * @since 1.6.4
	 * @return float
	 */
	function woo_wallet_get_cart_effective_tax_rate() {
		if ( ! wc_tax_enabled() || is_null( wc()->cart ) ) {
			return 0.0;
		}
		$pct   = 0.0;
		$rates = WC_Tax::get_rates( '', wc()->cart->get_customer() );
		foreach ( $rates as $rate ) {
			$pct += (float) $rate['rate'];
		}
		return (float) apply_filters( 'woo_wallet_partial_payment_effective_tax_rate', $pct / 100 );
	}
}

if ( ! function_exists( 'woo_wallet_get_cart_ex_tax_clampable_total' ) ) {
	/**
	 * The maximum a negative cart fee can reduce, EXCLUDING tax.
	 *
	 * Mirrors WooCommerce's `WC_Cart_Fees` negative-fee clamp
	 * (`cart_contents_total + running fees + shipping_total`, ex tax — see
	 * woocommerce/woocommerce#28028). Used to cap the partial-payment amount so the
	 * applied "Via wallet" line and the wallet debit can never disagree.
	 *
	 * @since 1.6.4
	 * @return float
	 */
	function woo_wallet_get_cart_ex_tax_clampable_total() {
		if ( is_null( wc()->cart ) ) {
			return 0.0;
		}
		return (float) wc()->cart->get_cart_contents_total() + (float) wc()->cart->get_shipping_total() + (float) get_woo_wallet_cart_fee_total();
	}
}

if ( ! function_exists( 'woo_wallet_get_partial_payment_max_amount' ) ) {
	/**
	 * Ceiling for the partial-payment amount in the active tax mode.
	 *
	 * `payment` mode caps at the ex-tax clampable total; `tax_inclusive_wallet` mode
	 * caps at the full gross (ex-tax total grossed up by the effective tax rate).
	 *
	 * @since 1.6.4
	 * @return float
	 */
	function woo_wallet_get_partial_payment_max_amount() {
		$mode   = woo_wallet_get_partial_payment_tax_mode();
		$ex_tax = woo_wallet_get_cart_ex_tax_clampable_total();
		$cap    = ( 'tax_inclusive_wallet' === $mode ) ? $ex_tax * ( 1 + woo_wallet_get_cart_effective_tax_rate() ) : $ex_tax;
		return (float) apply_filters( 'woo_wallet_partial_payment_max_amount', max( 0.0, $cap ), $mode );
	}
}

if ( ! function_exists( 'get_woowallet_cart_total' ) ) {
	/**
	 * Get WooCommerce cart total.
	 *
	 * @return float
	 */
	function get_woowallet_cart_total() {
		$cart_total = 0;
		if ( ! is_admin() && is_array( wc()->cart->cart_contents ) && count( wc()->cart->cart_contents ) > 0 ) {
			$cart_total = wc()->cart->get_subtotal( 'edit' ) + wc()->cart->get_taxes_total() + wc()->cart->get_shipping_total( 'edit' ) - wc()->cart->get_discount_total() + get_woowallet_coupon_cashback_amount() + get_woo_wallet_cart_fee_total();
		}
		return apply_filters( 'woowallet_cart_total', $cart_total );
	}
}

if ( ! function_exists( 'is_enable_wallet_partial_payment' ) ) {
	/**
	 * Check if enable partial payment.
	 *
	 * @return Boolean
	 */
	function is_enable_wallet_partial_payment() {
		$is_enable  = false;
		$cart_total = get_woowallet_cart_total();
		if ( ! is_wallet_rechargeable_cart() && is_user_logged_in() && ( ( ! is_null( wc()->session ) && wc()->session->get( 'partial_payment_amount', false ) ) || 'on' === woo_wallet()->settings_api->get_option( 'is_auto_deduct_for_partial_payment', '_wallet_settings_general' ) ) && $cart_total >= apply_filters( 'woo_wallet_partial_payment_amount', woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) ) {
			$is_enable = true;
		}
		return apply_filters( 'is_enable_wallet_partial_payment', $is_enable );
	}
}

if ( ! function_exists( 'is_partial_payment_order_item' ) ) {
	/**
	 * Check if order item is partial payment instance.
	 *
	 * @param Int               $item_id item_id.
	 * @param WC_Order_Item_Fee $item item.
	 * @return boolean
	 */
	function is_partial_payment_order_item( $item_id, $item ) {
		if ( get_metadata( 'order_item', $item_id, '_legacy_fee_key', true ) && '_via_wallet_partial_payment' === get_metadata( 'order_item', $item_id, '_legacy_fee_key', true ) ) {
			return true;
		} elseif ( 'via_wallet' === strtolower( str_replace( ' ', '_', $item->get_name( 'edit' ) ) ) ) {
			return true;
		}
		return false;
	}
}

if ( ! function_exists( 'get_order_partial_payment_amount' ) ) {
	/**
	 * Get total partial payment amount from an order.
	 *
	 * @param Int $order_id order_id.
	 * @return Number
	 */
	function get_order_partial_payment_amount( $order_id ) {
		$via_wallet = 0;
		$order      = wc_get_order( $order_id );
		if ( $order ) {
			$line_items_fee = $order->get_items( 'fee' );
			foreach ( $line_items_fee as $item_id => $item ) {
				if ( is_partial_payment_order_item( $item_id, $item ) ) {
					$via_wallet += $item->get_total( 'edit' ) + $item->get_total_tax( 'edit' );
				}
			}
		}
		return apply_filters( 'woo_wallet_order_partial_payment_amount', abs( $via_wallet ), $order_id );
	}
}

if ( ! function_exists( 'update_wallet_partial_payment_session' ) ) {
	/**
	 * Refresh WooCommerce session for partial payment.
	 *
	 * @param float $amount set.
	 */
	function update_wallet_partial_payment_session( $amount = 0 ) {
		if ( ! is_null( wc()->session ) ) {
			wc()->session->set( 'partial_payment_amount', $amount );
		}
	}
}

if ( ! function_exists( 'get_wallet_rechargeable_orders' ) ) {

	/**
	 * Return wallet rechargeable order id.
	 *
	 * @param array $args args.
	 * @return array
	 */
	function get_wallet_rechargeable_orders( $args = array() ) {
		$hpos_enabled = OrderUtil::custom_orders_table_usage_is_enabled();
		if ( $hpos_enabled ) {
			$order_ids = wc_get_orders(
				array(
					'limit'      => -1,
					'meta_query' => array(
						array(
							'key'   => '_wc_wallet_purchase_credited',
							'value' => true,
						),
					),
					'return'     => 'ids',
					'status'     => wc_get_is_paid_statuses(),
				)
			);
		} else {
			$order_ids = wc_get_orders(
				array(
					'limit'       => -1,
					'return'      => 'ids',
					'topuporders' => true,
					'status'      => wc_get_is_paid_statuses(),
				)
			);
		}
		return $order_ids;
	}
}

if ( ! function_exists( 'get_wallet_rechargeable_product' ) ) {

	/**
	 * Get rechargeable product.
	 *
	 * @return WC_Product object
	 */
	function get_wallet_rechargeable_product() {
		Woo_Wallet_Install::cteate_product_if_not_exist();
		return wc_get_product( apply_filters( 'woo_wallet_rechargeable_product_id', get_option( '_woo_wallet_recharge_product' ) ) );
	}
}

if ( ! function_exists( 'set_wallet_transaction_meta' ) ) {

	/**
	 * Insert meta data into transaction meta table
	 *
	 * @global object $wpdb
	 * @param int    $transaction_id transaction_id.
	 * @param string $meta_key meta_key.
	 * @param mixed  $meta_value meta_value.
	 * @param int    $user_id user ID.
	 * @return boolean
	 */
	function set_wallet_transaction_meta( $transaction_id, $meta_key, $meta_value, $user_id = '' ) {
		global $wpdb;
		$meta_key   = wp_unslash( $meta_key );
		$meta_value = wp_unslash( $meta_value );
		$meta_value = maybe_serialize( $meta_value );
		$wpdb->insert( // @codingStandardsIgnoreLine
			"{$wpdb->base_prefix}woo_wallet_transaction_meta",
			array(
				'transaction_id' => $transaction_id,
				'meta_key'       => $meta_key, // @codingStandardsIgnoreLine
				'meta_value'     => $meta_value, // @codingStandardsIgnoreLine
			),
			array(
				'%d',
				'%s',
				'%s',
			)
		);
		$meta_id = $wpdb->insert_id;
		clear_woo_wallet_cache( $user_id );
		return $meta_id;
	}
}

if ( ! function_exists( 'update_wallet_transaction_meta' ) ) {

	/**
	 * Update meta data into transaction meta table
	 *
	 * @global object $wpdb
	 * @param int    $transaction_id transaction_id.
	 * @param string $meta_key meta_key.
	 * @param mixed  $meta_value meta_value.
	 * @param int    $user_id user ID.
	 * @return boolean
	 */
	function update_wallet_transaction_meta( $transaction_id, $meta_key, $meta_value, $user_id = '' ) {
		global $wpdb;
		if ( is_null( $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", array( $transaction_id, $meta_key ) ) ) ) ) { // @codingStandardsIgnoreLine
			return set_wallet_transaction_meta( $transaction_id, $meta_key, $meta_value, $user_id );
		} else {
			$meta_key   = wp_unslash( $meta_key );
			$meta_value = wp_unslash( $meta_value );
			$meta_value = maybe_serialize( $meta_value );
			$status     = $wpdb->update( // @codingStandardsIgnoreLine
				"{$wpdb->base_prefix}woo_wallet_transaction_meta",
				array( 'meta_value' => $meta_value ), // @codingStandardsIgnoreLine
				array(
					'transaction_id' => $transaction_id,
					'meta_key'       => $meta_key, // @codingStandardsIgnoreLine
				),
				array( '%s' ),
				array( '%d', '%s' )
			);
			clear_woo_wallet_cache( $user_id );
			return $status;
		}
	}
}

if ( ! function_exists( 'get_wallet_transaction_meta' ) ) {

	/**
	 * Fetch transaction meta
	 *
	 * @global object $wpdb
	 * @param int    $transaction_id transaction_id.
	 * @param string $meta_key meta_key.
	 * @param mixed  $default The fallback value to return if the data does not exist.
	 *                           Default false.
	 * @return mixed
	 */
	function get_wallet_transaction_meta( $transaction_id, $meta_key, $default = false ) {
		global $wpdb;
		$resualt = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", $transaction_id, $meta_key ) ); // @codingStandardsIgnoreLine
		if ( ! is_null( $resualt ) ) {
			return maybe_unserialize( $resualt );
		} else {
			return $default;
		}
	}
}

if ( ! function_exists( 'get_wallet_transactions' ) ) {

	/**
	 * Get all wallet transactions
	 *
	 * @global object $wpdb
	 * @param array $args args.
	 * @param mixed $output output.
	 * @return Object rows
	 */
	function get_wallet_transactions( $args = array(), $output = OBJECT ) {
		global $wpdb;
		$default_args = array(
			'user_id'         => get_current_user_id(),
			'where'           => array(),
			'where_meta'      => array(),
			'order_by'        => 'transaction_id',
			'order'           => 'DESC',
			'join_type'       => 'INNER',
			'limit'           => '',
			'include_deleted' => false,
			'fields'          => 'all', // Support all | all_with_meta.
			'nocache'         => is_multisite() ? true : false,
		);
		$args         = apply_filters( 'woo_wallet_transactions_query_args', $args );
		$args         = wp_parse_args( $args, $default_args );

		// Since 1.6.3 the semantic kind lives on the `category` column of
		// `woo_wallet_transactions`. Translate the `category` filter into a
		// direct `where` clause on that column — no meta join required.
		// Allowed values still gate the input to keep the surface small;
		// third-party slugs are accepted as long as they pass sanitize_key().
		if ( ! empty( $args['category'] ) ) {
			$raw_cats = is_array( $args['category'] ) ? $args['category'] : explode( ',', $args['category'] );
			$cats     = array();
			foreach ( $raw_cats as $c ) {
				$c = trim( sanitize_key( $c ) );
				if ( '' !== $c ) {
					$cats[] = substr( $c, 0, 32 );
				}
			}
			if ( ! empty( $cats ) ) {
				if ( ! isset( $args['where'] ) || ! is_array( $args['where'] ) ) {
					$args['where'] = array();
				}
				$args['where'][] = array(
					'key'      => 'category',
					'value'    => 1 === count( $cats ) ? $cats[0] : $cats,
					'operator' => 1 === count( $cats ) ? '=' : 'IN',
				);
			}
			unset( $args['category'] );
		}

		extract( $args ); // @codingStandardsIgnoreLine
		// Build query safely: validate identifiers and use prepared statements for values.
		$select = 'SELECT transactions.*';
		$from   = "FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions";

		// Validate and whitelist inputs that become SQL identifiers.
		$allowed_order_cols = array( 'transaction_id', 'user_id', 'amount', 'currency', 'date', 'type', 'category', 'deleted' );
		if ( ! in_array( $order_by, $allowed_order_cols, true ) ) {
			$order_by = 'transaction_id';
		}
		$order = strtoupper( $order ) === 'ASC' ? 'ASC' : 'DESC';

		$join_type_allowed = array( 'INNER', 'LEFT', 'RIGHT' );
		$join_type         = strtoupper( $join_type );
		if ( ! in_array( $join_type, $join_type_allowed, true ) ) {
			$join_type = 'INNER';
		}

		// Sanitize limit: allow either integer or "offset,limit" numeric pattern.
		$limit_sql = '';
		if ( $limit ) {
			if ( is_numeric( $limit ) ) {
				$limit_sql = 'LIMIT ' . absint( $limit );
			} elseif ( is_string( $limit ) && preg_match( '/^\d+,\d+$/', $limit ) ) {
				$limit_sql = 'LIMIT ' . $limit;
			}
		}

		$joins = array();
		if ( ! empty( $where_meta ) ) {
			$joins[] = "{$join_type} JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta AS transaction_meta ON transactions.transaction_id = transaction_meta.transaction_id";
		}

		// Build WHERE clauses and parameter list for $wpdb->prepare.
		$where_clauses = array( '1=1' );
		$params        = array();

		if ( $user_id ) {
			$where_clauses[] = 'transactions.user_id = %d';
			$params[]        = absint( $user_id );
		}

		if ( ! $include_deleted ) {
			$where_clauses[] = 'transactions.deleted = 0';
		}

		// Allowed operators for safety.
		$allowed_ops = array( '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN' );

		// where_meta (meta_key/meta_value) - meta_key is compared with placeholder, meta_value handled safely.
		if ( ! empty( $where_meta ) ) {
			foreach ( $where_meta as $value ) {
				$op = isset( $value['operator'] ) ? strtoupper( $value['operator'] ) : '=';
				if ( ! in_array( $op, $allowed_ops, true ) ) {
					$op = '=';
				}
				$meta_key = isset( $value['key'] ) ? $value['key'] : '';
				if ( 'IN' === $op || 'NOT IN' === $op ) {
					if ( is_array( $value['value'] ) && count( $value['value'] ) ) {
						$placeholders    = implode( ',', array_fill( 0, count( $value['value'] ), '%s' ) );
						$where_clauses[] = "(transaction_meta.meta_key = %s AND transaction_meta.meta_value {$op} ({$placeholders}))";
						$params[]        = $meta_key;
						foreach ( $value['value'] as $v ) {
							$params[] = $v;
						}
					}
				} elseif ( 'LIKE' === $op || 'NOT LIKE' === $op ) {
						$val             = '%' . $wpdb->esc_like( $value['value'] ) . '%';
						$where_clauses[] = "(transaction_meta.meta_key = %s AND transaction_meta.meta_value {$op} %s)";
						$params[]        = $meta_key;
						$params[]        = $val;
				} else {
					$where_clauses[] = "(transaction_meta.meta_key = %s AND transaction_meta.meta_value {$op} %s)";
					$params[]        = $meta_key;
					$params[]        = $value['value'];
				}
			}
		}

		// where on transactions table: validate columns against whitelist.
		if ( ! empty( $where ) ) {
			foreach ( $where as $value ) {
				$op = isset( $value['operator'] ) ? strtoupper( $value['operator'] ) : '=';
				if ( ! in_array( $op, $allowed_ops, true ) ) {
					$op = '=';
				}
				$col = isset( $value['key'] ) ? $value['key'] : '';
				if ( ! in_array( $col, $allowed_order_cols, true ) ) {
					// Skip unknown/unsafe column names.
					continue;
				}

				if ( 'IN' === $op || 'NOT IN' === $op ) {
					if ( is_array( $value['value'] ) && count( $value['value'] ) ) {
						$placeholders    = implode( ',', array_fill( 0, count( $value['value'] ), '%s' ) );
						$where_clauses[] = "transactions.{$col} {$op} ({$placeholders})";
						foreach ( $value['value'] as $v ) {
							$params[] = $v;
						}
					}
				} elseif ( 'LIKE' === $op || 'NOT LIKE' === $op ) {
						$val             = '%' . $wpdb->esc_like( $value['value'] ) . '%';
						$where_clauses[] = "transactions.{$col} {$op} %s";
						$params[]        = $val;
				} else {
					$where_clauses[] = "transactions.{$col} {$op} %s";
					$params[]        = $value['value'];
				}
			}
		}

		if ( ! empty( $after ) || ! empty( $before ) ) {
			$after           = empty( $after ) ? '0000-00-00' : $after;
			$before          = empty( $before ) ? current_time( 'mysql', 1 ) : $before;
			$where_clauses[] = 'transactions.date BETWEEN %s AND %s';
			$params[]        = $after;
			$params[]        = $before;
		}

		$order_by_sql = "ORDER BY transactions.{$order_by} {$order}";

		$wpdb->hide_errors();

		$query = apply_filters( 'woo_wallet_transactions_query', array( $select, $from, implode( ' ', $joins ), 'WHERE ' . implode( ' AND ', $where_clauses ), $order_by_sql, $limit_sql ) );
		$query = implode( ' ', $query );

		if ( ! empty( $params ) ) {
			$query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $params ) );
		}

		$query_hash     = md5( absint( $user_id ) . $query );
		$cached_results = is_array( get_transient( "woo_wallet_transaction_resualts_{$user_id}" ) ) ? get_transient( "woo_wallet_transaction_resualts_{$user_id}" ) : array();

		if ( $nocache || ! isset( $cached_results[ $query_hash ] ) ) {
			// Enable big selects.
			$wpdb->query( 'SET SESSION SQL_BIG_SELECTS=1' ); // @codingStandardsIgnoreLine

			$query_resualts = $wpdb->get_results( $query, $output ); // @codingStandardsIgnoreLine

			if ( 'all_with_meta' === $fields && ! empty( $query_resualts ) ) {
				$transaction_ids = array_map( 'absint', wp_list_pluck( $query_resualts, 'transaction_id' ) );
				$placeholders    = implode( ', ', array_fill( 0, count( $transaction_ids ), '%d' ) );
				$all_meta        = $wpdb->get_results( // @codingStandardsIgnoreLine
					$wpdb->prepare( "SELECT transaction_id, meta_key, meta_value FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id IN ({$placeholders})", ...$transaction_ids ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					OBJECT
				);
				$meta_by_txn_id  = array();
				foreach ( $all_meta as $meta_row ) {
					$meta_by_txn_id[ $meta_row->transaction_id ][] = $meta_row;
				}
				foreach ( $query_resualts as $key => $query_resualt ) {
					$query_resualts[ $key ]->meta = isset( $meta_by_txn_id[ $query_resualt->transaction_id ] ) ? $meta_by_txn_id[ $query_resualt->transaction_id ] : array();
				}
			}
			$cached_results[ $query_hash ] = $query_resualts;
			set_transient( "woo_wallet_transaction_resualts_{$user_id}", $cached_results, DAY_IN_SECONDS );
		}

		$result = $cached_results[ $query_hash ];

		return $result;
	}
}

if ( ! function_exists( 'get_wallet_transactions_count' ) ) {
	/**
	 * Get wallet transactions count.
	 *
	 * Accepts either a scalar user id (legacy callers) or the same `$args`
	 * array shape as `get_wallet_transactions()` so a paginated list endpoint
	 * can report a filter-aware `X-WP-Total`. Keys ignored: `limit`, `order_by`,
	 * `order`, `fields`, `nocache` — they don't affect COUNT(*).
	 *
	 * @global object $wpdb
	 * @param int|array|null $user_id_or_args User id (legacy) or args array.
	 * @param bool           $include_deleted include_deleted (legacy, ignored when array supplied).
	 * @return int total count of transactions.
	 */
	function get_wallet_transactions_count( $user_id_or_args = null, $include_deleted = false ) {
		global $wpdb;

		// Back-compat scalar path. Same SQL as the original implementation.
		if ( ! is_array( $user_id_or_args ) ) {
			$where  = array();
			$params = array();
			if ( $user_id_or_args ) {
				$where[]  = 'user_id = %d';
				$params[] = absint( $user_id_or_args );
			}
			if ( ! $include_deleted ) {
				$where[] = 'deleted = 0';
			}
			$sql = "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions";
			if ( ! empty( $where ) ) {
				$sql .= ' WHERE ' . implode( ' AND ', $where );
			}
			if ( ! empty( $params ) ) {
				$sql = $wpdb->prepare( $sql, ...$params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		}

		$args     = _woo_wallet_normalize_transaction_query_args( $user_id_or_args );
		$compiled = _woo_wallet_build_transaction_where( $args );

		$select = 'SELECT COUNT(DISTINCT transactions.transaction_id)';
		$from   = "FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions";
		$joins  = $compiled['joins'];
		$where  = 'WHERE ' . implode( ' AND ', $compiled['where_clauses'] );
		$params = $compiled['params'];

		$sql = trim( $select . ' ' . $from . ' ' . implode( ' ', $joins ) . ' ' . $where );
		if ( ! empty( $params ) ) {
			$sql = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $sql ), $params ) );
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
	}
}

if ( ! function_exists( '_woo_wallet_normalize_transaction_query_args' ) ) {
	/**
	 * Normalize the args array consumed by `get_wallet_transactions()` and the
	 * filter-aware path of `get_wallet_transactions_count()`. Pure function — no
	 * SQL side effects. Lives next to the query builders so both stay in sync.
	 *
	 * @param array $args Raw args from caller.
	 * @return array Normalized args.
	 */
	function _woo_wallet_normalize_transaction_query_args( array $args ) {
		$defaults = array(
			'user_id'         => 0,
			'user_ids'        => array(),
			'where'           => array(),
			'where_meta'      => array(),
			'category'        => '',
			'after'           => '',
			'before'          => '',
			'include'         => array(),
			'exclude'         => array(),
			'search'          => '',
			'include_deleted' => false,
		);
		$args     = wp_parse_args( $args, $defaults );

		// Since 1.6.3: filter by the first-class `category` column instead of
		// joining `transaction_meta` on `_type`. Mirrors get_wallet_transactions().
		if ( ! empty( $args['category'] ) ) {
			$raw_cats = is_array( $args['category'] ) ? $args['category'] : explode( ',', $args['category'] );
			$cats     = array();
			foreach ( $raw_cats as $c ) {
				$c = trim( sanitize_key( $c ) );
				if ( '' !== $c ) {
					$cats[] = substr( $c, 0, 32 );
				}
			}
			if ( ! empty( $cats ) ) {
				if ( ! isset( $args['where'] ) || ! is_array( $args['where'] ) ) {
					$args['where'] = array();
				}
				$args['where'][] = array(
					'key'      => 'category',
					'value'    => 1 === count( $cats ) ? $cats[0] : $cats,
					'operator' => 1 === count( $cats ) ? '=' : 'IN',
				);
			}
		}
		unset( $args['category'] );

		return $args;
	}
}

if ( ! function_exists( '_woo_wallet_build_transaction_where' ) ) {
	/**
	 * Build the WHERE/JOIN fragments + bound params for a transaction query.
	 * Used by both `get_wallet_transactions_count()` (when given an args array)
	 * and the upcoming admin REST list endpoint. Validates every identifier
	 * against a whitelist and binds every value through %s/%d placeholders.
	 *
	 * @param array $args Normalized args.
	 * @return array { joins: string[], where_clauses: string[], params: array }
	 */
	function _woo_wallet_build_transaction_where( array $args ) {
		global $wpdb;

		$allowed_cols = array( 'transaction_id', 'user_id', 'amount', 'currency', 'original_currency', 'date', 'type', 'category', 'deleted' );
		$allowed_ops  = array( '=', '!=', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN' );

		$joins         = array();
		$where_clauses = array( '1=1' );
		$params        = array();

		if ( ! empty( $args['where_meta'] ) ) {
			$joins[] = "INNER JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta AS transaction_meta ON transactions.transaction_id = transaction_meta.transaction_id";
		}

		// user_id (single) or user_ids (multi). Mutually composable but user_id wins if both set.
		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'transactions.user_id = %d';
			$params[]        = absint( $args['user_id'] );
		} elseif ( ! empty( $args['user_ids'] ) && is_array( $args['user_ids'] ) ) {
			$ids = array_map( 'absint', $args['user_ids'] );
			$ids = array_filter( $ids );
			if ( ! empty( $ids ) ) {
				$ph              = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where_clauses[] = "transactions.user_id IN ({$ph})";
				foreach ( $ids as $id ) {
					$params[] = $id;
				}
			}
		}

		if ( empty( $args['include_deleted'] ) ) {
			$where_clauses[] = 'transactions.deleted = 0';
		}

		// `where` (transactions table). Whitelisted identifiers, prepared values.
		if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
			foreach ( $args['where'] as $value ) {
				$op  = isset( $value['operator'] ) ? strtoupper( $value['operator'] ) : '=';
				$op  = in_array( $op, $allowed_ops, true ) ? $op : '=';
				$col = isset( $value['key'] ) ? $value['key'] : '';
				if ( ! in_array( $col, $allowed_cols, true ) ) {
					continue;
				}
				if ( 'IN' === $op || 'NOT IN' === $op ) {
					if ( is_array( $value['value'] ) && count( $value['value'] ) ) {
						$ph              = implode( ',', array_fill( 0, count( $value['value'] ), '%s' ) );
						$where_clauses[] = "transactions.{$col} {$op} ({$ph})";
						foreach ( $value['value'] as $v ) {
							$params[] = $v;
						}
					}
				} elseif ( 'LIKE' === $op || 'NOT LIKE' === $op ) {
					$where_clauses[] = "transactions.{$col} {$op} %s";
					$params[]        = '%' . $wpdb->esc_like( $value['value'] ) . '%';
				} else {
					$where_clauses[] = "transactions.{$col} {$op} %s";
					$params[]        = $value['value'];
				}
			}
		}

		// where_meta. Joined above; meta_key + meta_value bound as placeholders.
		if ( ! empty( $args['where_meta'] ) && is_array( $args['where_meta'] ) ) {
			foreach ( $args['where_meta'] as $value ) {
				$op       = isset( $value['operator'] ) ? strtoupper( $value['operator'] ) : '=';
				$op       = in_array( $op, $allowed_ops, true ) ? $op : '=';
				$meta_key = isset( $value['key'] ) ? $value['key'] : '';
				if ( 'IN' === $op || 'NOT IN' === $op ) {
					if ( is_array( $value['value'] ) && count( $value['value'] ) ) {
						$ph              = implode( ',', array_fill( 0, count( $value['value'] ), '%s' ) );
						$where_clauses[] = "(transaction_meta.meta_key = %s AND transaction_meta.meta_value {$op} ({$ph}))";
						$params[]        = $meta_key;
						foreach ( $value['value'] as $v ) {
							$params[] = $v;
						}
					}
				} elseif ( 'LIKE' === $op || 'NOT LIKE' === $op ) {
					$where_clauses[] = '(transaction_meta.meta_key = %s AND transaction_meta.meta_value ' . $op . ' %s)';
					$params[]        = $meta_key;
					$params[]        = '%' . $wpdb->esc_like( $value['value'] ) . '%';
				} else {
					$where_clauses[] = "(transaction_meta.meta_key = %s AND transaction_meta.meta_value {$op} %s)";
					$params[]        = $meta_key;
					$params[]        = $value['value'];
				}
			}
		}

		if ( ! empty( $args['after'] ) || ! empty( $args['before'] ) ) {
			$after           = empty( $args['after'] ) ? '0000-00-00' : $args['after'];
			$before          = empty( $args['before'] ) ? current_time( 'mysql', 1 ) : $args['before'];
			$where_clauses[] = 'transactions.date BETWEEN %s AND %s';
			$params[]        = $after;
			$params[]        = $before;
		}

		if ( ! empty( $args['include'] ) && is_array( $args['include'] ) ) {
			$ids = array_filter( array_map( 'absint', $args['include'] ) );
			if ( ! empty( $ids ) ) {
				$ph              = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where_clauses[] = "transactions.transaction_id IN ({$ph})";
				foreach ( $ids as $id ) {
					$params[] = $id;
				}
			}
		}
		if ( ! empty( $args['exclude'] ) && is_array( $args['exclude'] ) ) {
			$ids = array_filter( array_map( 'absint', $args['exclude'] ) );
			if ( ! empty( $ids ) ) {
				$ph              = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
				$where_clauses[] = "transactions.transaction_id NOT IN ({$ph})";
				foreach ( $ids as $id ) {
					$params[] = $id;
				}
			}
		}

		// Free-text search: LIKE on `details`. User-side search (login/email/display_name)
		// is composed by the controller via `user_ids` after a separate WP_User_Query —
		// we don't join wp_users here to keep this builder DB-agnostic to multisite prefix.
		if ( ! empty( $args['search'] ) && is_string( $args['search'] ) ) {
			$where_clauses[] = 'transactions.details LIKE %s';
			$params[]        = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		return array(
			'joins'         => $joins,
			'where_clauses' => $where_clauses,
			'params'        => $params,
		);
	}
}

if ( ! function_exists( 'woo_wallet_get_balance_by_currency' ) ) {
	/**
	 * Per-currency wallet balance breakdown for a user.
	 *
	 * Returns one row per currency present in the user's non-deleted ledger,
	 * plus the base-currency reduction via `Woo_Wallet_Currency_Manager`. Used
	 * by the admin REST `users/{id}/balance` endpoint to render multicurrency
	 * balance tables. The cached `_current_woo_wallet_balance` user meta stays
	 * the single-currency display cache — this is the authoritative breakdown.
	 *
	 * @param int $user_id User id.
	 * @return array {
	 *   user_id: int,
	 *   base_currency: string,
	 *   balance_base: float,
	 *   balance_base_formatted: string,
	 *   by_currency: array<int, array{currency:string,balance:float,formatted:string}>,
	 *   is_locked: bool,
	 * }
	 */
	function woo_wallet_get_balance_by_currency( $user_id ) {
		global $wpdb;
		$user_id = absint( $user_id );

		$rows = array();
		if ( $user_id ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"SELECT currency, COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0) AS balance
					FROM {$wpdb->base_prefix}woo_wallet_transactions
					WHERE user_id = %d AND deleted = 0
					GROUP BY currency",
					$user_id
				)
			);
		}

		$base = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			$base = Woo_Wallet_Currency_Manager::instance()->get_base_currency();
		}
		$manager = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;

		$by_currency  = array();
		$balance_base = 0.0;
		foreach ( (array) $rows as $row ) {
			$cur           = isset( $row->currency ) && '' !== $row->currency ? strtoupper( $row->currency ) : $base;
			$balance       = (float) $row->balance;
			$price_args    = function_exists( 'woo_wallet_wc_price_args' )
				? woo_wallet_wc_price_args( $user_id, array( 'currency' => $cur ) )
				: array( 'currency' => $cur );
			$by_currency[] = array(
				'currency'  => $cur,
				'balance'   => $balance,
				'formatted' => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $balance, $price_args ) ) : (string) $balance,
			);
			$balance_base += $manager ? (float) $manager->convert( $balance, $cur, $base ) : $balance;
		}

		$base_price_args        = function_exists( 'woo_wallet_wc_price_args' )
			? woo_wallet_wc_price_args( $user_id, array( 'currency' => $base ) )
			: array( 'currency' => $base );
		$balance_base_formatted = function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $balance_base, $base_price_args ) ) : (string) $balance_base;

		return array(
			'user_id'                => $user_id,
			'base_currency'          => $base,
			'balance_base'           => $balance_base,
			'balance_base_formatted' => $balance_base_formatted,
			'by_currency'            => $by_currency,
			'is_locked'              => function_exists( 'is_wallet_account_locked' ) ? (bool) is_wallet_account_locked( $user_id ) : false,
		);
	}
}

if ( ! function_exists( 'get_wallet_referrals' ) ) {

	/**
	 * Query the referral tracking table.
	 *
	 * Mirrors get_wallet_transactions(): the ORDER BY column is whitelisted and
	 * every value is bound through $wpdb->prepare(). This is the single read
	 * path for all referral reporting — the customer referral page, the admin
	 * Referral Report screen and the me/referrals REST summary.
	 *
	 * Not cached: referral volume is low and reports must always be fresh.
	 *
	 * @global object $wpdb
	 * @param array $args   Query args. See $default_args below.
	 * @param mixed $output Output type passed to $wpdb->get_results().
	 * @return array|int Rows, or an integer count when 'count' is true.
	 */
	function get_wallet_referrals( $args = array(), $output = OBJECT ) {
		global $wpdb;
		$default_args = array(
			'referrer_id'      => 0,
			'referred_user_id' => null, // null = unfiltered; 0 is a valid filter (anonymous visits).
			'type'             => '',
			'status'           => '',
			'transaction_id'   => 0,
			'order_id'         => 0,
			'after'            => '',
			'before'           => '',
			'order_by'         => 'referral_id',
			'order'            => 'DESC',
			'limit'            => '',
			'count'            => false,
		);
		$args         = apply_filters( 'woo_wallet_referrals_query_args', $args );
		$args         = wp_parse_args( $args, $default_args );

		$table = $wpdb->base_prefix . 'woo_wallet_referrals';

		// Whitelist the ORDER BY column — it cannot be parameterised.
		$allowed_order_cols = array( 'referral_id', 'referrer_id', 'referred_user_id', 'type', 'status', 'amount', 'transaction_id', 'order_id', 'date_created', 'date_credited' );
		$order_by           = in_array( $args['order_by'], $allowed_order_cols, true ) ? $args['order_by'] : 'referral_id';
		$order              = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		// Sanitize limit: allow either an integer or an "offset,limit" numeric pattern.
		$limit_sql = '';
		if ( $args['limit'] ) {
			if ( is_numeric( $args['limit'] ) ) {
				$limit_sql = 'LIMIT ' . absint( $args['limit'] );
			} elseif ( is_string( $args['limit'] ) && preg_match( '/^\d+,\d+$/', $args['limit'] ) ) {
				$limit_sql = 'LIMIT ' . $args['limit'];
			}
		}

		$where  = array( '1=1' );
		$params = array();

		if ( $args['referrer_id'] ) {
			$where[]  = 'referrer_id = %d';
			$params[] = absint( $args['referrer_id'] );
		}
		if ( null !== $args['referred_user_id'] && '' !== $args['referred_user_id'] ) {
			$where[]  = 'referred_user_id = %d';
			$params[] = absint( $args['referred_user_id'] );
		}
		if ( $args['type'] && in_array( $args['type'], array( 'visit', 'signup' ), true ) ) {
			$where[]  = 'type = %s';
			$params[] = $args['type'];
		}
		if ( $args['status'] && in_array( $args['status'], array( 'pending', 'completed', 'rejected' ), true ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}
		if ( $args['transaction_id'] ) {
			$where[]  = 'transaction_id = %d';
			$params[] = absint( $args['transaction_id'] );
		}
		if ( $args['order_id'] ) {
			$where[]  = 'order_id = %d';
			$params[] = absint( $args['order_id'] );
		}
		if ( ! empty( $args['after'] ) || ! empty( $args['before'] ) ) {
			$where[]  = 'date_created BETWEEN %s AND %s';
			$params[] = empty( $args['after'] ) ? '0000-00-00 00:00:00' : $args['after'];
			$params[] = empty( $args['before'] ) ? current_time( 'mysql', 1 ) : $args['before'];
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where );
		$wpdb->hide_errors();

		if ( $args['count'] ) {
			$query = "SELECT COUNT(*) FROM {$table} {$where_sql}";
			if ( ! empty( $params ) ) {
				$query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $params ) );
			}
			return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		$query = "SELECT * FROM {$table} {$where_sql} ORDER BY {$order_by} {$order} {$limit_sql}";
		if ( ! empty( $params ) ) {
			$query = call_user_func_array( array( $wpdb, 'prepare' ), array_merge( array( $query ), $params ) );
		}

		return $wpdb->get_results( $query, $output ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}

if ( ! function_exists( 'get_wallet_referrals_count' ) ) {
	/**
	 * Count referral rows matching the given filter args.
	 *
	 * Thin wrapper over get_wallet_referrals() with 'count' forced on — used by
	 * report pagination and the summary headers.
	 *
	 * @param array $args Same filter args as get_wallet_referrals().
	 * @return int
	 */
	function get_wallet_referrals_count( $args = array() ) {
		$args['count'] = true;
		return (int) get_wallet_referrals( $args );
	}
}

if ( ! function_exists( 'woo_wallet_referral_display_currency' ) ) {
	/**
	 * Currency that referral amounts should be displayed in.
	 *
	 * Referral rewards are stored in the store base currency; every customer and
	 * admin display path reconverts them to the currency the storefront is
	 * currently showing, so the figures track a currency switch.
	 *
	 * @return string ISO 4217 code.
	 */
	function woo_wallet_referral_display_currency() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return Woo_Wallet_Currency_Manager::instance()->get_active_currency();
		}
		$currency = get_option( 'woocommerce_currency' );
		return is_string( $currency ) && '' !== $currency ? strtoupper( $currency ) : 'USD';
	}
}

if ( ! function_exists( 'woo_wallet_referral_convert_amount' ) ) {
	/**
	 * Convert a stored referral amount into the active display currency.
	 *
	 * Fail-open: with no multi-currency provider the amount is returned
	 * unchanged (the conversion manager logs the gap).
	 *
	 * @param float  $amount        Amount in $from_currency.
	 * @param string $from_currency Currency the amount is stored in.
	 * @return float
	 */
	function woo_wallet_referral_convert_amount( $amount, $from_currency ) {
		$from = strtoupper( (string) $from_currency );
		$to   = woo_wallet_referral_display_currency();
		if ( '' === $from || $from === $to ) {
			return (float) $amount;
		}
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return (float) Woo_Wallet_Currency_Manager::instance()->convert( $amount, $from, $to );
		}
		return (float) $amount;
	}
}

if ( ! function_exists( 'woo_wallet_referral_format_amount' ) ) {
	/**
	 * Convert and format a stored referral amount for display.
	 *
	 * @param float  $amount        Amount in $from_currency.
	 * @param string $from_currency Currency the amount is stored in.
	 * @param int    $user_id       Optional user the figure belongs to.
	 * @return string HTML price string in the active display currency.
	 */
	function woo_wallet_referral_format_amount( $amount, $from_currency, $user_id = 0 ) {
		$converted = woo_wallet_referral_convert_amount( $amount, $from_currency );
		return wc_price(
			$converted,
			woo_wallet_wc_price_args( $user_id ? $user_id : '', array( 'currency' => woo_wallet_referral_display_currency() ) )
		);
	}
}

if ( ! function_exists( 'woo_wallet_referral_user_label' ) ) {
	/**
	 * Human label for a referred user id.
	 *
	 * @param int $user_id Referred user id (0 for an anonymous visitor).
	 * @return string
	 */
	function woo_wallet_referral_user_label( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return __( 'Guest visitor', 'woo-wallet' );
		}
		$user = get_userdata( $user_id );
		return $user ? $user->display_name : __( 'Deleted user', 'woo-wallet' );
	}
}

if ( ! function_exists( 'woo_wallet_get_referral_summary' ) ) {
	/**
	 * Aggregate referral figures for one referrer.
	 *
	 * Shared by the customer referral page and the me/referrals REST endpoint
	 * so both surface identical numbers. All money is reconverted to the active
	 * display currency.
	 *
	 * @param int $user_id Referrer.
	 * @return array visitors, signups, pending, earned, legacy_earned, currency.
	 */
	function woo_wallet_get_referral_summary( $user_id ) {
		$user_id = absint( $user_id );

		$earned    = 0.0;
		$completed = get_wallet_referrals(
			array(
				'referrer_id' => $user_id,
				'status'      => 'completed',
			)
		);
		foreach ( (array) $completed as $row ) {
			$earned += woo_wallet_referral_convert_amount( $row->amount, $row->currency );
		}

		// Legacy pre-1.6.2 earnings — stored untagged, treated as base currency.
		$base_currency = class_exists( 'Woo_Wallet_Currency_Manager' )
			? Woo_Wallet_Currency_Manager::instance()->get_base_currency()
			: woo_wallet_referral_display_currency();
		$legacy_raw    = (float) get_user_meta( $user_id, '_woo_wallet_referring_earning', true );

		return array(
			'visitors'      => get_wallet_referrals_count(
				array(
					'referrer_id' => $user_id,
					'type'        => 'visit',
					'status'      => 'completed',
				)
			),
			'signups'       => get_wallet_referrals_count(
				array(
					'referrer_id' => $user_id,
					'type'        => 'signup',
					'status'      => 'completed',
				)
			),
			'pending'       => get_wallet_referrals_count(
				array(
					'referrer_id' => $user_id,
					'type'        => 'signup',
					'status'      => 'pending',
				)
			),
			'earned'        => $earned,
			'legacy_earned' => woo_wallet_referral_convert_amount( $legacy_raw, $base_currency ),
			'currency'      => woo_wallet_referral_display_currency(),
		);
	}
}

if ( ! function_exists( 'get_wallet_transaction' ) ) {
	/**
	 * Get Wallet transactions.
	 *
	 * @param int $transaction_id transaction_id.
	 */
	function get_wallet_transaction( $transaction_id ) {
		global $wpdb;
		$transaction = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = %d", $transaction_id ) ); // @codingStandardsIgnoreLine
		return $transaction;
	}
}

if ( ! function_exists( 'woo_wallet_get_dashboard_url' ) ) {
	/**
	 * Get the My Account wallet dashboard URL.
	 *
	 * Resolves the configurable wallet endpoint slug and returns the full
	 * My Account wallet page URL. Used by the wallet emails (and elsewhere)
	 * to build "View your wallet" links.
	 *
	 * @since 1.6.4
	 * @return string Wallet dashboard URL.
	 */
	function woo_wallet_get_dashboard_url() {
		$endpoint = get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' );
		return apply_filters( 'woo_wallet_dashboard_url', wc_get_account_endpoint_url( $endpoint ) );
	}
}

if ( ! function_exists( 'woo_wallet_get_topup_url' ) ) {
	/**
	 * Get the wallet top-up (recharge) URL.
	 *
	 * Returns the My Account wallet "add" sub-endpoint URL where customers
	 * can recharge their wallet. Used by the wallet emails to build the
	 * "Recharge your wallet" call to action.
	 *
	 * @since 1.6.4
	 * @return string Wallet top-up URL.
	 */
	function woo_wallet_get_topup_url() {
		$endpoint = get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' );
		return apply_filters( 'woo_wallet_topup_url', wc_get_endpoint_url( $endpoint, 'add', wc_get_page_permalink( 'myaccount' ) ) );
	}
}

if ( ! function_exists( 'get_wallet_transaction_type' ) ) {
	/**
	 * Return transaction type by transaction id
	 *
	 * @since 1.2.7
	 * @global object $wpdb
	 * @param int $transaction_id transaction_id.
	 * @return type(string) | false
	 */
	function get_wallet_transaction_type( $transaction_id ) {
		global $wpdb;
		$transaction = $wpdb->get_row( $wpdb->prepare( "SELECT type FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = %d", $transaction_id ) ); // @codingStandardsIgnoreLine
		if ( $transaction ) {
			return $transaction->type;
		}
		return false;
	}
}

if ( ! function_exists( 'update_wallet_transaction' ) ) {
	/**
	 * Update wallet transactions.
	 *
	 * @param int   $transaction_id transaction_id.
	 * @param int   $user_id user_id.
	 * @param array $data data.
	 * @param array $format format.
	 */
	function update_wallet_transaction( $transaction_id, $user_id, $data = array(), $format = null ) {
		global $wpdb;
		$update = false;
		if ( ! empty( $data ) ) {
			$update = $wpdb->update( "{$wpdb->base_prefix}woo_wallet_transactions", $data, array( 'transaction_id' => $transaction_id ), $format, array( '%d' ) ); // @codingStandardsIgnoreLine
			if ( $update ) {
				clear_woo_wallet_cache( $user_id );
			}
		}
		return $update;
	}
}

if ( ! function_exists( 'clear_woo_wallet_cache' ) ) {

	/**
	 * Clear WooCommerce Wallet user transient
	 *
	 * @param int $user_id user_id.
	 */
	function clear_woo_wallet_cache( $user_id = '' ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		delete_transient( "woo_wallet_transaction_resualts_{$user_id}" );
	}
}

if ( ! function_exists( 'get_wallet_cashback_amount' ) ) {

	/**
	 * Get wallet cashback amount.
	 *
	 * @param int $order_id order_id.
	 * @return float
	 */
	function get_wallet_cashback_amount( $order_id = 0 ) {
		_deprecated_function( 'get_wallet_cashback_amount', '1.3.0', 'woo_wallet()->cashback->calculate_cashback()' );
		if ( $order_id ) {
			return woo_wallet()->cashback->calculate_cashback( false, $order_id );
		}
		return woo_wallet()->cashback->calculate_cashback();
	}
}

if ( ! function_exists( 'is_full_payment_through_wallet' ) ) {

	/**
	 * Check if cart eligible for full payment through wallet
	 *
	 * @return boolean
	 */
	function is_full_payment_through_wallet() {
		$is_valid_payment_through_wallet = false;
		$current_wallet_balance          = woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' );
		$total                           = 0;
		$order_id                        = null;
		if ( WC()->cart ) {
			$order_id = absint( get_query_var( 'order-pay' ) );

			// Gets order total from "pay for order" page.
			if ( 0 < $order_id ) {
				$order = wc_get_order( $order_id );
				if ( $order ) {
					$total = (float) $order->get_total();
				}

				// Gets order total from cart/checkout.
			} elseif ( 0 < WC()->cart->total ) {
					$total = (float) get_woowallet_cart_total();
			}
		}

		if ( ! is_admin() && $current_wallet_balance >= $total && ( ! is_wallet_rechargeable_cart() ) ) {
			$is_valid_payment_through_wallet = true;
		}

		if ( $order_id && is_wallet_rechargeable_order( $order ) ) {
			$is_valid_payment_through_wallet = false;
		}

		return apply_filters( 'is_valid_payment_through_wallet', $is_valid_payment_through_wallet );
	}
}

if ( ! function_exists( 'get_all_wallet_users' ) ) {
	/**
	 * Get all wallet users.
	 *
	 * @param bool $exclude_me exclude_me.
	 */
	function get_all_wallet_users( $exclude_me = true ) {
		$args = array(
			'blog_id' => get_current_blog_id(),
			'exclude' => $exclude_me ? array( get_current_user_id() ) : array(),
			'orderby' => 'login',
			'order'   => 'ASC',
		);
		return get_users( $args );
	}
}

if ( ! function_exists( 'get_total_order_cashback_amount' ) ) {

	/**
	 * Get total cashback amount of an order.
	 *
	 * @param int $order_id order_id.
	 * @return float
	 */
	function get_total_order_cashback_amount( $order_id ) {
		$order                 = wc_get_order( $order_id );
		$total_cashback_amount = 0;
		if ( $order ) {
			$transaction_ids = array();

			// Coerce to array — supports both the legacy scalar (pre-1.6.1) and
			// the new array-of-ids format introduced in 1.6.1 (R1).
			foreach ( array( '_general_cashback_transaction_id', '_coupon_cashback_transaction_id' ) as $meta_key ) {
				$val = $order->get_meta( $meta_key, true );
				if ( is_array( $val ) ) {
					foreach ( $val as $id ) {
						if ( $id ) {
							$transaction_ids[] = (int) $id;
						}
					}
				} elseif ( $val ) {
					$transaction_ids[] = (int) $val;
				}
			}

			$transaction_ids = array_unique( array_filter( $transaction_ids ) );

			if ( ! empty( $transaction_ids ) ) {
				$rows = get_wallet_transactions(
					array(
						'user_id' => $order->get_customer_id(),
						'where'   => array(
							array(
								'key'      => 'transaction_id',
								'value'    => $transaction_ids,
								'operator' => 'IN',
							),
						),
					)
				);

				// Every caller (admin order-screen display, cancellation clawback,
				// refund-proportional clawback, recalculation delta) treats the
				// return value as if it were denominated in the order's currency.
				// In single_base mode the credited rows were normalized to base
				// on write, so on a non-base order their raw `amount` is wrong by
				// the exchange rate; in per_currency mode rows usually match the
				// order currency already and the conversion is a no-op. Either
				// way, converting per row into the order currency yields the
				// answer the callers expect.
				$order_currency = strtoupper( (string) $order->get_currency( 'edit' ) );
				$manager        = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;
				foreach ( $rows as $row ) {
					$row_currency           = isset( $row->currency ) && '' !== $row->currency ? strtoupper( $row->currency ) : $order_currency;
					$total_cashback_amount += $manager ? (float) $manager->convert( $row->amount, $row_currency, $order_currency ) : (float) $row->amount;
				}
			}
		}
		return apply_filters( 'woo_wallet_total_order_cashback_amount', $total_cashback_amount );
	}
}

if ( ! function_exists( 'woo_wallet_persistent_cart_update' ) ) {
	/**
	 * Update WooWallet persistent cart to restore cart after recharge wallet.
	 */
	function woo_wallet_persistent_cart_update() {
		if ( get_current_user_id() && apply_filters( 'woo_wallet_persistent_cart_enabled', true ) ) {
			update_user_meta(
				get_current_user_id(),
				'_woo_wallet_persistent_cart_' . get_current_blog_id(),
				get_user_meta( get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true )
			);
		}
	}
}

if ( ! function_exists( 'woo_wallet_persistent_cart_destroy' ) ) {
	/**
	 * Delete WooWallet persistent cart after restoring WooCommerce cart.
	 */
	function woo_wallet_persistent_cart_destroy() {
		if ( get_current_user_id() ) {
			delete_user_meta( get_current_user_id(), '_woo_wallet_persistent_cart_' . get_current_blog_id() );
		}
	}
}

if ( ! function_exists( 'woo_wallet_get_saved_cart' ) ) {
	/**
	 * Get saved WooWallet cart items.
	 *
	 * @return array
	 */
	function woo_wallet_get_saved_cart() {
		$saved_cart = array();

		if ( apply_filters( 'woo_wallet_persistent_cart_enabled', true ) ) {
			$saved_cart_meta = get_user_meta( get_current_user_id(), '_woo_wallet_persistent_cart_' . get_current_blog_id(), true );

			if ( isset( $saved_cart_meta['cart'] ) ) {
				$saved_cart = array_filter( (array) $saved_cart_meta['cart'] );
			}
		}

		return $saved_cart;
	}
}

if ( ! function_exists( 'woo_wallet_wc_price_args' ) ) {
	/**
	 * Get WC price args.
	 *
	 * In single_base mode the default `currency` stays empty so wc_price falls
	 * through to base — preserving 1.5.x behaviour for sites that haven't
	 * migrated to the per-currency ledger model. In per_currency mode the
	 * default becomes the active provider's `get_active_currency()` so balance
	 * pills and transaction rows render with the customer's selected symbol
	 * even when the calling code hasn't been audited to pass an explicit row
	 * currency. Callers that DO pass `'currency' => $tx->currency` still win
	 * via wp_parse_args.
	 *
	 * @param int   $user_id user_id.
	 * @param array $args    Optional overrides forwarded into wp_parse_args.
	 * @return array
	 */
	function woo_wallet_wc_price_args( $user_id = '', $args = array() ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$default_currency = '';
		if (
			apply_filters( 'woo_wallet_enable_per_currency_mode', false )
			&& class_exists( 'Woo_Wallet_Currency_Manager' )
			&& 'per_currency' === woo_wallet()->settings_api->get_option( 'wallet_currency_mode', '_wallet_settings_general', 'single_base' )
		) {
			$active = Woo_Wallet_Currency_Manager::instance()->get_active_currency();
			if ( is_string( $active ) && '' !== $active ) {
				$default_currency = $active;
			}
		}

		$args = apply_filters(
			'woo_wallet_wc_price_args',
			wp_parse_args(
				$args,
				array(
					'ex_tax_label'       => false,
					'currency'           => $default_currency,
					'decimal_separator'  => wc_get_price_decimal_separator(),
					'thousand_separator' => wc_get_price_thousand_separator(),
					'decimals'           => wc_get_price_decimals(),
					'price_format'       => get_woocommerce_price_format(),
				)
			),
			$user_id
		);
		return $args;
	}
}

if ( ! function_exists( 'get_wallet_user_capability' ) ) {
	/**
	 * Wallet user admin capability.
	 *
	 * @return string
	 */
	function get_wallet_user_capability() {
		return apply_filters( 'woo_wallet_user_capability', 'manage_woocommerce' );
	}
}

if ( ! function_exists( 'delete_user_wallet_transactions' ) ) {
	/**
	 * Delete user wallet transactions.
	 *
	 * @param int  $user_id user_id.
	 * @param bool $force_delete force_delete.
	 */
	function delete_user_wallet_transactions( $user_id, $force_delete = false ) {
		global $wpdb;
		if ( ! $force_delete ) {
			$update = $wpdb->update( "{$wpdb->base_prefix}woo_wallet_transactions", array( 'deleted' => 1 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) ); // @codingStandardsIgnoreLine
			if ( $update ) {
				clear_woo_wallet_cache( $user_id );
			}
		} else {
			$user_wallet_transactions = get_wallet_transactions(
				array(
					'user_id'         => $user_id,
					'include_deleted' => true,
				)
			);
			if ( $user_wallet_transactions ) {
				foreach ( $user_wallet_transactions as $transaction ) {
					$wpdb->delete( "{$wpdb->base_prefix}woo_wallet_transactions", array( 'transaction_id' => $transaction->transaction_id ) ); // @codingStandardsIgnoreLine
					$wpdb->delete( "{$wpdb->base_prefix}woo_wallet_transaction_meta", array( 'transaction_id' => $transaction->transaction_id ) ); // @codingStandardsIgnoreLine
				}
			}
			clear_woo_wallet_cache( $user_id );
		}
	}
}

if ( ! function_exists( 'woo_wallet_purge_user_transactions' ) ) {
	/**
	 * Purge a user's wallet transaction history with explicit delete + balance handling.
	 *
	 * Single-lock atomic op. SUM(pre-balance), the delete (soft or hard), and the
	 * optional balancing-row insert all run under GET_LOCK('woo_wallet_lock_user_<id>')
	 * + a MySQL transaction — closing the race window in the legacy bulk-delete path
	 * where a concurrent top-up between the read and the re-credit was silently lost.
	 *
	 * Negative balances are handled symmetrically: a debt of -25 is preserved by
	 * inserting a balancing **debit** of 25 (the legacy path skipped negatives
	 * because $balance was falsy, silently zeroing them out).
	 *
	 * @since 1.6.1
	 *
	 * @param int    $user_id          Target user id.
	 * @param string $delete_mode      'soft' (set deleted=1, recoverable) or 'hard' (DELETE FROM, permanent). Default 'soft'.
	 * @param string $balance_handling 'keep' (insert balancing entry so post-op balance equals pre-op) or 'wipe' (let balance settle to zero). Default 'keep'.
	 * @return array|WP_Error
	 */
	function woo_wallet_purge_user_transactions( $user_id, $delete_mode = 'soft', $balance_handling = 'keep' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( ! $user_id || ! get_userdata( $user_id ) ) {
			return new WP_Error( 'woo_wallet_invalid_user', __( 'Invalid user id.', 'woo-wallet' ) );
		}
		$delete_mode      = 'hard' === $delete_mode ? 'hard' : 'soft';
		$balance_handling = 'wipe' === $balance_handling ? 'wipe' : 'keep';

		$lock_name    = 'woo_wallet_lock_user_' . $user_id;
		$lock_timeout = (int) apply_filters( 'woo_wallet_db_lock_timeout', 5, $user_id );
		$got_lock     = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, $lock_timeout ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( '1' !== $got_lock && 1 !== $got_lock ) {
			return new WP_Error(
				'woo_wallet_lock_timeout',
				/* translators: %d: user id */
				sprintf( __( 'Could not acquire wallet lock for user #%d. Try again.', 'woo-wallet' ), $user_id )
			);
		}

		$pre_balance      = 0.0;
		$balancing_txn_id = 0;
		$caught           = null;

		try {
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

			// Raw ledger SUM — same source-of-truth pattern as recode_transaction() and transfer().
			// Intentionally NOT through apply_filters('woo_wallet_current_balance'); see the comment
			// at class-woo-wallet-wallet.php:1107 for the TOCTOU reasoning.
			$pre_balance = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			if ( 'soft' === $delete_mode ) {
				$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					"{$wpdb->base_prefix}woo_wallet_transactions",
					array( 'deleted' => 1 ),
					array(
						'user_id' => $user_id,
						'deleted' => 0,
					),
					array( '%d' ),
					array( '%d', '%d' )
				);
			} else {
				// Hard delete via existing helper — idempotent, also drops meta rows.
				delete_user_wallet_transactions( $user_id, true );
			}

			$should_insert_balancing = 'keep' === $balance_handling
				&& abs( $pre_balance ) > 0.00001
				&& apply_filters( 'woo_wallet_credit_user_after_delete_log', true );

			if ( $should_insert_balancing ) {
				$is_credit = $pre_balance > 0;
				$row_args  = array(
					'blog_id'           => get_current_blog_id(),
					'user_id'           => $user_id,
					'type'              => $is_credit ? 'credit' : 'debit',
					'amount'            => abs( $pre_balance ),
					'original_amount'   => abs( $pre_balance ),
					'original_currency' => get_woocommerce_currency(),
					'original_rate'     => 1.0,
					'mode'              => 0,
					'currency'          => get_woocommerce_currency(),
					'details'           => __( 'Balance carried over after deleting transaction logs', 'woo-wallet' ),
					'date'              => current_time( 'mysql' ),
					'created_by'        => get_current_user_id(),
				);
				if ( $wpdb->insert( "{$wpdb->base_prefix}woo_wallet_transactions", $row_args, array( '%d', '%d', '%s', '%f', '%f', '%s', '%f', '%d', '%s', '%s', '%s', '%d' ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
					$balancing_txn_id = (int) $wpdb->insert_id;
					update_user_meta( $user_id, '_current_woo_wallet_balance', $is_credit ? abs( $pre_balance ) : -abs( $pre_balance ) );
				}
			} else {
				// Wipe, or keep-but-zero, or filter said don't credit — cached meta must reflect the new ledger.
				update_user_meta( $user_id, '_current_woo_wallet_balance', 0 );
			}

			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$caught = $e;
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( $caught ) {
			return new WP_Error( 'woo_wallet_purge_failed', $caught->getMessage() );
		}

		clear_woo_wallet_cache( $user_id );

		if ( $balancing_txn_id ) {
			// Fired outside the lock so third-party listeners can call back into the
			// ledger without contending on a lock we no longer hold.
			do_action( 'woo_wallet_transaction_recorded', $balancing_txn_id, $user_id, abs( $pre_balance ), $pre_balance > 0 ? 'credit' : 'debit' );
		}

		/**
		 * Fires after woo_wallet_purge_user_transactions() completes for a user.
		 *
		 * @since 1.6.1
		 *
		 * @param int    $user_id          Target user id.
		 * @param string $delete_mode      'soft' or 'hard'.
		 * @param string $balance_handling 'keep' or 'wipe'.
		 * @param float  $pre_balance      Pre-purge ledger balance.
		 * @param int    $balancing_txn_id Inserted balancing-row id, or 0.
		 */
		do_action( 'woo_wallet_user_transactions_purged', $user_id, $delete_mode, $balance_handling, $pre_balance, $balancing_txn_id );

		return array(
			'pre_balance'      => $pre_balance,
			'balancing_txn_id' => $balancing_txn_id,
			'mode'             => $delete_mode,
			'handling'         => $balance_handling,
		);
	}
}

if ( ! function_exists( 'is_wallet_account_locked' ) ) {
	/**
	 * Check if user wallet account is locked.
	 *
	 * @param int $user_id user_id.
	 * @return bool
	 */
	function is_wallet_account_locked( $user_id = '' ) {
		if ( empty( $user_id ) ) {
			$user_id = get_current_user_id();
		}
		return apply_filters( 'woo_wallet_is_user_wallet_locked', get_user_meta( $user_id, '_is_wallet_locked', true ), $user_id );
	}
}

if ( ! function_exists( 'woo_wallet_get_setting' ) ) {
	/**
	 * Read a single field's value from a wallet settings tab — works for the
	 * built-in tabs (`_wallet_settings_general` / `_wallet_settings_credit` /
	 * `_wallet_settings_withdrawal`), legacy PHP-filter-registered tabs, and
	 * JS-registered tabs (`wallet_ext_*`). All three use the same
	 * one-option-per-tab storage convention, so a single helper is enough.
	 *
	 * Canonical read API for third-party plugins extending the settings page —
	 * see docs/EXTENDING_SETTINGS.md.
	 *
	 * @param string $tab_id     Tab/section ID (the WordPress option key).
	 * @param string $field_name Field name within the tab.
	 * @param mixed  $default    Value to return when the field is unset.
	 * @return mixed
	 */
	function woo_wallet_get_setting( $tab_id, $field_name, $default = null ) {
		$tab_id     = sanitize_key( $tab_id );
		$field_name = sanitize_key( $field_name );
		if ( '' === $tab_id || '' === $field_name ) {
			return $default;
		}
		$values = get_option( $tab_id, array() );
		if ( ! is_array( $values ) || ! array_key_exists( $field_name, $values ) ) {
			return $default;
		}
		return $values[ $field_name ];
	}
}

if ( ! function_exists( 'woo_wallet_normalize_legacy_currency_rows' ) ) {

	/**
	 * Normalize legacy non-base-currency ledger rows into the store base currency.
	 *
	 * In single_base mode every ledger row is expected to already be stored in
	 * the base currency, which is what makes the raw `SUM(amount)` authoritative
	 * — and that raw SUM is exactly what the race-safe debit gate in
	 * `Woo_Wallet_Wallet::recode_transaction()` checks. Stores migrated from a
	 * previous per_currency / multicurrency configuration can still carry rows
	 * stamped with a foreign `currency` (and `mode = 1`). The display path papers
	 * over them through `filter_woo_wallet_current_balance` (which re-sums every
	 * row through the converter), but the raw-SUM gate treats those foreign
	 * amounts at face value — so the displayed balance and the spendable balance
	 * diverge and debits between the two figures are silently blocked.
	 *
	 * This converts each non-base row to base through the same converter the
	 * display path uses (matching the figure the customer was already shown),
	 * quantizes it to the store's price decimals, stamps it `mode = 0`, and
	 * records the pre-conversion value in the `original_*` audit columns. A
	 * currency the active provider has no rate for fails open to face value (1:1)
	 * — identical to how the display already valued it — so the result never
	 * diverges from the balance the customer saw. Idempotent / safe to re-run.
	 *
	 * @global object $wpdb wpdb.
	 * @return int|false Number of rows converted (0 when already clean), or false
	 *                   when the work cannot run safely (per_currency mode, or no
	 *                   usable non-generic currency provider) and should be
	 *                   retried on a later request.
	 */
	function woo_wallet_normalize_legacy_currency_rows() {
		global $wpdb;

		// Only single_base stores rely on a base-denominated raw SUM. In
		// per_currency mode the foreign rows are legitimate and per-currency
		// scoped, so leave them untouched.
		if ( apply_filters( 'woo_wallet_enable_per_currency_mode', false )
			&& 'per_currency' === woo_wallet()->settings_api->get_option( 'wallet_currency_mode', '_wallet_settings_general', 'single_base' ) ) {
			return false;
		}

		if ( ! class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return false;
		}

		$manager  = Woo_Wallet_Currency_Manager::instance();
		$provider = $manager->get_active_provider();

		// Without a real provider the foreign->base rate is unknowable; bail so
		// the caller can retry once one is available. Never guess a rate — that
		// would corrupt the ledger with a face-value "conversion".
		if ( ! $provider || 'generic' === $provider->get_id() ) {
			return false;
		}

		$base = strtoupper( (string) $manager->get_base_currency() );
		if ( '' === $base ) {
			return false;
		}

		$table = $wpdb->base_prefix . 'woo_wallet_transactions';

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT transaction_id, user_id, amount, currency, original_amount, original_currency FROM {$table} WHERE currency <> %s", $base ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( empty( $rows ) ) {
			return 0;
		}

		$decimals        = (int) wc_get_price_decimals();
		$converted_count = 0;
		$affected_users  = array();

		foreach ( $rows as $row ) {
			$from = strtoupper( (string) $row->currency );
			if ( $from === $base ) {
				continue;
			}

			// Convert through the manager — the exact same call the display
			// filter (`filter_woo_wallet_current_balance`) uses — so the
			// normalized base amount matches the figure the customer was already
			// shown and could spend. A currency the provider has no rate for
			// fails open to its face value (1:1); that is identical to how the
			// display already valued it, and the manager logs that fallback.
			$converted_raw = $manager->convert( (float) $row->amount, $from, $base );
			if ( ! is_numeric( $converted_raw ) ) {
				$converted_raw = (float) $row->amount;
			}
			$converted = (float) wc_format_decimal( (float) $converted_raw, $decimals );

			// Effective per-unit rate actually applied (1.0 on a fail-open), for
			// the audit column.
			$rate = ( (float) $row->amount !== 0.0 ) ? ( (float) $converted_raw / (float) $row->amount ) : 1.0;

			// Preserve the pre-conversion value as the audit record. Legacy
			// per_currency rows already carry the source amount/currency here, so
			// this is idempotent; we additionally record the real conversion rate.
			$original_amount   = ( null !== $row->original_amount && (float) $row->original_amount > 0 ) ? (float) $row->original_amount : (float) $row->amount;
			$original_currency = ( ! empty( $row->original_currency ) ) ? strtoupper( (string) $row->original_currency ) : $from;

			$updated = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					"UPDATE {$table} SET amount = %s, currency = %s, mode = 0, original_amount = %s, original_currency = %s, original_rate = %s WHERE transaction_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$converted,
					$base,
					$original_amount,
					$original_currency,
					$rate,
					$row->transaction_id
				)
			);

			if ( $updated ) {
				++$converted_count;
				$affected_users[ (int) $row->user_id ] = true;
			}
		}

		// Rebuild the cached balance for every affected user from the now
		// base-denominated raw SUM (the same value recode_transaction caches).
		foreach ( array_keys( $affected_users ) as $uid ) {
			$balance = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0) FROM {$table} WHERE user_id = %d AND deleted = 0", $uid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			update_user_meta( $uid, '_current_woo_wallet_balance', $balance );
			if ( function_exists( 'clear_woo_wallet_cache' ) ) {
				clear_woo_wallet_cache( $uid );
			}
		}

		return $converted_count;
	}
}

if ( ! function_exists( 'woo_wallet_maybe_normalize_legacy_currency_rows' ) ) {

	/**
	 * Drain the one-shot "normalize legacy currency rows" marker.
	 *
	 * The 1.6.4 schema migration cannot do the conversion itself — migrations run
	 * at `plugins_loaded`, before the currency providers register on `init`, so
	 * `convert()` would no-op. Instead the migration sets a pending flag and this
	 * drain runs the normalization from `Woo_Wallet::init()` (right after the
	 * providers are registered), clearing the flag only once the work has
	 * actually completed. Mirrors the deferred-drain pattern used by
	 * `Woo_Wallet_Signup_Handler`.
	 *
	 * @return void
	 */
	function woo_wallet_maybe_normalize_legacy_currency_rows() {
		if ( ! get_option( 'woo_wallet_pending_legacy_currency_normalize' ) ) {
			return;
		}
		$result = woo_wallet_normalize_legacy_currency_rows();
		// Clear the marker only on a definite outcome (an int row count, including
		// 0). A false return means "could not run safely yet" — keep the flag so
		// a later request (with a provider available) retries.
		if ( false !== $result ) {
			delete_option( 'woo_wallet_pending_legacy_currency_normalize' );
		}
	}
}

if ( ! function_exists( 'woo_wallet_get_user_category_total' ) ) {

	/**
	 * Sum a user's transaction `amount` for one type+category, reduced to the
	 * store base currency.
	 *
	 * This is the single source of truth behind the customer dashboard stat
	 * cards and the admin user-report figures (see
	 * `Terawallet_REST_Admin_Users_Controller::sum_category()`, which delegates
	 * here). Rows are read through `get_wallet_transactions()` and each row's
	 * stored `currency` is converted to `$base_currency` via
	 * `Woo_Wallet_Currency_Manager`, so the result is correct in both
	 * `single_base` mode (all rows already base currency — the conversion is a
	 * no-op) and on stores that still carry legacy multicurrency rows.
	 *
	 * payments and partial payments, because the full-payment debit records with
	 * `'for' => 'purchase'`, which the ledger canonicalises to `partial_payment`
	 * (see `Woo_Wallet_Wallet::resolve_category_and_details()`).
	 *
	 * @since 1.6.4
	 * @param int    $user_id       User id.
	 * @param string $type          'credit' or 'debit'.
	 * @param array  $category      Canonical category slug (e.g. topup, partial_payment, cashback).
	 * @param string $base_currency Optional base currency code; resolved from the user balance when empty.
	 * @return float Summed amount in base currency.
	 */
	function woo_wallet_get_user_category_total( $user_id, $type, $category, $base_currency = '' ) {
		$user_id = absint( $user_id );
		$type    = ( 'debit' === $type ) ? 'debit' : 'credit';
		if ( is_string( $category ) ) {
			$category = array( $category );
		} elseif ( ! is_array( $category ) ) {
			$category = array();
		}
		$category = array_filter( array_map( 'sanitize_key', $category ), 'strlen' );
		if ( ! $user_id || empty( $category ) ) {
			return 0.0;
		}

		if ( '' === $base_currency ) {
			$balance       = woo_wallet_get_balance_by_currency( $user_id );
			$base_currency = isset( $balance['base_currency'] ) && '' !== $balance['base_currency']
				? $balance['base_currency']
				: ( function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD' );
		}
		$base_currency = strtoupper( (string) $base_currency );
		$rows          = get_wallet_transactions(
			array(
				'user_id'  => $user_id,
				'fields'   => 'all',
				'nocache'  => true,
				'category' => $category,
				'where'    => array(
					array(
						'key'   => 'type',
						'value' => $type,
					),
				),
			)
		);
		if ( empty( $rows ) ) {
			return 0.0;
		}

		$manager = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;
		$total   = 0.0;
		foreach ( $rows as $row ) {
			$cur    = isset( $row->currency ) && '' !== $row->currency ? strtoupper( $row->currency ) : $base_currency;
			$total += $manager ? (float) $manager->convert( $row->amount, $cur, $base_currency ) : (float) $row->amount;
		}
		return (float) $total;
	}
}

if ( ! function_exists( 'woo_wallet_get_stat_card_icon' ) ) {

	/**
	 * Resolve a dashboard stat-card icon to escape-safe markup.
	 *
	 * Accepts one of the built-in feather-style keys (`arrow-up`, `arrow-down`,
	 * `gift`, `wallet`) returned as a trusted inline SVG; a Dashicons class
	 * (anything starting with `dashicons`) returned as a `<span>`; or an
	 * arbitrary third-party SVG/HTML string, which is sanitised through
	 * `wp_kses()` with an SVG allow-list. The return value is ready to echo
	 * directly — callers must not double-escape it.
	 *
	 * @since 1.6.4
	 * @param string $icon Icon key, dashicons class, or raw SVG markup.
	 * @return string Escape-safe icon markup.
	 */
	function woo_wallet_get_stat_card_icon( $icon ) {
		$icon = is_string( $icon ) ? trim( $icon ) : '';
		if ( '' === $icon ) {
			$icon = 'wallet';
		}

		$paths = array(
			'arrow-up'   => '<line x1="12" y1="19" x2="12" y2="5"></line><polyline points="5 12 12 5 19 12"></polyline>',
			'arrow-down' => '<line x1="12" y1="5" x2="12" y2="19"></line><polyline points="19 12 12 19 5 12"></polyline>',
			'gift'       => '<polyline points="20 12 20 22 4 22 4 12"></polyline><rect x="2" y="7" width="20" height="5"></rect><line x1="12" y1="22" x2="12" y2="7"></line><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"></path><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"></path>',
			'wallet'     => '<rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line>',
		);

		if ( isset( $paths[ $icon ] ) ) {
			return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $icon ] . '</svg>';
		}

		// Dashicons class (e.g. `dashicons-money-alt` or `dashicons dashicons-x`).
		if ( 0 === strpos( $icon, 'dashicons' ) ) {
			$parts = array_filter( array_map( 'sanitize_html_class', explode( ' ', $icon ) ) );
			if ( ! in_array( 'dashicons', $parts, true ) ) {
				array_unshift( $parts, 'dashicons' );
			}
			return '<span class="' . esc_attr( implode( ' ', $parts ) ) . '" aria-hidden="true"></span>';
		}

		// Otherwise treat as third-party-supplied SVG/HTML and sanitise it.
		$allowed = array(
			'svg'      => array(
				'xmlns'           => true,
				'viewbox'         => true,
				'width'           => true,
				'height'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
				'class'           => true,
				'role'            => true,
				'aria-hidden'     => true,
				'focusable'       => true,
			),
			'g'        => array(
				'fill'      => true,
				'stroke'    => true,
				'transform' => true,
			),
			'path'     => array(
				'd'               => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'circle'   => array(
				'cx'           => true,
				'cy'           => true,
				'r'            => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'rect'     => array(
				'x'            => true,
				'y'            => true,
				'width'        => true,
				'height'       => true,
				'rx'           => true,
				'ry'           => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'line'     => array(
				'x1'             => true,
				'y1'             => true,
				'x2'             => true,
				'y2'             => true,
				'stroke'         => true,
				'stroke-width'   => true,
				'stroke-linecap' => true,
			),
			'polyline' => array(
				'points'          => true,
				'fill'            => true,
				'stroke'          => true,
				'stroke-width'    => true,
				'stroke-linecap'  => true,
				'stroke-linejoin' => true,
			),
			'polygon'  => array(
				'points'       => true,
				'fill'         => true,
				'stroke'       => true,
				'stroke-width' => true,
			),
			'ellipse'  => array(
				'cx'     => true,
				'cy'     => true,
				'rx'     => true,
				'ry'     => true,
				'fill'   => true,
				'stroke' => true,
			),
		);
		return wp_kses( $icon, $allowed );
	}
}

if ( ! function_exists( 'woo_wallet_get_dashboard_stat_cards' ) ) {

	/**
	 * Build the ordered set of stat cards shown on the customer wallet dashboard.
	 *
	 * Core ships four cards — total top-ups, total spent, cashback earned (only
	 * when the cashback reward program is enabled), and available balance — each
	 * fed by native wallet helpers. The assembled array is passed through the
	 * `woo_wallet_dashboard_stat_cards` filter so third-party/Pro plugins can
	 * add their own cards (e.g. "Total withdrawn"), then stable-sorted by the
	 * per-card `priority` (ascending; core uses 10/20/30/90).
	 *
	 * Each card is an associative array:
	 *   - id       (string)  Stable identifier.
	 *   - priority (int)     Sort weight, ascending. Default 10.
	 *   - tone     (string)  success|danger|brand|neutral|warning|info|teal|accent.
	 *   - icon     (string)  Core key (arrow-up|arrow-down|gift|wallet), a
	 *                        `dashicons-*` class, or a raw SVG string.
	 *   - label    (string)  Translated card label.
	 *   - value    (string)  Price HTML / display string.
	 *
	 * @since 1.6.4
	 * @param int $user_id User id. Defaults to the current user.
	 * @return array Keyed, priority-sorted card definitions.
	 */
	function woo_wallet_get_dashboard_stat_cards( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$balance = woo_wallet_get_balance_by_currency( $user_id );
		$base    = isset( $balance['base_currency'] ) ? $balance['base_currency'] : '';

		$price_args = function_exists( 'woo_wallet_wc_price_args' )
			? woo_wallet_wc_price_args( $user_id, array( 'currency' => $base ) )
			: array( 'currency' => $base );

		$fmt = function ( $amount ) use ( $price_args ) {
			return function_exists( 'wc_price' ) ? wc_price( $amount, $price_args ) : (string) $amount;
		};

		$cards = array();

		$cards['topup'] = array(
			'id'       => 'topup',
			'priority' => 10,
			'tone'     => 'success',
			'icon'     => 'arrow-up',
			'label'    => __( 'Total top-ups', 'woo-wallet' ),
			'value'    => $fmt( woo_wallet_get_user_category_total( $user_id, 'credit', array( 'topup' ), $base ) ),
		);

		$cards['spent'] = array(
			'id'       => 'spent',
			'priority' => 20,
			'tone'     => 'danger',
			'icon'     => 'arrow-down',
			'label'    => __( 'Total spent', 'woo-wallet' ),
			'value'    => $fmt( woo_wallet_get_user_category_total( $user_id, 'debit', array( 'purchase', 'partial_payment' ), $base ) ),
		);

		if ( 'on' === woo_wallet_get_setting( '_wallet_settings_credit', 'is_enable_cashback_reward_program', 'off' ) ) {
			$cards['cashback'] = array(
				'id'       => 'cashback',
				'priority' => 30,
				'tone'     => 'brand',
				'icon'     => 'gift',
				'label'    => __( 'Cashback earned', 'woo-wallet' ),
				'value'    => $fmt( woo_wallet_get_user_category_total( $user_id, 'credit', array( 'cashback' ), $base ) ),
			);
		}

		$cards['balance'] = array(
			'id'       => 'balance',
			'priority' => 90,
			'tone'     => 'neutral',
			'icon'     => 'wallet',
			'label'    => __( 'Available balance', 'woo-wallet' ),
			'value'    => woo_wallet()->wallet->get_wallet_balance( $user_id ),
		);

		/**
		 * Filter the customer wallet dashboard stat cards.
		 *
		 * @since 1.6.4
		 * @param array $cards   Keyed array of card definitions (see function doc for shape).
		 * @param int   $user_id User the cards are rendered for.
		 */
		$cards = apply_filters( 'woo_wallet_dashboard_stat_cards', $cards, $user_id );
		if ( ! is_array( $cards ) ) {
			return array();
		}

		// Stable sort by priority: decorate with the original sequence so equal
		// priorities keep insertion order (uasort is not stable on its own).
		$seq = 0;
		foreach ( $cards as $key => $card ) {
			if ( ! is_array( $card ) ) {
				unset( $cards[ $key ] );
				continue;
			}
			$cards[ $key ]['_order'] = isset( $card['priority'] ) ? (int) $card['priority'] : 10;
			$cards[ $key ]['_seq']   = $seq++;
		}
		uasort(
			$cards,
			function ( $a, $b ) {
				if ( $a['_order'] === $b['_order'] ) {
					return $a['_seq'] - $b['_seq'];
				}
				return $a['_order'] - $b['_order'];
			}
		);
		foreach ( $cards as $key => $card ) {
			unset( $cards[ $key ]['_order'], $cards[ $key ]['_seq'] );
		}

		return $cards;
	}
}
