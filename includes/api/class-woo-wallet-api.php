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
			// Abstract bases (loaded first — controllers extend these).
			include_once __DIR__ . '/abstracts/class-terawallet-rest-controller-base.php';
			include_once __DIR__ . '/abstracts/class-terawallet-rest-me-controller-base.php';
			include_once __DIR__ . '/abstracts/class-terawallet-rest-admin-controller-base.php';

			// Central route registry.
			include_once __DIR__ . '/class-terawallet-rest-route-registry.php';

			// wc/v3/* — admin/server-to-server.
			include_once __DIR__ . '/Controllers/Version3/class-terawallet-rest-transactions-controller.php';
			include_once __DIR__ . '/Controllers/Version3/class-terawallet-rest-settings-controller.php';
			$multicurrency_controller = __DIR__ . '/Controllers/Version3/class-terawallet-rest-multicurrency-controller.php';
			if ( file_exists( $multicurrency_controller ) ) {
				include_once $multicurrency_controller;
			}

			// terawallet/v1/* — customer (React dashboard). Files are conditionally
			// included; missing files are skipped so partial PRs don't fatal.
			$me_dir = __DIR__ . '/v1/me/';
			foreach ( array(
				'class-terawallet-rest-me-controller.php',
				'class-terawallet-rest-me-balance-controller.php',
				'class-terawallet-rest-me-transactions-controller.php',
				'class-terawallet-rest-me-topup-controller.php',
				'class-terawallet-rest-me-transfer-controller.php',
				'class-terawallet-rest-me-referrals-controller.php',
				'class-terawallet-rest-me-cashback-rules-controller.php',
			) as $file ) {
				if ( file_exists( $me_dir . $file ) ) {
					include_once $me_dir . $file;
				}
			}

			// terawallet/v1/admin/* — admin DataView surface.
			$admin_dir = __DIR__ . '/v1/admin/';
			foreach ( array(
				'class-terawallet-rest-admin-transactions-controller.php',
				'class-terawallet-rest-admin-users-controller.php',
				'class-terawallet-rest-admin-transfer-controller.php',
			) as $file ) {
				if ( file_exists( $admin_dir . $file ) ) {
					include_once $admin_dir . $file;
				}
			}

			// terawallet/v1/public/* — unauthenticated/public endpoints.
			$public_dir = __DIR__ . '/v1/public/';
			foreach ( array(
				'class-terawallet-rest-public-settings-controller.php',
			) as $file ) {
				if ( file_exists( $public_dir . $file ) ) {
					include_once $public_dir . $file;
				}
			}
		}

		/**
		 * Register REST API routes.
		 *
		 * Files are loaded via rest_api_includes(), then the central registry
		 * dispatches to every controller in a single call.
		 *
		 * @since 1.2.5
		 */
		public function register_rest_routes() {
			$this->rest_api_includes();
			TeraWallet_REST_Route_Registry::register_all();
		}
	}

}
