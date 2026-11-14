<?php
/**
 * 1.7.5: the checkout "can the wallet pay this?" pre-check asks the ledger's question.
 *
 * Before, the wallet gateway (availability + process_payment) compared the order
 * total with the balance converted to the active currency and floored for display,
 * while the debit compares the total converted to base against the base SUM. A
 * customer who topped up 10 KD was told "balance is low" for a 10 KD order that
 * the debit itself would accept (10 KD = 32.70 USD -> shown as 9.999 KD).
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::can_cover
 * @covers Woo_Gateway_Wallet_payment::process_payment
 * @covers ::is_full_payment_through_wallet
 */
class Test_Checkout_Precheck extends WP_UnitTestCase {

	/**
	 * Storefront currency reported by the test FX provider.
	 *
	 * @var string
	 */
	public static $active = 'USD';

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Integration instance registered for this test.
	 *
	 * @var Woo_Wallet_Multicurrency_Integration
	 */
	private $integration;

	/**
	 * Base USD, KWD at 0.3058 per USD, real integration hooks, logged-in customer.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'USD' );
		self::$active = 'USD';

		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-precheck';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return Test_Checkout_Precheck::$active;
				}
				public function get_rate( $from, $to ) {
					$rates = array(
						'USD' => 1.0,
						'KWD' => 0.3058,
					);
					if ( $from === $to ) {
						return 1.0;
					}
					return isset( $rates[ $from ], $rates[ $to ] ) ? $rates[ $to ] / $rates[ $from ] : null;
				}
			},
			1
		);
		$this->set_integration_registered( false );
		$this->integration = new Woo_Wallet_Multicurrency_Integration();

		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->user_id );
		if ( is_null( WC()->cart ) ) {
			WC()->initialize_cart();
		}
	}

	/**
	 * Drop the provider and the integration hooks.
	 */
	public function tear_down() {
		global $wp_filter, $wp_query;
		foreach ( $wp_filter as $hook => $callbacks ) {
			foreach ( $callbacks->callbacks as $priority => $entries ) {
				foreach ( $entries as $entry ) {
					if ( is_array( $entry['function'] ) && $entry['function'][0] === $this->integration ) {
						remove_filter( $hook, $entry['function'], $priority );
					}
				}
			}
		}
		$this->set_integration_registered( true );
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-precheck' );
		$wp_query->set( 'order-pay', '' );
		parent::tear_down();
	}

	/**
	 * Flip the integration's once-per-request guard.
	 *
	 * @param bool $value New value.
	 */
	private function set_integration_registered( $value ) {
		$prop = new ReflectionProperty( 'Woo_Wallet_Multicurrency_Integration', 'hooks_registered' );
		$prop->setAccessible( true );
		$prop->setValue( null, $value );
	}

	/**
	 * Customer browsing in KWD tops up an amount in KWD.
	 *
	 * @param float $kd Amount in KWD.
	 */
	private function top_up_kd( $kd ) {
		self::$active = 'KWD';
		woo_wallet()->wallet->credit( $this->user_id, $kd, 'Top-up', array( 'currency' => 'KWD' ) );
	}

	/**
	 * Wallet-gateway order in KWD.
	 *
	 * @param float $total Total in KWD.
	 * @return WC_Order
	 */
	private function kwd_order( $total ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( 'KWD' );
		$order->set_payment_method( 'wallet' );
		$order->set_total( $total );
		$order->save();
		return $order;
	}

