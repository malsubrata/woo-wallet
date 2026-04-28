<?php
/**
 * GET  /terawallet/v1/me/transactions
 * GET  /terawallet/v1/me/transactions/{id}
 *
 * Self-scoped transaction list — always filters by `get_current_user_id()`,
 * never by an `email`/`user` parameter. Single-item endpoint returns 404
 * (not 403) when the transaction belongs to a different user, to avoid
 * leaking which ids exist.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Transactions_Controller' ) ) {

	/**
	 * Customer transactions controller.
	 */
	class TeraWallet_REST_Me_Transactions_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/transactions';

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
						'permission_callback' => array( $this, 'check_me_permissions' ),
						'args'                => $this->get_collection_params(),
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
		 * Collection params — no `email` (would be a security smell on a `/me/*` route).
		 *
		 * @return array
		 */
		public function get_collection_params() {
			$params             = parent::get_collection_params();
			$params['per_page'] = array(
				'type'              => 'integer',
				'description'       => __( 'Transactions per page.', 'woo-wallet' ),
				'default'           => 20,
				'minimum'           => 1,
				'maximum'           => 100,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			);
			$params['page']     = array(
				'type'              => 'integer',
				'description'       => __( 'Current page.', 'woo-wallet' ),
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => 'rest_validate_request_arg',
			);
			$params['orderby']  = array(
				'type'              => 'string',
				'description'       => __( 'Order transactions by column.', 'woo-wallet' ),
				'enum'              => array( 'date', 'amount', 'transaction_id' ),
				'default'           => 'transaction_id',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			);
			$params['order']    = array(
				'type'              => 'string',
				'description'       => __( 'Sort direction.', 'woo-wallet' ),
				'enum'              => array( 'asc', 'desc' ),
				'default'           => 'desc',
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			);
			$params['type']     = array(
				'type'              => 'string',
				'description'       => __( 'Filter by transaction type.', 'woo-wallet' ),
				'enum'              => array( 'credit', 'debit' ),
				'sanitize_callback' => 'sanitize_key',
				'validate_callback' => 'rest_validate_request_arg',
			);
			$params['search']   = array(
				'type'              => 'string',
				'description'       => __( 'Filter by note text (substring).', 'woo-wallet' ),
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => 'rest_validate_request_arg',
			);
			return $params;
		}

		/**
		 * List the calling user's transactions.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_items( $request ) {
			$user_id  = $this->current_user_id();
			$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
			$page     = max( 1, (int) $request->get_param( 'page' ) );
			$offset   = ( $page - 1 ) * $per_page;
			$type     = $request->get_param( 'type' );

			$args = array(
				'user_id'  => $user_id,
				'fields'   => 'all_with_meta',
				'nocache'  => true,
				'order_by' => $request->get_param( 'orderby' ) ? $request->get_param( 'orderby' ) : 'transaction_id',
				'order'    => strtoupper( $request->get_param( 'order' ) ? $request->get_param( 'order' ) : 'DESC' ),
			);
			if ( ! empty( $type ) ) {
				$args['where'] = array(
					array(
						'key'      => 'type',
						'value'    => $type,
						'operator' => '=',
					),
				);
			}

			$args = apply_filters( 'terawallet_rest_me_transactions_query_args', $args, $request );

			// get_wallet_transactions() accepts limit as int or "offset,limit" string.
			$args['limit'] = $offset > 0 ? "{$offset},{$per_page}" : $per_page;
			$rows          = get_wallet_transactions( $args );

			$items = array();
			foreach ( (array) $rows as $row ) {
				$prepared = $this->prepare_item_for_response( $row, $request );
				$items[]  = $this->prepare_response_for_collection( $prepared );
			}

			$total    = function_exists( 'get_wallet_transactions_count' ) ? (int) get_wallet_transactions_count( $user_id ) : count( $items );
			$response = new WP_REST_Response( $items, 200 );
			$response = $this->add_pagination_headers( $response, $total, $page, $per_page );

			// ETag for client-side revalidation: hash the (user, total, latest_id) tuple.
			$latest_id = isset( $rows[0]->transaction_id ) ? (int) $rows[0]->transaction_id : 0;
			$response->header( 'ETag', '"' . md5( $user_id . ':' . $total . ':' . $latest_id ) . '"' );

			return $this->private_no_store( $response );
		}

		/**
		 * Read a single transaction by id (ownership-checked, 404 otherwise).
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_item( $request ) {
			$id  = (int) $request['id'];
			$row = $this->fetch_transaction( $id );
			if ( ! $row ) {
				return $this->error( 'rest_transaction_not_found', __( 'Transaction not found.', 'woo-wallet' ), 404 );
			}
			$ownership = $this->confirm_owner( isset( $row->user_id ) ? (int) $row->user_id : 0, 'transaction' );
			if ( is_wp_error( $ownership ) ) {
				return $ownership;
			}
			$response = $this->prepare_item_for_response( $row, $request );
			return $this->private_no_store( $response );
		}

		/**
		 * Direct-table read by id.
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
		 * Prepare a transaction row for the response. Mirrors the wc/v3 controller's
		 * shape but publishes only the `view` context fields a customer should see.
		 *
		 * @param object          $transaction Raw row.
		 * @param WP_REST_Request $request     Request.
		 * @return WP_REST_Response
		 */
		public function prepare_item_for_response( $transaction, $request ) {
			$data = array(
				'id'        => isset( $transaction->transaction_id ) ? (int) $transaction->transaction_id : 0,
				'type'      => isset( $transaction->type ) ? $transaction->type : '',
				'amount'    => isset( $transaction->amount ) ? (float) $transaction->amount : 0,
				'currency'  => isset( $transaction->currency ) ? $transaction->currency : get_woocommerce_currency(),
				'details'   => isset( $transaction->details ) ? $transaction->details : '',
				'date'      => isset( $transaction->date ) ? mysql_to_rfc3339( $transaction->date ) : '',
				'formatted' => array(
					'amount' => isset( $transaction->amount ) ? wp_strip_all_tags( wc_price( $transaction->amount, woo_wallet_wc_price_args( $this->current_user_id() ) ) ) : '',
				),
			);

			$context = ! empty( $request['context'] ) ? $request['context'] : 'view';
			$data    = $this->add_additional_fields_to_object( $data, $request );
			$data    = $this->filter_response_by_context( $data, $context );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base . '/' . $data['id'] ) );
			$response->add_link( 'collection', rest_url( $this->namespace . '/' . $this->rest_base ) );

			return apply_filters( 'terawallet_rest_me_prepare_transaction', $response, $transaction, $request );
		}

		/**
		 * Schema (view-context only — no `user_id`/`deleted` exposed to customer).
		 *
		 * @return array
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'terawallet_me_transaction',
				'type'       => 'object',
				'properties' => array(
					'id'        => array(
						'type'     => 'integer',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'type'      => array(
						'type'     => 'string',
						'enum'     => array( 'credit', 'debit' ),
						'context'  => array( 'view' ),
						'readonly' => true,
					),
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
					'details'   => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'date'      => array(
						'type'     => 'string',
						'format'   => 'date-time',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'formatted' => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'amount' => array( 'type' => 'string' ),
						),
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
