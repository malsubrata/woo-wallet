<?php
/**
 * Top-up credit amount tests (CVE-2026-16538).
 *
 * A wallet top-up goes through the normal WooCommerce cart, coupon field
 * included. Crediting the pre-discount line subtotal let a customer apply an
 * ordinary store coupon and mint wallet credit for less than its value. The
 * credited amount must never exceed what the order actually collected.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::wallet_credit_purchase
 */
class Test_Credit_Purchase_Discount extends WP_UnitTestCase {

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
	 * Wallet balance as float.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * Build a rechargeable order for $line_total with an explicit paid total.
	 *
	 * @param float $line_total  line subtotal (the requested top-up amount).
	 * @param float $order_total amount actually collected.
	 * @return WC_Order
	 */
	private function make_topup_order( $line_total, $order_total ) {
		$product = get_wallet_rechargeable_product();
		$this->assertInstanceOf( 'WC_Product', $product, 'Rechargeable product must exist.' );

		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );

		$item = new WC_Order_Item_Product();
		$item->set_product_id( $product->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( $line_total );
		$item->set_total( $line_total );
		$order->add_item( $item );

		$order->set_total( $order_total );
		$order->save();

		$this->assertTrue( is_wallet_rechargeable_order( $order ) );

		return $order;
	}

	/**
	 * A coupon-discounted top-up credits only what was paid, not the subtotal.
	 */
	public function test_discounted_topup_credits_paid_total() {
		// 100 requested, 90% coupon applied, 10 collected.
		$order = $this->make_topup_order( 100, 10 );

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );

		$this->assertEqualsWithDelta( 10.0, $this->balance(), 0.01, 'Credited the pre-discount subtotal.' );
	}

	/**
	 * An undiscounted top-up still credits the full amount.
	 */
	public function test_undiscounted_topup_credits_full_amount() {
		$order = $this->make_topup_order( 100, 100 );

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );

		$this->assertEqualsWithDelta( 100.0, $this->balance(), 0.01 );
	}

	/**
	 * Tax or shipping pushing the total above the subtotal must not inflate the
	 * credit — the customer topped up 100, not 100 plus tax.
	 */
	public function test_taxed_topup_credits_subtotal_not_total() {
		$order = $this->make_topup_order( 100, 118 );

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );

		$this->assertEqualsWithDelta( 100.0, $this->balance(), 0.01 );
	}
}
