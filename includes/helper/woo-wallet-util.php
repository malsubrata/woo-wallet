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
                $is_wallet_rechargeable_order = true;
                break;
            }
        }
        return apply_filters('woo_wallet_is_wallet_rechargeable_order', $is_wallet_rechargeable_order, $order);
    }

}

if (!function_exists('is_wallet_rechargeable_cart')) {

    /**
     * Check if cart contains rechargeable product
     * @return boolean
     */
    function is_wallet_rechargeable_cart() {
        $is_wallet_rechargeable_cart = false;
        if (!is_null(wc()->cart) && sizeof(wc()->cart->get_cart()) > 0 && get_wallet_rechargeable_product()) {
            foreach (wc()->cart->get_cart() as $key => $cart_item) {
                if ($cart_item['product_id'] == get_wallet_rechargeable_product()->get_id()) {
                    $is_wallet_rechargeable_cart = true;
                    break;
                }
            }
        }
        return apply_filters('woo_wallet_is_wallet_rechargeable_cart', $is_wallet_rechargeable_cart);
    }

}

if (!function_exists('get_woowallet_cart_total')) {

    function get_woowallet_cart_total() {
        $cart_total = 0;
        if (is_array(wc()->cart->cart_contents) && sizeof(wc()->cart->cart_contents) > 0) {
            $cart_total = wc()->cart->get_subtotal('edit') + wc()->cart->get_taxes_total() + wc()->cart->get_shipping_total('edit') - wc()->cart->get_discount_total();
        }
        return apply_filters('woowallet_cart_total', $cart_total);
    }

}

if (!function_exists('is_enable_wallet_partial_payment')) {

    function is_enable_wallet_partial_payment() {
        $is_enable = false;
        $cart_total = get_woowallet_cart_total();
        if ( !is_wallet_rechargeable_cart() && is_user_logged_in() && ( (!is_null(wc()->session) && wc()->session->get('is_wallet_partial_payment', false)) || 'on' === woo_wallet()->settings_api->get_option('is_auto_deduct_for_partial_payment', '_wallet_settings_general')) && $cart_total > apply_filters('woo_wallet_partial_payment_amount', woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'edit'))) {
            $is_enable = true;
        }
        return apply_filters('is_enable_wallet_partial_payment', $is_enable);
    }

}

if (!function_exists('get_order_partial_payment_amount')) {

    function get_order_partial_payment_amount($order_id) {
        $via_wallet = 0;
        $order = wc_get_order($order_id);
        if ($order) {
            $line_items_fee = $order->get_items('fee');
            foreach ($line_items_fee as $item_id => $item) {
                if ('via_wallet' === strtolower(str_replace(' ', '_', $item->get_name('edit')))) {
                    $via_wallet += $item->get_total('edit');
                }
            }
        }
        return abs($via_wallet);
    }

}

if (!function_exists('update_wallet_partial_payment_session')) {

    function update_wallet_partial_payment_session($set = false) {
        wc()->session->set('is_wallet_partial_payment', $set);
    }

}

if (!function_exists('get_wallet_rechargeable_orders')) {

    /**
     * Return wallet rechargeable order id 
     * @return array
     */
    function get_wallet_rechargeable_orders() {
        $args = array(
            'posts_per_page' => -1,
            'meta_key' => '_wc_wallet_purchase_credited',
            'meta_value' => true,
            'post_type' => 'shop_order',
            'post_status' => array('completed', 'processing', 'on-hold'),
            'suppress_filters' => true
        );
        $orders = get_posts($args);
        return wp_list_pluck($orders, 'ID');
    }

}

