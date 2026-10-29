<?php
/**
 * M1 (1.7.4): a refunded or cancelled top-up takes the credit back.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::reverse_topup_on_refund
 * @covers Woo_Wallet_Wallet::reverse_topup_on_status
 * @covers Woo_Wallet_Wallet::wallet_credit_purchase
 */
class Test_Topup_Reversal extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer, default settings.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		delete_option( '_wallet_settings_general' );
	}

	/**
	 * Drop test filters.
	 */
	public function tear_down() {
		remove_all_filters( 'woo_wallet_reverse_topup_on_refund' );
		parent::tear_down();
	}

	/**
	 * Top-up order of 250, credited unless paid by cash on delivery.
	 *
	 * @param string $gateway Gateway id.
	 * @return WC_Order
	 */
	private function credited_topup( $gateway = 'bacs' ) {
		$item = new WC_Order_Item_Product();
		$item->set_product_id( get_wallet_rechargeable_product()->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( 250 );
		$item->set_total( 250 );

		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$order->add_item( $item );
		$order->set_payment_method( $gateway );
		$order->set_total( 250 );
		$order->save();
		$order->update_status( 'processing' );
		return wc_get_order( $order->get_id() );
	}

	/**
	 * Refund part of an order.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Amount.
	 * @return WC_Order_Refund
	 */
	private function refund( $order, $amount ) {
		$refund = wc_create_refund(
			array(
				'amount'   => $amount,
				'order_id' => $order->get_id(),
			)
		);
		$this->assertNotWPError( $refund );
		return $refund;
	}

	/**
	 * Wallet balance (raw ledger).
	 *
	 * @return float
	 */
	private function balance() {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Reversal debit rows.
	 *
	 * @return array
	 */
	private function reversal_rows() {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT amount, category, details FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND type='debit' AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Order note texts.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function notes( $order ) {
		return implode( "\n", wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' ) );
	}

	public function test_full_refund_takes_whole_credit_back() {
		$order = $this->credited_topup();
		$this->assertEqualsWithDelta( 250, $this->balance(), 0.001 );

		$this->refund( $order, 250 );

		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );
		$rows = $this->reversal_rows();
		$this->assertCount( 1, $rows );
		$this->assertSame( 'topup_reversal', $rows[0]->category );
		$this->assertStringContainsString( 'refunded', $rows[0]->details );
		$this->assertStringContainsString( 'taken back from the customer', $this->notes( $order ) );
	}

	public function test_manual_status_refunded_takes_credit_back_once() {
		$order = $this->credited_topup();
		$order->update_status( 'refunded' ); // WooCommerce creates the full refund.

		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );
		$this->assertCount( 1, $this->reversal_rows() );
	}

	public function test_partial_refund_takes_its_share() {
		$order = $this->credited_topup();
		$this->refund( $order, 100 ); // 40%.

		$this->assertEqualsWithDelta( 150, $this->balance(), 0.001 );
	}

	public function test_partial_refund_then_cancel_takes_only_the_rest() {
		$order = $this->credited_topup();
		$this->refund( $order, 100 );
		wc_get_order( $order->get_id() )->update_status( 'cancelled' );

		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );
		$this->assertEqualsWithDelta( 250, array_sum( wp_list_pluck( $this->reversal_rows(), 'amount' ) ), 0.001 );
		$this->assertStringContainsString( 'cancelled', $this->reversal_rows()[1]->details );
	}

	public function test_spent_balance_debits_to_zero_and_records_shortfall() {
		$order = $this->credited_topup();
		woo_wallet()->wallet->debit( $this->user_id, 200, 'spent' );

		$this->refund( $order, 250 );

		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );
		$order = wc_get_order( $order->get_id() );
		$this->assertEqualsWithDelta( 200, (float) $order->get_meta( '_wc_wallet_topup_reversal_shortfall' ), 0.001 );
		$this->assertStringContainsString( 'The customer had already spent', $this->notes( $order ) );

		// No retry: a later cancel takes nothing more.
		$order->update_status( 'cancelled' );
		$this->assertCount( 2, $this->reversal_rows() ); // "spent" + one reversal.
	}

	public function test_held_cod_topup_cancelled_is_untouched() {
		$order = $this->credited_topup( 'cod' );
		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );

		$order->update_status( 'cancelled' );

		$this->assertCount( 0, $this->reversal_rows() );
		$this->assertStringNotContainsString( 'Wallet top-up refund', $this->notes( $order ) );
	}

	public function test_filter_false_keeps_credit() {
		add_filter( 'woo_wallet_reverse_topup_on_refund', '__return_false' );
		$order = $this->credited_topup();
		$this->refund( $order, 250 );

		$this->assertEqualsWithDelta( 250, $this->balance(), 0.001 );
	}

	public function test_same_refund_hook_twice_debits_once() {
		$order  = $this->credited_topup();
		$refund = $this->refund( $order, 100 );

		do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );

		$this->assertEqualsWithDelta( 150, $this->balance(), 0.001 );
		$this->assertCount( 1, $this->reversal_rows() );
	}

	public function test_non_topup_order_untouched() {
		woo_wallet()->wallet->credit( $this->user_id, 250, 'gift' );
		$product = new WC_Product_Simple();
		$product->set_regular_price( 40 );
		$product->save();
		$order   = wc_create_order( array( 'customer_id' => $this->user_id ) );
		$order->add_product( $product, 1 );
		$order->calculate_totals();
		$order->update_status( 'processing' );
		$order->update_status( 'cancelled' );

		$this->assertEqualsWithDelta( 250, $this->balance(), 0.001 );
		$this->assertCount( 0, $this->reversal_rows() );
	}

	public function test_missing_credit_row_skips_with_one_note() {
		$order = $this->credited_topup();
		$order->delete_meta_data( '_wallet_payment_transaction_id' ); // Like an order credited by an old version.
		$order->save();

		$this->refund( $order, 250 ); // Fires the Refunded status hook and the refund hook.

		$this->assertEqualsWithDelta( 250, $this->balance(), 0.001 );
		$this->assertSame( 1, substr_count( $this->notes( $order ), 'Wallet top-up not taken back: no wallet credit' ) );
	}

	public function test_busy_lock_skips_with_a_note() {
		$order = $this->credited_topup();
		// Hold the order lock from a second connection, as a concurrent request would.
		$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		$other->query( "SELECT GET_LOCK('woo_wallet_credit_purchase_" . $order->get_id() . "', 0)" );
		add_filter( 'woo_wallet_db_lock_timeout', '__return_zero' );

		woo_wallet()->wallet->reverse_topup_on_status( $order->get_id() );

		remove_filter( 'woo_wallet_db_lock_timeout', '__return_zero' );
		$other->close();
		$this->assertEqualsWithDelta( 250, $this->balance(), 0.001 );
		$this->assertStringContainsString( 'Wallet top-up not taken back: the wallet was busy', $this->notes( $order ) );
	}

	public function test_refund_on_free_topup_takes_nothing_until_cancel() {
		add_filter( 'woo_wallet_credit_purchase_amount', array( $this, 'fifty' ) ); // Opted-in coupon top-up: typed 50, paid 0.
		add_filter( 'woocommerce_order_fully_refunded_status', '__return_false' ); // Keep the status, so only the refund hook runs.
		$item = new WC_Order_Item_Product();
		$item->set_product_id( get_wallet_rechargeable_product()->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( 50 );
		$item->set_total( 0 );
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->add_item( $item );
		$order->set_payment_method( 'bacs' );
		$order->set_total( 0 );
		$order->save();
		$order->update_status( 'processing' );
		$this->assertEqualsWithDelta( 50, $this->balance(), 0.001 );

		$this->refund( $order, 0 );
		$this->assertEqualsWithDelta( 50, $this->balance(), 0.001 );

		wc_get_order( $order->get_id() )->update_status( 'cancelled' );
		remove_filter( 'woo_wallet_credit_purchase_amount', array( $this, 'fifty' ) );
		remove_filter( 'woocommerce_order_fully_refunded_status', '__return_false' );
		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );
	}

	/**
	 * Credit amount stand-in.
	 *
	 * @return float
	 */
	public function fifty() {
		return 50.0;
	}

	public function test_reopened_order_is_not_recredited_and_gets_a_note() {
		$order = $this->credited_topup();
		$order->update_status( 'cancelled' );
		wc_get_order( $order->get_id() )->update_status( 'processing' );

		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );
		$this->assertStringContainsString( 'not credited again automatically', $this->notes( $order ) );
	}
}
