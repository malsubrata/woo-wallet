<?php
/**
 * Daily visit reward tests.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Action_Daily_Visits
 */
class Test_Daily_Visit_Reward extends WP_UnitTestCase {

	/**
	 * Customer under test.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Set up a customer and a default enabled action.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $this->user_id );
		$this->configure( array() );
	}

	/**
	 * Save daily-visit settings (unprefixed keys) and return a fresh action.
	 *
	 * @param array $overrides Setting overrides.
	 * @return Action_Daily_Visits
	 */
	private function configure( array $overrides ) {
		$settings = array_merge(
			array(
				'enabled'     => 'yes',
				'amount'      => '5',
				'description' => 'Visit reward',
			),
			$overrides
		);
		$prefixed = array();
		foreach ( $settings as $key => $value ) {
			$prefixed[ 'daily_visits__' . $key ] = $value;
		}
		update_option( '_wallet_settings_actions', $prefixed );
		$this->action = new Action_Daily_Visits();
		return $this->action;
	}

	/**
	 * Current action under test.
	 *
	 * @var Action_Daily_Visits
	 */
	private $action;

	/**
	 * Number of ledger rows for the user.
	 *
	 * @return int
	 */
	private function row_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d", $this->user_id ) );
	}

	/**
	 * Latest ledger row.
	 *
	 * @return object|null
	 */
	private function last_row() {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d ORDER BY transaction_id DESC LIMIT 1", $this->user_id ) );
	}

	/**
	 * Pretend the last reward was on a given offset day (store timezone).
	 *
	 * @param string $modify strtotime-style offset.
	 */
	private function set_last_visit( $modify ) {
		$date = ( new DateTimeImmutable( 'now', wp_timezone() ) )->modify( $modify )->format( 'Y-m-d' );
		update_user_meta( $this->user_id, '_woo_wallet_daily_visit_last', $date );
	}

	/**
	 * Create a simple product.
	 *
	 * @return WC_Product_Simple
	 */
	private function simple_product() {
		$product = new WC_Product_Simple();
		$product->set_name( 'Test product' );
		$product->set_regular_price( '10' );
		$product->save();
		return $product;
	}

	/**
	 * Create an order for the user.
	 *
	 * @param bool   $topup  Whether it contains the wallet top-up product.
	 * @param string $status Order status.
	 */
	private function make_order( $topup, $status = 'completed' ) {
		$product = $topup ? get_wallet_rechargeable_product() : $this->simple_product();
		$order   = wc_create_order( array( 'customer_id' => $this->user_id ) );
		$order->add_product( $product, 1 );
		$order->set_status( $status );
		$order->save();
	}

	public function test_first_visit_credits_once_with_category() {
		$this->action->woo_wallet_site_visit_credit();
		$this->action->woo_wallet_site_visit_credit();
		$this->assertSame( 1, $this->row_count() );
		$this->assertSame( 'engagement_reward', $this->last_row()->category );
		$this->assertSame( wp_date( 'Y-m-d' ), get_user_meta( $this->user_id, '_woo_wallet_daily_visit_last', true ) );
	}

	public function test_next_day_credits_again() {
		$this->action->woo_wallet_site_visit_credit();
		$this->set_last_visit( '-1 day' );
		$this->action->woo_wallet_site_visit_credit();
		$this->assertSame( 2, $this->row_count() );
	}

	public function test_held_lock_blocks_credit() {
		$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME ); // phpcs:ignore
		$name  = 'woo_wallet_daily_visit_' . $this->user_id;
		$this->assertSame( '1', (string) $other->query( "SELECT GET_LOCK('{$name}', 0)" )->fetch_row()[0] );
		try {
			$this->action->woo_wallet_site_visit_credit();
			$this->assertSame( 0, $this->row_count() );
			$this->assertSame( '', get_user_meta( $this->user_id, '_woo_wallet_daily_visit_last', true ) );
		} finally {
			$other->query( "SELECT RELEASE_LOCK('{$name}')" );
			$other->close();
		}
		$this->action->woo_wallet_site_visit_credit();
		$this->assertSame( 1, $this->row_count(), 'Credits again once the lock is free.' );
	}

	public function test_empty_or_zero_amount_credits_nothing() {
		foreach ( array( '', '0' ) as $amount ) {
			$this->configure( array( 'amount' => $amount ) )->woo_wallet_site_visit_credit();
		}
		$this->assertSame( 0, $this->row_count() );
	}

	public function test_excluded_role_credits_nothing() {
		$this->configure( array( 'exclude_role' => array( 'customer' ) ) )->woo_wallet_site_visit_credit();
		$this->assertSame( 0, $this->row_count() );
	}

	public function test_require_paid_order() {
		$action = $this->configure( array( 'require_paid_order' => 'yes' ) );

		$action->woo_wallet_site_visit_credit();
		$this->assertSame( 0, $this->row_count(), 'No orders.' );

		$this->make_order( true );
		$action->woo_wallet_site_visit_credit();
		$this->assertSame( 0, $this->row_count(), 'Top-up order only.' );

		$this->make_order( false, 'pending' );
		$action->woo_wallet_site_visit_credit();
		$this->assertSame( 0, $this->row_count(), 'Unpaid order.' );
		$this->assertSame( '', get_user_meta( $this->user_id, '_woo_wallet_daily_visit_qualified', true ), 'Never cache "not qualified".' );

		$this->make_order( false );
		$action->woo_wallet_site_visit_credit();
		$this->assertSame( 1, $this->row_count() );
		$this->assertSame( 'yes', get_user_meta( $this->user_id, '_woo_wallet_daily_visit_qualified', true ) );
	}

	public function test_cap_stops_credits_without_partial_payment() {
		$action = $this->configure(
			array(
				'amount'     => '5',
				'cap_amount' => '12',
				'cap_period' => 'lifetime',
			)
		);
		for ( $i = 3; $i > 0; $i-- ) {
			$this->set_last_visit( '-1 day' );
			$action->woo_wallet_site_visit_credit();
		}
		$this->assertSame( 2, $this->row_count(), '5 + 5 = 10; a third 5 would pass 12.' );
		$this->assertEquals( 10, get_user_meta( $this->user_id, '_woo_wallet_daily_visit_earned_lifetime', true ) );
	}

	public function test_month_cap_resets_next_month() {
		$action = $this->configure(
			array(
				'amount'     => '5',
				'cap_amount' => '5',
				'cap_period' => 'month',
			)
		);
		$action->woo_wallet_site_visit_credit();
		$this->set_last_visit( '-1 day' );
		$action->woo_wallet_site_visit_credit();
		$this->assertSame( 1, $this->row_count(), 'Cap reached this month.' );

		// Move this month's total to last month's key: the new month starts at zero.
		$key = '_woo_wallet_daily_visit_earned_' . wp_date( 'Y-m' );
		delete_user_meta( $this->user_id, $key );
		update_user_meta( $this->user_id, '_woo_wallet_daily_visit_earned_1999-01', 5 );
		$action->woo_wallet_site_visit_credit();
		$this->assertSame( 2, $this->row_count() );
	}

	public function test_notice_flag_set_then_cleared_after_render() {
		$this->action->woo_wallet_site_visit_credit();
		$this->assertEquals( 5, get_user_meta( $this->user_id, '_woo_wallet_daily_visit_notice', true ) );

		ob_start();
		$this->action->render_notice();
		$html = ob_get_clean();
		$this->assertStringContainsString( 'role="status"', $html );
		$this->assertSame( '', get_user_meta( $this->user_id, '_woo_wallet_daily_visit_notice', true ) );

		ob_start();
		$this->action->render_notice();
		$this->assertSame( '', ob_get_clean() );
	}

	public function test_registration_and_review_use_engagement_category() {
		update_option(
			'_wallet_settings_actions',
			array(
				'new_registration__enabled' => 'yes',
				'new_registration__amount'  => '3',
				'product_review__enabled'   => 'yes',
				'product_review__amount'    => '2',
			)
		);
		( new Action_New_Registration() )->woo_wallet_new_user_registration_credit( $this->user_id );
		$this->assertSame( 'engagement_reward', $this->last_row()->category );

		$product = $this->simple_product();
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_post_ID'  => $product->get_id(),
				'user_id'          => $this->user_id,
				'comment_approved' => '1',
			)
		);
		( new Action_Product_Review() )->woo_wallet_product_review_credit( 'approved', 'unapproved', $comment );
		$this->assertSame( 2, $this->row_count() );
		$this->assertSame( 'engagement_reward', $this->last_row()->category );
	}

	public function test_category_is_registered_with_label() {
		$types = woo_wallet_get_transaction_types();
		$this->assertSame( 'Engagement reward', $types['engagement_reward']['label'] );
	}
}
