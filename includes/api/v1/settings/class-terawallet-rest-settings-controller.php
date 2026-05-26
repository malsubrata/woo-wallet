<?php
/**
 * GET /terawallet/v1/settings
 *
 * Returns the full settings schema (sections, fields, values, context) for
 * the React admin UI. Canonical replacement for GET /wc/v3/wallet/settings.
 *
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings read controller.
 */
class TeraWallet_REST_Settings_V1_Controller extends TeraWallet_REST_Settings_Controller_Base {

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
	protected $rest_base = 'settings';

	/**
	 * Register routes.
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
	}

	/**
	 * GET /terawallet/v1/settings
	 *
	 * Returns sections, fields, values, and React context payload.
	 *
	 * @return WP_REST_Response
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

		// Surface JS-registered tab options so React rehydrates saved values on reload.
		foreach ( $this->get_js_section_option_names() as $option_name ) {
			if ( array_key_exists( $option_name, $values ) ) {
				continue;
			}
			$values[ $option_name ] = get_option( $option_name, array() );
		}

		// Action checkbox fields persist as 'yes'/'no'; flip to 'on'/'off' for React.
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
	 *
	 * @param array $field Raw field definition from the settings schema.
	 * @return array Normalized field.
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

		// Defence-in-depth: sanitize label/hint flowing through third-party filters.
		if ( isset( $field['label'] ) && is_string( $field['label'] ) ) {
			$field['label'] = wp_kses_post( $field['label'] );
		}
		if ( isset( $field['hint'] ) && is_string( $field['hint'] ) ) {
			$field['hint'] = wp_kses_post( $field['hint'] );
		}

		return $field;
	}

	/**
	 * List wp_options rows matching the js-section namespaces.
	 *
	 * @return string[] Option names.
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
	 * Build the context payload for the React UI.
	 *
	 * @return array
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
