<?php
/**
 * M2 (1.7.4): a top-up never credits more than was paid for its line.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_topup_credit_amount
 * @covers Woo_Wallet_Wallet::wallet_credit_purchase
 */
class Test_Topup_Credit_Cap extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer, default settings.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		delete_option( '_wallet_settings_general' );
	}

	/**
	 * Top-up order: a typed-100 line (marked 1.7.2+ unless $marked is false).
	 *
	 * @param float $line_total Post-discount line total.
	 * @param array $fees       Fee name => amount.
	 * @param array $args       coupon (code), marked (bool), other (price of a second product line).
	 * @return WC_Order
	 */
	private function order( $line_total = 100, $fees = array(), $args = array() ) {
		$item = new WC_Order_Item_Product();
		$item->set_product_id( get_wallet_rechargeable_product()->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( $line_total );
		if ( $args['marked'] ?? true ) {
			$item->add_meta_data( WOO_Wallet_Helper::TOPUP_GROSS_META, 'yes', true );
		}
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->add_item( $item );
		if ( ! empty( $args['other'] ) ) {
			$product = new WC_Product_Simple();
			$product->set_regular_price( $args['other'] );
			$product->save();
			$other = new WC_Order_Item_Product();
			$other->set_product_id( $product->get_id() );
			$other->set_quantity( 1 );
			$other->set_subtotal( $args['other'] );
			$other->set_total( $args['other'] );
			$order->add_item( $other );
		}
		foreach ( $fees as $name => $amount ) {
			$fee = new WC_Order_Item_Fee();
			$fee->set_name( $name );
			$fee->set_total( $amount );
			$order->add_item( $fee );
		}
		if ( ! empty( $args['coupon'] ) ) {
			$coupon = new WC_Order_Item_Coupon();
			$coupon->set_code( $args['coupon'] );
			$coupon->set_discount( 100 - $line_total );
			$order->add_item( $coupon );
		}
		$order->set_payment_method( 'bacs' );
		$order->calculate_totals( false );
		$order->save();
		return $order;
	}

	/**
	 * Credit amount for an order.
	 *
	 * @param WC_Order $order Order.
	 * @return float
	 */
	private function credit( $order ) {
		return WOO_Wallet_Helper::get_topup_credit_amount( $order, get_wallet_rechargeable_product()->get_id() );
	}

	/**
	 * Turn on allow_coupons_on_topup.
	 */
	private function allow_coupons() {
		update_option( '_wallet_settings_general', array( 'allow_coupons_on_topup' => 'on' ) );
	}

	public function test_negative_fee_credits_what_was_paid() {
		$order = $this->order( 100, array( 'Discount' => -30 ) );
		$this->assertEqualsWithDelta( 70, (float) $order->get_total(), 0.001 );
		$this->assertEqualsWithDelta( 70, $this->credit( $order ), 0.001 );
	}

	public function test_paid_order_credits_the_capped_amount() {
		$order = $this->order( 100, array( 'Discount' => -30 ) );
		$order->update_status( 'processing' );
		$this->assertEqualsWithDelta( 70, (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' ), 0.001 );
	}

	public function test_line_discount_with_coupons_off_credits_what_was_paid() {
		$this->assertEqualsWithDelta( 90, $this->credit( $this->order( 90 ) ), 0.001 );
	}

	public function test_coupon_with_setting_on_credits_typed_amount() {
		$this->allow_coupons();
		$this->assertEqualsWithDelta( 100, $this->credit( $this->order( 90, array(), array( 'coupon' => 'tenoff' ) ) ), 0.001 );
	}

	public function test_setting_on_still_caps_negative_fees() {
		$this->allow_coupons();
		$this->assertEqualsWithDelta( 80, $this->credit( $this->order( 90, array( 'Discount' => -20 ), array( 'coupon' => 'tenoff' ) ) ), 0.001 );
	}

	public function test_setting_on_without_a_coupon_caps_line_discount() {
		$this->allow_coupons();
		$this->assertEqualsWithDelta( 90, $this->credit( $this->order( 90 ) ), 0.001 );
	}

	public function test_positive_fee_never_raises_credit() {
		$this->assertEqualsWithDelta( 100, $this->credit( $this->order( 100, array( 'Card surcharge' => 2.5 ) ) ), 0.001 );
	}

	public function test_positive_fee_does_not_offset_negative_fee() {
		// Owner decision (1.7.4): only negative fees count; a surcharge never raises the credit.
		$this->assertEqualsWithDelta( 95, $this->credit( $this->order( 100, array( 'Card surcharge' => 5, 'Discount' => -5 ) ) ), 0.001 );
	}

	public function test_unrounded_line_credits_what_the_order_charged() {
		// Round-at-subtotal style line: 16.664999 paid → the order and the ledger both land on 16.66.
		$order = $this->order( 16.664999, array(), array() );
		$this->assertSame( '16.66', wc_format_decimal( $this->credit( $order ), wc_get_price_decimals() ) );
		$this->assertSame( '16.66', wc_format_decimal( $order->get_total(), wc_get_price_decimals() ) );
	}

	public function test_normal_topup_unchanged() {
		$this->assertEqualsWithDelta( 100, $this->credit( $this->order() ), 0.001 );
	}

	public function test_credit_never_below_zero() {
		$this->assertSame( 0.0, $this->credit( $this->order( 100, array( 'Discount' => -150 ) ) ) );
	}

	public function test_negative_fee_shared_across_mixed_lines() {
		// Admin-made order: top-up 100 + product 100, fee −50 → top-up's share is −25.
		$this->assertEqualsWithDelta( 75, $this->credit( $this->order( 100, array( 'Discount' => -50 ), array( 'other' => 100 ) ) ), 0.001 );
	}

	public function test_pre_172_line_keeps_old_rule() {
		$this->assertEqualsWithDelta( 100, $this->credit( $this->order( 100, array( 'Discount' => -30 ), array( 'marked' => false ) ) ), 0.001 );
	}
}
