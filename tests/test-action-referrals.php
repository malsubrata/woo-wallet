<?php
/**
 * Referral earning-action integration tests.
 *
 * Covers the v1.6.2 referral fixes:
 *  - visit and signup bonuses are credited in the store base currency;
 *  - the signup processor resolves the referrer from the code captured at
 *    registration time when the live cookie is gone (deferred / SSO path);
 *  - the "Signups" limit counts credited signups, not registrations;
 *  - a deleted referrer is never credited (no `new WP_User( 0 )`);
 *  - Woo_Wallet_Signup_Handler::process() dispatches the signup bonuses.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Action_Referrals
 * @covers Woo_Wallet_Signup_Handler
 */
class Test_Action_Referrals extends WP_UnitTestCase {

	const BASE_CURRENCY   = 'USD';
	const ACTIVE_CURRENCY = 'EUR';

	/**
	 * Force a known base currency and a different active currency, so a
	 * regression that defaults to the active currency is detectable.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', self::BASE_CURRENCY );
		add_filter( 'woocommerce_currency', array( $this, 'force_active_currency' ) );
	}

	/**
	 * Drop the active-currency override and the referral cookie, then rebuild
	 * the shared action registry from the (now rolled-back) database.
	 *
	 * `Woo_Wallet_Signup_Handler` is registered globally at bootstrap and runs
	 * the registry's action handlers for every `user_register`. Tests here may
	 * leave the registry holding enabled actions; rebuilding it after the
	 * per-test rollback ensures it cannot credit factory users in later tests.
	 */
	public function tear_down() {
		remove_filter( 'woocommerce_currency', array( $this, 'force_active_currency' ) );
		unset( $_COOKIE['woo_wallet_referral'] );
		parent::tear_down();
		wp_cache_delete( 'alloptions', 'options' );
		if ( class_exists( 'WOO_Wallet_Actions' ) ) {
			WOO_Wallet_Actions::instance()->init();
		}
	}

	/**
	 * Filter callback: report the simulated active storefront currency.
	 *
	 * @return string
	 */
	public function force_active_currency() {
		return self::ACTIVE_CURRENCY;
	}

	/**
	 * Persist earning-action settings into the unified option.
	 *
	 * @param array $settings Field => value map, keys already `{id}__` prefixed.
	 */
	private function set_action_settings( array $settings ) {
		update_option( '_wallet_settings_actions', $settings );
	}

	/**
	 * Fetch the most recent ledger row for a user.
	 *
	 * @param int $user_id User id.
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
	 * Count credit rows for a user.
	 *
	 * @param int $user_id User id.
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
	 * Assert a ledger row is denominated in the base currency with no
	 * active-currency conversion applied.
	 *
	 * @param object $row             Transaction row.
	 * @param float  $expected_amount Configured action amount.
	 */
	private function assertRowInBaseCurrency( $row, $expected_amount ) {
		$this->assertNotNull( $row, 'Expected a referral credit row.' );
		$this->assertSame( 'credit', $row->type );
		$this->assertSame(
			self::BASE_CURRENCY,
			strtoupper( (string) $row->original_currency ),
			'Referral credit must record the base currency as its source currency.'
		);
		$this->assertSame( self::BASE_CURRENCY, strtoupper( (string) $row->currency ) );
		$this->assertEquals( 1.0, (float) $row->original_rate );
		$this->assertEquals( $expected_amount, (float) $row->amount );
	}

	/**
	 * A referred visitor credits the referrer in the base currency.
	 */
	public function test_referral_visit_credit_uses_base_currency() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->set_action_settings(
			array(
				'referrals__enabled'                           => 'yes',
				'referrals__referal_link'                      => 'id',
				'referrals__referring_visitors_amount'         => '5',
				'referrals__referring_visitors_limit_duration' => '0',
				'referrals__referring_visitors_description'    => 'Visitor referral',
			)
		);

		$_COOKIE['woo_wallet_referral'] = (string) $referrer;

		$action = new Action_Referrals();
		$action->init_referral_visit();

