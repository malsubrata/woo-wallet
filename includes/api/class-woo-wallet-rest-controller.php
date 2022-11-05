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
	exit; // Exit if accessed directly.
}
/**
 * REST API WooWallet controller class.
 *
 * @extends WC_REST_Controller
 */
class WOO_Wallet_REST_Controller extends WP_REST_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wp/v2';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'wallet';

	/**
	 * Registers the routes for the objects of the controller.
	 *
	 * @since 1.1.6
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
					'args'                => $this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/current_balance/(?P<id>[\d]+)',
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
	 * Retrieves one item from the collection.
	 *
	 * @since 1.1.6
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		// get parameters from request.
		$params  = $request->get_params();
		$balance = woo_wallet()->wallet->get_wallet_balance( $params['id'], 'edit' );
		return new WP_REST_Response( $balance, 200 );
	}

	/**
	 * Retrieves a collection of items.
	 *
	 * @since 1.1.6
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		// get parameters from request.
		$params = $request->get_params();
		$data   = get_wallet_transactions( array( 'user_id' => $params['id'] ) );
		return new WP_REST_Response( $data, 200 );
	}

	/**
	 * Creates one item from the collection.
	 *
	 * @since 1.1.6
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|WP_REST_Response Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		// get parameters from request.
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
	 * Checks if a given request has access to get items.
	 *
	 * @since 1.1.6
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		return apply_filters( 'woo_wallet_rest_check_permissions', current_user_can( 'manage_woocommerce' ), 'read', $request );
	}

	/**
	 * Checks if a given request has access to create items.
	 *
	 * @since 1.1.6
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 * @return WP_Error|bool True if the request has access to create items, WP_Error object otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		return apply_filters( 'woo_wallet_rest_check_permissions', current_user_can( 'manage_woocommerce' ), 'create', $request );
	}

}
