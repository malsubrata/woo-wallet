<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Woo_Wallet_WCFMMP')) {

    class Woo_Wallet_WCFMMP {

        /**
         * The single instance of the class.
         *
         * @var Woo_Wallet_WCFMMP
         * @since 1.2.9
         */
        protected static $_instance = null;

        /**
         * Gateway slug.
         * @var string 
         */
        public static $gateway_slug = 'woo_wallet';

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

        public function __construct() {
            add_action('wcfm_init', array($this, 'init'), 10);
        }

        public function init() {
            add_filter('wcfm_marketplace_withdrwal_payment_methods', array($this, 'wcfm_marketplace_withdrwal_payment_methods'));
            add_filter('wcfm_marketplace_settings_fields_withdrawal_charges', array($this, 'wcfm_marketplace_settings_fields_withdrawal_charges'), 50, 3);
            require_once WOO_WALLET_ABSPATH . 'includes/marketplace/wcfmmp/class-woo-wallet-wcfmmp-gateway.php';
        }

        public function wcfm_marketplace_withdrwal_payment_methods($payment_methods) {
            $payment_methods[self::$gateway_slug] = 'Wallet';
            return $payment_methods;
        }

        public function wcfm_marketplace_settings_fields_withdrawal_charges($withdrawal_charges, $wcfm_withdrawal_options, $withdrawal_charge) {
            $withdrawal_charge_wallet = isset($withdrawal_charge[self::$gateway_slug]) ? $withdrawal_charge[self::$gateway_slug] : array();
            $payment_withdrawal_charges = array("withdrawal_charge_" . self::$gateway_slug => array('label' => __('Wallet Charge', 'woo-wallet'), 'type' => 'multiinput', 'name' => 'wcfm_withdrawal_options[withdrawal_charge][' . self::$gateway_slug . ']', 'class' => 'withdraw_charge_block withdraw_charge_' . self::$gateway_slug, 'label_class' => 'wcfm_title wcfm_ele wcfm_fill_ele withdraw_charge_block withdraw_charge_' . self::$gateway_slug, 'value' => $withdrawal_charge_wallet, 'custom_attributes' => array('limit' => 1), 'options' => array(
                        "percent" => array('label' => __('Percent Charge(%)', 'woo-wallet'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele withdraw_charge_field withdraw_charge_percent withdraw_charge_percent_fixed', 'label_class' => 'wcfm_title wcfm_ele withdraw_charge_field withdraw_charge_percent withdraw_charge_percent_fixed', 'attributes' => array('min' => '0.1', 'step' => '0.1')),
                        "fixed" => array('label' => __('Fixed Charge', 'woo-wallet'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele withdraw_charge_field withdraw_charge_fixed withdraw_charge_percent_fixed', 'label_class' => 'wcfm_title wcfm_ele withdraw_charge_field withdraw_charge_fixed withdraw_charge_percent_fixed', 'attributes' => array('min' => '0.1', 'step' => '0.1')),
                        "tax" => array('label' => __('Charge Tax', 'woo-wallet'), 'type' => 'number', 'class' => 'wcfm-text wcfm_ele', 'label_class' => 'wcfm_title wcfm_ele', 'attributes' => array('min' => '0.1', 'step' => '0.1'), 'hints' => __('Tax for withdrawal charge, calculate in percent.', 'woo-wallet')),
            )));
            return array_merge($withdrawal_charges, $payment_withdrawal_charges);
        }

    }

}

Woo_Wallet_WCFMMP::instance();

