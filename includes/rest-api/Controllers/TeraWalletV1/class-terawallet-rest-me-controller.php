<?php
/**
 * GET /terawallet/v1/me
 *
 * Profile snapshot for the React dashboard's app shell. Returns the calling
 * user's id, display name, email, locale, currency, balance, and a coarse
 * capability map the SPA uses to decide which tiles to render.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Controller' ) ) {

	/**
	 * Profile snapshot controller.
	 */
	class TeraWallet_REST_Me_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me';

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
		 * Build the profile snapshot.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response|WP_Error
		 */
		public function get_item( $request ) {
			$user_id = $this->current_user_id();
			$user    = get_userdata( $user_id );
			if ( ! $user ) {
				return $this->error( 'rest_not_logged_in', __( 'User not found.', 'woo-wallet' ), 401 );
			}

			$balance_raw = (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );

			$data = array(
				'id'           => $user_id,
				'display_name' => $user->display_name,
				'email'        => $user->user_email,
				'locale'       => get_user_locale( $user_id ),
				'currency'     => get_woocommerce_currency(),
				'balance'      => array(
					'amount'    => $balance_raw,
					'formatted' => wp_strip_all_tags( wc_price( $balance_raw, woo_wallet_wc_price_args( $user_id ) ) ),
				),
				'capabilities' => $this->build_capabilities( $user_id ),
			);

			$data = apply_filters( 'terawallet_rest_me_profile', $data, $user, $request );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/me' ) );
			$response->add_link( 'related', rest_url( $this->namespace . '/me/balance' ), array( 'name' => 'balance' ) );
			$response->add_link( 'related', rest_url( $this->namespace . '/me/transactions' ), array( 'name' => 'transactions' ) );

			return $this->private_no_store( $response );
		}

		/**
		 * Capability snapshot the SPA uses to decide which tiles to show.
		 *
		 * Free flags only — pro modules add their own (`can_withdraw`,
		 * `can_view_expiring_credits`) via `terawallet_rest_me_capabilities`.
		 *
		 * @param int $user_id Calling user id.
		 * @return array<string,bool>
		 */
		protected function build_capabilities( $user_id ) {
			$settings = woo_wallet()->settings_api;

			$caps = array(
				'can_topup'    => 'on' === $settings->get_option( 'is_enable_wallet_partial_payment', '_wallet_settings_general', 'on' )
					|| 'on' === $settings->get_option( 'is_enable_wallet_topup', '_wallet_settings_general', 'on' ),
				'can_transfer' => 'on' === $settings->get_option( 'is_enable_wallet_transfer', '_wallet_settings_general', 'on' ),
				'can_view_referrals' => true,
			);

			return apply_filters( 'terawallet_rest_me_capabilities', $caps, $user_id );
		}

		/**
		 * Profile schema.
		 *
		 * @return array
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'terawallet_me',
				'type'       => 'object',
				'properties' => array(
					'id'           => array(
						'type'     => 'integer',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'display_name' => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'email'        => array(
						'type'     => 'string',
						'format'   => 'email',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'locale'       => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'currency'     => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'balance'      => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'amount'    => array( 'type' => 'number' ),
							'formatted' => array( 'type' => 'string' ),
						),
					),
					'capabilities' => array(
						'type'     => 'object',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
