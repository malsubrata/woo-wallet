<?php
/**
 * Balance-filter clamp integration tests.
 *
 * `woo_wallet_current_balance` lets third parties recompute the advertised
 * balance, but the debit gate in recode_transaction() only ever trusts the raw
 * ledger SUM. A filter that drifts upward therefore creates money the store
 * advertises and can never debit — checkout applies a wallet partial-payment
 * fee larger than the gate allows and the order is forced to on-hold with
 * "Wallet partial payment could not be debited (insufficient balance)".
 *
 * Reported in the wild against the Pro credit-expire module, whose credit-lot
 * total permanently exceeded the ledger because unallocated debit remainders
 * were discarded.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::get_wallet_balance
 */
class Test_Balance_Filter_Clamp extends WP_UnitTestCase {

	/**
	 * Customer the test operates on.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Create a fresh customer for each test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $this->user_id, 50.20, 'Seed' );
		clear_woo_wallet_cache( $this->user_id );
	}

	/**
	 * Remove any filter a test registered.
	 */
	public function tear_down() {
		remove_all_filters( 'woo_wallet_current_balance' );
		remove_all_filters( 'woo_wallet_disallow_negative_transaction' );
		parent::tear_down();
	}

	/**
	 * Register a filter that reports a fixed balance, as an expiry/lot plugin does.
	 *
	 * @param float $value Balance to advertise.
	 */
	private function advertise( $value ) {
		add_filter(
			'woo_wallet_current_balance',
			function () use ( $value ) {
				return $value;
			},
			999
		);
	}

	/**
	 * Spendable balance as checkout sees it ('edit' numeric context).
	 *
	 * @return float
	 */
	private function spendable() {
		clear_woo_wallet_cache( $this->user_id );
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * The reported bug: an over-reporting filter must not raise the spendable
	 * balance above the ledger, and whatever is advertised must be debitable.
	 */
	public function test_over_reporting_filter_is_capped_at_the_ledger() {
		$this->advertise( 54.40 );

		$spendable = $this->spendable();
		$this->assertEquals( 50.20, $spendable );

		// The whole point of the cap: what we advertise, we can actually pay with.
		$this->assertIsInt( woo_wallet()->wallet->debit( $this->user_id, $spendable, 'Spend full balance' ) );
	}

	/**
	 * Under-reporting (expiry, holds, reserved funds) is always safe and must
	 * pass through untouched.
	 */
	public function test_under_reporting_filter_is_left_alone() {
		$this->advertise( 30.00 );
		$this->assertEquals( 30.00, $this->spendable() );
	}

	/**
	 * With no filter registered the raw ledger SUM is unaffected by the cap.
	 */
	public function test_unfiltered_balance_is_unchanged() {
		$this->assertEquals( 50.20, $this->spendable() );
	}

	/**
	 * A store that deliberately allows overdraft switches the debit gate off via
	 * `woo_wallet_disallow_negative_transaction`; the cap must switch off with it
	 * so the advertised balance and the gate can never disagree.
	 */
	public function test_overdraft_stores_keep_the_filtered_balance() {
		$this->advertise( 54.40 );
		add_filter( 'woo_wallet_disallow_negative_transaction', '__return_false' );

		$this->assertEquals( 54.40, $this->spendable() );
	}
}
