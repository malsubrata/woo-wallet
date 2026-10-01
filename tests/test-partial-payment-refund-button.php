<?php
/**
 * The "Refund" button on the "Via wallet" fee row.
 *
 * Covers the two defects fixed in 1.7.1: the AJAX handler must reject a
 * request without the `order-item` nonce (CSRF), and it must reverse the
 * stored base-currency amount like the cancel/proportional paths instead of
 * re-converting the order-currency amount at today's rate.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Ajax::woo_wallet_refund_partial_payment
 */
class Test_Partial_Payment_Refund_Button extends WP_Ajax_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Shop manager acting on the order screen; load the AJAX handlers.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );

		if ( ! class_exists( 'Woo_Wallet_Ajax' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php';
		}
		new Woo_Wallet_Ajax();
	}

	/**
	 * Wallet balance as float.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * An order whose $100 wallet share has been debited.
	 *
	 * @return WC_Order
	 */
	private function make_debited_order() {
		woo_wallet()->wallet->credit( $this->user_id, 200, 'seed' );
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( 'Via wallet' );
		$fee->set_total( -100 );
		$order->add_item( $fee );
		$order->set_total( 20 );
		$order->save();
		woo_wallet()->wallet->woocommerce_order_processed( $order );
		return wc_get_order( $order->get_id() );
	}

	/**
	 * Run the AJAX action and return the decoded JSON response (null on wp_die).
	 *
	 * @return array|null
	 */
	private function dispatch() {
		try {
			$this->_handleAjax( 'woo_wallet_refund_partial_payment' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// wp_send_json() always dies; the response is already captured.
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// check_ajax_referer() failure: wp_die( -1 ).
		}
		return json_decode( (string) $this->_last_response, true );
	}

	/**
	 * Clear request superglobals between tests.
	 */
	public function tearDown(): void {
		unset( $_POST['order_id'], $_POST['security'], $_REQUEST['security'] );
		parent::tearDown();
	}

	/**
	 * Without the order-item nonce nothing is credited (CSRF).
	 */
	public function test_rejects_request_without_nonce() {
		$order            = $this->make_debited_order();
		$_POST['order_id'] = $order->get_id();

		$this->dispatch();

		$this->assertEquals( 100.0, $this->balance() );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_payment_refunded' ) );
	}

	/**
	 * A forged nonce is rejected too.
	 */
	public function test_rejects_request_with_bad_nonce() {
		$order             = $this->make_debited_order();
		$_POST['order_id'] = $order->get_id();
		$_POST['security'] = 'not-a-nonce';

		$this->dispatch();

		$this->assertEquals( 100.0, $this->balance() );
	}

	/**
	 * A marketplace-vendor-like role (edit_shop_orders, no manage_woocommerce) is rejected
	 * by both wallet refund handlers even with a valid nonce.
	 */
	public function test_rejects_role_without_manage_woocommerce() {
		$order = $this->make_debited_order();
		add_role( 'woo_wallet_test_vendor', 'Vendor', array( 'read' => true, 'edit_shop_orders' => true ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'woo_wallet_test_vendor' ) ) );

		$_POST['order_id'] = $order->get_id();
		$_POST['security'] = wp_create_nonce( 'order-item' );
		$this->dispatch();

		$_POST['refund_amount']   = '10';
		$_POST['refunded_amount'] = '0';
		try {
			$this->_handleAjax( 'woo_wallet_order_refund' );
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// wp_die( -1 ) from the capability check.
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Would only be reached if the handler ran.
		}
		unset( $_POST['refund_amount'], $_POST['refunded_amount'] );
		remove_role( 'woo_wallet_test_vendor' );

		$this->assertEquals( 100.0, $this->balance() );
		$this->assertEquals( 0.0, (float) wc_get_order( $order->get_id() )->get_total_refunded() );
		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_payment_refunded' ) );
	}

	/**
	 * With a valid nonce the wallet share is returned once.
	 */
	public function test_valid_nonce_refunds_wallet_share() {
		$order             = $this->make_debited_order();
		$_POST['order_id'] = $order->get_id();
		$_POST['security'] = wp_create_nonce( 'order-item' );

		$response = $this->dispatch();

		$this->assertTrue( $response['success'] );
		$this->assertEquals( 200.0, $this->balance() );
		$this->assertNotEmpty( wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_payment_refunded' ) );
	}

	/**
	 * The button reverses the stored base amount, not a fresh FX conversion.
	 */
	public function test_uses_stored_base_amount() {
		$order = $this->make_debited_order();
		// Simulate a cross-currency debit whose base value was 80, not 100.
		$order->update_meta_data( '_partial_payment_base_amount', 80 );
		$order->update_meta_data( '_partial_payment_base_currency', get_woocommerce_currency() );
		$order->save();
		$_POST['order_id'] = $order->get_id();
		$_POST['security'] = wp_create_nonce( 'order-item' );

		$this->dispatch();

		$this->assertEquals( 180.0, $this->balance() ); // 100 left + 80 base.
	}

	/**
	 * After a proportional refund the button returns only the base remainder.
	 */
	public function test_remainder_after_partial_refund_uses_base_amount() {
		$order = $this->make_debited_order();
		$order->update_meta_data( '_partial_payment_base_amount', 80 );
		$order->update_meta_data( '_partial_payment_base_currency', get_woocommerce_currency() );
		$order->update_meta_data( '_woo_wallet_partial_refunded_total', 25 ); // 25 of 100 already returned.
		$order->save();
		$_POST['order_id'] = $order->get_id();
		$_POST['security'] = wp_create_nonce( 'order-item' );

		$this->dispatch();

		$this->assertEquals( 160.0, $this->balance() ); // 100 + 80 * 75/100.
	}
}
