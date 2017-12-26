<?php

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Woo_Wallet_WCMp')) {

    class Woo_Wallet_WCMp {

        public function __construct() {
            add_filter('automatic_payment_method', array($this, 'add_wallet_payment_method'));
            add_filter('wcmp_vendor_payment_mode', array($this, 'add_wallet_vendor_payment_method'));
            add_filter('wcmp_payment_gateways', array(&$this, 'add_wcmp_wallet_payment_gateway'));
        }

        public function add_wallet_payment_method($payment_methods) {
            return array_merge($payment_methods, array('woo_wallet' => __('Wallet', 'woo-wallet')));
        }

        public function add_wallet_vendor_payment_method($payment_method) {
            if ('Enable' === get_wcmp_vendor_settings('payment_method_woo_wallet', 'payment')) {
                return array_merge($payment_method, array('woo_wallet' => __('Wallet', 'woo-wallet')));
            }
            return $payment_method;
        }

        public function add_wcmp_wallet_payment_gateway($load_gateways) {
            $load_gateways[] = 'WCMp_Gateway_Wallet';
            return $load_gateways;
        }

    }

}
new Woo_Wallet_WCMp();
