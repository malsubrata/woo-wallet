<?php
/**
 * REST API: terawallet/v1/admin/transactions
 *
 * Powers the admin DataView for the wallet transaction ledger across all
 * users. Mirrors every action the legacy `Woo_Wallet_Transaction_Details`
 * and `Woo_Wallet_Balance_Details` WP_List_Tables expose:
 *   - GET    /admin/transactions          paginated, filterable list
 *   - POST   /admin/transactions          create credit/debit
 *   - GET    /admin/transactions/{id}     single row
 *   - PATCH  /admin/transactions/{id}     edit `details`
 *   - DELETE /admin/transactions/{id}     soft/hard delete (?force=true)
 *   - POST   /admin/transactions/bulk     bulk credit | debit | delete
 *
 * Auth: cookie + nonce; capability `manage_woocommerce` via the base
 * controller's check_capability(). Idempotency: every state-changing route
 * runs through WooWallet_Idempotency for replay safety.
 *
 * @package StandaleneTech
 * @since   1.6.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin transactions controller.
 */
class TeraWallet_REST_Admin_Transactions_Controller extends TeraWallet_REST_Controller_Base {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'terawallet/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'admin/transactions';

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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'permissions_read' ),
					'args'                => $this->get_collection_params(),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions_write' ),
					'args'                => $this->get_create_args(),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/bulk',
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'bulk_action' ),
					'permission_callback' => array( $this, 'permissions_write' ),
					'args'                => $this->get_bulk_args(),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			array(
				'args'   => array(
					'id' => array(
						'type'        => 'integer',
						'description' => __( 'Transaction id.', 'woo-wallet' ),
					),
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'permissions_read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_item' ),
					'permission_callback' => array( $this, 'permissions_write' ),
					'args'                => array(
						'details' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_item' ),
					'permission_callback' => array( $this, 'permissions_write' ),
					'args'                => array(
						'force' => array(
							'type'    => 'boolean',
							'default' => false,
						),
					),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/* ---------------- permissions ---------------- */

	public function permissions_read( $request ) {
		return $this->check_capability( 'read', $request );
	}

	public function permissions_write( $request ) {
		return $this->check_capability( 'edit', $request );
	}

	/* ---------------- argument schemas ---------------- */

	public function get_collection_params() {
		return array(
			'page'              => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1, 'sanitize_callback' => 'absint' ),
			'per_page'          => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 200, 'sanitize_callback' => 'absint' ),
			'orderby'           => array(
				'type'    => 'string',
				// `id` is an alias for `transaction_id` so DataView field ids map 1:1.
				'enum'    => array( 'id', 'transaction_id', 'date', 'amount', 'currency', 'type', 'user_id' ),
				'default' => 'transaction_id',
			),
			'order'             => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'desc' ),
			'user_id'           => array( 'type' => 'integer', 'sanitize_callback' => 'absint' ),
			'user_ids'          => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'type'              => array( 'type' => 'string', 'enum' => array( 'credit', 'debit' ) ),
			'currency'          => array( 'type' => 'string' ),
			'original_currency' => array( 'type' => 'string' ),
			'category'          => array( 'type' => 'string' ),
			'after'             => array( 'type' => 'string', 'format' => 'date-time' ),
			'before'            => array( 'type' => 'string', 'format' => 'date-time' ),
			'include'           => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'exclude'           => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'include_deleted'   => array( 'type' => 'boolean', 'default' => false ),
			'search'            => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
		);
	}

	protected function get_create_args() {
		return array(
			'user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
			'type'    => array( 'type' => 'string', 'required' => true, 'enum' => array( 'credit', 'debit' ) ),
			'amount'  => array( 'type' => 'number', 'required' => true, 'minimum' => 0.01 ),
			'currency' => array( 'type' => 'string' ),
			'note'    => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
		);
	}

	protected function get_bulk_args() {
		return array(
			'action'   => array( 'type' => 'string', 'required' => true, 'enum' => array( 'credit', 'debit', 'delete' ) ),
			'user_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'ids'      => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ),
			'amount'   => array( 'type' => 'number', 'minimum' => 0.01 ),
			'currency' => array( 'type' => 'string' ),
			'note'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
			'force'    => array( 'type' => 'boolean', 'default' => false ),
		);
	}

	/* ---------------- handlers ---------------- */

	public function get_items( $request ) {
		$params = $request->get_params();

		// Compose user_ids from a free-text search on user fields.
		$user_ids = isset( $params['user_ids'] ) ? array_filter( array_map( 'absint', (array) $params['user_ids'] ) ) : array();
		if ( ! empty( $params['search'] ) ) {
			$user_query = new WP_User_Query(
				array(
					'search'         => '*' . $params['search'] . '*',
					'search_columns' => array( 'user_login', 'user_email', 'display_name' ),
					'fields'         => 'ID',
					'number'         => 500,
				)
			);
			$matched_ids = array_map( 'absint', $user_query->get_results() );
			if ( ! empty( $matched_ids ) ) {
				$user_ids = array_unique( array_merge( $user_ids, $matched_ids ) );
			}
			// If we have a search but no user_id hits, still let the LIKE on details run.
		}

		$args = array(
			'fields'          => 'all_with_meta',
			'nocache'         => true,
			'order_by'        => 'id' === $params['orderby'] ? 'transaction_id' : $params['orderby'],
			'order'           => strtoupper( $params['order'] ),
			'include_deleted' => ! empty( $params['include_deleted'] ),
		);
		if ( ! empty( $params['user_id'] ) ) {
			$args['user_id'] = (int) $params['user_id'];
		} elseif ( ! empty( $user_ids ) ) {
			$args['user_ids'] = $user_ids;
		} else {
			$args['user_id'] = 0; // listing across all users.
		}
		if ( ! empty( $params['type'] ) ) {
			$args['where'][] = array( 'key' => 'type', 'value' => $params['type'], 'operator' => '=' );
		}
		if ( ! empty( $params['currency'] ) ) {
			$args['where'][] = array( 'key' => 'currency', 'value' => strtoupper( $params['currency'] ), 'operator' => '=' );
		}
		if ( ! empty( $params['original_currency'] ) ) {
			$args['where'][] = array( 'key' => 'original_currency', 'value' => strtoupper( $params['original_currency'] ), 'operator' => '=' );
		}
		if ( ! empty( $params['category'] ) ) {
			$args['category'] = $params['category'];
		}
		if ( ! empty( $params['after'] ) ) {
			$args['after'] = gmdate( 'Y-m-d H:i:s', strtotime( $params['after'] ) );
		}
		if ( ! empty( $params['before'] ) ) {
			$args['before'] = gmdate( 'Y-m-d H:i:s', strtotime( $params['before'] ) );
		}
		if ( ! empty( $params['include'] ) ) {
			$args['include'] = array_map( 'absint', (array) $params['include'] );
		}
		if ( ! empty( $params['exclude'] ) ) {
			$args['exclude'] = array_map( 'absint', (array) $params['exclude'] );
		}
		if ( ! empty( $params['search'] ) ) {
			$args['search'] = $params['search'];
		}

		$per_page = max( 1, min( 200, (int) $params['per_page'] ) );
		$page     = max( 1, (int) $params['page'] );
		$offset   = ( $page - 1 ) * $per_page;
		$args['limit'] = $offset > 0 ? "{$offset},{$per_page}" : $per_page;

		$args = apply_filters( 'woo_wallet_rest_admin_transactions_query_args', $args, $request );

		$transactions = get_wallet_transactions( $args );

		$items = array();
		foreach ( (array) $transactions as $row ) {
			$prepared = $this->prepare_item_for_response( $row, $request );
			$items[]  = $this->prepare_response_for_collection( $prepared );
		}

		$count_args = $args;
		unset( $count_args['limit'], $count_args['order_by'], $count_args['order'], $count_args['fields'], $count_args['nocache'] );
		$total = (int) get_wallet_transactions_count( $count_args );

		$response = new WP_REST_Response( $items, 200 );
		return $this->add_pagination_headers( $response, $total, $page, $per_page );
	}

	public function get_item( $request ) {
		global $wpdb;
		$id  = (int) $request['id'];
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		if ( ! $row ) {
			return $this->error( 'terawallet_rest_transaction_not_found', __( 'Transaction not found.', 'woo-wallet' ), 404 );
		}
		$meta_rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->base_prefix}woo_wallet_transaction_meta WHERE transaction_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row->meta = $meta_rows ? $meta_rows : array();
		return $this->prepare_item_for_response( $row, $request );
	}

	public function create_item( $request ) {
		$params = $request->get_params();
		$user   = get_userdata( (int) $params['user_id'] );
		if ( ! $user ) {
			return $this->error( 'terawallet_rest_invalid_user', __( 'Invalid user id.', 'woo-wallet' ), 404 );
		}

		$idem_key = $this->require_idempotency_key( $request );
		if ( is_wp_error( $idem_key ) ) {
			return $idem_key;
		}
		$result = WooWallet_Idempotency::run(
			get_current_user_id(),
			'admin_txn_create:' . $idem_key,
			function () use ( $params, $request ) {
				$call_args = array();
				if ( ! empty( $params['currency'] ) ) {
					$call_args['currency'] = strtoupper( $params['currency'] );
				}
				$note = isset( $params['note'] ) ? $params['note'] : '';
				$txn_id = 'credit' === $params['type']
					? woo_wallet()->wallet->credit( (int) $params['user_id'], (float) $params['amount'], $note, $call_args ?: null )
					: woo_wallet()->wallet->debit( (int) $params['user_id'], (float) $params['amount'], $note, $call_args ?: null );
				if ( ! $txn_id ) {
					return $this->error( 'terawallet_rest_transaction_failed', __( 'Wallet transaction could not be recorded.', 'woo-wallet' ), 500 );
				}
				$request['id'] = $txn_id;
				return $this->get_item( $request );
			}
		);
		return $result;
	}

	public function update_item( $request ) {
		$id      = (int) $request['id'];
		$details = (string) $request['details'];
		$ok      = woo_wallet()->wallet->update_transaction_details( $id, $details );
		if ( is_wp_error( $ok ) ) {
			return $this->error( $ok->get_error_code(), $ok->get_error_message(), 400 );
		}
		return $this->get_item( $request );
	}

	public function delete_item( $request ) {
		$id   = (int) $request['id'];
		$hard = ! empty( $request['force'] );
		$ok   = woo_wallet()->wallet->delete_transaction( $id, $hard );
		if ( is_wp_error( $ok ) ) {
			$code = 'woo_wallet_transaction_not_found' === $ok->get_error_code() ? 404 : 400;
			return $this->error( $ok->get_error_code(), $ok->get_error_message(), $code );
		}
		return new WP_REST_Response( array( 'deleted' => true, 'id' => $id, 'force' => $hard ), 200 );
	}

	public function bulk_action( $request ) {
		$params = $request->get_params();
		$action = $params['action'];

		$idem_key = $this->require_idempotency_key( $request );
		if ( is_wp_error( $idem_key ) ) {
			return $idem_key;
		}

		$results = array();
		if ( 'credit' === $action || 'debit' === $action ) {
			$user_ids = array_filter( array_map( 'absint', (array) ( $params['user_ids'] ?? array() ) ) );
			$amount   = (float) ( $params['amount'] ?? 0 );
			$note     = isset( $params['note'] ) ? $params['note'] : '';
			$call_args = array();
			if ( ! empty( $params['currency'] ) ) {
				$call_args['currency'] = strtoupper( $params['currency'] );
			}
			if ( ! $user_ids || $amount <= 0 ) {
				return $this->error( 'terawallet_rest_bulk_invalid', __( 'user_ids and amount are required.', 'woo-wallet' ), 400 );
			}
			$current_user = get_current_user_id();
			// Per-row idempotency: a retry after a mid-loop process death
			// must not re-credit users who already received the credit on
			// the first attempt. The wrapping `admin_txn_bulk` key still
			// caches the envelope so an identical-shape replay returns the
			// original response verbatim.
			foreach ( $user_ids as $uid ) {
				$row = WooWallet_Idempotency::run(
					$current_user,
					'admin_txn_bulk_row:' . $action . ':' . $idem_key . ':' . $uid,
					function () use ( $action, $uid, $amount, $note, $call_args ) {
						$args   = $call_args ?: null;
						$txn_id = 'credit' === $action
							? woo_wallet()->wallet->credit( $uid, $amount, $note, $args )
							: woo_wallet()->wallet->debit( $uid, $amount, $note, $args );
						return new WP_REST_Response(
							array( 'user_id' => $uid, 'transaction_id' => (int) $txn_id, 'ok' => (bool) $txn_id ),
							200
						);
					}
				);
				$results[] = $row instanceof WP_REST_Response ? $row->get_data() : array( 'user_id' => $uid, 'transaction_id' => 0, 'ok' => false );
			}
		} else {
			$ids   = array_filter( array_map( 'absint', (array) ( $params['ids'] ?? array() ) ) );
			$force = ! empty( $params['force'] );
			if ( ! $ids ) {
				return $this->error( 'terawallet_rest_bulk_invalid', __( 'ids are required.', 'woo-wallet' ), 400 );
			}
			foreach ( $ids as $id ) {
				$ok        = woo_wallet()->wallet->delete_transaction( $id, $force );
				$results[] = array( 'id' => $id, 'ok' => ! is_wp_error( $ok ), 'error' => is_wp_error( $ok ) ? $ok->get_error_message() : null );
			}
		}

		$response = new WP_REST_Response( array( 'action' => $action, 'results' => $results ), 200 );
		// Cache the aggregate envelope under the top-level key so a verbatim
		// replay of the whole request returns the same response.
		return WooWallet_Idempotency::run(
			get_current_user_id(),
			'admin_txn_bulk:' . $action . ':' . $idem_key,
			function () use ( $response ) {
				return $response;
			}
		);
	}

	/* ---------------- projection ---------------- */

	public function prepare_item_for_response( $transaction, $request ) {
		$data    = $this->build_transaction_data( $transaction, $request );
		$response = rest_ensure_response( $data );
		$response->add_links(
			array(
				'self'       => array( 'href' => rest_url( sprintf( '%s/%s/%d', $this->namespace, $this->rest_base, (int) $data['id'] ) ) ),
				'collection' => array( 'href' => rest_url( sprintf( '%s/%s', $this->namespace, $this->rest_base ) ) ),
				'user'       => array( 'href' => rest_url( sprintf( 'wp/v2/users/%d', (int) $data['user_id'] ) ), 'embeddable' => true ),
			)
		);
		return apply_filters( 'woo_wallet_rest_prepare_admin_transaction', $response, $transaction, $request );
	}

	public function get_item_schema() {
		return array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'wallet_admin_transaction',
			'type'       => 'object',
			'properties' => array(
				'id'                  => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
				'user_id'             => array( 'type' => 'integer', 'context' => array( 'view', 'edit' ) ),
				'user'                => array( 'type' => array( 'object', 'null' ), 'context' => array( 'view', 'edit' ), 'readonly' => true ),
				'type'                => array( 'type' => 'string', 'enum' => array( 'credit', 'debit' ), 'context' => array( 'view', 'edit' ) ),
				'amount'              => array( 'type' => 'number', 'context' => array( 'view', 'edit' ) ),
				'currency'            => array( 'type' => 'string', 'context' => array( 'view', 'edit' ) ),
				'original_amount'     => array( 'type' => array( 'number', 'null' ), 'context' => array( 'view' ), 'readonly' => true ),
				'original_currency'   => array( 'type' => array( 'string', 'null' ), 'context' => array( 'view' ), 'readonly' => true ),
				'original_rate'       => array( 'type' => array( 'number', 'null' ), 'context' => array( 'view' ), 'readonly' => true ),
				'mode'                => array( 'type' => 'integer', 'enum' => array( 0, 1 ), 'context' => array( 'view' ), 'readonly' => true ),
				'details'             => array( 'type' => 'string', 'context' => array( 'view', 'edit' ) ),
				'date'                => array( 'type' => 'string', 'format' => 'date-time', 'context' => array( 'view' ), 'readonly' => true ),
				'created_by'          => array( 'type' => 'integer', 'context' => array( 'view' ), 'readonly' => true ),
				'deleted'             => array( 'type' => 'boolean', 'context' => array( 'view' ), 'readonly' => true ),
				'category'            => array( 'type' => 'string', 'context' => array( 'view' ), 'readonly' => true ),
				'cashback_expires_at' => array( 'type' => array( 'string', 'null' ), 'format' => 'date-time', 'context' => array( 'view' ), 'readonly' => true ),
				'formatted'           => array( 'type' => 'object', 'context' => array( 'view', 'edit' ), 'readonly' => true ),
			),
		);
	}
}
