<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Action_Referrals extends WooWalletAction {

    /**
     * Referral base.
     * @var string 
     */
    public $referral_handel = null;

    public function __construct() {
        $this->id = 'referrals';
        $this->action_title = __('Referrals', 'woo-wallet');
        $this->description = __('Set credit ruls for referrals', 'woo-wallet');
        $this->init_form_fields();
        $this->init_settings();
        // Actions.
        add_action('wp_loaded', array($this, 'load_woo_wallet_referral'));
    }

    /**
     * Initialise Gateway Settings Form Fields.
     */
    public function init_form_fields() {

        $this->form_fields = apply_filters('woo_wallet_action_referrals_form_fields', array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woo-wallet'),
                'type' => 'checkbox',
                'label' => __('Enable credit for referrals.', 'woo-wallet'),
                'default' => 'no',
            ),
            array(
                'title' => __('Referring Visitors', 'woo-wallet'),
                'type' => 'title',
                'desc' => '',
                'id' => 'referring_visitors',
            ),
            'referring_visitors_amount' => array(
                'title' => __('Amount', 'woo-wallet'),
                'type' => 'price',
                'description' => __('Enter amount which will be credited to the user wallet for daily visits.', 'woo-wallet'),
                'default' => '10',
                'desc_tip' => true
            ),
            'referring_visitors_limit_duration' => array(
                'title' => __('Limit', 'woo-wallet'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'css' => 'min-width: 350px;',
                'options' => array('0' => __('No Limit', 'woo-wallet'), 'day' => __('Per Day', 'woo-wallet'), 'week' => __('Per Week', 'woo-wallet'), 'month' => __('Per Month', 'woo-wallet'))
            ),
            'referring_visitors_limit' => array(
                'type' => 'number',
                'default' => 0,
            ),
            'referring_visitors_description' => array(
                'title' => __('Description', 'woo-wallet'),
                'type' => 'textarea',
                'description' => __('Wallet transaction description that will display as transaction note.', 'woo-wallet'),
                'default' => __('Balance credited for referring a visitor', 'woo-wallet'),
                'desc_tip' => true,
            ),
            array(
                'title' => __('Referring Signups', 'woo-wallet'),
                'type' => 'title',
                'desc' => '',
                'id' => 'referring_signups',
            ),
            'referring_signups_amount' => array(
                'title' => __('Amount', 'woo-wallet'),
                'type' => 'price',
                'description' => __('Enter amount which will be credited to the user wallet for referring a user.', 'woo-wallet'),
                'default' => '10',
                'desc_tip' => true
            ),
            'referral_order_amount' => array(
                'title' => __('Minimum Order Amount', 'woo-wallet'),
                'type' => 'number',
                'description' => __('Enter the minimum order amount for referral credit.', 'woo-wallet'),
                'default' => 0,
                'desc_tip' => true,
                'custom_attributes' => array('min' => 0)
            ),
            'referring_signups_limit_duration' => array(
                'title' => __('Limit', 'woo-wallet'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'css' => 'min-width: 350px;',
                'options' => array('0' => __('No Limit', 'woo-wallet'), 'day' => __('Per Day', 'woo-wallet'), 'week' => __('Per Week', 'woo-wallet'), 'month' => __('Per Month', 'woo-wallet'))
            ),
            'referring_signups_limit' => array(
                'type' => 'number',
                'default' => 0,
            ),
            'referring_signups_description' => array(
                'title' => __('Description', 'woo-wallet'),
                'type' => 'textarea',
                'description' => __('Wallet transaction description that will display as transaction note.', 'woo-wallet'),
                'default' => __('Balance credited for referring a new member', 'woo-wallet'),
                'desc_tip' => true,
            ),
            array(
                'title' => __('Referral Links', 'woo-wallet'),
                'type' => 'title',
                'desc' => '',
                'id' => 'referring_links',
            ),
            'referal_link' => array(
                'title' => __('Referral Format', 'woo-wallet'),
                'type' => 'select',
                'class' => 'wc-enhanced-select',
                'css' => 'min-width: 350px;',
                'options' => array('id' => __('Numeric referral ID', 'woo-wallet'), 'username' => __('Usernames as referral ID', 'woo-wallet'))
            )
        ));
    }

    public function load_woo_wallet_referral() {
        if ($this->is_enabled()) {
            $this->referral_handel = apply_filters('woo_wallet_referral_handel', 'wwref');
            add_filter('woo_wallet_nav_menu_items', array($this, 'add_referral_nav_menu'), 10, 2);
            add_action('woo_wallet_menu_content', array($this, 'referral_content'));
            add_filter('woo_wallet_endpoint_actions', array($this, 'woo_wallet_endpoint_actions'));
            $this->init_referrals();
            add_action('wp', array($this, 'init_referral_visit'), 105);
            add_action('user_register', array($this, 'woo_wallet_referring_signup'));
            add_action('woocommerce_order_status_changed', array($this, 'woo_wallet_credit_referring_signup'), 100);
        }
    }

    public function add_referral_nav_menu($nav_menu, $is_rendred_from_myaccount) {
        $nav_menu['referrals'] = array(
            'title' => apply_filters('woo_wallet_account_referrals_menu_title', __('Referrals', 'woo-wallet')),
            'url' => $is_rendred_from_myaccount ? esc_url(wc_get_endpoint_url(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'), 'referrals', wc_get_page_permalink('myaccount'))) : add_query_arg('wallet_action', 'referrals'),
            'icon' => 'dashicons dashicons-groups'
        );
        return $nav_menu;
    }

    public function woo_wallet_endpoint_actions($actions) {
        $actions[] = 'referrals';
        return $actions;
    }

    public function referral_content() {
        global $wp;
        if (apply_filters('woo_wallet_is_enable_referrals', true) && ( ( isset($wp->query_vars['woo-wallet']) && 'referrals' === $wp->query_vars['woo-wallet'] ) || ( isset($_GET['wallet_action']) && 'referrals' === $_GET['wallet_action'] ) )) {
            woo_wallet()->get_template('woo-wallet-referrals.php', array('settings' => $this->settings, 'referral' => $this));
        }
    }

    public function init_referrals() {
        if (isset($_GET[$this->referral_handel]) && !empty($_GET[$this->referral_handel])) {
            if (!headers_sent() && did_action('wp_loaded')) {
                wc_setcookie('woo_wallet_referral', $_GET[$this->referral_handel], time() + DAY_IN_SECONDS);
            }
        }
    }

    public function get_referral_user() {
        if (isset($_COOKIE['woo_wallet_referral'])) {
            $woo_wallet_referral = $_COOKIE['woo_wallet_referral'];
            if ('id' === $this->settings['referal_link']) {
                $user = get_user_by('ID', $woo_wallet_referral);
            } else {
                $user = get_user_by('login', $woo_wallet_referral);
            }
            if ( !$user || $user->ID === get_current_user_id()) {
                return false;
            }
            return apply_filters('woo_wallet_referral_user', $user, $this);
        }
        return false;
    }

    public function init_referral_visit() {
        $referral_user = $this->get_referral_user();
        if (!$referral_user) {
            return;
        }
        $referral_visit_amount = apply_filters('woo_wallet_referring_visitor_amount', $this->settings['referring_visitors_amount'], $referral_user->ID);
        if ($referral_visit_amount && $this->get_referral_user()) {
            if (apply_filters('woo_wallet_restrict_referral_visit_by_cookie', isset($_COOKIE['woo_wallet_referral_visit_credited_' . $referral_user->ID]), $this)) {
                return;
            }
            $limit = $this->settings['referring_visitors_limit_duration'];
            $referral_visitor_count = get_user_meta($referral_user->ID, '_woo_wallet_referring_visitor', true) ? get_user_meta($referral_user->ID, '_woo_wallet_referring_visitor', true) : 0;
            $woo_wallet_referring_earning = get_user_meta($referral_user->ID, '_woo_wallet_referring_earning', true) ? get_user_meta($referral_user->ID, '_woo_wallet_referring_earning', true) : 0;
            if ($limit) {
                $woo_wallet_referral_visit_count = get_transient('woo_wallet_referral_visit_' . $referral_user->ID) ? get_transient('woo_wallet_referral_visit_' . $referral_user->ID) : 0;
                if ($woo_wallet_referral_visit_count < $this->settings['referring_visitors_limit']) {
                    if (!headers_sent() && did_action('wp_loaded')) {
                        $transiant_duration = DAY_IN_SECONDS;
                        if ('week' === $limit) {
                            $transiant_duration = WEEK_IN_SECONDS;
                        } else if ('month' === $limit) {
                            $transiant_duration = MONTH_IN_SECONDS;
                        }
                        set_transient('woo_wallet_referral_visit_' . $referral_user->ID, $woo_wallet_referral_visit_count + 1, $transiant_duration);
                        $transaction_id = woo_wallet()->wallet->credit($referral_user->ID, $referral_visit_amount, $this->settings['referring_visitors_description']);
                        update_user_meta($referral_user->ID, '_woo_wallet_referring_visitor', $referral_visitor_count + 1);
                        update_user_meta($referral_user->ID, '_woo_wallet_referring_earning', $woo_wallet_referring_earning + $referral_visit_amount);
                        do_action('woo_wallet_after_referral_visit', $transaction_id, $this);
                    }
                }
            } else {
                $transaction_id = woo_wallet()->wallet->credit($referral_user->ID, $referral_visit_amount, $this->settings['referring_visitors_description']);
                update_user_meta($referral_user->ID, '_woo_wallet_referring_visitor', $referral_visitor_count + 1);
                update_user_meta($referral_user->ID, '_woo_wallet_referring_earning', $woo_wallet_referring_earning + $referral_visit_amount);
                do_action('woo_wallet_after_referral_visit', $transaction_id, $this);
            }
            wc_setcookie('woo_wallet_referral_visit_credited_' . $referral_user->ID, true, time() + DAY_IN_SECONDS);
        }
    }

    public function woo_wallet_referring_signup($user_id) {
        $referral_user = $this->get_referral_user();
        if ($this->get_referral_user()) {
            $limit = $this->settings['referring_signups_limit_duration'];
            if ($limit) {
                $woo_wallet_referral_signup_count = get_transient('woo_wallet_referral_signup_' . $referral_user->ID) ? get_transient('woo_wallet_referral_signup_' . $referral_user->ID) : 0;
                if ($woo_wallet_referral_signup_count < $this->settings['referring_signups_limit']) {
                    if (!headers_sent() && did_action('wp_loaded')) {
                        $transiant_duration = DAY_IN_SECONDS;
                        if ('week' === $limit) {
                            $transiant_duration = WEEK_IN_SECONDS;
                        } else if ('month' === $limit) {
                            $transiant_duration = MONTH_IN_SECONDS;
                        }
                        set_transient('woo_wallet_referral_signup_' . $referral_user->ID, $woo_wallet_referral_signup_count + 1, $transiant_duration);
                        update_user_meta($user_id, '_referral_user_id', $referral_user->ID);
                    }
                }
            } else {
                update_user_meta($user_id, '_referral_user_id', $referral_user->ID);
            }
            $minimum_spent = isset($this->settings['referral_order_amount']) ? $this->settings['referral_order_amount'] : 0;
            if(!$minimum_spent){
                $this->credit_referring_signup($user_id);
            }
        }
    }

    public function woo_wallet_credit_referring_signup($order_id) {
        $order = wc_get_order($order_id);
        $customer_id = $order->get_customer_id();
        $referral_user_id = get_user_meta($customer_id, '_referral_user_id', true);
        if (!$referral_user_id || get_user_meta($customer_id, '_woo_wallet_referral_signup_credited', true)) {
            return;
        }
        $customer_total_spent = wc_get_customer_total_spent($customer_id);
        $minimum_spent = isset($this->settings['referral_order_amount']) ? $this->settings['referral_order_amount'] : 0;
        if ($order->is_paid() && $customer_total_spent >= $minimum_spent) {
            $this->credit_referring_signup($customer_id, $order_id);
        }
    }
    
    public function credit_referring_signup($customer_id, $order_id = 0) {
        $referral_user_id = get_user_meta($customer_id, '_referral_user_id', true);
        if (!$referral_user_id || get_user_meta($customer_id, '_woo_wallet_referral_signup_credited', true)) {
            return;
        }
        $referral_user = new WP_User($referral_user_id);
        $referral_signup_count = get_user_meta($referral_user->ID, '_woo_wallet_referring_signup', true) ? get_user_meta($referral_user->ID, '_woo_wallet_referring_signup', true) : 0;
        $woo_wallet_referring_earning = get_user_meta($referral_user->ID, '_woo_wallet_referring_earning', true) ? get_user_meta($referral_user->ID, '_woo_wallet_referring_earning', true) : 0;
        $referral_signup_amount = apply_filters('woo_wallet_referring_signup_amount', $this->settings['referring_signups_amount'], $referral_user->ID, $customer_id, $order_id);
        if($referral_signup_amount){
            $transaction_id = woo_wallet()->wallet->credit($referral_user->ID, $referral_signup_amount, $this->settings['referring_signups_description']);
            update_user_meta($referral_user->ID, '_woo_wallet_referring_signup', $referral_signup_count + 1);
            update_user_meta($referral_user->ID, '_woo_wallet_referring_earning', $woo_wallet_referring_earning + $referral_signup_amount);
            update_user_meta($customer_id, '_woo_wallet_referral_signup_credited', true);
            do_action('woo_wallet_after_referral_signup', $transaction_id, $customer_id, $this);
        }
    }

}
