<?php
/**
 * Dashboard stat-card aggregation tests.
 *
 * Covers `woo_wallet_get_user_category_total()` — the single helper behind both
 * the customer dashboard stat cards and the admin user-report figures. The most
 * important guarantee is that "Total spent" (category `partial_payment`) counts
 * BOTH partial payments and full wallet-gateway payments, because the
 * full-payment debit records with `'for' => 'purchase'`, which the ledger
 * canonicalises to `partial_payment`.
 *
 * Also exercises the card registry (`woo_wallet_get_dashboard_stat_cards()`):
 * the cashback card is gated on the reward-program setting, and the
 * `woo_wallet_dashboard_stat_cards` filter lets third parties add cards that
 * sort by priority.
 *
 * @package WooWallet\Tests
 */

class Test_Dashboard_Stats extends WP_UnitTestCase {

	private $user_id;

	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		update_option( '_wallet_settings_credit', array() );
	}

	/**
	 * Top-ups sum only `topup` credits.
	 */
	public function test_total_topup() {
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'Top-up A', array( 'category' => 'topup' ) );
		woo_wallet()->wallet->credit( $this->user_id, 50.00, 'Top-up B', array( 'category' => 'topup' ) );
		// A non-topup credit must not count.
		woo_wallet()->wallet->credit( $this->user_id, 9.00, 'Cashback', array( 'category' => 'cashback' ) );

		$this->assertEqualsWithDelta(
			150.00,
			woo_wallet_get_user_category_total( $this->user_id, 'credit', 'topup' ),
			0.001
		);
	}

	/**
	 * Total spent counts a partial payment AND a full wallet payment. Since 1.6.4
	 * the full-payment `for => purchase` debit records as its own
	 * `category = purchase` (no longer aliased to `partial_payment`), so the
	 * "total spent" figure must sum BOTH `purchase` and `partial_payment` — which
	 * is exactly what the dashboard/admin aggregations pass.
	 */
	public function test_total_spent_includes_full_and_partial_payments() {
		woo_wallet()->wallet->credit( $this->user_id, 500.00, 'Fund', array( 'category' => 'topup' ) );

		// Partial payment debit.
		woo_wallet()->wallet->debit( $this->user_id, 40.00, 'Partial pay #1', array( 'category' => 'partial_payment' ) );
		// Full wallet-gateway payment debit (legacy `for` arg → `purchase`).
		woo_wallet()->wallet->debit( $this->user_id, 60.00, 'Full pay #2', array( 'for' => 'purchase' ) );
		// A non-purchase debit (e.g. transfer/withdrawal) must not count as spent.
		woo_wallet()->wallet->debit( $this->user_id, 25.00, 'Adjustment', array( 'category' => 'adjustment' ) );

		$this->assertEqualsWithDelta(
			100.00,
			woo_wallet_get_user_category_total( $this->user_id, 'debit', array( 'purchase', 'partial_payment' ) ),
			0.001
		);
	}

	/**
	 * Cashback sums `cashback` credits.
	 */
	public function test_total_cashback() {
		woo_wallet()->wallet->credit( $this->user_id, 12.50, 'Cashback A', array( 'category' => 'cashback' ) );
		woo_wallet()->wallet->credit( $this->user_id, 7.50, 'Cashback B', array( 'category' => 'cashback' ) );

		$this->assertEqualsWithDelta(
			20.00,
			woo_wallet_get_user_category_total( $this->user_id, 'credit', 'cashback' ),
			0.001
		);
	}

	/**
	 * Unknown user / empty category return 0.
	 */
	public function test_empty_inputs_return_zero() {
		$this->assertSame( 0.0, woo_wallet_get_user_category_total( 0, 'credit', 'topup' ) );
		$this->assertSame( 0.0, woo_wallet_get_user_category_total( $this->user_id, 'credit', '' ) );
	}

	/**
	 * The cashback card appears only when the reward program is enabled.
	 */
	public function test_cashback_card_gated_on_setting() {
		wp_set_current_user( $this->user_id );

		update_option( '_wallet_settings_credit', array( 'is_enable_cashback_reward_program' => 'off' ) );
		$cards = woo_wallet_get_dashboard_stat_cards( $this->user_id );
		$this->assertArrayNotHasKey( 'cashback', $cards );
		$this->assertArrayHasKey( 'topup', $cards );
		$this->assertArrayHasKey( 'spent', $cards );
		$this->assertArrayHasKey( 'balance', $cards );

		update_option( '_wallet_settings_credit', array( 'is_enable_cashback_reward_program' => 'on' ) );
		$cards = woo_wallet_get_dashboard_stat_cards( $this->user_id );
		$this->assertArrayHasKey( 'cashback', $cards );
	}

	/**
	 * Third-party cards register via the filter and sort by priority.
	 */
	public function test_filter_registers_and_sorts_cards() {
		wp_set_current_user( $this->user_id );

		$cb = function ( $cards, $user_id ) {
			$cards['withdrawal'] = array(
				'id'       => 'withdrawal',
				'priority' => 40,
				'tone'     => 'warning',
				'icon'     => 'arrow-up',
				'label'    => 'Total withdrawn',
				'value'    => '0',
			);
			return $cards;
		};
		add_filter( 'woo_wallet_dashboard_stat_cards', $cb, 10, 2 );
		$cards = woo_wallet_get_dashboard_stat_cards( $this->user_id );
		remove_filter( 'woo_wallet_dashboard_stat_cards', $cb, 10 );

		$this->assertArrayHasKey( 'withdrawal', $cards );

		// Priority order: topup(10), spent(20), withdrawal(40), balance(90).
		$order = array_keys( $cards );
		$this->assertLessThan( array_search( 'balance', $order, true ), array_search( 'withdrawal', $order, true ) );
		$this->assertLessThan( array_search( 'withdrawal', $order, true ), array_search( 'spent', $order, true ) );
	}

	/**
	 * The icon helper returns a trusted SVG for core keys, a span for
	 * dashicons, and strips disallowed markup from third-party input.
	 */
	public function test_icon_helper() {
		$this->assertStringContainsString( '<svg', woo_wallet_get_stat_card_icon( 'arrow-up' ) );
		$this->assertStringContainsString( 'dashicons-money-alt', woo_wallet_get_stat_card_icon( 'dashicons-money-alt' ) );

		$dirty = woo_wallet_get_stat_card_icon( '<svg><script>alert(1)</script><path d="M0 0"></path></svg>' );
		$this->assertStringNotContainsString( '<script', $dirty );
		$this->assertStringContainsString( '<path', $dirty );
	}
}
