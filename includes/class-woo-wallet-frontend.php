<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_Frontend')) {

    class Woo_Wallet_Frontend {

        /**
         * The single instance of the class.
         *
         * @var Woo_Wallet_Frontend
         * @since 1.1.10
         */
        protected static $_instance = null;

        /**
         * Main instance
         * @return class object
         */
        public static function instance() {
            if (is_null(self::$_instance)) {
                self::$_instance = new self();
            }
            return self::$_instance;
        }

        /**
         * Class constructor
         */
        public function __construct() {
            add_filter('wp_nav_menu_items', array($this, 'add_wallet_nav_menu'), 100, 2);
            add_filter('woocommerce_get_query_vars', array($this, 'add_woocommerce_query_vars'));
            add_filter('woocommerce_endpoint_woo-wallet_title', array($this, 'woocommerce_endpoint_title'), 10, 2);
            add_filter('woocommerce_endpoint_woo-wallet-transactions_title', array($this, 'woocommerce_endpoint_title'), 10, 2);
            add_filter('woocommerce_account_menu_items', array($this, 'woo_wallet_menu_items'), 10, 1);
            add_action('woocommerce_account_woo-wallet_endpoint', array($this, 'woo_wallet_endpoint_content'));
            add_action('woocommerce_account_woo-wallet-transactions_endpoint', array($this, 'woo_wallet_transactions_endpoint_content'));

            add_filter('woocommerce_is_purchasable', array($this, 'make_woo_wallet_recharge_product_purchasable'), 10, 2);
            add_action('wp_loaded', array($this, 'woo_wallet_frontend_loaded'), 20);
            add_action('woocommerce_before_calculate_totals', array($this, 'woo_wallet_set_recharge_product_price'));
            add_filter('woocommerce_add_to_cart_validation', array($this, 'restrict_other_from_add_to_cart'), 20);
            add_action('wp_enqueue_scripts', array(&$this, 'woo_wallet_styles'));
            add_filter('woocommerce_available_payment_gateways', array($this, 'woocommerce_available_payment_gateways'), 30);
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on')) {
                add_action('woocommerce_before_cart_table', array($this, 'woocommerce_before_cart_table'));
                add_action('woocommerce_before_checkout_form', array($this, 'woocommerce_before_cart_table'));
            }
            add_action('woocommerce_checkout_order_processed', array($this, 'woocommerce_checkout_order_processed'), 30, 3);
            add_action('woocommerce_review_order_after_order_total', array($this, 'woocommerce_review_order_after_order_total'));
            add_action('woocommerce_get_order_item_totals', array($this, 'woocommerce_get_order_item_totals'), 10, 2);
            add_action('woocommerce_checkout_create_order_coupon_item', array($this, 'convert_coupon_to_cashbak_if'), 10, 4);
            add_action('woocommerce_shop_loop_item_title', array($this, 'display_cashback'), 15);
            add_action('woocommerce_before_single_product_summary', array($this, 'display_cashback'), 15);

            add_filter('woocommerce_coupon_message', array($this, 'update_woocommerce_coupon_message_as_cashback'), 10, 3);
            add_filter('woocommerce_cart_totals_coupon_label', array($this, 'change_coupon_label'), 10, 2);
            add_filter('woocommerce_cart_get_total', array($this, 'woocommerce_cart_get_total'));
            add_shortcode('woo-wallet', __CLASS__ . '::woo_wallet_shortcode_callback');
            add_action('woocommerce_cart_calculate_fees', array($this, 'woo_wallet_add_partial_payment_fee'));
            add_filter('woocommerce_cart_totals_get_fees_from_cart_taxes', array($this, 'woocommerce_cart_totals_get_fees_from_cart_taxes'), 10, 2);
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
                    $title = apply_filters('woo_wallet_account_menu_title', __('My Wallet', 'woo-wallet'));
                    break;
                case 'woo-wallet-transactions' :
                    $title = apply_filters('woo_wallet_account_transaction_menu_title', __('Wallet Transactions', 'woo-wallet'));
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
        public function woo_wallet_styles() {
            $wp_scripts = wp_scripts();
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            wp_register_style('woo-wallet-payment-jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.css', false, $wp_scripts->registered['jquery-ui-core']->ver, false);
            wp_register_style('jquery-datatables-style', '//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css');
            wp_register_style('woo-wallet-style', woo_wallet()->plugin_url() . '/assets/css/frontend.css', array(), WOO_WALLET_PLUGIN_VERSION);
            // Add RTL support
            wp_style_add_data('woo-wallet-style', 'rtl', 'replace');
            wp_register_script('jquery-datatables-script', '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js', array('jquery'));
            wp_register_script('wc-endpoint-wallet', woo_wallet()->plugin_url() . '/assets/js/frontend/wc-endpoint-wallet' . $suffix . '.js', array('jquery', 'jquery-datatables-script'), WOO_WALLET_PLUGIN_VERSION);
            $wallet_localize_param = array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'search_by_user_email' => apply_filters('woo_wallet_user_search_exact_match', true),
                'i18n' => array(
                    'emptyTable' => __('No transactions available', 'woo-wallet'),
                    'lengthMenu' => sprintf(__('Show %s entries', 'woo-wallet'), '_MENU_'),
                    'info' => sprintf(__('Showing %1s to %2s of %3s entries', 'woo-wallet'), '_START_', '_END_', '_TOTAL_'),
                    'paginate' => array(
                        'first' => __('First', 'woo-wallet'),
                        'last' => __('Last', 'woo-wallet'),
                        'next' => __('Next', 'woo-wallet'),
                        'previous' => __('Previous', 'woo-wallet')
                    ),
                    'non_valid_email_text' => __('Please enter a valid email address', 'woo-wallet'),
                    'no_resualt' => __('No results found', 'woo-wallet'),
                    'inputTooShort' => __('Please enter 3 or more characters', 'woo-wallet'),
                    'searching' => __('Searching…', 'woo-wallet')
                )
            );
            wp_localize_script('wc-endpoint-wallet', 'wallet_param', $wallet_localize_param);
            wp_enqueue_style('woo-wallet-style');
            if (is_account_page()) {
                wp_enqueue_style('dashicons');
                wp_enqueue_style('select2');
                wp_enqueue_style('jquery-datatables-style');
                wp_enqueue_script('selectWoo');
                wp_enqueue_script('jquery-datatables-script');
                wp_enqueue_script('wc-endpoint-wallet');
            }
        }

        /**
         * WooCommerce wallet menu
         * @param array $items
         * @return array
         */
        public function woo_wallet_menu_items($items) {
            unset($items['edit-account']);
            unset($items['customer-logout']);
            $items[get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet')] = apply_filters('woo_wallet_account_menu_title', __('My Wallet', 'woo-wallet'));
            $items['edit-account'] = __('Account details', 'woo-wallet');
            $items['customer-logout'] = __('Logout', 'woo-wallet');
            return $items;
        }

        /**
         * WooCommerce endpoint contents for wallet 
         */
        public function woo_wallet_endpoint_content() {
            woo_wallet()->get_template('wc-endpoint-wallet.php');
        }

        /**
         * WooCommerce endpoint contents for transaction details
         */
        public function woo_wallet_transactions_endpoint_content() {
            woo_wallet()->get_template('wc-endpoint-wallet-transactions.php');
        }

        /**
         * Do wallet frontend load functions.
         */
        public function woo_wallet_frontend_loaded() {
            // reset partial payment session
            if (!is_ajax()) {
                update_wallet_partial_payment_session();
            }
            /**
             * Process wallet recharge.
             */
            if (isset($_POST['woo_add_to_wallet'])) {
                if (isset($_POST['woo_wallet_balance_to_add']) && !empty($_POST['woo_wallet_balance_to_add'])) {
                    $is_valid = $this->is_valid_wallet_recharge_amount($_POST['woo_wallet_balance_to_add']);
                    if ($is_valid['is_valid']) {
                        add_filter('woocommerce_add_cart_item_data', array($this, 'add_woo_wallet_product_price_to_cart_item_data'), 10, 2);
                        $product = get_wallet_rechargeable_product();
                        if ($product) {
                            wc()->cart->empty_cart();
                            wc()->cart->add_to_cart($product->get_id());
                            $redirect_url = apply_filters('woo_wallet_redirect_to_checkout_after_added_amount', true) ? wc_get_checkout_url() : wc_get_cart_url();
                            wp_safe_redirect($redirect_url);
                            exit();
                        }
                    } else {
                        wc_add_notice($is_valid['message'], 'error');
                    }
                }
            }
            /**
             * Process wallet transfer.
             */
            if (isset($_POST['woo_wallet_transfer_fund']) && apply_filters('woo_wallet_is_enable_transfer', 'on' === woo_wallet()->settings_api->get_option('is_enable_wallet_transfer', '_wallet_settings_general', 'on'))) {
                $response = $this->do_wallet_transfer();
                if (!$response['is_valid']) {
                    wc_add_notice($response['message'], 'error');
                } else {
                    wc_add_notice($response['message']);
                }
            }
        }

        /**
         * Check wallet recharge amount.
         * @param float $amount
         * @return array
         */
        public function is_valid_wallet_recharge_amount($amount = 0) {
            $response = array('is_valid' => true);
            $min_topup_amount = woo_wallet()->settings_api->get_option('min_topup_amount', '_wallet_settings_general', 0);
            $max_topup_amount = woo_wallet()->settings_api->get_option('max_topup_amount', '_wallet_settings_general', 0);
            if (isset($_POST['woo_wallet_topup']) && wp_verify_nonce($_POST['woo_wallet_topup'], 'woo_wallet_topup')) {
                if ($min_topup_amount && $amount < $min_topup_amount) {
                    $response = array(
                        'is_valid' => false,
                        'message' => sprintf(__('The minimum amount needed for wallet top up is %s', 'woo-wallet'), wc_price($min_topup_amount))
                    );
                }
                if ($max_topup_amount && $amount > $max_topup_amount) {
                    $response = array(
                        'is_valid' => false,
                        'message' => sprintf(__('Wallet top up amount should be less than %s', 'woo-wallet'), wc_price($max_topup_amount))
                    );
                }
                if ($min_topup_amount && $max_topup_amount && ($amount < $min_topup_amount || $amount > $max_topup_amount)) {
                    $response = array(
                        'is_valid' => false,
                        'message' => sprintf(__('Wallet top up amount should be between %s and %s', 'woo-wallet'), wc_price($min_topup_amount), wc_price($max_topup_amount))
                    );
                }
            } else {
                $response = array(
                    'is_valid' => false,
                    'message' => __('Cheatin&#8217; huh?', 'woo-wallet')
                );
            }
            return apply_filters('woo_wallet_is_valid_wallet_recharge_amount', $response, $amount);
        }

        /**
         * Do transfer wallet amount.
         * @return array
         */
        public function do_wallet_transfer() {
            $response = array('is_valid' => true, 'message' => '');
            if (isset($_POST['woo_wallet_transfer']) && wp_verify_nonce($_POST['woo_wallet_transfer'], 'woo_wallet_transfer')) {
                if (isset($_POST['woo_wallet_transfer_user_id'])) {
                    $whom = $_POST['woo_wallet_transfer_user_id'];
                }
                if (isset($_POST['woo_wallet_transfer_amount'])) {
                    $amount = $_POST['woo_wallet_transfer_amount'];
                }
                $note = isset($_POST['woo_wallet_transfer_note']) ? $_POST['woo_wallet_transfer_note'] : __('Wallet transfer', 'woo-wallet');
                $whom = get_userdata($whom);
                $transfer_charge_type = woo_wallet()->settings_api->get_option('transfer_charge_type', '_wallet_settings_general', 'percent');
                $transfer_charge_amount = woo_wallet()->settings_api->get_option('transfer_charge_amount', '_wallet_settings_general', 0);
                $transfer_charge = 0;
                if ('percent' === $transfer_charge_type) {
                    $transfer_charge = ($amount * $transfer_charge_amount) / 100;
                } else {
                    $transfer_charge = $transfer_charge_amount;
                }
                $transfer_charge = apply_filters('woo_wallet_transfer_charge_amount', $transfer_charge, $whom);
                $credit_amount = apply_filters('woo_wallet_transfer_credit_amount', $amount, $whom);
                $debit_amount = apply_filters('woo_wallet_transfer_debit_amount', $amount + $transfer_charge, $whom);
                if (!$whom) {
                    return array(
                        'is_valid' => false,
                        'message' => __('Invalid user', 'woo-wallet')
                    );
                }
                if (floatval($debit_amount) > woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'edit')) {
                    return array(
                        'is_valid' => false,
                        'message' => __('Entered amount is greater than current wallet amount.', 'woo-wallet')
                    );
                }

                if ($credit_transaction_id = woo_wallet()->wallet->credit($whom->ID, $credit_amount, $note)) {
                    do_action('woo_wallet_transfer_amount_credited', $credit_transaction_id, $whom->ID, get_current_user_id());
                    $debit_transaction_id = woo_wallet()->wallet->debit(get_current_user_id(), $debit_amount, $note);
                    do_action('woo_wallet_transfer_amount_debited', $debit_transaction_id, get_current_user_id(), $whom->ID);
                    update_wallet_transaction_meta($debit_transaction_id, '_wallet_transfer_charge', $transfer_charge, get_current_user_id());
                    $response = array(
                        'is_valid' => true,
                        'message' => __('Amount transferred successfully!!', 'woo-wallet')
                    );
                }
            } else {
                $response = array(
                    'is_valid' => false,
                    'message' => __('Cheatin&#8217; huh?', 'woo-wallet')
                );
            }
            return $response;
        }

        /**
         * WooCommerce add cart item data
         * @param array $cart_item_data
         * @param int $product_id
         * @return array
         */
        public function add_woo_wallet_product_price_to_cart_item_data($cart_item_data, $product_id) {
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
        public function make_woo_wallet_recharge_product_purchasable($is_purchasable, $product) {
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
        public function woo_wallet_set_recharge_product_price($cart) {
            $product = get_wallet_rechargeable_product();
            if (!$product && empty($cart->cart_contents)) {
                return;
            }
            foreach ($cart->cart_contents as $key => $value) {
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
                        wc_add_notice(apply_filters('woo_wallet_restrict_other_from_add_to_cart', __('You can not add another product while your cart contains with wallet rechargeable product.', 'woo-wallet')), 'error');
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
                    <?php
                    if (is_user_logged_in()) {
                        echo apply_filters('woo_wallet_cashback_notice_text', sprintf(__('Upon placing this order a cashback of %s will be credited to your wallet.', 'woo-wallet'), wc_price(get_wallet_cashback_amount())), get_wallet_cashback_amount());
                    } else {
                        echo apply_filters('woo_wallet_cashback_notice_text', sprintf(__('Please <a href="%s">log in</a> to avail %s cashback from this order.', 'woo-wallet'), esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))), wc_price(get_wallet_cashback_amount())), get_wallet_cashback_amount());
                    }
                    ?>
                </div>
                <?php
            endif;
        }

        /**
         * Handel cashback and partial payment on order processed hook
         * @param int $order_id
         * @param array $posted_data
         * @param Object $order
         */
        public function woocommerce_checkout_order_processed($order_id, $posted_data, $order) {
            if (get_wallet_cashback_amount() && !is_wallet_rechargeable_order(wc_get_order($order_id)) && is_user_logged_in()) {
                update_post_meta($order_id, '_wallet_cashback', get_wallet_cashback_amount());
            }
        }

        /**
         * Sets partial payment amount to cart as negative fee
         * @since 1.2.1
         */
        public function woo_wallet_add_partial_payment_fee() {
            $parial_payment_amount = apply_filters('woo_wallet_partial_payment_amount', woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), 'edit'), 'NULL');
            $fee = array(
                'id' => '_via_wallet_partial_payment',
                'name' => __('Via wallet'),
                'amount' => (float) -1 * $parial_payment_amount,
                'taxable' => false,
                'tax_class' => '',
            );
            if (is_enable_wallet_partial_payment()) {
                wc()->cart->fees_api()->add_fee($fee);
            } else {
                $all_fees = wc()->cart->fees_api()->get_fees();
                if (isset($all_fees['_via_partial_payment_wallet'])) {
                    unset($all_fees['_via_partial_payment_wallet']);
                    wc()->cart->fees_api()->set_fees($all_fees);
                }
            }
        }

        /**
         * Unset Fee tax for partial amount
         * @param array $fee_taxes
         * @param object $fee
         * @return array
         */
        public function woocommerce_cart_totals_get_fees_from_cart_taxes($fee_taxes, $fee) {
            if ('_via_wallet_partial_payment' === $fee->object->id) {
                $fee_taxes = array();
            }
            return $fee_taxes;
        }

        /**
         * Function that display partial payment option
         * @return NULL
         */
        public function woocommerce_review_order_after_order_total() {
            if (apply_filters('woo_wallet_disable_partial_payment', (is_full_payment_through_wallet() || is_wallet_rechargeable_cart()))) {
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
            $via_other_gateway = get_post_meta($order->get_id(), '_original_order_amount', true) - get_post_meta($order->get_id(), '_via_wallet_payment', true);
            $order_total = $total_rows['order_total'];
            unset($total_rows['order_total']);
            $total_rows['via_wallet'] = array('label' => __('Via wallet:', 'woo-wallet'), 'value' => wc_price(get_post_meta($order->get_id(), '_via_wallet_payment', true), array('currency' => $order->get_currency())));
            $total_rows['via_other_gateway'] = array('label' => sprintf(__('Via %s:', 'woo-wallet'), $order->get_payment_method_title()), 'value' => wc_price($via_other_gateway, array('currency' => $order->get_currency())));
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
            if ('yes' === $_is_coupon_cashback && is_user_logged_in()) {
                $discount_total = $order->get_discount_total('edit');
                $coupon_amount = WC()->cart->get_coupon_discount_amount($code, WC()->cart->display_cart_ex_tax);
                $discount_total -= $coupon_amount;
                $order->set_discount_total($discount_total);
                $order_id = $order->save();
                $_coupon_cashback_amount = floatval(get_post_meta($order_id, '_coupon_cashback_amount', true));
                update_post_meta($order_id, '_coupon_cashback_amount', ($_coupon_cashback_amount + $coupon_amount));
            }
        }

        /**
         * Display cashback amount in product
         * 
         * @return void
         */
        public function display_cashback() {
            $product = wc_get_product(get_the_ID());
            if ($product && 'on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit') && $product->get_price('edit') && apply_filters('is_display_cashback_on_product', true) && apply_filters('is_product_cashback_enable', true, $product->get_id())) {
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
            if (is_user_logged_in() && 'yes' === $_is_coupon_cashback && 200 === $msg_code) {
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
            if (is_user_logged_in() && 'yes' === $_is_coupon_cashback) {
                $label = sprintf(esc_html__('Cashback: %s', 'woo-wallet'), $coupon->get_code());
            }
            return $label;
        }

        /**
         * Update WC Cart get_total if cashback coupon applied.
         * @param float $total
         * @return float
         */
        public function woocommerce_cart_get_total($total) {
            if (is_user_logged_in()) {
                foreach (WC()->cart->get_applied_coupons() as $code) {
                    $coupon = new WC_Coupon($code);
                    $_is_coupon_cashback = get_post_meta($coupon->get_id(), '_is_coupon_cashback', true);
                    if ('yes' === $_is_coupon_cashback) {
                        $total += WC()->cart->get_coupon_discount_amount($code, WC()->cart->display_cart_ex_tax);
                    }
                }
            }
            return $total;
        }

        /**
         * Shortcode Wrapper.
         *
         * @param string[] $function Callback function.
         * @param array    $atts     Attributes. Default to empty array.
         *
         * @return string
         */
        public static function shortcode_wrapper($function, $atts = array()) {
            ob_start();
            call_user_func($function, $atts);
            return ob_get_clean();
        }

        /**
         * Wallet shortcode callback
         * @param array $atts
         * @return string
         */
        public static function woo_wallet_shortcode_callback($atts) {
            return self::shortcode_wrapper(array('Woo_Wallet_Frontend', 'woo_wallet_shortcode_output'), $atts);
        }

        /**
         * Wallet shortcode output
         * @param array $atts
         */
        public static function woo_wallet_shortcode_output($atts) {
            if (!is_user_logged_in()) {
                echo '<div class="woocommerce">';
                wc_get_template('myaccount/form-login.php');
                echo '</div>';
            } else {
                wp_enqueue_style('dashicons');
                wp_enqueue_style('select2');
                wp_enqueue_style('jquery-datatables-style');
                wp_enqueue_script('jquery-datatables-script');
                wp_enqueue_script('selectWoo');
                wp_enqueue_script('wc-endpoint-wallet');
                if (isset($_GET['wallet_action']) && !empty($_GET['wallet_action'])) {
                    if ('view_transactions' === $_GET['wallet_action']) {
                        woo_wallet()->get_template('wc-endpoint-wallet-transactions.php');
                    } else if (in_array($_GET['wallet_action'], array('add', 'transfer'))) {
                        woo_wallet()->get_template('wc-endpoint-wallet.php');
                    }
                    do_action('woo_wallet_shortcode_action', $_GET['wallet_action']);
                } else {
                    woo_wallet()->get_template('wc-endpoint-wallet.php');
                }
            }
        }

    }

}
Woo_Wallet_Frontend::instance();
