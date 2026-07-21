<?php
/**
 * Idempotency replay tests.
 *
 * Covers the guarantee that matters on a money path: a key that has already
 * been claimed never re-runs its side-effect. A repeat of a completed request
 * replays the stored response, a repeat of an in-flight one is refused with a
 * 409 rather than executing a second charge, and a genuine failure releases the
 * key so the client can retry.
 *
 * @package WooWallet\Tests
 */

require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-idempotency.php';

/**
 * @covers WooWallet_Idempotency::run
 */
class Test_Idempotency extends WP_UnitTestCase {

	/**
	 * Customer the test operates on.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * How many times the callback under test actually ran.
	 *
	 * @var int
	 */
	private $calls;

	/**
	 * Create a fresh customer for each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->calls   = 0;
	}

	/**
	 * A callback that counts its invocations and returns a 200 response.
	 *
	 * @return callable
	 */
	private function counting_callback() {
		return function () {
			++$this->calls;
			return new WP_REST_Response( array( 'run' => $this->calls ), 200 );
		};
	}

	/**
	 * Transient name WooWallet_Idempotency::run() uses for a given key.
	 *
	 * @param string $key Idempotency key.
	 * @return string
	 */
	private function transient_for( $key ) {
		return 'wwidem_' . $this->user_id . '_' . md5( $key );
	}

	/**
	 * A second call with the same key replays the first response and does not
	 * re-run the side-effect.
	 */
	public function test_repeat_replays_without_re_executing() {
		$first  = WooWallet_Idempotency::run( $this->user_id, 'key-replay', $this->counting_callback() );
		$second = WooWallet_Idempotency::run( $this->user_id, 'key-replay', $this->counting_callback() );

		$this->assertSame( 1, $this->calls, 'Callback must run exactly once across both calls.' );
		$this->assertSame( 200, $second->get_status() );
		$this->assertSame( $first->get_data(), $second->get_data() );
		$this->assertSame( 'true', $second->get_headers()['Idempotent-Replay'] );
	}

	/**
	 * A retry arriving while the original request is unaccounted for is refused
	 * with a 409 — the case that used to re-run the ledger write and report a
	 * completed transaction as failed.
	 */
	public function test_in_flight_claim_is_refused_with_409() {
		set_transient(
			$this->transient_for( 'key-inflight' ),
			array(
				'state' => 'in_progress',
				'at'    => time(),
			),
			5 * MINUTE_IN_SECONDS
		);

		$result = WooWallet_Idempotency::run( $this->user_id, 'key-inflight', $this->counting_callback() );

		$this->assertSame( 0, $this->calls, 'An in-flight key must never re-run the side-effect.' );
		$this->assertWPError( $result );
		$this->assertSame( 'terawallet_rest_idempotency_in_progress', $result->get_error_code() );
		$this->assertSame( 409, $result->get_error_data()['status'] );
	}

	/**
	 * The claim is written before the callback runs, so a request that dies
	 * mid-flight leaves the key blocked rather than open to a second charge.
	 */
	public function test_claim_is_written_before_callback_runs() {
		$seen = null;

		WooWallet_Idempotency::run(
			$this->user_id,
			'key-claim',
			function () use ( &$seen ) {
				$seen = get_transient( $this->transient_for( 'key-claim' ) );
				return new WP_REST_Response( array( 'ok' => true ), 200 );
			}
		);

		$this->assertIsArray( $seen );
		$this->assertSame( 'in_progress', $seen['state'] );
	}

	/**
	 * A callback returning WP_Error releases the key, so the client can fix the
	 * request and retry with the same key.
	 */
	public function test_error_releases_the_key_for_retry() {
		$failing = function () {
			++$this->calls;
			return new WP_Error( 'nope', 'Failed', array( 'status' => 400 ) );
		};

		$first = WooWallet_Idempotency::run( $this->user_id, 'key-error', $failing );
		$this->assertWPError( $first );
		$this->assertFalse( get_transient( $this->transient_for( 'key-error' ) ) );

		WooWallet_Idempotency::run( $this->user_id, 'key-error', $failing );
		$this->assertSame( 2, $this->calls, 'A failed request must stay retryable.' );
	}

	/**
	 * With no key (or no user) the helper is a passthrough — unchanged behaviour
	 * for callers that do not send the header.
	 */
	public function test_missing_key_or_user_passes_through() {
		WooWallet_Idempotency::run( $this->user_id, '', $this->counting_callback() );
		WooWallet_Idempotency::run( 0, 'key-nouser', $this->counting_callback() );

		$this->assertSame( 2, $this->calls );
	}
}
