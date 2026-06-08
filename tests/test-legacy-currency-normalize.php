<?php
/**
 * Legacy mixed-currency row normalization tests.
 *
 * Regression coverage for the single_base divergence bug: a store running in
 * single_base mode that still carries legacy per-currency rows (e.g. left over
 * from a previous per_currency / multicurrency configuration) reports an
 * inflated, currency-converted balance through
 * `filter_woo_wallet_current_balance`, while the debit security gate in
 * `recode_transaction` uses the raw numeric `SUM(amount)` — which treats the
 * foreign rows at face value. The two diverge, so debits between the two
 * figures are silently blocked even though the UI shows ample balance.
 *
 * The fix normalizes legacy non-base rows into base currency (at the active
 * provider's current rate, matching the displayed value), preserving the
 * source values in the `original_*` audit columns, so the raw SUM becomes the
 * single source of truth again.
 *
 * @package WooWallet\Tests
 */

if ( ! class_exists( 'Woo_Wallet_Test_Fake_Currency_Provider' ) && class_exists( 'Woo_Wallet_Abstract_Currency_Provider' ) ) {
	/**
	 * Deterministic provider with fixed foreign->base rates for tests.
	 */
	class Woo_Wallet_Test_Fake_Currency_Provider extends Woo_Wallet_Abstract_Currency_Provider {

		/**
		 * Map of "FROM>TO" => rate.
		 *
		 * @var array
		 */
		private $rates;

		/**
		 * Constructor.
		 *
		 * @param array $rates Map of "FROM>TO" => float rate.
		 */
		public function __construct( $rates ) {
			$this->rates = $rates;
		}

		/** {@inheritDoc} */
		public function get_id() {
			return 'test_fake_mc';
		}

		/** {@inheritDoc} */
		public function get_label() {
			return 'Test Fake MC';
		}

		/** {@inheritDoc} */
		public function is_available() {
			return true;
		}

		/** {@inheritDoc} */
		public function get_rate( $from, $to ) {
			$from = strtoupper( (string) $from );
			$to   = strtoupper( (string) $to );
			if ( $from === $to ) {
				return 1.0;
			}
			$key = $from . '>' . $to;
			return isset( $this->rates[ $key ] ) ? (float) $this->rates[ $key ] : null;
		}
	}
}

/**
 * @covers ::woo_wallet_normalize_legacy_currency_rows
 * @covers ::woo_wallet_maybe_normalize_legacy_currency_rows
 */
class Test_Legacy_Currency_Normalize extends WP_UnitTestCase {

	/**
	 * Customer the test operates on.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Store base currency.
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Two foreign currency codes distinct from base.
	 *
	 * @var string[]
	 */
	private $foreign;

	/**
	 * Foreign->base rates used by the fake provider.
	 *
	 * @var array
	 */
	private $rates;

