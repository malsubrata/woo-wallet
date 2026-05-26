<?php
/**
 * TeraWallet REST Route Registry.
 *
 * Central dispatch point that instantiates and registers every REST
 * controller. The bootstrapper (`WooWallet_API`) loads all controller files
 * via `rest_api_includes()` and then calls `TeraWallet_REST_Route_Registry::register_all()`
 * once — no controller file needs to know about any other.
 *
 * Adding a new controller: load its file in `WooWallet_API::rest_api_includes()`
 * and add its class name to the appropriate array below.
 *
 * @package StandaleneTech
 * @since   1.6.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Route_Registry' ) ) {

	/**
	 * Central registry for all TeraWallet REST controllers.
	 */
	class TeraWallet_REST_Route_Registry {

		/**
		 * Canonical terawallet/v1 controller class names.
		 *
		 * @var string[]
		 */
		private static $canonical_controllers = array();

		/**
		 * Legacy wc/v3 controller class names.
		 *
		 * @var string[]
		 */
		private static $legacy_controllers = array();

		/**
		 * Instantiate and register routes for every known controller.
		 *
		 * Controllers are skipped (not fataled) when their class does not
		 * exist, so a partial rollout never breaks REST init for other routes.
		 */
		public static function register_all() {
			// Canonical controllers (terawallet/v1).
			$canonical = array(
				'TeraWallet_REST_Me_Controller',
				'TeraWallet_REST_Me_Balance_Controller',
				'TeraWallet_REST_Me_Transactions_Controller',
				'TeraWallet_REST_Me_Topup_Controller',
				'TeraWallet_REST_Me_Transfer_Controller',
				'TeraWallet_REST_Me_Referrals_Controller',
				'TeraWallet_REST_Me_Cashback_Rules_Controller',
				'TeraWallet_REST_Public_Settings_Controller',
				'TeraWallet_REST_Admin_Transactions_Controller',
				'TeraWallet_REST_Admin_Users_Controller',
				'TeraWallet_REST_Admin_Transfer_Controller',
			);

			// Legacy controllers (wc/v3) — retained for back-compat.
			$legacy = array(
				'TeraWallet_REST_Transactions_Controller',
				'TeraWallet_REST_Settings_Controller',
				'TeraWallet_REST_Multicurrency_Controller',
			);

			self::$canonical_controllers = $canonical;
			self::$legacy_controllers    = $legacy;

			foreach ( array_merge( $canonical, $legacy ) as $class ) {
				if ( class_exists( $class ) ) {
					( new $class() )->register_routes();
				}
			}
		}
	}
}
