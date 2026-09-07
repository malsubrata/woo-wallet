<?php
/**
 * Wallet payment gateway ("Woo_Gateway_Wallet_payment") regression tests.
 *
 * An HPOS-compatibility audit found `process_payment()`'s two failure
 * branches used to `return;` (null) instead of an array. WooCommerce Blocks /
 * Store API checkout calls `array_merge( $result->payment_details,
 * $gateway_result )` on that return value
 * (`Automattic\WooCommerce\StoreApi\Legacy::process_legacy_payment()`), so a
 * null result was a fatal `TypeError`, not a graceful checkout failure. These
 * tests pin both failure branches to an array with `result => failure`, the
 * happy path to `result => success` plus a redirect, and the two null guards
 * added alongside it in `process_refund()` and
 * `woocommerce_pre_payment_complete()`.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Gateway_Wallet_payment::process_payment
 * @covers Woo_Gateway_Wallet_payment::process_refund
 * @covers Woo_Gateway_Wallet_payment::woocommerce_pre_payment_complete
 */
class Test_Payment_Method_Gateway extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * The gateway under test.
	 *
	 * @var Woo_Gateway_Wallet_payment
	 */
	private $gateway;

	/**
	 * Fresh customer, logged in, reusing the gateway instance WooCommerce
	 * already registered — a second `new Woo_Gateway_Wallet_payment()` would
	 * add a duplicate `woocommerce_pre_payment_complete` listener and double-debit.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->user_id );
		$this->gateway = WC()->payment_gateways()->payment_gateways()['wallet'];
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
	 * Build an unsaved-total order for this customer, paid via the wallet gateway.
	 *
	 * @param float $total order total.
	 * @return WC_Order
	 */
	private function make_order( $total ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$order->set_payment_method( 'wallet' );
		$order->set_total( $total );
		$order->save();
		return $order;
	}

	/**
	 * A non-existent order id must not fatal (the Blocks TypeError regression)
	 * and must report failure as an array, not null.
	 */
	public function test_process_payment_invalid_order_returns_failure_array() {
		$nonexistent_order_id = 999999999;

		$result = $this->gateway->process_payment( $nonexistent_order_id );

		$this->assertIsArray( $result, 'A null return here is exactly the Store API TypeError this guards against.' );
		$this->assertArrayHasKey( 'result', $result );
		$this->assertSame( 'failure', $result['result'] );
	}

	/**
	 * Insufficient wallet balance is the sibling failure branch — also an array.
	 */
	public function test_process_payment_insufficient_balance_returns_failure_array() {
		woo_wallet()->wallet->credit( $this->user_id, 10.00, 'seed' );
		$order = $this->make_order( 50.00 ); // exceeds the 10.00 balance.

		$result = $this->gateway->process_payment( $order->get_id() );

		$this->assertIsArray( $result, 'A null return here is exactly the Store API TypeError this guards against.' );
		$this->assertArrayHasKey( 'result', $result );
		$this->assertSame( 'failure', $result['result'] );
		// No money should have moved on the failure path.
		$this->assertEquals( 10.00, $this->balance() );
	}

	/**
	 * Sufficient balance debits the wallet and returns a success + redirect array.
	 */
	public function test_process_payment_success_returns_redirect_array() {
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'seed' );
		$order = $this->make_order( 40.00 );

		$result = $this->gateway->process_payment( $order->get_id() );

		$this->assertIsArray( $result );
		$this->assertSame( 'success', $result['result'] );
		$this->assertArrayHasKey( 'redirect', $result );
		$this->assertNotEmpty( $result['redirect'] );
		$this->assertEquals( 60.00, $this->balance() );
	}

	/**
	 * `process_refund()` on an invalid order id returns a WP_Error, not a fatal
	 * or a bare boolean, and does not credit anything.
	 */
	public function test_process_refund_invalid_order_returns_wp_error() {
		$nonexistent_order_id = 999999999;

		$result = $this->gateway->process_refund( $nonexistent_order_id, 10.00 );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertEquals( 0.0, $this->balance() );
	}

	/**
	 * `woocommerce_pre_payment_complete()` on an invalid order id must return
	 * early without fataling and without moving any money.
	 */
	public function test_pre_payment_complete_invalid_order_no_fatal_no_debit() {
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'seed' );
		$nonexistent_order_id = 999999999;

		// No exception / fatal is itself the assertion; a reverted guard would
		// hit wc_get_order( $order_id )->get_payment_method() on false and fatal.
		$this->gateway->woocommerce_pre_payment_complete( $nonexistent_order_id );

		$this->assertEquals( 100.00, $this->balance() );
	}
}
