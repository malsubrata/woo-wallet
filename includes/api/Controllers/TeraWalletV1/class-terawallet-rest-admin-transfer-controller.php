<?php
/**
 * REST API: terawallet/v1/admin/transfer
 *
 * Admin-initiated peer-to-peer wallet transfer. Wraps
 * `Woo_Wallet_Wallet::transfer()` (which already handles deterministic
 * lock ordering, atomic per-user GET_LOCKs, and balance validation) and
 * adds idempotent replay protection for double-clicks from the DataView.
 *
 * @package StandaleneTech
 * @since   1.6.3
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin transfer controller.
 */
class TeraWallet_REST_Admin_Transfer_Controller extends TeraWallet_REST_Admin_Controller_Base {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'admin/transfer';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'permissions' ),
					'args'                => array(
						'from_user_id' => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
						'to_user_id'   => array( 'type' => 'integer', 'required' => true, 'sanitize_callback' => 'absint' ),
						'amount'       => array( 'type' => 'number', 'required' => true, 'minimum' => 0.01 ),
						'debit_note'   => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
						'credit_note'  => array( 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field' ),
					),
				),
			)
		);
	}

	public function permissions( $request ) {
		return $this->check_capability( 'edit', $request );
	}

	public function create_item( $request ) {
		$params = $request->get_params();
		$from   = (int) $params['from_user_id'];
		$to     = (int) $params['to_user_id'];
		if ( $from === $to ) {
			return $this->error( 'terawallet_rest_transfer_same_user', __( 'Sender and recipient must differ.', 'woo-wallet' ), 400 );
		}
		if ( ! get_userdata( $from ) || ! get_userdata( $to ) ) {
			return $this->error( 'terawallet_rest_invalid_user', __( 'Invalid user id.', 'woo-wallet' ), 404 );
		}

		$idem_key = $this->require_idempotency_key( $request );
		if ( is_wp_error( $idem_key ) ) {
			return $idem_key;
		}
		return WooWallet_Idempotency::run(
			get_current_user_id(),
			"admin_transfer:{$from}:{$to}:" . $idem_key,
			function () use ( $from, $to, $params ) {
				$amount      = (float) $params['amount'];
				$debit_note  = isset( $params['debit_note'] ) ? $params['debit_note'] : '';
				$credit_note = isset( $params['credit_note'] ) ? $params['credit_note'] : '';

				$result = woo_wallet()->wallet->transfer( $from, $to, $amount, $debit_note, $credit_note );
				if ( is_wp_error( $result ) ) {
					return $this->error( $result->get_error_code(), $result->get_error_message(), 400 );
				}
				if ( ! $result ) {
					return $this->error( 'terawallet_rest_transfer_failed', __( 'Transfer failed.', 'woo-wallet' ), 500 );
				}
				return new WP_REST_Response(
					array(
						'transferred'  => true,
						'from_user_id' => $from,
						'to_user_id'   => $to,
						'amount'       => $amount,
						'result'       => $result,
					),
					200
				);
			}
		);
	}
}
