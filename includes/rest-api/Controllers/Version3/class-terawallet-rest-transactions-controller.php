<?php
/**
 * REST API Wallet controller
 *
 * Handles requests to the /wallet endpoint.
 *
 * @author   Subrata Mal
 * @category API
 * @since   1.3.23
 * @package StandaleneTech
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API TeraWallet transactions controller class.
 *
 * @extends TeraWallet_REST_Controller_Base
 */
class TeraWallet_REST_Transactions_Controller extends TeraWallet_REST_Controller_Base {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc/v3';

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
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
					'args'                => array_merge(
						$this->get_endpoint_args_for_item_schema( WP_REST_Server::CREATABLE ),
						array(
							'email'  => array(
								'required'          => true,
								'type'              => 'string',
								'description'       => __( 'User email address', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_email',
								'validate_callback' => 'rest_validate_request_arg',
								'format'            => 'email',
							),
							'type'   => array(
								'required'          => true,
								'type'              => 'string',
								'enum'              => array( 'credit', 'debit' ),
								'description'       => __( 'Wallet transaction type.', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_key',
								'validate_callback' => 'rest_validate_request_arg',
							),
							'amount' => array(
								'required'          => true,
								'description'       => __( 'Wallet transaction amount.', 'woo-wallet' ),
								'type'              => 'number',
								'minimum'           => 0.01,
								'sanitize_callback' => function ( $value ) {
									return function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $value ) : (float) $value;
								},
								'validate_callback' => 'rest_validate_request_arg',
							),
							'note'   => array(
								'required'          => false,
								'description'       => __( 'Wallet transaction details.', 'woo-wallet' ),
								'type'              => 'string',
								'sanitize_callback' => 'sanitize_textarea_field',
								'validate_callback' => 'rest_validate_request_arg',
							),
						)
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)',
			array(
				'args'   => array(
					'id' => array(
						'description' => __( 'Unique identifier for the transaction.', 'woo-wallet' ),
						'type'        => 'integer',
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'context' => $this->get_context_param( array( 'default' => 'view' ) ),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/balance',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_balance' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
					'args'                => array_merge(
						$this->get_endpoint_args_for_item_schema( WP_REST_Server::READABLE ),
						array(
							'email' => array(
								'required'          => true,
								'type'              => 'string',
								'description'       => __( 'User email address', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_email',
								'validate_callback' => 'rest_validate_request_arg',
								'format'            => 'email',
							),
						)
					),
				),
				'schema' => array( $this, 'get_public_batch_schema' ),
			)
		);
	}
	/**
	 * Collection params for get request.
	 *
	 * @return array
	 */
	public function get_collection_params() {
		$params             = parent::get_collection_params();
		$params['email']    = array(
			'required'          => true,
			'type'              => 'string',
			'description'       => __( 'User email address', 'woo-wallet' ),
			'sanitize_callback' => 'sanitize_email',
			'validate_callback' => 'rest_validate_request_arg',
			'format'            => 'email',
		);
		$params['per_page'] = array(
			'required'          => false,
			'type'              => 'integer',
			'description'       => __( 'Transactions per page', 'woo-wallet' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'default'           => 10,
			'minimum'           => 1,
			'maximum'           => 100,
		);
		$params['page']     = array(
			'required'          => false,
			'type'              => 'integer',
			'description'       => __( 'Current page', 'woo-wallet' ),
			'sanitize_callback' => 'absint',
			'validate_callback' => 'rest_validate_request_arg',
			'default'           => 1,
			'minimum'           => 1,
		);
		$params['orderby']  = array(
			'required'          => false,
			'type'              => 'string',
			'description'       => __( 'Order transactions by column.', 'woo-wallet' ),
			'enum'              => array( 'date', 'amount', 'transaction_id' ),
			'default'           => 'transaction_id',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['order']    = array(
			'required'          => false,
			'type'              => 'string',
			'description'       => __( 'Sort direction.', 'woo-wallet' ),
			'enum'              => array( 'asc', 'desc' ),
			'default'           => 'desc',
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['type']     = array(
			'required'          => false,
			'type'              => 'string',
			'description'       => __( 'Filter by transaction type.', 'woo-wallet' ),
			'enum'              => array( 'credit', 'debit' ),
			'sanitize_callback' => 'sanitize_key',
			'validate_callback' => 'rest_validate_request_arg',
		);
		$params['currency'] = array(
			'required'          => false,
			'type'              => 'string',
			'description'       => __( 'Filter rows by ISO 4217 currency code (post-1.6 ledger only).', 'woo-wallet' ),
			'pattern'           => '^[A-Z]{3}$',
			'sanitize_callback' => function ( $v ) {
				return is_string( $v ) ? strtoupper( trim( $v ) ) : '';
			},
			'validate_callback' => function ( $v ) {
				return '' === $v || ( is_string( $v ) && (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( trim( $v ) ) ) );
			},
		);
		$params['category'] = array(
			'required'          => false,
			'type'              => 'string',
			'description'       => __( 'Filter by transaction category. Matches the _type transaction meta. Accepts a single value or a comma-separated list.', 'woo-wallet' ),
			'sanitize_callback' => 'sanitize_text_field',
			'validate_callback' => 'rest_validate_request_arg',
		);
		return $params;
	}

	/**
	 * Check whether a given request has permission to read transactions.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_items_permissions_check( $request ) {
		return $this->check_capability( 'read', $request );
	}

	/**
	 * Check whether a given request has permission to read a single transaction.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function get_item_permissions_check( $request ) {
		return $this->check_capability( 'read', $request );
	}

	/**
	 * Check whether a given request has permission to create new transactions.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function create_item_permissions_check( $request ) {
		return $this->check_capability( 'create', $request );
	}
	/**
	 * Get all wallet transactions
	 *
	 * @param WP_REST_Request $request request.
	 * @return WP_REST_Response | WP_Error
	 */
	public function get_items( $request ) {
		$params = $request->get_params();
		$user   = get_user_by( 'email', $params['email'] );
		if ( ! $user ) {
			return $this->error( 'terawallet_rest_invalid_email', __( 'Invalid User.', 'woo-wallet' ), 404 );
		}

		$per_page = max( 1, min( 100, (int) $params['per_page'] ) );
		$page     = max( 1, (int) $params['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$args = array(
			'user_id'  => $user->ID,
			'fields'   => 'all_with_meta',
			'nocache'  => true,
			'order_by' => 'transaction_id' === $params['orderby'] ? 'transaction_id' : $params['orderby'],
			'order'    => strtoupper( $params['order'] ),
		);
		if ( ! empty( $params['type'] ) ) {
			$args['where'][] = array(
				'key'      => 'type',
				'value'    => $params['type'],
				'operator' => '=',
			);
		}
		if ( ! empty( $params['currency'] ) ) {
			$args['where'][] = array(
				'key'      => 'currency',
				'value'    => $params['currency'],
				'operator' => '=',
			);
		}
		if ( ! empty( $params['category'] ) ) {
			$args['category'] = sanitize_text_field( $params['category'] );
		}
		$args = apply_filters( 'woo_wallet_rest_api_get_items_args', $args, $request );

		// get_wallet_transactions() accepts limit as either an integer or a
		// strict "offset,limit" string (regex ^\d+,\d+$ — no whitespace).
		$args['limit'] = $offset > 0 ? "{$offset},{$per_page}" : $per_page;
		$transactions  = get_wallet_transactions( $args );

		$items = array();
		foreach ( (array) $transactions as $transaction ) {
			$prepared = $this->prepare_item_for_response( $transaction, $request );
			$items[]  = $this->prepare_response_for_collection( $prepared );
		}

		// Filter-aware total: pass the same args so X-WP-Total reflects type/currency/category/date filters.
		$count_args = $args;
		unset( $count_args['limit'], $count_args['order_by'], $count_args['order'], $count_args['fields'], $count_args['nocache'] );
		$total    = function_exists( 'get_wallet_transactions_count' ) ? (int) get_wallet_transactions_count( $count_args ) : count( $items );
		$response = new WP_REST_Response( $items, 200 );
		return $this->add_pagination_headers( $response, $total, $page, $per_page );
	}

	/**
	 * Get a single wallet transaction by id.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response | WP_Error
	 */
	public function get_item( $request ) {
		$id          = (int) $request['id'];
		$transaction = $this->fetch_transaction( $id );
		if ( ! $transaction ) {
			return $this->error( 'terawallet_rest_transaction_not_found', __( 'Transaction not found.', 'woo-wallet' ), 404 );
		}
		return $this->prepare_item_for_response( $transaction, $request );
	}

	/**
	 * Fetch a single transaction row by id, or null if missing/deleted.
	 *
	 * @param int $id Transaction id.
	 * @return object|null
	 */
	protected function fetch_transaction( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = %d AND deleted = 0",
				$id
			)
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $row ? $row : null;
	}
	/**
	 * Get user wallet balance.
	 *
	 * @param WP_REST_Request $request request.
	 * @return WP_REST_Request | WP_Error
	 */
	public function get_balance( $request ) {
		// get parameters from request.
		$params = $request->get_params();
		$user   = get_user_by( 'email', $params['email'] );
		if ( ! $user ) {
			return new WP_Error( 'terawallet_rest_invalid_email', __( 'Invalid User.', 'woo-wallet' ), array( 'status' => 404 ) );
		}
		$balance = woo_wallet()->wallet->get_wallet_balance( $user->ID, 'edit' );
		return new WP_REST_Response(
			array(
				'balance'  => $balance,
				'currency' => get_woocommerce_currency(),
			),
			200
		);
	}
	/**
	 * Create new wallet transaction.
	 *
	 * @param WP_REST_Request $request request.
	 * @return WP_REST_Response | WP_Error
	 */
	public function create_item( $request ) {
		$params = $request->get_params();
		$user   = get_user_by( 'email', $params['email'] );
		if ( ! $user ) {
			return new WP_Error( 'terawallet_rest_invalid_email', __( 'Invalid User.', 'woo-wallet' ), array( 'status' => 404 ) );
		}
		if ( ! isset( $params['type'], $params['amount'] ) ) {
			return new WP_Error( 'terawallet_rest_invalid_request', __( 'Missing required parameter: type or amount.', 'woo-wallet' ), array( 'status' => 400 ) );
		}
		$note           = isset( $params['note'] ) ? $params['note'] : '';
		$transaction_id = false;
		if ( 'credit' === $params['type'] ) {
			$transaction_id = woo_wallet()->wallet->credit( $user->ID, $params['amount'], $note );
		} elseif ( 'debit' === $params['type'] ) {
			$transaction_id = woo_wallet()->wallet->debit( $user->ID, $params['amount'], $note );
		} else {
			return new WP_Error( 'terawallet_rest_invalid_type', __( 'Invalid transaction type. Must be credit or debit.', 'woo-wallet' ), array( 'status' => 400 ) );
		}
		if ( ! $transaction_id ) {
			return new WP_Error( 'terawallet_rest_transaction_failed', __( 'Wallet transaction could not be recorded.', 'woo-wallet' ), array( 'status' => 500 ) );
		}
		return new WP_REST_Response(
			array(
				'response' => 'success',
				'id'       => $transaction_id,
			),
			200
		);
	}

	/**
	 * Prepare a wallet transaction row for the REST response.
	 *
	 * @param object          $transaction Raw transaction row from get_wallet_transactions().
	 * @param WP_REST_Request $request     The request.
	 * @return WP_REST_Response
	 */
	public function prepare_item_for_response( $transaction, $request ) {
		$data    = $this->build_transaction_data( $transaction, $request );
		$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
		$data    = $this->add_additional_fields_to_object( $data, $request );
		$data    = $this->filter_response_by_context( $data, $context );

		$response = rest_ensure_response( $data );
		$response->add_links( $this->prepare_links( $transaction, $request ) );

		return apply_filters( 'woo_wallet_rest_prepare_transaction', $response, $transaction, $request );
	}

	/**
	 * Build the standard `_links` envelope for a transaction.
	 *
	 * @param object          $transaction Raw transaction row.
	 * @param WP_REST_Request $request     The request.
	 * @return array
	 */
	protected function prepare_links( $transaction, $request ) {
		$id    = isset( $transaction->transaction_id ) ? (int) $transaction->transaction_id : 0;
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, $id ) ),
			),
			'collection' => array(
				'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ),
			),
		);
		if ( ! empty( $transaction->user_id ) ) {
			$links['user'] = array(
				'href'       => rest_url( sprintf( 'wp/v2/users/%d', (int) $transaction->user_id ) ),
				'embeddable' => true,
			);
		}
		return $links;
	}