	/**
	 * Raw ledger SUM in base.
	 *
	 * @return float
	 */
	private function raw() {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * The reported case: top up 10 KD, the shown balance is 9.999 KD, but 10 KD is payable.
	 */
	public function test_topped_up_amount_is_payable() {
		$this->top_up_kd( 10 );

		$this->assertLessThan( 10, (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' ), 'Display balance floors below 10 KD.' );
		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->user_id, 10, 'KWD' ) );
		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->user_id, 10 ), 'No currency = active (KWD).' );
		// 10.01 KD = 32.73 USD > the 32.70 USD held.
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->user_id, 10.01, 'KWD' ) );
	}

	/**
	 * process_payment accepts the 10 KD order and the debit empties the wallet.
	 */
	public function test_gateway_pays_the_topped_up_amount() {
		$this->top_up_kd( 10 );
		$order = $this->kwd_order( 10 );

		$result = WC()->payment_gateways()->payment_gateways()['wallet']->process_payment( $order->get_id() );

		$this->assertSame( 'success', $result['result'] );
		$this->assertEqualsWithDelta( 0.0, $this->raw(), 0.0001 );
	}

	/**
	 * One minor unit more than the wallet holds is still refused, nothing debited.
	 */
	public function test_gateway_refuses_more_than_the_ledger_holds() {
		$this->top_up_kd( 10 );
		$order = $this->kwd_order( 10.01 );

		$result = WC()->payment_gateways()->payment_gateways()['wallet']->process_payment( $order->get_id() );

		$this->assertSame( 'failure', $result['result'] );
		$this->assertEqualsWithDelta( 32.70, $this->raw(), 0.0001 );
	}

	/**
	 * Gateway availability on the pay-for-order page uses the same check.
	 */
	public function test_order_pay_page_offers_the_wallet() {
		global $wp_query;
		$this->top_up_kd( 10 );
		$order = $this->kwd_order( 10 );
		$wp_query->set( 'order-pay', $order->get_id() );

		$this->assertTrue( is_full_payment_through_wallet() );

		$wp_query->set( 'order-pay', $this->kwd_order( 10.01 )->get_id() );
		$this->assertFalse( is_full_payment_through_wallet() );
	}

	/**
	 * A balance filter (e.g. Pro credit expiry) still caps what checkout may spend.
	 */
	public function test_balance_filter_still_caps_checkout() {
		woo_wallet()->wallet->credit( $this->user_id, 50, 'Seed', array( 'currency' => 'USD' ) );
		$expire = function ( $balance, $user_id, $currency ) {
			unset( $user_id );
			// 10 USD of the 50 has lapsed; answer in whatever currency was asked.
			return $balance - Woo_Wallet_Currency_Manager::instance()->convert( 10, 'USD', $currency ?: 'USD' );
		};
		add_filter( 'woo_wallet_current_balance', $expire, 999, 3 );

		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->user_id, 40, 'USD' ) );
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->user_id, 40.01, 'USD' ) );
		self::$active = 'KWD';
		// 12.233 KD = 40.003 USD -> 40.00: still within the 40 USD unexpired.
		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->user_id, 12.231, 'KWD' ) );
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->user_id, 12.24, 'KWD' ) );

		remove_filter( 'woo_wallet_current_balance', $expire, 999 );
	}

	/**
	 * Overdraft stores: as in 1.7.4, the wallet is OFFERED only when the balance
	 * really covers the total (so partial payment stays available otherwise), but a
	 * wallet payment that does reach process_payment may still overdraw.
	 */
	public function test_overdraft_store_availability_unchanged() {
		global $wp_query;
		woo_wallet()->wallet->credit( $this->user_id, 5, 'Seed', array( 'currency' => 'USD' ) );
		add_filter( 'woo_wallet_disallow_negative_transaction', '__return_false' );
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( 'USD' );
		$order->set_payment_method( 'wallet' );
		$order->set_total( 50 );
		$order->save();

		$wp_query->set( 'order-pay', $order->get_id() );
		$offered = is_full_payment_through_wallet();
		$result  = WC()->payment_gateways()->payment_gateways()['wallet']->process_payment( $order->get_id() );
		remove_filter( 'woo_wallet_disallow_negative_transaction', '__return_false' );

		$this->assertFalse( $offered );
		$this->assertSame( 'success', $result['result'] );
	}

	/**
	 * A balance filter that re-sums rows in PHP floats (9.999999999999998 for 10.00)
	 * must not refuse an order that exactly equals the balance.
	 */
	public function test_float_noise_in_balance_filter_does_not_refuse_exact_total() {
		woo_wallet()->wallet->credit( $this->user_id, 10, 'Seed', array( 'currency' => 'USD' ) );
		$noise = function ( $balance ) {
			return $balance - 1e-12;
		};
		add_filter( 'woo_wallet_current_balance', $noise, 999 );
		$covered = woo_wallet()->wallet->can_cover( $this->user_id, 10, 'USD' );
		remove_filter( 'woo_wallet_current_balance', $noise, 999 );

		$this->assertTrue( $covered );
	}

	/**
	 * Locked wallets cannot pay; overdraft stores can; zero needs nothing.
	 */
	public function test_lock_overdraft_and_zero() {
		woo_wallet()->wallet->credit( $this->user_id, 5, 'Seed', array( 'currency' => 'USD' ) );

		update_user_meta( $this->user_id, '_is_wallet_locked', true );
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->user_id, 1, 'USD' ) );
		delete_user_meta( $this->user_id, '_is_wallet_locked' );

		add_filter( 'woo_wallet_disallow_negative_transaction', '__return_false' );
		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->user_id, 500, 'USD' ) );
		remove_filter( 'woo_wallet_disallow_negative_transaction', '__return_false' );

		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->user_id, 0, 'USD' ) );
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->user_id, 5.01, 'USD' ) );
	}
}
