<?php
/**
 * Partial-payment refund tests.
 *
 * Covers returning the wallet-paid portion proportionally on a partial
 * WooCommerce refund: proration, idempotency, the cumulative cap, the
 * interaction with a following cancellation, FX-stable reversal via the stored
 * base amount, and that a taxable fee's gross (base + tax) is captured.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::process_partial_payment_refund
 * @covers Woo_Wallet_Wallet::process_cancelled_order
 */
class Test_Partial_Payment_Refund extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer; isolate the auto refund hook so the method is driven explicitly.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		remove_action( 'woocommerce_order_refunded', array( woo_wallet()->wallet, 'process_partial_payment_refund' ), 10 );
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
	 * Build an order with a partial-payment fee and an explicit order total.
	 *
	 * @param float $fee_base    fee base (positive; stored as negative).
	 * @param float $fee_tax     fee tax (positive; stored as negative).
	 * @param float $order_total order total to persist.
	 * @return WC_Order
	 */
	private function make_order( $fee_base, $fee_tax, $order_total ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( 'Via wallet' );
		$fee->set_total( -1 * $fee_base );
		if ( $fee_tax ) {
			// Set tax through the taxes array (total_tax is recomputed from it on save).
			$fee->set_taxes( array( 'total' => array( '1' => -1 * $fee_tax ) ) );
		}
		$order->add_item( $fee );
		$order->set_total( $order_total );
		$order->save();
		return $order;
	}

	/**
	 * Place + debit the order through the normal path so markers/base meta are set.
	 *
	 * @param WC_Order $order order.
	 */
	private function debit_order( $order ) {
		woo_wallet()->wallet->woocommerce_order_processed( $order );
	}

	/**
	 * A partial refund returns the wallet portion proportionally.
	 */
	public function test_proportional_refund() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_order( 100, 0, 100 ); // gross 100, order total 100.
		$this->debit_order( $order );               // balance now 100.

		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 50,
			)
		);
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $refund->get_id() );

		$this->assertEquals( 150.0, $this->balance() ); // +50.
		$this->assertEquals( 50.0, (float) wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_refunded_total' ) );
	}

	/**
	 * Reprocessing the same refund id is a no-op.
	 */
	public function test_refund_is_idempotent() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_order( 100, 0, 100 );
		$this->debit_order( $order );

		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 50,
			)
		);
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $refund->get_id() );
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $refund->get_id() );

		$this->assertEquals( 150.0, $this->balance() ); // credited once.
	}

	/**
	 * Cumulative wallet refunds never exceed the original wallet debit.
	 */
	public function test_cumulative_refund_capped_at_debit() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_order( 100, 0, 100 );
		$this->debit_order( $order ); // balance 100.

		$r1 = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 60,
			)
		);
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $r1->get_id() );
		$r2 = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 40,
			)
		);
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $r2->get_id() );

		// 60% + 40% of a 100 wallet payment == exactly 100 returned, never more.
		$this->assertEquals( 200.0, $this->balance() );
		$this->assertEquals( 100.0, (float) wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_refunded_total' ) );
	}

	/**
	 * Cancelling after a partial refund only returns the remaining wallet portion.
	 */
	public function test_cancel_after_partial_refund_returns_remainder() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_order( 100, 0, 100 );
		$this->debit_order( $order ); // balance 100.

		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 40,
			)
		);
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $refund->get_id() ); // +40 -> 140.

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() ); // remainder 60 -> 200.

		$this->assertEquals( 200.0, $this->balance() );
		$this->assertNotEmpty( wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_payment_refunded' ) );
	}

	/**
	 * A taxable fee's gross (base + tax) is the amount debited.
	 */
	public function test_tax_inclusive_gross_is_debited() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_order( 100, 10, 0 ); // base 100 + tax 10 = gross 110.
		$this->debit_order( $order );

		$this->assertEquals( 90.0, $this->balance() ); // 200 - 110.
	}

	/**
	 * FX-stable: the refund credits the stored base amount, not a fresh conversion.
	 */
	public function test_refund_uses_stored_base_amount() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = $this->make_order( 100, 0, 100 ); // order-currency gross 100.
		// Simulate a cross-currency debit whose base value was 80 (not 100).
		$order->update_meta_data( '_partial_pay_through_wallet_compleate', 1 );
		$order->update_meta_data( '_partial_payment_base_amount', 80 );
		$order->update_meta_data( '_partial_payment_base_currency', get_woocommerce_currency() );
		$order->save();

		$refund = wc_create_refund(
			array(
				'order_id' => $order->get_id(),
				'amount'   => 50,
			)
		);
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $refund->get_id() );

		// refund_now (order ccy) = 50, but credit uses base: 80 * (50/100) = 40.
		$this->assertEquals( 240.0, $this->balance() );
	}
}
