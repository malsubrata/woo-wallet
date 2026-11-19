<?php
/**
 * 1.7.5: wallet transfers on a multi-currency store (single_base mode).
 *
 * Regression for the reported overdraw: the transfer balance gate compared the
 * typed amount (active / requested currency) against the base-currency ledger
 * SUM without converting, while the row writer converted it to base. A 100 USD
 * balance browsed in KWD could send 100 KD (= 327 USD), pushing the sender
 * negative and minting spendable funds for the recipient.
 *
 * The real Woo_Wallet_Multicurrency_Integration is registered against a test FX
 * provider, so the limit filters under test are the ones production installs.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::transfer
 * @covers WooWallet_Transfer_Service::execute
 */
class Test_Transfer_Multicurrency extends WP_UnitTestCase {

	/**
	 * Storefront currency reported by the test FX provider.
	 *
	 * @var string
	 */
	public static $active = 'USD';

	/**
	 * Units of each currency per 1 USD (base).
	 *
	 * @var array
	 */
	private static $rates = array(
		'USD' => 1.0,
		'KWD' => 0.3058,
		'INR' => 83.0,
	);

	/**
	 * Sender id.
	 *
	 * @var int
	 */
	private $sender;

	/**
	 * Recipient id.
	 *
	 * @var int
	 */
	private $recipient;

	/**
	 * Integration instance whose hooks were registered for this test.
	 *
	 * @var Woo_Wallet_Multicurrency_Integration
	 */
	private $integration;

	/**
	 * `woo_wallet_transaction_recorded` calls: [ user_id, amount, type ].
	 *
	 * @var array
	 */
	private $recorded = array();

