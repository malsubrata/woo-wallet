<?php
/**
 * Referral service integration tests.
 *
 * Covers WooWallet_Referral_Service — the 1.6.2 dedicated-table referral core:
 *  - record_visit() writes a completed, currency-tagged visit row + ledger credit;
 *  - record_signup() writes a pending attribution row and is idempotent;
 *  - credit_signup() flips pending → completed and credits the referrer;
 *  - period limits reject further rewards once the cap is reached;
 *  - a deleted referrer is rejected, never credited;
 *  - get_wallet_referrals() filters, paginates and whitelists ORDER BY.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WooWallet_Referral_Service
 * @covers get_wallet_referrals
 */
class Test_Referral_Service extends WP_UnitTestCase {

	const BASE_CURRENCY = 'USD';

	/**
	 * Force a known base currency and load the service under test.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', self::BASE_CURRENCY );
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-referral-service.php';
	}

	/**
	 * Rebuild the shared action registry after the per-test rollback.
	 */
	public function tear_down() {
		unset( $_COOKIE['woo_wallet_referral'] );
		remove_filter( 'query', array( $this, 'break_referral_insert' ) );
		remove_filter( 'query', array( $this, 'break_referral_completion' ) );
		remove_all_filters( 'woocommerce_logging_class' );
		parent::tear_down();
		wp_cache_delete( 'alloptions', 'options' );
		if ( class_exists( 'WOO_Wallet_Actions' ) ) {
			WOO_Wallet_Actions::instance()->init();
		}
	}

	/**
	 * Test query filter — redirect the referral-row INSERT to a missing table
	 * so it fails, simulating a row-write failure without DDL (the WP test
	 * framework rewrites real DROP TABLE statements to DROP TEMPORARY TABLE).
	 *
	 * @param string $query SQL query.
	 * @return string
	 */
	public function break_referral_insert( $query ) {
		if ( 0 === stripos( ltrim( $query ), 'INSERT' ) && false !== strpos( $query, 'woo_wallet_referrals' ) ) {
			return 'INSERT INTO ' . $GLOBALS['wpdb']->base_prefix . 'woo_wallet_referrals_missing_xyz (x) VALUES (1)';
		}
		return $query;
	}

	/**
	 * Test query filter — make the post-credit referral UPDATE (the one that
	 * writes transaction_id) fail, simulating a lost bookkeeping write.
	 *
	 * @param string $query SQL query.
	 * @return string
	 */
	public function break_referral_completion( $query ) {
		if ( 0 === stripos( ltrim( $query ), 'UPDATE' ) && false !== strpos( $query, 'woo_wallet_referrals' ) && false !== strpos( $query, 'transaction_id' ) ) {
			return 'UPDATE ' . $GLOBALS['wpdb']->base_prefix . 'woo_wallet_referrals_missing_xyz SET x = 1';
		}
		return $query;
	}

	/**
	 * Route wc_get_logger() to a stub that records messages per source.
	 *
	 * @return ArrayObject Logged [ source, message ] pairs.
	 */
	private function capture_logs() {
		$logs   = new ArrayObject();
		$logger = new class( $logs ) extends WC_Logger {
			/**
			 * Captured entries.
			 *
			 * @var ArrayObject
			 */
			private $logs;

			/**
			 * @param ArrayObject $logs Sink.
			 */
			public function __construct( $logs ) {
				$this->logs = $logs;
			}

			/**
			 * @param string $level   Level.
			 * @param string $message Message.
			 * @param array  $context Context.
			 */
			public function log( $level, $message, $context = array() ) {
				$this->logs[] = array( $context['source'] ?? '', $message );
			}
		};
		add_filter(
			'woocommerce_logging_class',
			function () use ( $logger ) {
				return $logger;
			}
		);
		return $logs;
	}

	/**
	 * Build an Action_Referrals with the referral feature enabled.
	 *
	 * @param array $settings Extra `referrals__`-prefixed settings.
	 * @return Action_Referrals
	 */
	private function make_action( array $settings = array() ) {
		update_option(
			'_wallet_settings_actions',
			array_merge(
				array(
					'referrals__enabled'      => 'yes',
					'referrals__referal_link' => 'id',
				),
				$settings
			)
		);
		return new Action_Referrals();
	}

