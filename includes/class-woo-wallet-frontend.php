<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_Frontend')) {

    class Woo_Wallet_Frontend {

        /**
         * Class constructor
         */
        public function __construct() {
            add_filter('wp_nav_menu_items', array($this, 'add_wallet_nav_menu'), 100, 2);
            add_filter('woocommerce_get_query_vars', array($this, 'add_woocommerce_query_vars'));
            add_filter('woocommerce_endpoint_woo-wallet_title', array($this, 'woocommerce_endpoint_title'), 10, 2);
            add_filter('woocommerce_endpoint_woo-wallet-transactions_title', array($this, 'woocommerce_endpoint_title'), 10, 2);
            add_filter('woocommerce_account_menu_items', array($this, 'wc_wallet_menu_items'), 10, 1);
            add_action('woocommerce_account_woo-wallet_endpoint', array($this, 'wc_wallet_endpoint_content'));
            add_action('woocommerce_account_woo-wallet-transactions_endpoint', array($this, 'wc_wallet_transactions_endpoint_content'));

            add_filter('woocommerce_is_purchasable', array($this, 'make_wc_wallet_recharge_product_purchasable'), 10, 2);
            add_action('wp_loaded', array($this, 'wc_wallet_add_wallet_recharge_product'), 20);
            add_action('woocommerce_before_calculate_totals', array($this, 'wc_wallet_payment_set_recharge_product_price'));
            add_filter('woocommerce_add_to_cart_validation', array($this, 'restrict_other_from_add_to_cart'), 20);
            add_action('wp_enqueue_scripts', array(&$this, 'wc_wallet_payment_styles'));
            add_filter('woocommerce_available_payment_gateways', array($this, 'woocommerce_available_payment_gateways'), 30);
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on')) {
                add_action('woocommerce_before_cart_table', array($this, 'woocommerce_before_cart_table'));
            }
            add_action('woocommerce_checkout_order_processed', array($this, 'woocommerce_checkout_order_processed'), 30, 3);
            add_action('woocommerce_review_order_after_order_total', array($this, 'woocommerce_review_order_after_order_total'));
            add_action('woocommerce_get_order_item_totals', array($this, 'woocommerce_get_order_item_totals'), 10, 2);
            add_action('woocommerce_checkout_create_order_coupon_item', array($this, 'convert_coupon_to_cashbak_if'), 10, 4);
            add_action('woocommerce_shop_loop_item_title', array($this, 'display_cashback'), 15);
            add_action('woocommerce_before_single_product_summary', array($this, 'display_cashback'), 15);

            add_filter('woocommerce_coupon_message', array($this, 'update_woocommerce_coupon_message_as_cashback'), 10, 3);
            add_filter('woocommerce_cart_totals_coupon_label', array($this, 'change_coupon_label'), 10, 2);
            add_filter('woocommerce_cart_totals_order_total_html', array($this, 'woocommerce_cart_totals_order_total_html'));
        }

        /**
         * Add a new item to a menu
         * @param string $menu
         * @param array $args
         * @return string
         */
        public function add_wallet_nav_menu($menu, $args) {
            // Check if add a new item to a menu assigned to Primary Navigation Menu location
            if (apply_filters('woo_wallet_hide_nav_menu', false, $menu, $args) || in_array($args->theme_location, apply_filters('woo_wallet_exclude_nav_menu_location', array(), $menu, $args))) {
                return $menu;
            }

            if ('off' === woo_wallet()->settings_api->get_option($args->theme_location, '_wallet_settings_general', 'off') || !is_user_logged_in()) {
                return $menu;
            }

            ob_start();
            woo_wallet()->get_template('mini-wallet.php');
            $mini_wallet = ob_get_clean();
            return $menu . $mini_wallet;
        }

        /**
         * Add WooCommerce query vars.
         * @param type $query_vars
         * @return type
         */
        public function add_woocommerce_query_vars($query_vars) {
            $query_vars['woo-wallet'] = get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet');
            $query_vars['woo-wallet-transactions'] = get_option('woocommerce_woo_wallet_transactions_endpoint', 'woo-wallet-transactions');
            return $query_vars;
        }

        /**
         * Change WooCommerce endpoint title for wallet pages.
         */
        public function woocommerce_endpoint_title($title, $endpoint) {
            switch ($endpoint) {
                case 'woo-wallet' :
                    $title = __('My Wallet', 'woo-wallet');
                    break;
                case 'woo-wallet-transactions' :
                    $title = __('Wallet Transactions', 'woo-wallet');
                    break;
                default :
                    $title = '';
                    break;
            }
            return $title;
        }

        /**
         * Register and enqueue frontend styles and scripts
         */
        public function wc_wallet_payment_styles() {
            $wp_scripts = wp_scripts();
            wp_register_style('woo-wallet-payment-jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.css', false, $wp_scripts->registered['jquery-ui-core']->ver, false);
            wp_register_style('jquery-datatables-style', '//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css');
            wp_register_style('woo-endpoint-wallet-style', woo_wallet()->plugin_url() . '/assets/frontend/css/wc-endpoint-wallet.css', array(), WOO_WALLET_PLUGIN_VERSION);
            wp_register_style('woo-wallet-frontend-style', woo_wallet()->plugin_url() . '/assets/frontend/css/woo-wallet-frontend.css', array(), WOO_WALLET_PLUGIN_VERSION);
            wp_register_script('jquery-datatables-script', '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js', array('jquery'));
            wp_register_script('wc-endpoint-wallet-transactions', woo_wallet()->plugin_url() . '/assets/frontend/js/wc-endpoint-wallet-transactions.js', array('jquery', 'jquery-datatables-script'), WOO_WALLET_PLUGIN_VERSION);
            if (is_account_page()) {
                wp_enqueue_style('dashicons');
                wp_enqueue_style('jquery-datatables-style');
                wp_enqueue_style('woo-endpoint-wallet-style');
                wp_enqueue_script('jquery-datatables-script');
                wp_enqueue_script('wc-endpoint-wallet-transactions');
            }
            wp_enqueue_style('woo-wallet-frontend-style');
        }

        /**
         * WooCommerce wallet menu
         * @param array $items
         * @return array
         */
        public function wc_wallet_menu_items($items) {
            unset($items['edit-account']);
            unset($items['customer-logout']);
            $items[get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet')] = __('My Wallet', 'woo-wallet');
            $items['edit-account'] = __('Account details', 'woo-wallet');
            $items['customer-logout'] = __('Logout', 'woo-wallet');
            return $items;
        }

        /**
         * WooCommerce endpoint contents for wallet 
         */
        public function wc_wallet_endpoint_content() {
            woo_wallet()->get_template('wc-endpoint-wallet.php');
        }

        /**
         * WooCommerce endpoint contents for transaction details
         */
        public function wc_wallet_transactions_endpoint_content() {
            woo_wallet()->get_template('wc-endpoint-wallet-transactions.php');
        }

        /**
         * Add to cart rechargeable produt
         */
        public function wc_wallet_add_wallet_recharge_product() {
            if (isset($_POST['woo_add_to_wallet'])) {
                if (isset($_POST['woo_wallet_balance_to_add']) && !empty($_POST['woo_wallet_balance_to_add'])) {
                    add_filter('woocommerce_add_cart_item_data', array($this, 'add_wc_wallet_product_price_to_cart_item_data'), 10, 2);
                    $product = get_wallet_rechargeable_product();
                    if ($product) {
                        wc()->cart->empty_cart();
                        wc()->cart->add_to_cart($product->get_id());
                    }
                }
            }
        }

        /**
         * WooCommerce add cart item data
         * @param array $cart_item_data
         * @param int $product_id
         * @return array
         */
        public function add_wc_wallet_product_price_to_cart_item_data($cart_item_data, $product_id) {
            $product = wc_get_product($product_id);
            if (isset($_POST['woo_wallet_balance_to_add']) && $product) {
                $recharge_amount = round($_POST['woo_wallet_balance_to_add'], 2);
                $cart_item_data['recharge_amount'] = $recharge_amount;
            }
            return $cart_item_data;
        }

        /**
         * Make rechargeable product purchasable
         * @param boolean $is_purchasable
         * @param WC_Product object $product
         * @return boolean
         */
        public function make_wc_wallet_recharge_product_purchasable($is_purchasable, $product) {
            $wallet_product = get_wallet_rechargeable_product();
            if ($wallet_product) {
                if ($wallet_product->get_id() == $product->get_id()) {
                    $is_purchasable = true;
                }
            }
            return $is_purchasable;
        }

        /**
         * Set topup product price at run time
         * @param OBJECT $cart
         * @return NULL
         */
        public function wc_wallet_payment_set_recharge_product_price($cart) {
            $product = get_wallet_rechargeable_product();
            if (!$product) {
                return;
            }
            foreach ($cart->get_cart_contents() as $key => $value) {
                if (isset($value['recharge_amount']) && $value['recharge_amount'] && $product->get_id() == $value['product_id']) {
                    $value['data']->set_price($value['recharge_amount']);
                }
            }
        }

        /**
         * Restrict customer to order other product along with rechargeable product
         * @param boolean $valid
         * @return boolean
         */
        public function restrict_other_from_add_to_cart($valid) {
            $product = get_wallet_rechargeable_product();
            if (sizeof(wc()->cart->get_cart()) > 0 && $product) {
                foreach (wc()->cart->get_cart() as $key => $cart_item) {
                    if ($cart_item['product_id'] == $product->get_id()) {
                        wc_add_notice(__('You can not add another product while your cart contains with wallet recharge product.', 'woo-wallet'), 'error');
                        $valid = false;
                    }
                }
            }
            return $valid;
        }

        /**
         * Filter WooCommerce available payment gateway
         * for add balance to wallet
         * @param type $_available_gateways
         * @return type
         */
        public function woocommerce_available_payment_gateways($_available_gateways) {
            if (is_wallet_rechargeable_cart()) {
                foreach ($_available_gateways as $gateway_id => $gateway) {
                    if (woo_wallet()->settings_api->get_option($gateway_id, '_wallet_settings_general', 'on') != 'on') {
                        unset($_available_gateways[$gateway_id]);
                    }
                }
            }
            return $_available_gateways;
        }

        /**
         * Cashback notice
         */
        public function woocommerce_before_cart_table() {
            if (get_wallet_cashback_amount() && !is_wallet_rechargeable_cart()) :
                ?>
                <div class="woocommerce-Message woocommerce-Message--info woocommerce-info">
                    <?php echo sprintf(__('If you place this order then %s will be credited to your wallet', 'woo-wallet'), wc_price(get_wallet_cashback_amount())); ?>
                </div>
                <?php
            endif;
        }

        public function woocommerce_checkout_order_processed($order_id, $posted_data, $order) {
            if (get_wallet_cashback_amount() && !is_wallet_rechargeable_order(wc_get_order($order_id))) {
                update_post_meta($order_id, '_wallet_cashback', get_wallet_cashback_amount());
            }
            if (!is_full_payment_through_wallet() && ((isset($_POST['partial_pay_through_wallet']) && !empty($_POST['partial_pay_through_wallet'])) || 'on' === woo_wallet()->settings_api->get_option('is_auto_deduct_for_partial_payment', '_wallet_settings_general')) && !is_wallet_rechargeable_order(wc_get_order($order_id))) {
                $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '');
                update_post_meta($order_id, '_original_order_amount', $order->get_total(''));
                $order->set_total($order->get_total('') - $current_wallet_balance);
                update_post_meta($order_id, '_via_wallet_payment', $current_wallet_balance);
                $order->save();
            }
        }

        /**
         * Function that display partial payment option
         * @return NULL
         */
        public function woocommerce_review_order_after_order_total() {
            if (is_full_payment_through_wallet() || is_wallet_rechargeable_cart() || woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '') <= 0 || (isset(wc()->cart->recurring_carts) && !empty(wc()->cart->recurring_carts))) {
                return;
            }
            wp_enqueue_style('dashicons');
            wp_enqueue_style('woo-wallet-payment-jquery-ui');
            wp_enqueue_script('jquery-ui-tooltip');
            woo_wallet()->get_template('woo-wallet-partial-payment.php');
        }

        /**
         * Add wallet withdrawal amount to thank you page
         * @param array $total_rows
         * @param Object $order
         * @return array
         */
        public function woocommerce_get_order_item_totals($total_rows, $order) {
            if (!get_post_meta($order->get_id(), '_via_wallet_payment', true)) {
                return $total_rows;
            }
            $order_total = $total_rows['order_total'];
            unset($total_rows['order_total']);
            $total_rows['via_wallet'] = array('label' => __('Via wallet:', 'woo-wallet'), 'value' => wc_price(get_post_meta($order->get_id(), '_via_wallet_payment', true), array('currency' => $order->get_currency())));
            $total_rows['order_total'] = $order_total;
            return $total_rows;
        }

        /**
         * Convert coupon to cashback.
         * @param array $item
         * @param string $code
         * @param Object $coupon
         * @param Object $order
         * @since 1.0.6
         */
        public function convert_coupon_to_cashbak_if($item, $code, $coupon, $order) {
            $coupon_id = $coupon->get_id();
            $_is_coupon_cashback = get_post_meta($coupon_id, '_is_coupon_cashback', true);
            if ('yes' === $_is_coupon_cashback) {
                $discount_total = $order->get_discount_total('edit');
                $order->set_discount_total(0);
                $order_id = $order->save();
                update_post_meta($order_id, '_coupon_cashback_amount', $discount_total);
            }
        }

        /**
         * Display cashback amount in product
         * @global type $post
         */
        public function display_cashback() {
            global $post;
            $product = wc_get_product($post->ID);
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit') && $product->get_price('edit') && apply_filters('is_display_cashback_on_product', true) && apply_filters('is_product_cashback_enable', true, $post->ID)) {
                $global_cashbak_type = woo_wallet()->settings_api->get_option('cashback_type', '_wallet_settings_credit', 'percent');
                $global_cashbak_amount = floatval(woo_wallet()->settings_api->get_option('cashback_amount', '_wallet_settings_credit', 0));
                if ('product' === woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart')) {
                    $product_wise_cashback_type = get_post_meta($product->get_id(), '_cashback_type', true);
                    $product_wise_cashback_amount = get_post_meta($product->get_id(), '_cashback_amount', true) ? get_post_meta($product->get_id(), '_cashback_amount', true) : 0;
                    $cashback_amount = 0;
                    $cashback_type = 'percent';
                    if ($product_wise_cashback_type && $product_wise_cashback_amount) {
                        if ('percent' === $product_wise_cashback_type) {
                            $cashback_amount = $product_wise_cashback_amount;
                        } else {
                            $cashback_amount = $product_wise_cashback_amount;
                            $cashback_type = 'fixed';
                        }
                    } else {
                        if ('percent' === $global_cashbak_type) {
                            $cashback_amount = $global_cashbak_amount;
                        } else {
                            $cashback_amount = $global_cashbak_amount;
                            $cashback_type = 'fixed';
                        }
                    }
                    if ($cashback_amount) {
                        if ('percent' === $cashback_type) {
                            echo '<span class="on-woo-wallet-cashback">' . $cashback_amount . '% ' . __('Cashback', 'woo-wallet') . '</span>';
                        } else {
                            echo '<span class="on-woo-wallet-cashback">' . wc_price($cashback_amount) . __(' Cashback', 'woo-wallet') . '</span>';
                        }
                    }
                } else if ('product_cat' === woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart')) {
                    $term_ids = $product->get_category_ids('edit');
                    $category_wise_cashback_amounts = array();
                    $cashback_amount = 0;
                    $cashback_type = 'percent';
                    if (!empty($term_ids)) {
                        foreach ($term_ids as $term_id) {
                            $category_wise_cashback_type = get_woocommerce_term_meta($term_id, '_woo_cashback_type', true);
                            $category_wise_cashback_amount = get_woocommerce_term_meta($term_id, '_woo_cashback_amount', true);
                            if ($category_wise_cashback_type && $category_wise_cashback_amount) {
                                if ('percent' === $category_wise_cashback_type) {
                                    $cashback_subtotal = $product->get_price() * ($category_wise_cashback_amount / 100);
                                    $category_wise_cashback_amounts[] = array('cashback_subtotal' => $cashback_subtotal, 'amount' => $category_wise_cashback_amount, 'type' => $category_wise_cashback_type);
                                } else {
                                    $category_wise_cashback_amounts[] = array('cashback_subtotal' => $category_wise_cashback_amount, 'amount' => $category_wise_cashback_amount, 'type' => $category_wise_cashback_type);
                                }
                            }
                        }
                    }
                    if (!empty($category_wise_cashback_amounts)) {
                        $category_wise_cashback = ('on' === woo_wallet()->settings_api->get_option('allow_min_cashback', '_wallet_settings_credit', 'on')) ? min($category_wise_cashback_amounts) : max($category_wise_cashback_amounts);
                        $cashback_amount = $category_wise_cashback['amount'];
                        $cashback_type = $category_wise_cashback['type'];
                    } else {
                        if ('percent' === $global_cashbak_type) {
                            $cashback_amount = $global_cashbak_amount;
                        } else {
                            $cashback_amount = $global_cashbak_amount;
                            $cashback_type = 'fixed';
                        }
                    }
                    if ($cashback_amount) {
                        if ('percent' === $cashback_type) {
                            echo '<span class="on-woo-wallet-cashback">' . $cashback_amount . '% ' . __('Cashback', 'woo-wallet') . '</span>';
                        } else {
                            echo '<span class="on-woo-wallet-cashback">' . wc_price($cashback_amount) . __(' Cashback', 'woo-wallet') . '</span>';
                        }
                    }
                }
            }
        }

        /**
         * 
         * @param string $msg
         * @param int $msg_code
         * @param Object $coupon
         * @return string
         */
        public function update_woocommerce_coupon_message_as_cashback($msg, $msg_code, $coupon) {
            $coupon_id = $coupon->get_id();
            $_is_coupon_cashback = get_post_meta($coupon_id, '_is_coupon_cashback', true);
            if ('yes' === $_is_coupon_cashback && 200 === $msg_code) {
                $msg = __('Coupon code applied successfully as cashback.', 'woo-wallet');
            }
            return $msg;
        }

        /**
         * Change coupon label in cart and checkout page
         * @param string $label
         * @param Object $coupon
         * @return string
         */
        public function change_coupon_label($label, $coupon) {
            $coupon_id = $coupon->get_id();
            $_is_coupon_cashback = get_post_meta($coupon_id, '_is_coupon_cashback', true);
            if ('yes' === $_is_coupon_cashback) {
                $label = sprintf(esc_html__('Cashback: %s', 'woo-wallet'), $coupon->get_code());
            }
            return $label;
        }
        
        /**
         * Modify order total html
         * @param string $value
         * @return string
         */
        public function woocommerce_cart_totals_order_total_html($value) {
            $value = '';
            $total = WC()->cart->get_total('edit');
            foreach (WC()->cart->get_applied_coupons() as $code) {
                $coupon = new WC_Coupon($code);
                $_is_coupon_cashback = get_post_meta($coupon->get_id(), '_is_coupon_cashback', true);
                if ('yes' === $_is_coupon_cashback) {
                    $total += WC()->cart->get_coupon_discount_amount($code);
                }
            }
            $value = '<strong>' . wc_price($total) . '</strong> ';
            // If prices are tax inclusive, show taxes here.
            if (wc_tax_enabled() && WC()->cart->display_prices_including_tax()) {
                $tax_string_array = array();
                $cart_tax_totals = WC()->cart->get_tax_totals();

                if (get_option('woocommerce_tax_total_display') == 'itemized') {
                    foreach ($cart_tax_totals as $code => $tax) {
                        $tax_string_array[] = sprintf('%s %s', $tax->formatted_amount, $tax->label);
                    }
                } elseif (!empty($cart_tax_totals)) {
                    $tax_string_array[] = sprintf('%s %s', wc_price(WC()->cart->get_taxes_total(true, true)), WC()->countries->tax_or_vat());
                }

                if (!empty($tax_string_array)) {
                    $taxable_address = WC()->customer->get_taxable_address();
                    $estimated_text = WC()->customer->is_customer_outside_base() && !WC()->customer->has_calculated_shipping() ? sprintf(' ' . __('estimated for %s', 'woocommerce'), WC()->countries->estimated_for_prefix($taxable_address[0]) . WC()->countries->countries[$taxable_address[0]]) : '';
                    $value .= '<small class="includes_tax">' . sprintf(__('(includes %s)', 'woocommerce'), implode(', ', $tax_string_array) . $estimated_text) . '</small>';
                }
            }
            return $value;
        }

    }

}
new Woo_Wallet_Frontend();
