<?php
/**
 * POST /terawallet/v1/settings/js-section
 *
 * Saves a JS-registered tab's values. Canonical replacement for
 * POST /wc/v3/wallet/settings/js-section.
 *
 * The schema (sanitize hints per field) is sent in the request body —
 * safe because the endpoint is admin-only and hints are validated
 * against an internal whitelist.
 *
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * JS-section settings write controller.
 */
class TeraWallet_REST_Settings_Js_Section_Controller extends TeraWallet_REST_Settings_Controller_Base {

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
	protected $rest_base = 'settings/js-section';

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
	 * POST /terawallet/v1/settings/js-section
	 *
	 * @param WP_REST_Request $request Full request details.
	 * @return WP_REST_Response|WP_Error
	 */
	public function save_js_section( WP_REST_Request $request ) {
		$section_id    = sanitize_key( $request->get_param( 'section_id' ) );
		$fields_schema = (array) $request->get_param( 'fields_schema' );
		$values        = (array) $request->get_param( 'values' );

		// Reject empty or unprefixed section IDs to prevent writes to core WP options.
		if ( '' === $section_id || ! preg_match( '/^(?:_wallet_settings_|wallet_ext_)[a-z0-9_]+$/', $section_id ) ) {
			return new WP_Error(
				'woo_wallet_invalid_js_section',
				__( 'Section ID must start with "_wallet_settings_" or "wallet_ext_".', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		$forbidden = array( 'siteurl', 'home', 'admin_email', 'blogname', 'wp_options' );
		if ( in_array( $section_id, $forbidden, true ) ) {
			return new WP_Error(
				'woo_wallet_forbidden_js_section',
				__( 'That section ID is reserved.', 'woo-wallet' ),
				array( 'status' => 400 )
			);
		}

		$hint_map = array();
		foreach ( $fields_schema as $entry ) {
			if ( ! is_array( $entry ) || empty( $entry['name'] ) ) {
				continue;
			}
			$name              = sanitize_key( $entry['name'] );
			$hint              = isset( $entry['sanitize'] ) ? (string) $entry['sanitize'] : '';
			$hint_map[ $name ] = $this->validate_sanitize_hint( $hint );
		}

		$sanitized = array();
		foreach ( $values as $key => $value ) {
			$skey               = sanitize_key( $key );
			$hint               = isset( $hint_map[ $skey ] ) ? $hint_map[ $skey ] : 'text';
			$sanitized[ $skey ] = $this->sanitize_by_hint( $hint, $value );
		}

		$old_values = (array) get_option( $section_id, array() );
		update_option( $section_id, $sanitized );

		/**
		 * Fires after a JS-registered settings section is saved.
		 *
		 * @param string $section_id The section ID (namespace-validated).
		 * @param array  $sanitized  Sanitized values persisted.
		 * @param array  $old_values Previously persisted values.
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
	 * Validate a sanitize hint against the server-side whitelist.
	 * Anything outside the whitelist is coerced to 'text'.
	 *
	 * Keep in sync with SANITIZE_HINTS in src/admin/settings/registry/index.js.
	 *
	 * @param string $hint Client-supplied hint.
	 * @return string Validated hint.
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
	 * @param string $hint  Validated hint from validate_sanitize_hint().
	 * @param mixed  $value Raw value from the request.
	 * @return mixed Sanitized value.
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
}
