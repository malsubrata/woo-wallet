<?php

if ( ! defined( 'ABSPATH' ) ) {
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
     * wallet instance.
     * @var Woo_Wallet_Wallet 
     */
    public $wallet = null;
    /**
     * Cashback instance.
     * @var Woo_Wallet_Cashback 
     */
    public $cashback = null;
    /**
     * Wallet REST API
     * @var WooWallet_API 
     */
    public $rest_api = null;

    /**
     * Main instance
     * @return class object
     */
    public static function instance() {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Class constructor
     */
    public function __construct() {
        if (Woo_Wallet_Dependencies::is_woocommerce_active() ) {
            $this->define_constants();
            $this->includes();
            $this->init_hooks();
            do_action( 'woo_wallet_loaded' );
        } else {
            add_action( 'admin_notices', array( $this, 'admin_notices' ), 15);
        }
    }

    /**
     * Constants define
     */
    private function define_constants() {
        $this->define( 'WOO_WALLET_ABSPATH', dirname(WOO_WALLET_PLUGIN_FILE) . '/' );
        $this->define( 'WOO_WALLET_PLUGIN_FILE', plugin_basename(WOO_WALLET_PLUGIN_FILE) );
        $this->define( 'WOO_WALLET_PLUGIN_VERSION', '1.3.22' );
    }

    /**
     * 
     * @param string $name
     * @param mixed $value
     */
    private function define( $name, $value ) {
        if ( ! defined( $name) ) {
            define( $name, $value );
        }
    }

    /**
     * Check request
     * @param string $type
     * @return bool
     */
    private function is_request( $type) {
        switch ( $type) {
            case 'admin' :
                return is_admin();
            case 'ajax' :
                return defined( 'DOING_AJAX' );
            case 'cron' :
                return defined( 'DOING_CRON' );
            case 'frontend' :
                return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
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
        
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-cashback.php' );
        $this->cashback = new Woo_Wallet_Cashback();
        
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-widgets.php' );
        
        if ( $this->is_request( 'admin' ) ) {
            include_once( WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-extensions.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php' );
        }
        if ( $this->is_request( 'frontend' ) ) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php' );
        }
        if ( $this->is_request( 'ajax' ) ) {
            include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php' );
        }
    }

    /**
     * Plugin url
     * @return string path
     */
    public function plugin_url() {
        return untrailingslashit(plugins_url( '/', WOO_WALLET_PLUGIN_FILE) );
    }

    /**
     * Plugin init
     */
    private function init_hooks() {
        register_activation_hook(WOO_WALLET_PLUGIN_FILE, array( 'Woo_Wallet_Install', 'install' ) );
        add_filter( 'plugin_action_links_' . plugin_basename(WOO_WALLET_PLUGIN_FILE), array( $this, 'plugin_action_links' ) );
        add_action( 'init', array( $this, 'init' ), 5);
        add_action( 'widgets_init', array($this, 'woo_wallet_widget_init') );
        add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded_callback' ) );
        add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
        do_action( 'woo_wallet_init' );
    }

    /**
     * Plugin init
     */
    public function init() {
        $this->load_plugin_textdomain();
        include_once( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-payment-method.php' );
        $this->add_marketplace_support();
        add_filter( 'woocommerce_email_classes', array( $this, 'woocommerce_email_classes' ), 999 );
        add_filter( 'woocommerce_template_directory', array( $this, 'woocommerce_template_directory' ), 10, 2);
        add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );

        foreach ( apply_filters( 'wallet_credit_purchase_order_status', array( 'processing', 'completed' ) ) as $status) {
            add_action( 'woocommerce_order_status_' . $status, array( $this->wallet, 'wallet_credit_purchase' ) );
        }

        foreach ( apply_filters( 'wallet_partial_payment_order_status', array( 'on-hold', 'processing', 'completed' ) ) as $status) {
            add_action( 'woocommerce_order_status_' . $status, array( $this->wallet, 'wallet_partial_payment' ) );
        }

        foreach ( apply_filters( 'wallet_cashback_order_status', $this->settings_api->get_option( 'process_cashback_status', '_wallet_settings_credit', array( 'processing', 'completed' ) ) ) as $status) {
            add_action( 'woocommerce_order_status_' . $status, array( $this->wallet, 'wallet_cashback' ), 12 );
        }

        add_action( 'woocommerce_order_status_cancelled', array( $this->wallet, 'process_cancelled_order' ) );

        add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'woocommerce_reports_get_order_report_query' ) );
        add_filter('woocommerce_analytics_revenue_query_args', array($this, 'remove_wallet_rechargable_order_from_analytics'));
        add_filter('woocommerce_analytics_orders_stats_query_args', array($this, 'remove_wallet_rechargable_order_from_analytics'));
        
        add_filter('woocommerce_analytics_orders_select_query', array($this, 'woocommerce_analytics_orders_select_query_callback'));
        
        add_action( 'woocommerce_new_order_item', array( $this, 'woocommerce_new_order_item' ), 10, 2 );

        add_rewrite_endpoint( get_option( 'woocommerce_woo_wallet_endpoint', 'woo-wallet' ), EP_PAGES);
        add_rewrite_endpoint( get_option( 'woocommerce_woo_wallet_transactions_endpoint', 'woo-wallet-transactions' ), EP_PAGES);
        if ( !get_option( '_wallet_enpoint_added' ) ) {
            flush_rewrite_rules();
            update_option( '_wallet_enpoint_added', true );
        }
        
        add_action('deleted_user', array($this, 'delete_user_transaction_records'));
    }
    /**
     * WooWallet init widget
     */
    public function woo_wallet_widget_init(){
        register_widget('Woo_Wallet_Topup');
    }
    
    public function woocommerce_template_directory($template_dir, $template) {
        if(in_array($template, array('emails/low-wallet-balance.php', 'emails/user-new-transaction.php'))){
            $template_dir = 'woo-wallet';
        }
        return $template_dir;
    }

    /**
     * Load WooCommerce dependent class file.
     */
    public function woocommerce_loaded_callback() {
        include_once WOO_WALLET_ABSPATH . 'includes/abstracts/abstract-woo-wallet-actions.php';
        require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-actions.php';
        include_once WOO_WALLET_ABSPATH . '/includes/class-woo-wallet-api.php';
        $this->rest_api = new WooWallet_API();
    }

    /**
     * WP REST API init.
     */
    public function rest_api_init() {
        include_once( WOO_WALLET_ABSPATH . 'includes/api/class-woo-wallet-rest-controller.php' );
        $rest_controller = new WOO_Wallet_REST_Controller();
        $rest_controller->register_routes();
    }
    /**
     * Add settings link to plugin list.
     * @param array $links
     * @return array
     */
    public function plugin_action_links( $links) {
        $action_links = array(
            'settings' => '<a href="' . admin_url( 'admin.php?page=woo-wallet-settings' ) . '" aria-label="' . esc_attr__( 'View Wallet settings', 'woo-wallet' ) . '">' . esc_html__( 'Settings', 'woo-wallet' ) . '</a>',
        );

        return array_merge( $action_links, $links);
    }

    /**
     * Text Domain loader
     */
    public function load_plugin_textdomain() {
        $locale = is_admin() && function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale();
        $locale = apply_filters( 'plugin_locale', $locale, 'woo-wallet' );

        unload_textdomain( 'woo-wallet' );
        load_textdomain( 'woo-wallet', WP_LANG_DIR . '/woo-wallet/woo-wallet-' . $locale . '.mo' );
        load_plugin_textdomain( 'woo-wallet', false, plugin_basename(dirname(WOO_WALLET_PLUGIN_FILE) ) . '/languages' );
    }

    /**
     * WooCommerce wallet payment gateway loader
     * @param array $load_gateways
     * @return array
     */
    public function load_gateway( $load_gateways) {
        $load_gateways[] = 'Woo_Gateway_Wallet_payment';
        return $load_gateways;
    }

    /**
     * WooCommerce email loader
     * @param array $emails
     * @return array
     */
    public function woocommerce_email_classes( $emails) {
        $emails['Woo_Wallet_Email_New_Transaction'] = include WOO_WALLET_ABSPATH . 'includes/emails/class-woo-wallet-email-new-transaction.php';
        $emails['Woo_Wallet_Email_Low_Wallet_Balance'] = include WOO_WALLET_ABSPATH . 'includes/emails/class-woo-wallet-email-low-wallet-balance.php';
        return $emails;
    }

    /**
     * Exclude rechargable orders from admin report
     * @param array $query
     * @return array
     */
    public function woocommerce_reports_get_order_report_query( $query ) {
        $rechargable_orders = get_wallet_rechargeable_orders();
        if ( ! empty( $rechargable_orders) && apply_filters('woo_wallet_exclude_wallet_rechargeable_orders_from_report', true) ) {
            $exclude_orders = implode( ', ', $rechargable_orders);
            $query['where'] .= " AND posts.ID NOT IN ({$exclude_orders})";
        }
        return $query;
    }
    /**
     * Exclude rechargable orders from admin analytics
     * @param array $query_vars
     * @return array
     */
    public function remove_wallet_rechargable_order_from_analytics($query_vars){
        if(get_wallet_rechargeable_product() && apply_filters('woo_wallet_exclude_wallet_rechargeable_orders_from_report', true)){
            $query_vars['product_excludes'][] = get_wallet_rechargeable_product()->get_id();
        }

        return $query_vars;
    }
    
    public function woocommerce_analytics_orders_select_query_callback($results) {
        if($results && isset($results->data) && !empty($results->data)){
            foreach ($results->data as $key=> $result){
                $order = wc_get_order($result['order_id']);
                if(is_wallet_rechargeable_order($order)){
                    unset($results->data[$key]);
                }
            }
        }
        return $results;
    }
    /**
     * Load marketplace supported file.
     */
    public function add_marketplace_support() {
        if (class_exists( 'WCMp' ) ) {
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/wc-merketplace/class-woo-wallet-wcmp-gateway.php' );
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/wc-merketplace/class-woo-wallet-wcmp.php' );
        }
        if (class_exists( 'WeDevs_Dokan' ) ) {
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/dokan/class-woo-wallet-dokan.php' );
        }
        if(class_exists('WCFMmp')){
            include_once( WOO_WALLET_ABSPATH . 'includes/marketplace/wcfmmp/class-woo-wallet-wcfmmp.php' );
        }
    }
    /**
     * Store fee key to order item meta.
     * @param Int $item_id
     * @param WC_Order_Item_Fee $item
     */
    public function woocommerce_new_order_item($item_id, $item){
        if ( $item->get_type() == 'fee' ) {
            update_metadata( 'order_item', $item_id, '_legacy_fee_key', $item->legacy_fee_key );
        }
    }
    
    public function delete_user_transaction_records($id){
        global $wpdb;
        if( apply_filters('woo_wallet_delete_transaction_records', true) ){
            $wpdb->query($wpdb->prepare( "DELETE t.*, tm.* FROM {$wpdb->base_prefix}woo_wallet_transactions t JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta tm ON t.transaction_id = tm.transaction_id WHERE t.user_id = %d", $id ));
        }
    }

    /**
     * Load template
     * @param string $template_name
     * @param array $args
     * @param string $template_path
     * @param string $default_path
     */
    public function get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
        if ( $args && is_array( $args ) ) {
            extract( $args );
        }
        $located = $this->locate_template( $template_name, $template_path, $default_path);
        include ( $located);
    }

    /**
     * Locate template file
     * @param string $template_name
     * @param string $template_path
     * @param string $default_path
     * @return string
     */
    public function locate_template( $template_name, $template_path = '', $default_path = '' ) {
        $default_path = apply_filters( 'woo_wallet_template_path', $default_path);
        if ( !$template_path) {
            $template_path = 'woo-wallet';
        }
        if ( !$default_path) {
            $default_path = WOO_WALLET_ABSPATH . 'templates/';
        }
        // Look within passed path within the theme - this is priority
        $template = locate_template( array(trailingslashit( $template_path) . $template_name, $template_name) );
        // Add support of third perty plugin
        $template = apply_filters( 'woo_wallet_locate_template', $template, $template_name, $template_path, $default_path);
        // Get default template
        if ( !$template) {
            $template = $default_path . $template_name;
        }
        return $template;
    }

    /**
     * Display admin notice
     */
    public function admin_notices() {
        echo '<div class="error"><p>';
        _e( 'WooCommerce Wallet plugin requires <a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> plugins to be active!', 'woo-wallet' );
        echo '</p></div>';
    }

}
