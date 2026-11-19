<?php
/**
 * 1.7.5: refunds of a wallet-paid order reverse the checkout debit, not today's rate.
 *
 * 1.7.4 credited the order-currency refund converted at the CURRENT rate, while the
 * debit stored base at the checkout rate: 100 EUR paid at 1.08 (108 USD) and refunded
 * at 1.15 came back as 115 USD. Asserts the raw ledger SUM.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::refund_order_to_wallet
 * @covers Woo_Gateway_Wallet_payment::process_refund
 */
class Test_Wallet_Refund_Fx extends WP_UnitTestCase {

	/**
	 * USD per 1 EUR.
	 *
	 * @var float
	 */
	public static $usd_per_eur = 1.08;

	/**
	 * Active currency.
	 *
	 * @var string
	 */
	public static $active = 'EUR';

	/**
	 * Customer.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Base USD, EUR storefront, 200 USD balance.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'USD' );
		self::$usd_per_eur = 1.08;
		self::$active      = 'EUR';
		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-refund';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return Test_Wallet_Refund_Fx::$active;
				}
				public function get_rate( $from, $to ) {
					$usd = array( 'USD' => 1.0, 'EUR' => Test_Wallet_Refund_Fx::$usd_per_eur, 'INR' => 1 / 83 );
					return isset( $usd[ $from ], $usd[ $to ] ) ? $usd[ $from ] / $usd[ $to ] : null;
				}
			},
			1
		);
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed', array( 'currency' => 'USD' ) );
	}

	/**
	 * Remove the provider.
	 */
	public function tear_down() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-refund' );
		parent::tear_down();
	}

	/**
	 * Raw ledger SUM (USD).
	 *
	 * @return float
	 */
	private function raw() {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * A 100 EUR order paid in full by wallet at the current rate.
	 *
	 * @param string $method Payment method.
	 * @return WC_Order
	 */
	private function paid_order( $method = 'wallet' ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( 'EUR' );
		$order->set_payment_method( $method );
		$order->set_total( 100 );
		$order->save();
		if ( 'wallet' === $method ) {
			$id = woo_wallet()->wallet->debit( $this->user_id, 100, 'pay', array( 'currency' => 'EUR' ) );
			$order->set_transaction_id( $id );
		}
		$order->set_status( 'processing' );
		$order->save();
		return wc_get_order( $order->get_id() );
	}

	/**
	 * Refund through WooCommerce, through the wallet gateway.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Order-currency amount.
	 */
	private function refund( $order, $amount ) {
		$refund = wc_create_refund(
			array(
				'order_id'       => $order->get_id(),
				'amount'         => $amount,
				'refund_payment' => true,
			)
		);
		$this->assertNotWPError( $refund );
	}

	/**
	 * Full refund after the rate moved either way returns exactly what was debited.
	 */
	public function test_full_refund_returns_the_checkout_debit_whatever_the_rate() {
		foreach ( array( 1.15, 1.00 ) as $later ) { // Stronger EUR (overpay direction), weaker (underpay).
			self::$usd_per_eur = 1.08;
			$start             = $this->raw();
			$order             = $this->paid_order();
			$this->assertEqualsWithDelta( $start - 108.0, $this->raw(), 0.0001 );

			self::$usd_per_eur = $later;
			$this->refund( $order, 100 );
			$this->assertEqualsWithDelta( $start, $this->raw(), 0.0001, "rate $later" );
		}
	}

	/**
	 * Partial refunds at drifting rates never add up to more (or less) than the debit.
	 */
	public function test_partial_refunds_sum_to_the_debit() {
		$order = $this->paid_order(); // 108.00 USD.
		self::$usd_per_eur = 1.15;
		$this->refund( $order, 33.33 ); // 35.99 USD (floored share).
		$this->assertEqualsWithDelta( 92.0 + 35.99, $this->raw(), 0.0001 );
		self::$usd_per_eur = 0.95;
		$this->refund( wc_get_order( $order->get_id() ), 66.67 ); // Final: the exact remainder.
		$this->assertEqualsWithDelta( 200.0, $this->raw(), 0.0001 );
	}

	/**
	 * The refund is the same whatever currency the admin's session is in.
	 */
	public function test_refund_ignores_the_session_currency() {
		$order        = $this->paid_order();
		self::$active = 'INR';
		$this->refund( $order, 100 );
		$this->assertEqualsWithDelta( 200.0, $this->raw(), 0.0001 );
	}

	/**
	 * An order partly refunded before 1.7.5 (no base counter) gets the pro-rata remainder.
	 */
	public function test_order_refunded_before_175_gets_the_remainder() {
		$order = $this->paid_order(); // 108.00 USD.
		wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => 40, 'refund_payment' => false ) );
		$this->refund( wc_get_order( $order->get_id() ), 60 );
		$this->assertEqualsWithDelta( 92.0 + 64.80, $this->raw(), 0.0001 ); // 108 - floor(108 * 0.4).
	}

	/**
	 * A card-paid order refunded to the wallet still credits the order currency at today's rate.
	 */
	public function test_non_wallet_order_refund_uses_todays_rate() {
		$order             = $this->paid_order( 'cod' );
		self::$usd_per_eur = 1.15;
		woo_wallet()->wallet->refund_order_to_wallet( $order, 100, 'to wallet' );
		$this->assertEqualsWithDelta( 315.0, $this->raw(), 0.0001 );
	}
}
