<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Woo_Wallet_Wallet')) {

    class Woo_Wallet_Wallet {
        /* WordPress user id */

        public $user_id = null;
        /* user wallet balance */
        public $wallet_balance = 0;
        /* Wallet balance meta key */
        public $meta_key = '_woo_wallet_balance';

        /*
         * Class constructor
         */

        public function __construct() {
            $this->user_id = get_current_user_id();
        }

        /**
         * setter method
         * @param int $user_id
         */
        private function set_user_id($user_id = '') {
            $this->user_id = $user_id ? $user_id : $this->user_id;
        }

        /**
         * Get user wallet balance or display
         * @global object $wpdb
         * @param int $user_id
         * @param string $context
         * @return mixed
         */
        public function get_wallet_balance($user_id = '', $context = 'view') {
            global $wpdb;
            $this->set_user_id($user_id);
            $this->wallet_balance = 0;
            $resualt = $wpdb->get_row("SELECT balance, currency FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = {$this->user_id} ORDER BY transaction_id DESC");
            if ($resualt) {
                $this->wallet_balance = number_format(apply_filters('woo_wallet_amount', apply_filters('woo_wallet_current_balance', $resualt->balance, $this->user_id), $resualt->currency), 2, '.', '');
            }
            return 'view' === $context ? wc_price($this->wallet_balance) : number_format($this->wallet_balance, 2, '.', '');
        }

        /**
         * Create wallet payment credit transaction
         * @param int $user_id
         * @param float $amount
         * @param string $details
         * @return int transaction id
         */
        public function credit($user_id = '', $amount = 0, $details = '') {
            $this->set_user_id($user_id);
            return $this->recode_transaction($amount, 'credit', $details);
        }

        /**
         * Create wallet payment debit transaction
         * @param int $user_id
         * @param float $amount
         * @param string $details
         * @return int transaction id
         */
        public function debit($user_id = '', $amount = 0, $details = '') {
            $this->set_user_id($user_id);
            return $this->recode_transaction($amount, 'debit', $details);
        }

        /**
         * Credit wallet balance through order payment
         * @param int $order_id
         * @return void
         */
        public function wallet_credit_purchase($order_id) {
            $wallet_product = get_wallet_rechargeable_product();
            $charge_amount = 0;
            if (get_post_meta($order_id, '_wc_wallet_purchase_credited', true) || !$wallet_product) {
                return;
            }
            $order = wc_get_order($order_id);
            if (!is_wallet_rechargeable_order($order)) {
                return;
            }
            $recharge_amount = $order->get_total('');
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_gateway_charge', '_wallet_settings_credit', 'off')) {
                $charge_amount = woo_wallet()->settings_api->get_option($order->get_payment_method(), '_wallet_settings_credit', 0);
                if ('percent' === woo_wallet()->settings_api->get_option('gateway_charge_type', '_wallet_settings_credit', 'percent')) {
                    $recharge_amount -= $recharge_amount * ($charge_amount / 100);
                } else {
                    $recharge_amount -= $charge_amount;
                }
                update_post_meta($order_id, '_wc_wallet_purchase_gateway_charge', $charge_amount);
            }
            $recharge_amount = apply_filters('woo_wallet_credit_purchase_amount', $recharge_amount, $order_id);
            $transaction_id = $this->credit($order->get_customer_id(), $recharge_amount, __('Wallet credit through purchase #' . $order->get_id(), 'woo-wallet'));
            if ($transaction_id) {
                update_post_meta($order_id, '_wc_wallet_purchase_credited', true);
                update_post_meta($order_id, '_wallet_payment_transaction_id', $transaction_id);
                update_wallet_transaction_meta($transaction_id, '_wc_wallet_purchase_gateway_charge', $charge_amount);
            }
        }

        public function wallet_cashback($order_id) {
            $order = wc_get_order($order_id);
            if (get_post_meta($order_id, '_wc_wallet_cashback_credited', true)) {
                return;
            }
            /* General Cashback */
            if (get_wallet_cashback_amount($order->get_id())) {
                $transaction_id = $this->credit($order->get_customer_id(), get_wallet_cashback_amount($order->get_id()), __('Wallet credit through cashback #' . $order->get_id(), 'woo-wallet'));
                if ($transaction_id) {
                    update_wallet_transaction_meta($transaction_id, '_type', 'cashback');
                }
            }
            /* Coupon Cashback */
            if (apply_filters('woo_wallet_coupon_cashback_amount', get_post_meta($order->get_id(), '_coupon_cashback_amount', true), $order)) {
                $transaction_id = $this->credit($order->get_customer_id(), get_post_meta($order->get_id(), '_coupon_cashback_amount', true), __('Wallet credit through cashback by applying coupon', 'woo-wallet'));
                if ($transaction_id) {
                    update_wallet_transaction_meta($transaction_id, '_type', 'cashback');
                }
            }
            update_post_meta($order_id, '_wc_wallet_cashback_credited', true);
        }

        public function wallet_partial_payment($order_id) {
            $order = wc_get_order($order_id);
            if (get_post_meta($order_id, '_via_wallet_payment', true) && !get_post_meta($order_id, '_partial_pay_through_wallet_compleate', true)) {
                $transaction_id = $this->debit($order->get_customer_id(), get_post_meta($order_id, '_via_wallet_payment', true), __('For order payment #' . $order->get_id(), 'woo-wallet'));
                $order->add_order_note(sprintf('%s paid through wallet', wc_price(get_post_meta($order_id, '_via_wallet_payment', true))));
                $order->set_total(floatval(get_post_meta($order_id, '_original_order_amount', true)));
                $order->save();
                update_wallet_transaction_meta($transaction_id, '_partial_payment', true);
                update_post_meta($order_id, '_partial_pay_through_wallet_compleate', true);
            }
        }

        /**
         * Record wallet transactions
         * @global object $wpdb
         * @param int $amount
         * @param string $type
         * @param string $details
         * @return boolean | transaction id
         */
        private function recode_transaction($amount, $type, $details) {
            global $wpdb;
            if ($amount < 0) {
                $amount = 0;
            }
            $balance = $this->get_wallet_balance($this->user_id, '');
            if ($type == 'debit' && apply_filters('woo_wallet_disallow_negative_transaction', ($balance <= 0 || $amount > $balance), $amount, $balance)) {
                return false;
            }
            if ($type == 'credit') {
                $balance += $amount;
            } else if ($type == 'debit') {
                $balance -= $amount;
            }
            if ($wpdb->insert("{$wpdb->base_prefix}woo_wallet_transactions", array('blog_id' => $GLOBALS['blog_id'], 'user_id' => $this->user_id, 'type' => $type, 'amount' => $amount, 'balance' => $balance, 'currency' => get_woocommerce_currency(), 'details' => $details), array('%d', '%d', '%s', '%f', '%f', '%s', '%s'))) {
                $email_admin = WC()->mailer()->emails['Woo_Wallet_Email_New_Transaction'];
                $email_admin->trigger($this->user_id, $amount, $type, $details);
                $transaction_id = $wpdb->insert_id;
                do_action('woo_wallet_transaction_recorded', $transaction_id, $amount, $type);
                return $transaction_id;
            }
            return false;
        }

    }

}