		$this->assertRowInBaseCurrency( $this->latest_transaction( $referrer ), 5.0 );
	}

	/**
	 * A referred signup credits the referrer in the base currency.
	 */
	public function test_referral_signup_credit_uses_base_currency() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->set_action_settings(
			array(
				'referrals__enabled'                       => 'yes',
				'referrals__referal_link'                  => 'id',
				'referrals__referring_signups_amount'      => '8',
				'referrals__referring_signups_description' => 'Signup referral',
				'referrals__referral_order_amount'         => 0,
			)
		);

		$_COOKIE['woo_wallet_referral'] = (string) $referrer;

		$action = new Action_Referrals();
		$action->woo_wallet_referring_signup( $customer );

		$this->assertRowInBaseCurrency( $this->latest_transaction( $referrer ), 8.0 );
	}

	/**
	 * With the cookie gone, the signup is still attributed via the referral
	 * code captured at registration time (the deferred / SSO path).
	 */
	public function test_referral_signup_resolves_from_stored_code() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->set_action_settings(
			array(
				'referrals__enabled'                       => 'yes',
				'referrals__referal_link'                  => 'id',
				'referrals__referring_signups_amount'      => '9',
				'referrals__referring_signups_description' => 'Signup referral',
				'referrals__referral_order_amount'         => 0,
			)
		);

		// No live cookie — only the value persisted by the signup handler.
		unset( $_COOKIE['woo_wallet_referral'] );
		update_user_meta( $customer, '_woo_wallet_referral_at_signup', (string) $referrer );

		$action = new Action_Referrals();
		$action->woo_wallet_referring_signup( $customer );

		$this->assertRowInBaseCurrency( $this->latest_transaction( $referrer ), 9.0 );
		$this->assertEquals( $referrer, (int) get_user_meta( $customer, '_referral_user_id', true ) );
	}

	/**
	 * The signup limit gates credited signups, not registrations.
	 */
	public function test_signup_limit_counts_credited_signups() {
		$referrer  = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer1 = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer2 = self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->set_action_settings(
			array(
				'referrals__enabled'                          => 'yes',
				'referrals__referal_link'                     => 'id',
				'referrals__referring_signups_amount'         => '4',
				'referrals__referring_signups_description'    => 'Signup referral',
				'referrals__referral_order_amount'            => 0,
				'referrals__referring_signups_limit_duration' => 'day',
				'referrals__referring_signups_limit'          => '1',
			)
		);

		$_COOKIE['woo_wallet_referral'] = (string) $referrer;

		$action = new Action_Referrals();
		$action->woo_wallet_referring_signup( $customer1 );
		$action->woo_wallet_referring_signup( $customer2 );

		// Only one signup may be credited within the limit window.
		$this->assertSame( 1, $this->count_credits( $referrer ) );
		// Attribution is still recorded for the over-limit signup.
		$this->assertEquals( $referrer, (int) get_user_meta( $customer2, '_referral_user_id', true ) );
	}

	/**
	 * A signup whose referrer was deleted credits nobody and does not fatal.
	 */
	public function test_deleted_referrer_not_credited() {
		require_once ABSPATH . 'wp-admin/includes/user.php';

		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->set_action_settings(
			array(
				'referrals__enabled'                       => 'yes',
				'referrals__referal_link'                  => 'id',
				'referrals__referring_signups_amount'      => '6',
				'referrals__referring_signups_description' => 'Signup referral',
				'referrals__referral_order_amount'         => 0,
			)
		);

		update_user_meta( $customer, '_referral_user_id', $referrer );
		wp_delete_user( $referrer );

		$action = new Action_Referrals();
		$action->credit_referring_signup( $customer );

		$this->assertNull( $this->latest_transaction( 0 ), 'A deleted referrer must never credit user ID 0.' );
		$this->assertEmpty( get_user_meta( $customer, '_woo_wallet_referral_signup_credited', true ) );
	}

	/**
	 * The referral form fields are labelled, grouped and free of the
	 * daily-visits copy-paste bug.
	 */
	public function test_referral_form_fields_are_admin_friendly() {
		$action = new Action_Referrals();
		$fields = $action->form_fields;

		// Section headings exist with stable ids.
		$heading_ids = array();
		foreach ( $fields as $field ) {
			if ( isset( $field['type'], $field['id'] ) && 'title' === $field['type'] ) {
				$heading_ids[] = $field['id'];
			}
		}
		$this->assertContains( 'referral_intro', $heading_ids );
		$this->assertContains( 'referring_visitors', $heading_ids );
		$this->assertContains( 'referring_signups', $heading_ids );
		$this->assertContains( 'referring_links', $heading_ids );

		// Previously-unlabelled limit fields now carry titles.
		$this->assertNotEmpty( $fields['referring_visitors_limit']['title'] );
		$this->assertNotEmpty( $fields['referring_signups_limit']['title'] );

		// Limit controls are paired side-by-side.
		$this->assertTrue( $fields['referring_visitors_limit_duration']['half'] );
		$this->assertTrue( $fields['referring_visitors_limit']['half'] );
		$this->assertTrue( $fields['referring_signups_limit_duration']['half'] );
		$this->assertTrue( $fields['referring_signups_limit']['half'] );

		// Cap fields are conditional on a limit period being chosen.
		$this->assertSame(
			'referring_visitors_limit_duration',
			$fields['referring_visitors_limit']['show_if']['field']
		);
		$this->assertSame(
			'referring_signups_limit_duration',
			$fields['referring_signups_limit']['show_if']['field']
		);

		// The daily-visits copy-paste bug is gone.
		$this->assertStringNotContainsStringIgnoringCase(
			'daily',
			$fields['referring_visitors_amount']['description']
		);

		// enabled remains the first form field (master-toggle metadata target).
		$first_key = array_key_first( $fields );
		$this->assertSame( 'enabled', $first_key );
	}

	/**
	 * Woo_Wallet_Signup_Handler::process() dispatches both the registration
	 * credit and the referral signup bonus for a pending user.
	 */
	public function test_signup_handler_processes_pending_user() {
		$referrer = self::factory()->user->create( array( 'role' => 'customer' ) );
		$customer = self::factory()->user->create( array( 'role' => 'customer' ) );

		$this->set_action_settings(
			array(
				'new_registration__enabled'                => 'yes',
				'new_registration__amount'                 => '10',
				'new_registration__description'            => 'Welcome credit',
				'referrals__enabled'                       => 'yes',
				'referrals__referal_link'                  => 'id',
				'referrals__referring_signups_amount'      => '7',
				'referrals__referring_signups_description' => 'Signup referral',
				'referrals__referral_order_amount'         => 0,
			)
		);
		// Rebuild the action registry so it reflects the settings above.
		WOO_Wallet_Actions::instance()->init();

		update_user_meta( $customer, '_woo_wallet_signup_pending', time() );
		update_user_meta( $customer, '_woo_wallet_referral_at_signup', (string) $referrer );

		$handler = new Woo_Wallet_Signup_Handler();
		$handler->process( $customer );

		// New user received the registration credit in base currency.
		$this->assertRowInBaseCurrency( $this->latest_transaction( $customer ), 10.0 );
		// Referrer received the signup bonus in base currency.
		$this->assertRowInBaseCurrency( $this->latest_transaction( $referrer ), 7.0 );
		// The pending marker is cleared so it is not processed again.
		$this->assertEmpty( get_user_meta( $customer, '_woo_wallet_signup_pending', true ) );
	}
}
