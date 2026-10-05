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
			} elseif ( 'select' === $type && empty( $field['multiple'] ) && ! empty( $field['options'] ) ) {
				$sanitized[ $skey ] = WOO_Wallet_Helper::constrain_select_value( $value, $field );
			} elseif ( is_array( $value ) ) {
				$sanitized[ $skey ] = array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
			} else {
				$sanitized[ $skey ] = sanitize_text_field( (string) $value );
			}
		}

		if ( '_wallet_settings_general' === $section_id ) {
			$invalid = $this->validate_general_amounts( $sanitized, $field_map );
			if ( is_wp_error( $invalid ) ) {
				return $invalid;
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
	 * Reject General-section amounts that are impossible at runtime: negative
	 * amounts, min above max, and percentage charges above 100. Groups that
	 * are switched off are skipped — their fields are hidden, so an old value
	 * there must not block saving the rest of the page.
	 *
	 * @param array $values    Sanitized section values.
	 * @param array $field_map Field definitions keyed by name (for labels).
	 * @return true|WP_Error
	 */
	private function validate_general_amounts( array $values, array $field_map ) {
		$on     = function ( $key ) use ( $values ) {
			return isset( $values[ $key ] ) && 'on' === $values[ $key ];
		};
		$amount = function ( $key ) use ( $values ) {
			return isset( $values[ $key ] ) && '' !== $values[ $key ] && is_numeric( $values[ $key ] ) ? (float) $values[ $key ] : null;
		};
		$label  = function ( $key ) use ( $field_map ) {
			$text = wp_strip_all_tags( $field_map[ $key ]['label'] ?? $key );
			// Gateway charge fields are labelled with the bare gateway title.
			/* translators: %s: payment gateway title. */
			return 0 === strpos( $key, 'charge_amount_' ) ? sprintf( __( 'Gateway charge for %s', 'woo-wallet' ), $text ) : $text;
		};

		$limits  = array(); // [ min key, max key ].
		$charges = array(); // [ charge key, charge-type key ].
		if ( $on( 'is_enable_wallet_topup' ) ) {
			$limits[] = array( 'min_topup_amount', 'max_topup_amount' );
			if ( $on( 'is_enable_gateway_charge' ) ) {
				foreach ( array_keys( $values ) as $key ) {
					if ( 0 === strpos( $key, 'charge_amount_' ) ) {
						$charges[] = array( $key, 'gateway_charge_type' );
					}
				}
			}
		}
		if ( $on( 'is_enable_wallet_transfer' ) ) {
			$limits[]  = array( 'min_transfer_amount', 'max_transfer_amount' );
			$charges[] = array( 'transfer_charge_amount', 'transfer_charge_type' );
		}

		$errors = array();
		foreach ( $limits as list( $min_key, $max_key ) ) {
			$min = $amount( $min_key );
			$max = $amount( $max_key );
			foreach ( array( $min_key => $min, $max_key => $max ) as $key => $value ) {
				if ( null !== $value && $value < 0 ) {
					/* translators: %s: field label. */
					$errors[] = sprintf( __( '%s cannot be negative.', 'woo-wallet' ), $label( $key ) );
				}
			}
			// A blank or zero maximum means "no limit" at runtime.
			if ( null !== $min && null !== $max && $max > 0 && $min > $max ) {
				/* translators: 1: minimum field label, 2: maximum field label. */
				$errors[] = sprintf( __( '%1$s cannot be more than %2$s.', 'woo-wallet' ), $label( $min_key ), $label( $max_key ) );
			}
		}
		foreach ( $charges as list( $charge_key, $type_key ) ) {
			$charge = $amount( $charge_key );
			if ( null === $charge ) {
				continue;
			}
			if ( $charge < 0 ) {
				/* translators: %s: field label. */
				$errors[] = sprintf( __( '%s cannot be negative.', 'woo-wallet' ), $label( $charge_key ) );
			} elseif ( $charge > 100 && 'percent' === ( $values[ $type_key ] ?? 'percent' ) ) {
				/* translators: %s: field label. */
				$errors[] = sprintf( __( '%s cannot be more than 100 when the charge type is a percentage.', 'woo-wallet' ), $label( $charge_key ) );
			}
		}

		if ( empty( $errors ) ) {
			return true;
		}
		return new WP_Error(
			'woo_wallet_invalid_settings',
			__( 'Settings not saved.', 'woo-wallet' ) . ' ' . implode( ' ', $errors ),
			array( 'status' => 400 )
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
