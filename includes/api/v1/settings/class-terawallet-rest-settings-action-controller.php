<?php
/**
 * POST /terawallet/v1/settings/action  (deprecated shim)
 *
 * Translates the old per-action {action_id, values} payload into a flat
 * section save via POST /terawallet/v1/settings/section. Kept for one
 * minor cycle so external callers that hit this URL keep working.
 *
 * @deprecated 1.6.1 Use POST /terawallet/v1/settings/section instead.
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Deprecated action settings shim controller.
 *
 * @deprecated 1.6.1 Use TeraWallet_REST_Settings_Section_Controller instead.
 */
class TeraWallet_REST_Settings_Action_Controller extends TeraWallet_REST_Settings_Controller_Base {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'terawallet/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'settings/action';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_action' ),
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
	}

	/**
	 * POST /terawallet/v1/settings/action — deprecated shim.
	 *
	 * Translates {action_id, values} into POST /terawallet/v1/settings/section
	 * with flattened keys and emits _doing_it_wrong().
	 *
	 * @param WP_REST_Request $request Full request details.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_action( WP_REST_Request $request ) {
		_doing_it_wrong(
			'POST /terawallet/v1/settings/action',
			esc_html__( 'Deprecated since 1.6.1. Use POST /terawallet/v1/settings/section with section_id=_wallet_settings_actions and namespaced field keys ({action_id}__{field_key}).', 'woo-wallet' ),
			'1.6.1'
		);

		$action_id = sanitize_key( $request->get_param( 'action_id' ) );
		$values    = (array) $request->get_param( 'values' );

		$actions = $this->get_actions_index();
		if ( ! isset( $actions[ $action_id ] ) ) {
			return new WP_Error(
				'woo_wallet_invalid_action',
				__( 'Invalid action ID.', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		$flattened = array();
		foreach ( $values as $key => $value ) {
			$flattened[ $action_id . '__' . sanitize_key( $key ) ] = $value;
		}

		$proxy = new WP_REST_Request( 'POST', '/terawallet/v1/settings/section' );
		$proxy->set_param( 'section_id', '_wallet_settings_actions' );
		$proxy->set_param( 'values', $flattened );

		$response = rest_do_request( $proxy );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data          = $response->get_data();
		$merged_values = isset( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array();
		$action_values = array();
		$prefix        = $action_id . '__';
		$prefix_len    = strlen( $prefix );
		foreach ( $merged_values as $key => $value ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				$action_values[ substr( $key, $prefix_len ) ] = $value;
			}
		}

		return rest_ensure_response(
			array(
				'action_id' => $action_id,
				'values'    => $action_values,
			)
		);
	}

	/**
	 * Resolve the WOO_Wallet_Actions registry keyed by action id.
	 *
	 * @return WooWalletAction[]
	 */
	private function get_actions_index() {
		if ( ! class_exists( 'WOO_Wallet_Actions' ) ) {
			return array();
		}
		$actions = WOO_Wallet_Actions::instance()->actions;
		return is_array( $actions ) ? $actions : array();
	}
}
