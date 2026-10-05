<?php
/**
 * Dashboard liability on a multi-currency ledger: every currency group is
 * converted to the base currency before it is added up.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Reports_Data
 */
class Test_Reports_Data_Multicurrency extends WP_UnitTestCase {

	/**
	 * @var Woo_Wallet_Reports_Data
	 */
	private $service;

	/**
	 * Base GBP, fixed rate 1 GBP = 100 INR.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';
		$this->service = new Woo_Wallet_Reports_Data();
		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-reports';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_base_currency() {
					return 'GBP';
				}
				public function convert( $amount, $from, $to ) {
					if ( $from === $to ) {
						return (float) $amount;
					}
					$rate = $GLOBALS['ww_test_inr_rate'] ?? 100;
					return 'INR' === $from ? (float) $amount / $rate : (float) $amount * $rate;
				}
			},
			1
		);
	}

	/**
	 * Remove the fake provider.
	 */
	public function tear_down() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-reports' );
		unset( $GLOBALS['ww_test_inr_rate'] );
		parent::tear_down();
	}

	/**
	 * Insert a raw ledger row (bypasses write-time conversion on purpose: the
	 * point is to read rows exactly as an older store left them).
	 *
	 * @param int    $user_id  User.
	 * @param string $type     credit|debit.
	 * @param float  $amount   Amount.
	 * @param string $currency Currency.
	 * @param string $category Category.
	 */
	private function row( $user_id, $type, $amount, $currency, $category ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->base_prefix . 'woo_wallet_transactions',
			compact( 'user_id', 'type', 'amount', 'currency', 'category' ) + array( 'deleted' => 0 )
		);
	}

	/**
	 * GBP 10 + INR 1000 (= GBP 10) is GBP 20 — not 1010.
	 */
	public function test_mixed_currencies_are_converted_to_base() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$a = self::factory()->user->create();
		$b = self::factory()->user->create();
		$this->row( $a, 'credit', 10, 'GBP', 'topup' );
		$this->row( $b, 'credit', 1200, 'INR', 'cashback' );
		$this->row( $b, 'debit', 200, 'INR', 'purchase' );
		$this->row( $b, 'credit', 5, '', 'topup' ); // Legacy empty currency = base.

		$this->assertEqualsWithDelta( 25.0, $this->service->total_liability(), 0.0001 );
		$this->assertEqualsWithDelta( 27.0, $this->service->lifetime_credited(), 0.0001 );
		$this->assertEqualsWithDelta( 2.0, $this->service->lifetime_debited(), 0.0001 );
		$this->assertSame( 2, $this->service->positive_wallets_count() );

		$by_slug = wp_list_pluck( $this->service->liability_by_category(), 'amount', 'slug' );
		$this->assertEqualsWithDelta( 15.0, $by_slug['topup'], 0.0001 );
		$this->assertEqualsWithDelta( 12.0, $by_slug['cashback'], 0.0001 );
		$this->assertEqualsWithDelta( -2.0, $by_slug['purchase'], 0.0001 );
	}

	/**
	 * "Positive" is decided on the converted per-user total: a user who is up
	 * in one currency and down in another can net to zero.
	 */
	public function test_positive_wallet_uses_converted_per_user_total() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 10, 'GBP', 'topup' );
		$this->row( $u, 'debit', 1000, 'INR', 'purchase' ); // GBP 10 → nets to 0.
		$this->assertSame( 0, $this->service->positive_wallets_count() );
	}

	/**
	 * A rate change (switchers auto-update rates, no ledger write) must not
	 * serve a summary converted at the old rate from the transient.
	 */
	public function test_summary_cache_follows_rate_changes() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$u = self::factory()->user->create();
		$this->row( $u, 'credit', 1000, 'INR', 'topup' );

		$this->assertEqualsWithDelta( 10.0, $this->service->get_summary()['total_liability'], 0.0001 );
		$GLOBALS['ww_test_inr_rate'] = 125;
		$this->assertEqualsWithDelta( 8.0, $this->service->get_summary()['total_liability'], 0.0001 );
	}
}
