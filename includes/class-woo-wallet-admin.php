<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}
if (!class_exists('Woo_Wallet_Admin')) {

    class Woo_Wallet_Admin {

        public $transaction_details_table = NULL;
        public $balance_details_table = NULL;

        /**
         * Class constructor
         */
        public function __construct() {
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'), 15);
            add_action('admin_menu', array($this, 'admin_menu'), 50);
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on') && 'product' === woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart')) {
                add_action('woocommerce_product_write_panel_tabs', array($this, 'woocommerce_product_write_panel_tabs'));
                add_action('woocommerce_product_data_panels', array($this, 'woocommerce_product_data_panels'));
                add_action('save_post_product', array($this, 'save_post_product'));
            }
            add_action('woocommerce_admin_order_totals_after_tax', array($this, 'add_wallet_partial_payment_amount'), 10, 1);

            add_action('woocommerce_coupon_options', array($this, 'add_coupon_option_for_cashback'));
            add_action('woocommerce_coupon_options_save', array($this, 'save_coupon_data'));

            add_filter('admin_footer_text', array($this, 'admin_footer_text'), 1);

            add_filter('woocommerce_account_settings', array($this, 'add_woocommerce_account_endpoint_settings'));
            if ('on' === woo_wallet()->settings_api->get_option('is_enable_cashback_reward_program', '_wallet_settings_credit', 'on') && 'product_cat' === woo_wallet()->settings_api->get_option('cashback_rule', '_wallet_settings_credit', 'cart')) {
                add_action('product_cat_add_form_fields', array($this, 'add_product_cat_cashback_field'));
                add_action('product_cat_edit_form_fields', array($this, 'edit_product_cat_cashback_field'));
                add_action('created_term', array($this, 'save_product_cashback_field'), 10, 3);
                add_action('edit_term', array($this, 'save_product_cashback_field'), 10, 3);
            }
        }

        /**
         * init admin menu
         */
        public function admin_menu() {
            $woo_wallet_menu_page_hook = add_menu_page(__('WooWallet', 'woo-wallet'), __('WooWallet', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet', array($this, 'wallet_page'), 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iaXNvLTg4NTktMSI/PjxzdmcgdmVyc2lvbj0iMS4xIiBpZD0iQ2FwYV8xIiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB4PSIwcHgiIHk9IjBweCIgdmlld0JveD0iMCAwIDMzNC44NzcgMzM0Ljg3NyIgc3R5bGU9ImVuYWJsZS1iYWNrZ3JvdW5kOm5ldyAwIDAgMzM0Ljg3NyAzMzQuODc3OyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PGc+PHBhdGggZD0iTTMzMy4xOTYsMTU1Ljk5OWgtMTYuMDY3VjgyLjA5YzAtMTcuNzE5LTE0LjQxNS0zMi4xMzQtMzIuMTM0LTMyLjEzNGgtMjEuNzYxTDI0MC45NjUsOS45MTdDMjM3LjU3MSwzLjc5OCwyMzEuMTEyLDAsMjI0LjEwNywwYy0zLjI2NSwwLTYuNTA0LDAuODQyLTkuMzY0LDIuNDI5bC04NS40NjQsNDcuNTI2SDMzLjgxNWMtMTcuNzE5LDAtMzIuMTM0LDE0LjQxNS0zMi4xMzQsMzIuMTM0djIyMC42NTNjMCwxNy43MTksMTQuNDE1LDMyLjEzNCwzMi4xMzQsMzIuMTM0aDI1MS4xOGMxNy43MTksMCwzMi4xMzQtMTQuNDE1LDMyLjEzNC0zMi4xMzR2LTY0LjgwMmgxNi4wNjdWMTU1Ljk5OXogTTI4NC45OTUsNjIuODA5YzkuODk3LDAsMTcuOTgyLDcuNTE5LDE5LjA2OCwxNy4xNGgtMjQuMTUybC05LjUyNS0xNy4xNEgyODQuOTk1eiBNMjIwLjk5NiwxMy42NjNjMy4wMTQtMS42OSw3LjA3LTAuNTA4LDguNzM0LDIuNDk0bDM1LjQ3Niw2My43ODZIMTAxLjc5OEwyMjAuOTk2LDEzLjY2M3ogTTMwNC4yNzUsMzAyLjc0MmMwLDEwLjYzLTguNjUxLDE5LjI4MS0xOS4yODEsMTkuMjgxSDMzLjgxNWMtMTAuNjMsMC0xOS4yODEtOC42NTEtMTkuMjgxLTE5LjI4MVY4Mi4wOWMwLTEwLjYzLDguNjUxLTE5LjI4MSwxOS4yODEtMTkuMjgxaDcyLjM1M0w3NS4zNDUsNzkuOTVIMzcuODMyYy0zLjU1NCwwLTYuNDI3LDIuODc5LTYuNDI3LDYuNDI3czIuODczLDYuNDI3LDYuNDI3LDYuNDI3aDE0LjM5NmgyMzQuODNoMTcuMjE3djYzLjIwMWgtNDYuOTk5Yy0yMS44MjYsMC0zOS41ODksMTcuNzY0LTM5LjU4OSwzOS41ODl2Mi43NjRjMCwyMS44MjYsMTcuNzY0LDM5LjU4OSwzOS41ODksMzkuNTg5aDQ2Ljk5OVYzMDIuNzQyeiBNMzIwLjM0MiwyMjUuMDg3aC0zLjIxM2gtNTkuODUzYy0xNC43NDMsMC0yNi43MzYtMTEuOTkyLTI2LjczNi0yNi43MzZ2LTIuNzY0YzAtMTQuNzQzLDExLjk5Mi0yNi43MzYsMjYuNzM2LTI2LjczNmg1OS44NTNoMy4yMTNWMjI1LjA4N3ogTTI3Ni45NjEsMTk3LjQ5N2MwLDcuODQxLTYuMzUsMTQuMTktMTQuMTksMTQuMTljLTcuODQxLDAtMTQuMTktNi4zNS0xNC4xOS0xNC4xOXM2LjM1LTE0LjE5LDE0LjE5LTE0LjE5QzI3MC42MTIsMTgzLjMwNiwyNzYuOTYxLDE4OS42NjIsMjc2Ljk2MSwxOTcuNDk3eiIvPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48Zz48L2c+PGc+PC9nPjxnPjwvZz48L3N2Zz4=', 59);
            add_action("load-$woo_wallet_menu_page_hook", array($this, 'add_woo_wallet_details'));
            $woo_wallet_menu_page_hook_add = add_submenu_page('', __('Woo Wallet', 'woo-wallet'), __('Woo Wallet', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet-add', array($this, 'add_balance_to_user_wallet'));
            add_action("load-$woo_wallet_menu_page_hook_add", array($this, 'add_woo_wallet_add_balance_option'));
            $woo_wallet_menu_page_hook_view = add_submenu_page('', __('Woo Wallet', 'woo-wallet'), __('Woo Wallet', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet-transactions', array($this, 'transaction_details_page'));
            add_action("load-$woo_wallet_menu_page_hook_view", array($this, 'add_woo_wallet_transaction_details_option'));
        }

        /**
         * Register and enqueue admin styles and scripts
         * @global type $post
         */
        public function admin_scripts() {
            global $post;
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            // register styles
            wp_register_style('woo_wallet_admin_styles', woo_wallet()->plugin_url() . '/assets/admin/css/balance-details' . $suffix . '.css', array(), WOO_WALLET_PLUGIN_VERSION);

            // Register scripts
            wp_register_script('woo_wallet_admin_product', woo_wallet()->plugin_url() . '/assets/admin/js/admin-product' . $suffix . '.js', array('jquery'), WOO_WALLET_PLUGIN_VERSION);
            wp_register_script('woo_wallet_admin_order', woo_wallet()->plugin_url() . '/assets/admin/js/admin-order' . $suffix . '.js', array('jquery', 'wc-admin-order-meta-boxes'), WOO_WALLET_PLUGIN_VERSION);

            if (in_array($screen_id, array('product', 'edit-product'))) {
                wp_enqueue_script('woo_wallet_admin_product');
                wp_localize_script('woo_wallet_admin_product', 'woo_wallet_admin_product_param', array('product_id' => get_wallet_rechargeable_product()->get_id(), 'is_hidden' => apply_filters('woo_wallet_hide_rechargeable_product', true)));
            }
            if (in_array($screen_id, array('shop_order'))) {
                $order = wc_get_order($post->ID);
                wp_enqueue_script('woo_wallet_admin_order');
                $order_localizer = array(
                    'order_id' => $post->ID,
                    'payment_method' => $order->get_payment_method(''),
                    'default_price' => wc_price(0),
                    'is_rechargeable_order' => is_wallet_rechargeable_order($order),
                    'i18n' => array(
                        'refund' => __('Refund', 'woo-wallet'),
                        'via_wallet' => __('Via wallet', 'woo-wallet')
                    )
                );
                wp_localize_script('woo_wallet_admin_order', 'woo_wallet_admin_order_param', $order_localizer);
            }
            if (in_array($screen_id, array('toplevel_page_woo-wallet'))) {
                wp_enqueue_style('woo_wallet_admin_styles');
            }
        }

        /**
         * Display user wallet details page
         */
        public function wallet_page() {
            ?>
            <div class="wrap">
                <h2><?php _e('Users wallet details', 'woo-wallet'); ?></h2>
                <form id="posts-filter" method="post">
                    <?php $this->balance_details_table->search_box(__('Search Users', 'woo-wallet'), 'search_id'); ?>
                    <?php $this->balance_details_table->display(); ?>
                </form>
                <div id="ajax-response"></div>
                <br class="clear"/>
            </div>
            <?php
        }

        /**
         * Admin add wallet balance form
         */
        public function add_balance_to_user_wallet() {
            $user_id = filter_input(INPUT_GET, 'user_id');
            $current_wallet_balance = 0;
            if ($user_id != NULL && !empty($user_id)) {
                $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance($user_id, '');
            }
            ?>
            <div class="wrap">
                <?php settings_errors(); ?>
                <h2><?php _e('Adjust Balance', 'woo-wallet'); ?> <a style="text-decoration: none;" href="<?php echo add_query_arg(array('page' => 'woo-wallet'), admin_url('admin.php')); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
                <p>
                    <?php
                    _e('Current wallet balance: ', 'woo-wallet');
                    echo wc_price($current_wallet_balance)
                    ?>
                </p>
                <form id="posts-filter" method="post">
                    <table class="form-table">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="balance_amount"><?php _e('Amount', 'woo-wallet'); ?></label></th>
                                <td>
                                    <input type="number" step="0.01" name="balance_amount" class="regular-text" />
                                    <p class="description"><?php _e('Enter Amount', 'woo-wallet'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="payment_type"><?php _e('Type', 'woo-wallet'); ?></label></th>
                                <td>
                                    <select class="regular-text" name="payment_type" id="payment_type">
                                        <option value="credit"><?php _e('Credit', 'woo-wallet'); ?></option>
                                        <option value="debit"><?php _e('Debit', 'woo-wallet'); ?></option>
                                    </select>
                                    <p class="description"><?php _e('Select payment type', 'woo-wallet'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="payment_description"><?php _e('Description', 'woo-wallet'); ?></label></th>
                                <td>
                                    <textarea name="payment_description" class="regular-text"></textarea>
                                    <p class="description"><?php _e('Enter Description', 'woo-wallet'); ?></p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <input type="hidden" name="user_id" value="<?php echo $user_id; ?>" />
                    <?php wp_nonce_field('wc-wallet-admin-add-balance', 'wc-wallet-admin-add-balance'); ?>
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
            $user_id = filter_input(INPUT_GET, 'user_id');
            $current_wallet_balance = 0;
            if ($user_id != NULL) {
                $current_wallet_balance = woo_wallet()->wallet->get_wallet_balance($user_id, '');
            }
            ?>
            <div class="wrap">
                <h2><?php _e('Transaction details', 'woo-wallet'); ?> <a style="text-decoration: none;" href="<?php echo add_query_arg(array('page' => 'woo-wallet'), admin_url('admin.php')); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
                <p><?php
                    _e('Current wallet balance: ', 'woo-wallet');
                    echo wc_price($current_wallet_balance)
                    ?></p>
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
            add_screen_option($option, $args);
            include_once( WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-balance-details.php' );
            $this->balance_details_table = new Woo_Wallet_Balance_Details();
            $this->balance_details_table->prepare_items();
        }

        /**
         * Handel admin add wallet balance
         */
        public function add_woo_wallet_add_balance_option() {
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['wc-wallet-admin-add-balance']) && wp_verify_nonce($_POST['wc-wallet-admin-add-balance'], 'wc-wallet-admin-add-balance')) {
                $transaction_id = NULL;
                $message = '';
                $user_id = filter_input(INPUT_POST, 'user_id');
                $amount = filter_input(INPUT_POST, 'balance_amount');
                $payment_type = filter_input(INPUT_POST, 'payment_type');
                $description = filter_input(INPUT_POST, 'payment_description');
                if ($user_id != NULL && !empty($user_id) && $amount != NULL && !empty($amount)) {
                    $amount = number_format($amount, 2, '.', '');
                    if ('credit' === $payment_type) {
                        $transaction_id = woo_wallet()->wallet->credit($user_id, $amount, $description);
                    } else if ('debit' === $payment_type) {
                        $transaction_id = woo_wallet()->wallet->debit($user_id, $amount, $description);
                    }
                    if (!$transaction_id) {
                        $message = __('An error occurred please try again', 'woo-wallet');
                    }
                } else {
                    $message = __('Please enter amount', 'woo-wallet');
                }
                if (!$transaction_id) {
                    add_settings_error('', '102', $message);
                } else {
                    wp_safe_redirect(add_query_arg(array('page' => 'woo-wallet'), admin_url('admin.php')));
                    exit();
                }
            }
        }

        /**
         * Transaction details page initialization
         */
        public function add_woo_wallet_transaction_details_option() {
            include_once( WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-transaction-details.php' );
            $this->transaction_details_table = new Woo_Wallet_Transaction_Details();
            $this->transaction_details_table->prepare_items();
        }

        /**
         * add wallet tab to product page
         */
        public function woocommerce_product_write_panel_tabs() {
            ?>
            <li class="wallet_tab">
                <a href="#wallet_data_tabs"> &nbsp;<?php _e('Cashback', 'woo-wallet'); ?></a>
            </li>
            <?php
        }

        /**
         * WooCommerce product tab content
         * @global object $post
         */
        public function woocommerce_product_data_panels() {
            global $post;
            ?>
            <div id="wallet_data_tabs" class="panel woocommerce_options_panel">
                <?php
                woocommerce_wp_select(array(
                    'id' => 'wcwp_cashback_type',
                    'label' => __('Cashback type', 'woo-wallet'),
                    'description' => __('Select cashback type percentage or fixed', 'woo-wallet'),
                    'options' => array('percent' => __('Percentage', 'woo-wallet'), 'fixed' => __('Fixed', 'woo-wallet')),
                    'value' => get_post_meta($post->ID, '_cashback_type', true)
                ));
                woocommerce_wp_text_input(array(
                    'id' => 'wcwp_cashback_amount',
                    'type' => 'number',
                    'label' => __('Cashback Amount', 'woo-wallet'),
                    'description' => __('Enter cashback amount', 'woo-wallet'),
                    'value' => get_post_meta($post->ID, '_cashback_amount', true)
                ));
                ?>
            </div>
            <?php
        }

        /**
         * Save post meta
         * @param int $post_ID
         */
        public function save_post_product($post_ID) {
            if (isset($_POST['wcwp_cashback_type'])) {
                update_post_meta($post_ID, '_cashback_type', $_POST['wcwp_cashback_type']);
            }
            if (isset($_POST['wcwp_cashback_amount'])) {
                update_post_meta($post_ID, '_cashback_amount', $_POST['wcwp_cashback_amount']);
            }
        }

        /**
         * Display partial payment amount in order page
         * @param type $order_id
         * @return type
         */
        public function add_wallet_partial_payment_amount($order_id) {
            $order = wc_get_order($order_id);
            if (!get_post_meta($order_id, '_via_wallet_payment', true)) {
                return;
            }
            $via_other_gateway = get_post_meta($order->get_id(), '_original_order_amount', true) - get_post_meta($order->get_id(), '_via_wallet_payment', true);
            ?>
            <tr>
                <td class="label"><?php _e('Via wallet', 'woo-wallet'); ?>:</td>
                <td width="1%"></td>
                <td class="via-wallet">
                    <?php echo wc_price(get_post_meta($order_id, '_via_wallet_payment', true), array('currency' => $order->get_currency())); ?>
                </td>
            </tr>
            <tr>
                <td class="label"><?php printf(__('Via %s', 'woo-wallet'), $order->get_payment_method_title()); ?>:</td>
                <td width="1%"></td>
                <td class="via-wallet">
                    <?php echo wc_price($via_other_gateway, array('currency' => $order->get_currency())); ?>
                </td>
            </tr>
            <?php
        }

        /**
         * Add setting to convert coupon to cashback.
         * @since 1.0.6
         */
        public function add_coupon_option_for_cashback() {
            woocommerce_wp_checkbox(array(
                'id' => '_is_coupon_cashback',
                'label' => __('Apply as cashback', 'woo-wallet'),
                'description' => __('Check this box if the coupon should apply as cashback.', 'woo-wallet'),
            ));
        }

        /**
         * Save coupon data
         * @param int $post_id
         * @since 1.0.6
         */
        public function save_coupon_data($post_id) {
            $_is_coupon_cashback = isset($_POST['_is_coupon_cashback']) ? 'yes' : 'no';
            update_post_meta($post_id, '_is_coupon_cashback', $_is_coupon_cashback);
        }

        /**
         * Add review link
         * @param string $footer_text
         * @return string
         */
        public function admin_footer_text($footer_text) {
            if (!current_user_can('manage_woocommerce')) {
                return $footer_text;
            }
            $current_screen = get_current_screen();
            $woo_wallet_pages = array('toplevel_page_woo-wallet', 'admin_page_woo-wallet-add', 'admin_page_woo-wallet-transactions', 'woowallet_page_woo-wallet-settings');
            if (isset($current_screen->id) && in_array($current_screen->id, $woo_wallet_pages)) {
                if (!get_option('woocommerce_wallet_admin_footer_text_rated')) {
                    $footer_text = sprintf(
                            __('If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'woo-wallet'), sprintf('<strong>%s</strong>', esc_html__('WooCommerce Wallet', 'woo-wallet')), '<a href="https://wordpress.org/support/plugin/woo-wallet/reviews?rate=5#new-post" target="_blank" class="wc-rating-link" data-rated="' . esc_attr__('Thanks :)', 'woo-wallet') . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
                    );
                    wc_enqueue_js("
					jQuery( 'a.wc-rating-link' ).click( function() {
						jQuery.post( '" . WC()->ajax_url() . "', { action: 'woocommerce_wallet_rated' } );
						jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) );
					});
				");
                } else {
                    $footer_text = __('Thank you using WooCommerce Wallet.', 'woo-wallet');
                }
            }
            return $footer_text;
        }

        /**
         * Wallet endpoins settings
         * @param array $settings
         * @return array
         */
        public function add_woocommerce_account_endpoint_settings($settings) {
            $walletendpoint_settings = array(
                array(
                    'title' => __('Wallet endpoints', 'woo-wallet'),
                    'type' => 'title',
                    'desc' => __('Endpoints are appended to your page URLs to handle specific actions on the accounts pages. They should be unique and can be left blank to disable the endpoint.', 'woo-wallet'),
                    'id' => 'wallet_endpoint_options'
                ),
                array(
                    'title' => __('My Wallet', 'woo-wallet'),
                    'desc' => __('Endpoint for the "My account &rarr; My Wallet" page.', 'woo-wallet'),
                    'id' => 'woocommerce_woo_wallet_endpoint',
                    'type' => 'text',
                    'default' => 'woo-wallet',
                    'desc_tip' => true,
                ),
                array(
                    'title' => __('Wallet Transactions', 'woo-wallet'),
                    'desc' => __('Endpoint for the "My account &rarr; View wallet transactions" page.', 'woo-wallet'),
                    'id' => 'woocommerce_woo_wallet_transactions_endpoint',
                    'type' => 'text',
                    'default' => 'woo-wallet-transactions',
                    'desc_tip' => true,
                ),
                array('type' => 'sectionend', 'id' => 'wallet_endpoint_options'),
            );
            $settings = array_merge($settings, $walletendpoint_settings);
            return $settings;
        }

        public function add_product_cat_cashback_field() {
            ?>
            <div class="form-field term-display-type-wrap">
                <label for="woo_product_cat_cashback_type"><?php _e('Cashback type', 'woo-wallet'); ?></label>
                <select name="woo_product_cat_cashback_type" id="woo_product_cat_cashback_type">
                    <option value="percent"><?php _e('Percentage', 'woo-wallet'); ?></option>
                    <option value="fixed"><?php _e('Fixed', 'woo-wallet'); ?></option>
                </select>
            </div>
            <div class="form-field term-display-type-wrap">
                <label for="woo_product_cat_cashback_amount"><?php _e('Cashback Amount', 'woo-wallet'); ?></label>
                <input type="number" name="woo_product_cat_cashback_amount" id="woo_product_cat_cashback_amount" value="" placeholder="">
            </div>
            <?php
        }

        public function edit_product_cat_cashback_field($term) {
            $cashback_type = get_woocommerce_term_meta($term->term_id, '_woo_cashback_type', true);
            $cashback_amount = get_woocommerce_term_meta($term->term_id, '_woo_cashback_amount', true);
            ?>
            <tr class="form-field">
                <th scope="row" valign="top"><?php _e('Cashback type', 'woo-wallet'); ?></th>
                <td>
                    <select name="woo_product_cat_cashback_type" id="woo_product_cat_cashback_type">
                        <option value="percent" <?php selected($cashback_type, 'percent'); ?>><?php _e('Percentage', 'woo-wallet'); ?></option>
                        <option value="fixed" <?php selected($cashback_type, 'fixed'); ?>><?php _e('Fixed', 'woo-wallet'); ?></option>
                    </select>
                </td>
            </tr>
            <tr class="form-field">
                <th scope="row" valign="top"><?php _e('Cashback Amount', 'woo-wallet'); ?></th>
                <td><input type="number" name="woo_product_cat_cashback_amount" id="woo_product_cat_cashback_amount" value="<?php echo $cashback_amount; ?>" placeholder=""></td>
            </tr>
            <?php
        }

        public function save_product_cashback_field($term_id, $tt_id = '', $taxonomy = '') {
            if ('product_cat' === $taxonomy) {
                if (isset($_POST['woo_product_cat_cashback_type'])) {
                    update_woocommerce_term_meta($term_id, '_woo_cashback_type', esc_attr($_POST['woo_product_cat_cashback_type']));
                }
                if (isset($_POST['woo_product_cat_cashback_amount'])) {
                    update_woocommerce_term_meta($term_id, '_woo_cashback_amount', sanitize_text_field($_POST['woo_product_cat_cashback_amount']));
                }
            }
        }

    }

}
new Woo_Wallet_Admin();
