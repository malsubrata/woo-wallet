<?php
/**
 * Legacy REST API: wc/v3/wallet/settings/*
 *
 * Thin proxy that re-dispatches every request to the canonical
 * terawallet/v1/settings/* routes and stamps responses with deprecation
 * headers. Contains no business logic. Will be removed in plugin 2.0.
 *
 * Canonical replacements:
 *   GET  /wc/v3/wallet/settings              → GET  /terawallet/v1/settings
 *   POST /wc/v3/wallet/settings/section      → POST /terawallet/v1/settings/section
 *   POST /wc/v3/wallet/settings/action       → POST /terawallet/v1/settings/action
 *   POST /wc/v3/wallet/settings/js-section   → POST /terawallet/v1/settings/js-section
 *
 * @deprecated 1.7.0 Use terawallet/v1/settings/* instead.
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Legacy settings controller — proxy to terawallet/v1/settings/*.
 *
 * @deprecated 1.7.0 Use TeraWallet_REST_Settings_V1_Controller and related classes.
 */
class TeraWallet_REST_Settings_Controller extends TeraWallet_REST_Controller_Base {

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
	protected $rest_base = 'wallet/settings';

	/**
	 * Register legacy wc/v3 settings routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'proxy_get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/section',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'proxy_save_section' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'section_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'values'     => array(
							'required'          => true,
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/action',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'proxy_save_action' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'action_id' => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'values'    => array(
							'required'          => true,
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/js-section',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'proxy_save_js_section' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'section_id'    => array(
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_key',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'fields_schema' => array(
							'required'          => true,
							'type'              => 'array',
							'validate_callback' => 'rest_validate_request_arg',
						),
						'values'        => array(
							'required'          => true,
							'type'              => 'object',
							'validate_callback' => 'rest_validate_request_arg',
						),
					),
				),
			)
		);
	}

	/**
	 * Capability check shared by all settings routes.
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
	 * GET /wc/v3/wallet/settings → GET /terawallet/v1/settings
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_get_settings( WP_REST_Request $request ) {
		$proxy = new WP_REST_Request( 'GET', '/terawallet/v1/settings' );
		return $this->dispatch_with_headers( $proxy, '/terawallet/v1/settings' );
	}

	/**
	 * POST /wc/v3/wallet/settings/section → POST /terawallet/v1/settings/section
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_save_section( WP_REST_Request $request ) {
		$proxy = new WP_REST_Request( 'POST', '/terawallet/v1/settings/section' );
		$proxy->set_param( 'section_id', $request->get_param( 'section_id' ) );
		$proxy->set_param( 'values', $request->get_param( 'values' ) );
		return $this->dispatch_with_headers( $proxy, '/terawallet/v1/settings/section' );
	}

	/**
	 * POST /wc/v3/wallet/settings/action → POST /terawallet/v1/settings/action
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_save_action( WP_REST_Request $request ) {
		$proxy = new WP_REST_Request( 'POST', '/terawallet/v1/settings/action' );
		$proxy->set_param( 'action_id', $request->get_param( 'action_id' ) );
		$proxy->set_param( 'values', $request->get_param( 'values' ) );
		return $this->dispatch_with_headers( $proxy, '/terawallet/v1/settings/action' );
	}

	/**
	 * POST /wc/v3/wallet/settings/js-section → POST /terawallet/v1/settings/js-section
	 *
	 * @param WP_REST_Request $request Incoming request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function proxy_save_js_section( WP_REST_Request $request ) {
		$proxy = new WP_REST_Request( 'POST', '/terawallet/v1/settings/js-section' );
		$proxy->set_param( 'section_id', $request->get_param( 'section_id' ) );
		$proxy->set_param( 'fields_schema', $request->get_param( 'fields_schema' ) );
		$proxy->set_param( 'values', $request->get_param( 'values' ) );
		return $this->dispatch_with_headers( $proxy, '/terawallet/v1/settings/js-section' );
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
class_alias( 'TeraWallet_REST_Settings_Controller', 'WC_REST_TeraWallet_Settings_Controller' );
