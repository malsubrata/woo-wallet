<?php
/**
 * M3 + M4 (1.7.4): cashback clawback on cancel / refund.
 *
 * M3: a cancel after a partial-refund clawback takes back only what is left.
 * M4: the balance check compares like with like on a non-base-currency order.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Wallet::process_cancelled_order
 * @covers Woo_Wallet_Wallet::process_refunded_order
 */
class Test_Cashback_Clawback extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * USD per GBP for the test FX provider.
	 *
	 * @var float
	 */
	public static $rate = 1.324;

	/**
	 * Base GBP; a test FX provider at 1 GBP = 1.324 USD.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		self::$rate    = 1.324;
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-clawback';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return 'GBP';
				}
				public function convert( $amount, $from, $to ) {
					if ( $from === $to ) {
						return (float) $amount;
					}
					return 'GBP' === $from ? (float) $amount * Test_Cashback_Clawback::$rate : (float) $amount / Test_Cashback_Clawback::$rate;
				}
			},
			1
		);
	}

	/**
	 * Remove fakes and filters.
	 */
	public function tear_down() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-clawback' );
		remove_all_filters( 'woo_wallet_cashback_clawback_strategy' );
		remove_all_filters( 'woo_wallet_disallow_negative_transaction' );
		remove_all_filters( 'woo_wallet_db_lock_timeout' );
		parent::tear_down();
	}

	/**
	 * Processing order with a credited cashback row.
	 *
	 * @param float  $total    Order total.
	 * @param float  $cashback Cashback in the order currency.
	 * @param string $currency Order currency.
	 * @return WC_Order
	 */
	private function order_with_cashback( $total, $cashback, $currency = 'GBP' ) {
		$product = new WC_Product_Simple();
		$product->set_regular_price( $total );
		$product->save();
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( $currency );
		$order->add_product( $product, 1 );
		$order->set_total( $total );
		$order->set_status( 'processing' );
		$order->save();

		$transaction_id = woo_wallet()->wallet->credit(
			$this->user_id,
			$cashback,
			'cashback',
			array(
				'for'      => 'cashback',
				'currency' => $currency,
				'order_id' => $order->get_id(),
			)
		);
		$order->update_meta_data( '_general_cashback_transaction_id', array( $transaction_id ) );
		$order->save();
		return wc_get_order( $order->get_id() );
	}

	/**
	 * Wallet balance (raw ledger, base currency).
	 *
	 * @return float
	 */
	private function balance() {
		global $wpdb;
		return (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Set the wallet balance to an exact base amount.
	 *
	 * @param float $target Base amount.
	 */
	private function set_balance( $target ) {
		$delta = round( $target - $this->balance(), 2 );
		if ( $delta > 0 ) {
			woo_wallet()->wallet->credit( $this->user_id, $delta, 'seed', array( 'currency' => 'GBP' ) );
		} elseif ( $delta < 0 ) {
			woo_wallet()->wallet->debit( $this->user_id, -$delta, 'spend', array( 'currency' => 'GBP' ) );
		}
	}

	/**
	 * All order notes as one string.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function notes( $order ) {
		return implode( ' | ', wp_list_pluck( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ), 'content' ) );
	}

	public function test_m3_cancel_after_partial_refund_takes_back_only_the_rest() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		$this->assertEqualsWithDelta( 110, $this->balance(), 0.001 );

		wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) ); // Claws back 5.
		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001 );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 100, $this->balance(), 0.001, 'Total clawed back must equal the 10 cashback credited, never more.' );
	}

	public function test_same_refund_hook_twice_claws_back_once() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order  = $this->order_with_cashback( 100, 10 );
		$refund = wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) );

		do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );

		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001 );
	}

	public function test_spent_cashback_is_not_retried_on_cancel() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		$order = $this->order_with_cashback( 100, 10 );
		$this->set_balance( 2 ); // Customer spent most of the cashback.

		wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) ); // Due 5: takes 2, 3 recorded as spent.
		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001 );

		$this->set_balance( 50 ); // Customer tops up later.
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		// Cancel takes the other 5 only — not the 3 already recorded as spent.
		$this->assertEqualsWithDelta( 45, $this->balance(), 0.001 );
	}

	public function test_per_currency_mode_uses_the_order_currency_bucket() {
		add_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		update_option( '_wallet_settings_general', array( 'wallet_currency_mode' => 'per_currency' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10, 'USD' ); // $10 in the USD bucket.
		woo_wallet()->wallet->debit( $this->user_id, 7, 'spend', array( 'currency' => 'USD' ) ); // $3 left.

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		remove_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );

		global $wpdb;
		$usd = (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END),0) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0 AND currency='USD'", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertEqualsWithDelta( 0, $usd, 0.001, 'The $3 left in the USD bucket is taken back.' );
	}

	public function test_cancel_recredit_cancel_takes_back_the_second_credit() {
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$this->assertEqualsWithDelta( 100, $this->balance(), 0.001 );

		// Reopened: wallet_cashback() sees no marker and credits again.
		$again = woo_wallet()->wallet->credit( $this->user_id, 10, 'cashback', array( 'for' => 'cashback', 'currency' => 'GBP' ) );
		$order = wc_get_order( $order->get_id() );
		$order->update_meta_data( '_general_cashback_transaction_id', array( $again ) );
		$order->save();
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 100, $this->balance(), 0.001, 'The second credit is taken back too.' );
	}

	public function test_refund_with_busy_wallet_stays_open_with_a_note() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		$other->query( "SELECT GET_LOCK('woo_wallet_lock_user_" . $this->user_id . "', 0)" );
		add_filter( 'woo_wallet_db_lock_timeout', '__return_zero' );

		$refund = wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) );
		$this->assertEqualsWithDelta( 110, $this->balance(), 0.001 ); // Busy: nothing taken.
		$this->assertStringContainsString( 'did not complete', $this->notes( $order ) );

		$other->close();
		do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );
		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001, 'A manual re-fire takes the share.' );
	}

	public function test_failed_debit_is_not_recorded_as_spent() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		add_filter( 'woo_wallet_disallow_negative_transaction', '__return_true' ); // Wallet refuses every debit.
		wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) );
		remove_filter( 'woo_wallet_disallow_negative_transaction', '__return_true' );
		$this->assertEqualsWithDelta( 110, $this->balance(), 0.001 );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 100, $this->balance(), 0.001, 'The refused 5 is still outstanding: cancel takes all 10.' );
	}

	public function test_refused_debit_leaves_the_refund_open_with_a_note() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		add_filter( 'woo_wallet_disallow_negative_transaction', '__return_true' );
		$refund = wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) );
		remove_filter( 'woo_wallet_disallow_negative_transaction', '__return_true' );
		$this->assertEqualsWithDelta( 110, $this->balance(), 0.001 );
		$this->assertStringContainsString( 'did not complete', $this->notes( $order ) );
		$this->assertStringNotContainsString( 'already spent', $this->notes( $order ) );
		$this->assertSame( '', wc_get_order( $order->get_id() )->get_meta( '_cashback_unreversed_amount' ) );

		do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );

		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001, 'A manual re-fire takes the share.' );
	}

	public function test_listener_exception_after_the_debit_keeps_tracking_and_claim() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		add_filter( 'woo_wallet_cashback_refund_clawback_amount', '__return_zero' );
		$refund = wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) );
		remove_filter( 'woo_wallet_cashback_refund_clawback_amount', '__return_zero' );
		$throw = function () {
			throw new RuntimeException( 'listener failed' );
		};
		add_action( 'woo_wallet_transaction_recorded', $throw );

		try {
			do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );
			$this->fail( 'The exception should reach the caller.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'listener failed', $e->getMessage() );
		} finally {
			remove_action( 'woo_wallet_transaction_recorded', $throw );
		}
		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001, 'The row is committed.' );

		do_action( 'woocommerce_order_refunded', $order->get_id(), $refund->get_id() );
		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001, 'A replay of the same refund takes nothing.' );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$this->assertEqualsWithDelta( 100, $this->balance(), 0.001, 'Total taken back is the 10 credited, not 15.' );
	}

	public function test_cancel_with_busy_wallet_keeps_markers_and_notes() {
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		$other->query( "SELECT GET_LOCK('woo_wallet_lock_user_" . $this->user_id . "', 0)" );
		add_filter( 'woo_wallet_db_lock_timeout', '__return_zero' );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
		$other->close();

		$this->assertEqualsWithDelta( 110, $this->balance(), 0.001 );
		$this->assertStringContainsString( 'did not complete', $this->notes( $order ) );
		$this->assertNotEmpty( wc_get_order( $order->get_id() )->get_meta( '_general_cashback_transaction_id' ) );
	}

	public function test_refused_partial_debit_records_no_shortfall() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		$order = $this->order_with_cashback( 100, 10 );
		$this->set_balance( 2 );
		add_filter( 'woo_wallet_disallow_negative_transaction', '__return_true' );
		wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) ); // Due 5, balance 2, debit refused.
		remove_filter( 'woo_wallet_disallow_negative_transaction', '__return_true' );
		$this->set_balance( 50 );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 40, $this->balance(), 0.001, 'Nothing moved, so the cancel takes all 10.' );
	}

	public function test_exception_under_the_wallet_lock_releases_it() {
		global $wpdb;
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		$lock  = 'woo_wallet_lock_user_' . $this->user_id;
		$armed = false;
		$fail  = function ( $query ) use ( &$armed, &$fail, $lock ) {
			if ( $armed ) {
				remove_filter( 'query', $fail );
				throw new RuntimeException( 'database gone' );
			}
			$armed = false !== strpos( $query, 'GET_LOCK' ) && false !== strpos( $query, $lock );
			return $query;
		};
		add_filter( 'query', $fail );

		try {
			woo_wallet()->wallet->process_cancelled_order( $order->get_id() );
			$this->fail( 'The exception should reach the caller.' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'database gone', $e->getMessage() );
		}

		$free = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_FREE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertSame( 1, $free );
	}

	/**
	 * Order refunded before 1.7.4: its clawback left a ledger row but no tracking meta.
	 */
	public function test_pre_174_order_without_tracking_keeps_the_full_cancel_clawback() {
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 10 );
		woo_wallet()->wallet->debit( $this->user_id, 5, 'old refund clawback', array( 'currency' => 'GBP', 'for' => 'refund' ) );

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 95, $this->balance(), 0.001 );
	}

	public function test_rate_change_between_refund_and_cancel_never_over_claws() {
		update_option( '_wallet_settings_credit', array( 'cashback_clawback_on_refund' => 'on' ) );
		woo_wallet()->wallet->credit( $this->user_id, 100, 'seed', array( 'currency' => 'GBP' ) );
		$order = $this->order_with_cashback( 100, 13.24, 'USD' ); // Stored as £10.
		wc_create_refund( array( 'amount' => 50, 'order_id' => $order->get_id() ) ); // $6.62 = £5.
		$this->assertEqualsWithDelta( 105, $this->balance(), 0.001 );

		self::$rate = 2.0; // The rate moves before the cancel.
		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 100, $this->balance(), 0.001, 'Exactly the £10 credited is taken back in total.' );
	}

	public function test_m4_full_or_skip_compares_in_one_currency() {
		add_filter(
			'woo_wallet_cashback_clawback_strategy',
			function () {
				return 'full_or_skip';
			}
		);
		$order = $this->order_with_cashback( 100, 13.24, 'USD' ); // Stored as £10.
		$this->set_balance( 12 ); // £12 covers $13.24 (= £10).

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 2, $this->balance(), 0.001, '£12 covers the £10 cashback, so it must be taken back.' );
	}

	public function test_m4_partial_takes_the_whole_available_balance() {
		$order = $this->order_with_cashback( 100, 13.24, 'USD' ); // Stored as £10.
		$this->set_balance( 9 ); // Only £9 left (= $11.92).

		woo_wallet()->wallet->process_cancelled_order( $order->get_id() );

		$this->assertEqualsWithDelta( 0, $this->balance(), 0.001, 'All £9 available must be taken back.' );
	}
}
