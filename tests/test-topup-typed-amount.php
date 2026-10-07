<?php
/**
 * Top-up "typed amount" tests (1.7.2).
 *
 * From 1.7.2 a top-up credits exactly the amount the customer typed
 * (tax-inclusive), the customer pays exactly that amount on both
 * tax-inclusive and tax-exclusive stores, and store coupons are refused on
 * top-ups unless `allow_coupons_on_topup` is on. Orders created before 1.7.2
 * (no line marker) keep the old post-discount, pre-tax credit — that legacy
 * rule is covered by Test_Credit_Purchase_Discount.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_topup_credit_amount
 * @covers WOO_Wallet_Helper::get_topup_net_price
 * @covers Woo_Wallet_Frontend::woo_wallet_set_recharge_product_price
 * @covers Woo_Wallet_Frontend::mark_topup_order_line_item
 * @covers Woo_Wallet_Frontend::restrict_coupon_on_topup
 * @covers WooWallet_Topup_Service::create_order
 */
class Test_Topup_Typed_Amount extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer, a 20% standard rate charged at the store base location,
	 * and an empty cart.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->user_id );

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'base' );
		WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '20.0000',
				'tax_rate_name'     => 'VAT',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);

		$product = get_wallet_rechargeable_product();
		$product->set_tax_status( 'taxable' );
		$product->save();

		if ( is_null( WC()->cart ) ) {
			WC()->initialize_cart();
		}
		WC()->cart->empty_cart();
		wc_clear_notices();
	}

	/**
	 * Empty the cart so nothing leaks into sibling suites.
	 */
	public function tear_down() {
		WC()->cart->empty_cart();
		wc_clear_notices();
		parent::tear_down();
		WC_Cache_Helper::invalidate_cache_group( 'taxes' );
	}

	/**
	 * Set one general wallet setting.
	 *
	 * @param string $key   Setting key.
	 * @param string $value Value.
	 */
	private function set_general( $key, $value ) {
		update_option( '_wallet_settings_general', array_merge( (array) get_option( '_wallet_settings_general', array() ), array( $key => $value ) ) );
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
	 * Top-up order with explicit line figures.
	 *
	 * @param bool  $marked        Whether the line carries the 1.7.2 marker.
	 * @param float $subtotal      Pre-discount net.
	 * @param float $subtotal_tax  Pre-discount tax.
	 * @param float $total         Post-discount net.
	 * @param float $total_tax     Post-discount tax.
	 * @return WC_Order
	 */
	private function make_order( $marked, $subtotal, $subtotal_tax, $total, $total_tax ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );

		$item = new WC_Order_Item_Product();
		$item->set_product_id( get_wallet_rechargeable_product()->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( $subtotal );
		$item->set_total( $total );
		$item->set_taxes(
			array(
				'subtotal' => array( '1' => $subtotal_tax ),
				'total'    => array( '1' => $total_tax ),
			)
		);
		if ( $marked ) {
			$item->add_meta_data( WOO_Wallet_Helper::TOPUP_GROSS_META, 'yes', true );
		}
		$order->add_item( $item );
		$order->set_total( $total + $total_tax );
		$order->save();
		return $order;
	}

	/**
	 * Put a typed top-up amount in the cart and total it.
	 *
	 * @param float $typed Typed amount.
	 */
	private function cart_topup( $typed ) {
		WC()->cart->add_to_cart( get_wallet_rechargeable_product()->get_id(), 1, 0, array(), array( 'recharge_amount' => $typed ) );
		WC()->cart->calculate_totals();
	}

	/**
	 * A 1.7.2 top-up credits the typed, tax-inclusive amount (150), even with
	 * a store-allowed 10% coupon — the reported £112.50 case.
	 */
	public function test_marked_order_credits_typed_amount() {
		woo_wallet()->wallet->wallet_credit_purchase( $this->make_order( true, 125, 25, 112.5, 22.5 )->get_id() );

		$this->assertEqualsWithDelta( 150.0, $this->balance(), 0.001 );
	}

	/**
	 * An order from before 1.7.2 (no marker) credits exactly what it did then.
	 */
	public function test_unmarked_order_keeps_legacy_credit() {
		woo_wallet()->wallet->wallet_credit_purchase( $this->make_order( false, 125, 25, 112.5, 22.5 )->get_id() );

		$this->assertEqualsWithDelta( 112.5, $this->balance(), 0.001 );
	}

	/**
	 * Prices entered including tax: the customer pays the typed amount.
	 */
	public function test_cart_total_equals_typed_amount_prices_inclusive() {
		update_option( 'woocommerce_prices_include_tax', 'yes' );
		$this->cart_topup( 150 );

		$this->assertEqualsWithDelta( 150.0, (float) WC()->cart->get_total( 'edit' ), 0.001 );
	}

	/**
	 * Prices entered excluding tax: tax is backed out of the typed amount so
	 * the customer still pays exactly the typed amount, not 150 + 20%.
	 */
	public function test_cart_total_equals_typed_amount_prices_exclusive() {
		update_option( 'woocommerce_prices_include_tax', 'no' );
		$this->cart_topup( 150 );

		$this->assertEqualsWithDelta( 150.0, (float) WC()->cart->get_total( 'edit' ), 0.001 );
		$this->assertEqualsWithDelta( 25.0, (float) WC()->cart->get_total_tax(), 0.001 );
	}

	/**
	 * Checkout marks the top-up line (classic and Store API share the hook)
	 * and leaves other lines alone.
	 */
	public function test_checkout_marks_only_topup_line() {
		$frontend = Woo_Wallet_Frontend::instance();
		$topup    = new WC_Order_Item_Product();
		$other    = new WC_Order_Item_Product();

		$frontend->mark_topup_order_line_item( $topup, 'k1', array( 'recharge_amount' => 150 ) );
		$frontend->mark_topup_order_line_item( $other, 'k2', array() );

		$this->assertSame( 'yes', $topup->get_meta( WOO_Wallet_Helper::TOPUP_GROSS_META ) );
		$this->assertSame( '', $other->get_meta( WOO_Wallet_Helper::TOPUP_GROSS_META ) );
	}

	/**
	 * Coupon on a top-up cart is refused by default, with a clear notice.
	 */
	public function test_coupon_refused_on_topup_by_default() {
		$coupon = new WC_Coupon();
		$coupon->set_code( 'TENOFF' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$this->cart_topup( 150 );
		$applied = WC()->cart->apply_coupon( 'TENOFF' );

		$this->assertFalse( $applied );
		$this->assertSame( array(), WC()->cart->get_applied_coupons() );
		$errors = wp_list_pluck( wc_get_notices( 'error' ), 'notice' );
		$this->assertContains( 'Coupons cannot be used on a wallet top-up.', $errors );
	}

	/**
	 * With the option on, the coupon applies as before.
	 */
	public function test_coupon_allowed_when_option_on() {
		$this->set_general( 'allow_coupons_on_topup', 'on' );
		$coupon = new WC_Coupon();
		$coupon->set_code( 'TENOFF2' );
		$coupon->set_discount_type( 'percent' );
		$coupon->set_amount( 10 );
		$coupon->save();

		$this->cart_topup( 150 );

		$this->assertTrue( WC()->cart->apply_coupon( 'TENOFF2' ) );
	}

	/**
	 * REST top-up: the order charges exactly the typed amount (not typed +
	 * tax) and, once paid, credits exactly the typed amount.
	 *
	 * @dataProvider price_modes
	 *
	 * @param string $prices_include_tax WooCommerce option value.
	 */
	public function test_rest_topup_charges_and_credits_typed_amount( $prices_include_tax ) {
		update_option( 'woocommerce_prices_include_tax', $prices_include_tax );
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-topup-service.php';

		$result = WooWallet_Topup_Service::create_order( $this->user_id, 150 );
		$this->assertTrue( $result['is_valid'] );

		$order = wc_get_order( $result['order_id'] );
		$this->assertEqualsWithDelta( 150.0, (float) $order->get_total(), 0.001 );

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );
		$this->assertEqualsWithDelta( 150.0, $this->balance(), 0.001 );
	}

	/**
	 * Both WooCommerce price-entry modes.
	 *
	 * @return array
	 */
	public function price_modes() {
		return array(
			'prices include tax' => array( 'yes' ),
			'prices exclude tax' => array( 'no' ),
		);
	}
}
