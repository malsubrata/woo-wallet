<?php
/**
 * Base controller for the customer-facing `terawallet/v1/me/*` namespace.
 *
 * Enforces the contract that customer endpoints:
 *   - require a logged-in cookie session (no consumer-key auth path),
 *   - resolve the target user from `get_current_user_id()` only — never from
 *     a request-supplied id or email,
 *   - never cache responses in shared caches (Cache-Control: private, no-store),
 *   - return 404 (not 403) on unauthorized item access to avoid leaking existence.
 *
 * Concrete `/me/*` controllers extend this and only need to implement schema +
 * route registration + callbacks.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Controller_Base' ) ) {

	/**
	 * Customer-namespace base controller.
	 */
	abstract class TeraWallet_REST_Me_Controller_Base extends TeraWallet_REST_Controller_Base {

		/**
		 * REST API namespace for all customer (React-dashboard) endpoints.
		 *
		 * @var string
		 */
		protected $namespace = 'terawallet/v1';

		/**
		 * Permission callback shared by every `/me/*` route.
		 *
		 * Rejects:
		 *   - anonymous requests → 401 rest_not_logged_in
		 *   - consumer-key authenticated requests → 401 rest_consumer_key_in_me_namespace
		 *     (a leaked consumer key must not be able to read another customer's data;
		 *     wc/v3/wallet remains available for that audience).
		 *
		 * @param WP_REST_Request $request The request being authorized.
		 * @return true|WP_Error
		 */
		public function check_me_permissions( $request ) {
			if ( ! is_user_logged_in() ) {
				return $this->error(
					'rest_not_logged_in',
					__( 'You must be logged in to access this resource.', 'woo-wallet' ),
					rest_authorization_required_code()
				);
			}

			// WC's REST authentication adds a `wc_rest_authentication_method` hint when
			// the request was authenticated via consumer key/secret. If we see anything
			// other than a cookie session, reject — a customer-scoped endpoint must not
			// be reachable with a server-to-server credential.
			if ( ! empty( $request['_wc_rest_consumer_key'] ) || self::is_consumer_key_request() ) {
				return $this->error(
					'rest_consumer_key_in_me_namespace',
					__( 'Consumer key authentication is not permitted on customer endpoints.', 'woo-wallet' ),
					401
				);
			}

			$allowed = apply_filters( 'terawallet_rest_me_permissions', true, $request );
			if ( true !== $allowed ) {
				return is_wp_error( $allowed ) ? $allowed : $this->error(
					'rest_forbidden',
					__( 'Sorry, you are not allowed to access this resource.', 'woo-wallet' ),
					rest_authorization_required_code()
				);
			}

			return true;
		}

		/**
		 * Heuristic: did the current request authenticate with a WC consumer key?
		 *
		 * WC core hard-rejects basic-auth on non-https in production but the auth
		 * marker is only available inside `WC_REST_Authentication`. Re-derive it
		 * cheaply by checking the inbound credentials.
		 *
		 * @return bool
		 */
		private static function is_consumer_key_request() {
			if ( ! empty( $_SERVER['PHP_AUTH_USER'] ) && 0 === strpos( (string) $_SERVER['PHP_AUTH_USER'], 'ck_' ) ) {
				return true;
			}
			if ( ! empty( $_GET['consumer_key'] ) || ! empty( $_GET['consumer_secret'] ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Resolve the calling user. Always trust `get_current_user_id()` over any
		 * request parameter — concrete handlers must call this (or read it directly)
		 * rather than `get_user_by('email', $request['email'])`.
		 *
		 * @return int
		 */
		protected function current_user_id() {
			return (int) get_current_user_id();
		}

		/**
		 * 404-on-foreign-resource helper. Use after loading an item by id to confirm
		 * it belongs to the caller. Returns 404 (not 403) so an attacker can't
		 * enumerate ids belonging to other users.
		 *
		 * @param int    $owner_user_id User id stored on the loaded resource.
		 * @param string $resource      Slug used in the error message ("transaction").
		 * @return true|WP_Error
		 */
		protected function confirm_owner( $owner_user_id, $resource = 'resource' ) {
			if ( (int) $owner_user_id !== $this->current_user_id() ) {
				return $this->error(
					'rest_' . $resource . '_not_found',
					/* translators: %s: resource name. */
					sprintf( __( '%s not found.', 'woo-wallet' ), ucfirst( $resource ) ),
					404
				);
			}
			return true;
		}

		/**
		 * Mark a response as private + non-cacheable. Apply to every customer-scoped
		 * payload so shared caches/CDNs never store another user's data.
		 *
		 * @param WP_REST_Response $response Response to mutate.
		 * @return WP_REST_Response
		 */
		protected function private_no_store( WP_REST_Response $response ) {
			$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
			return $response;
		}

		/**
		 * Wrap a state-changing handler in idempotency-key replay. The SPA generates
		 * a UUID per submission and resends it on retries; the second call returns
		 * the first call's response without re-executing.
		 *
		 * @param WP_REST_Request $request  Request (read header + user).
		 * @param callable        $callback Zero-arg handler producing WP_REST_Response|WP_Error.
		 * @return WP_REST_Response|WP_Error
		 */
		protected function idempotent( WP_REST_Request $request, callable $callback ) {
			$key = (string) $request->get_header( 'Idempotency-Key' );
			if ( '' === $key ) {
				return $callback();
			}
			if ( ! class_exists( 'WooWallet_Idempotency' ) ) {
				include_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-idempotency.php';
			}
			return WooWallet_Idempotency::run( $this->current_user_id(), $key, $callback );
		}
	}
}
