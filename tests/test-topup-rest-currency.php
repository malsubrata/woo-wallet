<?php
/**
 * M5 (1.7.4): REST top-up with an explicit currency.
 *
 * The typed amount is charged in the requested currency, the ledger gets its
 * base value, and a currency without a known rate is refused.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WooWallet_Topup_Service::create_order
 */
class Test_Topup_Rest_Currency extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Storefront currency reported by the test FX provider.
	 *
	 * @var string
	 */
	public static $active = 'GBP';

	/**
	 * Base GBP; a test FX provider that knows USD (1.25) only.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		self::$active  = 'GBP';
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		include_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-topup-service.php';

		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-m5';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return Test_Topup_Rest_Currency::$active;
				}
				public function get_rate( $from, $to ) {
					if ( $from === $to ) {
						return 1.0;
					}
					$rates = array(
						'GBP' => 1.0,
						'USD' => 1.25,
					);
					return isset( $rates[ $from ], $rates[ $to ] ) ? $rates[ $to ] / $rates[ $from ] : null;
				}
			},
			1
		);
		// A switcher reports the shopper's currency to WooCommerce, also on REST.
		add_filter( 'woocommerce_currency', array( __CLASS__, 'active_currency' ) );
		// What the multi-currency integration registers on a store with a switcher.
		add_filter( 'woo_wallet_rechargeable_amount', array( $this, 'active_to_base' ) );
		add_filter( 'woo_wallet_get_option__wallet_settings_general_min_topup_amount', array( $this, 'base_to_active' ) );
		add_filter( 'woo_wallet_get_option__wallet_settings_general_max_topup_amount', array( $this, 'base_to_active' ) );
	}

	/**
	 * Remove fakes and filters.
	 */
	public function tear_down() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-m5' );
		remove_filter( 'woocommerce_currency', array( __CLASS__, 'active_currency' ) );
		remove_filter( 'woo_wallet_rechargeable_amount', array( $this, 'active_to_base' ) );
		remove_filter( 'woo_wallet_get_option__wallet_settings_general_min_topup_amount', array( $this, 'base_to_active' ) );
		remove_filter( 'woo_wallet_get_option__wallet_settings_general_max_topup_amount', array( $this, 'base_to_active' ) );
		delete_option( '_wallet_settings_general' );
		parent::tear_down();
	}

	/**
	 * Currency a switcher hands to WooCommerce.
	 *
	 * @return string
	 */
	public static function active_currency() {
		return self::$active;
	}

	/**
	 * Same as Woo_Wallet_Multicurrency_Integration::filter_woo_wallet_rechargeable_amount().
	 *
	 * @param float $amount Amount in active currency.
	 * @return float
	 */
	public function active_to_base( $amount ) {
		return Woo_Wallet_Currency_Manager::instance()->convert( $amount, self::$active, 'GBP' );
	}

	/**
	 * Same idea as Woo_Wallet_Multicurrency_Integration::filter_settings_option().
	 *
	 * @param mixed $value Setting in base currency.
	 * @return float
	 */
	public function base_to_active( $value ) {
		return Woo_Wallet_Currency_Manager::instance()->convert( (float) $value, 'GBP', self::$active );
	}

	/**
	 * Save top-up limits.
	 *
	 * @param float $min Minimum in base.
	 * @param float $max Maximum in base.
	 */
	private function set_limits( $min, $max ) {
		$settings                     = (array) get_option( '_wallet_settings_general', array() );
		$settings['min_topup_amount'] = $min;
		$settings['max_topup_amount'] = $max;
		update_option( '_wallet_settings_general', $settings );
	}

	/**
	 * Pay an order and return the wallet ledger change in base.
	 *
	 * @param array $result create_order() result.
	 * @return float
	 */
	private function credit( array $result ) {
		global $wpdb;
		woo_wallet()->wallet->wallet_credit_purchase( $result['order_id'] );
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(amount),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0 AND type='credit'", $this->user_id ) );
	}

	/**
	 * Orders the customer has.
	 *
	 * @return int
	 */
	private function order_count() {
		return count(
			wc_get_orders(
				array(
					'customer_id' => $this->user_id,
					'limit'       => -1,
					'return'      => 'ids',
				)
			)
		);
	}

	/**
	 * Shopper browsing in USD asks for USD: pays the typed figure, base value credited.
	 */
	public function test_requested_currency_is_charged_as_typed() {
		self::$active = 'USD';
		$result       = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'USD' );

		$this->assertTrue( $result['is_valid'] );
		$order = wc_get_order( $result['order_id'] );
		$this->assertSame( 'USD', $order->get_currency() );
		$this->assertEqualsWithDelta( 100.0, (float) $order->get_total(), 0.001 );
		$this->assertEqualsWithDelta( 100.0, $result['amount'], 0.001 );
		$this->assertEqualsWithDelta( 80.0, $this->credit( $result ), 0.001 );
	}

	/**
	 * Shopper browsing in USD asks for base: typed figure is already base.
	 */
	public function test_base_currency_requested_while_browsing_another() {
		self::$active = 'USD';
		$result       = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'GBP' );

		$order = wc_get_order( $result['order_id'] );
		$this->assertSame( 'GBP', $order->get_currency() );
		$this->assertEqualsWithDelta( 100.0, (float) $order->get_total(), 0.001 );
		$this->assertEqualsWithDelta( 100.0, $this->credit( $result ), 0.001 );
	}

	/**
	 * Currency with no known rate: refused, no order.
	 */
	public function test_currency_without_rate_is_refused() {
		$result = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'INR' );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'rest_invalid_currency', $result['code'] );
		$this->assertSame( 400, $result['status'] );
		$this->assertSame( 0, $this->order_count() );
	}

	/**
	 * Store with no currency switcher: only base works.
	 */
	public function test_store_without_switcher_accepts_only_base() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-m5' );

		$refused = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'USD' );
		$this->assertFalse( $refused['is_valid'] );
		$this->assertSame( 0, $this->order_count() );

		$allowed = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'GBP' );
		$this->assertTrue( $allowed['is_valid'] );
	}

	/**
	 * Well-formed code WooCommerce does not know: refused, no order, no exception.
	 */
	public function test_unknown_currency_code_is_refused() {
		$result = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'XYZ' );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'rest_invalid_currency', $result['code'] );
		$this->assertSame( 0, $this->order_count() );
	}

	/**
	 * Limits are compared by value, in whichever currency was requested.
	 */
	public function test_limits_compare_the_requested_currency() {
		$this->set_limits( 100, 200 );

		// $100 = £80, under the £100 minimum.
		$low = WooWallet_Topup_Service::create_order( $this->user_id, 100, '', 'USD' );
		$this->assertSame( 'rest_amount_below_minimum', $low['code'] );

		// $260 = £208, over the £200 maximum.
		$high = WooWallet_Topup_Service::create_order( $this->user_id, 260, '', 'USD' );
		$this->assertSame( 'rest_amount_above_maximum', $high['code'] );

		// $125 = exactly the £100 minimum.
		$edge = WooWallet_Topup_Service::create_order( $this->user_id, 125, '', 'USD' );
		$this->assertTrue( $edge['is_valid'] );

		// Browsing in USD, asking for GBP: £110 passes, £90 is under the £100 minimum.
		self::$active = 'USD';
		$browsing     = WooWallet_Topup_Service::create_order( $this->user_id, 110, '', 'GBP' );
		$this->assertTrue( $browsing['is_valid'] );
		$under = WooWallet_Topup_Service::create_order( $this->user_id, 90, '', 'GBP' );
		$this->assertSame( 'rest_amount_below_minimum', $under['code'] );
	}

	/**
	 * No currency sent: amount is in the active currency, order in base (unchanged).
	 */
	public function test_no_currency_keeps_converting_active_to_base() {
		self::$active = 'USD';
		$result       = WooWallet_Topup_Service::create_order( $this->user_id, 100 );

		$order = wc_get_order( $result['order_id'] );
		$this->assertSame( 'GBP', $order->get_currency() );
		$this->assertEqualsWithDelta( 80.0, (float) $order->get_total(), 0.001 );
		$this->assertEqualsWithDelta( 80.0, $this->credit( $result ), 0.001 );
	}
}
