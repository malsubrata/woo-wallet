<?php

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Woo_Wallet_Wallet')) {

    class Woo_Wallet_Wallet {
        /* WordPress user id */

        public $user_id = 0;
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
            if ($this->user_id) {
                $resualt = $wpdb->get_row("SELECT balance, currency FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = {$this->user_id} ORDER BY transaction_id DESC");
                if ($resualt) {
                    $this->wallet_balance = number_format(apply_filters('woo_wallet_amount', apply_filters('woo_wallet_current_balance', $resualt->balance, $this->user_id), $resualt->currency), 2, '.', '');
                }
            }
            return 'view' === $context ? wc_price($this->wallet_balance) : number_format($this->wallet_balance, 2, '.', '');
        }


        /**
         * Create wallet payment credit transaction
         * @param int $user_id
         * @param float $amount
         * @param string $details
         * @param int $approver
         * @return int transaction id
         */
        public function credit($user_id = '', $amount = 0, $details = '', $approver = 0) {
            $this->set_user_id($user_id);
            return $this->recode_transaction($amount, 'credit', $details, $approver);
        }

        /**
         * Create wallet payment debit transaction
         * @param int $user_id
         * @param float $amount
         * @param string $details
         * @param int $approver
         * @return int transaction id
         */
        public function debit($user_id = '', $amount = 0, $details = '', $approver = 0) {
            $this->set_user_id($user_id);
            return $this->recode_transaction($amount, 'debit', $details, $approver);
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
            $recharge_amount = apply_filters('woo_wallet_credit_purchase_amount', $order->get_subtotal('edit'), $order_id);
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_gateway_charge', '_wallet_settings_credit', 'off')) {
                $charge_amount = woo_wallet()->settings_api->get_option($order->get_payment_method(), '_wallet_settings_credit', 0);
                if ('percent' === woo_wallet()->settings_api->get_option('gateway_charge_type', '_wallet_settings_credit', 'percent')) {
                    $recharge_amount -= $recharge_amount * ($charge_amount / 100);
                } else {
                    $recharge_amount -= $charge_amount;
                }
                update_post_meta($order_id, '_wc_wallet_purchase_gateway_charge', $charge_amount);
            }
            $transaction_id = $this->credit($order->get_customer_id(), $recharge_amount, __('Added funds to wallet from order ', 'woo-wallet') . $order->get_order_number());
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
                $transaction_id = $this->credit($order->get_customer_id(), get_wallet_cashback_amount($order->get_id()), __('Wallet credit through cashback #', 'woo-wallet') . $order->get_id());
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
                $transaction_id = $this->debit($order->get_customer_id(), get_post_meta($order_id, '_via_wallet_payment', true), __('For order #', 'woo-wallet') . $order->get_order_number());
                $order->add_order_note(sprintf('%s paid through wallet', wc_price(get_post_meta($order_id, '_via_wallet_payment', true))));
                $order->set_total(floatval(get_post_meta($order_id, '_original_order_amount', true)));
                $order->save();
                update_wallet_transaction_meta($transaction_id, '_partial_payment', true);
                update_post_meta($order_id, '_partial_pay_through_wallet_compleate', true);
            }
        }


	/**
	 * Get current credit limit
	 * @param int $user_id
	 * @return mixed
	 */

	public function get_current_credit_limit( $user_id = '' ) {
		return $this->get_wallet_credit_details( $user_id, 'current' );
	}

        /**
         * Get user wallet credit details
         * @global object $wpdb
         * @param int $user_id
         * @param string $context
         * @return mixed
         */
        public function get_wallet_credit_details( $user_id = '', $context = 'current' ) {
		global $wpdb;
		$this->set_user_id($user_id,1);
		$this->credit_limit = number_format($results[0]->amount, 2, '.', '');
		if ($this->user_id) {
			$results = $wpdb->get_results("SELECT * FROM {$wpdb->base_prefix}woo_wallet_credit_limits WHERE user_id = {$this->user_id} ORDER BY crlim_id DESC");
		}
		if ($results) {
			$this->credit_limit = number_format($results[0]->amount, 2, '.', '');
			if ( $context === 'all' ) {
				return $results;
			} 
		}
		if ( $context === 'view' ) {
			return wc_price($this->credit_limit*-1);
		} else {
			return $this->credit_limit;
		}
        }

	/**
	* Record wallet transactions
	* @global object $wpdb
	* @param int $amount
	* @param string $type
	* @param string $details
	* @param string $approver
	* @return boolean | transaction id
	**/
	private function recode_transaction($amount, $type, $details, $approver = 0) {
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
		if ($wpdb->insert("{$wpdb->base_prefix}woo_wallet_transactions", array('blog_id' => $GLOBALS['blog_id'], 'user_id' => $this->user_id, 'type' => $type, 'amount' => $amount, 'balance' => $balance, 'currency' => get_woocommerce_currency(), 'details' => $details, 'approver_id' => $approver), array('%d', '%d', '%s', '%f', '%f', '%s', '%s', '%d'))) {
			$email_admin = WC()->mailer()->emails['Woo_Wallet_Email_New_Transaction'];
			$email_admin->trigger($this->user_id, $amount, $type, $details);
			$transaction_id = $wpdb->insert_id;
			do_action('woo_wallet_transaction_recorded', $transaction_id, $amount, $type);
			return $transaction_id;
		}
		return false;
	}

        /**
         * Record new credit limit
         * @global object $wpdb
         * @param int $user_id
         * @param int $amount
         * @param string $type
         * @param string $description
         * @return boolean | transaction id
         */
        public function record_credit_limit($user_id = 0, $amount = 0, $type = '', $description = '') {
		global $wpdb;

		if ( $user_id == 0 || $type == '' ) {
			return false;
		}

		$this->set_user_id($user_id);

		if ( $wpdb->insert( "{$wpdb->base_prefix}woo_wallet_credit_limits",
			array(	'user_id' => $this->user_id,
				'type' => $type,
				'amount' => $amount,
				'description' => $description,
				'approver_id' => get_current_user_id(),
				'date' => current_time('mysql',false)
			), 
			array( '%d', '%s', '%f', '%s', '%d', '%s')) ) {

			$crlim_id = $wpdb->insert_id;
			return $crlim_id;
		}
		return false;
	}



    }

}
