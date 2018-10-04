<?php

/**
 * Woo Wallet settings
 *
 * @author Subrata Mal
 */
if ( ! class_exists( 'Woo_Wallet_Extensions_Settings' ) ):

    class Woo_Wallet_Extensions_Settings {
        /* setting api object */

        private $settings_api;

        /**
         * Class constructor
         * @param object $settings_api
         */
        public function __construct( $settings_api) {
            $this->settings_api = $settings_api;
            add_action( 'admin_init', array( $this, 'plugin_settings_page_init' ) );
            add_action( 'admin_menu', array( $this, 'admin_menu' ), 65);
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
            add_action( 'woo_wallet_form_bottom__wallet_settings_extensions_general', array( $this, 'display_extensions' ) );
        }

        /**
         * wc wallet menu
         */
        public function admin_menu() {
            add_submenu_page( 'woo-wallet', __( 'Extensions', 'woo-wallet' ), __( 'Extensions', 'woo-wallet' ), 'manage_woocommerce', 'woo-wallet-extensions', array( $this, 'plugin_page' ) );
        }

        /**
         * admin init 
         */
        public function plugin_settings_page_init() {
            //set the settings
            $this->settings_api->set_sections( $this->get_settings_sections() );
            foreach ( $this->get_settings_sections() as $section) {
                if (method_exists( $this, "update_option_{$section['id']}_callback" ) ) {
                    add_action( "update_option_{$section['id']}", array( $this, "update_option_{$section['id']}_callback" ), 10, 3);
                }
            }
            $this->settings_api->set_fields( $this->get_settings_fields() );
            //initialize settings
            $this->settings_api->admin_init();
        }

        /**
         * Enqueue scripts and styles
         */
        public function admin_enqueue_scripts() {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            $suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
            wp_register_script( 'woo-wallet-admin-settings', woo_wallet()->plugin_url() . '/assets/js/admin/admin-settings' . $suffix . '.js', array( 'jquery' ), WOO_WALLET_PLUGIN_VERSION);
            if (in_array( $screen_id, array( 'woowallet_page_woo-wallet-extensions' ) ) ) {
                wp_enqueue_style( 'dashicons' );
                wp_enqueue_style( 'wp-color-picker' );
                wp_enqueue_style( 'woo_wallet_admin_styles' );
                wp_add_inline_style( 'woo_wallet_admin_styles', 'tr.licence_key_nonce{ display:none; }' );
                wp_enqueue_media();
                wp_enqueue_script( 'wp-color-picker' );
                wp_enqueue_script( 'jquery' );
                wp_enqueue_script( 'woo-wallet-admin-settings' );
                $localize_param = array(
                    'screen_id' => $screen_id
                );
                wp_localize_script( 'woo-wallet-admin-settings', 'woo_wallet_admin_settings_param', $localize_param);
            }
        }

        /**
         * Setting sections
         * @return array
         */
        public function get_settings_sections() {
            $sections = array(
                array(
                    'id' => '_wallet_settings_extensions_general',
                    'title' => __( 'Extensions', 'woo-wallet' ),
                    'icon' => 'dashicons-admin-plugins',
                )
            );
            return apply_filters( 'woo_wallet_extensions_settings_sections', $sections);
        }

        /**
         * Returns all the settings fields
         *
         * @return array settings fields
         */
        public function get_settings_fields() {
            $settings_fields = array(
            );
            return apply_filters( 'woo_wallet_extensions_settings_filds', $settings_fields);
        }

        /**
         * display plugin settings page
         */
        public function plugin_page() {
            echo '<div class="wrap wc_addons_wrap">';
            settings_errors();
            echo '<div class="wallet-settings-extensions-wrap">';
            $this->settings_api->show_navigation();
            $this->settings_api->show_forms();
            echo '</div>';
            echo '</div>';
        }

        public function display_extensions() {
            ?>
            <style type="text/css">
                div#_wallet_settings_extensions_general h2 {
                    display: none;
                }
            </style>
            <div class="addons-featured">
                <div class="addons-banner-block">
                    <h1>Obtain Superpowers to get the best out of WooWallet </h1>
                    <p>These power boosting extensions can unlock the ultimate potential for your site.</p>
                    <div class="addons-banner-block-items">
                        <div class="addons-banner-block-item">
                            <div class="addons-banner-block-item-icon">
                                <img class="addons-img" src="https://d30y9cdsu7xlg0.cloudfront.net/png/691726-200.png">
                            </div>
                            <div class="addons-banner-block-item-content">
                                <h3>WooWallet Withdrawal</h3>
                                <p>Let your users withdraw their WooWallet balance to bank and other digital accounts like PayPal with this awesome addon.</p>
                                <a target="_blank" class="addons-button addons-button-solid" href="https://woowallet.in/product/woo-wallet-withdrawal/">
                                    From: $49		</a>
                            </div>
                        </div>
                        <div class="addons-banner-block-item">
                            <div class="addons-banner-block-item-icon">
                                <img class="addons-img" src="https://d30y9cdsu7xlg0.cloudfront.net/png/24574-200.png">
                            </div>
                            <div class="addons-banner-block-item-content">
                                <h3>WooWallet Importer</h3>
                                <p>WooWallet importer addon enables you to modify the WooWallet balances of multiple or all users with just one CSV import, hassle free.</p>
                                <a target="_blank" class="addons-button addons-button-solid" href="https://woowallet.in/product/woo-wallet-importer/">
                                    From: $15		</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php

        }

    }

    endif;

new Woo_Wallet_Extensions_Settings(woo_wallet()->settings_api);
