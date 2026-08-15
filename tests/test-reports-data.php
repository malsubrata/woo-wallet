<?php
/**
 * Wallet liability reporting — read-only aggregate tests.
 *
 * Seeds a known ledger across two users (including a soft-deleted row that must
 * be excluded) and asserts the reporting data service matches the manual
 * SUM(credit - debit) WHERE deleted = 0, and that the public metrics filter
 * actually mutates the assembled cards.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Reports_Data
 */
class Test_Reports_Data extends WP_UnitTestCase {

	/**
	 * @var Woo_Wallet_Reports_Data
	 */
	private $service;

	/**
	 * @var int
	 */
	private $user_a;

	/**
	 * @var int
	 */
	private $user_b;

	/**
	 * Seed a deterministic ledger.
	 */
	public function set_up() {
		parent::set_up();
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';
		$this->service = new Woo_Wallet_Reports_Data();

		$this->user_a = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->user_b = self::factory()->user->create( array( 'role' => 'customer' ) );

		// User A: +100, -30  => net 70.
		woo_wallet()->wallet->credit( $this->user_a, 100, 'seed a credit' );
		woo_wallet()->wallet->debit( $this->user_a, 30, 'seed a debit' );

		// User B: +25 => net 25.
		woo_wallet()->wallet->credit( $this->user_b, 25, 'seed b credit' );

		// A soft-deleted credit of 9999 that must never be counted.
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->base_prefix . 'woo_wallet_transactions',
			array(
				'user_id'  => $this->user_b,
				'type'     => 'credit',
				'category' => 'topup',
				'amount'   => 9999,
				'currency' => get_woocommerce_currency(),
				'deleted'  => 1,
			)
		);
	}

	/**
	 * Manual SUM(credit - debit) over live rows for the seeded users.
	 *
	 * @return float
	 */
	private function manual_total() {
		global $wpdb;
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0)
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE deleted = 0 AND user_id IN ( %d, %d )",
				$this->user_a,
				$this->user_b
			)
		);
	}

	/**
	 * Total liability equals the manual ledger sum (and is the expected 95).
	 */
	public function test_total_liability_matches_ledger_sum() {
		$this->assertEquals( 95.0, $this->manual_total() );
		$this->assertEquals( $this->manual_total(), $this->service->total_liability() );
	}

	/**
	 * The soft-deleted 9999 row is excluded from every aggregate.
	 */
	public function test_deleted_rows_are_excluded() {
		$this->assertEquals( 95.0, $this->service->total_liability() );
		// 100 + 25 credited (deleted 9999 excluded), 30 debited.
		$this->assertEquals( 125.0, $this->service->lifetime_credited() );
		$this->assertEquals( 30.0, $this->service->lifetime_debited() );
	}

	/**
	 * Both seeded wallets carry a positive balance.
	 */
	public function test_positive_wallet_count() {
		$this->assertGreaterThanOrEqual( 2, $this->service->positive_wallets_count() );
	}

	/**
	 * The summary composition sums back to the total liability.
	 */
	public function test_composition_sums_to_total() {
		$summary = $this->service->get_summary();
		$sum     = 0.0;
		foreach ( $summary['composition'] as $row ) {
			$sum += (float) $row['amount'];
		}
		$this->assertEquals( $summary['total_liability'], round( $sum, 8 ) );
	}

	/**
	 * Category labels come from the canonical transaction-type registry, not a
	 * second hard-coded map that silently omits slugs.
	 */
	public function test_category_labels_cover_every_registered_type() {
		$service = new ReflectionMethod( 'Woo_Wallet_Reports_Data', 'category_labels' );
		$service->setAccessible( true );
		$labels = $service->invoke( $this->service );

		foreach ( array_keys( woo_wallet_get_transaction_types() ) as $slug ) {
			$this->assertArrayHasKey(
				$slug,
				$labels,
				"Registered category '{$slug}' has no report label and would display as a raw slug."
			);
		}
	}

	/**
	 * Partial-payment cancellation refunds must not fall through to 'other'.
	 *
	 * Woo_Wallet_Wallet passes `for => partial_payment_refund` when crediting a
	 * cancelled order back; before 1.6.11 that slug was unregistered, so
	 * resolve_category_and_details() collapsed it to 'other' and the credit
	 * showed up as an uncategorised chunk of liability.
	 */
	public function test_partial_payment_refund_is_a_known_category() {
		$this->assertTrue(
			woo_wallet_is_known_transaction_type( 'partial_payment_refund' ),
			'partial_payment_refund must be registered or cancellation refunds land in "other".'
		);
	}

	/**
	 * The composition rows reconcile: credits plus debits equal the headline
	 * liability figure the dashboard renders above the card.
	 */
	public function test_composition_credits_and_debits_reconcile() {
		$summary  = $this->service->get_summary();
		$positive = 0.0;
		$negative = 0.0;
		foreach ( $summary['composition'] as $row ) {
			if ( $row['amount'] > 0 ) {
				$positive += (float) $row['amount'];
			} else {
				$negative += (float) $row['amount'];
			}
		}

		$this->assertEquals( $summary['total_liability'], round( $positive + $negative, 8 ) );

		// The shares rendered in the card are of credit issued, so that
		// denominator must be the positive subtotal — never the net.
		$this->assertGreaterThanOrEqual( $summary['total_liability'], $positive );
	}

	/**
	 * The woo_wallet_reports_metrics filter mutates the assembled cards.
	 */
	public function test_metrics_filter_mutates_output() {
		require_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-reports.php';
		new Woo_Wallet_Reports(); // Registers the free default cards.

		$callback = function ( $metrics ) {
			$metrics[] = array(
				'id'    => 'throwaway_test_card',
				'label' => 'Throwaway',
				'value' => '42',
			);
			return $metrics;
		};
		add_filter( 'woo_wallet_reports_metrics', $callback, 20, 2 );

		$metrics = apply_filters( 'woo_wallet_reports_metrics', array(), array() );
		$ids     = wp_list_pluck( $metrics, 'id' );

		remove_filter( 'woo_wallet_reports_metrics', $callback, 20 );

		$this->assertContains( 'throwaway_test_card', $ids );
		$this->assertContains( 'total_liability', $ids );
	}
}
