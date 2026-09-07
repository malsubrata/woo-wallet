<?php
/**
 * Sell Content purchase replay-guard regression tests.
 *
 * v1.6.15 added an "already paid" check to `handle_purchase_content()` to stop a
 * replayed "buy this content" POST debiting the wallet twice. A WordPress nonce
 * is CSRF protection, not replay protection — it stays valid for its whole tick
 * window and verifies any number of times — so the paid marker is the only thing
 * standing between one purchase and two debits.
 *
 * That first guard was check-then-act: it read the marker, debited, and only
 * then wrote the marker. Two requests racing each other both read "not paid"
 * and both debited, because `debit()`'s own per-user lock keeps the balance
 * arithmetic correct but has no idea the two calls are the same purchase. The
 * fix serializes check-debit-mark on a per-purchase `GET_LOCK`.
 *
 * These tests pin both halves: the sequential replay must be refused, and a
 * request that cannot take the purchase lock must decline rather than debit.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Action_Sell_Content::handle_purchase_content
 */
class Test_Sell_Content_Replay extends WP_UnitTestCase {

	/**
	 * Buyer.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Content author (profit share recipient).
	 *
	 * @var int
	 */
	private $author_id;

	/**
	 * The post being sold.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * The action under test.
	 *
	 * @var Woo_Wallet_Action_Sell_Content
	 */
	private $action;

	/**
	 * Price of the content.
	 *
	 * @var float
	 */
	private $price = 15.0;

	/**
	 * A separate DB connection, used to hold the purchase lock from "another
	 * request". Null until a test asks for one.
	 *
	 * @var wpdb|null
	 */
	private $other_connection = null;

	/**
	 * Funded buyer, an authored post set as the global one, and the action
	 * instance with sell-content enabled and a profit share configured.
	 */
	public function set_up() {
		parent::set_up();

		$this->author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$this->user_id   = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->post_id   = self::factory()->post->create(
			array(
				'post_author' => $this->author_id,
				'post_title'  => 'Paid content',
			)
		);

		wp_set_current_user( $this->user_id );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'Test float' );

		$GLOBALS['post'] = get_post( $this->post_id );

