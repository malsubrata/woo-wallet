<?php
/**
 * Ledger credit / debit integration tests.
 *
 * Exercises the core append-only ledger: a credit raises the balance, a
 * debit lowers it, the balance equals SUM(credit - debit) over non-deleted
 * rows, and a debit beyond the available balance is rejected.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::credit
 * @covers Woo_Wallet_Wallet::debit
 * @covers Woo_Wallet_Wallet::get_wallet_balance
 */
class Test_Ledger_Credit_Debit extends WP_UnitTestCase {

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
	}

	/**
	 * Read the wallet balance as a float in 'edit' context.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * A credit returns a transaction id and raises the balance.
	 */
	public function test_credit_increases_balance() {
		$transaction_id = woo_wallet()->wallet->credit( $this->user_id, 50.00, 'Test credit' );

		$this->assertIsInt( $transaction_id );
		$this->assertGreaterThan( 0, $transaction_id );
		$this->assertEquals( 50.00, $this->balance() );
	}

	/**
	 * A debit lowers the balance by the debited amount.
	 */
	public function test_debit_decreases_balance() {
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'Seed' );
		$transaction_id = woo_wallet()->wallet->debit( $this->user_id, 30.00, 'Test debit' );

		$this->assertIsInt( $transaction_id );
		$this->assertEquals( 70.00, $this->balance() );
	}

	/**
	 * The reported balance equals the raw SUM(credit - debit) of the ledger.
	 */
	public function test_balance_equals_ledger_sum() {
		woo_wallet()->wallet->credit( $this->user_id, 10, 'a' );
		woo_wallet()->wallet->credit( $this->user_id, 25, 'b' );
		woo_wallet()->wallet->debit( $this->user_id, 5, 'c' );

		global $wpdb;
		$sum = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(CASE WHEN type='credit' THEN amount ELSE -amount END)
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE user_id = %d AND deleted = 0",
				$this->user_id
			)
		);

		$this->assertEquals( 30.0, $sum );
		$this->assertEquals( $sum, $this->balance() );
	}

	/**
	 * A debit larger than the balance is rejected and leaves the balance intact.
	 */
	public function test_overdraft_debit_is_rejected() {
		woo_wallet()->wallet->credit( $this->user_id, 20.00, 'Seed' );

		$result = woo_wallet()->wallet->debit( $this->user_id, 999.00, 'Overdraft attempt' );

		$this->assertFalse( $result );
		$this->assertEquals( 20.00, $this->balance() );
	}

	/**
	 * A debit against an empty wallet is rejected.
	 */
	public function test_debit_on_empty_wallet_is_rejected() {
		$result = woo_wallet()->wallet->debit( $this->user_id, 1.00, 'Debit empty wallet' );

		$this->assertFalse( $result );
		$this->assertEquals( 0.0, $this->balance() );
	}
}
