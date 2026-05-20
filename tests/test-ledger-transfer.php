<?php
/**
 * Peer-to-peer transfer integration tests.
 *
 * Exercises Woo_Wallet_Wallet::transfer(): funds move atomically from one
 * user to another, both ledger rows are written, and an underfunded
 * transfer is rejected without mutating either balance.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::transfer
 */
class Test_Ledger_Transfer extends WP_UnitTestCase {

	/**
	 * Sending customer.
	 *
	 * @var int
	 */
	private $sender;

	/**
	 * Receiving customer.
	 *
	 * @var int
	 */
	private $recipient;

	/**
	 * Create two fresh customers for each test.
	 *
	 * transfer() wraps its work in its own START TRANSACTION/COMMIT, which
	 * implicitly commits — and so defeats — WP_UnitTestCase's per-test
	 * rollback. The ledger tables are therefore truncated explicitly so each
	 * test starts from a deterministic, empty state.
	 */
	public function set_up() {
		parent::set_up();

		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transaction_meta" ); // phpcs:ignore WordPress.DB

		$this->sender    = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->recipient = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	/**
	 * Read a user's wallet balance as a float.
	 *
	 * @param int $user_id User id.
	 * @return float
	 */
	private function balance( $user_id ) {
		return (float) woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
	}

	/**
	 * A funded transfer moves the amount and writes both ledger rows.
	 */
	public function test_transfer_moves_funds_between_users() {
		woo_wallet()->wallet->credit( $this->sender, 100.00, 'Seed sender' );

		$result = woo_wallet()->wallet->transfer(
			$this->sender,
			$this->recipient,
			40.00,
			'Transfer out',
			'Transfer in'
		);

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'debit', $result );
		$this->assertArrayHasKey( 'credit', $result );
		$this->assertGreaterThan( 0, $result['debit'] );
		$this->assertGreaterThan( 0, $result['credit'] );

		$this->assertEquals( 60.00, $this->balance( $this->sender ) );
		$this->assertEquals( 40.00, $this->balance( $this->recipient ) );
	}

	/**
	 * A transfer larger than the sender's balance is rejected and no
	 * balance changes on either side.
	 */
	public function test_transfer_beyond_balance_is_rejected() {
		woo_wallet()->wallet->credit( $this->sender, 25.00, 'Seed sender' );

		$result = woo_wallet()->wallet->transfer(
			$this->sender,
			$this->recipient,
			500.00,
			'Transfer out',
			'Transfer in'
		);

		$this->assertFalse( $result );
		$this->assertEquals( 25.00, $this->balance( $this->sender ) );
		$this->assertEquals( 0.00, $this->balance( $this->recipient ) );
	}

	/**
	 * A transfer to the same user is rejected.
	 */
	public function test_transfer_to_self_is_rejected() {
		woo_wallet()->wallet->credit( $this->sender, 50.00, 'Seed sender' );

		$result = woo_wallet()->wallet->transfer(
			$this->sender,
			$this->sender,
			10.00,
			'Transfer out',
			'Transfer in'
		);

		$this->assertFalse( $result );
		$this->assertEquals( 50.00, $this->balance( $this->sender ) );
	}
}
