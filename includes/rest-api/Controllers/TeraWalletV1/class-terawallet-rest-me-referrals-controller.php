<?php
/**
 * GET /terawallet/v1/me/referrals
 *
 * Customer's referral snapshot — share URL plus visitor/signup/earning figures
 * read from the dedicated `woo_wallet_referrals` table (via
 * woo_wallet_get_referral_summary()). Every money figure is reconverted to the
 * active storefront currency, and the frozen pre-1.6.2 user-meta total is
 * surfaced separately as a read-only `legacy_earning`.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Referrals_Controller' ) ) {

	/**
	 * Customer referrals controller.
	 */
	class TeraWallet_REST_Me_Referrals_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/referrals';

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
						'callback'            => array( $this, 'get_item' ),
						'permission_callback' => array( $this, 'check_referrals_permissions' ),
						'args'                => array(
							'context' => $this->get_context_param( array( 'default' => 'view' ) ),
						),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
		}

		/**
		 * Permission gate: logged in + referrals feature enabled.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return true|WP_Error
		 */
		public function check_referrals_permissions( $request ) {
			$base = $this->check_me_permissions( $request );
			if ( is_wp_error( $base ) ) {
				return $base;
			}
			if ( ! apply_filters( 'woo_wallet_is_enable_referrals', true ) ) {
				return $this->error( 'rest_referrals_disabled', __( 'Referrals are disabled on this site.', 'woo-wallet' ), 404 );
			}
			return true;
		}

		/**
		 * Build the referral snapshot.
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

			$action = $this->resolve_referral_action();
			if ( ! $action ) {
				return $this->error( 'rest_referrals_unavailable', __( 'Referral feature is unavailable.', 'woo-wallet' ), 503 );
			}

			$settings   = isset( $action->settings ) ? (array) $action->settings : array();
			$by_user_id = isset( $settings['referal_link'] ) && 'id' === $settings['referal_link'];
			$handle     = isset( $action->referral_handel ) ? $action->referral_handel : 'wwref';
			$identifier = $by_user_id ? $user_id : $user->user_login;

			$share_url = add_query_arg( $handle, $identifier, wc_get_page_permalink( 'myaccount' ) );

			$summary  = woo_wallet_get_referral_summary( $user_id );
			$currency = $summary['currency'];

			$data = array(
				'handle'    => $handle,
				'code'      => (string) $identifier,
				'share_url' => $share_url,
				'stats'     => array(
					'visitors'       => (int) $summary['visitors'],
					'signups'        => (int) $summary['signups'],
					'pending'        => (int) $summary['pending'],
					'earning'        => array(
						'amount'    => (float) $summary['earned'],
						'currency'  => $currency,
						'formatted' => wp_strip_all_tags( woo_wallet_referral_format_amount( $summary['earned'], $currency, $user_id ) ),
					),
					'legacy_earning' => array(
						'amount'    => (float) $summary['legacy_earned'],
						'currency'  => $currency,
						'formatted' => wp_strip_all_tags( woo_wallet_referral_format_amount( $summary['legacy_earned'], $currency, $user_id ) ),
					),
				),
			);

			$data = apply_filters( 'terawallet_rest_me_referrals', $data, $user, $request );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/' . $this->rest_base ) );
			return $this->private_no_store( $response );
		}

		/**
		 * Locate the referrals action object on the WOO_Wallet_Actions registry.
		 *
		 * @return Woo_Wallet_Action_Referrals|null
		 */
		protected function resolve_referral_action() {
			if ( ! class_exists( 'WOO_Wallet_Actions' ) ) {
				return null;
			}
			$registry = WOO_Wallet_Actions::instance();
			if ( ! $registry || empty( $registry->actions ) ) {
				return null;
			}
			foreach ( $registry->actions as $action ) {
				if ( isset( $action->id ) && 'referrals' === $action->id ) {
					return $action;
				}
			}
			return null;
		}

		/**
		 * Schema.
		 *
		 * @return array
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'terawallet_referrals',
				'type'       => 'object',
				'properties' => array(
					'handle'    => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'code'      => array(
						'type'     => 'string',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'share_url' => array(
						'type'     => 'string',
						'format'   => 'uri',
						'context'  => array( 'view' ),
						'readonly' => true,
					),
					'stats'     => array(
						'type'       => 'object',
						'context'    => array( 'view' ),
						'readonly'   => true,
						'properties' => array(
							'visitors'       => array( 'type' => 'integer' ),
							'signups'        => array( 'type' => 'integer' ),
							'pending'        => array( 'type' => 'integer' ),
							'earning'        => array(
								'type'       => 'object',
								'properties' => array(
									'amount'    => array( 'type' => 'number' ),
									'currency'  => array( 'type' => 'string' ),
									'formatted' => array( 'type' => 'string' ),
								),
							),
							'legacy_earning' => array(
								'type'        => 'object',
								'description' => __( 'Frozen referral earnings recorded before 1.6.2 history tracking. Read-only.', 'woo-wallet' ),
								'properties'  => array(
									'amount'    => array( 'type' => 'number' ),
									'currency'  => array( 'type' => 'string' ),
									'formatted' => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
