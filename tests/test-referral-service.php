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
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount'         => '5',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description'    => 'Visit',
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
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$action   = $this->make_action(
			array(
				'referrals__referring_visitors_amount'         => '5',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description'    => 'Visit',
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
				'referrals__referring_visitors_amount'         => '5',
				'referrals__referring_visitors_limit_duration' => 'day',
				'referrals__referring_visitors_limit'          => '1',
				'referrals__referring_visitors_description'    => 'Visit',
			)
		);

		$first  = WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
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
				'referrals__referring_visitors_amount'         => '2',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description'    => 'Visit',
			)
		);

		WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );
		WooWallet_Referral_Service::record_visit( $action, get_userdata( $referrer ), (string) $referrer );

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
}
