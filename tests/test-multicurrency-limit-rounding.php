<?php
/**
 * Converted min/max wallet limits round in the safe direction.
 *
 * `filter_settings_option()` rounds converted limits to price decimals so the
 * number inputs accept valid amounts. Maximums must round down (a displayed cap
 * never exceeds the configured one), minimums up, float noise must not push an
 * exact value over a unit, and a non-zero limit must never become 0 ("no limit").
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Multicurrency_Integration::filter_settings_option
 */
class Test_Multicurrency_Limit_Rounding extends WP_UnitTestCase {

	const MAX_HOOK = 'woo_wallet_get_option__wallet_settings_general_max_topup_amount';
	const MIN_HOOK = 'woo_wallet_get_option__wallet_settings_general_min_topup_amount';

	/**
	 * Integration instance (built without the constructor's global side effects).
	 *
	 * @var Woo_Wallet_Multicurrency_Integration
	 */
	private $integration;

	/**
	 * Register the filter on the real hook names. Base == active currency, so
	 * convert() is the identity and only the rounding is under test.
	 */
	public function set_up() {
		parent::set_up();
		$this->integration = ( new ReflectionClass( 'Woo_Wallet_Multicurrency_Integration' ) )->newInstanceWithoutConstructor();
		add_filter( self::MAX_HOOK, array( $this->integration, 'filter_settings_option' ) );
		add_filter( self::MIN_HOOK, array( $this->integration, 'filter_settings_option' ) );
	}

	/**
	 * Drop the test registrations.
	 */
	public function tear_down() {
		remove_filter( self::MAX_HOOK, array( $this->integration, 'filter_settings_option' ) );
		remove_filter( self::MIN_HOOK, array( $this->integration, 'filter_settings_option' ) );
		parent::tear_down();
	}

	/**
	 * Maximum rounds down, never up past the configured cap.
	 */
	public function test_max_rounds_down() {
		$this->assertEqualsWithDelta( 99.99, apply_filters( self::MAX_HOOK, 99.996 ), 0.00001 );
	}

	/**
	 * Minimum rounds up, never below the configured floor.
	 */
	public function test_min_rounds_up() {
		$this->assertEqualsWithDelta( 10.01, apply_filters( self::MIN_HOOK, 10.001 ), 0.00001 );
	}

	/**
	 * Float noise on an exact value does not move it by a unit.
	 */
	public function test_float_noise_stays_exact() {
		$this->assertEqualsWithDelta( 30.0, apply_filters( self::MIN_HOOK, 0.1 * 300 + 0.000000000000004 ), 0.00001 );
		$this->assertEqualsWithDelta( 30.0, apply_filters( self::MAX_HOOK, 29.999999999999996 ), 0.00001 );
	}

	/**
	 * A tiny non-zero maximum keeps the smallest unit instead of becoming "no limit".
	 */
	public function test_nonzero_max_never_becomes_zero() {
		$this->assertEqualsWithDelta( 0.01, apply_filters( self::MAX_HOOK, 0.004 ), 0.00001 );
	}

	/**
	 * Zero still means "no limit".
	 */
	public function test_zero_stays_zero() {
		$this->assertEquals( 0.0, (float) apply_filters( self::MAX_HOOK, 0 ) );
	}
}