	/**
	 * The single signup referral row for a referred user, or null.
	 *
	 * @param int $referred_user_id Referred user.
	 * @return object|null
	 */
	private function signup_row( $referred_user_id ) {
		$rows = get_wallet_referrals(
			array(
				'referred_user_id' => $referred_user_id,
				'type'             => 'signup',
			)
		);
		return ! empty( $rows ) ? $rows[0] : null;
	}

	/**
	 * Count credit ledger rows for a user.
	 *
	 * @param int $user_id User.
	 * @return int
	 */
	private function count_credits( $user_id ) {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE user_id = %d AND type = 'credit'",
				$user_id
			)
		);
	}

	/**
	 * Most recent ledger row for a user.
	 *
	 * @param int $user_id User.
	 * @return object|null
	 */
	private function latest_transaction( $user_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE user_id = %d ORDER BY transaction_id DESC LIMIT 1",
				$user_id
			)
		);
	}

	/**
	 * record_visit() writes a completed, currency-tagged row + ledger credit.
	 */
	public function test_record_visit_writes_completed_row() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount' => '5',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description' => 'Visit',
			)
		);

		$result = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		$this->assertTrue( $result['is_valid'] );

		$rows = get_wallet_referrals(
			array(
				'referrer_id' => $referrer,
				'type'        => 'visit',
			)
		);
		$this->assertCount( 1, $rows );
		$row = $rows[0];
		$this->assertSame( 'completed', $row->status );
		$this->assertSame( self::BASE_CURRENCY, strtoupper( $row->currency ) );
		$this->assertEquals( 5.0, (float) $row->amount );
		$this->assertGreaterThan( 0, (int) $row->transaction_id );
		$this->assertNotEmpty( $row->date_credited );
		$this->assertSame( 1, $this->count_credits( $referrer ) );
	}

	/**
	 * record_visit() must not credit the wallet when the referral row cannot
	 * be written — a referral credit must never exist without its audit row.
	 */
	public function test_record_visit_does_not_credit_without_a_row() {
		global $wpdb;
		$suppress = $wpdb->suppress_errors( true );
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount' => '5',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description' => 'Visit',
			)
		);

		// Force the referral-row INSERT to fail, leaving the table intact.
		add_filter( 'query', array( $this, 'break_referral_insert' ) );
		$result = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		remove_filter( 'query', array( $this, 'break_referral_insert' ) );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'record_failed', $result['code'] );
		$this->assertSame(
			0,
			$this->count_credits( $referrer ),
			'No wallet credit may happen when the referral row was not written.'
		);
		$wpdb->suppress_errors( $suppress );
	}

	/**
	 * record_visit() refuses an anonymous visitor: no referral row, no credit.
	 */
	public function test_record_visit_refuses_anonymous_visitor() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount' => '5',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description' => 'Visit',
			)
		);
		wp_set_current_user( 0 );

		$result = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'anonymous_visit', $result['code'] );
		$this->assertSame(
			0,
			get_wallet_referrals_count(
				array(
					'referrer_id' => $referrer,
					'type'        => 'visit',
				)
			)
		);
		$this->assertSame( 0, $this->count_credits( $referrer ) );
	}

	/**
	 * record_signup() writes a pending attribution row and credits nobody.
	 */
	public function test_record_signup_creates_pending_row() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_signups_amount' => '8' ) );

		$result = WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );
		$this->assertTrue( $result['is_valid'] );

		$row = $this->signup_row( $customer );
		$this->assertNotNull( $row );
		$this->assertSame( 'pending', $row->status );
		$this->assertEquals( $referrer, (int) $row->referrer_id );
		$this->assertSame( 0, (int) $row->transaction_id );
		$this->assertSame( 0, $this->count_credits( $referrer ) );
	}

	/**
	 * credit_signup() flips a pending row to completed and credits the referrer.
	 */
	public function test_credit_signup_completes_pending_row() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_signups_amount'      => '8',
				'referrals__referring_signups_description' => 'Signup',
			)
		);

		WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );
		$result = WooWallet_Referral_Service::credit_signup( $action, $customer, 0 );

		$this->assertTrue( $result['is_valid'] );
		$row = $this->signup_row( $customer );
		$this->assertSame( 'completed', $row->status );
		$this->assertEquals( 8.0, (float) $row->amount );
		$this->assertSame( self::BASE_CURRENCY, strtoupper( $row->currency ) );
		$this->assertGreaterThan( 0, (int) $row->transaction_id );
		$this->assertNotEmpty( $row->date_credited );
		$this->assertSame( 1, $this->count_credits( $referrer ) );
	}

	/**
	 * At most one signup row per referred user, ever.
	 */
	public function test_record_signup_is_idempotent() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_signups_amount' => '8' ) );

		$first  = WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );
		$second = WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );

		$this->assertTrue( $first['is_valid'] );
		$this->assertFalse( $second['is_valid'] );
		$this->assertCount(
			1,
			get_wallet_referrals(
				array(
					'referred_user_id' => $customer,
					'type'             => 'signup',
				)
			)
		);
	}

	/**
	 * Once the visit period cap is reached, further visits are rejected.
	 */
	public function test_visit_period_limit_blocks_further_credits() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount' => '5',
				'referrals__referring_visitors_limit_duration' => 'day',
				'referrals__referring_visitors_limit'  => '1',
				'referrals__referring_visitors_description' => 'Visit',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$first = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$second = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );

		$this->assertTrue( $first['is_valid'] );
		$this->assertFalse( $second['is_valid'] );
		$this->assertSame( 'limit_reached', $second['code'] );
		$this->assertCount(
			1,
			get_wallet_referrals(
				array(
					'referrer_id' => $referrer,
					'type'        => 'visit',
				)
			)
		);
		$this->assertSame( 1, $this->count_credits( $referrer ) );
	}

	/**
	 * A signup whose referrer was deleted is rejected, never credited.
	 */
	public function test_credit_signup_rejects_deleted_referrer() {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_signups_amount' => '6' ) );

		WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );
		wp_delete_user( $referrer );

		$result = WooWallet_Referral_Service::credit_signup( $action, $customer, 0 );
		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'referrer_deleted', $result['code'] );

		$row = $this->signup_row( $customer );
		$this->assertSame( 'rejected', $row->status );
		$this->assertSame( 'referrer_deleted', $row->reject_reason );
		$this->assertNull( $this->latest_transaction( 0 ), 'A deleted referrer must never credit user ID 0.' );
	}

	/**
	 * get_wallet_referrals() filters by type/status, paginates, and falls back
	 * to a safe ORDER BY column for an unknown order_by.
	 */
	public function test_get_wallet_referrals_filters_and_pagination() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount' => '2',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description' => 'Visit',
			)
		);

		// Three distinct logged-in visitors: one visit reward each.
		for ( $i = 0; $i < 3; $i++ ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
			WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		}

		$this->assertSame(
			3,
			get_wallet_referrals_count(
				array(
					'referrer_id' => $referrer,
					'type'        => 'visit',
				)
			)
		);
		$this->assertSame(
			0,
			get_wallet_referrals_count(
				array(
					'referrer_id' => $referrer,
					'type'        => 'signup',
				)
			)
		);
		$this->assertSame(
			3,
			get_wallet_referrals_count(
				array(
					'referrer_id' => $referrer,
					'status'      => 'completed',
				)
			)
		);

		$page1 = get_wallet_referrals(
			array(
				'referrer_id' => $referrer,
				'limit'       => '0,2',
			)
		);
		$page2 = get_wallet_referrals(
			array(
				'referrer_id' => $referrer,
				'limit'       => '2,2',
			)
		);
		$this->assertCount( 2, $page1 );
		$this->assertCount( 1, $page2 );

		// An unsafe order_by must fall back, not error or inject SQL.
		$safe = get_wallet_referrals(
			array(
				'referrer_id' => $referrer,
				'order_by'    => 'referral_id; DROP TABLE wp_users',
			)
		);
		$this->assertCount( 3, $safe );
	}

	/**
	 * Visit the referral link as a fresh logged-in customer.
	 *
	 * @param Action_Referrals $action   Action.
	 * @param int              $referrer Referrer.
	 * @return array Service envelope.
	 */
	private function visit_as_new_user( $action, $referrer ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		return WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
	}

	/**
	 * Daily cap 2: the 3rd visit that day is refused with `daily_cap`, no row
	 * is inserted and nothing is credited.
	 */
	public function test_visit_daily_cap_blocks_third_visit() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount'    => '1',
				'referrals__referring_visitors_daily_cap' => '2',
			)
		);

		$this->assertTrue( $this->visit_as_new_user( $action, $referrer )['is_valid'] );
		$this->assertTrue( $this->visit_as_new_user( $action, $referrer )['is_valid'] );
		$third = $this->visit_as_new_user( $action, $referrer );

		$this->assertFalse( $third['is_valid'] );
		$this->assertSame( 'daily_cap', $third['code'] );
		$this->assertSame( 2, get_wallet_referrals_count( array( 'referrer_id' => $referrer ) ) );
		$this->assertSame( 2, $this->count_credits( $referrer ) );
	}

	/**
	 * The day starts at midnight in the store time zone: visits before it do
	 * not count, visits after it do.
	 */
	public function test_visit_daily_cap_resets_at_store_midnight() {
		global $wpdb;
		update_option( 'timezone_string', 'Asia/Kolkata' );
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount'    => '1',
				'referrals__referring_visitors_daily_cap' => '2',
			)
		);
		$this->visit_as_new_user( $action, $referrer );
		$this->visit_as_new_user( $action, $referrer );

		// Move both rows to one minute before store midnight → yesterday.
		$now     = current_datetime();
		$elapsed = $now->getTimestamp() - $now->setTime( 0, 0 )->getTimestamp();
		$table   = $wpdb->base_prefix . 'woo_wallet_referrals';
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET date_created = NOW() - INTERVAL %d SECOND WHERE referrer_id = %d", $elapsed + 60, $referrer ) ); // phpcs:ignore WordPress.DB

		$this->assertTrue( $this->visit_as_new_user( $action, $referrer )['is_valid'] );
		$this->assertTrue( $this->visit_as_new_user( $action, $referrer )['is_valid'] );
		$this->assertSame( 'daily_cap', $this->visit_as_new_user( $action, $referrer )['code'] );
		$this->assertSame( 4, $this->count_credits( $referrer ) );
	}

	/**
	 * Default cap (setting never saved) is 20; cap 0 means no limit.
	 */
	public function test_visit_daily_cap_default_and_zero() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_visitors_amount' => '1' ) );
		for ( $i = 0; $i < 20; $i++ ) {
			$this->assertTrue( $this->visit_as_new_user( $action, $referrer )['is_valid'] );
		}
		$this->assertSame( 'daily_cap', $this->visit_as_new_user( $action, $referrer )['code'] );

		$action = $this->make_action(
			array(
				'referrals__referring_visitors_amount'    => '1',
				'referrals__referring_visitors_daily_cap' => '0',
			)
		);
		$this->assertTrue( $this->visit_as_new_user( $action, $referrer )['is_valid'] );
		$this->assertSame( 21, $this->count_credits( $referrer ) );
	}

	/**
	 * The woo_wallet_referral_visit_daily_cap filter overrides the setting
	 * and receives the referrer id.
	 */
	public function test_visit_daily_cap_filter() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_visitors_amount' => '1' ) );
		$seen     = 0;
		$filter   = function ( $cap, $referrer_id ) use ( &$seen ) {
			$seen = $referrer_id;
			return 1;
		};
		add_filter( 'woo_wallet_referral_visit_daily_cap', $filter, 10, 2 );
		$this->visit_as_new_user( $action, $referrer );
		$second = $this->visit_as_new_user( $action, $referrer );
		remove_filter( 'woo_wallet_referral_visit_daily_cap', $filter, 10 );

		$this->assertSame( 'daily_cap', $second['code'] );
		$this->assertSame( $referrer, $seen );
	}

	/**
	 * Sign-up rewards are not limited by the visit cap.
	 */
	public function test_visit_daily_cap_does_not_affect_signups() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount'    => '1',
				'referrals__referring_visitors_daily_cap' => '1',
				'referrals__referring_signups_amount'     => '8',
			)
		);
		$this->visit_as_new_user( $action, $referrer );
		$this->assertSame( 'daily_cap', $this->visit_as_new_user( $action, $referrer )['code'] );

		WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );
		$this->assertTrue( WooWallet_Referral_Service::credit_signup( $action, $customer, 0 )['is_valid'] );
		$this->assertSame( 2, $this->count_credits( $referrer ) );
	}

	/**
	 * Sign-up: when the write after the credit fails, the row is already
	 * claimed, so a later call cannot pay it again; the failure is logged.
	 */
	public function test_signup_failed_completion_update_never_pays_twice() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_signups_amount' => '8' ) );
		WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );
		$logs = $this->capture_logs();

		add_filter( 'query', array( $this, 'break_referral_completion' ) );
		$suppress = $GLOBALS['wpdb']->suppress_errors( true );
		$first  = WooWallet_Referral_Service::credit_signup( $action, $customer, 0 );
		$second = WooWallet_Referral_Service::credit_signup( $action, $customer, 0 );
		remove_filter( 'query', array( $this, 'break_referral_completion' ) );
		$GLOBALS['wpdb']->suppress_errors( $suppress );

		$this->assertTrue( $first['is_valid'] );
		$this->assertFalse( $second['is_valid'] );
		$this->assertSame( 1, $this->count_credits( $referrer ) );
		$this->assertSame( 'completed', $this->signup_row( $customer )->status );
		$sources = array_column( $logs->getArrayCopy(), 0 );
		$this->assertContains( 'woo-wallet-referrals', $sources );
	}

	/**
	 * Sign-up: when the credit itself fails, the claim is released so a later
	 * qualifying order can still pay it — once.
	 */
	public function test_signup_failed_credit_returns_row_to_pending() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_signups_amount' => '8' ) );
		WooWallet_Referral_Service::record_signup( $action, get_userdata( $referrer ), $customer, (string) $referrer );

		update_user_meta( $referrer, '_is_wallet_locked', true );
		$failed = WooWallet_Referral_Service::credit_signup( $action, $customer, 0 );
		$this->assertSame( 'credit_failed', $failed['code'] );
		$this->assertSame( 'pending', $this->signup_row( $customer )->status );

		delete_user_meta( $referrer, '_is_wallet_locked' );
		$this->assertTrue( WooWallet_Referral_Service::credit_signup( $action, $customer, 0 )['is_valid'] );
		$this->assertFalse( WooWallet_Referral_Service::credit_signup( $action, $customer, 0 )['is_valid'] );
		$this->assertSame( 1, $this->count_credits( $referrer ) );
	}

	/**
	 * Visit: when the write after the credit fails, the row still counts, so
	 * the same visitor is not paid again in the period.
	 */
	public function test_visit_failed_completion_update_still_dedups() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action( array( 'referrals__referring_visitors_amount' => '1' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		$this->capture_logs();

		add_filter( 'query', array( $this, 'break_referral_completion' ) );
		$suppress = $GLOBALS['wpdb']->suppress_errors( true );
		$first  = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		$second = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		remove_filter( 'query', array( $this, 'break_referral_completion' ) );
		$GLOBALS['wpdb']->suppress_errors( $suppress );

		$this->assertTrue( $first['is_valid'] );
		$this->assertSame( 'already_credited', $second['code'] );
		$this->assertSame( 1, $this->count_credits( $referrer ) );
	}

	/**
	 * Pre-1.6.2 meta-only referral: the pending row is synthesised under the
	 * per-user lock. While another connection holds that lock, no row is
	 * written and nothing is paid; once it is free the referral pays once.
	 */
	public function test_legacy_shim_waits_for_referred_user_lock() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		update_user_meta( $customer, '_referral_user_id', $referrer );
		$action = $this->make_action( array( 'referrals__referring_signups_amount' => '8' ) );

		$other = mysqli_init();
		if ( ! @$other->real_connect( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			$this->markTestSkipped( 'Second DB connection unavailable.' );
		}
		$other->query( "SELECT GET_LOCK('woo_wallet_referral_signup_lock_" . (int) $customer . "', 5)" );
		add_filter( 'woo_wallet_db_lock_timeout', '__return_zero' );

		$blocked = WooWallet_Referral_Service::credit_signup( $action, $customer, 0 );

		$other->query( "SELECT RELEASE_LOCK('woo_wallet_referral_signup_lock_" . (int) $customer . "')" );
		$other->close();
		remove_filter( 'woo_wallet_db_lock_timeout', '__return_zero' );

		$this->assertSame( 'no_pending_referral', $blocked['code'] );
		$this->assertNull( $this->signup_row( $customer ) );
		$this->assertSame( 0, $this->count_credits( $referrer ) );

		$this->assertTrue( WooWallet_Referral_Service::credit_signup( $action, $customer, 0 )['is_valid'] );
		$this->assertFalse( WooWallet_Referral_Service::credit_signup( $action, $customer, 0 )['is_valid'] );
		$this->assertSame( 1, $this->count_credits( $referrer ) );
	}
}
