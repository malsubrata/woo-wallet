<?php
/**
 * Locked-wallet partial payment regression tests.
 *
 * A locked wallet must never produce a "Via wallet" checkout discount. The
 * ledger already refuses debit while locked; if the cart fee still applies,
 * the customer is undercharged without funds being withdrawn.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers ::is_enable_wallet_partial_payment
 * @covers Woo_Wallet_Wallet::debit_partial_payment_for_order
 */
class Test_Partial_Payment_Locked_Wallet extends WP_UnitTestCase {

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
		wp_set_current_user( $this->user_id );
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
	 * Wallet balance as float.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * Simulate a cart whose total exceeds the wallet balance (partial-payment case).
	 *
	 * @param float $total cart total.
	 */
	private function stub_cart_total( $total ) {
		add_filter(
			'woowallet_cart_total',
			static function () use ( $total ) {
				return $total;
			}
		);
	}

	/**
	 * Auto-deduct + locked wallet must not enable partial payment (bug report).
	 */
	public function test_auto_deduct_disabled_when_wallet_locked() {
		woo_wallet()->wallet->credit( $this->user_id, 700, 'seed' );
		update_user_meta( $this->user_id, '_is_wallet_locked', true );

		$this->set_setting( 'is_enable_partial_payment', 'on' );
		$this->set_setting( 'is_auto_deduct_for_partial_payment', 'on' );
		$this->stub_cart_total( 900 );

		$this->assertNotEmpty( is_wallet_account_locked( $this->user_id ) );
		$this->assertFalse( is_enable_wallet_partial_payment() );
	}

	/**
	 * Same setup with an unlocked wallet still enables auto-deduct partial payment.
	 */
	public function test_auto_deduct_enabled_when_wallet_unlocked() {
		woo_wallet()->wallet->credit( $this->user_id, 700, 'seed' );

		$this->set_setting( 'is_enable_partial_payment', 'on' );
		$this->set_setting( 'is_auto_deduct_for_partial_payment', 'on' );
		$this->stub_cart_total( 900 );

		$this->assertEmpty( is_wallet_account_locked( $this->user_id ) );
		$this->assertTrue( is_enable_wallet_partial_payment() );
	}

	/**
	 * Filters must not re-enable partial payment for a locked wallet.
	 */
	public function test_lock_overrides_enable_filter() {
		woo_wallet()->wallet->credit( $this->user_id, 700, 'seed' );
		update_user_meta( $this->user_id, '_is_wallet_locked', true );

		$this->set_setting( 'is_auto_deduct_for_partial_payment', 'on' );
		$this->stub_cart_total( 900 );

		add_filter( 'is_enable_wallet_partial_payment', '__return_true' );

		$this->assertFalse( is_enable_wallet_partial_payment() );
	}

	/**
	 * If a partial-payment fee somehow reaches order processing while locked,
	 * the debit fails, balance is unchanged, and the order is held.
	 */
	public function test_partial_debit_fails_cleanly_when_locked() {
		woo_wallet()->wallet->credit( $this->user_id, 700, 'seed' );
		update_user_meta( $this->user_id, '_is_wallet_locked', true );

		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( 'Via wallet' );
		$fee->set_total( -700 );
		$fee->set_total_tax( 0 );
		$order->add_item( $fee );
		$order->save();

		woo_wallet()->wallet->woocommerce_order_processed( $order );

		$this->assertEquals( 700.0, $this->balance() );
		$fresh = wc_get_order( $order->get_id() );
		$this->assertEmpty( $fresh->get_meta( '_partial_pay_through_wallet_compleate' ) );
		$this->assertEquals( 'on-hold', $fresh->get_status() );
	}
}
