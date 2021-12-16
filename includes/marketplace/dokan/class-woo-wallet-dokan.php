<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Woo_Wallet_Dokan')) {

    class Woo_Wallet_Dokan {

        /**
         * The single instance of the class.
         *
         * @var Woo_Wallet_Dokan
         * @since 1.1.10
         */
        protected static $_instance = null;

        /**
         * Dokan withdrawal method ID.
         * @var string
         * @since 1.2.3 
         */
        private static $method_id = 'woo_wallet';

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
            add_filter('dokan_settings_fields', array($this, 'dokan_settings_fields'));
            
            add_filter('dokan_withdraw_methods', array($this, 'load_withdraw_method'));
            add_filter('dokan_get_seller_active_withdraw_methods', array($this, 'dokan_get_seller_active_withdraw_methods'));
            
            add_action('dokan_withdraw_created', array($this, 'dokan_withdraw_created_callback'));
            add_action('dokan_withdraw_updated', array($this, 'dokan_withdraw_updated_callback'));

            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on') && 'product' === woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart')) {
                add_filter('dokan_settings_selling_option_vendor_capability', array($this, 'dokan_settings_selling_option_vendor_capability'));
                add_action('dokan_product_edit_after_options', array($this, 'dokan_product_edit_after_options'));
                add_action('dokan_product_updated', array($this, 'dokan_product_updated'));
            }
            
            // Cashback issue fixing
            add_filter('process_woo_wallet_general_cashback', array($this, 'process_woo_wallet_cashback_callback'), 10, 2);
            add_filter('process_woo_wallet_coupon_cashback', array($this, 'process_woo_wallet_cashback_callback'), 10, 2);
        }
        /**
         * Adding auto pay wallet withdraw settings
         * @param array $settings_fields
         * @return array
         */
        public function dokan_settings_fields($settings_fields) {
            $withdraw_methods = dokan_withdraw_get_active_methods();
            if (array_key_exists(self::$method_id, $withdraw_methods) && !empty($withdraw_methods[self::$method_id])) {
                $settings_fields['dokan_withdraw']['auto_approve_wallet_withdraw'] = [
                    'name' => 'auto_approve_wallet_withdraw',
                    'label' => __('Wallet Withdraw', 'woo-wallet'),
                    'desc' => __('Auto approve wallet withdraw on request', 'woo-wallet'),
                    'type' => 'checkbox',
                    'default' => 'off',
                ];
            }
            return $settings_fields;
        }
        
        /**
         * Add wallet method 
         * @param array $methods
         * @return array
         */
        public function load_withdraw_method($methods) {
            $methods[self::$method_id] = array(
                'title' => __('Wallet', 'woo-wallet')
            );

            return $methods;
        }
        
        /**
         * Display wallet method to vendor
         * @param array $active_payment_methods
         * @return array
         */
        public function dokan_get_seller_active_withdraw_methods($active_payment_methods) {
            $withdraw_methods = dokan_withdraw_get_active_methods();
            if (array_key_exists(self::$method_id, $withdraw_methods) && !empty($withdraw_methods[self::$method_id])) {
                $active_payment_methods[] = self::$method_id;
            }
            return $active_payment_methods;
        }
        
        /**
         * Auto pay wallet withdraw 
         * @param Withdraw $withdraw
         * @return null
         */
        public function dokan_withdraw_created_callback($withdraw) {
            if ($withdraw->get_method() != self::$method_id) {
                return;
            }

            $is_auto_withdrawal_enabled = dokan_get_option('auto_approve_wallet_withdraw', 'dokan_withdraw', 'off') !== 'off' ? true : false;
            if (!$is_auto_withdrawal_enabled) {
                return;
            }
            $withdraw->set_status(1);
            $withdraw->save();
        }

        /**
         * Credit user wallet upon withdrawal status change.
         * @param Withdraw $withdraw
         */
        public function dokan_withdraw_updated_callback($withdraw) {
            global $wpdb;
            if ($withdraw->get_method() != self::$method_id) {
                return;
            }
            $wallet_transaction = $wpdb->get_row($wpdb->prepare("SELECT transactions.transaction_id FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions INNER JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta AS transaction_meta ON transactions.transaction_id = transaction_meta.transaction_id WHERE transaction_meta.meta_key = %s AND transaction_meta.meta_value = %d", '_dokan_withdrawal_id', $withdraw->get_id()));
            if (1 === $withdraw->get_status()) {
                if ($wallet_transaction && isset($wallet_transaction->transaction_id)) {
                    update_wallet_transaction($wallet_transaction->transaction_id, $withdraw->get_user_id(), array('deleted' => 0), array('%d'));
                } else {
                    $transaction_id = woo_wallet()->wallet->credit($withdraw->get_user_id(), $withdraw->get_amount(), __('Withdrawal request #', 'woo-wallet') . $withdraw->get_id());
                    update_wallet_transaction_meta($transaction_id, '_dokan_withdrawal_id', $withdraw->get_id());
                }
            } else {
                if ($wallet_transaction && isset($wallet_transaction->transaction_id)) {
                    update_wallet_transaction($wallet_transaction->transaction_id, $withdraw->get_user_id(), array('deleted' => 1), array('%d'));
                }
            }
        }
        
        /**
         * Add cashback option change capability.
         * @param array $vendor_capability
         * @return array
         */
        public function dokan_settings_selling_option_vendor_capability($vendor_capability) {
            $vendor_capability['product_cahback_change'] = [
                'name' => 'product_cahback_change',
                'label' => __('Cashback Option', 'woo-wallet'),
                'desc' => __('Allow vendor to update product cashback', 'woo-wallet'),
                'type' => 'checkbox',
                'default' => 'off',
                'tooltip' => __('Checking this will enable sellers to change the product cashback option. If unchecked, only admin can change product cashback.', 'woo-wallet'),
            ];
            return $vendor_capability;
        }

        /**
         * Dokan cashback settings form.
         * @global WP_Post Object $post
         * @param Post ID $post_id
         */
        public function dokan_product_edit_after_options($post_id) {
            $is_allow_product_cashback = dokan_get_option('product_cahback_change', 'dokan_selling', 'off') !== 'off' ? true : false;
            if(!$is_allow_product_cashback){
                return;
            }
            // REMOVE IF DOKAN MERGE PULL REQUEST
            global $post;
            if (!$post_id) {
                if (isset($post->ID) && $post->ID && $post->post_type == 'product') {
                    $post_id = $post->ID;
                }
                if (isset($_GET['product_id'])) {
                    $post_id = intval($_GET['product_id']);
                }
            }
            // END
            ?>
            <div class="dokan-cashback-options dokan-edit-row dokan-clearfix" style="margin-bottom: 20px;">
                <div class="dokan-section-heading" data-togglehandler="dokan_cashback_options">
                    <h2><i class="fas fa-wallet" aria-hidden="true"></i> <?php _e('Cashback Options', 'woo-wallet'); ?></h2>
                    <p><?php _e('Set product cashback options', 'woo-wallet'); ?></p>
                    <a href="#" class="dokan-section-toggle">
                        <i class="fa fa-sort-desc fa-flip-vertical" aria-hidden="true"></i>
                    </a>
                    <div class="dokan-clearfix"></div>
                </div>

                <div class="dokan-section-content">

                    <div class="dokan-form-group content-half-part">
                        <label for="_cashback_type" class="form-label"><?php _e('Cashback type', 'woo-wallet'); ?></label>
                        <?php
                        dokan_post_input_box($post_id, '_cashback_type', array('options' => array(
                                'percent' => __('Percentage', 'woo-wallet'),
                                'fixed' => __('Fixed', 'woo-wallet')
                            )), 'select');
                        ?>
                    </div>

                    <div class="dokan-form-group content-half-part">
                        <label for="_cashback_amount" class="form-label"><?php _e('Cashback Amount', 'woo-wallet'); ?></label>
                        <div class="dokan-input-group">
                            <span class="dokan-input-group-addon"><?php echo get_woocommerce_currency_symbol(); ?></span>
                            <?php dokan_post_input_box($post_id, '_cashback_amount', array('class' => 'dokan-product-sales-price', 'placeholder' => __('0.00', 'woo-wallet')), 'number'); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        /**
         * Update product meta
         * @param INT $post_id
         */
        public function dokan_product_updated($post_id) {
            $is_allow_product_cashback = dokan_get_option('product_cahback_change', 'dokan_selling', 'off') !== 'off' ? true : false;
            if(!$is_allow_product_cashback){
                return;
            }
            if (isset($_POST['_cashback_type'])) {
                update_post_meta($post_id, '_cashback_type', esc_attr($_POST['_cashback_type']));
            }
            if (isset($_POST['_cashback_amount'])) {
                update_post_meta($post_id, '_cashback_amount', sanitize_text_field($_POST['_cashback_amount']));
            }
        }
        /**
         * 
         * @param bool $process_cashback
         * @param WC_Order $order
         * @return bool
         */
        public function process_woo_wallet_cashback_callback($process_cashback, $order) {
            if($order->get_parent_id()){
                return false;
            }
            return $process_cashback;
        }
    
    }

}
Woo_Wallet_Dokan::instance();
