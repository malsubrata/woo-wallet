<?php
/**
 * Partial-payment debit-timing tests.
 *
 * Covers the `partial_payment_debit_on` setting: debit at order placement
 * (default) vs debit when the order reaches a paid status, the double-debit
 * guard, and the insufficient-balance "hold the order" fallback.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::woocommerce_order_processed
 * @covers Woo_Wallet_Wallet::maybe_debit_partial_payment_on_status
 */
class Test_Partial_Payment_Debit_Timing extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	/**
	 * Persist a value into the general settings section.
	 *
	 * @param string $key   option key.
	 * @param mixed  $value value.
	 */
	private function set_setting( $key, $value ) {
		$opt         = (array) get_option( '_wallet_settings_general', array() );
		$opt[ $key ] = $value;
		update_option( '_wallet_settings_general', $opt );
	}

	/**
	 * Wallet balance as float.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * Build an order carrying a partial-payment fee of the given gross amount.
	 *
	 * @param float $gross gross wallet amount (fee base + fee tax).
	 * @return WC_Order
	 */
	private function make_partial_order( $gross ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( 'Via wallet' );
		$fee->set_total( -1 * $gross );
		$fee->set_total_tax( 0 );
		$order->add_item( $fee );
		$order->save();
		return $order;
	}

	/**
	 * Default (order_created) mode debits at order placement and records the base amount.
	 */
	public function test_debits_at_order_placement_by_default() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_partial_order( 100 );

		woo_wallet()->wallet->woocommerce_order_processed( $order );

		$this->assertEquals( 100.0, $this->balance() );
		$fresh = wc_get_order( $order->get_id() );
		$this->assertNotEmpty( $fresh->get_meta( '_partial_pay_through_wallet_compleate' ) );
		$this->assertEquals( 100.0, (float) $fresh->get_meta( '_partial_payment_base_amount' ) );
	}

	/**
	 * payment_complete mode does NOT debit at order placement.
	 */
	public function test_no_debit_at_placement_in_payment_complete_mode() {
		$this->set_setting( 'partial_payment_debit_on', 'payment_complete' );
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_partial_order( 100 );

		woo_wallet()->wallet->woocommerce_order_processed( $order );

		$this->assertEquals( 200.0, $this->balance() );
		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( '_partial_pay_through_wallet_compleate' ) );
	}

	/**
	 * payment_complete mode debits when the order reaches a paid status.
	 */
	public function test_debits_on_paid_status_in_payment_complete_mode() {
		$this->set_setting( 'partial_payment_debit_on', 'payment_complete' );
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_partial_order( 100 );

		woo_wallet()->wallet->maybe_debit_partial_payment_on_status( $order->get_id() );

		$this->assertEquals( 100.0, $this->balance() );
	}

	/**
	 * The meta guard prevents a second debit for the same order.
	 */
	public function test_no_double_debit() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_partial_order( 100 );

		woo_wallet()->wallet->woocommerce_order_processed( $order );
		// Second call (e.g. duplicate webhook) must be a no-op.
		woo_wallet()->wallet->woocommerce_order_processed( wc_get_order( $order->get_id() ) );

		$this->assertEquals( 100.0, $this->balance() );
	}

	/**
	 * When the balance was spent before the debit, the order is held — never overdrafted.
	 */
	public function test_insufficient_balance_holds_order() {
		$this->set_setting( 'partial_payment_debit_on', 'payment_complete' );
		woo_wallet()->wallet->credit( $this->user_id, 40, 'seed' ); // less than the 100 gross.
		$order = $this->make_partial_order( 100 );

		$fired = false;
		add_action(
			'woo_wallet_partial_payment_debit_failed',
			function () use ( &$fired ) {
				$fired = true;
			}
		);

		woo_wallet()->wallet->maybe_debit_partial_payment_on_status( $order->get_id() );

		$this->assertEquals( 40.0, $this->balance() );
		$this->assertTrue( $fired );
		$this->assertEquals( 'on-hold', wc_get_order( $order->get_id() )->get_status() );
	}
}