	/**
	 * Set up a customer, a deterministic provider, and the rate table.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->base    = strtoupper( get_woocommerce_currency() );

		$pool          = array_diff( array( 'EUR', 'GBP', 'JPY', 'AUD', 'CAD' ), array( $this->base ) );
		$pool          = array_values( $pool );
		$this->foreign = array( $pool[0], $pool[1] );

		// Rate: 1 unit of foreign[0] = 5 base, foreign[1] = 10 base.
		$this->rates = array(
			$this->foreign[0] . '>' . $this->base => 5.0,
			$this->foreign[1] . '>' . $this->base => 10.0,
		);

		$this->register_fake_provider();
	}

	/**
	 * Remove the fake provider so it can't leak into other test cases.
	 */
	public function tear_down() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test_fake_mc' );
		}
		delete_option( 'woo_wallet_pending_legacy_currency_normalize' );
		parent::tear_down();
	}

	/**
	 * Register the deterministic provider at top priority so it wins selection.
	 */
	private function register_fake_provider() {
		$provider = new Woo_Wallet_Test_Fake_Currency_Provider( $this->rates );
		Woo_Wallet_Currency_Manager::instance()->register_provider( $provider, 1 );
	}

	/**
	 * Insert a transaction row directly, bypassing the quantizing write path.
	 *
	 * @param string $type     'credit' | 'debit'.
	 * @param float  $amount   Amount in $currency.
	 * @param string $currency ISO code.
	 * @param int    $mode     0 single_base, 1 per_currency.
	 */
	private function insert_row( $type, $amount, $currency, $mode ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			"{$wpdb->base_prefix}woo_wallet_transactions",
			array(
				'blog_id'           => get_current_blog_id(),
				'user_id'           => $this->user_id,
				'type'              => $type,
				'amount'            => $amount,
				'original_amount'   => $amount,
				'original_currency' => $currency,
				'original_rate'     => 1,
				'mode'              => $mode,
				'currency'          => $currency,
				'date'              => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
		clear_woo_wallet_cache( $this->user_id );
	}

	/**
	 * Raw, unconverted ledger SUM (what the debit gate uses).
	 *
	 * @return float
	 */
	private function raw_sum() {
		global $wpdb;
		return (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0)
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE user_id = %d AND deleted = 0",
				$this->user_id
			)
		);
	}

	/**
	 * Seed the ledger with one base row and three legacy foreign rows.
	 */
	private function seed_mixed_ledger() {
		$this->insert_row( 'credit', 100, $this->base, 0 );
		$this->insert_row( 'credit', 10, $this->foreign[0], 1 );
		$this->insert_row( 'credit', 12, $this->foreign[1], 1 );
		$this->insert_row( 'debit', 2, $this->foreign[1], 1 );
	}

	/**
	 * The bug precondition: the raw gate sees foreign rows at face value, so a
	 * debit that the converted (displayed) balance would allow is blocked.
	 */
	public function test_precondition_raw_gate_blocks_debit() {
		$this->seed_mixed_ledger();

		// Face-value SUM: 100 + 10 + 12 - 2 = 120.
		$this->assertEquals( 120.0, $this->raw_sum() );

		// Converted (what the customer is shown): 100 + 50 + 120 - 20 = 250.
		// A 200 debit is well within the converted balance but above the raw SUM.
		$result = woo_wallet()->wallet->debit( $this->user_id, 200, 'Should be blocked pre-fix' );
		$this->assertFalse( $result );
	}

	/**
	 * After normalization every row is in base currency, the raw SUM equals the
	 * converted total, audit columns are preserved, and the cache is rebuilt.
	 */
	public function test_normalizes_foreign_rows_and_preserves_audit() {
		global $wpdb;
		$this->seed_mixed_ledger();

		$normalized = woo_wallet_normalize_legacy_currency_rows();

		// Three foreign rows were converted; the base row was already compliant.
		$this->assertSame( 3, $normalized );

		// No non-base rows remain.
		$remaining = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d AND currency <> %s",
				$this->user_id,
				$this->base
			)
		);
		$this->assertSame( 0, $remaining );

		// Raw SUM now equals the converted total the customer always saw: 250.
		$this->assertEquals( 250.0, $this->raw_sum() );

		// Audit trail: the foreign[1] credit (12 @ rate 10) is preserved.
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT amount, currency, mode, original_amount, original_currency, original_rate
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE user_id = %d AND type = 'credit' AND original_currency = %s",
				$this->user_id,
				$this->foreign[1]
			)
		);
		$this->assertNotNull( $row );
		$this->assertEquals( 120.0, (float) $row->amount );
		$this->assertEquals( $this->base, strtoupper( $row->currency ) );
		$this->assertEquals( 0, (int) $row->mode );
		$this->assertEquals( 12.0, (float) $row->original_amount );
		$this->assertEquals( $this->foreign[1], strtoupper( $row->original_currency ) );
		$this->assertEquals( 10.0, (float) $row->original_rate );

		// Cache rebuilt to the true base balance.
		$this->assertEquals( 250.0, (float) get_user_meta( $this->user_id, '_current_woo_wallet_balance', true ) );

		// The previously-blocked debit now clears the gate.
		$result = woo_wallet()->wallet->debit( $this->user_id, 200, 'Now allowed' );
		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );
	}

	/**
	 * A clean single_base ledger (all base rows) is a no-op.
	 */
	public function test_clean_ledger_is_noop() {
		$this->insert_row( 'credit', 100, $this->base, 0 );

		$this->assertSame( 0, woo_wallet_normalize_legacy_currency_rows() );
		$this->assertEquals( 100.0, $this->raw_sum() );
	}

	/**
	 * In per_currency mode foreign rows are legitimate and must NOT be touched.
	 */
	public function test_per_currency_mode_is_skipped() {
		update_option( '_wallet_settings_general', array( 'wallet_currency_mode' => 'per_currency' ) );
		add_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );

		$this->seed_mixed_ledger();
		$result = woo_wallet_normalize_legacy_currency_rows();

		remove_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		delete_option( '_wallet_settings_general' );

		$this->assertFalse( $result );
		// Foreign rows untouched (face-value SUM still 120).
		$this->assertEquals( 120.0, $this->raw_sum() );
	}

	/**
	 * The drain clears the pending flag once normalization succeeds.
	 */
	public function test_drain_clears_pending_flag_on_success() {
		update_option( 'woo_wallet_pending_legacy_currency_normalize', 1 );
		$this->seed_mixed_ledger();

		woo_wallet_maybe_normalize_legacy_currency_rows();

		$this->assertFalse( (bool) get_option( 'woo_wallet_pending_legacy_currency_normalize' ) );
		$this->assertEquals( 250.0, $this->raw_sum() );
	}

	/**
	 * Without a usable (non-generic) provider the work cannot be done safely;
	 * the pending flag is retained for a later request and rows are untouched.
	 */
	public function test_drain_retains_flag_without_provider() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test_fake_mc' );
		add_filter( 'woo_wallet_active_currency_provider', array( $this, 'force_generic_provider' ) );

		update_option( 'woo_wallet_pending_legacy_currency_normalize', 1 );
		$this->seed_mixed_ledger();

		woo_wallet_maybe_normalize_legacy_currency_rows();

		remove_filter( 'woo_wallet_active_currency_provider', array( $this, 'force_generic_provider' ) );

		$this->assertTrue( (bool) get_option( 'woo_wallet_pending_legacy_currency_normalize' ) );
		$this->assertEquals( 120.0, $this->raw_sum() );
	}

	/**
	 * Force the generic fallback provider to be selected.
	 *
	 * @return string
	 */
	public function force_generic_provider() {
		return 'generic';
	}
}
