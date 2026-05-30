<?php
/**
 * Abstract base for all TeraWallet settings REST controllers.
 *
 * Provides the shared capability check for the settings surface
 * (GET /terawallet/v1/settings and all POST /settings/* routes).
 *
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings controller base.
 */
abstract class TeraWallet_REST_Settings_Controller_Base extends TeraWallet_REST_Admin_Controller_Base {

	/**
	 * Check that the current user can manage wallet settings.
	 *
	 * Uses get_wallet_user_capability() rather than the generic
	 * manage_woocommerce so site owners can narrow the capability.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( get_wallet_user_capability() ) ) {
			return new WP_Error(
				'woo_wallet_rest_cannot_manage_settings',
				__( 'Sorry, you are not allowed to manage wallet settings.', 'woo-wallet' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * Load the settings class if we are outside admin context (e.g. REST request).
	 */
	protected function maybe_load_settings_class() {
		if ( ! class_exists( 'Woo_Wallet_Settings' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php';
		}
	}
}
