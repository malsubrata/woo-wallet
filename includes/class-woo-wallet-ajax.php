<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_Ajax')) {

    class Woo_Wallet_Ajax {

        /**
         * The single instance of the class.
         *
         * @var Woo_Wallet_Ajax
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
            add_action('wp_ajax_woo_wallet_order_refund', array($this, 'woo_wallet_order_refund'));
            add_action('wp_ajax_woocommerce_wallet_rated', array($this, 'woocommerce_wallet_rated'));
            add_action('wp_ajax_woo-wallet-user-search', array($this, 'woo_wallet_user_search'));
            add_action('wp_ajax_woo_wallet_partial_payment_update_session', array($this, 'woo_wallet_partial_payment_update_session'));
            add_action('wp_ajax_woo_wallet_refund_partial_payment', array($this, 'woo_wallet_refund_partial_payment'));
            add_action('wp_ajax_woo-wallet-dismiss-promotional-notice', array($this, 'woo_wallet_dismiss_promotional_notice'));
            add_action('wp_ajax_draw_wallet_transaction_details_table', array($this, 'draw_wallet_transaction_details_table'));

            add_action('woocommerce_order_after_calculate_totals', array($this, 'recalculate_order_cashback_after_calculate_totals'), 10, 2);
            
            add_action('wp_ajax_terawallet_export_user_search', array($this, 'terawallet_export_user_search'));
            
            add_action('wp_ajax_terawallet_do_ajax_transaction_export', array($this, 'terawallet_do_ajax_transaction_export'));
            
            add_action('wp_ajax_lock_unlock_terawallet', array($this, 'lock_unlock_terawallet'));
        }
        
        public function lock_unlock_terawallet() {
            $user_id = $_POST['user_id'];
            $type = $_POST['type'];
            if('lock' === $type){
                update_user_meta($user_id, '_is_wallet_locked', true);
                wp_send_json_success(array('type' => 'unlock', 'text' => __('Unlock', 'woo-wallet')));
            } else{
                delete_user_meta($user_id, '_is_wallet_locked');
                wp_send_json_success(array('type' => 'lock', 'text' => __('Lock', 'woo-wallet')));
            }
        }
        /**
         * Generate export CSV file.
         */
        public function terawallet_do_ajax_transaction_export() {
            check_ajax_referer('terawallet-exporter-script', 'security');
            include_once WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php';
            $step = isset($_POST['step']) ? absint($_POST['step']) : 1; // WPCS: input var ok, sanitization ok.

            $exporter = new TeraWallet_CSV_Exporter();

            $exporter->set_step($step);

            if (!empty($_POST['selected_columns'])) { // WPCS: input var ok.
                $exporter->set_columns_to_export(wp_unslash($_POST['selected_columns'])); // WPCS: input var ok, sanitization ok.
            }

            if (!empty($_POST['selected_users'])) { // WPCS: input var ok.
                $exporter->set_users_to_export(wp_unslash($_POST['selected_users'])); // WPCS: input var ok, sanitization ok.
            }

            if (!empty($_POST['start_date'])) {
                $exporter->set_start_date(wp_unslash($_POST['start_date']));
            }

            if (!empty($_POST['end_date'])) {
                $exporter->set_end_date(wp_unslash($_POST['end_date']));
            }

            if (!empty($_POST['filename'])) { // WPCS: input var ok.
                $exporter->set_filename(wp_unslash($_POST['filename'])); // WPCS: input var ok, sanitization ok.
            }
            $exporter->write_to_csv();
            $query_args = array(
                'nonce' => wp_create_nonce('terawallet-transaction-csv'),
                'action' => 'download_export_csv',
                'filename' => $exporter->get_filename(),
            );
            if ($exporter->get_percent_complete() >= 100) {
                wp_send_json_success(
                        array(
                            'step' => 'done',
                            'percentage' => 100,
                            'url' => add_query_arg($query_args, admin_url('admin.php?page=terawallet-exporter')),
                        )
                );
            } else {
                wp_send_json_success(
                        array(
                            'step' => ++$step,
                            'percentage' => $exporter->get_percent_complete(),
                            'columns' => '',
                        )
                );
            }
        }

        /**
         * Search users for export transactions.
         */
        public function terawallet_export_user_search() {
            $return = array();
            if (isset($_REQUEST['site_id'])) {
                $id = absint($_REQUEST['site_id']);
            } else {
                $id = get_current_blog_id();
            }

            $users = get_users(array(
                'blog_id' => $id,
                'search' => '*' . $_REQUEST['term'] . '*',
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

        /**
         * Recalculate and send order cashback.
         * @param Bool $and_taxes Description
         * @param WC_Order $order
         */
        public function recalculate_order_cashback_after_calculate_totals($and_taxes, $order) {
            $cashback_amount = woo_wallet()->cashback->calculate_cashback(false, $order->get_id(), true);
            if ($transaction_id = get_post_meta($order->get_id(), '_general_cashback_transaction_id', true)) {
                update_wallet_transaction($transaction_id, $order->get_customer_id(), array('amount' => $cashback_amount), array('%f'));
            }
        }

        /**
         * Wallet partial payment refund.
         */
        public function woo_wallet_refund_partial_payment() {
            if (!current_user_can('edit_shop_orders')) {
                wp_die(-1);
            }
            $response = array('success' => false);
            $order_id = absint(filter_input(INPUT_POST, 'order_id'));
            $order = wc_get_order($order_id);
            $partial_payment_amount = get_order_partial_payment_amount($order_id);
            $transaction_id = woo_wallet()->wallet->credit($order->get_customer_id(), $partial_payment_amount, __('Wallet refund #', 'woo-wallet') . $order->get_order_number());
            if ($transaction_id) {
                $response['success'] = true;
                $order->add_order_note(sprintf(__('%s refunded to customer wallet', 'woo-wallet'), wc_price($partial_payment_amount, woo_wallet_wc_price_args($order->get_customer_id()))));
                update_post_meta($order_id, '_woo_wallet_partial_payment_refunded', true);
                update_post_meta($order_id, '_partial_payment_refund_id', $transaction_id);
                add_action('woo_wallet_partial_order_refunded', $order_id, $transaction_id);
            }
            wp_send_json($response);
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
                    $transaction_id = woo_wallet()->wallet->credit($order->get_customer_id(), $refund_amount, $refund_reason);
                    if (!$transaction_id) {
                        throw new Exception(__('Refund not credited to customer', 'woo-wallet'));
                    } else {
                        do_action('woo_wallet_order_refunded', $order, $refund, $transaction_id);
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
            if (apply_filters('woo_wallet_user_search_exact_match', true)) {
                $user = get_user_by(apply_filters('woo_wallet_user_search_by', 'email'), $_REQUEST['term']);
                if ($user && wp_get_current_user()->user_email != $user->user_email) {
                    $return[] = array(
                        /* translators: 1: user_login, 2: user_email */
                        'label' => sprintf(_x('%1$s (%2$s)', 'user autocomplete result', 'woo-wallet'), $user->user_login, $user->user_email),
                        'value' => $user->ID,
                    );
                }
            } else {
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
            }
            wp_send_json($return);
        }

        public function woo_wallet_partial_payment_update_session() {
            if (isset($_POST['checked']) && $_POST['checked'] == 'true') {
                update_wallet_partial_payment_session(true);
            } else {
                update_wallet_partial_payment_session();
            }
            wp_die();
        }

        public function woo_wallet_dismiss_promotional_notice() {
            $post_data = wp_unslash($_POST);
            if (!current_user_can('manage_options')) {
                wp_send_json_error(__('You have no permission to do that', 'woo-wallet'));
            }

            if (!wp_verify_nonce($post_data['nonce'], 'woo_wallet_admin')) {
                wp_send_json_error(__('Invalid nonce', 'woo-wallet'));
            }
            update_option('_woo_wallet_promotion_dismissed', true);
            wp_send_json_success();
        }

        /**
         * Send wallet transaction AJAX response.
         */
        public function draw_wallet_transaction_details_table() {
            check_ajax_referer('woo-wallet-transactions', 'security');
            $start = isset($_POST['start']) ? $_POST['start'] : 0;
            $length = isset($_POST['length']) ? $_POST['length'] : 10;
            $search = $_POST['search'];
            $args = array(
                'limit' => "$start, $length"
            );
            if ($search['value'] && !empty($search['value'])) {
                $args['where'] = array(
                    array(
                        'key' => 'date',
                        'value' => $search['value'] . '%',
                        'operator' => 'LIKE'
                    )
                );
            }
            $transactions = get_wallet_transactions($args);
            unset($args['limit']);
            $recordsTotal = get_wallet_transactions_count(get_current_user_id());

            $response = array(
                'draw' => $_POST['draw'],
                'recordsTotal' => $recordsTotal,
                'recordsFiltered' => count(get_wallet_transactions($args)),
                'data' => array()
            );
            if ($transactions) {
                foreach ($transactions as $transaction) {
                    $response['data'][] = apply_filters('woo_wallet_transactons_datatable_row_data', array(
                        'id' => $transaction->transaction_id,
                        'credit' => $transaction->type === 'credit' ? wc_price(apply_filters('woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id), woo_wallet_wc_price_args($transaction->user_id)) : ' - ',
                        'debit' => $transaction->type === 'debit' ? wc_price(apply_filters('woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id), woo_wallet_wc_price_args($transaction->user_id)) : ' - ',
                        'details' => $transaction->details,
                        'date' => wc_string_to_datetime($transaction->date)->date_i18n(wc_date_format())
                            ), $transaction);
                }
            }
            wp_send_json($response);
        }

    }

}
Woo_Wallet_Ajax::instance();
