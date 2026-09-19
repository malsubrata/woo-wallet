<?php
/**
 * Per-request balance memo tests.
 *
 * `get_wallet_balance()` used to re-run the ledger SUM for every caller, and a
 * single checkout render calls it three or four times (Blocks payment data,
 * is_full_payment_through_wallet(), the partial-payment fee, and the
 * partial-payment gate). The memo collapses those into one query per request.
 *
 * The risk it introduces is staleness, so these tests pin the invalidation as
 * hard as the saving: a write in the same request must be visible to the very
 * next read, and the memo must never leak across users or currency scopes.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::get_wallet_balance
 * @covers Woo_Wallet_Wallet::flush_balance_cache
 */
class Test_Balance_Request_Cache extends WP_UnitTestCase {

	/**
	 * Customer the test operates on.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Create a fresh customer, seeded with a known balance.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'Seed' );
		Woo_Wallet_Wallet::flush_balance_cache();
	}

	/**
	 * Never let a memo survive into another test.
	 */
	public function tear_down() {
		Woo_Wallet_Wallet::flush_balance_cache();
		remove_all_filters( 'woo_wallet_current_balance' );
		parent::tear_down();
	}

	/**
	 * Count the ledger SUM queries issued while $callback runs.
	 *
	 * Hooks wpdb's `query` filter rather than reading `$wpdb->queries`, which
	 * is only populated when SAVEQUERIES is defined and is not in this suite.
	 * Counting real queries (rather than mocking) means the assertion fails if
	 * the memo is bypassed by any path, not just the one called directly.
	 *
	 * The `t.type` alias is what distinguishes `get_wallet_balance()`'s SUM
	 * from the unaliased gate queries in recode_transaction()/transfer(),
	 * which deliberately bypass the memo and must not be counted here.
	 *
	 * @param callable $callback Code to measure.
	 * @return int Number of balance SUM queries issued.
	 */
	private function count_balance_queries( $callback ) {
		$count = 0;

		$counter = function ( $query ) use ( &$count ) {
			if ( false !== strpos( $query, 'woo_wallet_transactions' )
				&& false !== strpos( $query, "WHEN t.type = 'credit'" ) ) {
				++$count;
			}
			return $query;
		};

		add_filter( 'query', $counter );
		try {
			$callback();
		} finally {
			remove_filter( 'query', $counter );
		}

		return $count;
	}

	/**
	 * The whole point of the change: repeated reads cost one query.
	 */
	public function test_repeat_reads_issue_a_single_query() {
		$wallet = woo_wallet()->wallet;

		$count = $this->count_balance_queries(
			function () use ( $wallet ) {
				$wallet->get_wallet_balance( $this->user_id, 'edit' );
				$wallet->get_wallet_balance( $this->user_id, 'edit' );
				$wallet->get_wallet_balance( $this->user_id, 'edit' );
			}
		);

		$this->assertSame( 1, $count, 'Repeated balance reads should hit the ledger once per request.' );
	}

	/**
	 * Memoizing a stale balance is the failure mode that matters: a credit or
	 * debit in the same request must be visible to the next read.
	 */
	public function test_write_in_same_request_invalidates_the_memo() {
		$wallet = woo_wallet()->wallet;

		$this->assertEquals( 100.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ) );

		$wallet->credit( $this->user_id, 25.00, 'Top-up' );
		$this->assertEquals( 125.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ), 'A credit must invalidate the memo.' );

		$wallet->debit( $this->user_id, 40.00, 'Purchase' );
		$this->assertEquals( 85.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ), 'A debit must invalidate the memo.' );
	}

	/**
	 * A transfer writes to two users; both memos must drop.
	 */
	public function test_transfer_invalidates_both_sides() {
		$wallet    = woo_wallet()->wallet;
		$recipient = self::factory()->user->create( array( 'role' => 'customer' ) );

		// Prime both memos.
		$this->assertEquals( 100.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ) );
		$this->assertEquals( 0.00, $wallet->get_wallet_balance( $recipient, 'edit' ) );

		$wallet->transfer( $this->user_id, $recipient, 30.00, 'Sent', 'Received' );

		$this->assertEquals( 70.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ), 'Sender memo must drop after a transfer.' );
		$this->assertEquals( 30.00, $wallet->get_wallet_balance( $recipient, 'edit' ), 'Recipient memo must drop after a transfer.' );
	}

	/**
	 * The memo is keyed per user — one customer's balance must never be served
	 * to another.
	 */
	public function test_memo_does_not_leak_between_users() {
		$wallet = woo_wallet()->wallet;
		$other  = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $other, 7.50, 'Seed other' );

		$this->assertEquals( 100.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ) );
		$this->assertEquals( 7.50, $wallet->get_wallet_balance( $other, 'edit' ) );
		$this->assertEquals( 100.00, $wallet->get_wallet_balance( $this->user_id, 'edit' ) );
	}

	/**
	 * Only the raw SUM is memoized, so a third-party balance filter still runs
	 * on every call rather than being frozen into the first read.
	 */
	public function test_balance_filter_still_runs_on_every_read() {
		$wallet = woo_wallet()->wallet;
		$calls  = 0;

		add_filter(
			'woo_wallet_current_balance',
			function ( $balance ) use ( &$calls ) {
				++$calls;
				return $balance;
			}
		);

		$wallet->get_wallet_balance( $this->user_id, 'edit' );
		$wallet->get_wallet_balance( $this->user_id, 'edit' );

		$this->assertSame( 2, $calls, 'The balance filter must not be short-circuited by the memo.' );
	}

	/**
	 * An explicit flush forces the next read back to the database.
	 */
	public function test_flush_forces_a_refetch() {
		$wallet = woo_wallet()->wallet;

		$wallet->get_wallet_balance( $this->user_id, 'edit' );
		Woo_Wallet_Wallet::flush_balance_cache( $this->user_id );

		$count = $this->count_balance_queries(
			function () use ( $wallet ) {
				$wallet->get_wallet_balance( $this->user_id, 'edit' );
			}
		);

		$this->assertSame( 1, $count, 'A flushed memo must re-query.' );
	}
}
