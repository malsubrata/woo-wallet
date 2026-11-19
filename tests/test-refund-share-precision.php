<?php
/**
 * 1.7.5 F1: a refund's order-currency share is never rounded at the LEDGER's decimals.
 *
 * Base JPY (0 dp), order in USD (2 dp), 1 USD = 150 JPY. Rounding the USD share to
 * 0 dp turned a 6 USD refund of a 10 USD wallet part into 1 USD (150 JPY instead of
 * 90) and a 4 USD refund into 0 (nothing instead of 60).
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::process_partial_payment_refund
 * @covers Woo_Wallet_Wallet::process_refunded_order
 */
class Test_Refund_Share_Precision extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Base JPY at 0 decimals; test FX provider 1 USD = 150 JPY.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'JPY' );
		add_filter( 'woo_wallet_ledger_decimals', '__return_zero' );
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		remove_action( 'woocommerce_order_refunded', array( woo_wallet()->wallet, 'process_partial_payment_refund' ), 10 );
		remove_action( 'woocommerce_order_refunded', array( woo_wallet()->wallet, 'process_refunded_order' ), 10 );
		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-jpy';
				}
				public function get_label() {
					return 'Test FX JPY';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return 'JPY';
				}
				public function convert( $amount, $from, $to ) {
					if ( $from === $to ) {
						return (float) $amount;
					}
					return 'USD' === $from ? (float) $amount * 150 : (float) $amount / 150;
				}
			},
			1
		);
		woo_wallet()->wallet->credit( $this->user_id, 10000, 'seed', array( 'currency' => 'JPY' ) );
	}

	/**
	 * Remove fakes.
	 */
	public function tear_down() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-jpy' );
		remove_filter( 'woo_wallet_ledger_decimals', '__return_zero' );
		remove_all_filters( 'woo_wallet_cashback_clawback_strategy' );
		parent::tear_down();
	}

	/**
	 * Raw ledger SUM (base JPY).
	 *
	 * @return float
	 */
	private function raw_balance() {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * USD order: 100 total, of which 10 USD was paid from the wallet (1500 JPY debited).
	 *
	 * @return WC_Order
	 */
	private function partial_order() {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( 'USD' );
		$fee = new WC_Order_Item_Fee();
		$fee->set_name( 'Via wallet' );
		$fee->set_total( -10 );
		$order->add_item( $fee );
		$order->set_total( 100 );
		$order->update_meta_data( '_partial_pay_through_wallet_compleate', 1 );
		$order->update_meta_data( '_partial_payment_base_amount', 1500 );
		$order->update_meta_data( '_partial_payment_base_currency', 'JPY' );
		$order->save();
		return $order;
	}

	/**
	 * Refund $amount USD and run the partial-payment reversal; return the JPY credited.
	 *
	 * @param WC_Order $order  Order.
	 * @param float    $amount Refund in USD.
	 * @return float
	 */
	private function refund_partial( $order, $amount ) {
		$before = $this->raw_balance();
		$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => $amount ) );
		woo_wallet()->wallet->process_partial_payment_refund( $order->get_id(), $refund->get_id() );
		return $this->raw_balance() - $before;
	}

	public function test_partial_refund_share_is_not_rounded_up_to_a_whole_order_unit() {
		$this->assertEqualsWithDelta( 90, $this->refund_partial( $this->partial_order(), 6 ), 0.0001, '6% of 1500 JPY, not 1 USD = 150.' );
	}

	public function test_partial_refund_share_is_not_rounded_down_to_nothing() {
		$this->assertEqualsWithDelta( 60, $this->refund_partial( $this->partial_order(), 4 ), 0.0001, '4% of 1500 JPY, not 0.' );
	}

	public function test_split_refunds_return_exactly_the_debit() {
		$order = $this->partial_order();
		$total = 0.0;
		foreach ( array( 33.33, 33.33, 33.34 ) as $amount ) {
			$total += $this->refund_partial( $order, $amount );
		}
		$this->assertEqualsWithDelta( 1500, $total, 0.0001, 'Three thirds return the whole 1500 JPY, no more.' );
	}

	public function test_cashback_clawback_share_is_not_rounded_to_a_whole_order_unit() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		$product = new WC_Product_Simple();
		$product->set_regular_price( 100 );
		$product->save();
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( 'USD' );
		$order->add_product( $product, 1 );
		$order->set_total( 100 );
		$order->set_status( 'processing' );
		$order->save();
		// 1.60 USD cashback = 240 JPY.
		$tx = woo_wallet()->wallet->credit( $this->user_id, 1.6, 'cashback', array( 'for' => 'cashback', 'currency' => 'USD', 'order_id' => $order->get_id() ) );
		$order->update_meta_data( '_general_cashback_transaction_id', array( $tx ) );
		$order->save();

		$before = $this->raw_balance();
		$refund = wc_create_refund( array( 'order_id' => $order->get_id(), 'amount' => 30 ) );
		woo_wallet()->wallet->process_refunded_order( $order->get_id(), $refund->get_id() );

		// 30% of 1.60 USD = 0.48 USD = 72 JPY (rounding at 0 dp made it 0 USD: nothing taken).
		$this->assertEqualsWithDelta( -72, $this->raw_balance() - $before, 0.0001 );
	}

	/**
	 * Set the raw balance to an exact JPY amount.
	 *
	 * @param float $target JPY.
	 */
	private function set_balance( $target ) {
		$delta = $target - $this->raw_balance();
		if ( $delta > 0 ) {
			woo_wallet()->wallet->credit( $this->user_id, $delta, 'seed', array( 'currency' => 'JPY' ) );
		} elseif ( $delta < 0 ) {
			woo_wallet()->wallet->debit( $this->user_id, -$delta, 'spend', array( 'currency' => 'JPY' ) );
		}
	}

	/**
	 * Processing USD order with $cashback USD credited as cashback.
	 *
	 * @param float $cashback USD.
	 * @return WC_Order
	 */
	private function cashback_order( $cashback ) {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( 'USD' );
		$order->set_total( 100 );
		$order->set_status( 'processing' );
		$order->save();
		$tx = woo_wallet()->wallet->credit( $this->user_id, $cashback, 'cashback', array( 'for' => 'cashback', 'currency' => 'USD', 'order_id' => $order->get_id() ) );
		$order->update_meta_data( '_general_cashback_transaction_id', array( $tx ) );
		$order->save();
		return wc_get_order( $order->get_id() );
	}

	/**
	 * Finding A: 1000 JPY left of a 1500 JPY cashback. The balance as USD floored at 0 dp
	 * was 6 USD, so only 900 JPY was taken and 600 booked as spent.
	 */
	public function test_partial_clawback_takes_the_whole_ledger_balance() {
		$order = $this->cashback_order( 10 ); // 1500 JPY.
		$this->set_balance( 1000 );
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$this->assertEqualsWithDelta( 0, $this->raw_balance(), 0.0001, 'All 1000 JPY is taken back.' );
	}

	/**
	 * Finding A: 149 JPY (just under 1 USD) left: the floor made it 0 USD, nothing taken.
	 */
	public function test_partial_clawback_takes_a_sub_unit_balance() {
		$order = $this->cashback_order( 10 );
		$this->set_balance( 149 );
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$this->assertEqualsWithDelta( 0, $this->raw_balance(), 0.0001 );
	}

	/**
	 * Findings A/B: full_or_skip with a balance that covers the clawback exactly in the
	 * ledger unit (975 JPY = 6.5 USD) must take it, not skip because floor(6.67) = 6 < 6.5.
	 */
	public function test_full_or_skip_takes_a_covered_clawback() {
		add_filter(
			'woo_wallet_cashback_clawback_strategy',
			function () {
				return 'full_or_skip';
			}
		);
		$order = $this->cashback_order( 6.5 ); // 975 JPY.
		$this->set_balance( 975 );
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$this->assertEqualsWithDelta( 0, $this->raw_balance(), 0.0001 );
	}

	/**
	 * Finding C: a share under one ledger unit writes no 0 JPY credit row.
	 */
	public function test_sub_unit_partial_refund_writes_no_zero_row() {
		global $wpdb;
		$order = $this->partial_order(); // 10 USD = 1500 JPY of a 100 USD order.
		$rows  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertEqualsWithDelta( 0, $this->refund_partial( $order, 0.03 ), 0.0001 ); // 0.45 JPY share.
		$this->assertSame( $rows, (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d", $this->user_id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Finding D: many small refunds then the rest still return the whole debit
	 * (6-dp shares summed short of the 1e-6 final tolerance).
	 */
	public function test_many_small_refunds_then_the_rest_return_the_whole_debit() {
		$order = $this->partial_order();
		$order->set_total( 30 );
		$order->save();
		$total = 0.0;
		foreach ( array( 1, 1, 1, 1, 1, 1, 24 ) as $amount ) {
			$total += $this->refund_partial( $order, $amount );
		}
		$this->assertEqualsWithDelta( 1500, $total, 0.0001 );
		$this->assertNotEmpty( wc_get_order( $order->get_id() )->get_meta( '_woo_wallet_partial_payment_refunded' ) );
	}

	/**
	 * Re-check: a 1.7.4 off-grid balance (1000.55 JPY) is floored to 1000 and taken,
	 * not rounded to 1001 and refused by the debit gate.
	 */
	public function test_partial_clawback_takes_an_off_grid_legacy_balance() {
		$order = $this->cashback_order( 10 ); // 1500 JPY.
		$this->set_balance( 1000 );
		$two = function () {
			return 2;
		};
		remove_filter( 'woo_wallet_ledger_decimals', '__return_zero' );
		add_filter( 'woo_wallet_ledger_decimals', $two );
		woo_wallet()->wallet->credit( $this->user_id, 0.55, 'legacy', array( 'currency' => 'JPY' ) );
		remove_filter( 'woo_wallet_ledger_decimals', $two );
		add_filter( 'woo_wallet_ledger_decimals', '__return_zero' );
		$this->assertEqualsWithDelta( 1000.55, $this->raw_balance(), 0.0001 );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$this->assertEqualsWithDelta( 0.55, $this->raw_balance(), 0.0001, '1000 JPY taken; the sub-unit residue stays.' );
	}
}
