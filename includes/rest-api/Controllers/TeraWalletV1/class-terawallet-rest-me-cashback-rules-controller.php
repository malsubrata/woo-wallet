<?php
/**
 * GET /terawallet/v1/me/cashback-rules
 *
 * Read-only summary of the cashback program — what the customer can earn, on
 * what scope, and any caps. No admin-only fields are exposed (no role
 * exclusions, no per-product overrides).
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Cashback_Rules_Controller' ) ) {

	/**
	 * Customer cashback summary controller.
	 */
	class TeraWallet_REST_Me_Cashback_Rules_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/cashback-rules';

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
						'permission_callback' => array( $this, 'check_me_permissions' ),
						'args'                => array(
							'context' => $this->get_context_param( array( 'default' => 'view' ) ),
						),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
		}

		/**
		 * Build the cashback summary.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function get_item( $request ) {
			$settings = woo_wallet()->settings_api;
			$enabled  = 'on' === $settings->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'off' );

			$scope     = $settings->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' );
			$type      = $settings->get_option( 'cashback_type', '_wallet_settings_credit', 'percent' );
			$amount    = (float) $settings->get_option( 'cashback_amount', '_wallet_settings_credit', 0 );
			$max       = (float) $settings->get_option( 'max_cashback_amount', '_wallet_settings_credit', 0 );
			$min_cart  = (float) $settings->get_option( 'min_cart_amount', '_wallet_settings_credit', 0 );
			$user_id   = $this->current_user_id();

			$data = array(
				'enabled'    => $enabled,
				'scope'      => $scope, // cart | product | category
				'type'       => $type,  // percent | flat
				'amount'     => $amount,
				'max_amount' => $max,
				'min_cart'   => $min_cart,
				'formatted'  => array(
					'amount'     => 'percent' === $type ? $amount . '%' : wp_strip_all_tags( wc_price( $amount, woo_wallet_wc_price_args( $user_id ) ) ),
					'max_amount' => $max ? wp_strip_all_tags( wc_price( $max, woo_wallet_wc_price_args( $user_id ) ) ) : '',
					'min_cart'   => $min_cart ? wp_strip_all_tags( wc_price( $min_cart, woo_wallet_wc_price_args( $user_id ) ) ) : '',
				),
			);

			$data = apply_filters( 'terawallet_rest_me_cashback_rules', $data, $user_id, $request );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
			return $this->private_no_store( $response );
		}

		/**
		 * Schema.
		 *
		 * @return array
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'terawallet_cashback_rules',
				'type'       => 'object',
				'properties' => array(
					'enabled'    => array(
						'type'     => 'boolean',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'scope'      => array(
						'type'     => 'string',
						'enum'     => array( 'cart', 'product', 'category' ),
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'type'       => array(
						'type'     => 'string',
						'enum'     => array( 'percent', 'flat' ),
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'amount'     => array(
						'type'     => 'number',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'max_amount' => array(
						'type'     => 'number',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'min_cart'   => array(
						'type'     => 'number',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'formatted'  => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'amount'     => array( 'type' => 'string' ),
							'max_amount' => array( 'type' => 'string' ),
							'min_cart'   => array( 'type' => 'string' ),
						),
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
