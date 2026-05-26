<?php
/**
 * POST /terawallet/v1/settings/section
 *
 * Saves one settings section's values. Canonical replacement for
 * POST /wc/v3/wallet/settings/section.
 *
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings section write controller.
 */
class TeraWallet_REST_Settings_Section_Controller extends TeraWallet_REST_Settings_Controller_Base {

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
	protected $rest_base = 'settings/section';

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
					'callback'            => array( $this, 'save_section' ),
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
	}

	/**
	 * POST /terawallet/v1/settings/section
	 *
	 * @param WP_REST_Request $request Full request details.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_section( WP_REST_Request $request ) {
		$this->maybe_load_settings_class();

		$section_id = sanitize_key( $request->get_param( 'section_id' ) );
		$values     = (array) $request->get_param( 'values' );

		$settings_obj = new Woo_Wallet_Settings( woo_wallet()->settings_api );
		$sections     = $settings_obj->get_settings_sections();
		$section_ids  = wp_list_pluck( $sections, 'id' );

		if ( ! in_array( $section_id, $section_ids, true ) ) {
			return new WP_Error(
				'woo_wallet_invalid_section',
				__( 'Invalid section ID.', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		// Register side-effect callbacks (product/image/tax sync).
		$callback_name = "update_option_{$section_id}_callback";
		if ( method_exists( $settings_obj, $callback_name ) ) {
			add_action( "update_option_{$section_id}", array( $settings_obj, $callback_name ), 10, 3 );
		}

		$all_fields     = $settings_obj->get_settings_fields();
		$all_fields     = apply_filters( 'woo_wallet_settings_fields', $all_fields );
		$section_fields = isset( $all_fields[ $section_id ] ) ? $all_fields[ $section_id ] : array();
		$field_map      = array();
		foreach ( $section_fields as $field ) {
			$field_map[ $field['name'] ] = $field;
		}

		$is_actions_section = '_wallet_settings_actions' === $section_id;
		$actions_index      = $is_actions_section ? $this->get_actions_index() : array();

		$sanitized = array();
		foreach ( $values as $key => $value ) {
			$skey  = sanitize_key( $key );
			$field = isset( $field_map[ $skey ] ) ? $field_map[ $skey ] : array( 'type' => 'text' );
			$type  = isset( $field['type'] ) ? $field['type'] : 'text';

			if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
				$sanitized[ $skey ] = call_user_func( $field['sanitize_callback'], $value );
				continue;
			}

			if ( $is_actions_section ) {
				list( $action_id, $field_key ) = $this->split_action_field_name( $skey );
				if ( $action_id && isset( $actions_index[ $action_id ] ) ) {
					$action     = $actions_index[ $action_id ];
					$method     = 'validate_' . $type . '_field';
					$bool_yesno = ! empty( $field['bool_format'] ) && 'yes_no' === $field['bool_format'];

					if ( 'checkbox' === $type && $bool_yesno ) {
						$sanitized[ $skey ] = $this->coerce_truthy( $value ) ? 'yes' : 'no';
						continue;
					}
					if ( method_exists( $action, $method ) ) {
						$sanitized[ $skey ] = $action->$method( $field_key, $value );
						continue;
					}
				}
			}

			if ( 'checkbox' === $type ) {
				$sanitized[ $skey ] = $this->coerce_truthy( $value ) ? 'on' : 'off';
			} elseif ( 'number' === $type ) {
				$sanitized[ $skey ] = is_numeric( $value ) ? $value : '';
			} elseif ( 'attachment' === $type ) {
				$sanitized[ $skey ] = absint( $value );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $skey ] = array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
			} else {
				$sanitized[ $skey ] = sanitize_text_field( (string) $value );
			}
		}

		update_option( $section_id, $sanitized );

		if ( $is_actions_section ) {
			foreach ( $actions_index as $action ) {
				$action->init_settings();
			}
		}

		$response_values = get_option( $section_id, array() );
		if ( $is_actions_section && is_array( $response_values ) ) {
			$response_values = $settings_obj->prepare_actions_values_for_react( $response_values );
		}

		return rest_ensure_response(
			array(
				'section_id' => $section_id,
				'values'     => $response_values,
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

	/**
	 * Split a flattened action field name into [action_id, field_key].
	 *
	 * @param string $name Flattened field name.
	 * @return array{0: ?string, 1: string}
	 */
	private function split_action_field_name( $name ) {
		$actions = $this->get_actions_index();
		foreach ( $actions as $action_id => $action ) {
			$prefix = $action_id . '__';
			if ( 0 === strpos( $name, $prefix ) ) {
				return array( $action_id, substr( $name, strlen( $prefix ) ) );
			}
		}
		return array( null, $name );
	}

	/**
	 * Coerce common truthy values from React checkbox payloads.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	private function coerce_truthy( $value ) {
		return in_array( $value, array( 'on', 'yes', true, 1, '1' ), true );
	}
}
