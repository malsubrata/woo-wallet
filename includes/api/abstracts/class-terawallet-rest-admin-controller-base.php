<?php
/**
 * Abstract base controller for TeraWallet admin REST endpoints.
 *
 * Extends the shared base with the `terawallet/v1` namespace and default
 * `manage_woocommerce` permission callbacks. Admin controllers that need a
 * different context string (e.g. 'edit' instead of 'create' for writes)
 * can override `permissions_write()` in the concrete class.
 *
 * @package StandaleneTech
 * @since   1.6.3
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Admin_Controller_Base' ) ) {

	/**
	 * Base controller for TeraWallet admin (terawallet/v1) REST endpoints.
	 */
	abstract class TeraWallet_REST_Admin_Controller_Base extends TeraWallet_REST_Controller_Base {

		/**
		 * REST API namespace for all admin endpoints.
		 *
		 * @var string
		 */
		protected $namespace = 'terawallet/v1';

		/**
		 * Permission callback for read/list routes.
		 *
		 * Delegates to check_capability('read') which requires manage_woocommerce
		 * and passes through the woo_wallet_rest_check_permissions filter.
		 *
		 * @param WP_REST_Request $request The request being authorized.
		 * @return true|WP_Error
		 */
		public function permissions_read( $request ) {
			return $this->check_capability( 'read', $request );
		}

		/**
		 * Permission callback for state-changing routes (create/update/delete).
		 *
		 * Delegates to check_capability('create') which requires manage_woocommerce.
		 * Concrete controllers may override this to use a different context string
		 * (e.g. 'edit') if the route semantics warrant it.
		 *
		 * @param WP_REST_Request $request The request being authorized.
		 * @return true|WP_Error
		 */
		public function permissions_write( $request ) {
			return $this->check_capability( 'create', $request );
		}
	}
}
