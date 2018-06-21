<?php

/**
 * Woo Wallet settings
 *
 * @author Subrata Mal
 */
if (!class_exists('Woo_Wallet_Extensions_Settings')):

    class Woo_Wallet_Extensions_Settings {
        /* setting api object */

        private $settings_api;

        /**
         * Class constructor
         * @param object $settings_api
         */
        public function __construct($settings_api) {
            $this->settings_api = $settings_api;
            add_action('admin_init', array($this, 'plugin_settings_page_init'));
            add_action('admin_menu', array($this, 'admin_menu'), 65);
            add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
            add_action('woo_wallet_form_bottom__wallet_settings_extensions_general', array($this, 'display_extensions'));
        }

        /**
         * wc wallet menu
         */
        public function admin_menu() {
            add_submenu_page('woo-wallet', __('Extensions', 'woo-wallet'), __('Extensions', 'woo-wallet'), 'manage_woocommerce', 'woo-wallet-extensions', array($this, 'plugin_page'));
        }

        /**
         * admin init 
         */
        public function plugin_settings_page_init() {
            //set the settings
            $this->settings_api->set_sections($this->get_settings_sections());
            foreach ($this->get_settings_sections() as $section) {
                if (method_exists($this, "update_option_{$section['id']}_callback")) {
                    add_action("update_option_{$section['id']}", array($this, "update_option_{$section['id']}_callback"), 10, 3);
                }
            }
            $this->settings_api->set_fields($this->get_settings_fields());
            //initialize settings
            $this->settings_api->admin_init();
        }

        /**
         * Enqueue scripts and styles
         */
        public function admin_enqueue_scripts() {
            $screen = get_current_screen();
            $screen_id = $screen ? $screen->id : '';
            $suffix = defined('SCRIPT_DEBUG') && SCRIPT_DEBUG ? '' : '.min';
            wp_register_script('woo-wallet-admin-settings', woo_wallet()->plugin_url() . '/assets/js/admin/admin-settings' . $suffix . '.js', array('jquery'), WOO_WALLET_PLUGIN_VERSION);
            if (in_array($screen_id, array('woowallet_page_woo-wallet-extensions'))) {
                wp_enqueue_style('dashicons');
                wp_enqueue_style('wp-color-picker');
                wp_enqueue_style('woo_wallet_admin_styles');
                wp_enqueue_media();
                wp_enqueue_script('wp-color-picker');
                wp_enqueue_script('jquery');
                wp_enqueue_script('woo-wallet-admin-settings');
                $localize_param = array(
                    'screen_id' => $screen_id
                );
                wp_localize_script('woo-wallet-admin-settings', 'woo_wallet_admin_settings_param', $localize_param);
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
                    'title' => __('Extensions', 'woo-wallet'),
                    'icon' => 'dashicons-admin-generic',
                )
            );
            return apply_filters('woo_wallet_extensions_settings_sections', $sections);
        }

        /**
         * Returns all the settings fields
         *
         * @return array settings fields
         */
        public function get_settings_fields() {
            $settings_fields = array(
            );
            return apply_filters('woo_wallet_extensions_settings_filds', $settings_fields);
        }

        /**
         * display plugin settings page
         */
        public function plugin_page() {
            echo '<div class="wrap">';
            //echo '<h2 style="margin-bottom: 15px;">' . __('Extensions', 'woo-wallet') . '</h2>';
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
                .woo-wallet-extension-card {
                    box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2);
                    transition: 0.3s;
                    width: 30%;
                    float: left;
                    margin: 0 38px 0px 0;
                }
                
                .addon-img-container{
                        background: #f7f7f7;
                }

                .woo-wallet-extension-card:hover {
                    box-shadow: 0 8px 16px 0 rgba(0,0,0,0.2);
                }

                .woo-wallet-extension-card .container {
                    padding: 2px 16px 18px;
                }
            </style>

            <div class="woo-wallet-extension-card">
                <div class="addon-img-container">
                    <img src="//woowallet.in/wp-content/uploads/2018/06/if_Money_877024-1.png" alt="Avatar" style="width:100%">
                </div>
                <div class="container">
                    <h4><b>Woo Wallet withdrawal</b></h4> 
                    <p>Let users withdraw their fund to account via bank, PayPal or any other supported payment gateway's</p> 
                    <a href="" class="button-primary" target="_blank">From: $49</a>
                </div>
            </div>
            <div class="woo-wallet-extension-card">
                <div class="addon-img-container">
                <img src="http://placekitten.com/816/610" alt="Avatar" style="width:100%">
                </div>
                <div class="container">
                    <h4><b>Woo wallet importer</b></h4> 
                    <p>Architect & Engineer</p> 
                    <a href="" class="button-primary" target="_blank">From: $19</a>
                </div>
            </div>
            <?php

        }

    }

    endif;

new Woo_Wallet_Extensions_Settings(woo_wallet()->settings_api);
