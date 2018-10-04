<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

abstract class WooWalletAction extends WC_Settings_API {

    /**
     * Yes or no based on whether the method is enabled.
     *
     * @var string
     */
    public $enabled = 'no';

    /**
     * Gateway title.
     * @var string
     */
    public $action_title = '';

    /**
     * Gateway ID
     * @var string 
     */
    public $id = '';

    /**
     * Action description
     * @var string 
     */
    public $description = '';

    /**
     * The plugin ID. Used for option names.
     *
     * @var string
     */
    public $plugin_id = 'woo_wallet_';

    /**
     * Return the title for admin screens.
     * @return string
     */
    public function get_action_title() {
        return apply_filters( 'woo_wallet_gateway_action_title', $this->action_title, $this );
    }

    /**
     * Return the id for admin screens.
     * @return string
     */
    public function get_action_id() {
        return $this->id;
    }

    public function get_action_description() {
        return apply_filters( 'woo_wallet_action_description', $this->description, $this );
    }

    public function is_enabled() {
        return 'yes' === $this->enabled ? true : false;
    }

    public function admin_options() {
        if ( $this->get_post_data() ) {
            parent::process_admin_options();
            add_settings_error( $this->id, '200', __( 'Your settings have been saved.', 'woo-wallet' ), 'updated' );
        }
        echo '<h2>' . esc_html( $this->get_action_title() );
        wc_back_link( __( 'Return to actions', 'woo-wallet' ), admin_url( 'admin.php?page=woo-wallet-actions' ) );
        settings_errors();
        echo '</h2>';
        echo wp_kses_post( wpautop( $this->get_action_description() ) );
        parent::admin_options();
    }

    /**
     * Init settings for gateways.
     */
    public function init_settings() {
        parent::init_settings();
        $this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
    }

}