	/**
	 * Item schema for a wallet transaction.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wallet_transaction',
			'type'       => 'object',
			'properties' => array(
				'id'       => array(
					'description' => __( 'Unique identifier for the transaction.', 'woo-wallet' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'user_id'  => array(
					'description' => __( 'User ID this transaction belongs to.', 'woo-wallet' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
				),
				'user'                => array(
					'description' => __( 'Embedded user block (login, email, display_name, avatar_url).', 'woo-wallet' ),
					'type'        => array( 'object', 'null' ),
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'created_by'          => array(
					'description' => __( 'User id that recorded the transaction (admin or 0 for system).', 'woo-wallet' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'formatted'           => array(
					'description' => __( 'Server-rendered display strings (amount, original_amount, date, type/category labels).', 'woo-wallet' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
				'type'     => array(
					'description' => __( 'Transaction type: credit or debit.', 'woo-wallet' ),
					'type'        => 'string',
					'enum'        => array( 'credit', 'debit' ),
					'context'     => array( 'view', 'edit' ),
				),
				'amount'   => array(
					'description' => __( 'Transaction amount.', 'woo-wallet' ),
					'type'        => 'number',
					'context'     => array( 'view', 'edit' ),
				),
				'currency' => array(
					'description' => __( 'Currency code (canonical ledger currency for this row).', 'woo-wallet' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'original_amount'   => array(
					'description' => __( 'Source amount as the customer saw it before any currency conversion. Null on pre-1.6 rows.', 'woo-wallet' ),
					'type'        => array( 'number', 'null' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'original_currency' => array(
					'description' => __( 'Source currency the customer transacted in. Null on pre-1.6 rows.', 'woo-wallet' ),
					'type'        => array( 'string', 'null' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'original_rate'     => array(
					'description' => __( 'Conversion rate applied at write time (original_currency → currency). Null on pre-1.6 rows.', 'woo-wallet' ),
					'type'        => array( 'number', 'null' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'mode'              => array(
					'description' => __( 'Storage mode this row was written in: 0 = single_base, 1 = per_currency.', 'woo-wallet' ),
					'type'        => 'integer',
					'enum'        => array( 0, 1 ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'details'  => array(
					'description' => __( 'Note attached to the transaction.', 'woo-wallet' ),
					'type'        => 'string',
					'context'     => array( 'view', 'edit' ),
				),
				'date'     => array(
					'description' => __( 'Date the transaction was recorded, in RFC3339 format.', 'woo-wallet' ),
					'type'        => 'string',
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'deleted'             => array(
					'description' => __( 'Whether the transaction is marked deleted.', 'woo-wallet' ),
					'type'        => 'boolean',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'category'            => array(
					'description' => __( 'Typed category derived from the _type transaction meta. Both namespaces use the same enum values.', 'woo-wallet' ),
					'type'        => 'string',
					'enum'        => array( 'topup', 'cashback', 'cashback_adjustment', 'cashback_refund', 'partial_payment', 'transfer', 'refund', 'adjustment', 'other' ),
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'cashback_expires_at' => array(
					'description' => __( 'ISO-8601 timestamp at which this cashback row expires. Null when no expiry is set. Core does not enforce expiry; intended for Pro/addon use.', 'woo-wallet' ),
					'type'        => array( 'string', 'null' ),
					'format'      => 'date-time',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);
		return $this->add_additional_fields_schema( $schema );
	}

}

// Back-compat alias for the pre-rename class name. Remove in 2.1.
class_alias( 'TeraWallet_REST_Transactions_Controller', 'WC_REST_TeraWallet_V3_Controller' );
