<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Wallet actions.
 *
 * @author subrata
 */
class WOO_Wallet_Actions {

    /** @var array Array of action classes. */
    public $actions;

    /**
     * @var WOO_Wallet_Actions The single instance of the class
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Main WOO_Wallet_Actions Instance.
     *
     * Ensures only one instance of WOO_Wallet_Actions is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @return WOO_Wallet_Actions Main instance
     */
    public static function instance() {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Class Constructor
     */
    public function __construct() {
        $this->load_actions();
        $this->init();
    }

    public function init() {
        $load_actions = apply_filters('woo_wallet_actions', array(
            'Action_New_Registration',
            'Action_Product_Review',
            'Action_Daily_Visits',
//            'Action_Referrals'
        ));
        foreach ($load_actions as $action) {
            $load_action = is_string($action) ? new $action() : $action;
            $this->actions[$load_action->id] = $load_action;
        }
    }

    public function load_actions() {
        require_once(WOO_WALLET_ABSPATH . 'includes/actions/class-woo-wallet-action-new-registration.php');
        require_once(WOO_WALLET_ABSPATH . 'includes/actions/class-woo-wallet-action-product-review.php');
        require_once(WOO_WALLET_ABSPATH . 'includes/actions/class-woo-wallet-action-daily-visits.php');
        require_once(WOO_WALLET_ABSPATH . 'includes/actions/class-woo-wallet-action-referrals.php');
        do_action('woo_wallet_load_actions');
    }

    public function get_available_actions() {
        $actions = array();
        foreach ($this->actions as $action) {
            if ($action->is_enabled()) {
                $actions[] = $action;
            }
        }
        return $actions;
    }

}

WOO_Wallet_Actions::instance();
