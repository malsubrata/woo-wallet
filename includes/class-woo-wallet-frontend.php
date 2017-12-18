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
        }

        /**
         * Register and enqueue frontend styles and scripts
         */
        public function wc_wallet_payment_styles() {
            $wp_scripts = wp_scripts();
            wp_register_style('woo-wallet-payment-jquery-ui', '//ajax.googleapis.com/ajax/libs/jqueryui/' . $wp_scripts->registered['jquery-ui-core']->ver . '/themes/smoothness/jquery-ui.css', false, $wp_scripts->registered['jquery-ui-core']->ver, false);
            wp_register_style('jquery-datatables-style', '//cdn.datatables.net/1.10.16/css/jquery.dataTables.min.css');
            wp_register_style('woo-endpoint-wallet-style', woo_wallet()->plugin_url() . '/assets/frontend/css/wc-endpoint-wallet.css', array(), WOO_WALLET_PLUGIN_VERSION);
            wp_register_script('jquery-datatables-script', '//cdn.datatables.net/1.10.16/js/jquery.dataTables.min.js', array('jquery'));
            wp_register_script('wc-endpoint-wallet-transactions', woo_wallet()->plugin_url() . '/assets/frontend/js/wc-endpoint-wallet-transactions.js', array('jquery', 'jquery-datatables-script'), WOO_WALLET_PLUGIN_VERSION);
            if (is_account_page()) {
                wp_enqueue_style('jquery-datatables-style');
                wp_enqueue_style('woo-endpoint-wallet-style');
                wp_enqueue_script('jquery-datatables-script');
                wp_enqueue_script('wc-endpoint-wallet-transactions');
            }
        }

        /**
         * WooCommerce wallet menu
         * @param array $items
         * @return array
         */
        public function wc_wallet_menu_items($items) {
            unset($items['edit-account']);
            unset($items['customer-logout']);
            $items['woo-wallet'] = __('My Wallet', 'woo-wallet');
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
            if (!is_full_payment_through_wallet() && isset($_POST['partial_pay_through_wallet']) && !empty($_POST['partial_pay_through_wallet'])) {
                $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '');
                update_post_meta($order_id, '_original_order_amount', $order->get_total(''));
                $order->set_total($order->get_total('') - $current_wallet_balance);
                update_post_meta($order_id, '_via_wallet_payment', $current_wallet_balance);
                $order->save();
            }
        }

        public function woocommerce_review_order_after_order_total() {
            if (is_full_payment_through_wallet() || is_wallet_rechargeable_cart() || woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '') <= 0) {
                return;
            }
            $rest_amount = wc()->cart->get_total('') - woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), '');
            wp_enqueue_style('woo-wallet-payment-jquery-ui');
            wp_enqueue_script('jquery-ui-tooltip');
            ?>
            <tr class="wallet-pay-partial">
                <th><?php _e('Pay by wallet', 'woo-wallet'); ?> <span id="partial_wallet_payment_tooltip" style="vertical-align: middle;" title="<?php echo sprintf('If checked %s%0.2f will be debited from your wallet and %s%0.2f will be paid throught other payment method', get_woocommerce_currency_symbol(), woo_wallet()->wallet->get_wallet_balance(get_current_user_id(), ''),get_woocommerce_currency_symbol(), $rest_amount); ?>" class="dashicons dashicons-info"></span></th>
                <td data-title="<?php esc_attr_e('Pay by wallet', 'woo-wallet'); ?>"><input type="checkbox" style="vertical-align: middle;" name="partial_pay_through_wallet" class="partial_pay_through_wallet" /></td>
            </tr>
            <script type="text/javascript">
                jQuery(function ($) {
                    $('#partial_wallet_payment_tooltip').tooltip();
                });
            </script>
            <?php
        }
        /**
         * Add wallet withdrawal amount to thank you page
         * @param array $total_rows
         * @param Object $order
         * @return array
         */
        public function woocommerce_get_order_item_totals($total_rows, $order){
            if(!get_post_meta($order->get_id(), '_via_wallet_payment', true)){
                return $total_rows;
            }
            $order_total = $total_rows['order_total'];
            unset($total_rows['order_total']);
            $total_rows['via_wallet'] = array('label' => __('Via wallet:', 'woo-wallet'), 'value' => wc_price(get_post_meta($order->get_id(), '_via_wallet_payment', true), array( 'currency' => $order->get_currency() )));
            $total_rows['order_total'] = $order_total;
            return $total_rows;
        }
    }

}
new Woo_Wallet_Frontend();