		$this->action = new Woo_Wallet_Action_Sell_Content();
		$this->action->update_option( 'enabled', 'yes' );
		$this->action->update_option( 'profit_share', 50 );
		$this->action->update_option( 'expiration', 0 );
		$this->action->init_settings();
	}

	/**
	 * Release the borrowed lock and drop the second connection.
	 */
	public function tear_down() {
		if ( $this->other_connection ) {
			$this->other_connection->query( 'SELECT RELEASE_LOCK("' . $this->lock_name() . '")' );
			$this->other_connection = null;
		}
		unset( $GLOBALS['post'] );
		parent::tear_down();
	}

	/**
	 * The paid marker / lock key for this buyer, post and price.
	 *
	 * @return string
	 */
	private function purchase_key() {
		return md5( 'tw-sell-content' . $this->post_id . $this->user_id . $this->price );
	}

	/**
	 * The GET_LOCK name guarding this purchase.
	 *
	 * @return string
	 */
	private function lock_name() {
		return 'woo_wallet_sell_content_' . $this->purchase_key();
	}

	/**
	 * Buyer's balance as a float.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * Author's balance as a float.
	 *
	 * @return float
	 */
	private function author_balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->author_id, 'edit' );
	}

	/**
	 * Populate $_POST with a valid purchase request, exactly as the buy form
	 * would, and run the handler once.
	 */
	private function submit_purchase() {
		$_POST['amount']               = (string) $this->price;
		$_POST['tw_buy_content_nonce'] = wp_create_nonce(
			'tw_buy_content_nonce_' . $this->post_id . '_' . $this->user_id . '_' . $this->price
		);

		$this->action->handle_purchase_content();

		unset( $_POST['amount'], $_POST['tw_buy_content_nonce'] );
	}

	/**
	 * A second MySQL session, so a lock taken on it genuinely contends with the
	 * one the handler takes. GET_LOCK is re-entrant within a single session, so
	 * a second connection is the only way to simulate the concurrent request.
	 *
	 * @return wpdb
	 */
	private function other_connection() {
		if ( null === $this->other_connection ) {
			$this->other_connection = new wpdb( DB_USER, DB_PASSWORD, DB_NAME, DB_HOST );
		}
		return $this->other_connection;
	}

	/**
	 * Baseline: one submission debits the buyer once and pays the author.
	 */
	public function test_single_purchase_debits_once() {
		$opening = $this->balance();

		$this->submit_purchase();

		$this->assertEquals( $opening - $this->price, $this->balance(), 'Buyer should be debited exactly the price.' );
		$this->assertEquals( $this->price / 2, $this->author_balance(), 'Author should receive the 50% profit share.' );
		$this->assertTrue( (bool) get_transient( $this->purchase_key() ), 'The purchase should be marked paid.' );
	}

	/**
	 * The sequential replay: resubmitting the same valid nonce after the first
	 * purchase completed must not debit again.
	 */
	public function test_replayed_purchase_does_not_debit_twice() {
		$opening = $this->balance();

		$this->submit_purchase();
		$after_first = $this->balance();

		$this->submit_purchase();

		$this->assertEquals( $opening - $this->price, $after_first );
		$this->assertEquals( $after_first, $this->balance(), 'A replayed purchase must not debit the wallet a second time.' );
		$this->assertEquals( $this->price / 2, $this->author_balance(), 'A replayed purchase must not pay the author a second time.' );
	}

	/**
	 * The race: while another request holds the purchase lock, this one must
	 * decline outright rather than run its own check-and-debit alongside it.
	 *
	 * This is what fails on the pre-fix code — with no lock at all, the handler
	 * read "not paid" and debited regardless of what any concurrent request was
	 * doing.
	 */
	public function test_purchase_declines_while_another_request_holds_the_lock() {
		$opening = $this->balance();

		$got = $this->other_connection()->get_var(
			'SELECT GET_LOCK("' . $this->lock_name() . '", 5)'
		);
		$this->assertSame( '1', (string) $got, 'The simulated concurrent request should hold the purchase lock.' );

		$this->submit_purchase();

		$this->assertEquals( $opening, $this->balance(), 'A purchase that cannot take the lock must not debit the wallet.' );
		$this->assertEquals( 0.0, $this->author_balance(), 'A purchase that cannot take the lock must not pay the author.' );
		$this->assertFalse( get_transient( $this->purchase_key() ), 'A purchase that cannot take the lock must not be marked paid.' );
	}

	/**
	 * If the author's profit-share credit blows up after the buyer's debit has
	 * already committed, the buyer must still be marked paid — otherwise the
	 * retry they will inevitably make charges them a second time, which is the
	 * exact bug the replay guard exists to prevent.
	 */
	public function test_paid_marker_survives_a_failing_profit_share_credit() {
		$opening = $this->balance();

		// Stand in for a third-party listener that throws on the author's credit.
		$exploder = function ( $transaction_id, $user_id, $amount, $type ) {
			if ( 'credit' === $type && (int) $user_id === $this->author_id ) {
				throw new Exception( 'Third-party listener exploded' );
			}
		};
		add_action( 'woo_wallet_transaction_recorded', $exploder, 10, 4 );

		try {
			$this->submit_purchase();
		} catch ( Exception $e ) {
			// The throw is the scenario, not a test failure.
			unset( $e );
		}

		remove_action( 'woo_wallet_transaction_recorded', $exploder, 10 );

		$after_first = $this->balance();
		$this->assertEquals( $opening - $this->price, $after_first, 'The buyer should have been debited once.' );
		$this->assertTrue(
			(bool) get_transient( $this->purchase_key() ),
			'The purchase must be marked paid as soon as the buyer is charged, even though the profit-share credit failed.'
		);

		// The retry the buyer would make must not charge them again.
		$this->submit_purchase();
		$this->assertEquals( $after_first, $this->balance(), 'A retry after a failed profit-share credit must not debit the buyer again.' );
	}

	/**
	 * A request that cannot take the lock should tell the buyer, not fail silently.
	 */
	public function test_lock_contention_surfaces_a_notice() {
		if ( ! function_exists( 'wc_get_notices' ) ) {
			$this->markTestSkipped( 'Notices unavailable.' );
		}
		wc_clear_notices();

		$this->other_connection()->get_var( 'SELECT GET_LOCK("' . $this->lock_name() . '", 5)' );
		$this->submit_purchase();

		$this->assertNotEmpty( wc_get_notices( 'error' ), 'Lock contention should surface an error notice to the buyer.' );
		wc_clear_notices();
	}

	/**
	 * The lock must be released once the purchase finishes, or the buyer could
	 * never purchase anything else in the same request cycle.
	 */
	public function test_lock_is_released_after_purchase() {
		$this->submit_purchase();

		$got = $this->other_connection()->get_var(
			'SELECT GET_LOCK("' . $this->lock_name() . '", 2)'
		);

		$this->assertSame( '1', (string) $got, 'The purchase lock should be released once the handler returns.' );
	}
}
