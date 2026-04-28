<?php
/**
 * REST API Settings controller for TeraWallet
 *
 * Handles GET /wc/v3/wallet/settings (schema + current values)
 * and POST /wc/v3/wallet/settings/section  (save one section)
 * and POST /wc/v3/wallet/settings/action   (save one action)
 *
 * @package StandaleneTech
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API TeraWallet Settings controller class.
 */
class TeraWallet_REST_Settings_Controller extends TeraWallet_REST_Controller_Base {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

	/**
	 * REST API base path.
	 *
	 * @var string
	 */
	protected $rest_base = 'wallet/settings';

	/**
	 * Register REST routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/action',
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
	 * Check if the current user can manage wallet settings.
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
	 * GET /wc/v3/wallet/settings
	 * Returns the full schema (sections, fields, values, actions, context).
	 */
	public function get_settings() {
		$this->maybe_load_settings_class();

		$settings_obj = new Woo_Wallet_Settings( woo_wallet()->settings_api );
		$sections     = $settings_obj->get_settings_sections();
		$fields       = $settings_obj->get_settings_fields();
		// Normalize each field for React consumption (desc→hint, select+multiple→multiselect).
		$normalized_fields = array();
		foreach ( $fields as $section_id => $section_fields ) {
			$normalized_fields[ $section_id ] = array_map(
				array( $this, 'normalize_section_field' ),
				$section_fields
			);
		}

		// Collect current values for each section.
		$values = array();
		foreach ( $sections as $section ) {
			$values[ $section['id'] ] = get_option( $section['id'], array() );
		}

		$context      = $this->build_context();
		$actions_data = $this->build_actions_data( $context );

		return rest_ensure_response(
			array(
				'sections' => $sections,
				'fields'   => $normalized_fields,
				'values'   => $values,
				'actions'  => $actions_data,
				'context'  => $context,
			)
		);
	}

