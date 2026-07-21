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
		const IN_FLIGHT_TTL    = 5 * MINUTE_IN_SECONDS;

		/**
		 * Run $callback once for ($user_id, $key); replay the stored response on retries.
		 *
		 * The key is *claimed* before the callback runs, not after it returns. The
		 * side-effect (a committed ledger row) becomes durable the moment the insert
		 * lands, but the stored response is only written once the callback returns —
		 * so a request that dies in between (client timeout under PHP-FPM, fatal in a
		 * post-insert hook) used to leave the money moved and no replay record, and the
		 * retry would re-execute. Nothing in the ledger dedupes, so that retry could
		 * charge twice. The pre-claim closes that window: a retry that arrives while
		 * the original is unaccounted for gets a 409 "in progress" instead of a second
		 * charge or a misleading failure.
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

			// A disconnecting client must not abort a money-moving request midway —
			// that is precisely how the stored response went missing while the ledger
			// row survived. Let it run to completion and record its outcome.
			ignore_user_abort( true );

			$transient = self::TRANSIENT_PREFIX . $user_id . '_' . md5( $key );

			$cached = get_transient( $transient );
			if ( is_array( $cached ) && isset( $cached['status'], $cached['body'] ) ) {
				$response = new WP_REST_Response( $cached['body'], (int) $cached['status'] );
				$response->header( 'Idempotent-Replay', 'true' );
				return $response;
			}
			if ( is_array( $cached ) && isset( $cached['state'] ) && 'in_progress' === $cached['state'] ) {
				return new WP_Error(
					'terawallet_rest_idempotency_in_progress',
					__( 'A request with this Idempotency-Key is already being processed. Its outcome is not yet known — it may well have succeeded. Do not resubmit it as a new request; check the wallet transaction list, or retry this same key shortly.', 'woo-wallet' ),
					array( 'status' => 409 )
				);
			}

			// ponytail: check-then-set, not atomic — two *simultaneous* first requests
			// can both claim. The ledger's per-user GET_LOCK and balance gate still
			// serialize the actual money move; the window being closed here is the
			// sequential retry-after-crash. Upgrade path if simultaneity ever matters:
			// atomic claim via add_option() on the raw `_transient_*` option name.
			set_transient(
				$transient,
				array(
					'state' => 'in_progress',
					'at'    => time(),
				),
				// ponytail: a request that truly dies unblocks after 5 minutes rather
				// than staying wedged for the full 24h TTL. Past that a retry
				// re-executes — today's behaviour, minus a 5-minute guard.
				self::IN_FLIGHT_TTL
			);

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
			} else {
				// A genuine failure stays retryable — releasing the claim rather than
				// leaving the key wedged behind an error the client can fix.
				delete_transient( $transient );
			}

			return $result;
		}
	}
}
