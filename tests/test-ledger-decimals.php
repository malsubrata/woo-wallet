<?php
/**
 * 1.7.5: ledger rows are rounded to the store's own decimals, not the shopper's.
 *
 * Currency switchers rewrite the store's decimals to the active currency's:
 * WOOCS via `pre_option_woocommerce_price_num_decimals`, YayCurrency (and others)
 * via `wc_get_price_decimals`. Before 1.7.5 the ledger quantized base amounts with
 * that filtered value, so a shopper browsing in JPY (0 decimals) had USD rows
 * rounded to whole dollars, and one browsing in KWD (3) got 3-decimal base rows.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_ledger_decimals
 * @covers Woo_Wallet_Wallet::credit
 * @covers Woo_Wallet_Wallet::transfer
 */
class Test_Ledger_Decimals extends WP_UnitTestCase {

	/**
	 * Decimals the "switcher" reports, or null for none.
	 *
	 * @var int|null
	 */
	private static $shopper_decimals = null;

	/**
	 * Fresh ledger, base USD at 2 decimals, both switcher-style filters in place.
	 */
	public function set_up() {
		parent::set_up();
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transaction_meta" ); // phpcs:ignore WordPress.DB
		update_option( 'woocommerce_currency', 'USD' );
		update_option( 'woocommerce_price_num_decimals', 2 );
		self::$shopper_decimals = null;
		add_filter( 'pre_option_woocommerce_price_num_decimals', array( __CLASS__, 'pre_option' ) );
		add_filter( 'wc_get_price_decimals', array( __CLASS__, 'price_decimals' ), 999 );
	}

	/**
	 * Remove the filters.
	 */
	public function tear_down() {
		remove_filter( 'pre_option_woocommerce_price_num_decimals', array( __CLASS__, 'pre_option' ) );
		remove_filter( 'wc_get_price_decimals', array( __CLASS__, 'price_decimals' ), 999 );
		update_option( 'woocommerce_price_num_decimals', 2 );
		parent::tear_down();
	}

	/**
	 * WOOCS-style override of the option itself.
	 *
	 * @param mixed $pre Short-circuit value.
	 * @return mixed
	 */
	public static function pre_option( $pre ) {
		return null === self::$shopper_decimals ? $pre : self::$shopper_decimals;
	}

	/**
	 * YayCurrency-style override of wc_get_price_decimals().
	 *
	 * @param int $decimals Decimals.
	 * @return int
	 */
	public static function price_decimals( $decimals ) {
		return null === self::$shopper_decimals ? $decimals : self::$shopper_decimals;
	}

