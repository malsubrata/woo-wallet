<?php
/**
 * Store API (Blocks checkout) partial-payment fee tax regression tests.
 *
 * The Blocks checkout calls `$order->calculate_totals()` on the *unsaved* order
 * (see WooCommerce OrderController::update_order_from_cart). WooCommerce's
 * negative-fee branch in WC_Order_Item_Fee::calculate_taxes() ignores the fee's
 * own tax class and apportions tax across the order's tax classes, so a wallet
 * fee of -94.24 picks up -16.96 of tax. Since the fee amount is already capped
 * to the wallet balance, the resulting debit (111.20) can never be satisfied.
 *
 * `_legacy_fee_key` order-item meta does not exist yet at that point — it is
 * written on save — so the tax-stripping guard must fall back to the public
 * `legacy_fee_key` property that WC_Checkout::create_order_fee_lines() sets.
 *
 * @package WooWallet\Tests
 */

/**
 * Regression cover for the wallet fee tax stripped on Store API order creation.
 *
 * @covers Woo_Wallet::woocommerce_order_item_fee_after_calculate_taxes_callback
 * @covers ::get_order_partial_payment_amount
 */
class Test_Partial_Payment_Blocks_Fee_Tax extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Inserted tax rate id.
	 *
	 * @var int
	 */
	private $rate_id;

	/**
	 * Fresh customer + an 18% store-wide tax rate per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		$this->rate_id = WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate_state'    => '',
				'tax_rate'          => '18.0000',
				'tax_rate_name'     => 'GST',
				'tax_rate_priority' => 1,
				'tax_rate_compound' => 0,
				'tax_rate_shipping' => 1,
				'tax_rate_order'    => 0,
				'tax_rate_class'    => '',
			)
		);
	}

	/**
	 * Remove the tax rate again so it cannot leak into sibling suites.
	 */
	public function tear_down() {
		WC_Tax::_delete_tax_rate( $this->rate_id );
		update_option( 'woocommerce_calc_taxes', 'no' );
		parent::tear_down();
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
	 * Build an order the way the Store API does: taxable line item plus an
	 * unsaved wallet fee carrying `legacy_fee_key` as a property (not meta),
	 * then recalculate totals *with* taxes.
	 *
	 * @param float $wallet_amount amount the wallet should cover.
	 * @return WC_Order
	 */
	private function make_store_api_order( $wallet_amount ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Hoodie' );
		$product->set_regular_price( 135 );
		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( '' );
		$product->save();

		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );

		$line = new WC_Order_Item_Product();
		$line->set_product( $product );
		$line->set_quantity( 1 );
		$line->set_subtotal( 135 );
		$line->set_total( 135 );
		$order->add_item( $line );

		// Mirrors WC_Checkout::create_order_fee_lines() for a non-taxable cart fee.
		$fee                 = new WC_Order_Item_Fee();
		$fee->legacy_fee_key = '_via_wallet_partial_payment';

		$fee->set_name( 'Via wallet' );
		$fee->set_tax_class( 0 );
		$fee->set_amount( -1 * $wallet_amount );
		$fee->set_total( -1 * $wallet_amount );
		$fee->set_total_tax( 0 );
		$fee->set_taxes( array( 'total' => array() ) );
		$order->add_item( $fee );

		$order->calculate_totals();
		$order->save();

		return $order;
	}

	/**
	 * `payment` mode: the recalculation must not add tax to the wallet fee, so
	 * the debit amount equals exactly what the customer opted to spend.
	 */
	public function test_store_api_recalculation_keeps_wallet_fee_tax_free() {
		$this->set_setting( 'partial_payment_tax_treatment', 'payment' );

		$order = $this->make_store_api_order( 94.24 );

		$fees = $order->get_items( 'fee' );
		$fee  = reset( $fees );

		$this->assertEquals( 0.0, (float) $fee->get_total_tax( 'edit' ), 'Wallet fee must carry no tax in payment mode.' );
		$this->assertEqualsWithDelta( 94.24, (float) get_order_partial_payment_amount( $order->get_id() ), 0.01 );
	}

	/**
	 * The debit must stay within the wallet balance it was capped to — the
	 * original failure was a debit of 111.20 against a balance of 94.24.
	 */
	public function test_debit_succeeds_for_a_balance_sized_partial_payment() {
		$this->set_setting( 'partial_payment_tax_treatment', 'payment' );
		$this->set_setting( 'partial_payment_debit_on', 'order_created' );
		woo_wallet()->wallet->credit( $this->user_id, 94.24, 'seed' );

		$order = $this->make_store_api_order( 94.24 );
		woo_wallet()->wallet->woocommerce_order_processed( $order );

		$fresh = wc_get_order( $order->get_id() );
		$this->assertNotEmpty( $fresh->get_meta( '_partial_pay_through_wallet_compleate' ), 'Debit should have completed.' );
		$this->assertEqualsWithDelta( 0.0, (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' ), 0.01 );
	}

	/**
	 * `tax_inclusive_wallet` mode intentionally keeps the negative fee tax so the
	 * wallet covers the gross — the fallback must not strip it there.
	 */
	public function test_tax_inclusive_mode_keeps_the_fee_tax() {
		$this->set_setting( 'partial_payment_tax_treatment', 'tax_inclusive_wallet' );

		$order = $this->make_store_api_order( 80 );

		$fees = $order->get_items( 'fee' );
		$fee  = reset( $fees );

		$this->assertLessThan( 0.0, (float) $fee->get_total_tax( 'edit' ), 'Tax-inclusive mode keeps the negative fee tax.' );
	}
}
