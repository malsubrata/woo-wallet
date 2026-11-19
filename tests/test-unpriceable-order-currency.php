<?php
/**
 * 1.7.5: orders in a currency the switcher can no longer price. The wallet must
 * refuse BEFORE anything half-completes: no WooCommerce refund row without a wallet
 * credit, and a top-up that cannot be credited says why on the order.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Ajax::woo_wallet_order_refund
 * @covers Woo_Wallet_Wallet::wallet_credit_purchase
 */
class Test_Unpriceable_Order_Currency extends WP_Ajax_UnitTestCase {

	/**
	 * Customer.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Active (storefront) currency reported by the test provider.
	 *
	 * @var string
	 */
	public static $active = 'USD';

	/**
	 * EUR per 1 USD.
	 *
	 * @var float
	 */
	public static $eur = 0.9;

	/**
	 * Base USD; the test provider prices only USD and EUR (EUR = 0.9 USD^-1).
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'woocommerce_currency', 'USD' );
		self::$active = 'USD';
		self::$eur    = 0.9;
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( ! class_exists( 'Woo_Wallet_Ajax' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php';
		}
		new Woo_Wallet_Ajax();
		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-unpriced';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return Test_Unpriceable_Order_Currency::$active;
				}
				public function get_rate( $from, $to ) {
					$map = array( 'USD' => 1.0, 'EUR' => Test_Unpriceable_Order_Currency::$eur );
					return isset( $map[ $from ], $map[ $to ] ) ? $map[ $to ] / $map[ $from ] : null;
				}
			},
			1
		);
		woo_wallet()->wallet->credit( $this->user_id, 50, 'seed', array( 'currency' => 'USD' ) );
	}

	/**
	 * Remove the provider and request state.
	 */
	public function tearDown(): void {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-unpriced' );
		$_POST = array();
		parent::tearDown();
	}

