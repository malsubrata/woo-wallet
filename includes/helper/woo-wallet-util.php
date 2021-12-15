<?php

if ( ! function_exists( 'is_wallet_rechargeable_order' ) ) {

    /**
     * Check if order contains rechargeable product
     * @param WC_Order object $order
     * @return boolean
     */
    function is_wallet_rechargeable_order( $order ) {
        $is_wallet_rechargeable_order = false;
        if( $order instanceof WC_Order){
            foreach ( $order->get_items( 'line_item' ) as $item ) {
                $product_id = $item['product_id'];
                if ( $product_id == get_wallet_rechargeable_product()->get_id() ) {
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
     * @return boolean
     */
    function is_wallet_rechargeable_cart() {
        $is_wallet_rechargeable_cart = false;
        if ( ! is_null( wc()->cart) && sizeof( wc()->cart->get_cart() ) > 0 && get_wallet_rechargeable_product() ) {
            foreach ( wc()->cart->get_cart() as $key => $cart_item ) {
                if ( $cart_item['product_id'] == get_wallet_rechargeable_product()->get_id() ) {
                    $is_wallet_rechargeable_cart = true;
                    break;
                }
            }
        }
        return apply_filters( 'woo_wallet_is_wallet_rechargeable_cart', $is_wallet_rechargeable_cart);
    }

}

if( !function_exists( 'get_woowallet_coupon_cashback_amount' ) ){
    /**
     * Get coupon cash-back amount from cart.
     * @return Number
     */
    function get_woowallet_coupon_cashback_amount(){
        $coupon_cashback_amount = 0;
        foreach (WC()->cart->get_applied_coupons() as $code) {
            $coupon = new WC_Coupon( $code);
            $_is_coupon_cashback = get_post_meta( $coupon->get_id(), '_is_coupon_cashback', true );
            if ( 'yes' === $_is_coupon_cashback) {
                $coupon_cashback_amount += WC()->cart->get_coupon_discount_amount( $code, WC()->cart->display_cart_ex_tax);
            }
        }
        return $coupon_cashback_amount;
    }
    
}

if(!function_exists('get_woo_wallet_cart_fee_total')){
    /**
     * Get total fee amount from cart.
     * @return number
     */
    function get_woo_wallet_cart_fee_total(){
        $fee_amount = 0;
        $fees = wc()->cart->get_fees();
        if($fees){
            foreach ($fees as $fee_key => $fee){
                if('_via_wallet_partial_payment' != $fee_key){
                    $fee_amount += $fee->amount;
                }
            }
        }
        return $fee_amount;
    }
}

if ( ! function_exists( 'get_woowallet_cart_total' ) ) {
    /**
     * Get WooCommerce cart total.
     * @return number
     */
    function get_woowallet_cart_total() {
        $cart_total = 0;
        if ( !is_admin() && is_array( wc()->cart->cart_contents) && sizeof( wc()->cart->cart_contents) > 0 ) {
            $cart_total = wc()->cart->get_subtotal( 'edit' ) + wc()->cart->get_taxes_total() + wc()->cart->get_shipping_total( 'edit' ) - wc()->cart->get_discount_total() + get_woowallet_coupon_cashback_amount() + get_woo_wallet_cart_fee_total();
        }
        return apply_filters( 'woowallet_cart_total', $cart_total );
    }

}

if ( ! function_exists( 'is_enable_wallet_partial_payment' ) ) {
    /**
     * Check if enable partial payment.
     * @return Boolean
     */
    function is_enable_wallet_partial_payment() {
        $is_enable = false;
        $cart_total = get_woowallet_cart_total();
        if ( ! is_wallet_rechargeable_cart() && is_user_logged_in() && ( ( ! is_null( wc()->session) && wc()->session->get( 'is_wallet_partial_payment', false ) ) || 'on' === woo_wallet()->settings_api->get_option( 'is_auto_deduct_for_partial_payment', '_wallet_settings_general' ) ) && $cart_total >= apply_filters( 'woo_wallet_partial_payment_amount', woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) ) {
            $is_enable = true;
        }
        return apply_filters( 'is_enable_wallet_partial_payment', $is_enable);
    }

}

if( !function_exists( 'is_partial_payment_order_item' ) ){
    /**
     * Check if order item is partial payment instance.
     * @param Int $item_id
     * @param WC_Order_Item_Fee $item
     * @return boolean
     */
    function is_partial_payment_order_item($item_id, $item){
        if( get_metadata( 'order_item', $item_id, '_legacy_fee_key', true ) && '_via_wallet_partial_payment' === get_metadata( 'order_item', $item_id, '_legacy_fee_key', true ) ){
            return true;
        }
        else if ( 'via_wallet' === strtolower(str_replace( ' ', '_', $item->get_name( 'edit' ) ) ) ) {
            return true;
        }
        return false;
    }
    
}

if ( ! function_exists( 'get_order_partial_payment_amount' ) ) {
    /**
     * Get total partial payment amount from an order.
     * @param Int $order_id
     * @return Number
     */
    function get_order_partial_payment_amount( $order_id ) {
        $via_wallet = 0;
        $order = wc_get_order( $order_id );
        if ( $order ) {
            $line_items_fee = $order->get_items( 'fee' );
            foreach ( $line_items_fee as $item_id => $item ) {
                if(is_partial_payment_order_item($item_id, $item)){
                    $via_wallet += $item->get_total( 'edit' ) + $item->get_total_tax( 'edit' );
                }
            }
        }
        return apply_filters('woo_wallet_order_partial_payment_amount', abs( $via_wallet ), $order_id);
    }

}

if ( ! function_exists( 'update_wallet_partial_payment_session' ) ) {
    /**
     * Refresh WooCommerce session for partial payment.
     * @param boolean $set
     */
    function update_wallet_partial_payment_session( $set = false ) {
        if(!is_null(wc()->session)){
            wc()->session->set( 'is_wallet_partial_payment', $set );
        }
    }

}

if ( ! function_exists( 'get_wallet_rechargeable_orders' ) ) {

    /**
     * Return wallet rechargeable order id 
     * @return array
     */
    function get_wallet_rechargeable_orders( $args = array() ) {
        $default_args = array(
            'posts_per_page'   => -1,
            'meta_key'         => '_wc_wallet_purchase_credited',
            'meta_value'       => true,
            'post_type'        => 'shop_order',
            'post_status'      => array( 'completed', 'processing', 'on-hold' ),
            'suppress_filters' => true
        );
        $args = wp_parse_args( $args, $default_args );
        $orders = get_posts( $args );
        return wp_list_pluck( $orders, 'ID' );
    }

}

if ( ! function_exists( 'get_wallet_rechargeable_product' ) ) {

    /**
     * get rechargeable product
     * @return WC_Product object
     */
    function get_wallet_rechargeable_product() {
        Woo_Wallet_Install::cteate_product_if_not_exist();
        return wc_get_product(apply_filters( 'woo_wallet_rechargeable_product_id', get_option( '_woo_wallet_recharge_product' ) ) );
    }

}

if ( ! function_exists( 'set_wallet_transaction_meta' ) ) {

    /**
     * Insert meta data into transaction meta table
     * @global object $wpdb
     * @param int $transaction_id
     * @param string $meta_key
     * @param mixed $meta_value
     * @return boolean
     */
    function set_wallet_transaction_meta( $transaction_id, $meta_key, $meta_value, $user_id = '' ) {
        global $wpdb;
        $meta_key   = wp_unslash( $meta_key );
        $meta_value = wp_unslash( $meta_value );
        $meta_value = maybe_serialize( $meta_value );
        $wpdb->insert( "{$wpdb->base_prefix}woo_wallet_transaction_meta", array( "transaction_id" => $transaction_id, "meta_key" => $meta_key, "meta_value" => $meta_value ) );
        $meta_id = $wpdb->insert_id;
        clear_woo_wallet_cache( $user_id );
        return $meta_id;
    }

}

if ( ! function_exists( 'update_wallet_transaction_meta' ) ) {

    /**
     * Update meta data into transaction meta table
     * @global object $wpdb
     * @param int $transaction_id
     * @param string $meta_key
     * @param mixed $meta_value
     * @return boolean
     */
    function update_wallet_transaction_meta( $transaction_id, $meta_key, $meta_value, $user_id = '' ) {
        global $wpdb;
        if ( is_null( $wpdb->get_var( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", array( $transaction_id, $meta_key ) ) ) ) ) {
            return set_wallet_transaction_meta( $transaction_id, $meta_key, $meta_value, $user_id );
        } else {
            $meta_key   = wp_unslash( $meta_key );
            $meta_value = wp_unslash( $meta_value );
            $meta_value = maybe_serialize( $meta_value );
            $status     = $wpdb->update( "{$wpdb->base_prefix}woo_wallet_transaction_meta", array( 'meta_value' => $meta_value ), array( 'transaction_id' => $transaction_id, "meta_key" => $meta_key ), array( '%s' ), array( '%d', '%s' ) );
            clear_woo_wallet_cache( $user_id );
            return $status;
        }
    }

}

if ( ! function_exists( 'get_wallet_transaction_meta' ) ) {

    /**
     * Fetch transaction meta
     * @global object $wpdb
     * @param int $transaction_id
     * @param string $meta_key
     * @param boolean $single
     * @return boolean
     */
    function get_wallet_transaction_meta( $transaction_id, $meta_key, $single = true ) {
        global $wpdb;
        $resualt = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", $transaction_id, $meta_key ) );
        if ( ! is_null( $resualt ) ) {
            return maybe_unserialize( $resualt );
        } else {
            return false;
        }
    }

}

if ( ! function_exists( 'get_wallet_transactions' ) ) {

    /**
     * Get all wallet transactions
     * @global object $wpdb
     * @param array $args
     * @param mixed $output
     * @return db rows
     */
    function get_wallet_transactions( $args = array(), $output = OBJECT) {
        global $wpdb;
        $default_args = array(
            'user_id'    => get_current_user_id(),
            'where'      => array(),
            'where_meta' => array(),
            'order_by'   => 'transaction_id',
            'order'      => 'DESC',
            'join_type'  => 'INNER',
            'limit'      => '',
            'include_deleted' => false,
            'nocache'    => is_multisite() ? true : false
        );
        $args = apply_filters( 'woo_wallet_transactions_query_args', $args );
        $args = wp_parse_args( $args, $default_args );
        extract( $args );
        $query           = array();
        $query['select'] = "SELECT transactions.*";
        $query['from']   = "FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions";
        // Joins
        $joins = array();
        if ( ! empty( $where_meta ) ) {
            $joins["order_items"] = "{$join_type} JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta AS transaction_meta ON transactions.transaction_id = transaction_meta.transaction_id";
        }
        $query['join'] = implode( ' ', $joins );

        $query['where']  = "WHERE transactions.user_id = {$user_id}";
        if(!$include_deleted){
            $query['where'] .= " AND transactions.deleted = 0";
        }

        if ( ! empty( $where_meta ) ) {
            foreach ( $where_meta as $value ) {
                if ( ! isset( $value['operator'] ) ) {
                    $value['operator'] = '=';
                }
                $query['where'] .= " AND (transaction_meta.meta_key = '{$value['key']}' AND transaction_meta.meta_value {$value['operator']} '{$value['value']}' )";
            }
        }

        if ( ! empty( $where ) ) {
            foreach ( $where as $value ) {
                if ( ! isset( $value['operator'] ) ) {
                    $value['operator'] = '=';
                }
                if ( $value['operator'] == 'IN' && is_array( $value['value'] ) ) {
                    $value['value']  = implode( ',', $value['value'] );
                    $query['where'] .= " AND transactions.{$value['key']} {$value['operator']} ({$value['value']})";
                } else {
                    $query['where'] .= " AND transactions.{$value['key']} {$value['operator']} '{$value['value']}'";
                }
            }
        }

        if ( ! empty( $after) || ! empty( $before) ) {
            $after           = empty( $after) ? '0000-00-00' : $after;
            $before          = empty( $before) ? current_time( 'mysql', 1 ) : $before;
            $query['where'] .= " AND ( transactions.date BETWEEN STR_TO_DATE( '" . $after . "', '%Y-%m-%d %H:%i:%s' ) AND STR_TO_DATE( '" . $before . "', '%Y-%m-%d %H:%i:%s' ))";
        }

        if ( $order_by) {
            $query['order_by'] = "ORDER BY transactions.{$order_by} {$order}";
        }

        if ( $limit) {
            $query['limit'] = "LIMIT {$limit}";
        }
        $wpdb->hide_errors();
        $query          = apply_filters( 'woo_wallet_transactions_query', $query );
        $query          = implode( ' ', $query );
        $query_hash     = md5( $user_id . $query );
        $cached_results = is_array( get_transient( "woo_wallet_transaction_resualts_{$user_id}" ) ) ? get_transient( "woo_wallet_transaction_resualts_{$user_id}" ) : array();

        if ( $nocache || ! isset( $cached_results[$query_hash] ) ) {
            // Enable big selects for reports
            $wpdb->query( 'SET SESSION SQL_BIG_SELECTS=1' );
            $cached_results[$query_hash] = $wpdb->get_results( $query );
            set_transient( "woo_wallet_transaction_resualts_{$user_id}", $cached_results, DAY_IN_SECONDS );
        }

        $result = $cached_results[$query_hash];

        return $result;
    }

}

if (!function_exists('get_wallet_transactions_count')) {
    /**
     * Get wallet transactions count.
     * @global object $wpdb
     * @param int|null $user_id
     * @param bool $include_deleted
     * @return int total count of transactions.
     */
    function get_wallet_transactions_count($user_id = null, $include_deleted = false) {
        global $wpdb;
        $sql = "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions";
        if($user_id){
            $sql .= " WHERE user_id=$user_id";
        }
        if(!$include_deleted){
            if($user_id){
                $sql .= " AND deleted=0";
            } else{
                $sql .= "WHERE deleted=0";
            }
        }
        return $wpdb->get_var( $sql );
    }

}

if(!function_exists('get_wallet_transaction')){
    function get_wallet_transaction($transaction_id){
        global $wpdb;
        $sql = "SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = {$transaction_id}";
        $transaction = $wpdb->get_row($sql);
        return $transaction;
    }
}

if(!function_exists('get_wallet_transaction_type')){
    /**
     * Return transaction type by transaction id
     * @since 1.2.7
     * @global object $wpdb
     * @param int $transaction_id
     * @return type(string) | false
     */
    function get_wallet_transaction_type($transaction_id){
        global $wpdb;
        $transaction = $wpdb->get_row("SELECT type FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = {$transaction_id}");
        if( $transaction ){
            return $transaction->type;
        }
        return false;
    }
}

if ( ! function_exists( 'update_wallet_transaction' ) ) {

    function update_wallet_transaction( $transaction_id, $user_id, $data = array(), $format = NULL ) {
        global $wpdb;
        $update = false;
        if ( ! empty( $data) ) {
            $update = $wpdb->update( "{$wpdb->base_prefix}woo_wallet_transactions", $data, array( 'transaction_id' => $transaction_id ), $format, array( '%d' ) );
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
     */
    function clear_woo_wallet_cache( $user_id = '' ) {
        if ( ! $user_id ) {
            $user_id = get_current_user_id();
        }
        
        delete_transient("woo_wallet_transaction_resualts_{$user_id}");
    }

}

if ( ! function_exists( 'get_wallet_cashback_amount' ) ) {

    /**
     * 
     * @param int $order_id
     * @return float
     */
    function get_wallet_cashback_amount( $order_id = 0 ) {
        _deprecated_function('get_wallet_cashback_amount', '1.3.0', 'woo_wallet()->cashback->calculate_cashback()');
        if($order_id){
            return woo_wallet()->cashback->calculate_cashback(false, $order_id);
        }
        return woo_wallet()->cashback->calculate_cashback();
    }

}

if ( ! function_exists( 'is_full_payment_through_wallet' ) ) {

    /**
     * Check if cart eligible for full payment through wallet
     * @return boolean
     */
    function is_full_payment_through_wallet() {
        $is_valid_payment_through_wallet = false;
        $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' );
        $total = 0;
        $order_id = null;
        if(WC()->cart){
            $order_id = absint( get_query_var( 'order-pay' ) );

            // Gets order total from "pay for order" page.
            if ( 0 < $order_id ) {
                    $order = wc_get_order( $order_id );
                    $total = (float) $order->get_total();

            // Gets order total from cart/checkout.
            } elseif ( 0 < WC()->cart->total ) {
                    $total = (float) get_woowallet_cart_total();
            }
        }
        
        if(!is_admin() && $current_wallet_balance >= $total && ( !is_wallet_rechargeable_cart() )){
            $is_valid_payment_through_wallet = true;
        }
        
        if($order_id && is_wallet_rechargeable_order($order)){
            $is_valid_payment_through_wallet = false;
        }
        
        return apply_filters( 'is_valid_payment_through_wallet', $is_valid_payment_through_wallet );
    }

}

if ( ! function_exists( 'get_all_wallet_users' ) ) {

    function get_all_wallet_users( $exclude_me = true ) {
        $args = array(
            'blog_id' => $GLOBALS['blog_id'],
            'exclude' => $exclude_me ? array( get_current_user_id() ) : array(),
            'orderby' => 'login',
            'order'   => 'ASC'
        );
        return get_users( $args );
    }

}

if ( ! function_exists( 'get_total_order_cashback_amount' ) ) {

    /**
     * Get total cashback amount of an order.
     * @param int $order_id
     * @return float
     */
    function get_total_order_cashback_amount( $order_id ) {
        $order = wc_get_order( $order_id );
        $total_cashback_amount = 0;
        if ( $order ) {
            $transaction_ids                  = array();
            $_general_cashback_transaction_id = get_post_meta( $order_id, '_general_cashback_transaction_id', true );
            $_coupon_cashback_transaction_id  = get_post_meta( $order_id, '_coupon_cashback_transaction_id', true );
            if ( $_general_cashback_transaction_id ) {
                $transaction_ids[] = $_general_cashback_transaction_id;
            }
            if ( $_coupon_cashback_transaction_id ) {
                $transaction_ids[] = $_coupon_cashback_transaction_id;
            }
            if ( ! empty( $transaction_ids ) ) {
                $total_cashback_amount = array_sum( wp_list_pluck( get_wallet_transactions( array( 'user_id' => $order->get_customer_id(), 'where' => array( array( 'key' => 'transaction_id', 'value' => $transaction_ids, 'operator' => 'IN' ) ) ) ), 'amount' ) );
            }
        }
        return apply_filters( 'woo_wallet_total_order_cashback_amount', $total_cashback_amount );
    }

}

if (!function_exists('woo_wallet_persistent_cart_update')) {
    /**
     * Update WooWallet persistent cart to restore cart after recharge wallet.
     */
    function woo_wallet_persistent_cart_update() {
        if (get_current_user_id() && apply_filters('woo_wallet_persistent_cart_enabled', true)) {
            update_user_meta(
                    get_current_user_id(), '_woo_wallet_persistent_cart_' . get_current_blog_id(), 
                    get_user_meta(get_current_user_id(), '_woocommerce_persistent_cart_' . get_current_blog_id(), true)
            );
        }
    }

}

if (!function_exists('woo_wallet_persistent_cart_destroy')) {
    /**
     * Delete WooWallet persistent cart after restoring WooCommerce cart.
     */
    function woo_wallet_persistent_cart_destroy() {
        if (get_current_user_id()) {
            delete_user_meta(get_current_user_id(), '_woo_wallet_persistent_cart_' . get_current_blog_id());
        }
    }

}

if (!function_exists('woo_wallet_get_saved_cart')) {
    /**
     * Get saved WooWallet cart items.
     * @return array
     */
    function woo_wallet_get_saved_cart() {
        $saved_cart = array();

        if (apply_filters('woo_wallet_persistent_cart_enabled', true)) {
            $saved_cart_meta = get_user_meta(get_current_user_id(), '_woo_wallet_persistent_cart_' . get_current_blog_id(), true);

            if (isset($saved_cart_meta['cart'])) {
                $saved_cart = array_filter((array) $saved_cart_meta['cart']);
            }
        }

        return $saved_cart;
    }

}

if (!function_exists('woo_wallet_wc_price_args')) {

    function woo_wallet_wc_price_args($user_id = '') {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        $args = apply_filters('woo_wallet_wc_price_args', array(
            'ex_tax_label' => false,
            'currency' => '',
            'decimal_separator' => wc_get_price_decimal_separator(),
            'thousand_separator' => wc_get_price_thousand_separator(),
            'decimals' => wc_get_price_decimals(),
            'price_format' => get_woocommerce_price_format(),
                ), $user_id);
        return $args;
    }

}

if(!function_exists('get_wallet_user_capability')){
    /**
     * Wallet user admin capability.
     * @return string
     */
    function get_wallet_user_capability(){
        return apply_filters('woo_wallet_user_capability', 'manage_woocommerce');
    }
    
}

if(!function_exists('delete_user_wallet_transactions')){
    function delete_user_wallet_transactions($user_id, $force_delete = false){
        global $wpdb;
        if(!$force_delete){
            $update = $wpdb->update( "{$wpdb->base_prefix}woo_wallet_transactions", array('deleted' => 1), array( 'user_id' => $user_id ), array('%d'), array( '%d' ) );
            if ( $update ) {
                clear_woo_wallet_cache( $user_id );
            }
        } else{
            $user_wallet_transactions = get_wallet_transactions(array('user_id' => $user_id, 'include_deleted' => true));
            if($user_wallet_transactions){
                foreach ($user_wallet_transactions as $transaction){
                    $wpdb->delete("{$wpdb->base_prefix}woo_wallet_transactions", array('transaction_id' => $transaction->transaction_id));
                    $wpdb->delete("{$wpdb->base_prefix}woo_wallet_transaction_meta", array('transaction_id' => $transaction->transaction_id));
                }
            }
            clear_woo_wallet_cache( $user_id );
        }
    }
}

if(!function_exists('is_wallet_account_locked')){
    /**
     * Check if user wallet account is locked.
     * @param int $user_id
     * @return bool
     */
    function is_wallet_account_locked($user_id = ''){
        if(empty($user_id)){
            $user_id = get_current_user_id();
        }
        return get_user_meta($user_id, '_is_wallet_locked', true);
    }
}