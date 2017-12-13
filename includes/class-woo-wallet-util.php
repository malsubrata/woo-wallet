<?php

if (!function_exists('is_wallet_rechargeable_order')) {

    /**
     * Check if order contains rechargeable product
     * @param WC_Order object $order
     * @return boolean
     */
    function is_wallet_rechargeable_order($order) {
        $is_wallet_rechargeable_order = false;
        foreach ($order->get_items('line_item') as $item) {
            $product_id = $item['product_id'];
            if ($product_id == get_wallet_rechargeable_product()->get_id()) {
                return true;
            }
        }
        return $is_wallet_rechargeable_order;
    }

}

if (!function_exists('is_wallet_rechargeable_cart')) {

    /**
     * Check if cart contains rechargeable product
     * @return boolean
     */
    function is_wallet_rechargeable_cart() {
        if (sizeof(wc()->cart->get_cart()) > 0 && get_wallet_rechargeable_product()) {
            foreach (wc()->cart->get_cart() as $key => $cart_item) {
                if ($cart_item['product_id'] == get_wallet_rechargeable_product()->get_id()) {
                    return true;
                }
            }
        }
        return false;
    }

}

if (!function_exists('get_wallet_rechargeable_product')) {

    /**
     * get rechargeable product
     * @return WC_Product object
     */
    function get_wallet_rechargeable_product() {
        return wc_get_product(get_option('_woo_wallet_recharge_product'));
    }

}

if (!function_exists('set_wallet_transaction_meta')) {

    /**
     * Insert meta data into transaction meta table
     * @global object $wpdb
     * @param int $transaction_id
     * @param string $meta_key
     * @param mixed $meta_value
     * @return boolean
     */
    function set_wallet_transaction_meta($transaction_id, $meta_key, $meta_value) {
        global $wpdb;
        $meta_key = wp_unslash($meta_key);
        $meta_value = wp_unslash($meta_value);
        $meta_value = maybe_serialize($meta_value);
        return $wpdb->insert("{$wpdb->prefix}woo_wallet_transaction_meta", array("transaction_id" => $transaction_id, "meta_key" => $meta_key, "meta_value" => $meta_value));
    }

}

if (!function_exists('update_wallet_transaction_meta')) {

    /**
     * Update meta data into transaction meta table
     * @global object $wpdb
     * @param int $transaction_id
     * @param string $meta_key
     * @param mixed $meta_value
     * @return boolean
     */
    function update_wallet_transaction_meta($transaction_id, $meta_key, $meta_value) {
        global $wpdb;
        if (is_null($wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", $transaction_id, $meta_key)))) {
            return set_wallet_transaction_meta($transaction_id, $meta_key, $meta_value);
        } else {
            $meta_key = wp_unslash($meta_key);
            $meta_value = wp_unslash($meta_value);
            $meta_value = maybe_serialize($meta_value);
            return $wpdb->update("{$wpdb->prefix}woo_wallet_transaction_meta", array('meta_value' => $meta_value), array('transaction_id' => $transaction_id, "meta_key" => $meta_key), array('%s'), array('%d', '%s'));
        }
    }

}

if (!function_exists('get_wallet_transaction_meta')) {

    /**
     * Fetch transaction meta
     * @global object $wpdb
     * @param int $transaction_id
     * @param string $meta_key
     * @param boolean $single
     * @return boolean
     */
    function get_wallet_transaction_meta($transaction_id, $meta_key, $single = true) {
        global $wpdb;
        $resualt = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", $transaction_id, $meta_key));
        if (!is_null($resualt)) {
            return maybe_unserialize($resualt);
        } else {
            return false;
        }
    }

}

if (!function_exists('get_wallet_transactions')) {

    /**
     * Get all wallet transactions
     * @global object $wpdb
     * @param array $args
     * @param mixed $output
     * @return db rows
     */
    function get_wallet_transactions($args = array(), $limit = '', $output = OBJECT) {
        global $wpdb;
        $query = '';
        if (!empty($args)) {
            foreach ($args as $key => $arg) {
                if (!$wpdb->get_var("SHOW COLUMNS FROM `{$wpdb->prefix}woo_wallet_transactions` LIKE '{$key}';")) {
                    unset($args[$key]);
                }
            }
            $query .= ' WHERE ';
            $query .= implode(' AND ', array_map(
                            function ($v, $k) {
                        return sprintf("%s = '%s'", $k, $v);
                    }, $args, array_keys($args)
            ));
        }
        if($limit){
            $limit = " LIMIT 0, {$limit}";
        }
        return $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woo_wallet_transactions {$query} ORDER BY `transaction_id` DESC" . $limit, $output);
    }

}

if (!function_exists('get_wallet_cashback_amount')) {

    function get_wallet_cashback_amount($order_id = 0) {
        $cashback_amount = 0;
        if($order_id){
            return get_post_meta($order_id, '_wallet_cashback', true) ? get_post_meta($order_id, '_wallet_cashback', true) : 0;
        }
        if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on')) {
            $cashback_rule = woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart');
            $global_cashbak_type = woo_wallet()->settings_api->get_option('cashback_type', '_wallet_settings_credit', 'percent');
            $global_cashbak_amount = floatval(woo_wallet()->settings_api->get_option('cashback_amount', '_wallet_settings_credit', 0));
            if ('product' === $cashback_rule) {
                if (sizeof(wc()->cart->get_cart()) > 0) {
                    foreach (wc()->cart->get_cart() as $key => $cart_item) {
                        $product = wc_get_product($cart_item['product_id']);
                        $product_wise_cashback_type = get_post_meta($product->get_id(), '_cashback_type', true);
                        $product_wise_cashback_amount = get_post_meta($product->get_id(), '_cashback_amount', true) ? get_post_meta($product->get_id(), '_cashback_amount', true) : 0;
                        if ($product_wise_cashback_type && $product_wise_cashback_amount) {
                            if ('percent' === $product_wise_cashback_type) {
                                $cashback_amount += $product->get_price() * ($product_wise_cashback_amount / 100);
                            } else {
                                $cashback_amount += $product_wise_cashback_amount;
                            }
                        } else {
                            if ('percent' === $global_cashbak_type) {
                                $cashback_amount += $product->get_price() * ($global_cashbak_amount / 100);
                            } else {
                                $cashback_amount += $global_cashbak_amount;
                            }
                        }
                    }
                }
            } else {
                if ('percent' === $global_cashbak_type) {
                    $cashback_amount += wc()->cart->get_subtotal() * ($global_cashbak_amount / 100);
                } else {
                    $cashback_amount += $global_cashbak_amount;
                }
            }
            return apply_filters('wcwp_cashback_amount', $cashback_amount);
        }
    }

}

if(!function_exists('is_full_payment_through_wallet')){
    function is_full_payment_through_wallet(){
        $is_valid_payment_through_wallet = true;
        $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '');
        if ($current_wallet_balance < wc()->cart->get_total('') || is_wallet_rechargeable_cart()) {
            $is_valid_payment_through_wallet = false;
        }
        return apply_filters('is_valid_payment_through_wallet', $is_valid_payment_through_wallet);
    }
}
    