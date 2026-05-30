<?php
/**
 * Ledger precision / rounding integration tests.
 *
 * Guards the money-quantization invariant: the spendable balance reported to
 * checkout must never exceed the true raw ledger SUM, and amounts written to
 * the ledger must be quantized to the store's price decimals so no sub-cent
 * "dust" accumulates.
 *
 * Regression coverage for the round-half-up loophole: a raw balance of
 * 124.12511111 displayed as 124.13 could not be debited (124.13 > raw), which
 * blocked wallet-gateway payments and silently broke partial payments.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::get_wallet_balance
 * @covers Woo_Wallet_Wallet::recode_transaction
 */
class Test_Ledger_Precision extends WP_UnitTestCase {

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
	 * Raw, unrounded ledger balance (the authoritative SUM).
	 *
	 * @return float
	 */
	private function balance_raw() {
		global $wpdb;
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0)
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE user_id = %d AND deleted = 0",
				$this->user_id
			)
		);
	}

	/**
	 * Spendable balance as checkout sees it ('edit' numeric context).
	 *
	 * @return float
	 */
	private function spendable() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * Insert a legacy, unrounded credit row directly — simulating sub-cent dust
	 * left behind by pre-fix multicurrency conversion (e.g. 1 EUR -> 111.111… INR).
	 * Bypasses credit() on purpose, since the fixed write path now quantizes.
	 *
	 * @param float $amount Raw amount to persist.
	 */
	private function insert_raw_credit( $amount ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"{$wpdb->base_prefix}woo_wallet_transactions",
			array(
				'blog_id'  => get_current_blog_id(),
				'user_id'  => $this->user_id,
				'type'     => 'credit',
				'amount'   => $amount,
				'currency' => get_woocommerce_currency(),
				'date'     => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%f', '%s', '%s' )
		);
		clear_woo_wallet_cache( $this->user_id );
	}

	/**
	 * The reported loophole: a raw balance with sub-cent dust must surface a
	 * spendable balance that is (a) never greater than the real balance and
	 * (b) fully debitable.
	 */
	public function test_dusty_balance_is_spendable_and_never_overstated() {
		$this->insert_raw_credit( 124.12511111 );

		$raw = $this->balance_raw();
		$this->assertEqualsWithDelta( 124.12511111, $raw, 0.0000001 );

		$spendable = $this->spendable();

		// The core invariant — checkout must never offer more than truly exists.
		$this->assertLessThanOrEqual( $raw, $spendable );
		$this->assertEquals( 124.12, $spendable );

		// And the full spendable amount must actually clear the debit gate.
		$result = woo_wallet()->wallet->debit( $this->user_id, $spendable, 'Spend full balance' );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * A balance that is already a clean cent value must not lose a cent to
	 * float-truncation when flooring (124.13 * 100 = 12412.9999… in IEEE754).
	 */
	public function test_clean_cent_balance_is_not_eroded() {
		$this->insert_raw_credit( 124.13 );

		$this->assertEquals( 124.13, $this->spendable() );

		$result = woo_wallet()->wallet->debit( $this->user_id, 124.13, 'Spend clean balance' );
		$this->assertIsInt( $result );
	}

	/**
	 * Amounts are quantized to the store's price decimals on write, so no new
	 * sub-cent dust can enter the ledger.
	 */
	public function test_write_quantizes_subcent_amounts() {
		woo_wallet()->wallet->credit( $this->user_id, 0.014, 'Dusty credit' );

		$this->assertEquals( 0.01, $this->balance_raw() );
		$this->assertEquals( 0.01, $this->spendable() );
	}
}
