<?php
/**
 * REST API Settings controller for TeraWallet
 *
 * Handles GET  /wc/v3/wallet/settings              (full schema + current values)
 *         POST /wc/v3/wallet/settings/section      (save one section, incl. actions)
 *         POST /wc/v3/wallet/settings/js-section   (save a JS-registered tab)
 *         POST /wc/v3/wallet/settings/action       (deprecated 1.6.1 shim → /section)
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

		// JS-first extension endpoint. Accepts a section_id contributed by the
		// JS registry, a fields_schema describing each field's sanitize hint,
		// and the values to persist. The endpoint validates the section_id
		// namespace and applies whitelist-driven sanitization — arbitrary
		// sanitize hints are coerced to plain text.
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/js-section',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'save_js_section' ),
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
	 * Returns the full schema (sections, fields, values, context). As of 1.6.1
	 * the earning actions are part of the standard sections/fields pipeline
	 * under section id `_wallet_settings_actions`; the separate top-level
	 * `actions` array shipped before 1.6.1 was removed.
	 */
	public function get_settings() {
		$this->maybe_load_settings_class();

		$settings_obj = new Woo_Wallet_Settings( woo_wallet()->settings_api );
		$sections     = $settings_obj->get_settings_sections();
		$fields       = $settings_obj->get_settings_fields();

		$normalized_fields = array();
		foreach ( $fields as $section_id => $section_fields ) {
			$normalized_fields[ $section_id ] = array_map(
				array( $this, 'normalize_section_field' ),
				$section_fields
			);
		}

		$values = array();
		foreach ( $sections as $section ) {
			$values[ $section['id'] ] = get_option( $section['id'], array() );
		}

		// Also surface any JS-registered tab options (saved via /js-section) so
		// the React app rehydrates user-entered values on reload instead of the
		// registry seeding stale defaults. We scan for the same namespaces the
		// /js-section saver permits — `wallet_ext_*` (third-party tabs) and
		// `_wallet_settings_*` (a few built-in tabs already covered above; the
		// merge is a no-op for those since the section pass populated them).
		foreach ( $this->get_js_section_option_names() as $option_name ) {
			if ( array_key_exists( $option_name, $values ) ) {
				continue;
			}
			$values[ $option_name ] = get_option( $option_name, array() );
		}

		// Action checkbox fields persist as 'yes'/'no' so WC_Settings_API's
		// is_enabled() and validate_checkbox_field() keep working. Flip the
		// stored values to the standard 'on'/'off' tokens before shipping to
		// the React CheckboxField; save_section() performs the inverse on write.
		if ( isset( $values['_wallet_settings_actions'] ) && is_array( $values['_wallet_settings_actions'] ) ) {
			$values['_wallet_settings_actions'] = $settings_obj->prepare_actions_values_for_react( $values['_wallet_settings_actions'] );
		}

		return rest_ensure_response(
			array(
				'sections' => $sections,
				'fields'   => $normalized_fields,
				'values'   => $values,
				'context'  => $this->build_context(),
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

		// Defence-in-depth: `label` and `hint` flow through third-party
		// `woo_wallet_*_form_fields` filters before reaching React. The
		// React SectionHeading component renders `hint` via
		// `dangerouslySetInnerHTML`, so strip executable HTML here. `label`
		// is rendered as a text node, but sanitising it costs nothing and
		// guards any other consumer that may render it as HTML.
		if ( isset( $field['label'] ) && is_string( $field['label'] ) ) {
			$field['label'] = wp_kses_post( $field['label'] );
		}
		if ( isset( $field['hint'] ) && is_string( $field['hint'] ) ) {
			$field['hint'] = wp_kses_post( $field['hint'] );
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

			// Delegate to the owning action's WC_Settings_API validator when we
			// can identify it (e.g. validate_textarea_field strips tags, etc.).
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

		// update_option fires do_action("update_option_{$option}") which triggers
		// the side-effect callbacks registered by plugin_settings_page_init() above.
		update_option( $section_id, $sanitized );

		// Force action subclasses to repopulate their $settings cache so any
		// in-process reads (e.g. via woo_wallet_transaction_recorded handlers
		// running later in the request) see the new values.
		if ( $is_actions_section ) {
			foreach ( $actions_index as $action ) {
				$action->init_settings();
			}
		}

		// Build the response values in the same React-facing shape the GET
		// endpoint emits — for the actions section that means yes/no → on/off.
		$response_values = get_option( $section_id, array() );
		if ( $is_actions_section && is_array( $response_values ) ) {
			$settings_obj    = isset( $settings_obj ) ? $settings_obj : new Woo_Wallet_Settings( woo_wallet()->settings_api );
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
	 * Resolve the WOO_Wallet_Actions registry keyed by action id; empty array
	 * if the actions loader has not run (e.g. broken bootstrap).
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
	 * Split a flattened action field name (`daily_visits__amount`) into its
	 * `[action_id, field_key]` parts. Returns `[null, $name]` if the prefix
	 * doesn't match any known action.
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
	 * Common truthy coercion for checkbox payloads — React may send `true`,
	 * `'on'`, `'yes'`, `1`, or `'1'` depending on the field component.
	 *
	 * @param mixed $value Raw value from the request.
	 * @return bool
	 */
	private function coerce_truthy( $value ) {
		return in_array( $value, array( 'on', 'yes', true, 1, '1' ), true );
	}

	/**
	 * POST /wc/v3/wallet/settings/action — deprecated 1.6.1 shim.
	 *
	 * The React UI no longer calls this endpoint; it now saves the unified
	 * `_wallet_settings_actions` section through `save_section()`. Kept for one
	 * minor cycle so external clients (custom dashboards, server-to-server
	 * callers) that hit this URL keep working: we translate the
	 * `{action_id, values}` payload into the equivalent flattened-section save
	 * and emit `_doing_it_wrong()`.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function save_action( WP_REST_Request $request ) {
		_doing_it_wrong(
			'POST /wc/v3/wallet/settings/action',
			esc_html__( 'Deprecated since 1.6.1. Use POST /wc/v3/wallet/settings/section with section_id=_wallet_settings_actions and namespaced field keys ({action_id}__{field_key}).', 'woo-wallet' ),
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

		$proxy = new WP_REST_Request( 'POST', '/wc/v3/wallet/settings/section' );
		$proxy->set_param( 'section_id', '_wallet_settings_actions' );
		$proxy->set_param( 'values', $flattened );

		$response = $this->save_section( $proxy );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data           = $response->get_data();
		$merged_values  = isset( $data['values'] ) && is_array( $data['values'] ) ? $data['values'] : array();
		$action_values  = array();
		$prefix         = $action_id . '__';
		$prefix_len     = strlen( $prefix );
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
	 * POST /wc/v3/wallet/settings/js-section
	 * Saves a JS-registered tab's values. The schema (sanitize hints per field)
	 * is sent in the request body — safe because the endpoint is admin-only and
	 * hints are validated against an internal whitelist.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 */
	public function save_js_section( WP_REST_Request $request ) {
		$section_id    = sanitize_key( $request->get_param( 'section_id' ) );
		$fields_schema = (array) $request->get_param( 'fields_schema' );
		$values        = (array) $request->get_param( 'values' );

		// Reject empty or unprefixed section IDs. Forces add-ons to namespace
		// their option keys and prevents accidental writes to core WordPress
		// options like `siteurl`, `home`, etc.
		if ( '' === $section_id || ! preg_match( '/^(?:_wallet_settings_|wallet_ext_)[a-z0-9_]+$/', $section_id ) ) {
			return new WP_Error(
				'woo_wallet_invalid_js_section',
				__( 'Section ID must start with "_wallet_settings_" or "wallet_ext_".', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		// Defence-in-depth: never overwrite a built-in option key that ships
		// with WordPress or WooCommerce, even if the prefix check is bypassed.
		$forbidden = array( 'siteurl', 'home', 'admin_email', 'blogname', 'wp_options' );
		if ( in_array( $section_id, $forbidden, true ) ) {
			return new WP_Error(
				'woo_wallet_forbidden_js_section',
				__( 'That section ID is reserved.', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		// Build a hint map: field_name => sanitize-hint (validated against whitelist).
		$hint_map = array();
		foreach ( $fields_schema as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
				continue;
			}
			$name = sanitize_key( $entry['name'] );
			$hint = isset( $entry['sanitize'] ) ? (string) $entry['sanitize'] : '';
			$hint_map[ $name ] = $this->validate_sanitize_hint( $hint );
		}

		$sanitized = array();
		foreach ( $values as $key => $value ) {
			$skey = sanitize_key( $key );
			$hint = isset( $hint_map[ $skey ] ) ? $hint_map[ $skey ] : 'text';
			$sanitized[ $skey ] = $this->sanitize_by_hint( $hint, $value );
		}

		$old_values = (array) get_option( $section_id, array() );
		update_option( $section_id, $sanitized );

		/**
		 * Fires after a JS-registered settings section is saved.
		 *
		 * @param string $section_id The section ID (already namespace-validated).
		 * @param array  $sanitized  The sanitized values that were persisted.
		 * @param array  $old_values The previously persisted values.
		 */
		do_action( 'woo_wallet_js_section_saved', $section_id, $sanitized, $old_values );

		return rest_ensure_response(
			array(
				'section_id' => $section_id,
				'values'     => get_option( $section_id, array() ),
			)
		);
	}

	/**
	 * Validate a sanitize hint sent from the client against the whitelist.
	 * Anything outside the whitelist is coerced to `text` — so a hostile or
	 * compromised client cannot trick the server into running an unsafe
	 * sanitizer.
	 *
	 * Keep this list in sync with `SANITIZE_HINTS` in
	 * `src/admin/settings/registry/index.js`.
	 *
	 * @param string $hint Hint as received from the client.
	 * @return string A safe, server-side-recognised hint.
	 */
	private function validate_sanitize_hint( $hint ) {
		$whitelist = array(
			'text', 'textarea', 'kses_post', 'number', 'absint', 'float',
			'bool', 'email', 'url', 'key', 'array_of_text', 'array_of_int',
			'attachment_id', 'color_hex',
		);
		return in_array( $hint, $whitelist, true ) ? $hint : 'text';
	}

	/**
	 * Apply a whitelisted sanitize hint to a raw value.
	 *
	 * @param string $hint One of the values returned by validate_sanitize_hint().
	 * @param mixed  $value Raw value as received in the request body.
	 * @return mixed Sanitized value ready to persist.
	 */
	private function sanitize_by_hint( $hint, $value ) {
		switch ( $hint ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $value );
			case 'kses_post':
				return wp_kses_post( (string) $value );
			case 'number':
			case 'float':
				return is_numeric( $value ) ? (float) $value : '';
			case 'absint':
				return absint( $value );
			case 'bool':
				return ( 'on' === $value || true === $value || 1 === $value || '1' === $value || 'yes' === $value ) ? 'on' : 'off';
			case 'email':
				return sanitize_email( (string) $value );
			case 'url':
				return esc_url_raw( (string) $value );
			case 'key':
				return sanitize_key( (string) $value );
			case 'array_of_text':
				return array_values( array_map( 'sanitize_text_field', array_map( 'strval', (array) $value ) ) );
			case 'array_of_int':
				return array_values( array_map( 'absint', (array) $value ) );
			case 'attachment_id':
				return absint( $value );
			case 'color_hex':
				$hex = sanitize_hex_color( (string) $value );
				return $hex ? $hex : '';
			case 'text':
			default:
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', array_map( 'strval', $value ) );
				}
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * List wp_options rows whose names match the namespaces accepted by
	 * `/wc/v3/wallet/settings/js-section` (`wallet_ext_*` + `_wallet_settings_*`).
	 *
	 * Used by `get_settings()` so JS-registered tabs rehydrate their saved
	 * values on reload instead of the JS registry seeding stale defaults.
	 *
	 * @return string[] Option names; empty array if none.
	 */
	private function get_js_section_option_names() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_col(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wallet\\_ext\\_%' OR option_name LIKE '\\_wallet\\_settings\\_%'"
		);

		return is_array( $rows ) ? $rows : array();
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

}

// Back-compat alias for the pre-rename class name. Remove in 2.1.
class_alias( 'TeraWallet_REST_Settings_Controller', 'WC_REST_TeraWallet_Settings_Controller' );
