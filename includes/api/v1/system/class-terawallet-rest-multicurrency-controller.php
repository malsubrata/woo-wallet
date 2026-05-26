<?php
/**
 * GET /terawallet/v1/multicurrency
 *
 * Read-only inspection endpoint that surfaces the currency manager's runtime
 * state to the React settings panel. Canonical replacement for
 * GET /wc/v3/wallet/multicurrency.
 *
 * Response shape:
 *   {
 *     base_currency:        "USD",
 *     active_currency:      "EUR",
 *     mode:                 "single_base" | "per_currency",
 *     mode_setting:         "single_base" | "per_currency",
 *     per_currency_enabled: bool,
 *     active_provider:      { id, label, available },
 *     all_providers:        [ { id, label, available }, ... ],
 *     supported_currencies: [ "USD", "EUR", ... ]
 *   }
 *
 * @package StandaleneTech
 * @since   1.7.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Multi-currency admin controller (canonical terawallet/v1).
 */
class TeraWallet_REST_V1_Multicurrency_Controller extends TeraWallet_REST_Admin_Controller_Base {

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
	protected $rest_base = 'multicurrency';

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
					'callback'            => array( $this, 'get_state' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Manage-wallet-settings capability check.
	 *
	 * @return true|WP_Error
	 */
	public function check_permission() {
		if ( ! current_user_can( get_wallet_user_capability() ) ) {
			return $this->error(
				'woo_wallet_rest_cannot_manage_multicurrency',
				__( 'Sorry, you are not allowed to inspect the wallet currency configuration.', 'woo-wallet' ),
				rest_authorization_required_code()
			);
		}
		return true;
	}

	/**
	 * Build the multicurrency state response payload.
	 *
	 * @return WP_REST_Response
	 */
	public function get_state() {
		$manager = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;

		$base_currency   = $manager ? $manager->get_base_currency() : strtoupper( (string) get_option( 'woocommerce_currency', 'USD' ) );
		$active_currency = $manager ? $manager->get_active_currency() : $base_currency;
		$active_provider = $manager ? $manager->get_active_provider() : null;

		$all_providers = array();
		if ( $manager ) {
			foreach ( $manager->get_providers() as $id => $provider ) {
				$all_providers[] = array(
					'id'        => $id,
					'label'     => method_exists( $provider, 'get_label' ) ? (string) $provider->get_label() : $id,
					'available' => method_exists( $provider, 'is_available' ) ? (bool) $provider->is_available() : false,
				);
			}
		}

		$mode_setting         = woo_wallet()->settings_api->get_option( 'wallet_currency_mode', '_wallet_settings_general', 'single_base' );
		$mode_setting         = 'per_currency' === $mode_setting ? 'per_currency' : 'single_base';
		$per_currency_enabled = (bool) apply_filters( 'woo_wallet_enable_per_currency_mode', false );
		$effective_mode       = ( $per_currency_enabled && 'per_currency' === $mode_setting ) ? 'per_currency' : 'single_base';

		$supported = array();
		if ( $active_provider && method_exists( $active_provider, 'get_supported_currencies' ) ) {
			$supported = (array) $active_provider->get_supported_currencies();
		}
		if ( ! in_array( $base_currency, $supported, true ) ) {
			$supported[] = $base_currency;
		}
		$supported = array_values( array_unique( array_map( 'strtoupper', $supported ) ) );

		$data = array(
			'base_currency'        => $base_currency,
			'active_currency'      => $active_currency,
			'mode'                 => $effective_mode,
			'mode_setting'         => $mode_setting,
			'per_currency_enabled' => $per_currency_enabled,
			'active_provider'      => $active_provider ? array(
				'id'        => $active_provider->get_id(),
				'label'     => method_exists( $active_provider, 'get_label' ) ? (string) $active_provider->get_label() : $active_provider->get_id(),
				'available' => true,
			) : null,
			'all_providers'        => $all_providers,
			'supported_currencies' => $supported,
		);

		$data = apply_filters( 'terawallet_rest_multicurrency_state', $data );

		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'private, no-store' );
		return $response;
	}

	/**
	 * JSON schema for this endpoint's response.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'terawallet_multicurrency_state',
			'type'       => 'object',
			'properties' => array(
				'base_currency'        => array(
					'type'     => 'string',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'active_currency'      => array(
					'type'     => 'string',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'mode'                 => array(
					'type'     => 'string',
					'enum'     => array( 'single_base', 'per_currency' ),
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'mode_setting'         => array(
					'type'     => 'string',
					'enum'     => array( 'single_base', 'per_currency' ),
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'per_currency_enabled' => array(
					'type'     => 'boolean',
					'context'  => array( 'view' ),
					'readonly' => true,
				),
				'active_provider'      => array(
					'type'       => array( 'object', 'null' ),
					'context'    => array( 'view' ),
					'readonly'   => true,
					'properties' => array(
						'id'        => array( 'type' => 'string' ),
						'label'     => array( 'type' => 'string' ),
						'available' => array( 'type' => 'boolean' ),
					),
				),
				'all_providers'        => array(
					'type'     => 'array',
					'context'  => array( 'view' ),
					'readonly' => true,
					'items'    => array(
						'type'       => 'object',
						'properties' => array(
							'id'        => array( 'type' => 'string' ),
							'label'     => array( 'type' => 'string' ),
							'available' => array( 'type' => 'boolean' ),
						),
					),
				),
				'supported_currencies' => array(
					'type'     => 'array',
					'context'  => array( 'view' ),
					'readonly' => true,
					'items'    => array( 'type' => 'string' ),
				),
			),
		);
		return $this->add_additional_fields_schema( $schema );
	}
}