if (!function_exists('get_wallet_rechargeable_product')) {

    /**
     * get rechargeable product
     * @return WC_Product object
     */
    function get_wallet_rechargeable_product() {
        Woo_Wallet_Install::cteate_product_if_not_exist();
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
    function set_wallet_transaction_meta($transaction_id, $meta_key, $meta_value, $user_id = '') {
        global $wpdb;
        $meta_key = wp_unslash($meta_key);
        $meta_value = wp_unslash($meta_value);
        $meta_value = maybe_serialize($meta_value);
        $wpdb->insert("{$wpdb->base_prefix}woo_wallet_transaction_meta", array("transaction_id" => $transaction_id, "meta_key" => $meta_key, "meta_value" => $meta_value));
        $meta_id = $wpdb->insert_id;
        clear_woo_wallet_cache($user_id);
        return $meta_id;
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
    function update_wallet_transaction_meta($transaction_id, $meta_key, $meta_value, $user_id = '') {
        global $wpdb;
        if (is_null($wpdb->get_var($wpdb->prepare("SELECT meta_id FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", array($transaction_id, $meta_key))))) {
            return set_wallet_transaction_meta($transaction_id, $meta_key, $meta_value, $user_id);
        } else {
            $meta_key = wp_unslash($meta_key);
            $meta_value = wp_unslash($meta_value);
            $meta_value = maybe_serialize($meta_value);
            $status = $wpdb->update("{$wpdb->base_prefix}woo_wallet_transaction_meta", array('meta_value' => $meta_value), array('transaction_id' => $transaction_id, "meta_key" => $meta_key), array('%s'), array('%d', '%s'));
            clear_woo_wallet_cache($user_id);
            return $status;
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
        $resualt = $wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %s AND meta_key = %s", $transaction_id, $meta_key));
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
    function get_wallet_transactions($args = array(), $output = OBJECT) {
        global $wpdb;
        $default_args = array(
            'user_id' => get_current_user_id(),
            'where' => array(),
            'where_meta' => array(),
            'order_by' => 'transaction_id',
            'order' => 'DESC',
            'join_type' => 'INNER',
            'limit' => '',
            'nocache' => false
        );
        $args = apply_filters('woo_wallet_transactions_query_args', $args);
        $args = wp_parse_args($args, $default_args);
        extract($args);
        $query = array();
        $query['select'] = "SELECT transactions.*";
        $query['from'] = "FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions";
        // Joins
        $joins = array();
        if (!empty($where_meta)) {
            $joins["order_items"] = "{$join_type} JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta AS transaction_meta ON transactions.transaction_id = transaction_meta.transaction_id";
        }
        $query['join'] = implode(' ', $joins);

        $query['where'] = "WHERE transactions.user_id = {$user_id}";
        $query['where'] .= " AND transactions.deleted = 0";

        if (!empty($where_meta)) {
            foreach ($where_meta as $value) {
                if (!isset($value['operator'])) {
                    $value['operator'] = '=';
                }
                $query['where'] .= " AND (transaction_meta.meta_key = '{$value['key']}' AND transaction_meta.meta_value {$value['operator']} '{$value['value']}')";
            }
        }

        if (!empty($where)) {
            foreach ($where as $value) {
                if (!isset($value['operator'])) {
                    $value['operator'] = '=';
                }
                if ($value['operator'] == 'IN' && is_array($value['value'])) {
                    $value['value'] = implode(',', $value['value']);
                    $query['where'] .= " AND transactions.{$value['key']} {$value['operator']} ({$value['value']})";
                } else {
                    $query['where'] .= " AND transactions.{$value['key']} {$value['operator']} '{$value['value']}'";
                }
            }
        }

        if (!empty($after) || !empty($before)) {
            $after = empty($after) ? '0000-00-00' : $after;
            $before = empty($before) ? current_time('mysql', 1) : $before;
            $query['where'] .= " AND `date` BETWEEN STR_TO_DATE('" . $after . "', '%Y-%m-%d %H:%i:%s') and STR_TO_DATE('" . $before . "', '%Y-%m-%d %H:%i:%s')";
        }

        if ($order_by) {
            $query['order_by'] = "ORDER BY transactions.{$order_by} {$order}";
        }

        if ($limit) {
            $query['limit'] = "LIMIT {$limit}";
        }

        $query = apply_filters('woo_wallet_transactions_query', $query);
        $query = implode(' ', $query);
        $query_hash = md5($user_id . $query);
        $cached_results = is_array(get_transient('woo_wallet_transaction_resualts')) ? get_transient('woo_wallet_transaction_resualts') : array();

        if ($nocache || !isset($cached_results[$user_id][$query_hash])) {
            // Enable big selects for reports
            $wpdb->query('SET SESSION SQL_BIG_SELECTS=1');
            $cached_results[$user_id][$query_hash] = $wpdb->get_results($query);
            set_transient('woo_wallet_transaction_resualts', $cached_results, DAY_IN_SECONDS);
        }


        $result = $cached_results[$user_id][$query_hash];

        return $result;
    }

}

if (!function_exists('update_wallet_transaction')) {

    function update_wallet_transaction($transaction_id, $user_id, $data = array(), $format = NULL) {
        global $wpdb;
        $update = false;
        if (!empty($data)) {
            $update = $wpdb->update("{$wpdb->base_prefix}woo_wallet_transactions", $data, array('transaction_id' => $transaction_id), $format, array('%d'));
            if ($update) {
                clear_woo_wallet_cache($user_id);
            }
        }
        return $update;
    }

}

if (!function_exists('clear_woo_wallet_cache')) {

    /**
     * Clear WooCommerce Wallet user transient
     */
    function clear_woo_wallet_cache($user_id = '') {
        $cached_results = is_array(get_transient('woo_wallet_transaction_resualts')) ? get_transient('woo_wallet_transaction_resualts') : array();
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        if (isset($cached_results[$user_id])) {
            unset($cached_results[$user_id]);
        }
        set_transient('woo_wallet_transaction_resualts', $cached_results, DAY_IN_SECONDS);
    }

}

if (!function_exists('get_wallet_cashback_amount')) {

    /**
     * 
     * @param int $order_id
     * @return float
     */
    function get_wallet_cashback_amount($order_id = 0) {
        $cashback_amount = 0;
        if ($order_id) {
            return get_post_meta($order_id, '_wallet_cashback', true) ? get_post_meta($order_id, '_wallet_cashback', true) : 0;
        }
        if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit')) {
            $cashback_rule = woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart');
            $global_cashbak_type = woo_wallet()->settings_api->get_option('cashback_type', '_wallet_settings_credit', 'percent');
            $global_cashbak_amount = floatval(woo_wallet()->settings_api->get_option('cashback_amount', '_wallet_settings_credit', 0));
            $max_cashbak_amount = floatval(woo_wallet()->settings_api->get_option('max_cashback_amount', '_wallet_settings_credit', 0));
            if ('product' === $cashback_rule) {
                if (sizeof(wc()->cart->get_cart()) > 0) {
                    foreach (wc()->cart->get_cart() as $key => $cart_item) {
                        $product_id = $cart_item['product_id'];
                        $cart_item_product_id = ( 0 != $cart_item['variation_id'] ) ? $cart_item['variation_id'] : $cart_item['product_id'];
                        $product = wc_get_product($cart_item_product_id);
                        $qty = $cart_item['quantity'];
                        $product_wise_cashback_type = get_post_meta($product_id, '_cashback_type', true);
                        $product_wise_cashback_amount = get_post_meta($product_id, '_cashback_amount', true) ? get_post_meta($product_id, '_cashback_amount', true) : 0;
                        $product_price = 0;
                        if (wc()->cart->display_prices_including_tax()) {
                            $product_price = wc_get_price_including_tax($cart_item['data']);
                        } else {
                            $product_price = wc_get_price_excluding_tax($cart_item['data']);
                        }
                        if ($product_wise_cashback_type && $product_wise_cashback_amount) {
                            if ('percent' === $product_wise_cashback_type) {
                                $product_wise_percent_cashback_amount = $product_price * $qty * ($product_wise_cashback_amount / 100);
                                if ($max_cashbak_amount && $product_wise_percent_cashback_amount > $max_cashbak_amount) {
                                    $cashback_amount += $max_cashbak_amount;
                                } else {
                                    $cashback_amount += $product_wise_percent_cashback_amount;
                                }
                            } else {
                                $cashback_amount += $product_wise_cashback_amount;
                            }
                        } else {
                            if ('percent' === $global_cashbak_type) {
                                $product_wise_percent_cashback_amount = $product_price * $qty * ($global_cashbak_amount / 100);
                                if ($max_cashbak_amount && $product_wise_percent_cashback_amount > $max_cashbak_amount) {
                                    $cashback_amount += $max_cashbak_amount;
                                } else {
                                    $cashback_amount += $product_wise_percent_cashback_amount;
                                }
                            } else {
                                $cashback_amount += $global_cashbak_amount;
                            }
                        }
                    }
                }
            } else if ('product_cat' === $cashback_rule) {
                if (sizeof(wc()->cart->get_cart()) > 0) {
                    foreach (wc()->cart->get_cart() as $key => $cart_item) {
                        $product = wc_get_product($cart_item['product_id']);
                        $qty = $cart_item['quantity'];
                        $term_ids = $product->get_category_ids('edit');
                        $category_wise_cashback_amounts = array();
                        $product_price = 0;
                        if (wc()->cart->display_prices_including_tax()) {
                            $product_price = wc_get_price_including_tax($cart_item['data']);
                        } else {
                            $product_price = wc_get_price_excluding_tax($cart_item['data']);
                        }
                        if (!empty($term_ids)) {
                            foreach ($term_ids as $term_id) {
                                $category_wise_cashback_type = get_woocommerce_term_meta($term_id, '_woo_cashback_type', true);
                                $category_wise_cashback_amount = get_woocommerce_term_meta($term_id, '_woo_cashback_amount', true);
                                if ($category_wise_cashback_type && $category_wise_cashback_amount) {
                                    if ('percent' === $category_wise_cashback_type) {
                                        $category_wise_cashback_amount = $product_price * $qty * ($category_wise_cashback_amount / 100);
                                        if ($max_cashbak_amount && $category_wise_cashback_amount > $max_cashbak_amount) {
                                            $category_wise_cashback_amount = $max_cashbak_amount;
                                        }
                                    }
                                    $category_wise_cashback_amounts[] = $category_wise_cashback_amount;
                                }
                            }
                        }
                        if (!empty($category_wise_cashback_amounts)) {
                            $cashback_amount += ('on' === woo_wallet()->settings_api->get_option('allow_min_cashback', '_wallet_settings_credit', 'on')) ? min($category_wise_cashback_amounts) : max($category_wise_cashback_amounts);
                        } else {
                            if ('percent' === $global_cashbak_type) {
                                $category_wise_cashback_amount = $product_price * $qty * ($global_cashbak_amount / 100);
                                if ($max_cashbak_amount && $category_wise_cashback_amount > $max_cashbak_amount) {
                                    $cashback_amount += $max_cashbak_amount;
                                } else {
                                    $cashback_amount += $category_wise_cashback_amount;
                                }
                            } else {
                                $cashback_amount += $global_cashbak_amount;
                            }
                        }
                    }
                }
            } else {
                if (woo_wallet()->settings_api->get_option('min_cart_amount', '_wallet_settings_credit', 10) != 0 && WC()->cart->get_subtotal('edit') >= woo_wallet()->settings_api->get_option('min_cart_amount', '_wallet_settings_credit', 0)) {
                    if ('percent' === $global_cashbak_type) {
                        $percent_cashback_amount = wc()->cart->get_subtotal() * ($global_cashbak_amount / 100);
                        if ($max_cashbak_amount && $percent_cashback_amount > $max_cashbak_amount) {
                            $cashback_amount += $max_cashbak_amount;
                        } else {
                            $cashback_amount += $percent_cashback_amount;
                        }
                    } else {
                        $cashback_amount += $global_cashbak_amount;
                    }
                }
            }
            return apply_filters('woo_wallet_cashback_amount', $cashback_amount, $order_id);
        }
    }

}

if (!function_exists('is_full_payment_through_wallet')) {

    /**
     * Check if cart eligible for full payment through wallet
     * @return boolean
     */
    function is_full_payment_through_wallet() {
        $is_valid_payment_through_wallet = true;
        $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'edit');
        if ( (is_array(wc()->cart->cart_contents) && sizeof(wc()->cart->cart_contents) > 0) && ($current_wallet_balance < get_woowallet_cart_total() || is_wallet_rechargeable_cart())) {
            $is_valid_payment_through_wallet = false;
        }
        return apply_filters('is_valid_payment_through_wallet', $is_valid_payment_through_wallet);
    }

}

if (!function_exists('get_all_wallet_users')) {

    function get_all_wallet_users($exclude_me = true) {
        $args = array(
            'blog_id' => $GLOBALS['blog_id'],
            'exclude' => $exclude_me ? array(get_current_user_id()) : array(),
            'orderby' => 'login',
            'order' => 'ASC'
        );
        return get_users($args);
    }

}

if (!function_exists('get_total_order_cashback_amount')) {

    /**
     * Get total cashback amount of an order.
     * @param int $order_id
     * @return float
     */
    function get_total_order_cashback_amount($order_id) {
        $order = wc_get_order($order_id);
        $total_cashback_amount = 0;
        if ($order) {
            $transaction_ids = array();
            $_general_cashback_transaction_id = get_post_meta($order_id, '_general_cashback_transaction_id', true);
            $_coupon_cashback_transaction_id = get_post_meta($order_id, '_coupon_cashback_transaction_id', true);
            if ($_general_cashback_transaction_id) {
                $transaction_ids[] = $_general_cashback_transaction_id;
            }
            if ($_coupon_cashback_transaction_id) {
                $transaction_ids[] = $_coupon_cashback_transaction_id;
            }
            if (!empty($transaction_ids)) {
                $total_cashback_amount = array_sum(wp_list_pluck(get_wallet_transactions(array('user_id' => $order->get_customer_id(), 'where' => array(array('key' => 'transaction_id', 'value' => $transaction_ids, 'operator' => 'IN')))), 'amount'));
            }
        }
        return apply_filters('woo_wallet_total_order_cashback_amount', $total_cashback_amount);
    }

}