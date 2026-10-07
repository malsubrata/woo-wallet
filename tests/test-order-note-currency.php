<?php
/**
 * FE#23: wallet order notes format money in the ORDER currency, not the
 * currency of whoever triggers them, and a prorated cashback clawback says
 * "partly reversed" instead of "fully reversed".
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::execute_cashback_clawback
 * @covers WOO_Wallet_Helper::order_note_price
 */
class Test_Order_Note_Currency extends WP_UnitTestCase {

	/**
	 * GBP store; the admin triggering the note browses in INR.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		add_filter( 'woocommerce_currency', array( $this, 'admin_cookie_currency' ) );
	}

	/**
	 * Drop the currency override.
	 */
	public function tear_down() {
		remove_filter( 'woocommerce_currency', array( $this, 'admin_cookie_currency' ) );
		parent::tear_down();
	}

	/**
	 * Simulated active (cookie) currency.
	 *
	 * @return string
	 */
	public function admin_cookie_currency() {
		return 'INR';
	}

	/**
	 * A GBP order whose customer earned £13.75 cashback.
	 *
	 * @return WC_Order
	 */
	private function order_with_cashback() {
		global $wpdb;
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->base_prefix . 'woo_wallet_transactions',
			array(
				'user_id'  => $user_id,
				'type'     => 'credit',
				'amount'   => 13.75,
				'currency' => 'GBP',
				'category' => 'cashback',
				'deleted'  => 0,
			)
		);
		$transaction_id = (int) $wpdb->insert_id;
		$order          = wc_create_order( array( 'customer_id' => $user_id ) );
		$order->set_currency( 'GBP' );
		$order->update_meta_data( '_general_cashback_transaction_id', array( $transaction_id ) );
		$order->save();
		return $order;
	}

	/**
	 * Run the private clawback helper.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Amount to take back.
	 * @param string   $reason 'cancelled' | 'refunded'.
	 */
	private function clawback( $order, $amount, $reason ) {
		$method = new ReflectionMethod( 'Woo_Wallet_Wallet', 'execute_cashback_clawback' );
		$method->setAccessible( true );
		$method->invoke( woo_wallet()->wallet, $order, $amount, $reason, false );
	}

	/**
	 * All note texts on an order, decoded.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function notes( $order ) {
		$text = '';
		foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
			$text .= html_entity_decode( wp_strip_all_tags( $note->content ), ENT_QUOTES, 'UTF-8' ) . "\n";
		}
		return $text;
	}

	/**
	 * Prorated refund clawback: "partly reversed: £6.31 of £13.75", no ₹.
	 */
	public function test_partial_refund_clawback_note() {
		$order = $this->order_with_cashback();
		$this->clawback( $order, 6.31, 'refunded' );

		$notes = $this->notes( $order );
		$this->assertStringContainsString( 'Cashback partly reversed: £6.31 of £13.75.', $notes );
		$this->assertStringNotContainsString( 'fully reversed', $notes );
		$this->assertStringNotContainsString( '₹', $notes );
	}

	/**
	 * Whole cashback taken back on cancellation: "fully reversed", in £.
	 */
	public function test_full_cancellation_clawback_note() {
		$order = $this->order_with_cashback();
		$this->clawback( $order, 13.75, 'cancelled' );

		$this->assertStringContainsString( 'Cashback £13.75 fully reversed upon cancellation.', $this->notes( $order ) );
	}

	/**
	 * Whole cashback taken back on a full refund says "upon refund".
	 */
	public function test_full_refund_clawback_note() {
		$order = $this->order_with_cashback();
		$this->clawback( $order, 13.75, 'refunded' );

		$this->assertStringContainsString( 'Cashback £13.75 fully reversed upon refund.', $this->notes( $order ) );
	}

	/**
	 * The shared formatter ignores the active currency.
	 */
	public function test_order_note_price_uses_order_currency() {
		$order = $this->order_with_cashback();
		$this->assertSame( '£50.00', html_entity_decode( wp_strip_all_tags( WOO_Wallet_Helper::order_note_price( 50, $order ) ), ENT_QUOTES, 'UTF-8' ) );
	}
}
