<?php
/**
 * POST /terawallet/v1/me/transfer
 * GET  /terawallet/v1/me/transfer/recipients (gated by filter)
 *
 * Customer-initiated wallet transfer. Routes through `WooWallet_Transfer_Service`
 * so the rate limit + every `apply_filters` extension point that the form-side
 * handler uses also fires here.
 *
 * Idempotency: the SPA generates a UUID per submission, sends it as
 * `Idempotency-Key`. Replays return the original response verbatim — see
 * `WooWallet_Idempotency`.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Transfer_Controller' ) ) {

	/**
	 * Customer transfer controller.
	 */
	class TeraWallet_REST_Me_Transfer_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/transfer';

		/**
		 * Register routes.
		 */
		public function register_routes() {
			// Settings flag: is the transfer feature enabled at all?
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base,
				array(
					array(
						'methods'             => WP_REST_Server::CREATABLE,
						'callback'            => array( $this, 'create_item' ),
						'permission_callback' => array( $this, 'check_transfer_permissions' ),
						'args'                => array(
							'recipient_id'    => array(
								'type'              => 'integer',
								'description'       => __( 'Recipient user id. Either recipient_id or recipient_email is required.', 'woo-wallet' ),
								'sanitize_callback' => 'absint',
								'validate_callback' => 'rest_validate_request_arg',
							),
							'recipient_email' => array(
								'type'              => 'string',
								'format'            => 'email',
								'description'       => __( 'Recipient email. Either recipient_id or recipient_email is required.', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_email',
								'validate_callback' => 'rest_validate_request_arg',
							),
							'amount'          => array(
								'required'          => true,
								'type'              => 'number',
								'minimum'           => 0.01,
								'description'       => __( 'Transfer amount.', 'woo-wallet' ),
								'sanitize_callback' => function ( $v ) {
									return function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $v ) : (float) $v;
								},
								'validate_callback' => 'rest_validate_request_arg',
							),
							'note'            => array(
								'type'              => 'string',
								'description'       => __( 'Note to attach to the credit transaction.', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_textarea_field',
								'validate_callback' => 'rest_validate_request_arg',
							),
							'currency'        => array(
								'type'              => 'string',
								'description'       => __( 'Optional ISO 4217 currency code. In per_currency mode, scopes the balance check and the resulting ledger rows; cross-currency transfers are rejected.', 'woo-wallet' ),
								'pattern'           => '^[A-Z]{3}$',
								'sanitize_callback' => function ( $v ) {
									return is_string( $v ) ? strtoupper( trim( $v ) ) : '';
								},
								'validate_callback' => function ( $v ) {
									return '' === $v || ( is_string( $v ) && (bool) preg_match( '/^[A-Z]{3}$/', strtoupper( trim( $v ) ) ) );
								},
							),
						),
					),
				)
			);

			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base . '/recipients',
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_recipients' ),
						'permission_callback' => array( $this, 'check_recipient_lookup_permissions' ),
						'args'                => array(
							'search' => array(
								'required'          => true,
								'type'              => 'string',
								'description'       => __( 'Substring of email or display name.', 'woo-wallet' ),
								'sanitize_callback' => 'sanitize_text_field',
								'validate_callback' => 'rest_validate_request_arg',
							),
						),
					),
				)
			);
		}

		/**
		 * Permission gate for transfer POST: logged-in cookie + transfer feature enabled.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return true|WP_Error
		 */
		public function check_transfer_permissions( $request ) {
			$base = $this->check_me_permissions( $request );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			$enabled = apply_filters(
				'woo_wallet_is_enable_transfer',
				'on' === woo_wallet()->settings_api->get_option( 'is_enable_wallet_transfer', '_wallet_settings_general', 'on' )
			);
			if ( ! $enabled ) {
				return $this->error( 'rest_transfer_disabled', __( 'Wallet transfers are disabled on this site.', 'woo-wallet' ), 403 );
			}
			return true;
		}

		/**
		 * Permission gate for recipient autocomplete: opt-in via filter, off by default.
		 * Sites that don't enable it return 404 to avoid leaking the route's existence.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return true|WP_Error
		 */
		public function check_recipient_lookup_permissions( $request ) {
			$base = $this->check_me_permissions( $request );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			if ( ! apply_filters( 'terawallet_rest_allow_recipient_lookup', false ) ) {
				return $this->error( 'rest_recipient_lookup_disabled', __( 'Not found.', 'woo-wallet' ), 404 );
			}
			return true;
		}

		/**
		 * Execute the transfer (idempotent on `Idempotency-Key` header).
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function create_item( $request ) {
			return $this->idempotent(
				$request,
				function () use ( $request ) {
					return $this->run_transfer( $request );
				}
			);
		}

		/**
		 * Resolve recipient + delegate to service.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		protected function run_transfer( WP_REST_Request $request ) {
			$from_user_id    = $this->current_user_id();
			$recipient_id    = (int) $request->get_param( 'recipient_id' );
			$recipient_email = (string) $request->get_param( 'recipient_email' );
			$amount          = (float) $request->get_param( 'amount' );
			$note            = (string) $request->get_param( 'note' );
			$currency        = (string) $request->get_param( 'currency' );

			if ( ! $recipient_id && '' !== $recipient_email ) {
				$user = get_user_by( 'email', $recipient_email );
				if ( $user ) {
					$recipient_id = (int) $user->ID;
				}
			}
			if ( ! $recipient_id ) {
				return $this->error( 'rest_invalid_recipient', __( 'Recipient is required (recipient_id or recipient_email).', 'woo-wallet' ), 400 );
			}

			if ( ! class_exists( 'WooWallet_Transfer_Service' ) ) {
				include_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-transfer-service.php';
			}
			$result = WooWallet_Transfer_Service::execute( $from_user_id, $recipient_id, $amount, $note, $currency );

			if ( empty( $result['is_valid'] ) ) {
				$status = isset( $result['status'] ) ? (int) $result['status'] : 400;
				$code   = isset( $result['code'] ) ? $result['code'] : 'rest_transfer_failed';
				return $this->error( $code, $result['message'], $status );
			}

			$balance_after = (float) woo_wallet()->wallet->get_wallet_balance( $from_user_id, 'edit', $currency );
			$balance_args  = '' !== $currency ? array( 'currency' => $currency ) : array();
			$response      = new WP_REST_Response(
				array(
					'transaction_id' => (int) $result['debit_id'],
					'credit_id'      => (int) $result['credit_id'],
					'charge'         => (float) $result['charge'],
					'message'        => $result['message'],
					'balance'        => array(
						'amount'    => $balance_after,
						'currency'  => '' !== $currency ? $currency : '',
						'formatted' => wp_strip_all_tags( wc_price( $balance_after, woo_wallet_wc_price_args( $from_user_id, $balance_args ) ) ),
					),
				),
				201
			);
			return $this->private_no_store( $response );
		}

		/**
		 * Recipient autocomplete (only when `terawallet_rest_allow_recipient_lookup` filter
		 * is enabled). Returns minimal fields — display name + masked email — to limit
		 * harvesting.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function get_recipients( $request ) {
			$search = trim( (string) $request->get_param( 'search' ) );
			$me     = $this->current_user_id();

			$users = get_users(
				array(
					'search'         => '*' . $search . '*',
					'search_columns' => array( 'user_email', 'user_login', 'display_name' ),
					'number'         => 10,
					'exclude'        => array( $me ),
				)
			);

			$out = array();
			foreach ( $users as $user ) {
				$out[] = array(
					'id'           => (int) $user->ID,
					'display_name' => $user->display_name,
					'email_masked' => self::mask_email( $user->user_email ),
				);
			}

			$response = new WP_REST_Response( $out, 200 );
			return $this->private_no_store( $response );
		}

		/**
		 * Mask "alice@example.com" → "a***e@example.com" for autocomplete payloads.
		 *
		 * @param string $email Email address.
		 * @return string
		 */
		protected static function mask_email( $email ) {
			$at = strpos( $email, '@' );
			if ( false === $at || $at < 1 ) {
				return $email;
			}
			$local  = substr( $email, 0, $at );
			$domain = substr( $email, $at );
			if ( strlen( $local ) <= 2 ) {
				return $local[0] . '***' . $domain;
			}
			return $local[0] . str_repeat( '*', max( 1, strlen( $local ) - 2 ) ) . substr( $local, -1 ) . $domain;
		}
	}
}
