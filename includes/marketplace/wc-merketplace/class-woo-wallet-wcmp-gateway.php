<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WCMp_Gateway_Wallet' ) && class_exists( 'WCMp_Payment_Gateway' ) ) {

    class WCMp_Gateway_Wallet extends WCMp_Payment_Gateway {

        public $id;
        public $message = array();

        public function __construct() {
            $this->id              = 'woo_wallet';
            $this->payment_gateway = $this->id;
            $this->enabled         = get_wcmp_vendor_settings( 'payment_method_woo_wallet', 'payment' );
        }

        public function process_payment( $vendor, $commissions = array(), $transaction_mode = 'auto' ) {
            $this->vendor           = $vendor;
            $this->commissions      = $commissions;
            $this->currency         = get_woocommerce_currency();
            $this->transaction_mode = $transaction_mode;
            if ( $this->validate_request() ) {
                if ( $this->process_wallet_payment() ) {
                    $this->record_transaction();
                    if ( $this->transaction_id ) {
                        return array( 'message' => __( 'New transaction has been initiated', 'woo-wallet' ), 'type' => 'success', 'transaction_id' => $this->transaction_id );
                    }
                } else {
                    return $this->message;
                }
            } else {
                return $this->message;
            }
        }

        public function validate_request() {
            global $WCMp;
            if ( $this->enabled != 'Enable' ) {
                $this->message[] = array( 'message' => __( 'Invalid payment method', 'woo-wallet' ), 'type' => 'error' );
                return false;
            }
            if ( $this->transaction_mode != 'admin' ) {
                /* handle thesold time */
                $threshold_time = isset( $WCMp->vendor_caps->payment_cap['commission_threshold_time'] ) && ! empty( $WCMp->vendor_caps->payment_cap['commission_threshold_time'] ) ? $WCMp->vendor_caps->payment_cap['commission_threshold_time'] : 0;
                if ( $threshold_time > 0 ) {
                    foreach ( $this->commissions as $index => $commission) {
                        if (intval( (date( 'U' ) - get_the_date( 'U', $commission) ) / (3600 * 24) ) < $threshold_time) {
                            unset( $this->commissions[$index] );
                        }
                    }
                }
                /* handle thesold amount */
                $thesold_amount = isset( $WCMp->vendor_caps->payment_cap['commission_threshold'] ) && ! empty( $WCMp->vendor_caps->payment_cap['commission_threshold'] ) ? $WCMp->vendor_caps->payment_cap['commission_threshold'] : 0;
                if ( $this->get_transaction_total() > $thesold_amount ) {
                    return true;
                } else {
                    $this->message[] = array( 'message' => __( 'Minimum threshold amount to withdrawal commission is ' . $thesold_amount, 'woo-wallet' ), 'type' => 'error' );
                    return false;
                }
            }
            return parent::validate_request();
        }

        private function process_wallet_payment() {
            $amount_to_pay   = round( $this->get_transaction_total() - $this->transfer_charge( $this->transaction_mode) - $this->gateway_charge(), 2 );
            $for_commissions = implode( ',', $this->commissions );
            $transaction_id  = woo_wallet()->wallet->credit( $this->vendor->id, $amount_to_pay, __( 'Commission received for commission id ', 'woo-wallet' ). $for_commissions );
            if ( $transaction_id ) {
                update_wallet_transaction_meta( $transaction_id, '_type', 'vendor_commission', $this->vendor->id );
                return true;
            }
            return false;
        }

    }

}
