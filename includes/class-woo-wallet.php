<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

final class WooWallet {

    /**
     * The single instance of the class.
     *
     * @var WooWallet
     * @since 1.0.0
     */
    protected static $_instance = null;

    /**
     * Setting API instance
     * @var Woo_Wallet_Settings_API 
     */
    public $settings_api = null;

    /**
     * wallet instance
     * @var Woo_Wallet_Wallet 
     */
    public $wallet = null;

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
        if (Woo_Wallet_Dependencies::is_woocommerce_active()) {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
            do_action('woo_wallet_loaded');
        } else {
            add_action('admin_notices', array($this, 'admin_notices'), 15);
        }
    }

    /**
     * Constants define
     */
    private function define_constants() {
        $this->define('WOO_WALLET_ABSPATH', dirname(WOO_WALLET_PLUGIN_FILE) . '/');
        $this->define('WOO_WALLET_PLUGIN_FILE', plugin_basename(WOO_WALLET_PLUGIN_FILE));
        $this->define('WOO_WALLET_ICON', 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/PjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDMzNC44NzcgMzM0Ljg3NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgMzM0Ljg3NyAzMzQuODc3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGc+PHBhdGggZD0iTTMzMy4xOTYsMTU1Ljk5OWgtMTYuMDY3VjgyLjA5YzAtMTcuNzE5LTE0LjQxNS0zMi4xMzQtMzIuMTM0LTMyLjEzNGgtMjEuNzYxTDI0MC45NjUsOS45MTdDMjM3LjU3MSwzLjc5OCwyMzEuMTEyLDAsMjI0LjEwNywwYy0zLjI2NSwwLTYuNTA0LDAuODQyLTkuMzY0LDIuNDI5bC04NS40NjQsNDcuNTI2SDMzLjgxNWMtMTcuNzE5LDAtMzIuMTM0LDE0LjQxNS0zMi4xMzQsMzIuMTM0djIyMC42NTNjMCwxNy43MTksMTQuNDE1LDMyLjEzNCwzMi4xMzQsMzIuMTM0aDI1MS4xOGMxNy43MTksMCwzMi4xMzQtMTQuNDE1LDMyLjEzNC0zMi4xMzR2LTY0LjgwMmgxNi4wNjdWMTU1Ljk5OXogTTI4NC45OTUsNjIuODA5YzkuODk3LDAsMTcuOTgyLDcuNTE5LDE5LjA2OCwxNy4xNGgtMjQuMTUybC05LjUyNS0xNy4xNEgyODQuOTk1eiBNMjIwLjk5NiwxMy42NjNjMy4wMTQtMS42OSw3LjA3LTAuNTA4LDguNzM0LDIuNDk0bDM1LjQ3Niw2My43ODZIMTAxLjc5OEwyMjAuOTk2LDEzLjY2M3ogTTMwNC4yNzUsMzAyLjc0MmMwLDEwLjYzLTguNjUxLDE5LjI4MS0xOS4yODEsMTkuMjgxSDMzLjgxNWMtMTAuNjMsMC0xOS4yODEtOC42NTEtMTkuMjgxLTE5LjI4MVY4Mi4wOWMwLTEwLjYzLDguNjUxLTE5LjI4MSwxOS4yODEtMTkuMjgxaDcyLjM1M0w3NS4zNDUsNzkuOTVIMzcuODMyYy0zLjU1NCwwLTYuNDI3LDIuODc5LTYuNDI3LDYuNDI3czIuODczLDYuNDI3LDYuNDI3LDYuNDI3aDE0LjM5NmgyMzQuODNoMTcuMjE3djYzLjIwMWgtNDYuOTk5Yy0yMS44MjYsMC0zOS41ODksMTcuNzY0LTM5LjU4OSwzOS41ODl2Mi43NjRjMCwyMS44MjYsMTcuNzY0LDM5LjU4OSwzOS41ODksMzkuNTg5aDQ2Ljk5OVYzMDIuNzQyeiBNMzIwLjM0MiwyMjUuMDg3aC0zLjIxM2gtNTkuODUzYy0xNC43NDMsMC0yNi43MzYtMTEuOTkyLTI2LjczNi0yNi43MzZ2LTIuNzY0YzAtMTQuNzQzLDExLjk5Mi0yNi43MzYsMjYuNzM2LTI2LjczNmg1OS44NTNoMy4yMTNWMjI1LjA4N3ogTTI3Ni45NjEsMTk3LjQ5N2MwLDcuODQxLTYuMzUsMTQuMTktMTQuMTksMTQuMTljLTcuODQxLDAtMTQuMTktNi4zNS0xNC4xOS0xNC4xOXM2LjM1LTE0LjE5LDE0LjE5LTE0LjE5QzI3MC42MTIsMTgzLjMwNiwyNzYuOTYxLDE4OS42NjIsMjc2Ljk2MSwxOTcuNDk3eiIvPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48L3N2Zz4=');
        $this->define('WOO_WALLET_PLUGIN_VERSION', '1.2.2');
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    private function define($name, $value) {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Check request
     * @param string $type
     * @return bool
     */
    private function is_request($type) {
        switch ($type) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined('DOING_AJAX');
            case 'cron' :
                return defined('DOING_CRON');
            case 'frontend' :
                return (!is_admin() || defined('DOING_AJAX') ) && !defined('DOING_CRON');
        }
    }

    /**
     * load plugin files
     */
    public function includes() {
        include_once( WOO_WALLET_ABSPATH . 'includes/helper/woo-wallet-util.php' );
        include_once( WOO_WALLET_ABSPATH . 'includes/helper/woo-wallet-update-functions.php' );
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-install.php' );
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings-api.php' );
        $this->settings_api = new Woo_Wallet_Settings_API();
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-wallet.php' );
        $this->wallet = new Woo_Wallet_Wallet();
        if ($this->is_request('admin')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-extensions.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php' );
        }
        if ($this->is_request('frontend')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php' );
        }
        if ($this->is_request('ajax')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php' );
        }
    }

    /**
     * Plugin url
     * @return string path
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url('/', WOO_WALLET_PLUGIN_FILE));
    }

    /**
     * Plugin init
     */
    private function init_hooks() {
        register_activation_hook(WOO_WALLET_PLUGIN_FILE, array('Woo_Wallet_Install', 'install'));
        add_filter('plugin_action_links_' . plugin_basename(WOO_WALLET_PLUGIN_FILE), array($this, 'plugin_action_links'));
        add_action('init', array($this, 'init'), 5);
        add_action('rest_api_init', array($this, 'rest_api_init'));
        do_action('woo_wallet_init');
    }

    /**
     * Plugin init
     */
    public function init() {
        $this->load_plugin_textdomain();
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-payment-method.php' );
        $this->add_marketplace_support();
        add_filter('woocommerce_email_classes', array($this, 'woocommerce_email_classes'));
        add_filter('woocommerce_payment_gateways', array($this, 'load_gateway'));

        foreach (apply_filters('wallet_credit_purchase_order_status', array('processing', 'completed')) as $status) {
            add_action('woocommerce_order_status_' . $status, array($this->wallet, 'wallet_credit_purchase'));
        }

        foreach (apply_filters('wallet_partial_payment_order_status', array('on-hold', 'processing', 'completed')) as $status) {
            add_action('woocommerce_order_status_' . $status, array($this->wallet, 'wallet_partial_payment'));
        }

        foreach (apply_filters('wallet_cashback_order_status', $this->settings_api->get_option('process_cashback_status', '_wallet_settings_credit', array('processing', 'completed'))) as $status) {
            add_action('woocommerce_order_status_' . $status, array($this->wallet, 'wallet_cashback'), 12);
        }

        add_action('woocommerce_order_status_cancelled', array($this->wallet, 'process_cancelled_order'));

        add_filter('woocommerce_reports_get_order_report_query', array($this, 'woocommerce_reports_get_order_report_query'));

        add_rewrite_endpoint(get_option('woocommerce_woo_wallet_endpoint', 'woo-wallet'), EP_PAGES);
        add_rewrite_endpoint(get_option('woocommerce_woo_wallet_transactions_endpoint', 'woo-wallet-transactions'), EP_PAGES);
        if (!get_option('_wallet_enpoint_added')) {
            flush_rewrite_rules();
            update_option('_wallet_enpoint_added', true);
        }
    }

    /**
     * WP REST API init.
     */
    public function rest_api_init() {
        include_once( WOO_WALLET_ABSPATH . 'includes/api/class-woo-wallet-rest-controller.php' );
        $rest_controller = new WOO_Wallet_REST_Controller();
        $rest_controller->register_routes();
    }

    public function plugin_action_links($links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url('admin.php?page=woo-wallet-settings') . '" aria-label="' . esc_attr__('View Wallet settings', 'woo-wallet') . '">' . esc_html__('Settings', 'woo-wallet') . '</a>',
        );

        return array_merge($action_links, $links);
    }

    /**
     * Text Domain loader
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists('get_user_locale') ? get_user_locale() : get_locale();
        $locale = apply_filters('plugin_locale', $locale, 'woo-wallet');

        unload_textdomain('woo-wallet');
        load_textdomain('woo-wallet', WP_LANG_DIR . '/woo-wallet/woo-wallet-' . $locale . '.mo');
        load_plugin_textdomain('woo-wallet', false, plugin_basename(dirname(WOO_WALLET_PLUGIN_FILE)) . '/languages');
    }

    /**
     * WooCommerce wallet payment gateway loader
     * @param array $load_gateways
     * @return array
     */
    public function load_gateway($load_gateways) {
        $load_gateways[] = 'Woo_Gateway_Wallet_payment';
        return $load_gateways;
    }

    /**
     * WooCommerce email loader
     * @param array $emails
     * @return array
     */
    public function woocommerce_email_classes($emails) {
        $emails['Woo_Wallet_Email_New_Transaction'] = include WOO_WALLET_ABSPATH . 'includes/emails/class-woo-wallet-email-new-transaction.php';
        return $emails;
    }

    /**
     * Exclude rechargable orders from admin report
     * @param array $query
     * @return array
     */
    public function woocommerce_reports_get_order_report_query($query) {
        $rechargable_orders = get_wallet_rechargeable_orders();
        if (!empty($rechargable_orders)) {
            $exclude_orders = implode(', ', $rechargable_orders);
            $query['where'] .= " AND posts.ID NOT IN ({$exclude_orders})";
        }
        return $query;
    }

    public function add_marketplace_support() {
        if (class_exists('WCMp')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/wc-merketplace/class-woo-wallet-wcmp-gateway.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/wc-merketplace/class-woo-wallet-wcmp.php' );
        }
        if (class_exists('WeDevs_Dokan')) {
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/dokan/class-woo-wallet-dokan.php' );
        }
    }

    /**
     * Load template
     * @param string $template_name
     * @param array $args
     * @param string $template_path
     * @param string $default_path
     */
    public function get_template($template_name, $args = array(), $template_path = '', $default_path = '') {
        if ($args && is_array($args)) {
            extract($args);
        }
        $located = $this->locate_template($template_name, $template_path, $default_path);
        include ($located);
    }

    /**
     * Locate template file
     * @param string $template_name
     * @param string $template_path
     * @param string $default_path
     * @return string
     */
    public function locate_template($template_name, $template_path = '', $default_path = '') {
        $default_path = apply_filters('woo_wallet_template_path', $default_path);
        if (!$template_path) {
            $template_path = 'woo-wallet';
        }
        if (!$default_path) {
            $default_path = WOO_WALLET_ABSPATH . 'templates/';
        }
        // Look within passed path within the theme - this is priority
        $template = locate_template(array(trailingslashit($template_path) . $template_name, $template_name));
        // Add support of third perty plugin
        $template = apply_filters('woo_wallet_locate_template', $template, $template_name, $template_path, $default_path);
        // Get default template
        if (!$template) {
            $template = $default_path . $template_name;
        }
        return $template;
    }

    /**
     * Display admin notice
     */
    public function admin_notices() {
        echo '<div class="error"><p>';
        _e('WooCommerce Wallet plugin requires <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woo-wallet');
        echo '</p></div>';
    }

}
