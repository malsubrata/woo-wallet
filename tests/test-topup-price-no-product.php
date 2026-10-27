<?php
/**
 * Top-up price hook when the top-up product cannot be loaded (1.7.4 C1).
 *
 * `woocommerce_before_calculate_totals` must not fatal when
 * get_wallet_rechargeable_product() returns false while the cart still holds
 * items — including a line that carries `recharge_amount`.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Frontend::woo_wallet_set_recharge_product_price
 */
class Test_Topup_Price_No_Product extends WP_UnitTestCase {

	/**
	 * Make the top-up product lookup return false.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'woo_wallet_rechargeable_product_id', '__return_zero' );
	}

	/**
	 * Remove the lookup override.
	 */
	public function tear_down() {
		remove_filter( 'woo_wallet_rechargeable_product_id', '__return_zero' );
		parent::tear_down();
	}

	/**
	 * A cart-like object holding the given lines.
	 *
	 * @param array $lines Cart contents.
	 * @return object
	 */
	private function cart( array $lines ) {
		return (object) array( 'cart_contents' => $lines );
	}

	/**
	 * No top-up product, non-empty cart with a `recharge_amount` line → no
	 * fatal and the line price is left alone. Fails on 1.7.3 (get_id() on false).
	 */
	public function test_no_product_with_recharge_line_does_not_fatal() {
		$this->assertFalse( get_wallet_rechargeable_product() );

		$item = new WC_Product_Simple();
		$item->set_price( 7 );
		$item->save();
		$cart = $this->cart(
			array(
				'k1' => array(
					'product_id'      => $item->get_id(),
					'recharge_amount' => 50,
					'data'            => $item,
				),
			)
		);

		Woo_Wallet_Frontend::instance()->woo_wallet_set_recharge_product_price( $cart );

		$this->assertEquals( 7, $item->get_price() );
	}

	/**
	 * No top-up product, a normal item in the cart → no error, price unchanged.
	 */
	public function test_no_product_with_normal_item() {
		$item = new WC_Product_Simple();
		$item->set_price( 7 );
		$item->save();
		$cart = $this->cart(
			array(
				'k1' => array(
					'product_id' => $item->get_id(),
					'data'       => $item,
				),
			)
		);

		Woo_Wallet_Frontend::instance()->woo_wallet_set_recharge_product_price( $cart );

		$this->assertEquals( 7, $item->get_price() );
	}
}
