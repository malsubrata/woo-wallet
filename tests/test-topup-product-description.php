<?php
/**
 * F12: the top-up product's admin-only "do not delete" text no longer sits in
 * the product description, which block cart/checkout shows to customers.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers ::woo_wallet_update_172_clear_topup_product_description
 */
class Test_Topup_Product_Description extends WP_UnitTestCase {

	/**
	 * Point the top-up option at a fresh product with the given description.
	 *
	 * @param string $content Description.
	 * @return int Product id.
	 */
	private function topup_product( $content ) {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'product',
				'post_status'  => 'private',
				'post_content' => $content,
			)
		);
		update_option( '_woo_wallet_recharge_product', $id );
		return $id;
	}

	/**
	 * The original auto-generated text is cleared.
	 */
	public function test_default_text_is_cleared() {
		$id = $this->topup_product( 'Auto generated product for wallet recharge please do not delete or update.' );
		woo_wallet_update_172_clear_topup_product_description();
		$this->assertSame( '', get_post( $id )->post_content );
	}

	/**
	 * A description the store wrote itself is kept.
	 */
	public function test_custom_description_is_kept() {
		$id = $this->topup_product( 'Add money to your wallet.' );
		woo_wallet_update_172_clear_topup_product_description();
		$this->assertSame( 'Add money to your wallet.', get_post( $id )->post_content );
	}
}