	/**
	 * Base USD, fresh ledger, test FX provider + real integration hooks.
	 */
	public function set_up() {
		parent::set_up();
		global $wpdb;
		// transfer() runs its own START TRANSACTION/COMMIT, which defeats the
		// per-test rollback; start each test from an empty ledger instead.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "TRUNCATE TABLE {$wpdb->base_prefix}woo_wallet_transaction_meta" ); // phpcs:ignore WordPress.DB

		update_option( 'woocommerce_currency', 'USD' );
		delete_option( '_wallet_settings_general' );
		self::$active = 'USD';
		include_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-transfer-service.php';

		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-transfer';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return Test_Transfer_Multicurrency::$active;
				}
				public function get_rate( $from, $to ) {
					return Test_Transfer_Multicurrency::rate( $from, $to );
				}
			},
			1
		);

		// Register the integration's hooks the way a store with a switcher boots.
		$this->set_integration_registered( false );
		$this->integration = new Woo_Wallet_Multicurrency_Integration();

		$this->sender    = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->recipient = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $this->sender, 100.00, 'Seed', array( 'currency' => 'USD' ) );

		$this->recorded = array();
		add_action( 'woo_wallet_transaction_recorded', array( $this, 'record_hook' ), 10, 4 );
	}

	/**
	 * Remove the provider, the integration hooks and the settings.
	 */
	public function tear_down() {
		remove_action( 'woo_wallet_transaction_recorded', array( $this, 'record_hook' ), 10 );
		global $wp_filter;
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
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-transfer' );
		delete_option( '_wallet_settings_general' );
		parent::tear_down();
	}

	/**
	 * Exchange rate between two test currencies, null when unknown.
	 *
	 * @param string $from Source.
	 * @param string $to   Target.
	 * @return float|null
	 */
	public static function rate( $from, $to ) {
		if ( $from === $to ) {
			return 1.0;
		}
		return isset( self::$rates[ $from ], self::$rates[ $to ] ) ? self::$rates[ $to ] / self::$rates[ $from ] : null;
	}

	/**
	 * Capture post-commit ledger hook calls.
	 *
	 * @param int    $id      Transaction id.
	 * @param int    $user_id User.
	 * @param float  $amount  Amount.
	 * @param string $type    credit|debit.
	 */
	public function record_hook( $id, $user_id, $amount, $type ) {
		$this->recorded[] = array( (int) $user_id, (float) $amount, $type );
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
	 * Raw ledger SUM (base) — the figure the balance gate must protect.
	 *
	 * @param int $user_id User.
	 * @return float
	 */
	private function raw( $user_id ) {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $user_id ) ); // phpcs:ignore WordPress.DB
	}

	/**
	 * Save general settings.
	 *
	 * @param array $values Settings.
	 */
	private function settings( array $values ) {
		update_option( '_wallet_settings_general', $values );
	}

	/**
	 * The reported exploit: browsing in a stronger currency cannot overdraw.
	 */
	public function test_stronger_active_currency_cannot_overdraw() {
		self::$active = 'KWD';

		// 31 KD = 101.37 USD, more than the 100 USD balance.
		$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 31, 'out', 'in' ) );
		// 100 KD = 327.01 USD: the researcher's figure.
		$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 100, 'out', 'in' ) );

		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.001 );
		$this->assertEqualsWithDelta( 0.0, $this->raw( $this->recipient ), 0.001 );
	}

	/**
	 * An affordable active-currency transfer moves its base value, rounded.
	 */
	public function test_active_currency_transfer_moves_base_value() {
		self::$active = 'KWD';

		// 30 KD = 98.1033 USD -> 98.10.
		$result = woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 30, 'out', 'in' );
		$this->assertIsArray( $result );
		$this->assertEqualsWithDelta( 1.90, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 98.10, $this->raw( $this->recipient ), 0.0001 );

		// Audit columns keep what the customer typed.
		$row = get_wallet_transaction( $result['debit'] );
		$this->assertSame( 'USD', $row->currency );
		$this->assertSame( 'KWD', $row->original_currency );
		$this->assertEqualsWithDelta( 30.0, (float) $row->original_amount, 0.0001 );

		// Balance cache and post-commit hooks carry the base figures, not the typed ones.
		$this->assertEqualsWithDelta( 1.90, (float) get_user_meta( $this->sender, '_current_woo_wallet_balance', true ), 0.0001 );
		$this->assertEqualsWithDelta( 98.10, (float) get_user_meta( $this->recipient, '_current_woo_wallet_balance', true ), 0.0001 );
		$this->assertContains( array( $this->sender, 98.10, 'debit' ), $this->recorded );
		$this->assertContains( array( $this->recipient, 98.10, 'credit' ), $this->recorded );
	}

	/**
	 * A weaker active currency must not wrongly block an affordable transfer.
	 */
	public function test_weaker_active_currency_is_not_falsely_refused() {
		self::$active = 'INR';

		// 4150 INR = 50 USD.
		$this->assertIsArray( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 4150, 'out', 'in' ) );
		$this->assertEqualsWithDelta( 50.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 50.0, $this->raw( $this->recipient ), 0.0001 );
	}

	/**
	 * Explicit currency (REST) is converted even when it differs from the active one.
	 */
	public function test_explicit_currency_is_converted() {
		$args = array( 'currency' => 'KWD' );
		$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 100, 'out', 'in', null, $args ) );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.001 );

		$this->assertIsArray( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 30, 'out', 'in', null, $args ) );
		$this->assertEqualsWithDelta( 1.90, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * A currency with no known rate is refused rather than moved 1:1.
	 */
	public function test_currency_without_rate_is_refused() {
		$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 10, 'out', 'in', null, array( 'currency' => 'JPY' ) ) );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.001 );
		$this->assertEqualsWithDelta( 0.0, $this->raw( $this->recipient ), 0.001 );
	}

	/**
	 * Base-currency transfers are unchanged.
	 */
	public function test_base_currency_transfer_unchanged() {
		$this->assertIsArray( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 40, 'out', 'in' ) );
		$this->assertEqualsWithDelta( 60.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 40.0, $this->raw( $this->recipient ), 0.0001 );
		$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 60.01, 'out', 'in' ) );
	}

	/**
	 * Service: the form path (no currency) overdraw is refused end to end.
	 */
	public function test_service_form_path_cannot_overdraw() {
		self::$active = 'KWD';
		$result       = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 100 );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'rest_insufficient_balance', $result['code'] );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.001 );
	}

	/**
	 * Service: the maximum (stored in base) is enforced by value in the active currency.
	 */
	public function test_service_maximum_compares_by_value() {
		$this->settings( array( 'max_transfer_amount' => 50 ) );
		self::$active = 'KWD';

		// 16 KD = 52.32 USD, over the 50 USD maximum.
		$over = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 16 );
		$this->assertSame( 'rest_amount_above_maximum', $over['code'] );

		// 15 KD = 49.05 USD.
		$under = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 15 );
		$this->assertTrue( $under['is_valid'] );
		$this->assertEqualsWithDelta( 50.95, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * Service: the minimum is compared in the requested currency, not the active one.
	 */
	public function test_service_minimum_compares_requested_currency() {
		$this->settings( array( 'min_transfer_amount' => 10 ) );

		// Browsing in USD, sending 3.1 KD = 10.14 USD: above the 10 USD minimum.
		$ok = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 3.1, '', 'KWD' );
		$this->assertTrue( $ok['is_valid'] );

		// 3 KD = 9.81 USD: below it.
		$low = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 3, '', 'KWD' );
		$this->assertSame( 'rest_amount_below_minimum', $low['code'] );
	}

	/**
	 * No supported switcher (generic provider) but something filters the storefront
	 * currency: transfers keep working, 1:1 as before 1.7.5 — never refused.
	 */
	public function test_generic_provider_unknown_active_currency_is_not_refused() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-transfer' );
		$eur = function () {
			return 'EUR';
		};
		add_filter( 'woocommerce_currency', $eur );

		$result = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 40 );
		remove_filter( 'woocommerce_currency', $eur );

		$this->assertTrue( $result['is_valid'] );
		$this->assertEqualsWithDelta( 60.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 40.0, $this->raw( $this->recipient ), 0.0001 );
	}

	/**
	 * per_currency mode needs no rate, so an unpriceable currency is not refused.
	 */
	public function test_per_currency_mode_does_not_require_a_rate() {
		add_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		$this->settings( array( 'wallet_currency_mode' => 'per_currency' ) );
		woo_wallet()->wallet->credit( $this->sender, 500, 'Seed JPY', array( 'currency' => 'JPY' ) );

		$result = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 200, '', 'JPY' );
		remove_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );

		$this->assertTrue( $result['is_valid'] );
	}

	/**
	 * A filter that makes the recipient's credit exceed the sender's debit is refused.
	 */
	public function test_credit_above_debit_is_refused() {
		$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 10, 'out', 'in', 11 ) );

		$inflate = function ( $amount ) {
			return $amount * 2;
		};
		add_filter( 'woo_wallet_transfer_credit_amount', $inflate );
		$result = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 10 );
		remove_filter( 'woo_wallet_transfer_credit_amount', $inflate );

		$this->assertFalse( $result['is_valid'] );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.001 );
		$this->assertEqualsWithDelta( 0.0, $this->raw( $this->recipient ), 0.001 );
	}

	/**
	 * Admin REST transfer amounts are base, whatever currency the admin is browsing in.
	 */
	public function test_admin_rest_transfer_is_base_denominated() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		self::$active = 'KWD';

		$request = new WP_REST_Request( 'POST', '/terawallet/v1/admin/transfer' );
		$request->set_header( 'Idempotency-Key', 'mc-admin-' . wp_generate_password( 8, false ) );
		$request->set_body_params(
			array(
				'from_user_id' => $this->sender,
				'to_user_id'   => $this->recipient,
				'amount'       => 40,
			)
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
		$this->assertEqualsWithDelta( 60.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 40.0, $this->raw( $this->recipient ), 0.0001 );
	}

	/**
	 * Legacy wc/v3 credit/debit amounts are base, whatever the active currency.
	 */
	public function test_legacy_rest_amounts_are_base_denominated() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		self::$active = 'KWD';
		$email        = get_userdata( $this->sender )->user_email;

		foreach ( array( 'credit', 'debit' ) as $type ) {
			$request = new WP_REST_Request( 'POST', '/wc/v3/wallet' );
			$request->set_body_params(
				array(
					'email'  => $email,
					'type'   => $type,
					'amount' => 10,
				)
			);
			$this->assertLessThan( 300, rest_do_request( $request )->get_status(), $type );
			$this->assertEqualsWithDelta( 'credit' === $type ? 110.0 : 100.0, $this->raw( $this->sender ), 0.0001, $type );
		}
	}

	/**
	 * Service: a fixed charge (stored in base) is charged at its base value.
	 */
	public function test_service_fixed_charge_is_base_value() {
		$this->settings(
			array(
				'transfer_charge_type'   => 'fixed',
				'transfer_charge_amount' => 1,
			)
		);
		self::$active = 'KWD';

		// 10 KD = 32.70 USD to the recipient, plus a 1 USD charge to the sender.
		$result = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 10 );
		$this->assertTrue( $result['is_valid'] );
		$this->assertEqualsWithDelta( 32.70, $this->raw( $this->recipient ), 0.0001 );
		$this->assertEqualsWithDelta( 66.30, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * POST an admin transactions request as an administrator.
	 *
	 * @param string $route  Route under terawallet/v1/admin/transactions.
	 * @param array  $params Body.
	 * @return WP_REST_Response
	 */
	private function admin_txn( $route, array $params ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'POST', '/terawallet/v1/admin/transactions' . $route );
		$request->set_header( 'Idempotency-Key', 'mc-txn-' . wp_generate_password( 8, false ) );
		$request->set_body_params( $params );
		return rest_do_request( $request );
	}

	/**
	 * Admin create without `currency` is base, in a stronger (overdraw) and a
	 * weaker (false-refusal) active currency. 1.7.4 minted 227 USD per 100 in KWD.
	 */
	public function test_admin_transactions_create_is_base_denominated() {
		foreach ( array( 'KWD', 'INR' ) as $active ) {
			self::$active = $active;
			$before       = $this->raw( $this->sender );
			$credit       = $this->admin_txn( '', array( 'user_id' => $this->sender, 'type' => 'credit', 'amount' => 10 ) );
			$this->assertLessThan( 300, $credit->get_status(), $active );
			$this->assertEqualsWithDelta( $before + 10, $this->raw( $this->sender ), 0.0001, $active );

			$debit = $this->admin_txn( '', array( 'user_id' => $this->sender, 'type' => 'debit', 'amount' => 10 ) );
			$this->assertLessThan( 300, $debit->get_status(), $active );
			$this->assertEqualsWithDelta( $before, $this->raw( $this->sender ), 0.0001, $active );
		}
	}

	/**
	 * An explicit `currency` (≠ active) is still honoured: 10 KD = 32.70 USD.
	 */
	public function test_admin_transactions_create_explicit_currency_is_converted() {
		self::$active = 'INR';
		$response     = $this->admin_txn( '', array( 'user_id' => $this->sender, 'type' => 'credit', 'amount' => 10, 'currency' => 'kwd' ) );
		$this->assertLessThan( 300, $response->get_status() );
		$this->assertEqualsWithDelta( 132.70, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * Bulk credit/debit without `currency` is base for every user.
	 */
	public function test_admin_transactions_bulk_is_base_denominated() {
		self::$active = 'KWD';
		$ids          = array( $this->sender, $this->recipient );
		$credit       = $this->admin_txn( '/bulk', array( 'action' => 'credit', 'user_ids' => $ids, 'amount' => 10 ) );
		$this->assertSame( 200, $credit->get_status() );
		$this->assertEqualsWithDelta( 110.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 10.0, $this->raw( $this->recipient ), 0.0001 );

		self::$active = 'INR';
		$debit        = $this->admin_txn( '/bulk', array( 'action' => 'debit', 'user_ids' => $ids, 'amount' => 5 ) );
		$this->assertSame( 200, $debit->get_status() );
		$this->assertEqualsWithDelta( 105.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 5.0, $this->raw( $this->recipient ), 0.0001 );
	}

	/**
	 * INF / NAN never reach the writer, in either currency mode.
	 */
	public function test_non_finite_amounts_are_refused() {
		// The guard must stop them before SQL: MySQL rejecting `nan` was luck, not a check.
		$leaked = array();
		$spy    = function ( $sql ) use ( &$leaked ) {
			if ( preg_match( '/\b(nan|inf)\b/i', $sql ) && false !== stripos( $sql, 'woo_wallet_transactions' ) ) {
				$leaked[] = $sql;
			}
			return $sql;
		};
		add_filter( 'query', $spy );
		foreach ( array( 'single_base', 'per_currency' ) as $mode ) {
			add_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
			$this->settings( array( 'wallet_currency_mode' => $mode ) );
			$this->assertSame( $mode, woo_wallet()->wallet->get_currency_mode() );
			$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, INF, 'o', 'i' ), $mode );
			$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, NAN, 'o', 'i' ), $mode );
			$this->assertFalse( woo_wallet()->wallet->transfer( $this->sender, $this->recipient, 1, 'o', 'i', INF ), $mode );
			$this->assertFalse( woo_wallet()->wallet->credit( $this->sender, INF ), $mode );
			$this->assertFalse( woo_wallet()->wallet->debit( $this->sender, NAN ), $mode );
			$result = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, '1e309' );
			$this->assertFalse( $result['is_valid'], $mode );
			remove_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		}
		remove_filter( 'query', $spy );
		$this->assertSame( array(), $leaked );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.0001 );
		$this->assertEqualsWithDelta( 0.0, $this->raw( $this->recipient ), 0.0001 );
	}

	/**
	 * credit()/debit() refuse a named currency with no rate instead of storing it 1:1
	 * (8,300 INR refunded as 8,300 USD once INR left the switcher; a typo "INRR").
	 */
	public function test_named_currency_without_rate_is_refused_by_the_writer() {
		foreach ( array( 'USD', 'KWD', 'INR' ) as $active ) {
			self::$active = $active;
			$this->assertFalse( woo_wallet()->wallet->credit( $this->sender, 100, 'x', array( 'currency' => 'JPY' ) ), $active );
			$this->assertFalse( woo_wallet()->wallet->debit( $this->sender, 10, 'x', array( 'currency' => 'JPY' ) ), $active );
			$this->assertFalse( woo_wallet()->wallet->can_cover( $this->sender, 1, 'JPY' ), $active );
		}
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.0001 );

		// Still converted when a rate exists, in both directions.
		self::$active = 'USD';
		$this->assertNotFalse( woo_wallet()->wallet->credit( $this->sender, 10, 'x', array( 'currency' => 'KWD' ) ) );
		$this->assertNotFalse( woo_wallet()->wallet->debit( $this->sender, 83, 'x', array( 'currency' => 'INR' ) ) );
		$this->assertEqualsWithDelta( 100.0 + 32.70 - 1.0, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * An ACTIVE currency with no rate is refused too, named or implicit: 1:1 let a 30 KWD
	 * order be paid with 30 USD on an unsupported switcher (1.7.5-rc). Base stays 1:1.
	 */
	public function test_active_currency_without_rate_is_refused() {
		self::$active = 'JPY';
		$this->assertFalse( woo_wallet()->wallet->credit( $this->sender, 5, 'x', array( 'currency' => 'JPY' ) ) );
		$this->assertFalse( woo_wallet()->wallet->credit( $this->sender, 5, 'x' ) );
		$this->assertFalse( woo_wallet()->wallet->debit( $this->sender, 5, 'x' ) );
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->sender, 5, 'JPY' ) );
		$this->assertNotFalse( woo_wallet()->wallet->credit( $this->sender, 5, 'x', array( 'currency' => 'USD' ) ) );
		$this->assertEqualsWithDelta( 105.0, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * Refunding an order whose currency has lost its rate fails closed, nothing credited.
	 */
	public function test_refund_of_order_in_unpriceable_currency_is_refused() {
		$order = wc_create_order( array( 'customer_id' => $this->sender ) );
		$order->set_currency( 'JPY' );
		$order->set_total( 8300 );
		$order->save();
		$gateway = new Woo_Gateway_Wallet_payment();
		$thrown  = false;
		try {
			$gateway->process_refund( $order->get_id(), 8300 );
		} catch ( Exception $e ) {
			$thrown = true;
		}
		$this->assertTrue( $thrown );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * Admin REST create rejects a malformed currency at the schema and an unpriceable one in the writer.
	 */
	public function test_admin_transactions_reject_bad_currency() {
		$typo = $this->admin_txn( '', array( 'user_id' => $this->sender, 'type' => 'credit', 'amount' => 100, 'currency' => 'INRR' ) );
		$this->assertSame( 400, $typo->get_status() );
		$unpriced = $this->admin_txn( '', array( 'user_id' => $this->sender, 'type' => 'credit', 'amount' => 100, 'currency' => 'JPY' ) );
		$this->assertGreaterThanOrEqual( 400, $unpriced->get_status() );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.0001 );
	}

	/**
	 * can_cover() never says yes to INF/NAN ("inf" quantizes to 0).
	 */
	public function test_can_cover_refuses_non_finite() {
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->sender, INF ) );
		$this->assertFalse( woo_wallet()->wallet->can_cover( $this->sender, NAN ) );
		$this->assertTrue( woo_wallet()->wallet->can_cover( $this->sender, 100 ) );
	}

	/**
	 * can_cover() asks balance filters for the balance in the ledger currency (Pro credit-expiry contract).
	 */
	public function test_can_cover_asks_balance_filter_in_ledger_currency() {
		self::$active = 'KWD';
		$seen = array();
		$spy  = function ( $balance, $user_id, $currency = '' ) use ( &$seen ) {
			$seen[] = $currency;
			return $balance;
		};
		add_filter( 'woo_wallet_current_balance', $spy, 10, 3 );
		woo_wallet()->wallet->can_cover( $this->sender, 10, 'KWD' );
		remove_filter( 'woo_wallet_current_balance', $spy, 10 );
		$this->assertSame( array( 'USD' ), $seen );
	}

	/**
	 * Generic provider (no switcher): a named non-base currency is refused by the
	 * transfer service with a 400-class error, nothing moves.
	 */
	public function test_generic_provider_explicit_currency_is_refused() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-transfer' );
		$result = WooWallet_Transfer_Service::execute( $this->sender, $this->recipient, 10, '', 'EUR' );
		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'rest_invalid_currency', $result['code'] );
		$this->assertEqualsWithDelta( 100.0, $this->raw( $this->sender ), 0.0001 );
	}
}
