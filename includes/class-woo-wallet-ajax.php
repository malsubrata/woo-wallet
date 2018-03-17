<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_Ajax')) {

    class Woo_Wallet_Ajax {

        /**
         * Class constructor
         */
        public function __construct() {
            add_action('wp_ajax_woo_wallet_order_refund', array($this, 'woo_wallet_order_refund'));
            add_action('wp_ajax_woocommerce_wallet_rated', array($this, 'woocommerce_wallet_rated'));
            add_action('wp_ajax_woo-wallet-user-search', array($this, 'woo_wallet_user_search'));
        }

        /**
         * Process refund through wallet
         * @throws exception
         * @throws Exception
         */
        public function woo_wallet_order_refund() {
            ob_start();
            check_ajax_referer('order-item', 'security');
            if (!current_user_can('edit_shop_orders')) {
                wp_die(-1);
            }
	    $approver = get_current_user_id();
            $order_id = absint($_POST['order_id']);
            $refund_amount = wc_format_decimal(sanitize_text_field($_POST['refund_amount']), wc_get_price_decimals());
            $refund_reason = sanitize_text_field($_POST['refund_reason']);
            $line_item_qtys = json_decode(sanitize_text_field(stripslashes($_POST['line_item_qtys'])), true);
            $line_item_totals = json_decode(sanitize_text_field(stripslashes($_POST['line_item_totals'])), true);
            $line_item_tax_totals = json_decode(sanitize_text_field(stripslashes($_POST['line_item_tax_totals'])), true);
            $api_refund = 'true' === $_POST['api_refund'];
            $restock_refunded_items = 'true' === $_POST['restock_refunded_items'];
            $refund = false;
            $response_data = array();
            try {
                $order = wc_get_order($order_id);
                $order_items = $order->get_items();
                $max_refund = wc_format_decimal($order->get_total() - $order->get_total_refunded(), wc_get_price_decimals());

                if (!$refund_amount || $max_refund < $refund_amount || 0 > $refund_amount) {
                    throw new exception(__('Invalid refund amount', 'woo-wallet'));
                }
                // Prepare line items which we are refunding
                $line_items = array();
                $item_ids = array_unique(array_merge(array_keys($line_item_qtys, $line_item_totals)));

                foreach ($item_ids as $item_id) {
                    $line_items[$item_id] = array('qty' => 0, 'refund_total' => 0, 'refund_tax' => array());
                }
                foreach ($line_item_qtys as $item_id => $qty) {
                    $line_items[$item_id]['qty'] = max($qty, 0);
                }
                foreach ($line_item_totals as $item_id => $total) {
                    $line_items[$item_id]['refund_total'] = wc_format_decimal($total);
                }
                foreach ($line_item_tax_totals as $item_id => $tax_totals) {
                    $line_items[$item_id]['refund_tax'] = array_filter(array_map('wc_format_decimal', $tax_totals));
                }
                // Create the refund object.
                $refund = wc_create_refund(array(
                    'amount' => $refund_amount,
                    'reason' => $refund_reason,
                    'order_id' => $order_id,
                    'line_items' => $line_items,
                    'refund_payment' => $api_refund,
                    'restock_items' => $restock_refunded_items,
                ));
                if (!is_wp_error($refund)) {
                    $wallet_credit = woo_wallet()->wallet->credit($order->get_customer_id(), $refund_amount, __('Refund to wallet for order ' . $order->get_order_number(), 'woo-wallet'), $approver);
                    if (!$wallet_credit) {
                        throw new Exception(__('Refund not credited to customer', 'woo-wallet'));
                    }
                }

                if (is_wp_error($refund)) {
                    throw new Exception($refund->get_error_message());
                }

                if (did_action('woocommerce_order_fully_refunded')) {
                    $response_data['status'] = 'fully_refunded';
                }

                wp_send_json_success($response_data);
            } catch (Exception $ex) {
                if ($refund && is_a($refund, 'WC_Order_Refund')) {
                    wp_delete_post($refund->get_id(), true);
                }
                wp_send_json_error(array('error' => $ex->getMessage()));
            }
        }
        /**
         * Mark wallet rated.
         */
        public function woocommerce_wallet_rated() {
            update_option('woocommerce_wallet_admin_footer_text_rated', true);
            die;
        }
        /**
         * Search users
         */
        public function woo_wallet_user_search() {
            $return = array();
            
            if (isset($_REQUEST['site_id'])) {
                $id = absint($_REQUEST['site_id']);
            } else {
                $id = get_current_blog_id();
            }

            $users = get_users(array(
                'blog_id' => $id,
                'search' => '*' . $_REQUEST['term'] . '*',
                'exclude' => array(get_current_user_id()),
                'search_columns' => array('user_login', 'user_nicename', 'user_email'),
                    ));

            foreach ($users as $user) {
                $return[] = array(
                    /* translators: 1: user_login, 2: user_email */
                    'label' => sprintf(_x('%1$s (%2$s)', 'user autocomplete result', 'woo-wallet'), $user->user_login, $user->user_email),
                    'value' => $user->ID,
                );
            }

            wp_send_json($return);
        }

    }

}
new Woo_Wallet_Ajax();
