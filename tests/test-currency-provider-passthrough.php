<?php
/**
 * 1.7.5: switchers that hand an amount back unchanged for a currency they are not
 * set up for (Aelia, WCML) must not price it 1:1 — an IDR top-up would otherwise
 * be credited as the same number of dollars.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Currency_Provider_Aelia::convert
 * @covers Woo_Wallet_Currency_Manager::can_convert
 */
class Test_Currency_Provider_Passthrough extends WP_UnitTestCase {

	/**
	 * Base USD; Aelia enabled for USD + EUR, its convert filter echoing unknown codes.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'USD' );
		add_filter( 'wc_aelia_cs_enabled_currencies', array( $this, 'enabled' ) );
		add_filter( 'wc_aelia_cs_convert', array( $this, 'aelia_convert' ), 10, 3 );
	}

	/**
	 * Remove the fake Aelia filters.
	 */
	public function tear_down() {
		remove_filter( 'wc_aelia_cs_enabled_currencies', array( $this, 'enabled' ) );
		remove_filter( 'wc_aelia_cs_convert', array( $this, 'aelia_convert' ), 10 );
		parent::tear_down();
	}

	/**
	 * Aelia's enabled currencies.
	 *
	 * @return array
	 */
	public function enabled() {
		return array( 'USD', 'EUR' );
	}

	/**
	 * Aelia: converts EUR<->USD at 0.9, echoes anything else unchanged.
	 *
	 * @param float  $amount Amount.
	 * @param string $from   From.
	 * @param string $to     To.
	 * @return float
	 */
	public function aelia_convert( $amount, $from, $to ) {
		if ( 'EUR' === $from && 'USD' === $to ) {
			return $amount / 0.9;
		}
		if ( 'USD' === $from && 'EUR' === $to ) {
			return $amount * 0.9;
		}
		return $amount;
	}

	/**
	 * An unconfigured currency has no rate; configured ones still convert.
	 */
	public function test_aelia_unconfigured_currency_is_not_priced_one_to_one() {
		$provider = new Woo_Wallet_Currency_Provider_Aelia();
		$this->assertNull( $provider->get_rate( 'IDR', 'USD' ) );
		$this->assertNull( $provider->convert( 100, 'IDR', 'USD' ) );
		$this->assertEqualsWithDelta( 100.0, $provider->convert( 90, 'EUR', 'USD' ), 0.0001 );
	}

	/**
	 * Through the wallet: an IDR credit on that store is refused, the ledger untouched.
	 */
	public function test_wallet_refuses_credit_in_unconfigured_currency() {
		$manager = Woo_Wallet_Currency_Manager::instance();
		// A fresh id: (un)registering resets the manager's cached provider choice.
		$manager->register_provider(
			new class() extends Woo_Wallet_Currency_Provider_Aelia {
				public function get_id() {
					return 'aelia-test';
				}
			},
			1
		);
		$force = function () {
			return 'aelia-test';
		};
		add_filter( 'woo_wallet_active_currency_provider', $force );
		$user = self::factory()->user->create();

		$this->assertFalse( $manager->can_convert( 'IDR', 'USD' ) );
		$this->assertFalse( woo_wallet()->wallet->credit( $user, 100, 'x', array( 'currency' => 'IDR' ) ) );
		$this->assertNotFalse( woo_wallet()->wallet->credit( $user, 90, 'x', array( 'currency' => 'EUR' ) ) );

		remove_filter( 'woo_wallet_active_currency_provider', $force );
		$manager->unregister_provider( 'aelia-test' );

		global $wpdb;
		$raw = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $user ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertEqualsWithDelta( 100.0, $raw, 0.0001 );
	}
}