	/**
	 * Raw ledger SUM.
	 *
	 * @return float
	 */
	private function raw() {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Processing order in $currency.
	 *
	 * @param string $currency Currency.
	 * @return WC_Order
	 */
	private function order( $currency ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( $currency );
		$order->set_total( 8300 );
		$order->set_status( 'processing' );
		$order->save();
		return $order;
	}

	/**
	 * Wallet refund button on a JPY order: refused before a WC refund exists.
	 */
	public function test_refund_button_creates_no_refund_row_when_currency_is_unpriceable() {
		$order                     = $this->order( 'JPY' );
		$_POST['security']         = wp_create_nonce( 'order-item' );
		$_REQUEST['security']      = $_POST['security'];
		$_POST['order_id']         = $order->get_id();
		$_POST['refund_amount']    = '8300';
		$_POST['refunded_amount']  = '0';
		try {
			$this->_handleAjax( 'woo_wallet_order_refund' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
		$response = json_decode( (string) $this->_last_response, true );

		$this->assertFalse( $response['success'] ?? true );
		$this->assertStringContainsString( 'JPY', wp_json_encode( $response ) );
		$this->assertCount( 0, wc_get_order( $order->get_id() )->get_refunds(), 'No refund row without a wallet credit.' );
		$this->assertEqualsWithDelta( 50.0, $this->raw(), 0.0001 );
	}

	/**
	 * A top-up in JPY completed from admin: not credited, not marked credited, and the order says why.
	 */
	public function test_unpriceable_topup_leaves_a_note_and_stays_retryable() {
		$order = $this->order( 'JPY' );
		$item  = new WC_Order_Item_Product();
		$item->set_product_id( get_wallet_rechargeable_product()->get_id() );
		$item->set_total( 8300 );
		$order->add_item( $item );
		$order->save();

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );

		$order = wc_get_order( $order->get_id() );
		$this->assertEqualsWithDelta( 50.0, $this->raw(), 0.0001 );
		$this->assertEmpty( $order->get_meta( '_wc_wallet_purchase_credited' ) );
		$notes = implode( ' ', wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' ) );
		$this->assertStringContainsString( 'no exchange rate from JPY', $notes );
	}

	/**
	 * Admin REST create: `currency: ""` means base (back-compat), lowercase is accepted.
	 */
	public function test_admin_rest_empty_and_lowercase_currency() {
		foreach ( array( '' => 10.0, 'eur' => 10 / 0.9 ) as $currency => $expected ) {
			$before  = $this->raw();
			$request = new WP_REST_Request( 'POST', '/terawallet/v1/admin/transactions' );
			$request->set_header( 'Idempotency-Key', 'unpriced-' . wp_generate_password( 8, false ) );
			$request->set_body_params( array( 'user_id' => $this->user_id, 'type' => 'credit', 'amount' => 10, 'currency' => $currency ) );
			$response = rest_do_request( $request );
			$this->assertLessThan( 300, $response->get_status(), "currency='$currency'" );
			$this->assertEqualsWithDelta( $before + round( $expected, 2 ), $this->raw(), 0.0001 );
		}
	}

	/**
	 * The customer picked JPY (no rate) and the order completes in their own request:
	 * the active-currency fallback must not credit 1,000 JPY as 1,000 USD, for a top-up
	 * or for cashback. Asserts the raw ledger.
	 */
	public function test_inflows_in_an_unpriceable_active_currency_are_not_credited() {
		self::$active = 'JPY';
		$order        = $this->order( 'JPY' );
		$item         = new WC_Order_Item_Product();
		$item->set_product_id( get_wallet_rechargeable_product()->get_id() );
		$item->set_total( 1000 );
		$order->add_item( $item );
		$order->save();
		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );

		$cashback = $this->order( 'JPY' );
		$cashback->update_meta_data( '_wallet_cashback', 100 );
		$cashback->save();
		update_option( '_wallet_settings_credit', array( 'is_enable_cashback_reward_program' => 'on' ) );
		$this->assertEquals( 100, woo_wallet()->cashback->calculate_cashback( false, $cashback->get_id() ), 'Cashback is due on this order.' );
		woo_wallet()->wallet->wallet_cashback( $cashback->get_id() );
		delete_option( '_wallet_settings_credit' );

		$this->assertEqualsWithDelta( 50.0, $this->raw(), 0.0001 );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_wc_wallet_purchase_credited' ) );
		// Wallet checkout itself still works in that session (pre-1.7.5 1:1 fallback).
		$this->assertTrue( woo_wallet()->wallet->accepts_currency( 'JPY' ) );
	}

	/**
	 * A cashback credited 1:1 in the customer's JPY session (as 1.7.4 did) is still
	 * clawed back when the order is cancelled from wp-admin (active = base).
	 */
	public function test_clawback_of_an_active_currency_credit_runs_from_admin() {
		self::$active = 'JPY';
		$order        = $this->order( 'JPY' );
		$id           = woo_wallet()->wallet->credit( $this->user_id, 5, 'cashback', array( 'for' => 'cashback', 'currency' => 'JPY', 'order_id' => $order->get_id() ) );
		$this->assertNotFalse( $id );
		$order->update_meta_data( '_general_cashback_transaction_id', array( $id ) );
		$order->save();
		$this->assertEqualsWithDelta( 55.0, $this->raw(), 0.0001 );

		self::$active = 'USD';
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 50.0, $this->raw(), 0.0001 );
	}

	/**
	 * Wallet refund button on a wallet-paid EUR order after the rate moved: returns the
	 * USD the checkout debit took, not the refund re-converted at today's rate.
	 */
	public function test_refund_button_reverses_the_checkout_debit() {
		$order = $this->order( 'EUR' );
		$order->set_total( 45 );
		$order->set_payment_method( 'wallet' );
		$order->set_transaction_id( woo_wallet()->wallet->debit( $this->user_id, 45, 'pay', array( 'currency' => 'EUR' ) ) ); // 50.00 USD.
		$order->save();
		$this->assertEqualsWithDelta( 0.0, $this->raw(), 0.0001 );

		self::$eur                = 0.5; // EUR doubled in value.
		$_POST['security']        = wp_create_nonce( 'order-item' );
		$_REQUEST['security']     = $_POST['security'];
		$_POST['order_id']        = $order->get_id();
		$_POST['refund_amount']   = '45';
		$_POST['refunded_amount'] = '0';
		try {
			$this->_handleAjax( 'woo_wallet_order_refund' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
		$this->assertEqualsWithDelta( 50.0, $this->raw(), 0.0001, 'Not 90.00 at the new rate.' );
	}

	/**
	 * When the wallet credit fails after wc_create_refund() (here: the order's wallet
	 * payment is already fully returned), the button leaves no orphan WC refund.
	 */
	public function test_refund_button_removes_the_refund_when_the_credit_fails() {
		$order = $this->order( 'USD' );
		$order->set_total( 20 );
		$order->set_payment_method( 'wallet' );
		$order->set_transaction_id( woo_wallet()->wallet->debit( $this->user_id, 20, 'pay', array( 'currency' => 'USD' ) ) );
		$order->update_meta_data( '_woo_wallet_refunded_base_total', 20 ); // Nothing left to return.
		$order->save();
		$_POST['security']        = wp_create_nonce( 'order-item' );
		$_REQUEST['security']     = $_POST['security'];
		$_POST['order_id']        = $order->get_id();
		$_POST['refund_amount']   = '5';
		$_POST['refunded_amount'] = '0';
		try {
			$this->_handleAjax( 'woo_wallet_order_refund' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
		}
		$this->assertFalse( json_decode( (string) $this->_last_response, true )['success'] ?? true );
		$this->assertCount( 0, wc_get_order( $order->get_id() )->get_refunds() );
		$this->assertEqualsWithDelta( 30.0, $this->raw(), 0.0001 );
	}
}

