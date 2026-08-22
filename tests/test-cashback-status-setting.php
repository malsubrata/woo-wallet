<?php
/**
 * Cashback order-status setting shape tests.
 *
 * The `process_cashback_status` field offers `wc_get_order_statuses()` as its
 * options, whose keys are prefixed (`wc-processing`), while the field default,
 * the `woocommerce_order_status_{$status}` hook name and `WC_Order::get_status()`
 * are all unprefixed (`processing`). A store that never opens the settings page
 * runs on the default and works; the first save from the settings UI persists
 * the prefixed shape and cashback silently stops being credited.
 *
 * Both readers of the setting must therefore tolerate either shape, and the
 * public `wallet_cashback_order_status` filter must keep its existing contract
 * so third-party workarounds hooked to it stay harmless.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_cashback_order_statuses
 * @covers Woo_Wallet_Admin::recalculate_order_cashback
 */
class Test_Cashback_Status_Setting extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer and a known cashback configuration per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';

		// The status hooks registered at boot would credit cashback on the
		// transition itself; drop them so the admin recalculation path is
		// observed on its own.
		foreach ( array( 'processing', 'completed' ) as $status ) {
			remove_action( 'woocommerce_order_status_' . $status, array( woo_wallet()->wallet, 'wallet_cashback' ), 12 );
		}
	}

	/**
	 * Drop any filter a test attached to the public hook.
	 */
	public function tear_down() {
		remove_all_filters( 'wallet_cashback_order_status' );
		parent::tear_down();
	}

	/**
	 * Write the credit section exactly as the settings UI would.
	 *
	 * @param array $overrides Field values to merge over the cashback defaults.
	 */
	private function save_credit_settings( array $overrides = array() ) {
		update_option(
			'_wallet_settings_credit',
			array_merge(
				array(
					'is_enable_cashback_reward_program' => 'on',
					'cashback_rule'                     => 'cart',
					'cashback_type'                     => 'fixed',
					'cashback_amount'                   => '5',
					'min_cart_amount'                   => '0',
				),
				$overrides
			)
		);
	}

	/**
	 * Build a simple non-rechargeable order in the given status.
	 *
	 * @param string $status Order status, unprefixed.
	 * @return WC_Order
	 */
	private function make_order( $status = 'processing' ) {
		$product = new WC_Product_Simple();
		$product->set_regular_price( 100 );
		$product->save();

		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );

		$item = new WC_Order_Item_Product();
		$item->set_product_id( $product->get_id() );
		$item->set_quantity( 1 );
		$item->set_subtotal( 100 );
		$item->set_total( 100 );
		$order->add_item( $item );
		$order->set_total( 100 );
		$order->save();
		$order->set_status( $status );
		$order->save();

		return $order;
	}

	/**
	 * Cashback rows credited to the customer.
	 *
	 * @return int
	 */
	private function cashback_row_count() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d AND type = 'credit' AND deleted = 0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Never saved: the field default is used and must be returned untouched.
	 */
	public function test_default_shape_is_returned_unprefixed() {
		delete_option( '_wallet_settings_credit' );

		$this->assertSame(
			array( 'processing', 'completed' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'A store that never saved the setting must keep the documented default.'
		);
	}

	/**
	 * The shape the settings UI actually persists must resolve to real statuses.
	 *
	 * `woocommerce_order_status_wc-processing` is not a hook WooCommerce ever
	 * fires, so leaving the prefix in place is what stops cashback dead.
	 */
	public function test_saved_prefixed_shape_resolves_to_hookable_statuses() {
		$this->save_credit_settings( array( 'process_cashback_status' => array( 'wc-processing', 'wc-completed' ) ) );

		$this->assertSame(
			array( 'processing', 'completed' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'Saved wc- prefixed statuses must be normalised to the hook/get_status() shape.'
		);
	}

	/**
	 * A hand-edited or legacy unprefixed saved value must survive unchanged.
	 */
	public function test_saved_unprefixed_shape_is_left_alone() {
		$this->save_credit_settings( array( 'process_cashback_status' => array( 'processing', 'on-hold' ) ) );

		$this->assertSame(
			array( 'processing', 'on-hold' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'Already-correct values must not be rewritten.'
		);
	}

	/**
	 * A status whose own slug contains "wc-" must not be double-stripped.
	 */
	public function test_only_the_leading_prefix_is_stripped() {
		$this->save_credit_settings( array( 'process_cashback_status' => array( 'wc-wc-shipped', 'awaiting-wc-stock' ) ) );

		$this->assertSame(
			array( 'wc-shipped', 'awaiting-wc-stock' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'Only one leading wc- prefix may be removed, and never mid-string.'
		);
	}

	/**
	 * An empty saved selection means "never credit" and must stay empty.
	 */
	public function test_empty_selection_falls_back_to_default() {
		$this->save_credit_settings( array( 'process_cashback_status' => array() ) );

		$this->assertSame(
			array( 'processing', 'completed' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'An empty saved array is indistinguishable from unset and uses the default.'
		);
	}

	/**
	 * The reporter's production workaround must keep working verbatim.
	 */
	public function test_public_filter_contract_is_preserved() {
		$this->save_credit_settings( array( 'process_cashback_status' => array( 'wc-processing', 'wc-completed' ) ) );

		add_filter(
			'wallet_cashback_order_status',
			function () {
				return array( 'processing', 'completed' );
			}
		);

		$this->assertSame(
			array( 'processing', 'completed' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'A filter returning unprefixed statuses must be honoured unchanged.'
		);
	}

	/**
	 * A third party returning the prefixed shape from the filter is also fixed.
	 */
	public function test_filter_returning_prefixed_statuses_is_normalised() {
		add_filter(
			'wallet_cashback_order_status',
			function () {
				return array( 'wc-completed' );
			}
		);

		$this->assertSame(
			array( 'completed' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'Normalisation must run after the filter, not before it.'
		);
	}

	/**
	 * A filter returning a non-array must not fatal the boot sequence.
	 */
	public function test_filter_returning_non_array_is_survivable() {
		add_filter(
			'wallet_cashback_order_status',
			function () {
				return 'completed';
			}
		);

		$this->assertSame(
			array( 'completed' ),
			array_values( WOO_Wallet_Helper::get_cashback_order_statuses() ),
			'A scalar from a third-party filter must be coerced, not iterated blindly.'
		);
	}

	/**
	 * Second reader: manual "Recalculate cashback" order action.
	 *
	 * With the saved prefixed shape the status comparison never matched and the
	 * recalculation silently returned without writing anything.
	 */
	public function test_recalculate_order_cashback_matches_saved_prefixed_status() {
		$this->save_credit_settings( array( 'process_cashback_status' => array( 'wc-processing', 'wc-completed' ) ) );

		$order = $this->make_order( 'processing' );
		$this->assertSame( 0, $this->cashback_row_count(), 'Precondition: no cashback yet.' );

		Woo_Wallet_Admin::instance()->recalculate_order_cashback( $order );

		$this->assertSame(
			1,
			$this->cashback_row_count(),
			'Recalculation must run for an order whose status was selected in the settings UI.'
		);
	}

	/**
	 * A status that was not selected must still be skipped.
	 */
	public function test_recalculate_order_cashback_skips_unselected_status() {
		$this->save_credit_settings( array( 'process_cashback_status' => array( 'wc-completed' ) ) );

		$order = $this->make_order( 'on-hold' );

		Woo_Wallet_Admin::instance()->recalculate_order_cashback( $order );

		$this->assertSame(
			0,
			$this->cashback_row_count(),
			'Normalisation must not widen the selection to statuses the store did not pick.'
		);
	}
}
