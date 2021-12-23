<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}
if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {

    class Woo_Wallet_Admin {

        /**
         * The single instance of the class.
         *
         * @var Woo_Wallet_Admin
         * @since 1.1.10
         */
        protected static $_instance = null;

        /**
         * Woo_Wallet_Transaction_Details Class Object
         * @var Woo_Wallet_Transaction_Details 
         */
        public $transaction_details_table = NULL;

        /**
         * Woo_Wallet_Balance_Details Class Object
         * @var Woo_Wallet_Balance_Details 
         */
        public $balance_details_table = NULL;

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
            add_action( 'admin_init', array( $this, 'admin_init' ) );
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 10 );
            add_action( 'admin_menu', array( $this, 'admin_menu' ), 50 );
            if ( 'on' === woo_wallet()->settings_api->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'on' ) && 'product' === woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' ) ) {
                add_filter('woocommerce_product_data_tabs', array($this, 'woocommerce_product_data_tabs'));
                add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_product_data_panels' ) );
                add_action( 'save_post_product', array( $this, 'save_post_product' ) );
                
                add_action('woocommerce_variation_options_pricing', array($this, 'woocommerce_variation_options_pricing'), 10, 3);
                add_action('woocommerce_save_product_variation', array($this, 'woocommerce_save_product_variation'), 10, 2);
            }
            add_action( 'woocommerce_admin_order_totals_after_tax', array( $this, 'add_wallet_payment_amount' ), 10, 1 );

            add_action( 'woocommerce_coupon_options', array( $this, 'add_coupon_option_for_cashback' ) );
            add_action( 'woocommerce_coupon_options_save', array( $this, 'save_coupon_data' ) );

            add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 5);

            if ( 'on' === woo_wallet()->settings_api->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'on' ) && 'product_cat' === woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' ) ) {
                add_action( 'product_cat_add_form_fields', array( $this, 'add_product_cat_cashback_field' ) );
                add_action( 'product_cat_edit_form_fields', array( $this, 'edit_product_cat_cashback_field' ) );
                add_action( 'created_term', array( $this, 'save_product_cashback_field' ), 10, 3);
                add_action( 'edit_term', array( $this, 'save_product_cashback_field' ), 10, 3);
            }
            add_filter( 'woocommerce_custom_nav_menu_items', array( $this, 'woocommerce_custom_nav_menu_items' ) );

            add_filter( 'manage_users_columns', array( $this, 'manage_users_columns' ) );
            add_filter( 'manage_users_custom_column', array( $this, 'manage_users_custom_column' ), 10, 3);
            add_filter( 'set-screen-option', array( $this, 'set_wallet_screen_options' ), 10, 3);
            add_filter( 'woocommerce_screen_ids', array( $this, 'woocommerce_screen_ids_callback' ) );
            add_action('woocommerce_after_order_fee_item_name', array($this, 'woocommerce_after_order_fee_item_name_callback'), 10, 2);
            add_action('woocommerce_new_order', array($this, 'woocommerce_new_order'));
            add_filter( 'woocommerce_order_actions', array( $this, 'woocommerce_order_actions' ));
            add_action( 'woocommerce_order_action_recalculate_order_cashback', array( $this, 'recalculate_order_cashback'));
            
            add_action( 'admin_notices', array( $this, 'show_promotions' ) );
            add_filter( 'woocommerce_settings_pages', array( $this, 'add_woocommerce_account_endpoint_settings' ) );
            
            add_action('wp_nav_menu_item_custom_fields', array($this, 'wp_nav_menu_item_custom_fields'));
            add_filter('wp_update_nav_menu_item', array($this, 'wp_update_nav_menu_item'), 10, 2);
            add_action('woocommerce_after_dashboard_status_widget', array($this, 'add_wallet_topup_report'));
            
            add_action('edit_user_profile', array($this, 'add_wallet_management_fields'));
            add_action('show_user_profile', array($this, 'add_wallet_management_fields'));
        }
        /**
         * 
         * @param WP_User $user
         */
        public function add_wallet_management_fields($user) {
            ?>
            <h3 class="heading"><?php _e('Wallet Management', 'woo-wallet'); ?></h3>
            <table class="form-table">
                <tr>
                    <th><label for="contact"><?php _e('Current wallet balance', 'woo-wallet'); ?></label></th>

                    <td>
                        <?php echo woo_wallet()->wallet->get_wallet_balance($user->ID); ?>
                    </td>

                </tr>
                <tr>
                    <th><label for="contact"><?php _e('Lock / Unlock', 'woo-wallet'); ?></label></th>

                    <td>
                        <button type="button" class="button hide-if-no-js lock-unlock-user-wallet" data-user_id="<?php echo $user->ID; ?>" data-type="<?php echo get_user_meta($user->ID, '_is_wallet_locked', true) ? 'unlock' : 'lock'; ?>">
                            <?php if(get_user_meta($user->ID, '_is_wallet_locked', true)){ ?>
                                <span class="dashicons dashicons-unlock" style="padding-top: 3px;"></span> <label><?php _e('Unlock', 'woo-wallet'); ?></label>
                            <?php } else { ?>
                                <span class="dashicons dashicons-lock" style="padding-top: 3px;"></span> <label><?php _e('Lock', 'woo-wallet'); ?></label>
                            <?php } ?>
                        </button>
                    </td>

                </tr>
                <?php do_action('after_terawallet_management_fields', $user); ?>
            </table>

            <?php
        }

        /**
         * Add Total wallet top-up amount 
         * to WooCommerce Status report widget.
         */
        public function add_wallet_topup_report() {
            if (current_user_can('view_woocommerce_reports')) {
                $wallet_recharge_order_ids = get_wallet_rechargeable_orders( array( 'date_query' => array( 'after' => date('Y-m-01') ) ) );
                $top_up_amount = 0;
                foreach ($wallet_recharge_order_ids as $order_id){
                    $order = wc_get_order($order_id);
                    $recharge_amount = apply_filters( 'woo_wallet_credit_purchase_amount', $order->get_subtotal( 'edit' ), $order_id );
                    if($charge_amount = get_post_meta($order_id, '_wc_wallet_purchase_gateway_charge', true)){
                        $recharge_amount -= $charge_amount;
                    }
                    $top_up_amount += $recharge_amount;
                }
                ?>
                <li class="sales-this-month">
                    <a href="<?php echo esc_url(admin_url('admin.php?page=wc-reports&tab=orders&range=month')); ?>">
                <?php
                printf(
                        /* translators: %s: wallet top-up */
                        esc_html__('%s wallet top-up this month', 'woo-wallet'),
                        '<strong>' . wc_price($top_up_amount) . '</strong>'
                ); // phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
                ?>
                    </a>
                </li>
                <?php
            }
        }

        public function wp_update_nav_menu_item($menu_id, $menu_item_db_id){
            if (isset($_POST["show-wallet-icon-amount-$menu_item_db_id"]) && 'on' === $_POST["show-wallet-icon-amount-$menu_item_db_id"]) {
                update_post_meta($menu_item_db_id, '_show_wallet_icon_amount', true);
            } else {
                delete_post_meta($menu_item_db_id, '_show_wallet_icon_amount');
            }
        }

        public function wp_nav_menu_item_custom_fields($item_id){
            $menu_post = get_post($item_id);
            if ('my-wallet' != $menu_post->post_name) {
                return;
            }
            ?>
            <p class="field-wallet-icon wallet-icon">
                <label for="show-wallet-icon-amount-<?php echo $item_id; ?>">
                    <input type="checkbox" <?php checked(get_post_meta($item_id, '_show_wallet_icon_amount', true)); ?> id="edit-menu-item-wallet-icon-<?php echo $item_id; ?>" name="show-wallet-icon-amount-<?php echo $item_id; ?>"/>
                    <span class="description"><?php _e('Display wallet icon and amount instead of menu navigation label?', 'woo-wallet'); ?></span>
                </label>
            </p>
            <?php
        }

        /**
         * Admin init
         */
        public function admin_init() {
            if (version_compare(WC_VERSION, '3.4', '<' ) ) {
                add_filter( 'woocommerce_account_settings', array( $this, 'add_woocommerce_account_endpoint_settings' ) );
            }
            $this->download_export_file();
        }
        /**
         * Download generated export CSV file.
         */
        public function download_export_file() {
            if (isset($_GET['action'], $_GET['nonce']) && wp_verify_nonce(wp_unslash($_GET['nonce']), 'terawallet-transaction-csv') && 'download_export_csv' === wp_unslash($_GET['action'])) { // WPCS: input var ok, sanitization ok.
                $exporter = new TeraWallet_CSV_Exporter();
                if (!empty($_GET['filename'])) { // WPCS: input var ok.
                    $exporter->set_filename(wp_unslash($_GET['filename'])); // WPCS: input var ok, sanitization ok.
                }
                $exporter->export();
            }
        }

        /**
         * init admin menu
         */
        public function admin_menu() {
            $woo_wallet_menu_page_hook = add_menu_page( __('TeraWallet', 'woo-wallet'), __('TeraWallet', 'woo-wallet'), get_wallet_user_capability(), 'woo-wallet', array( $this, 'wallet_page' ), '', 59);
            add_action( "load-$woo_wallet_menu_page_hook", array( $this, 'add_woo_wallet_details' ) );
            $woo_wallet_menu_page_hook_add = add_submenu_page( '', __( 'Woo Wallet', 'woo-wallet' ), __( 'Woo Wallet', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-add', array( $this, 'add_balance_to_user_wallet' ) );
            add_action( "load-$woo_wallet_menu_page_hook_add", array( $this, 'add_woo_wallet_add_balance_option' ) );
            $woo_wallet_menu_page_hook_view = add_submenu_page( '', __( 'Woo Wallet', 'woo-wallet' ), __( 'Woo Wallet', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-transactions', array( $this, 'transaction_details_page' ) );
            add_action( "load-$woo_wallet_menu_page_hook_view", array( $this, 'add_woo_wallet_transaction_details_option' ) );
            add_submenu_page( 'woo-wallet', __( 'Actions', 'woo-wallet' ), __( 'Actions', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-actions', array( $this, 'plugin_actions_page' ) );
            
            add_submenu_page(null, null, null, get_wallet_user_capability(), 'terawallet-exporter', array($this, 'terawallet_exporter_page'));
        }
        
        public function terawallet_exporter_page() {
            include_once WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php';
            include_once WOO_WALLET_ABSPATH . 'templates/admin/html-exporter.php';
        }
        /**
         * Plugin action settings page 
         */
        public function plugin_actions_page() {
            $screen = get_current_screen();
            $wallet_actions = new WOO_Wallet_Actions();
            $woo_wallet_screen_id = sanitize_title( __( 'TeraWallet', 'woo-wallet' ) );
            if ( in_array($screen->id, array( "{$woo_wallet_screen_id}_page_woo-wallet-actions")) && isset( $_GET['action'] ) && isset( $wallet_actions->actions[$_GET['action']] ) ) {
                $this->display_action_settings();
            } else {
                $this->display_actions_table();
            }
        }
        /**
         * Plugin action setting init
         */
        public function display_action_settings() {
            $wallet_actions = WOO_Wallet_Actions::instance();
            ?>
            <div class="wrap woocommerce">
                <form method="post">
                    <?php
                    $wallet_actions->actions[$_GET['action']]->init_settings();
                    $wallet_actions->actions[$_GET['action']]->admin_options();
                    submit_button();
                    ?>
                </form>
            </div>
            <?php
        }
        /**
         * Plugin action setting table
         */
        public function display_actions_table() {
            $wallet_actions = WOO_Wallet_Actions::instance();
            echo '<div class="wrap">';
            echo '<h2>' . __( 'Wallet actions', 'woo-wallet' ) . '</h2>';
            settings_errors();
            ?>
            <p><?php _e( 'Integrated wallet actions are listed below. If active those actions will be triggered with respective WordPress hook.', 'woo-wallet' ); ?></p>
            <table class="wc_emails widefat" cellspacing="0">
                <thead>
                    <tr>
                        <th class="wc-email-settings-table-status"></th>
                        <th class="wc-email-settings-table-name"><?php _e( 'Action', 'woo-wallet' ); ?></th>
                        <th class="wc-email-settings-table-name"><?php _e( 'Description', 'woo-wallet' ); ?></th>
                        <th class="wc-email-settings-table-actions"></th>						
                    </tr>
                </thead>
                <tbody class="ui-sortable">
                    <?php foreach ( $wallet_actions->actions as $action) : ?>
                        <tr data-gateway_id="<?php echo $action->get_action_id(); ?>">
                            <td>
                                <?php
                                if ( $action->is_enabled() ) {
                                    echo '<span class="status-enabled tips" data-tip="' . esc_attr__( 'Enabled', 'woo-wallet' ) . '">' . esc_html__( 'Yes', 'woo-wallet' ) . '</span>';
                                } else {
                                    echo '<span class="status-disabled tips" data-tip="' . esc_attr__( 'Disabled', 'woo-wallet' ) . '">-</span>';
                                }
                                ?>
                            </td>
                            <td class="name" width=""><a href="<?php echo esc_url(admin_url( 'admin.php?page=woo-wallet-actions&action=' . strtolower( $action->id ) ) ); ?>" class="wc-payment-gateway-method-title"><?php echo $action->get_action_title(); ?></a></td>
                            <td class="description" width=""><?php echo $action->get_action_description(); ?></td>
                            <td class="action" width="1%"><a class="button alignright" href="<?php echo esc_url(admin_url( 'admin.php?page=woo-wallet-actions&action=' . strtolower( $action->id ) ) ); ?>"><?php
                                    if ( $action->is_enabled() ) {
                                        echo __( 'Manage', 'woo-wallet' );
                                    } else {
                                        echo __( 'Setup', 'woo-wallet' );
                                    }
                                    ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            echo '</div>';
        }

        /**
         * Register and enqueue admin styles and scripts
         * @global type $post
         */
        public function admin_scripts() {
            global $post;
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
            // register styles
            wp_register_style( 'woo_wallet_admin_styles', woo_wallet()->plugin_url() . '/assets/css/admin.css', array(), WOO_WALLET_PLUGIN_VERSION);

            // Register scripts
            wp_register_script( 'woo_wallet_admin_product', woo_wallet()->plugin_url() . '/assets/js/admin/admin-product' . $suffix . '.js', array( 'jquery' ), WOO_WALLET_PLUGIN_VERSION);
            wp_register_script( 'woo_wallet_admin_order', woo_wallet()->plugin_url() . '/assets/js/admin/admin-order' . $suffix . '.js', array( 'jquery', 'wc-admin-order-meta-boxes' ), WOO_WALLET_PLUGIN_VERSION);

            if (in_array( $screen_id, array( 'product', 'edit-product' ) ) ) {
                wp_enqueue_script( 'woo_wallet_admin_product' );
                wp_localize_script( 'woo_wallet_admin_product', 'woo_wallet_admin_product_param', array( 'product_id' => get_wallet_rechargeable_product()->get_id(), 'is_hidden' => apply_filters( 'woo_wallet_hide_rechargeable_product', true ) ) );
            }
            if (in_array( $screen_id, array( 'shop_order' ) ) ) {
                $order = wc_get_order( $post->ID );
                wp_enqueue_script( 'woo_wallet_admin_order' );
                $order_localizer = array(
                    'order_id' => $post->ID,
                    'payment_method' => $order->get_payment_method( 'edit' ),
                    'default_price' => wc_price( 0 ),
                    'is_refundable' => apply_filters( 'woo_wallet_is_order_refundable', ( ! is_wallet_rechargeable_order( $order ) && $order->get_payment_method( 'edit' ) != 'wallet' ) && $order->get_customer_id( 'edit' ), $order ),
                    'i18n' => array(
                        'refund' => __( 'Refund', 'woo-wallet' ),
                        'via_wallet' => __( 'to customer wallet', 'woo-wallet' )
                    )
                );
                wp_localize_script( 'woo_wallet_admin_order', 'woo_wallet_admin_order_param', $order_localizer);
            }
            wp_enqueue_style( 'woo_wallet_admin_styles' );
            
            // register exporter styles
            wp_register_style('terawallet-exporter-style', woo_wallet()->plugin_url() . '/assets/css/export.css', array(), WOO_WALLET_PLUGIN_VERSION);

            // register exporter scripts
            wp_register_script('terawallet-exporter-script', woo_wallet()->plugin_url() . '/assets/js/admin/export'. $suffix . '.js', array('jquery'), WOO_WALLET_PLUGIN_VERSION);
            wp_localize_script(
                    'terawallet-exporter-script',
                    'terawallet_export_params',
                    array(
                        'i18n' => array(
                            'inputTooShort' => __('Please enter 3 or more characters', 'woo-wallet'),
                            'no_resualt' => __('No results found', 'woo-wallet'),
                            'searching' => __('Searching…', 'woo-wallet'),
                        ),
                        'export_nonce' => wp_create_nonce('terawallet-exporter-script'),
                        'export_url' => '',
                        'export_button_title' => __('Export', 'woo-wallet')
                    )
            );

            wp_register_script('terawallet_admin', woo_wallet()->plugin_url() . '/assets/js/admin/admin'. $suffix . '.js', array('jquery'), WOO_WALLET_PLUGIN_VERSION, true);
            wp_localize_script(
                    'terawallet_admin', 
                    'terawallet_admin_params', 
                    apply_filters('terawallet_admin_js_params', array(
                                'ajax_url' => admin_url('admin-ajax.php'),
                                'export_url' => add_query_arg(array('page' => 'terawallet-exporter'), admin_url('admin.php')), 
                                'export_title' => __('Export', 'woo-wallet')
                            )
                        )
                    );

            if (in_array($screen_id, array('admin_page_terawallet-exporter'))) {
                wp_enqueue_style('select2');
                wp_enqueue_style('terawallet-exporter-style');
            }
            
            wp_enqueue_script('terawallet_admin');
        }

        /**
         * Display user wallet details page
         */
        public function wallet_page() {
            ?>
            <div class="wrap">
                <h2><?php _e('Users wallet details', 'woo-wallet'); ?></h2>
                <?php do_action('woo_wallet_before_balance_details_table'); ?>
                <?php $this->balance_details_table->views(); ?>
                <form id="posts-filter" method="post">
                    <?php $this->balance_details_table->search_box(__('Search Users', 'woo-wallet'), 'search_id'); ?>
                    <?php $this->balance_details_table->display(); ?>
                </form>
                <script type="text/javascript">
                jQuery(function ($) {
                    $('#search-submit').on('click', function (event){
                        event.preventDefault();
                        var search = $('#search_id-search-input').val();
                        var url = new URL(window.location.href); 
                        url.searchParams.set('s', search);
                        window.location.href = url;
                    });
                });
                </script>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Admin add wallet balance form
         */
        public function add_balance_to_user_wallet() {
            $user_id = filter_input(INPUT_GET, 'user_id' );
            $currency = apply_filters( 'woo_wallet_user_currency', '', $user_id );
            $user = new WP_User($user_id);
            ?>
            <div class="wrap">
                <?php settings_errors(); ?>
                <h2><?php echo sprintf(__('Adjust Balance: %1$s (%2$s)', 'woo-wallet'), $user->display_name, $user->user_email); ?> <a style="text-decoration: none;" href="<?php echo add_query_arg( array( 'page' => 'woo-wallet' ), admin_url( 'admin.php' ) ); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
                <p>
                    <?php
                    _e( 'Current wallet balance: ', 'woo-wallet' );
                    echo woo_wallet()->wallet->get_wallet_balance( $user_id );
                    ?>
                </p>
                <form id="posts-filter" method="post">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="balance_amount"><?php echo __( 'Amount', 'woo-wallet' ) . ' ( ' . get_woocommerce_currency_symbol( $currency) . ' )'; ?></label></th>
                                <td>
                                    <input type="number" step="any" name="balance_amount" class="regular-text" />
                                    <p class="description"><?php _e( 'Enter Amount', 'woo-wallet' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="payment_type"><?php _e( 'Type', 'woo-wallet' ); ?></label></th>
                                <td>
                                    <?php $payment_types = apply_filters('woo_wallet_adjust_balance_payment_type', array('credit' => __( 'Credit', 'woo-wallet' ), 'debit' => __( 'Debit', 'woo-wallet' ))); ?>
                                    <select class="regular-text" name="payment_type" id="payment_type">
                                        <?php foreach ($payment_types as $key => $value) : ?>
                                        <option value="<?php echo $key ?>"><?php echo $value; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php _e( 'Select payment type', 'woo-wallet' ); ?></p>
                                </td>
                            </tr>
                            <?php do_action('woo_wallet_after_payment_type_field') ?>
                            <tr>
                                <th scope="row"><label for="payment_description"><?php _e( 'Description', 'woo-wallet' ); ?></label></th>
                                <td>
                                    <textarea name="payment_description" class="regular-text"></textarea>
                                    <p class="description"><?php _e( 'Enter Description', 'woo-wallet' ); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
                    <?php wp_nonce_field( 'woo-wallet-admin-adjust-balance', 'woo-wallet-admin-adjust-balance' ); ?>
                    <?php submit_button(); ?>
                </form>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Display transaction details page
         */
        public function transaction_details_page() {
            $user_id = filter_input(INPUT_GET, 'user_id' );
            ?>
            <div class="wrap">
                <h2><?php _e( 'Transaction details', 'woo-wallet' ); ?> <a style="text-decoration: none;" href="<?php echo add_query_arg( array( 'page' => 'woo-wallet' ), admin_url( 'admin.php' ) ); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
                <p><?php _e( 'Current wallet balance: ', 'woo-wallet' ); echo woo_wallet()->wallet->get_wallet_balance( $user_id ); ?></p>
                <?php do_action('before_woo_wallet_transaction_details_page', $user_id); ?>
                <form id="posts-filter" method="get">
                    <?php $this->transaction_details_table->display(); ?>
                </form>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Wallet details page initialization
         */
        public function add_woo_wallet_details() {
            $option = 'per_page';
            $args = array(
                'label' => 'Number of items per page:',
                'default' => 15,
                'option' => 'users_per_page'
            );
            add_screen_option( $option, $args );
            include_once( WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-balance-details.php' );
            $this->balance_details_table = new Woo_Wallet_Balance_Details();
            $this->balance_details_table->prepare_items();
        }

        /**
         * Handel admin add wallet balance
         */
        public function add_woo_wallet_add_balance_option() {
            if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['woo-wallet-admin-adjust-balance'] ) && wp_verify_nonce( $_POST['woo-wallet-admin-adjust-balance'], 'woo-wallet-admin-adjust-balance' ) ) {
                $transaction_id = NULL;
                $message = '';
                $user_id = filter_input(INPUT_POST, 'user_id' );
                $amount = filter_input(INPUT_POST, 'balance_amount' );
                $payment_type = filter_input(INPUT_POST, 'payment_type' );
                $description = filter_input(INPUT_POST, 'payment_description' );
                if ( $user_id != NULL && ! empty( $user_id ) && $amount != NULL && ! empty( $amount ) ) {
                    $amount = apply_filters( 'woo_wallet_addjust_balance_amount', number_format( $amount, wc_get_price_decimals(), '.', '' ), $user_id );
                    if ( 'credit' === $payment_type) {
                        $transaction_id = woo_wallet()->wallet->credit( $user_id, $amount, $description);
                    } else if ( 'debit' === $payment_type) {
                        $transaction_id = woo_wallet()->wallet->debit( $user_id, $amount, $description);
                    }
                    if ( !$transaction_id ) {
                        $message = __( 'An error occurred please try again', 'woo-wallet' );
                    }
                } else {
                    $message = __( 'Please enter amount', 'woo-wallet' );
                }
                if ( !$transaction_id ) {
                    add_settings_error( '', '102', $message);
                } else {
                    do_action( 'woo_wallet_admin_adjust_balance', $transaction_id );
                    wp_safe_redirect(add_query_arg(array('page' => 'woo-wallet-transactions', 'user_id' => $user_id), admin_url('admin.php')));
                    exit();
                }
            }
        }

        /**
         * Transaction details page initialization
         */
        public function add_woo_wallet_transaction_details_option() {
            $option = 'per_page';
            $args = array(
                'label' => 'Number of items per page:',
                'default' => 10,
                'option' => 'transactions_per_page'
            );
            add_screen_option( $option, $args );
            include_once( WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-transaction-details.php' );
            $this->transaction_details_table = new Woo_Wallet_Transaction_Details();
            $this->transaction_details_table->prepare_items();
        }

        public function set_wallet_screen_options( $screen_option, $option, $value ) {
            if ( 'transactions_per_page' === $option) {
                $screen_option = $value;
            }
            return $screen_option;
        }
        
        /**
         * add wallet cashback tab to product page
         */
        public function woocommerce_product_data_tabs($tabs){
            $tabs['wallet_cashback'] = array(
                'label'    => __( 'Cashback', 'woo-wallet' ),
                'target'   => 'wallet_cashback_product_data',
                'class'    => array( 'hide_if_variable' ),
                'priority' => 80,
            );
            return $tabs;
        }

        /**
         * WooCommerce product tab content
         * @global object $post
         */
        public function woocommerce_product_data_panels() {
            global $post;
            ?>
            <div id="wallet_cashback_product_data" class="panel woocommerce_options_panel">
                <?php
                woocommerce_wp_select( array(
                    'id' => 'wcwp_cashback_type',
                    'label' => __( 'Cashback type', 'woo-wallet' ),
                    'description' => __( 'Select cashback type percentage or fixed', 'woo-wallet' ),
                    'options' => array( 'percent' => __( 'Percentage', 'woo-wallet' ), 'fixed' => __( 'Fixed', 'woo-wallet' ) ),
                    'value' => get_post_meta( $post->ID, '_cashback_type', true )
                ) );
                woocommerce_wp_text_input( array(
                    'id' => 'wcwp_cashback_amount',
                    'type' => 'number',
                    'data_type' => 'decimal',
                    'custom_attributes' => array( 'step' => '0.01' ),
                    'label' => __( 'Cashback Amount', 'woo-wallet' ),
                    'description' => __( 'Enter cashback amount', 'woo-wallet' ),
                    'value' => get_post_meta( $post->ID, '_cashback_amount', true )
                ) );
                ?>
            </div>
            <?php
        }

        /**
         * Save post meta
         * @param int $post_ID
         */
        public function save_post_product( $post_ID ) {
            if ( isset( $_POST['wcwp_cashback_type'] ) ) {
                update_post_meta( $post_ID, '_cashback_type', esc_attr( $_POST['wcwp_cashback_type'] ) );
            }
            if ( isset( $_POST['wcwp_cashback_amount'] ) ) {
                update_post_meta( $post_ID, '_cashback_amount', sanitize_text_field( $_POST['wcwp_cashback_amount'] ) );
            }
        }
        /**
         * Add cashback option for variable product.
         * @param int $loop
         * @param array $variation_data
         * @param object $variation
         */
        public function woocommerce_variation_options_pricing($loop, $variation_data, $variation){
            woocommerce_wp_select( array(
                'id' => 'variable_cashback_type[' . $loop . ']',
                'name' => 'variable_cashback_type[' . $loop . ']',
                'label' => __( 'Cashback type', 'woo-wallet' ),
                'options' => array( 'percent' => __( 'Percentage', 'woo-wallet' ), 'fixed' => __( 'Fixed', 'woo-wallet' ) ),
                'value' => get_post_meta( $variation->ID, '_cashback_type', true ),
                'wrapper_class' => 'form-row form-row-first',
            ) );
            woocommerce_wp_text_input( array(
                'id' => 'variable_cashback_amount[' . $loop . ']',
                'name' => 'variable_cashback_amount[' . $loop . ']',
                'type' => 'number',
                'data_type' => 'decimal',
                'custom_attributes' => array(
                        'step' => '1',
                        'min' => '0'
                    ),
                'label' => __( 'Cashback Amount', 'woo-wallet' ),
                'value' => get_post_meta( $variation->ID, '_cashback_amount', true ),
                'wrapper_class' => 'form-row form-row-last',
            ) );
        }
        /**
         * Save cashback option for variable product.
         * @param int $variation_id
         * @param int $i
         */
        public function woocommerce_save_product_variation($variation_id, $i){
            $cashback_type = isset( $_POST['variable_cashback_type'][ $i ] ) ? wc_clean( wp_unslash( $_POST['variable_cashback_type'][ $i ] ) ) : null;
            $cashback_amount = isset( $_POST['variable_cashback_amount'][ $i ] ) ? wc_clean( wp_unslash( $_POST['variable_cashback_amount'][ $i ] ) ) : null;
            update_post_meta($variation_id, '_cashback_type', esc_attr($cashback_type));
            update_post_meta($variation_id, '_cashback_amount', esc_attr($cashback_amount));
        }

        /**
         * Display partial payment and cashback amount in order page
         * @param type $order_id
         * @return type
         */
        public function add_wallet_payment_amount( $order_id ) {
            $order = wc_get_order( $order_id );
            if ( $total_cashback_amount = get_total_order_cashback_amount( $order_id ) ) {
                ?>
                <tr>
                    <td class="label"><?php _e( 'Cashback', 'woo-wallet' ); ?>:</td>
                    <td width="1%"></td>
                    <td class="via-wallet">
                        <?php echo wc_price( $total_cashback_amount, woo_wallet_wc_price_args($order->get_customer_id()) ); ?>
                    </td>
                </tr>
                <?php
            }
        }

        /**
         * Add setting to convert coupon to cashback.
         * @since 1.0.6
         */
        public function add_coupon_option_for_cashback() {
            woocommerce_wp_checkbox( array(
                'id' => '_is_coupon_cashback',
                'label' => __( 'Apply as cashback', 'woo-wallet' ),
                'description' => __( 'Check this box if the coupon should apply as cashback.', 'woo-wallet' ),
            ) );
        }

        /**
         * Save coupon data
         * @param int $post_id
         * @since 1.0.6
         */
        public function save_coupon_data( $post_id ) {
            $_is_coupon_cashback = isset( $_POST['_is_coupon_cashback'] ) ? 'yes' : 'no';
            update_post_meta( $post_id, '_is_coupon_cashback', $_is_coupon_cashback);
        }

        /**
         * Add review link
         * @param string $footer_text
         * @return string
         */
        public function admin_footer_text( $footer_text) {
            if ( !current_user_can(get_wallet_user_capability() ) ) {
                return $footer_text;
            }
            $current_screen = get_current_screen();
            $woo_wallet_settings_screen_id = sanitize_title( __( 'TeraWallet', 'woo-wallet' ) );
            $woo_wallet_pages = array( 'toplevel_page_woo-wallet', 'admin_page_woo-wallet-add', 'admin_page_woo-wallet-transactions', "{$woo_wallet_settings_screen_id}_page_woo-wallet-actions", "{$woo_wallet_settings_screen_id}_page_woo-wallet-extensions", "{$woo_wallet_settings_screen_id}_page_woo-wallet-settings" );
            if ( isset( $current_screen->id ) && in_array( $current_screen->id, $woo_wallet_pages) ) {
                if ( !get_option( 'woocommerce_wallet_admin_footer_text_rated' ) ) {
                    $footer_text = sprintf(
                            __( 'If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'woo-wallet' ), sprintf( '<strong>%s</strong>', esc_html__( 'WooCommerce Wallet', 'woo-wallet' ) ), '<a href="https://wordpress.org/support/plugin/woo-wallet/reviews?rate=5#new-post" target="_blank" class="wc-rating-link" data-rated="' . esc_attr__( 'Thanks :)', 'woo-wallet' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
                    );
                    wc_enqueue_js( "
					jQuery( 'a.wc-rating-link' ).click( function() {
						jQuery.post( '" . WC()->ajax_url() . "', { action: 'woocommerce_wallet_rated' } );
						jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) );
					});
				" );
                } else {
                    $footer_text = __( 'Thank you for using WooCommerce Wallet.', 'woo-wallet' );
                }
            }
            return $footer_text;
        }

        /**
         * Wallet endpoins settings
         * @param array $settings
         * @return array
         */
        public function add_woocommerce_account_endpoint_settings( $settings) {
            $settings_fields = apply_filters( 'woo_wallet_endpoint_settings_fields', array(
                array(
                    'title' => __( 'My Wallet', 'woo-wallet' ),
                    'desc' => __( 'Endpoint for the "My account &rarr; My Wallet" page.', 'woo-wallet' ),
                    'id' => 'woocommerce_woo_wallet_endpoint',
                    'type' => 'text',
                    'default' => 'woo-wallet',
                    'desc_tip' => true,
                ),
                array(
                    'title' => __( 'Wallet Transactions', 'woo-wallet' ),
                    'desc' => __( 'Endpoint for the "My account &rarr; View wallet transactions" page.', 'woo-wallet' ),
                    'id' => 'woocommerce_woo_wallet_transactions_endpoint',
                    'type' => 'text',
                    'default' => 'woo-wallet-transactions',
                    'desc_tip' => true,
                )
            ) );

            $walletendpoint_settings = array(
                array(
                    'title' => __( 'Wallet endpoints', 'woo-wallet' ),
                    'type' => 'title',
                    'desc' => __( 'Endpoints are appended to your page URLs to handle specific actions on the accounts pages. They should be unique and can be left blank to disable the endpoint.', 'woo-wallet' ),
                    'id' => 'wallet_endpoint_options'
                )
            );
            foreach ( $settings_fields as $settings_field) {
                $walletendpoint_settings[] = $settings_field;
            }
            $walletendpoint_settings[] = array( 'type' => 'sectionend', 'id' => 'wallet_endpoint_options' );

            return array_merge( $settings, $walletendpoint_settings);
        }

        /**
         * Display product category wise cashback field.
         */
        public function add_product_cat_cashback_field() {
            ?>
            <div class="form-field term-display-type-wrap">
                <label for="woo_product_cat_cashback_type"><?php _e( 'Cashback type', 'woo-wallet' ); ?></label>
                <select name="woo_product_cat_cashback_type" id="woo_product_cat_cashback_type">
                    <option value="percent"><?php _e( 'Percentage', 'woo-wallet' ); ?></option>
                    <option value="fixed"><?php _e( 'Fixed', 'woo-wallet' ); ?></option>
                </select>
            </div>
            <div class="form-field term-display-type-wrap">
                <label for="woo_product_cat_cashback_amount"><?php _e( 'Cashback Amount', 'woo-wallet' ); ?></label>
                <input type="number" step="0.01" name="woo_product_cat_cashback_amount" id="woo_product_cat_cashback_amount" value="" placeholder="">
            </div>
            <?php
        }

        /**
         * Display product category wise cashback field.
         */
        public function edit_product_cat_cashback_field( $term) {
            $cashback_type = get_term_meta( $term->term_id, '_woo_cashback_type', true );
            $cashback_amount = get_term_meta( $term->term_id, '_woo_cashback_amount', true );
            ?>
            <tr class="form-field">
                <th scope="row" valign="top"><?php _e( 'Cashback type', 'woo-wallet' ); ?></th>
                <td>
                    <select name="woo_product_cat_cashback_type" id="woo_product_cat_cashback_type">
                        <option value="percent" <?php selected( $cashback_type, 'percent' ); ?>><?php _e( 'Percentage', 'woo-wallet' ); ?></option>
                        <option value="fixed" <?php selected( $cashback_type, 'fixed' ); ?>><?php _e( 'Fixed', 'woo-wallet' ); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" valign="top"><?php _e( 'Cashback Amount', 'woo-wallet' ); ?></th>
                <td><input type="number" step="0.01" name="woo_product_cat_cashback_amount" id="woo_product_cat_cashback_amount" value="<?php echo $cashback_amount; ?>" placeholder=""></td>
            </tr>
            <?php
        }

        /**
         * Save cashback field on category save.
         * @param int $term_id
         * @param int $tt_id
         * @param string $taxonomy
         */
        public function save_product_cashback_field( $term_id, $tt_id = '', $taxonomy = '' ) {
            if ( 'product_cat' === $taxonomy) {
                if ( isset( $_POST['woo_product_cat_cashback_type'] ) ) {
                    update_term_meta( $term_id, '_woo_cashback_type', esc_attr( $_POST['woo_product_cat_cashback_type'] ) );
                }
                if ( isset( $_POST['woo_product_cat_cashback_amount'] ) ) {
                    update_term_meta( $term_id, '_woo_cashback_amount', sanitize_text_field( $_POST['woo_product_cat_cashback_amount'] ) );
                }
            }
        }

        /**
         * Adds wallet endpoint to WooCommerce endpoints menu option.
         * @param array $endpoints
         * @return array
         */
        public function woocommerce_custom_nav_menu_items( $endpoints) {
            $endpoints[get_option( 'woocommerce_woo_wallet_endpoint', 'woo-wallet' )] = __( 'My Wallet', 'woo-wallet' );
            return $endpoints;
        }

        /**
         * Add column
         * @param  array $columns
         * @return array
         */
        public function manage_users_columns( $columns) {
            if (current_user_can(get_wallet_user_capability() ) ) {
                $columns['current_wallet_balance'] = __( 'Wallet Balance', 'woo-wallet' );
            }
            return $columns;
        }

        /**
         * Column value
         * @param  string $value
         * @param  string $column_name
         * @param  int $user_id
         * @return string
         */
        public function manage_users_custom_column( $value, $column_name, $user_id ) {
            if ( $column_name === 'current_wallet_balance' ) {
                return sprintf( '<a href="%s" title="%s">%s</a>', admin_url( '?page=woo-wallet-transactions&user_id=' . $user_id ), __( 'View details', 'woo-wallet' ), woo_wallet()->wallet->get_wallet_balance( $user_id ) );
            }
            return $value;
        }
        /**
         * Add TeraWallet screen ids to WooCommerce
         * @param array $screen_ids
         * @return array
         */
        public function woocommerce_screen_ids_callback( $screen_ids ) {
            $woo_wallet_screen_id = sanitize_title( __( 'TeraWallet', 'woo-wallet' ) );
            $screen_ids[] = "{$woo_wallet_screen_id}_page_woo-wallet-actions";
            return $screen_ids;
        }
        /**
         * Add refund button to WooCommerce order page.
         * @param int $item_id
         * @param Object $item
         */
        public function woocommerce_after_order_fee_item_name_callback( $item_id, $item ){
            global $post, $thepostid;
            
            if( !is_partial_payment_order_item( $item_id, $item) ){
                return;
            }
            if ( ! is_int( $thepostid ) ) {
                    $thepostid = $post->ID;
            }
            
            $order_id = $thepostid;
            if ( get_post_meta($order_id, '_woo_wallet_partial_payment_refunded', true) ) {
                echo '<small class="refunded">' . __('Refunded', 'woo-wallet') . '</small>';
            } else{
                echo '<button type="button" class="button refund-partial-payment">'.__( 'Refund', 'woo-wallet').'</button>';
            }
        }
        /**
         * Admin new order add cashback.
         * @param int $order_id
         */
        public function woocommerce_new_order($order_id){
            woo_wallet()->cashback->calculate_cashback(false, $order_id, true);
        }

        /**
         * Add order action for recalculate order cashback
         * @param array $order_actions
         * @return array
         */
        public function woocommerce_order_actions($order_actions){
            $order_actions['recalculate_order_cashback'] = __( 'Recalculate order cashback', 'woo-wallet');
            return $order_actions;
        }
        /**
         * Recalculate and send order cashback.
         * @param WC_Order $order
         */
        public function recalculate_order_cashback($order){
            $cashback_amount = woo_wallet()->cashback->calculate_cashback(false, $order->get_id(), true);
            if (in_array($order->get_status(), apply_filters('wallet_cashback_order_status', woo_wallet()->settings_api->get_option('process_cashback_status', '_wallet_settings_credit', array('processing', 'completed'))))) {
                woo_wallet()->wallet->wallet_cashback($order->get_id());
                if($transaction_id = get_post_meta($order->get_id(), '_general_cashback_transaction_id', true)){
                    update_wallet_transaction($transaction_id, $order->get_customer_id(), array('amount' => $cashback_amount), array('%f'));
                }
            }
        }

        public function show_promotions() {
            if ( !current_user_can('manage_options') ) {
                return;
            }
            if( get_option('_woo_wallet_promotion_dismissed') ){
                return;
            }
            ?>
            <div class="notice woo-wallet-promotional-notice">
                <div class="thumbnail">
                    <img src="//plugins.svn.wordpress.org/woo-wallet/assets/icon-256x256.png" alt="Obtain Superpowers to get the best out of TeraWallet" class="">
                </div>
                <div class="content">
                    <h2 class=""><?php _e('Obtain Superpowers to get the best out of TeraWallet', 'woo-wallet'); ?></h2>
                    <p><?php _e('Use superpowers to stand above the crowd. our high-octane add-ons are designed to boost your store wallet features.', 'woo-wallet'); ?></p>
                    <a href="https://woowallet.in/extensions/?utm_source=woo-wallet-plugin&amp;utm_medium=banner&amp;utm_content=add-on&amp;utm_campaign=extensions" class="button button-primary promo-btn" target="_blank"><?php _e('Learn More', 'woo-wallet'); ?> →</a>
                </div>
                <span class="prmotion-close-icon dashicons dashicons-no-alt"></span>
                <div class="clear"></div>
            </div>
            <style>
                .woo-wallet-promotional-notice {
                    padding: 20px;
                    box-sizing: border-box;
                    position: relative;
                }

                .woo-wallet-promotional-notice .prmotion-close-icon{
                    position: absolute;
                    top: 20px;
                    right: 20px;
                    cursor: pointer;
                }

                .woo-wallet-promotional-notice .thumbnail {
                    width: 9.3%;
                    float: left;
                }

                .woo-wallet-promotional-notice .thumbnail img{
                    width: 100%;
                    height: auto;
                    box-shadow: 0px 0px 25px #bbbbbb;
                    margin-right: 20px;
                    box-sizing: border-box;
                    border-radius: 10px;
                }

                .woo-wallet-promotional-notice .content {
                    float:left;
                    margin-left: 20px;
                    width: 75%;
                }

                .woo-wallet-promotional-notice .content h2 {
                    margin: 3px 0px 5px;
                    font-size: 17px;
                    font-weight: bold;
                    color: #555;
                    line-height: 25px;
                }

                .woo-wallet-promotional-notice .content p {
                    font-size: 14px;
                    text-align: justify;
                    color: #666;
                    margin-bottom: 10px;
                }

                .woo-wallet-promotional-notice .content a {
                    border: none;
                    box-shadow: none;
                    height: 31px;
                    line-height: 30px;
                    border-radius: 3px;
                    background: #a46396;
                    text-shadow: none;
                    padding: 0px 20px;
                    text-align: center;
                }

            </style>
            <script type='text/javascript'>
                jQuery(document).ready(function($){
                    $('body').on('click', '.woo-wallet-promotional-notice span.prmotion-close-icon', function(e) {
                        e.preventDefault();

                        var self = $(this);

                        wp.ajax.send( 'woo-wallet-dismiss-promotional-notice', {
                            data: {
                                nonce: '<?php echo esc_attr( wp_create_nonce( 'woo_wallet_admin' ) ); ?>'
                            },
                            complete: function( resp ) {
                                self.closest('.woo-wallet-promotional-notice').fadeOut(200);
                            }
                        } );
                    });
                });
            </script>
            <?php
        }

    }

}
Woo_Wallet_Admin::instance();
