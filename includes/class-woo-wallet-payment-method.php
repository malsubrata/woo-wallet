<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Woo_Gateway_Wallet_payment extends WC_Payment_Gateway {
    /**
     * Class constructor
     */
    public function __construct() {
        $this->setup_properties();
        // Load the settings
        $this->init_form_fields();
        $this->init_settings();
        // Get settings
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    /**
     * Setup general properties for the gateway.
     */
    protected function setup_properties() {
        $this->id = 'wallet';
        $this->method_title = __('Wallet payments', 'woo-wallet');
        $this->method_description = __('Have your customers pay with wallet.', 'woo-wallet');
        $this->has_fields = false;
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-wallet'),
                'label' => __('Enable wallet payments', 'woo-wallet'),
                'type' => 'checkbox',
                'description' => '',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'woo-wallet'),
                'type' => 'text',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woo-wallet'),
                'default' => __('Wallet payment', 'woo-wallet'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'woo-wallet'),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your website.', 'woo-wallet'),
                'default' => __('Pay with wallet.', 'woo-wallet'),
                'desc_tip' => true,
            ),
            'instructions' => array(
                'title' => __('Instructions', 'woo-wallet'),
                'type' => 'textarea',
                'description' => __('Instructions that will be added to the thank you page.', 'woo-wallet'),
                'default' => __('Pay with wallet.', 'woo-wallet'),
                'desc_tip' => true,
            )
        );
    }
    /**
     * Is gateway available
     * @return boolean
     */
    public function is_available() {
        return is_full_payment_through_wallet();
    }
    /**
     * Process wallet payment
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $wallet_response = woo_wallet()->wallet->debit(get_current_user_id(), $order->get_total(''), __('For order payment #' . $order->get_id()));
        // Mark as processing or on-hold
        $order->update_status(apply_filters('woocommerce_wallet_process_payment_order_status', !$wallet_response ? 'on-hold' : 'processing', $order), __('Payment via wallet.', 'woo-wallet'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result' => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

}
