<?php

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Woo_Wallet_Dokan')) {

    class Woo_Wallet_Dokan {

        public function __construct() {
            add_filter('dokan_withdraw_methods', array($this, 'load_withdraw_method'));
            add_filter('dokan_get_seller_active_withdraw_methods', array($this, 'dokan_get_seller_active_withdraw_methods'));
        }

        /**
         * Add wallet method 
         * @param array $methods
         * @return array
         */
        public function load_withdraw_method($methods) {
            $methods['woo_wallet'] = array(
                'title' => __('Wallet', 'woo-wallet'),
                'callback' => '__return_false'
            );

            return $methods;
        }

        /**
         * Display wallet method to vendor
         * @param array $active_payment_methods
         * @return array
         */
        public function dokan_get_seller_active_withdraw_methods($active_payment_methods) {
            $options = get_option('dokan_withdraw', array());
            $withdraw_methods = !empty($options['withdraw_methods']) ? $options['withdraw_methods'] : array();
            if (array_key_exists('woo_wallet', $withdraw_methods)) {
                $active_payment_methods[] = 'woo_wallet';
            }
            return $active_payment_methods;
        }

    }

}
new Woo_Wallet_Dokan();