	/**
	 * Normalize a section field for React consumption.
	 * - Renames `desc` → `hint` (React side uses `hint`).
	 * - Converts `type: select` + `multiple: true` → `type: multiselect`.
	 * - Preserves `group`, `group_title`, `group_description`, `show_if` metadata.
	 *
	 * @param array $field Raw field definition from the settings schema.
	 * @return array Normalized field ready to ship to React.
	 */
	private function normalize_section_field( array $field ): array {
		if ( isset( $field['desc'] ) && ! isset( $field['hint'] ) ) {
			$field['hint'] = $field['desc'];
		}
		unset( $field['desc'] );

		if ( isset( $field['type'] ) && 'select' === $field['type'] && ! empty( $field['multiple'] ) ) {
			$field['type'] = 'multiselect';
		}

		if ( isset( $field['prefix'] ) ) {
			$field['prefix'] = html_entity_decode( $field['prefix'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		}

		return $field;
	}

	/**
	 * POST /wc/v3/wallet/settings/section
	 * Saves one settings section's values.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function save_section( WP_REST_Request $request ) {
		$this->maybe_load_settings_class();

		$section_id = sanitize_key( $request->get_param( 'section_id' ) );
		$values     = (array) $request->get_param( 'values' );

		// Validate section exists.
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

		// Register the per-section update_option side-effect callbacks (product title sync,
		// image sync, tax sync) so they fire when we call update_option() below. We can't
		// call the full plugin_settings_page_init() here because it invokes admin-only
		// functions like add_settings_section() which aren't available in REST context.
		$callback_name = "update_option_{$section_id}_callback";
		if ( method_exists( $settings_obj, $callback_name ) ) {
			add_action( "update_option_{$section_id}", array( $settings_obj, $callback_name ), 10, 3 );
		}

		// Build field map for this section (for type-aware sanitization).
		$all_fields     = $settings_obj->get_settings_fields();
		$all_fields     = apply_filters( 'woo_wallet_settings_fields', $all_fields );
		$section_fields = isset( $all_fields[ $section_id ] ) ? $all_fields[ $section_id ] : array();
		$field_map      = array();
		foreach ( $section_fields as $field ) {
			$field_map[ $field['name'] ] = $field;
		}

		$sanitized = array();
		foreach ( $values as $key => $value ) {
			$skey  = sanitize_key( $key );
			$field = isset( $field_map[ $skey ] ) ? $field_map[ $skey ] : array( 'type' => 'text' );
			$type  = isset( $field['type'] ) ? $field['type'] : 'text';

			if ( ! empty( $field['sanitize_callback'] ) && is_callable( $field['sanitize_callback'] ) ) {
				$sanitized[ $skey ] = call_user_func( $field['sanitize_callback'], $value );
			} elseif ( 'checkbox' === $type ) {
				$sanitized[ $skey ] = ( 'on' === $value || true === $value ) ? 'on' : 'off';
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

		// update_option fires do_action("update_option_{$option}") which triggers
		// the side-effect callbacks registered by plugin_settings_page_init() above.
		update_option( $section_id, $sanitized );

		return rest_ensure_response(
			array(
				'section_id' => $section_id,
				'values'     => get_option( $section_id, array() ),
			)
		);
	}

	/**
	 * POST /wc/v3/wallet/settings/action
	 * Saves one wallet action's settings.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function save_action( WP_REST_Request $request ) {
		$action_id = sanitize_key( $request->get_param( 'action_id' ) );
		$values    = (array) $request->get_param( 'values' );

		$actions_obj = WOO_Wallet_Actions::instance();
		if ( ! isset( $actions_obj->actions[ $action_id ] ) ) {
			return new WP_Error(
				'woo_wallet_invalid_action',
				__( 'Invalid action ID.', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		$action    = $actions_obj->actions[ $action_id ];
		$sanitized = array();

		foreach ( $action->form_fields as $key => $field ) {
			$field_value = isset( $values[ $key ] ) ? $values[ $key ] : ( isset( $field['default'] ) ? $field['default'] : '' );
			$type        = isset( $field['type'] ) ? $field['type'] : 'text';

			// Use WC_Settings_API's own validate_*_field method when available.
			$method = 'validate_' . $type . '_field';
			if ( method_exists( $action, $method ) ) {
				$sanitized[ $key ] = $action->$method( $key, $field_value );
			} elseif ( 'checkbox' === $type ) {
				$sanitized[ $key ] = in_array( $field_value, array( 'yes', true, '1', 1 ), true ) ? 'yes' : 'no';
			} elseif ( in_array( $type, array( 'price', 'decimal', 'number' ), true ) ) {
				$sanitized[ $key ] = is_numeric( $field_value ) ? wc_format_decimal( $field_value ) : '';
			} else {
				$sanitized[ $key ] = sanitize_text_field( (string) $field_value );
			}
		}

		// WC_Settings_API writes to {plugin_id}{id} option key.
		$option_key = $action->plugin_id . $action->id . '_settings';
		update_option( $option_key, $sanitized );
		$action->init_settings();

		return rest_ensure_response(
			array(
				'action_id' => $action_id,
				'values'    => $action->settings,
			)
		);
	}

	/**
	 * Load the settings class file if we're outside admin context (e.g. REST request).
	 */
	private function maybe_load_settings_class() {
		if ( ! class_exists( 'Woo_Wallet_Settings' ) ) {
			// Suppress auto-instantiation side-effect by checking before include;
			// the file's bottom-level `new Woo_Wallet_Settings()` is guarded by
			// is_admin() after our patch to class-woo-wallet-settings.php.
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php';
		}
	}

	/**
	 * Build the context payload — dynamic values the React UI needs for field options.
	 */
	private function build_context() {
		$gateways = array();
		$allowed  = array();
		if ( WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways as $gateway ) {
				if ( 'yes' === $gateway->enabled && 'wallet' !== $gateway->id ) {
					$title                    = $gateway->get_title() ? $gateway->get_title() : __( '(no title)', 'woo-wallet' );
					$gateways[ $gateway->id ] = $title;
					$allowed[ $gateway->id ]  = $title;
				}
			}
		}

		$order_statuses = array();
		foreach ( wc_get_order_statuses() as $status => $label ) {
			$order_statuses[ str_replace( 'wc-', '', $status ) ] = $label;
		}

		$user_roles = array();
		foreach ( array_reverse( wp_roles()->roles ) as $role => $details ) {
			$user_roles[ $role ] = translate_user_role( $details['name'] );
		}

		$menu_locations = array();
		if ( current_theme_supports( 'menus' ) ) {
			foreach ( get_registered_nav_menus() as $location => $title ) {
				$menu_locations[ $location ] = $title;
			}
		}

		$tax_classes = wc_tax_enabled() ? wc_get_product_tax_class_options() : array();

		return array(
			'currencySymbol'  => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
			'gateways'        => $gateways,
			'allowedGateways' => $allowed,
			'orderStatuses'   => $order_statuses,
			'userRoles'       => $user_roles,
			'menuLocations'   => $menu_locations,
			'taxClasses'      => $tax_classes,
			'taxEnabled'      => wc_tax_enabled(),
		);
	}

	/**
	 * Build actions data array with fields normalized for React consumption.
	 * WC_Settings_API field shape (title/description/desc_tip) → common JS shape (label/hint).
	 *
	 * @param array $context The context array containing dynamic values for field options.
	 */
	private function build_actions_data( array $context ) {
		$result = array();
		foreach ( WOO_Wallet_Actions::instance()->actions as $action ) {
			$action->init_settings();
			$normalized = array();
			foreach ( $action->form_fields as $key => $field ) {
				$normalized[] = $this->normalize_action_field( $key, $field, $context );
			}
			$result[] = array(
				'id'          => $action->id,
				'title'       => $action->get_action_title(),
				'description' => $action->get_action_description(),
				'enabled'     => $action->is_enabled(),
				'fields'      => $normalized,
				'values'      => $action->settings,
			);
		}
		return $result;
	}

	/**
	 * Normalize a WC_Settings_API field definition to the common JS-facing schema shape.
	 *
	 * WC shape:  title / type / description / desc_tip / label / options / default
	 * JS shape:  name  / type / label       / hint     /        / options / default / prefix
	 *
	 * @param string $key The field key.
	 * @param array  $field The field definition array.
	 * @param array  $context The context array.
	 */
	private function normalize_action_field( string $key, array $field, array $context ): array {
		$type   = isset( $field['type'] ) ? $field['type'] : 'text';
		$prefix = null;

		if ( 'price' === $type ) {
			$type   = 'number';
			$prefix = $context['currencySymbol'];
		}

		$result = array(
			'name'    => $key,
			'label'   => isset( $field['title'] ) ? $field['title'] : '',
			'hint'    => isset( $field['description'] ) ? $field['description'] : '',
			'type'    => $type,
			'default' => isset( $field['default'] ) ? $field['default'] : '',
		);

		if ( isset( $field['options'] ) ) {
			$result['options'] = $field['options'];
		}
		if ( null !== $prefix ) {
			$result['prefix'] = $prefix;
		}
		if ( isset( $field['placeholder'] ) ) {
			$result['placeholder'] = $field['placeholder'];
		}
		if ( isset( $field['min'] ) ) {
			$result['min'] = $field['min'];
		}
		if ( isset( $field['max'] ) ) {
			$result['max'] = $field['max'];
		}
		if ( isset( $field['step'] ) ) {
			$result['step'] = $field['step'];
		}

		return $result;
	}
}

// Back-compat alias for the pre-rename class name. Remove in 2.1.
class_alias( 'TeraWallet_REST_Settings_Controller', 'WC_REST_TeraWallet_Settings_Controller' );
