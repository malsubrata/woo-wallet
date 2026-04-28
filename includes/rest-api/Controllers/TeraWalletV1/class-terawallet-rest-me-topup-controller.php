<?php
/**
 * POST /terawallet/v1/me/topup
 *
 * Creates a top-up order for the calling user and returns a payment URL the
 * SPA navigates to so the chosen gateway handles redirect/return as it would
 * for a cart-flow checkout.
 *
 * Idempotency: the SPA generates a UUID per submission; replays return the
 * original order's `{ order_id, payment_url }` rather than creating a second
 * order.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Topup_Controller' ) ) {

	/**
	 * Customer topup controller.
	 */
	class TeraWallet_REST_Me_Topup_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/topup';

		/**
		 * Register the route.
		 */
		public function register_routes() {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base,
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_item' ),
						'permission_callback' => array( $this, 'check_me_permissions' ),
						'args'                => array(
							'amount'         => array(
								'required'          => true,
								'type'              => 'number',
								'minimum'           => 0.01,
								'description'       => __( 'Top-up amount.', 'woo-wallet' ),
								'sanitize_callback' => function ( $v ) {
									return function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $v ) : (float) $v;
								},
								'validate_callback' => 'rest_validate_request_arg',
							),
							'payment_method' => array(
								'type'              => 'string',
								'description'       => __( 'WooCommerce payment gateway id.', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => 'rest_validate_request_arg',
							),
						),
					),
				)
			);
		}

		/**
		 * Create a top-up order (idempotent on Idempotency-Key header).
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_item( $request ) {
			return $this->idempotent(
				$request,
				function () use ( $request ) {
					return $this->run_topup( $request );
				}
			);
		}

		/**
		 * Delegate to topup service and shape the response.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		protected function run_topup( WP_REST_Request $request ) {
			$amount         = (float) $request->get_param( 'amount' );
			$payment_method = (string) $request->get_param( 'payment_method' );

			if ( ! class_exists( 'WooWallet_Topup_Service' ) ) {
				include_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-topup-service.php';
			}
			$result = WooWallet_Topup_Service::create_order( $this->current_user_id(), $amount, $payment_method );

			if ( empty( $result['is_valid'] ) ) {
				$status = isset( $result['status'] ) ? (int) $result['status'] : 400;
				$code   = isset( $result['code'] ) ? $result['code'] : 'rest_topup_failed';
				return $this->error( $code, $result['message'], $status );
			}

			$response = new WP_REST_Response(
				array(
					'order_id'    => (int) $result['order_id'],
					'amount'      => (float) $result['amount'],
					'payment_url' => $result['payment_url'],
				),
				201
			);
			return $this->private_no_store( $response );
		}
	}
}
