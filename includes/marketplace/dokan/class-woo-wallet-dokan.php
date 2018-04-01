<?php

if (!defined('ABSPATH')) {
    exit;
}
if (!class_exists('Woo_Wallet_Dokan')) {

    class Woo_Wallet_Dokan {

        public function __construct() {
            add_filter('dokan_withdraw_methods', array($this, 'load_withdraw_method'));
            add_filter('dokan_get_seller_active_withdraw_methods', array($this, 'dokan_get_seller_active_withdraw_methods'));
            add_action('woo_wallet_payment_processed', array($this, 'woo_wallet_payment_processed'));
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

        /**
         * Process wallet commission transfer
         * @param int $order_id
         * @return Void
         * @throws Exception
         */
        public function woo_wallet_payment_processed($order_id) {
            $order = wc_get_order($order_id);
            $options = get_option('dokan_withdraw', array());
            $withdraw_methods = !empty($options['withdraw_methods']) ? $options['withdraw_methods'] : array();
            if (!array_key_exists('woo_wallet', $withdraw_methods)) {
                return;
            }
            $has_suborder = get_post_meta($order_id, 'has_sub_order', true);
            $all_orders = array();
            $all_withdraws = array();
            if ($has_suborder == '1') {
                $sub_orders = get_children(array('post_parent' => $order_id, 'post_type' => 'shop_order'));
                foreach ($sub_orders as $order_post) {
                    $sub_order = wc_get_order($order_post->ID);
                    $all_orders[] = $sub_order;
                }
            } else {
                $all_orders[] = $order;
            }
            foreach ($all_orders as $tmp_order) {
                $tmp_order_id = dokan_get_prop($tmp_order, 'id');
                $seller_id = get_post_field('post_author', $tmp_order_id);
                $do_order = $this->get_dokan_order($tmp_order_id, $seller_id);
                // in-case we can't find the order
                if (!$do_order) {
                    throw new Exception(__('Something went wrong and the order can not be processed!', 'dokan'));
                }
                $fee = floatval($do_order->order_total) - floatval($do_order->net_amount);
                $order_total = round($do_order->order_total, 2);
                $application_fee = round($fee, 2);
                if ($do_order->order_total == 0) {
                    $tmp_order->add_order_note(sprintf(__('Order %s payment completed', 'dokan'), $tmp_order->get_order_number()));
                    continue;
                }
                //data array for withdraw
                $withdraw_data = array(
                    'user_id' => $seller_id,
                    'amount' => $order_total - $application_fee,
                    'order_id' => $tmp_order_id,
                );

                $all_withdraws[] = $withdraw_data;
            }

            $this->process_seller_withdraws($all_withdraws);
        }

        /**
         * Get order details
         *
         * @param  int  $order_id
         * @param  int  $seller_id
         *
         * @return array
         */
        public function get_dokan_order($order_id, $seller_id) {
            global $wpdb;
            $sql = "SELECT * FROM {$wpdb->prefix}dokan_orders AS do WHERE do.seller_id = %d AND do.order_id = %d";
            return $wpdb->get_row($wpdb->prepare($sql, $seller_id, $order_id));
        }

        /**
         * Automatically process withdrwal for sellers per order
         *
         * @since 1.3.2
         *
         * @param array $all_withdraws
         *
         * @return void
         */
        public function process_seller_withdraws($all_withdraws = array()) {
            if (!empty($all_withdraws)) {
                $IP = dokan_get_client_ip();
                $withdraw = new Dokan_Withdraw();
                foreach ($all_withdraws as $withdraw_data) {

                    $data = array(
                        'date' => current_time('mysql'),
                        'status' => 1,
                        'method' => 'woo_wallet',
                        'notes' => sprintf(__('Order %d payment Auto paid via Wallet', 'woo-wallet'), $withdraw_data['order_id']),
                        'ip' => $IP
                    );

                    $data = array_merge($data, $withdraw_data);
                    $transaction_id = woo_wallet()->wallet->credit($data['user_id'], $data['amount'], __('Commission received for order id ', 'woo-wallet') . $data['order_id']);
                    if ($transaction_id) {
                        update_wallet_transaction_meta($transaction_id, '_type', 'vendor_commission', $data['user_id']);
                        $withdraw->insert_withdraw($data);
                    }
                }
            }
        }

    }

}
new Woo_Wallet_Dokan();
