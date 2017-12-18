<?php

/**
 * WC Qr codes settings
 *
 * @author Bappa Mal
 */
if (!class_exists('Woo_Wallet_Settings')):

    class Woo_Wallet_Settings {
        /* setting api object */

        private $settings_api;

        /**
         * Class constructor
         * @param object $settings_api
         */
        public function __construct($settings_api) {
            $this->settings_api = $settings_api;

            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'), 60);
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        }

        /**
         * admin init 
         */
        public function admin_init() {
            //set the settings
            $this->settings_api->set_sections($this->get_settings_sections());
            foreach ($this->get_settings_sections() as $section) {
                if (method_exists($this, "update_option_{$section['id']}_callback")) {
                    add_action("update_option_{$section['id']}", array($this, "update_option_{$section['id']}_callback"), 10, 3);
                }
            }
            $this->settings_api->set_fields($this->get_settings_fields());
            //initialize settings
            $this->settings_api->admin_init();
        }

        /**
         * wc wallet menu
         */
        public function admin_menu() {
            add_submenu_page('woo-wallet', __('Settings', 'woo-wallet'), __('Settings', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet-settings', array($this, 'plugin_page'));
        }

        /**
         * Enqueue scripts and styles
         */
        public function admin_enqueue_scripts() {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            wp_register_script('woo-wallet-admin-settings', woo_wallet()->plugin_url() . '/assets/admin/js/admin-settings.js', array('jquery'), '1.0.0');
            if (in_array($screen_id, array('woowallet_page_woo-wallet-settings'))) {
                wp_enqueue_script('woo-wallet-admin-settings');
                $localize_param = array(
                    'gateways' => $this->get_wc_payment_gateways('id')
                );
                wp_localize_script('woo-wallet-admin-settings', 'woo_wallet_admin_settings_param', $localize_param);
            }
        }

        /**
         * Setting sections
         * @return array
         */
        public function get_settings_sections() {
            $sections = array(
                array(
                    'id' => '_wallet_settings_general',
                    'title' => __('General', 'woo-wallet')
                ),
                array(
                    'id' => '_wallet_settings_credit',
                    'title' => __('Credit', 'woo-wallet')
                ),
//                array(
//                    'id' => '_wallet_settings_withdrawal',
//                    'title' => __('Withdrawal', 'woo-wallet')
//                )
            );
            return apply_filters('wc_wallet_payment_settings_sections', $sections);
        }

        /**
         * Returns all the settings fields
         *
         * @return array settings fields
         */
        public function get_settings_fields() {
            $settings_fields = array(
                '_wallet_settings_general' => array_merge(array(
                    array(
                        'name' => 'product_title',
                        'label' => __('Rechargeable Product Title', 'woo-wallet'),
                        'desc' => __('Enter wallet rechargeable product title', 'woo-wallet'),
                        'type' => 'text',
                        'default' => $this->get_rechargeable_product_title()
                    )), $this->get_wc_payment_allowed_gateways()
                ),
                '_wallet_settings_credit' => array_merge(array(
                    array(
                        'name' => 'is_enable_cashback_reward_program',
                        'label' => __('Cashback Reward Program', 'woo-wallet'),
                        'desc' => __('Run cashback reward program on your store', 'woo-wallet'),
                        'type' => 'checkbox',
                    ),
                    array(
                        'name' => 'cashback_rule',
                        'label' => __('Cashback Rule', 'woo-wallet'),
                        'desc' => __('Select Cashback Rule cart or product wise', 'woo-wallet'),
                        'type' => 'select',
                        'options' => array('cart' => __('Cart wise', 'woo-wallet'), 'product' => __('Product wise', 'woo-wallet')),
                        'size' => 'regular-text'
                    ),
                    array(
                        'name' => 'cashback_type',
                        'label' => __('Cashback type', 'woo-wallet'),
                        'desc' => __('Select cashback type percentage or fixed', 'woo-wallet'),
                        'type' => 'select',
                        'options' => array('percent' => __('Percentage', 'woo-wallet'), 'fixed' => __('Fixed', 'woo-wallet')),
                        'size' => 'regular-text'
                    ),
                    array(
                        'name' => 'cashback_amount',
                        'label' => __('Cashback Amount', 'woo-wallet'),
                        'desc' => __('Enter cashback amount', 'woo-wallet'),
                        'type' => 'number',
                    ),
                    array(
                        'name' => 'is_enable_gateway_charge',
                        'label' => __('Payment gateway charge', 'woo-wallet'),
                        'desc' => __('Charge customer when they add balance to there wallet?', 'woo-wallet'),
                        'type' => 'checkbox',
                    ),
                    array(
                        'name' => 'gateway_charge_type',
                        'label' => __('Charge type', 'woo-wallet'),
                        'desc' => __('Select gateway charge type percentage or fixed', 'woo-wallet'),
                        'type' => 'select',
                        'options' => array('percent' => __('Percentage', 'woo-wallet'), 'fixed' => __('Fixed', 'woo-wallet')),
                        'size' => 'regular-text'
                    )), $this->get_wc_payment_gateways(), array()
                ),
                '_wallet_settings_withdrawal' => array(
                    array(
                        'name' => 'is_enable_withdrawal',
                        'label' => __('Enable Withdrawal', 'woo-wallet'),
                        'desc' => __('Is user withdrawal there wallet balance', 'woo-wallet'),
                        'type' => 'checkbox',
                        'default' => 'on'
                    ),
                    array(
                        'name' => 'is_withdrawal_chargeable',
                        'label' => __('Enable Withdrawal charge', 'woo-wallet'),
                        'desc' => __('Enable Withdrawal charge', 'woo-wallet'),
                        'type' => 'checkbox'
                    ),
                    array(
                        'name' => 'withdrawal_charge_type',
                        'label' => __('Charge type', 'woo-wallet'),
                        'desc' => __('Select withdrawal charge type percentage or fixed', 'woo-wallet'),
                        'type' => 'select',
                        'options' => array('percent' => __('Percentage', 'woo-wallet'), 'fixed' => __('Fixed', 'woo-wallet')),
                        'size' => 'regular-text'
                    ),
                    array(
                        'name' => 'withdrawal_charge_amount',
                        'label' => __('Withdrawal Charge Amount', 'woo-wallet'),
                        'desc' => __('Enter wallet charge amount', 'woo-wallet'),
                        'type' => 'number',
                    )
                )
            );
            return apply_filters('wc_wallet_payment_settings_filds', $settings_fields);
        }

        /**
         * Fetch rechargeable product title
         * @return string title
         */
        public function get_rechargeable_product_title() {
            $product_title = '';
            $wallet_product = get_wallet_rechargeable_product();
            if ($wallet_product) {
                $product_title = $wallet_product->get_title();
            }
            return $product_title;
        }

        /**
         * Set rechargeable product title
         * @param string $title
         * @return boolean | int 
         */
        public function set_rechargeable_product_title($title) {
            $wallet_product = get_wallet_rechargeable_product();
            if ($wallet_product) {
                $wallet_product->set_name($title);
                return $wallet_product->save();
            }
            return false;
        }

        /**
         * display plugin settings page
         */
        public function plugin_page() {
            echo '<div class="wrap">';
            settings_errors();
            $this->settings_api->show_navigation();
            $this->settings_api->show_forms();
            echo '</div>';
        }

        /**
         * Chargeable payment gateways
         * @param string $context
         * @return array
         */
        public function get_wc_payment_gateways($context = 'field') {
            $gateways = array();
            foreach (WC()->payment_gateways->payment_gateways() as $gateway) {
                if ('yes' === $gateway->enabled && $gateway->id != 'wallet') {
                    $method_title = $gateway->get_title() ? $gateway->get_title() : __('(no title)', 'woo-wallet');
                    if ($context == 'field') {
                        $gateways[] = array(
                            'name' => $gateway->id,
                            'label' => $method_title,
                            'desc' => __('Enter gateway charge amount for ', 'woo-wallet') . $method_title,
                            'type' => 'text',
                        );
                    } else {
                        $gateways[] = $gateway->id;
                    }
                }
            }
            return $gateways;
        }

        /**
         * allowed payment gateways
         * @param string $context
         * @return array
         */
        public function get_wc_payment_allowed_gateways($context = 'field') {
            $gateways = array();
            foreach (WC()->payment_gateways->payment_gateways() as $gateway) {
                if ('yes' === $gateway->enabled && $gateway->id != 'wallet') {
                    $method_title = $gateway->get_title() ? $gateway->get_title() : __('(no title)', 'woo-wallet');
                    if ($context == 'field') {
                        $gateways[] = array(
                            'name' => $gateway->id,
                            'label' => $method_title,
                            'desc' => __('Allow this gatway for recharge wallet', 'woo-wallet'),
                            'type' => 'checkbox',
                            'default' => 'on'
                        );
                    }
                }
            }
            return $gateways;
        }

        /**
         * Callback fuction of all option after save
         * @param array $old_value
         * @param array $value
         * @param string $option
         */
        public function update_option__wallet_settings_general_callback($old_value, $value, $option) {
            /**
             * save product title on option change
             */
            if ($old_value['product_title'] != $value['product_title']) {
                $this->set_rechargeable_product_title($value['product_title']);
            }
        }

    }

    endif;

new Woo_Wallet_Settings(woo_wallet()->settings_api);
