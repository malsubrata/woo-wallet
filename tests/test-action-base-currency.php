<?php
/**
 * Earning-action base-currency integration tests.
 *
 * The `amount` field of an earning action is entered and saved in the store
 * base currency. These tests prove that the new-registration, daily-visit,
 * product-review and sell-content actions credit the wallet against the *base* currency — not the active
 * storefront currency — so the ledger never applies an active-currency
 * conversion to a value that is already in base.
 *
 * The discriminating column is `original_currency`: with the fix it equals
 * the base currency, and a regression (omitting the explicit `currency` arg)
 * would record the active currency instead.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Action_New_Registration::woo_wallet_new_user_registration_credit
 * @covers Action_Daily_Visits::woo_wallet_site_visit_credit
 * @covers Action_Product_Review::new_product_review
 * @covers Action_Product_Review::woo_wallet_product_review_credit
 * @covers Woo_Wallet_Action_Sell_Content::handle_purchase_content
 * @covers WooWalletAction::get_base_currency
 */
class Test_Action_Base_Currency extends WP_UnitTestCase {

	const BASE_CURRENCY   = 'USD';
	const ACTIVE_CURRENCY = 'EUR';

	/**
	 * Customer the test operates on.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Force a known base currency and a *different* active currency, so a
	 * regression that defaults to the active currency is detectable.
	 */
	public function set_up() {
		parent::set_up();

		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );

		// Base currency = the stored WooCommerce option.
		update_option( 'woocommerce_currency', self::BASE_CURRENCY );

		// Active currency = whatever the `woocommerce_currency` filter returns;
		// this mimics a multi-currency plugin switching the storefront currency.
		add_filter( 'woocommerce_currency', array( $this, 'force_active_currency' ) );
	}

	/**
	 * Drop the active-currency override.
	 */
	public function tear_down() {
		remove_filter( 'woocommerce_currency', array( $this, 'force_active_currency' ) );
		unset( $GLOBALS['post'] );
		parent::tear_down();
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
	 * Assert a ledger row is denominated in the base currency with no
	 * active-currency conversion applied.
	 *
	 * @param object $row             Transaction row.
	 * @param float  $expected_amount Configured action amount.
	 */
	private function assertRowInBaseCurrency( $row, $expected_amount ) {
		$this->assertNotNull( $row, 'Expected an earning-action credit row.' );
		$this->assertSame( 'credit', $row->type );

		// The fix: the credit is recorded against the base currency, so the
		// source-side `original_currency` is base — not the active currency.
		$this->assertSame(
			self::BASE_CURRENCY,
			strtoupper( (string) $row->original_currency ),
			'Earning-action credit must record the base currency as its source currency.'
		);
		$this->assertSame( self::BASE_CURRENCY, strtoupper( (string) $row->currency ) );

		// No conversion: rate is identity and the stored amount is verbatim.
		$this->assertEquals( 1.0, (float) $row->original_rate );
		$this->assertEquals( $expected_amount, (float) $row->amount );
	}

	/**
	 * New-user-registration credit is stored in the base currency.
	 */
	public function test_new_registration_credit_uses_base_currency() {
		$this->set_action_settings(
			array(
				'new_registration__enabled'     => 'yes',
				'new_registration__amount'      => '10',
				'new_registration__description' => 'Welcome credit',
			)
		);

		$action = new Action_New_Registration();
		$action->woo_wallet_new_user_registration_credit( $this->user_id );

		$this->assertRowInBaseCurrency( $this->latest_transaction( $this->user_id ), 10.0 );
	}

	/**
	 * Daily-visit credit is stored in the base currency.
	 */
	public function test_daily_visits_credit_uses_base_currency() {
		$this->set_action_settings(
			array(
				'daily_visits__enabled'      => 'yes',
				'daily_visits__amount'       => '15',
				'daily_visits__exclude_role' => array(),
				'daily_visits__description'  => 'Visit credit',
			)
		);

		wp_set_current_user( $this->user_id );

		$action = new Action_Daily_Visits();
		$action->woo_wallet_site_visit_credit();

		$this->assertRowInBaseCurrency( $this->latest_transaction( $this->user_id ), 15.0 );
	}

	/**
	 * Enable the product-review reward and create a pending review by the
	 * test customer on a fresh product.
	 *
	 * @return WP_Comment
	 */
	private function make_pending_review() {
		$this->set_action_settings(
			array(
				'product_review__enabled'     => 'yes',
				'product_review__amount'      => '10',
				'product_review__description' => 'Review reward',
			)
		);
		$product = new WC_Product_Simple();
		$product->set_name( 'Reviewed product' );
		$product->save();

		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'  => $product->get_id(),
				'user_id'          => $this->user_id,
				'comment_approved' => 0,
				'comment_type'     => 'review',
			)
		);
		return get_comment( $comment_id );
	}

	/**
	 * An admin approving a review — whatever their own storefront currency —
	 * credits the reviewer the configured amount in the base currency.
	 */
	public function test_product_review_approval_credit_uses_base_currency() {
		$comment = $this->make_pending_review();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$action = new Action_Product_Review();
		$action->woo_wallet_product_review_credit( 'approved', 'unapproved', $comment );

		$this->assertRowInBaseCurrency( $this->latest_transaction( $this->user_id ), 10.0 );
	}

	/**
	 * An auto-approved review credits the reviewer in the base currency.
	 */
	public function test_product_review_auto_approved_credit_uses_base_currency() {
		$comment = $this->make_pending_review();

		$action = new Action_Product_Review();
		$action->new_product_review(
			(int) $comment->comment_ID,
			1,
			array(
				'comment_post_ID'  => (int) $comment->comment_post_ID,
				'comment_approved' => 1,
				'user_id'          => $this->user_id,
			)
		);

		$this->assertRowInBaseCurrency( $this->latest_transaction( $this->user_id ), 10.0 );
	}

	/**
	 * Paid content: the buyer is debited and the author credited in the base
	 * currency — both legs use the configured price, so neither may be
	 * converted from the buyer's active currency.
	 */
	public function test_sell_content_debit_and_profit_use_base_currency() {
		$author_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$post_id   = self::factory()->post->create( array( 'post_author' => $author_id ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'Float', array( 'currency' => self::BASE_CURRENCY ) );
		wp_set_current_user( $this->user_id );
		$GLOBALS['post'] = get_post( $post_id );

		$action = new Woo_Wallet_Action_Sell_Content();
		$action->update_option( 'enabled', 'yes' );
		$action->update_option( 'profit_share', 50 );
		$action->update_option( 'expiration', 0 );
		$action->init_settings();

		$_POST['amount']               = '20';
		$_POST['tw_buy_content_nonce'] = wp_create_nonce( 'tw_buy_content_nonce_' . $post_id . '_' . $this->user_id . '_20' );
		$action->handle_purchase_content();
		unset( $_POST['amount'], $_POST['tw_buy_content_nonce'] );

		$debit = $this->latest_transaction( $this->user_id );
		$this->assertSame( 'debit', $debit->type );
		$this->assertSame( self::BASE_CURRENCY, strtoupper( (string) $debit->original_currency ) );
		$this->assertEquals( 20.0, (float) $debit->amount );

		$this->assertRowInBaseCurrency( $this->latest_transaction( $author_id ), 10.0 );
	}
}
