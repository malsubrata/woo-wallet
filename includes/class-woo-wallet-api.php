<?php
/**
 * WooWallet REST API
 *
 * @author Subrata Mal <m.subrata1991@gmail.com>
 * @since 1.2.5
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'WooWallet_API' ) ) {
	/**
	 * Wallet API class
	 */
	class WooWallet_API {
		/**
		 * Class constructor.
		 */
		public function __construct() {
			// WP REST API.
			$this->rest_api_init();
		}

		/**
		 * Init WP REST API.
		 *
		 * @since 1.2.5
		 */
		private function rest_api_init() {
			// REST API was included starting WordPress 4.4.
			if ( ! class_exists( 'WP_REST_Server' ) ) {
				return;
			}

			// Init REST API routes.
			add_action( 'rest_api_init', array( $this, 'register_rest_routes' ), 10 );
		}

		/**
		 * Include REST API classes.
		 *
		 * @since 1.2.5
		 */
		private function rest_api_includes() {
			// Shared bases.
			include_once __DIR__ . '/rest-api/class-terawallet-rest-controller-base.php';
			include_once __DIR__ . '/rest-api/class-terawallet-rest-me-controller-base.php';

			// wc/v3/* — admin/server-to-server.
			include_once __DIR__ . '/rest-api/Controllers/Version3/class-terawallet-rest-transactions-controller.php';
			include_once __DIR__ . '/rest-api/Controllers/Version3/class-terawallet-rest-settings-controller.php';
			$multicurrency_controller = __DIR__ . '/rest-api/Controllers/Version3/class-terawallet-rest-multicurrency-controller.php';
			if ( file_exists( $multicurrency_controller ) ) {
				include_once $multicurrency_controller;
			}

			// terawallet/v1/* — customer (React dashboard). Files are conditionally
			// included; missing files are skipped so partial PRs don't fatal.
			$me_dir = __DIR__ . '/rest-api/Controllers/TeraWalletV1/';
			foreach ( array(
				'class-terawallet-rest-me-controller.php',
				'class-terawallet-rest-me-balance-controller.php',
				'class-terawallet-rest-me-transactions-controller.php',
				'class-terawallet-rest-me-topup-controller.php',
				'class-terawallet-rest-me-transfer-controller.php',
				'class-terawallet-rest-me-referrals-controller.php',
				'class-terawallet-rest-me-cashback-rules-controller.php',
				'class-terawallet-rest-public-settings-controller.php',
				// admin DataView surface.
				'class-terawallet-rest-admin-transactions-controller.php',
				'class-terawallet-rest-admin-users-controller.php',
				'class-terawallet-rest-admin-transfer-controller.php',
			) as $file ) {
				if ( file_exists( $me_dir . $file ) ) {
					include_once $me_dir . $file;
				}
			}
		}

		/**
		 * Register REST API routes.
		 *
		 * @since 1.2.5
		 */
		public function register_rest_routes() {
			$this->rest_api_includes();
			$controllers = array(
				// wc/v3 controllers.
				'TeraWallet_REST_Transactions_Controller',
				'TeraWallet_REST_Settings_Controller',
				'TeraWallet_REST_Multicurrency_Controller',
				// terawallet/v1 controllers (instantiated only if the class exists,
				// so a partial PR4 rollout doesn't crash REST init).
				'TeraWallet_REST_Me_Controller',
				'TeraWallet_REST_Me_Balance_Controller',
				'TeraWallet_REST_Me_Transactions_Controller',
				'TeraWallet_REST_Me_Topup_Controller',
				'TeraWallet_REST_Me_Transfer_Controller',
				'TeraWallet_REST_Me_Referrals_Controller',
				'TeraWallet_REST_Me_Cashback_Rules_Controller',
				'TeraWallet_REST_Public_Settings_Controller',
				// Admin DataView surface.
				'TeraWallet_REST_Admin_Transactions_Controller',
				'TeraWallet_REST_Admin_Users_Controller',
				'TeraWallet_REST_Admin_Transfer_Controller',
			);
			foreach ( $controllers as $controller ) {
				if ( ! class_exists( $controller ) ) {
					continue;
				}
				$woo_wallet_api = new $controller();
				$woo_wallet_api->register_routes();
			}
		}
	}

}
