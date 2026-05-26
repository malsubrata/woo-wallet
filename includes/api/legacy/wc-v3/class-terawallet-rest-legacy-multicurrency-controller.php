<?php
/**
 * Legacy REST API: wc/v3/wallet/multicurrency
 *
 * Thin proxy that re-dispatches every request to the canonical
 * terawallet/v1/multicurrency route and stamps responses with deprecation
 * headers. Contains no business logic. Will be removed in plugin 2.0.
 *
 * Canonical replacement:
 *   GET /wc/v3/wallet/multicurrency → GET /terawallet/v1/multicurrency
 *
 * @deprecated 1.7.0 Use terawallet/v1/multicurrency instead.
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Legacy multicurrency controller — proxy to terawallet/v1/multicurrency.
 *
 * @deprecated 1.7.0 Use TeraWallet_REST_V1_Multicurrency_Controller instead.
 */
class TeraWallet_REST_Multicurrency_Controller extends TeraWallet_REST_Controller_Base {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'wallet/multicurrency';

	/**
	 * Register legacy wc/v3 multicurrency route.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'proxy_get_state' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);
	}

	/**
	 * Capability check for the multicurrency route.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( get_wallet_user_capability() ) ) {
			return new WP_Error(
				'woo_wallet_rest_cannot_manage_multicurrency',
				__( 'Sorry, you are not allowed to inspect the wallet currency configuration.', 'woo-wallet' ),
				array( 'status' => rest_authorization_required_code() )
			);
		}
		return true;
	}

	/**
	 * GET /wc/v3/wallet/multicurrency → GET /terawallet/v1/multicurrency
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_get_state( WP_REST_Request $request ) {
		$proxy = new WP_REST_Request( 'GET', '/terawallet/v1/multicurrency' );
		return $this->dispatch_with_headers( $proxy, '/terawallet/v1/multicurrency' );
	}

	/**
	 * Dispatch an internal request and stamp deprecation headers on the response.
	 *
	 * @param WP_REST_Request $proxy     Internal request to dispatch.
	 * @param string          $successor Canonical path shown in X-TeraWallet-Successor.
	 * @return WP_REST_Response|WP_Error
	 */
	private function dispatch_with_headers( WP_REST_Request $proxy, $successor ) {
		$response = rest_do_request( $proxy );
		if ( $response instanceof WP_REST_Response ) {
			$response->header( 'X-TeraWallet-Deprecated', '1' );
			$response->header( 'X-TeraWallet-Successor', $successor );
		}
		return $response;
	}
}

// Back-compat alias for the pre-rename class name. Remove in 2.1.
class_alias( 'TeraWallet_REST_Multicurrency_Controller', 'WC_REST_TeraWallet_Multicurrency_Controller' );