	/**
	 * Stored amount of one transaction.
	 *
	 * @param int $id Transaction id.
	 * @return string
	 */
	private function stored( $id ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT amount FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id=%d", $id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * The helper reads the store setting past both filters.
	 */
	public function test_ledger_decimals_ignore_switcher_filters() {
		self::$shopper_decimals = 0;
		$this->assertSame( 0, wc_get_price_decimals() );
		$this->assertSame( 2, WOO_Wallet_Helper::get_ledger_decimals() );

		update_option( 'woocommerce_price_num_decimals', 3 );
		$this->assertSame( 3, WOO_Wallet_Helper::get_ledger_decimals() );
	}

	/**
	 * Odd stored values are read exactly as WooCommerce's absint( get_option() ) does.
	 */
	public function test_ledger_decimals_match_woocommerce_for_odd_values() {
		global $wpdb;
		// Raw row values, as a hand-edited or migrated site may hold them.
		$raw = function ( $value ) use ( $wpdb ) {
			$wpdb->replace( $wpdb->options, array( 'option_name' => 'woocommerce_price_num_decimals', 'option_value' => $value, 'autoload' => 'yes' ) ); // phpcs:ignore WordPress.DB
		};
		$raw( '' );
		$this->assertSame( 0, WOO_Wallet_Helper::get_ledger_decimals() );
		$raw( '-1' );
		$this->assertSame( 1, WOO_Wallet_Helper::get_ledger_decimals() );
		$wpdb->delete( $wpdb->options, array( 'option_name' => 'woocommerce_price_num_decimals' ) ); // phpcs:ignore WordPress.DB
		$this->assertSame( 2, WOO_Wallet_Helper::get_ledger_decimals() );
	}

	/**
	 * Stores running a finer-grained wallet (points, crypto) can raise the ledger
	 * precision explicitly — the switcher-proof read must not take that away.
	 */
	public function test_ledger_decimals_filter_allows_finer_wallets() {
		$user  = self::factory()->user->create();
		$four  = function () {
			return 4;
		};
		add_filter( 'woo_wallet_ledger_decimals', $four );
		$id = woo_wallet()->wallet->credit( $user, 0.0004, 'Points', array( 'currency' => 'USD' ) );
		remove_filter( 'woo_wallet_ledger_decimals', $four );

		$this->assertEqualsWithDelta( 0.0004, (float) $this->stored( $id ), 0.000001 );
	}

	/**
	 * A store that LOWERED precision by `woocommerce_price_num_decimals` (whole-number
	 * wallet) keeps it only through woo_wallet_ledger_decimals, as the upgrade notice says.
	 */
	public function test_ledger_decimals_filter_allows_coarser_wallets() {
		$user = self::factory()->user->create();
		$zero = function () {
			return 0;
		};
		add_filter( 'woocommerce_price_num_decimals', $zero );
		$cents = woo_wallet()->wallet->credit( $user, 12.37, 'x', array( 'currency' => 'USD' ) );
		add_filter( 'woo_wallet_ledger_decimals', $zero );
		$whole = woo_wallet()->wallet->credit( $user, 12.37, 'x', array( 'currency' => 'USD' ) );
		remove_filter( 'woo_wallet_ledger_decimals', $zero );
		remove_filter( 'woocommerce_price_num_decimals', $zero );

		$this->assertEqualsWithDelta( 12.37, (float) $this->stored( $cents ), 0.000001 );
		$this->assertEqualsWithDelta( 12.0, (float) $this->stored( $whole ), 0.000001 );
	}

	/**
	 * Shopper browsing in a 0-decimal currency: base rows keep cents.
	 */
	public function test_zero_decimal_shopper_keeps_cents() {
		$user                   = self::factory()->user->create();
		self::$shopper_decimals = 0;

		$id = woo_wallet()->wallet->credit( $user, 10.37, 'Seed', array( 'currency' => 'USD' ) );
		$this->assertEqualsWithDelta( 10.37, (float) $this->stored( $id ), 0.00001 );
	}

	/**
	 * Shopper browsing in a 3-decimal currency: base rows stay at 2.
	 */
	public function test_three_decimal_shopper_gets_no_extra_precision() {
		$user                   = self::factory()->user->create();
		self::$shopper_decimals = 3;

		$id = woo_wallet()->wallet->credit( $user, 1.23456, 'Seed', array( 'currency' => 'USD' ) );
		$this->assertEqualsWithDelta( 1.23, (float) $this->stored( $id ), 0.00001 );
	}

	/**
	 * Transfers round the same way.
	 */
	public function test_transfer_uses_store_decimals() {
		$from = self::factory()->user->create();
		$to   = self::factory()->user->create();
		woo_wallet()->wallet->credit( $from, 50, 'Seed', array( 'currency' => 'USD' ) );
		self::$shopper_decimals = 0;

		$result = woo_wallet()->wallet->transfer( $from, $to, 10.37, 'out', 'in', null, array( 'currency' => 'USD' ) );
		$this->assertIsArray( $result );
		$this->assertEqualsWithDelta( 10.37, (float) $this->stored( $result['credit'] ), 0.00001 );
		$this->assertEqualsWithDelta( 10.37, (float) $this->stored( $result['debit'] ), 0.00001 );
	}

	/**
	 * A store whose own currency has 0 or 3 decimals is quantized to that.
	 */
	public function test_store_setting_is_respected() {
		$user = self::factory()->user->create();

		update_option( 'woocommerce_price_num_decimals', 0 );
		$id = woo_wallet()->wallet->credit( $user, 10.6, 'Seed', array( 'currency' => 'USD' ) );
		$this->assertEqualsWithDelta( 11.0, (float) $this->stored( $id ), 0.00001 );

		update_option( 'woocommerce_price_num_decimals', 3 );
		$id = woo_wallet()->wallet->credit( $user, 1.2346, 'Seed', array( 'currency' => 'USD' ) );
		$this->assertEqualsWithDelta( 1.235, (float) $this->stored( $id ), 0.00001 );
	}
}
