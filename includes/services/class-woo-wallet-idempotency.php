<?php
/**
 * Idempotency helper for state-changing REST endpoints.
 *
 * Stores (user_id, key) → (status_code, body, created_at) so a retry of the
 * same logical action returns the original response verbatim instead of
 * re-executing the side-effect. Differs from the form-side single-use claim
 * in Woo_Wallet_Frontend (`wwxfer_*` transients) — that one consumes the key
 * on first use; this one preserves the result for the TTL window.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooWallet_Idempotency' ) ) {

	/**
	 * Idempotency cache for REST POST handlers.
	 */
	class WooWallet_Idempotency {

		const TRANSIENT_PREFIX = 'wwidem_';
		const TTL              = DAY_IN_SECONDS;

		/**
		 * Run $callback once for ($user_id, $key); replay the stored response on retries.
		 *
		 * The callback must return a `WP_REST_Response` or `WP_Error`. Anything else
		 * is passed through to the caller without caching, so callers cannot accidentally
		 * cache transient errors.
		 *
		 * @param int      $user_id  Owning user.
		 * @param string   $key      Idempotency-Key header value (any client-chosen string).
		 * @param callable $callback Zero-arg producer that runs the side-effect.
		 * @return WP_REST_Response|WP_Error
		 */
		public static function run( $user_id, $key, callable $callback ) {
			$user_id = (int) $user_id;
			$key     = sanitize_text_field( (string) $key );

			if ( ! $user_id || '' === $key ) {
				return $callback();
			}

			$transient = self::TRANSIENT_PREFIX . $user_id . '_' . md5( $key );

			$cached = get_transient( $transient );
			if ( is_array( $cached ) && isset( $cached['status'], $cached['body'] ) ) {
				$response = new WP_REST_Response( $cached['body'], (int) $cached['status'] );
				$response->header( 'Idempotent-Replay', 'true' );
				return $response;
			}

			$result = $callback();

			if ( $result instanceof WP_REST_Response ) {
				set_transient(
					$transient,
					array(
						'status' => $result->get_status(),
						'body'   => $result->get_data(),
						'at'     => time(),
					),
					self::TTL
				);
			}

			return $result;
		}
	}
}
