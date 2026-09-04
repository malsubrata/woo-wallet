<?php
/**
 * `get_woowallet_cart_total()` regression tests.
 *
 * The helper answers one question for all four of its callers: what would the
 * customer owe if the wallet contributed nothing. It excluded the wallet fee's
 * base (`get_woo_wallet_cart_fee_total()`) but not the fee's own negative tax,
 * because `WC_Cart::get_taxes_total()` merges `get_fee_taxes()`. In
 * `tax_inclusive_wallet` mode that left the helper short by exactly the fee tax
 * once totals had been calculated, which
 *
 *   - under-reported the "paid through other payment method" figure at checkout, and
 *   - made `is_full_payment_through_wallet()` true for any cart between 100% and
 *     ~116.7% of the wallet balance at 20% VAT, offering the wallet gateway as a
 *     full payment method on an order the balance cannot cover.
 *
 * @package WooWallet\Tests
 */

/**
 * Cover the pre-wallet gross the helper is contracted to return.
 *
 * @covers ::get_woowallet_cart_total
 * @covers ::is_full_payment_through_wallet
 */
class Test_Partial_Payment_Cart_Total extends WP_UnitTestCase {

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
	 * Tax-inclusive store on a single 20% rate, mirroring the reported UK VAT setup.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->user_id );

		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'yes' );
		update_option( 'woocommerce_tax_based_on', 'base' );
		$this->rate_id = WC_Tax::_insert_tax_rate(
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

		$this->set_setting( 'is_enable_partial_payment', 'on' );
		$this->set_setting( 'is_auto_deduct_for_partial_payment', 'on' );
		$this->set_setting( 'partial_payment_tax_treatment', 'tax_inclusive_wallet' );

		if ( is_null( WC()->session ) ) {
			WC()->initialize_session();
		}
		if ( is_null( WC()->cart ) ) {
			WC()->initialize_cart();
		}
		WC()->customer->set_billing_country( 'GB' );
		WC()->cart->empty_cart();
	}

	/**
	 * Drop the rate and cart so nothing leaks into sibling suites.
	 */
	public function tear_down() {
		WC()->cart->empty_cart();
		WC_Tax::_delete_tax_rate( $this->rate_id );
		update_option( 'woocommerce_calc_taxes', 'no' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		delete_option( '_wallet_settings_general' );
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
	 * Put a single tax-inclusive product in the cart and fund the wallet.
	 *
	 * £49.98 inc 20% VAT = £41.65 ex + £8.33 VAT.
	 *
	 * @param float $balance wallet balance to credit.
	 */
	private function seed_cart( $balance ) {
		$product = new WC_Product_Simple();
		$product->set_name( 'Hoodie' );
		$product->set_regular_price( 49.98 );
		$product->set_tax_status( 'taxable' );
		$product->set_tax_class( '' );
		$product->save();

		if ( $balance > 0 ) {
			woo_wallet()->wallet->credit( $this->user_id, $balance, 'Test float' );
		}

		WC()->cart->add_to_cart( $product->get_id(), 1 );
		WC()->cart->calculate_totals();
	}

	/**
	 * The wallet fee's negative tax must not depress the pre-wallet gross.
	 *
	 * Balance £6.00 grosses down to a −£5.00 fee carrying −£1.00 of tax. Before
	 * the fix the helper returned £48.98 and the checkout notice promised the
	 * gateway would take £42.98 while it actually charged £43.98.
	 */
	public function test_cart_total_is_gross_before_wallet() {
		$this->seed_cart( 6.00 );

		$fees = WC()->cart->get_fees();
		$this->assertArrayHasKey(
			'_via_wallet_partial_payment',
			$fees,
			'The wallet fee should be on the cart for this scenario to be meaningful.'
		);
		$this->assertEqualsWithDelta(
			-1.00,
			(float) $fees['_via_wallet_partial_payment']->tax,
			0.01,
			'The taxable wallet fee should carry its own negative tax in tax_inclusive_wallet mode.'
		);

		$this->assertEqualsWithDelta(
			49.98,
			(float) get_woowallet_cart_total(),
			0.01,
			'The helper must report the gross payable with no wallet contribution.'
		);

		// The figure the notice promises and the figure the gateway charges.
		$this->assertEqualsWithDelta(
			43.98,
			(float) WC()->cart->get_total( 'edit' ),
			0.01,
			'Order total should be the gross less the full wallet gross.'
		);
		$this->assertEqualsWithDelta(
			(float) WC()->cart->get_total( 'edit' ),
			(float) get_woowallet_cart_total() - 6.00,
			0.01,
			'Helper minus the wallet amount must equal what the other gateway charges.'
		);
	}

	/**
	 * The full-wallet gateway must not be offered for an unaffordable order.
	 *
	 * Balance £46.00 against a £49.98 cart sits inside the band the fee tax used
	 * to open: the helper returned £42.31, so `46.00 >= 42.31` wrongly reported
	 * the wallet could cover the whole order.
	 */
	public function test_full_payment_gate_is_not_tricked_by_fee_tax() {
		$this->seed_cart( 46.00 );

		$this->assertFalse(
			is_full_payment_through_wallet(),
			'A £46.00 balance cannot pay a £49.98 order in full.'
		);
		$this->assertEqualsWithDelta(
			49.98,
			(float) get_woowallet_cart_total(),
			0.01,
			'Pre-wallet gross is unchanged by how much balance the customer holds.'
		);
	}

	/**
	 * The helper must answer identically whichever side of fee costing it runs on.
	 *
	 * `is_enable_wallet_partial_payment()` calls it from inside
	 * `woocommerce_cart_calculate_fees`, where fee taxes are still zeroed by
	 * `WC_Cart::reset_totals()`; every other caller runs after
	 * `calculate_totals()`, where they are not. A reconstruction that subtracts
	 * `$fee->tax` back out would disagree between the two.
	 */
	public function test_cart_total_is_stable_across_the_fee_lifecycle() {
		$captured = null;
		add_action(
			'woocommerce_cart_calculate_fees',
			function () use ( &$captured ) {
				$captured = (float) get_woowallet_cart_total();
			},
			999
		);

		$this->seed_cart( 6.00 );

		$this->assertNotNull( $captured, 'The fees hook should have run.' );
		$this->assertEqualsWithDelta(
			49.98,
			$captured,
			0.01,
			'Inside the fees hook the helper must already report the true gross.'
		);
		$this->assertEqualsWithDelta(
			$captured,
			(float) get_woowallet_cart_total(),
			0.01,
			'The helper must not change its answer once fee taxes are costed.'
		);
	}

	/**
	 * `payment` mode strips the fee tax, so the helper is unaffected there.
	 */
	public function test_payment_mode_total_is_unchanged() {
		$this->set_setting( 'partial_payment_tax_treatment', 'payment' );
		$this->seed_cart( 6.00 );

		$fees = WC()->cart->get_fees();
		$this->assertEqualsWithDelta(
			0.0,
			isset( $fees['_via_wallet_partial_payment']->tax ) ? (float) $fees['_via_wallet_partial_payment']->tax : 0.0,
			0.01,
			'payment mode must keep the wallet fee tax-free.'
		);
		$this->assertEqualsWithDelta(
			49.98,
			(float) get_woowallet_cart_total(),
			0.01,
			'payment mode reports the same pre-wallet gross.'
		);
	}

	/**
	 * A third-party taxable fee must still be counted, base and tax.
	 *
	 * The fix excludes only the wallet fee; excluding every fee tax would quietly
	 * under-report carts that carry a handling or gateway fee from another plugin.
	 */
	public function test_non_wallet_fee_tax_is_still_included() {
		add_action(
			'woocommerce_cart_calculate_fees',
			function () {
				WC()->cart->fees_api()->add_fee(
					array(
						'id'      => 'handling',
						'name'    => 'Handling',
						'amount'  => 10.00,
						'taxable' => true,
					)
				);
			},
			5
		);

		$this->seed_cart( 6.00 );

		// £49.98 gross + £10.00 ex-VAT handling + £2.00 its VAT = £61.98.
		$this->assertEqualsWithDelta(
			61.98,
			(float) get_woowallet_cart_total(),
			0.01,
			'A non-wallet taxable fee contributes both its base and its tax.'
		);
	}
}
