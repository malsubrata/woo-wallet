<?php
/**
 * Order edit screen "Cashback" row is formatted in the order's currency —
 * get_total_order_cashback_amount() already returns the amount in it.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Admin::add_wallet_payment_amount
 */
class Test_Order_Cashback_Row_Currency extends WP_UnitTestCase {

	/**
	 * Base GBP.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		}
	}

	/**
	 * Render the Cashback row for an order whose cashback row is stored in
	 * $currency.
	 *
	 * @param string $currency Order + cashback row currency.
	 * @return string
	 */
	private function render_for( $currency ) {
		global $wpdb;
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->base_prefix . 'woo_wallet_transactions',
			array(
				'user_id'  => $user_id,
				'type'     => 'credit',
				'amount'   => 7.79,
				'currency' => $currency,
				'category' => 'cashback',
				'deleted'  => 0,
			)
		);
		$transaction_id = (int) $wpdb->insert_id;
		$order          = wc_create_order( array( 'customer_id' => $user_id ) );
		$order->set_currency( $currency );
		$order->update_meta_data( '_general_cashback_transaction_id', array( $transaction_id ) );
		$order->save();

		ob_start();
		Woo_Wallet_Admin::instance()->add_wallet_payment_amount( $order->get_id() );
		return html_entity_decode( wp_strip_all_tags( ob_get_clean() ), ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * INR order on a GBP store: ₹7.79, not £7.79.
	 */
	public function test_non_base_order_uses_order_currency() {
		$html = $this->render_for( 'INR' );
		$this->assertStringContainsString( html_entity_decode( get_woocommerce_currency_symbol( 'INR' ) ) . '7.79', $html );
		$this->assertStringNotContainsString( '£', $html );
	}

	/**
	 * Base-currency order is unchanged: £7.79.
	 */
	public function test_base_order_unchanged() {
		$this->assertStringContainsString( '£7.79', $this->render_for( 'GBP' ) );
	}
}
