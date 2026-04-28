<?php
/**
 * GET /terawallet/v1/me/balance
 *
 * Lightweight balance read for the React dashboard's header pill — separate
 * from /me so the SPA can poll it cheaply without re-fetching the full
 * profile snapshot.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Balance_Controller' ) ) {

	/**
	 * Balance controller.
	 */
	class TeraWallet_REST_Me_Balance_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/balance';

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
		 * Read balance for the calling user.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function get_item( $request ) {
			$user_id = $this->current_user_id();
			$amount  = (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );

			$data = array(
				'amount'    => $amount,
				'currency'  => get_woocommerce_currency(),
				'formatted' => wp_strip_all_tags( wc_price( $amount, woo_wallet_wc_price_args( $user_id ) ) ),
			);

			$data = apply_filters( 'terawallet_rest_me_balance', $data, $user_id, $request );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/me/balance' ) );
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
				'title'      => 'terawallet_balance',
				'type'       => 'object',
				'properties' => array(
					'amount'    => array(
						'type'     => 'number',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'currency'  => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'formatted' => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
