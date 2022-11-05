<?php
/**
 * REST API WooWallet controller
 *
 * Handles requests to the /wallet endpoint.
 *
 * @author   Subrata Mal
 * @category API
 * @since    1.2.5
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API WooWallet controller class.
 *
 * @extends WC_REST_Controller
 */
class WC_REST_Woo_Wallet_Controller extends WC_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v2';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'wallet';

	/**
	 * Register the routes for customers.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'woo-wallet' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array_merge(
						$this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
						array(
							'type'    => array(
								'required'    => true,
								'type'        => 'string',
								'description' => __( 'Wallet transaction type.', 'woo-wallet' ),
							),
							'amount'  => array(
								'required'    => true,
								'description' => __( 'Wallet transaction amount.', 'woo-wallet' ),
								'type'        => 'number',
							),
							'details' => array(
								'required'    => false,
								'description' => __( 'Wallet transaction details.', 'woo-wallet' ),
								'type'        => 'string',
							),
						)
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/balance/(?P<id>[\d]+)',
			array(
				'args' => array(
					'id' => array(
						'description' => __( 'Unique identifier for the resource.', 'woo-wallet' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
			)
		);
	}

	/**
	 * Get all transactions by user id.
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_Error|WP_REST_Response
	 */
	public function get_items( $request ) {
		// get parameters from request.
		$params = $request->get_params();
		$data   = get_wallet_transactions( array( 'user_id' => $params['id'] ) );
		return new WP_REST_Response( $data, 200 );
	}
	/**
	 * Get user wallet balance
	 *
	 * @param WP_REST_Request $request request.
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		// get parameters from request.
		$params  = $request->get_params();
		$balance = woo_wallet()->wallet->get_wallet_balance( $params['id'], 'edit' );
		return new WP_REST_Response( $balance, 200 );
	}
	/**
	 * Insert new wallet transaction.
	 *
	 * @param WP_REST_Request $request request.
	 * @return \WP_REST_Response
	 */
	public function create_item( $request ) {
		$params = $request->get_params();
		if ( isset( $params['type'] ) && isset( $params['amount'] ) ) {
			$params['details'] = isset( $params['details'] ) ? $params['details'] : '';
			$transaction_id    = false;
			if ( 'credit' === $params['type'] ) {
				$transaction_id = woo_wallet()->wallet->credit( $params['id'], $params['amount'], $params['details'] );
			} elseif ( 'debit' === $params['type'] ) {
				$transaction_id = woo_wallet()->wallet->debit( $params['id'], $params['amount'], $params['details'] );
			}
			return new WP_REST_Response(
				array(
					'response' => 'success',
					'id'       => $transaction_id,
				),
				200
			);
		} else {
			return new WP_REST_Response( array( 'response' => 'Invalid Request' ), 401 );
		}
	}

	/**
	 * Check whether a given request has permission to read transactions.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		if ( ! apply_filters( 'woo_wallet_rest_check_permissions', current_user_can( 'manage_woocommerce' ), 'read', $request ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_view', __( 'Sorry, you cannot list resources.', 'woo-wallet' ), array( 'status' => rest_authorization_required_code() ) );
		}

		return true;
	}
	/**
	 * Check whether a given request has permission to create new transactions.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! apply_filters( 'woo_wallet_rest_check_permissions', current_user_can( 'manage_woocommerce' ), 'create', $request ) ) {
			return new WP_Error( 'woocommerce_rest_cannot_create', __( 'Sorry, you are not allowed to create resources.', 'woo-wallet' ), array( 'status' => rest_authorization_required_code() ) );
		}
		return true;
	}

}
