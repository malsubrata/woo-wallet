<?php
/**
 * Top-up credit amount tests (CVE-2026-16538).
 *
 * A wallet top-up goes through the normal WooCommerce cart, coupon field
 * included. Crediting the pre-discount line subtotal let a customer apply an
 * ordinary store coupon and mint wallet credit for less than its value. The
 * credited amount must equal the post-discount, pre-tax value of the top-up
 * line — what the store actually keeps — and never the pre-discount subtotal
 * nor the tax/shipping-inclusive order total.
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
	 * Ledger row count for this customer.
	 *
	 * @return int
	 */
	private function row_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d AND deleted = 0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Build a rechargeable order.
	 *
	 * @param float $line_subtotal pre-discount line subtotal (the requested top-up).
	 * @param float $line_total    post-discount line total (null = same as subtotal).
	 * @param float $line_tax      tax on the line.
	 * @param float $shipping      shipping total.
	 * @return WC_Order
	 */
	private function make_topup_order( $line_subtotal, $line_total = null, $line_tax = 0, $shipping = 0 ) {
		$product = get_wallet_rechargeable_product();
		$this->assertInstanceOf( 'WC_Product', $product, 'Rechargeable product must exist.' );

		if ( is_null( $line_total ) ) {
			$line_total = $line_subtotal;
		}

		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );

		$item = new WC_Order_Item_Product();
		$item->set_product_id( $product->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( $line_subtotal );
		$item->set_total( $line_total );
		if ( $line_tax ) {
			$item->set_taxes( array( 'total' => array( '1' => $line_tax ) ) );
		}
		$order->add_item( $item );

		if ( $shipping ) {
			$ship = new WC_Order_Item_Shipping();
			$ship->set_method_title( 'Flat rate' );
			$ship->set_total( $shipping );
			$order->add_item( $ship );
		}

		$order->set_shipping_total( $shipping );
		$order->set_cart_tax( $line_tax );
		$order->set_total( $line_total + $line_tax + $shipping );
		$order->save();

		$this->assertTrue( is_wallet_rechargeable_order( $order ) );

		return $order;
	}

	/**
	 * Drive the credit path.
	 *
	 * @param WC_Order $order order.
	 */
	private function credit_order( $order ) {
		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );
	}

	/**
	 * An undiscounted top-up credits the full amount.
	 */
	public function test_undiscounted_topup_credits_full_amount() {
		$this->credit_order( $this->make_topup_order( 100 ) );

		$this->assertEqualsWithDelta( 100.0, $this->balance(), 0.01 );
	}

	/**
	 * A coupon-discounted top-up credits only what was paid. This is the
	 * reported attack: 100 requested, 90% coupon, 10 collected.
	 */
	public function test_discounted_topup_credits_paid_amount() {
		$this->credit_order( $this->make_topup_order( 100, 10 ) );

		$this->assertEqualsWithDelta( 10.0, $this->balance(), 0.01, 'Credited the pre-discount subtotal.' );
	}

	/**
	 * Tax on an undiscounted top-up must not inflate the credit — the customer
	 * topped up 100, not 100 plus tax.
	 */
	public function test_taxed_topup_credits_line_total_not_order_total() {
		$this->credit_order( $this->make_topup_order( 100, 100, 18 ) );

		$this->assertEqualsWithDelta( 100.0, $this->balance(), 0.01 );
	}

	/**
	 * Coupon plus tax: the tax is collected but remitted, so only the
	 * discounted line value may be credited.
	 */
	public function test_discounted_and_taxed_topup_excludes_tax() {
		// 100 requested, 50% coupon, 18% tax on the discounted 50 → total 59.
		$this->credit_order( $this->make_topup_order( 100, 50, 9 ) );

		$this->assertEqualsWithDelta( 50.0, $this->balance(), 0.01, 'Credited the tax portion.' );
	}

	/**
	 * Shipping charged on a discounted top-up is not wallet value.
	 */
	public function test_discounted_topup_with_shipping_excludes_shipping() {
		$this->credit_order( $this->make_topup_order( 100, 50, 0, 5 ) );

		$this->assertEqualsWithDelta( 50.0, $this->balance(), 0.01, 'Credited the shipping portion.' );
	}

	/**
	 * A fully discounted top-up credits nothing and writes no ledger row, but
	 * still marks the order so later status transitions do not retry.
	 */
	public function test_fully_discounted_topup_writes_no_row() {
		$order = $this->make_topup_order( 100, 0 );
		$this->credit_order( $order );

		$this->assertSame( 0, $this->row_count(), 'Wrote an empty ledger row.' );
		$this->assertEqualsWithDelta( 0.0, $this->balance(), 0.01 );

		$order = wc_get_order( $order->get_id() );
		$this->assertTrue( (bool) $order->get_meta( '_wc_wallet_purchase_credited' ), 'Order not marked; the credit would be retried.' );
	}

	/**
	 * The public filter still overrides the credited amount.
	 */
	public function test_filter_overrides_credited_amount() {
		$callback = function () {
			return 42.0;
		};
		add_filter( 'woo_wallet_credit_purchase_amount', $callback );
		$this->credit_order( $this->make_topup_order( 100, 10 ) );
		remove_filter( 'woo_wallet_credit_purchase_amount', $callback );

		$this->assertEqualsWithDelta( 42.0, $this->balance(), 0.01 );
	}

	/**
	 * A replayed status transition credits once.
	 */
	public function test_repeat_credit_is_idempotent() {
		$order = $this->make_topup_order( 100, 10 );
		$this->credit_order( $order );
		$this->credit_order( $order );

		$this->assertSame( 1, $this->row_count() );
		$this->assertEqualsWithDelta( 10.0, $this->balance(), 0.01 );
	}
}
