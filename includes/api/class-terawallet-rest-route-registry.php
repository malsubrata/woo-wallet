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
		 * Instantiate and register routes for every known controller.
		 *
		 * Controllers are skipped (not fataled) when their class does not
		 * exist, so a partial rollout never breaks REST init for other routes.
		 */
		public static function register_all() {
			// Canonical controllers (terawallet/v1).
			$canonical = array(
				// me/*
				'TeraWallet_REST_Me_Controller',
				'TeraWallet_REST_Me_Balance_Controller',
				'TeraWallet_REST_Me_Transactions_Controller',
				'TeraWallet_REST_Me_Topup_Controller',
				'TeraWallet_REST_Me_Transfer_Controller',
				'TeraWallet_REST_Me_Referrals_Controller',
				'TeraWallet_REST_Me_Cashback_Rules_Controller',
				// public/*
				'TeraWallet_REST_Public_Settings_Controller',
				// admin/*
				'TeraWallet_REST_Admin_Transactions_Controller',
				'TeraWallet_REST_Admin_Users_Controller',
				'TeraWallet_REST_Admin_Transfer_Controller',
				// settings/*
				'TeraWallet_REST_Settings_V1_Controller',
				'TeraWallet_REST_Settings_Section_Controller',
				'TeraWallet_REST_Settings_Action_Controller',
				'TeraWallet_REST_Settings_Js_Section_Controller',
				// system/*
				'TeraWallet_REST_V1_Multicurrency_Controller',
			);

			// Legacy controllers (wc/v3) — retained for back-compat.
			$legacy = array(
				'TeraWallet_REST_Transactions_Controller',
				'TeraWallet_REST_Settings_Controller',
				'TeraWallet_REST_Multicurrency_Controller',
			);

			foreach ( array_merge( $canonical, $legacy ) as $class ) {
				if ( class_exists( $class ) ) {
					( new $class() )->register_routes();
				}
			}
		}
	}
}
