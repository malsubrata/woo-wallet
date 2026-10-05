<?php
/**
 * Delete logs → "Keep current balance": the carry-over preserves every
 * currency the wallet holds, in that currency. It used to add INR and GBP
 * amounts into one number and stamp it with one currency (₹3,642 → £3,642).
 *
 * @package WooWallet\Tests
 */

/**
 * @covers ::woo_wallet_purge_user_transactions
 */
class Test_Purge_Keep_Balance extends WP_UnitTestCase {

	/**
	 * Base GBP — via a filter, not update_option(): the purge's own START
	 * TRANSACTION implicitly commits WP_UnitTestCase's per-test transaction,
	 * so any DB write made before it would leak into later tests.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_option_woocommerce_currency', array( $this, 'gbp' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Rows seeded here were committed by the purge (see set_up), so the
	 * per-test rollback can't remove them. TRUNCATE commits itself, leaving
	 * later tests the empty ledger they expect (same as test-ledger-transfer).
	 */
	public function tear_down() {
		global $wpdb;
		remove_filter( 'pre_option_woocommerce_currency', array( $this, 'gbp' ) );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transaction_meta" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	/**
	 * Store base currency for these tests.
	 *
	 * @return string
	 */
	public function gbp() {
		return 'GBP';
	}

	/**
	 * Insert a raw ledger row.
	 *
	 * @param int    $user_id  User.
	 * @param string $type     credit|debit.
	 * @param float  $amount   Amount.
	 * @param string $currency Currency ('' = legacy row).
	 */
	private function row( $user_id, $type, $amount, $currency ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->base_prefix . 'woo_wallet_transactions',
			array(
				'user_id'  => $user_id,
				'type'     => $type,
				'amount'   => $amount,
				'currency' => $currency,
				'deleted'  => 0,
			)
		);
	}

	/**
	 * Net per currency over live rows.
	 *
	 * @param int $user_id User.
	 * @return array<string,float>
	 */
	private function nets( $user_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT currency, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) AS net FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d AND deleted = 0 GROUP BY currency ORDER BY currency", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$out  = array();
		foreach ( $rows as $r ) {
			$out[ $r->currency ] = round( (float) $r->net, 6 );
		}
		return $out;
	}

	/**
	 * Live row count.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	private function live_rows( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d AND deleted = 0", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Single-currency store: one carry-over row, same balance as before.
	 */
	public function test_single_currency_keep_is_unchanged() {
		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 100, 'GBP' );
		$this->row( $u, 'debit', 30, 'GBP' );

		$result = woo_wallet_purge_user_transactions( $u, 'soft', 'keep' );

		$this->assertNotWPError( $result );
		$this->assertSame( array( 'GBP' => 70.0 ), $this->nets( $u ) );
		$this->assertSame( 1, $this->live_rows( $u ) );
		$this->assertEquals( 70.0, $result['pre_balance'] );
		$this->assertCount( 1, $result['balancing_txn_ids'] );
		$this->assertSame( $result['balancing_txn_ids'][0], $result['balancing_txn_id'] );
	}

	/**
	 * Mixed GBP + INR wallet: each currency is carried over in its own currency.
	 */
	public function test_mixed_currency_keep_preserves_each_currency() {
		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 3700, 'INR' );
		$this->row( $u, 'debit', 57.66, 'INR' );
		$this->row( $u, 'credit', 1.30, 'GBP' );

		$result = woo_wallet_purge_user_transactions( $u, 'soft', 'keep' );

		$this->assertNotWPError( $result );
		$this->assertSame( array( 'GBP' => 1.3, 'INR' => 3642.34 ), $this->nets( $u ) );
		$this->assertSame( 2, $this->live_rows( $u ) );
		$this->assertCount( 2, $result['balancing_txn_ids'] );
	}

	/**
	 * A negative currency balance is carried over as a debit; legacy rows
	 * with no currency merge into the base-currency carry-over.
	 */
	public function test_negative_and_legacy_rows() {
		$u = self::factory()->user->create();
		$this->row( $u, 'debit', 5, 'INR' );
		$this->row( $u, 'credit', 4, '' );
		$this->row( $u, 'credit', 6, 'GBP' );

		woo_wallet_purge_user_transactions( $u, 'hard', 'keep' );

		$this->assertSame( array( 'GBP' => 10.0, 'INR' => -5.0 ), $this->nets( $u ) );
		$this->assertSame( 2, $this->live_rows( $u ) );
	}

	/**
	 * Wipe still leaves nothing behind.
	 */
	public function test_wipe_leaves_zero() {
		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 1000, 'INR' );
		$this->row( $u, 'credit', 2, 'GBP' );

		$result = woo_wallet_purge_user_transactions( $u, 'soft', 'wipe' );

		$this->assertSame( array(), $this->nets( $u ) );
		$this->assertSame( 0, $result['balancing_txn_id'] );
		$this->assertEquals( 0, get_user_meta( $u, '_current_woo_wallet_balance', true ) );
	}

	/**
	 * If a carry-over row cannot be written, nothing is deleted.
	 */
	public function test_failed_carry_over_rolls_back_the_purge() {
		global $wpdb;
		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 50, 'GBP' );
		$this->row( $u, 'credit', 900, 'INR' );

		$break = function ( $query ) use ( $wpdb ) {
			if ( 0 === strpos( ltrim( $query ), "INSERT INTO `{$wpdb->base_prefix}woo_wallet_transactions`" ) && false !== strpos( $query, "'INR'" ) ) {
				return 'INSERT INTO woo_wallet_missing_table_for_test (x) VALUES (1)';
			}
			return $query;
		};
		add_filter( 'query', $break );
		$suppress = $wpdb->suppress_errors( true );
		$result   = woo_wallet_purge_user_transactions( $u, 'soft', 'keep' );
		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', $break );

		$this->assertWPError( $result );
		$this->assertSame( array( 'GBP' => 50.0, 'INR' => 900.0 ), $this->nets( $u ) );
		$this->assertSame( 2, $this->live_rows( $u ), 'The original rows must still be live.' );
	}

	/**
	 * If the delete itself fails, no carry-over is added (old rows + carry-over
	 * would double the balance) and the purge reports an error.
	 */
	public function test_failed_delete_adds_no_carry_over() {
		global $wpdb;
		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 100, 'GBP' );

		$break = function ( $query ) use ( $wpdb ) {
			if ( 0 === strpos( ltrim( $query ), "UPDATE `{$wpdb->base_prefix}woo_wallet_transactions` SET `deleted`" ) ) {
				return 'UPDATE woo_wallet_missing_table_for_test SET x = 1';
			}
			return $query;
		};
		add_filter( 'query', $break );
		$suppress = $wpdb->suppress_errors( true );
		$result   = woo_wallet_purge_user_transactions( $u, 'soft', 'keep' );
		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', $break );

		$this->assertWPError( $result );
		$this->assertSame( array( 'GBP' => 100.0 ), $this->nets( $u ) );
		$this->assertSame( 1, $this->live_rows( $u ) );
	}
}
