<?php
/**
 * REST API: terawallet/v1/admin/users
 *
 * Powers the admin DataView for the per-user wallet balance summary,
 * mirroring the legacy `Woo_Wallet_Balance_Details` WP_List_Table:
 *   - GET  /admin/users                        paginated user list with totals
 *   - GET  /admin/users/{id}/balance           multicurrency balance breakdown
 *   - POST /admin/users/{id}/transactions/purge  purge logs (mode + balance handling)
 *
 * @package StandaleneTech
 * @since   1.6.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin users controller.
 */
class TeraWallet_REST_Admin_Users_Controller extends TeraWallet_REST_Admin_Controller_Base {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'admin/users';

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
					'args'                => array(
						'page'     => array( 'type' => 'integer', 'default' => 1, 'minimum' => 1 ),
						'per_page' => array( 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ),
						'orderby'  => array( 'type' => 'string', 'enum' => array( 'login', 'email', 'display_name', 'registered', 'balance' ), 'default' => 'login' ),
						'order'    => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'default' => 'asc' ),
						'search'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_text_field' ),
						'role'     => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_key' ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/balance',
			array(
				'args' => array( 'id' => array( 'type' => 'integer' ) ),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_balance' ),
					'permission_callback' => array( $this, 'permissions_read' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>\d+)/transactions/purge',
			array(
				'args' => array( 'id' => array( 'type' => 'integer' ) ),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'purge_transactions' ),
					'permission_callback' => array( $this, 'permissions_write' ),
					'args'                => array(
						'delete_mode'      => array( 'type' => 'string', 'enum' => array( 'soft', 'hard' ), 'default' => 'soft' ),
						'balance_handling' => array( 'type' => 'string', 'enum' => array( 'keep', 'wipe' ), 'default' => 'keep' ),
					),
				),
			)
		);
	}

	/**
	 * Override: purge is an edit-context action (harder than create).
	 *
	 * @param WP_REST_Request $request The request.
	 * @return true|WP_Error
	 */
	public function permissions_write( $request ) {
		return $this->check_capability( 'edit', $request );
	}

	public function get_items( $request ) {
		$params = $request->get_params();
		$per    = max( 1, min( 100, (int) $params['per_page'] ) );
		$page   = max( 1, (int) $params['page'] );

		$query_args = array(
			'number' => $per,
			'offset' => ( $page - 1 ) * $per,
			'fields' => 'all_with_meta',
		);
		if ( ! empty( $params['search'] ) ) {
			$query_args['search'] = '*' . $params['search'] . '*';
		}
		if ( ! empty( $params['role'] ) ) {
			$query_args['role'] = $params['role'];
		}
		// Map orderby. `balance` sorts by the cached _current_woo_wallet_balance meta.
		switch ( $params['orderby'] ) {
			case 'balance':
				$query_args['meta_key'] = '_current_woo_wallet_balance'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				$query_args['orderby']  = 'meta_value_num';
				break;
			case 'email':
				$query_args['orderby'] = 'user_email';
				break;
			case 'display_name':
				$query_args['orderby'] = 'display_name';
				break;
			case 'registered':
				$query_args['orderby'] = 'user_registered';
				break;
			default:
				$query_args['orderby'] = 'user_login';
		}
		$query_args['order'] = strtoupper( $params['order'] );

		$user_query = new WP_User_Query( $query_args );
		$users      = $user_query->get_results();
		$items      = array();
		foreach ( $users as $user ) {
			$items[] = $this->prepare_user_row( $user );
		}

		$response = new WP_REST_Response( $items, 200 );
		return $this->add_pagination_headers( $response, (int) $user_query->get_total(), $page, $per );
	}

	/**
	 * Build a user summary row: deposits / spent / cashback / balance / locked,
	 * each reduced to the store base currency using the multicurrency manager.
	 *
	 * @param WP_User $user User object.
	 * @return array
	 */
	protected function prepare_user_row( $user ) {
		$user_id  = (int) $user->ID;
		$balance  = woo_wallet_get_balance_by_currency( $user_id );

		// Since 1.6.3: filter by canonical `category` column slugs.
		// `topup` covers what was historically `credit_purchase` meta;
		// `partial_payment` covers both `purchase` and `partial_payment` meta.
		$deposits = $this->sum_category( $user_id, 'credit', 'topup', $balance['base_currency'] );
		$spent    = $this->sum_category( $user_id, 'debit', 'partial_payment', $balance['base_currency'] );
		$cashback = $this->sum_category( $user_id, 'credit', 'cashback', $balance['base_currency'] );

		$price_args = function_exists( 'woo_wallet_wc_price_args' )
			? woo_wallet_wc_price_args( $user_id, array( 'currency' => $balance['base_currency'] ) )
			: array( 'currency' => $balance['base_currency'] );
		$fmt = function ( $v ) use ( $price_args ) {
			return function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $v, $price_args ) ) : (string) $v;
		};

		return array(
			'id'                     => $user_id,
			'login'                  => $user->user_login,
			'email'                  => $user->user_email,
			'display_name'           => $user->display_name,
			'registered'             => mysql_to_rfc3339( $user->user_registered ),
			'avatar_url'             => get_avatar_url( $user_id ),
			'roles'                  => $user->roles,
			'base_currency'          => $balance['base_currency'],
			'balance'                => $balance['balance_base'],
			'balance_formatted'      => $balance['balance_base_formatted'],
			'by_currency'            => $balance['by_currency'],
			'total_deposits'         => $deposits,
			'total_deposits_formatted' => $fmt( $deposits ),
			'total_spent'            => $spent,
			'total_spent_formatted'  => $fmt( $spent ),
			'cashback_earned'        => $cashback,
			'cashback_earned_formatted' => $fmt( $cashback ),
			'is_locked'              => $balance['is_locked'],
		);
	}

	/**
	 * Sum a category total normalised to the base currency.
	 *
	 * @param int    $user_id   User id.
	 * @param string $type      'credit' or 'debit'.
	 * @param string $sub_type  Canonical category slug (e.g. `topup`, `cashback`).
	 * @param string $base      Base currency code.
	 * @return float
	 */
	protected function sum_category( $user_id, $type, $sub_type, $base ) {
		$rows = get_wallet_transactions(
			array(
				'user_id' => $user_id,
				'fields'  => 'all',
				'nocache' => true,
				'where'   => array(
					array( 'key' => 'type', 'value' => $type ),
					array( 'key' => 'category', 'value' => $sub_type ),
				),
			)
		);
		if ( empty( $rows ) ) {
			return 0.0;
		}
		$manager = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;
		$total   = 0.0;
		foreach ( $rows as $row ) {
			$cur     = isset( $row->currency ) && '' !== $row->currency ? strtoupper( $row->currency ) : $base;
			$total  += $manager ? (float) $manager->convert( $row->amount, $cur, $base ) : (float) $row->amount;
		}
		return $total;
	}

	public function get_balance( $request ) {
		$id   = (int) $request['id'];
		$user = get_userdata( $id );
		if ( ! $user ) {
			return $this->error( 'terawallet_rest_invalid_user', __( 'Invalid user id.', 'woo-wallet' ), 404 );
		}
		return new WP_REST_Response( woo_wallet_get_balance_by_currency( $id ), 200 );
	}

	public function purge_transactions( $request ) {
		$id   = (int) $request['id'];
		$user = get_userdata( $id );
		if ( ! $user ) {
			return $this->error( 'terawallet_rest_invalid_user', __( 'Invalid user id.', 'woo-wallet' ), 404 );
		}
		$idem_key = $this->require_idempotency_key( $request );
		if ( is_wp_error( $idem_key ) ) {
			return $idem_key;
		}
		return WooWallet_Idempotency::run(
			get_current_user_id(),
			'admin_user_purge:' . $id . ':' . $idem_key,
			function () use ( $id, $request ) {
				$result = woo_wallet_purge_user_transactions(
					$id,
					$request['delete_mode'],
					$request['balance_handling']
				);
				if ( is_wp_error( $result ) ) {
					return $this->error( $result->get_error_code(), $result->get_error_message(), 400 );
				}
				return new WP_REST_Response(
					array_merge( array( 'purged' => true, 'user_id' => $id ), is_array( $result ) ? $result : array() ),
					200
				);
			}
		);
	}
}
