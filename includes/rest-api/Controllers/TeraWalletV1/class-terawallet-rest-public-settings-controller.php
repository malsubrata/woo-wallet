<?php
/**
 * GET /terawallet/v1/settings/public
 *
 * The ONLY unauthenticated endpoint in the `terawallet/v1` namespace.
 * Returns site-wide configuration the React dashboard's app shell needs to
 * render before login (currency, top-up min/max, feature flags, terms URL).
 *
 * Anything user-scoped MUST NOT live here — those go in `/me/*` behind the
 * authenticated permission gate.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Public_Settings_Controller' ) ) {

	/**
	 * Public settings controller.
	 */
	class TeraWallet_REST_Public_Settings_Controller extends TeraWallet_REST_Controller_Base {

		/**
		 * REST namespace.
		 *
		 * @var string
		 */
		protected $namespace = 'terawallet/v1';

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'settings/public';

		/**
		 * Register the route.
		 */
		public function register_routes() {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_item' ),
						'permission_callback' => '__return_true',
						'args'                => array(
							'context' => $this->get_context_param( array( 'default' => 'view' ) ),
						),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
		}

		/**
		 * Build the public settings payload.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function get_item( $request ) {
			$settings = woo_wallet()->settings_api;

			$data = array(
				'currency'         => array(
					'code'   => get_woocommerce_currency(),
					'symbol' => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				),
				'topup'            => array(
					'enabled' => 'on' === $settings->get_option( 'is_enable_wallet_topup', '_wallet_settings_general', 'on' ),
					'min'     => (float) $settings->get_option( 'min_topup_amount', '_wallet_settings_general', 0 ),
					'max'     => (float) $settings->get_option( 'max_topup_amount', '_wallet_settings_general', 0 ),
				),
				'transfer'         => array(
					'enabled'      => 'on' === $settings->get_option( 'is_enable_wallet_transfer', '_wallet_settings_general', 'on' ),
					'min'          => (float) $settings->get_option( 'min_transfer_amount', '_wallet_settings_general', 0 ),
					'max'          => (float) $settings->get_option( 'max_transfer_amount', '_wallet_settings_general', 0 ),
					'charge_type'  => $settings->get_option( 'transfer_charge_type', '_wallet_settings_general', 'percent' ),
					'charge_value' => (float) $settings->get_option( 'transfer_charge_amount', '_wallet_settings_general', 0 ),
				),
				'cashback_enabled' => 'on' === $settings->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'off' ),
				'partial_payment'  => 'on' === $settings->get_option( 'is_enable_wallet_partial_payment', '_wallet_settings_general', 'on' ),
				'terms_url'        => esc_url_raw( apply_filters( 'terawallet_rest_terms_url', '' ) ),
			);

			$data = apply_filters( 'terawallet_rest_public_settings', $data, $request );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
			// Public — caches OK. Short window so feature-flag flips propagate quickly.
			$response->header( 'Cache-Control', 'public, max-age=60' );
			return $response;
		}

		/**
		 * Schema.
		 *
		 * @return array
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'terawallet_public_settings',
				'type'       => 'object',
				'properties' => array(
					'currency'         => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'code'   => array( 'type' => 'string' ),
							'symbol' => array( 'type' => 'string' ),
						),
					),
					'topup'            => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'enabled' => array( 'type' => 'boolean' ),
							'min'     => array( 'type' => 'number' ),
							'max'     => array( 'type' => 'number' ),
						),
					),
					'transfer'         => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'enabled'      => array( 'type' => 'boolean' ),
							'min'          => array( 'type' => 'number' ),
							'max'          => array( 'type' => 'number' ),
							'charge_type'  => array( 'type' => 'string' ),
							'charge_value' => array( 'type' => 'number' ),
						),
					),
					'cashback_enabled' => array(
						'type'     => 'boolean',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'partial_payment'  => array(
						'type'     => 'boolean',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'terms_url'        => array(
						'type'     => 'string',
						'format'   => 'uri',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
