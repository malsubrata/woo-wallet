<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Action_Daily_Visits extends WooWalletAction {

    public function __construct() {
        $this->id = 'daily_visits';
        $this->action_title = __('Daily visits', 'woo-wallet');
        $this->description = __('Set credit for daily visits', 'woo-wallet');
        $this->init_form_fields();
        $this->init_settings();
        // Actions.
        add_action('wp', array($this, 'woo_wallet_site_visit_credit'), 100);
        
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-wallet'),
                'type' => 'checkbox',
                'label' => __('Enable credit for daily visits.', 'woo-wallet'),
                'default' => 'no',
            ),
            'amount' => array(
                'title' => __('Amount', 'woo-wallet'),
                'type' => 'price',
                'description' => __('Enter amount which will be credited to the user wallet for daily visits.', 'woo-wallet'),
                'default' => '10',
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', 'woo-wallet'),
                'type' => 'textarea',
                'description' => __('Wallet transaction description that will display as transaction note.', 'woo-wallet'),
                'default' => __('Balance credited visiting site.', 'woo-wallet'),
                'desc_tip' => true,
            )
        );
    }
    
    public function woo_wallet_site_visit_credit() {
        if (!$this->is_enabled() || !is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        if (isset($_COOKIE['woo_wallet_site_visit_' . $user_id])) {
            return;
        }
        
        if ( ! headers_sent() && did_action( 'wp_loaded' ) ) {
            wc_setcookie('woo_wallet_site_visit_' . $user_id, 1, time() + DAY_IN_SECONDS);
        }
        
        if ($this->settings['amount'] && apply_filters('woo_wallet_site_visit_credit', true)) {
            woo_wallet()->wallet->credit($user_id, $this->settings['amount'], sanitize_textarea_field($this->settings['description']));
        }
    }

}

