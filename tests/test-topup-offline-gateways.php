<?php
/**
 * SEC-2: top-up gateways and credit timing.
 *
 * Part A: offline gateways are not in the default "Allowed Payment Gateways"
 * for top-ups (saved settings are respected).
 * Part B: cash-on-delivery top-ups are credited only on Completed.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_topup_offline_gateways
 * @covers Woo_Wallet_Frontend::woocommerce_available_payment_gateways
 * @covers Woo_Wallet_Wallet::wallet_credit_purchase
 */
class Test_Topup_Offline_Gateways extends WP_UnitTestCase {

	const NOTE = 'Wallet top-up not credited yet: cash on delivery.';

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Gateway list before the test, restored in tear_down.
	 *
	 * @var array
	 */
	private $saved_gateways;

	/**
	 * Enable bacs, cheque, cod and a stub online gateway; fresh customer.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id        = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->saved_gateways = WC()->payment_gateways()->payment_gateways;

		$online             = new class() extends WC_Payment_Gateway {
			/**
			 * Stub online gateway.
			 */
			public function __construct() {
				$this->id      = 'tw_online';
				$this->title   = 'Online';
				$this->enabled = 'yes';
			}
		};
		$gateways           = array();
		$saved              = WC()->payment_gateways()->payment_gateways(); // Keyed by id.
		foreach ( array( 'bacs', 'cheque', 'cod' ) as $id ) {
			$this->assertArrayHasKey( $id, $saved, "WooCommerce core gateway $id missing." );
			$gateways[ $id ]          = clone $saved[ $id ];
			$gateways[ $id ]->enabled = 'yes';
		}
		$gateways['tw_online'] = $online;
		WC()->payment_gateways()->payment_gateways = $gateways;
		delete_option( '_wallet_settings_general' );
	}

	/**
	 * Restore gateways and filters.
	 */
	public function tear_down() {
		WC()->payment_gateways()->payment_gateways = $this->saved_gateways;
		remove_all_filters( 'woo_wallet_is_wallet_rechargeable_cart' );
		remove_all_filters( 'woo_wallet_topup_offline_gateways' );
		remove_all_filters( 'woo_wallet_topup_credit_on_completed_gateways' );
		parent::tear_down();
	}

	/**
	 * Run the checkout gateway filter.
	 *
	 * @param bool $topup Whether the cart is a top-up cart.
	 * @return array Offered gateway ids.
	 */
	private function offered( $topup = true ) {
		add_filter( 'woo_wallet_is_wallet_rechargeable_cart', $topup ? '__return_true' : '__return_false' );
		$offered = Woo_Wallet_Frontend::instance()->woocommerce_available_payment_gateways( WC()->payment_gateways()->payment_gateways() );
		remove_all_filters( 'woo_wallet_is_wallet_rechargeable_cart' );
		return array_keys( $offered );
	}

	/**
	 * Build a top-up order paid with the given gateway.
	 *
	 * @param string $gateway Gateway id.
	 * @return WC_Order
	 */
	private function topup_order( $gateway ) {
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
		$this->assertTrue( is_wallet_rechargeable_order( $order ) );
		return $order;
	}

	/**
	 * Move the order to a status (fires the credit hooks).
	 *
	 * @param WC_Order $order  Order.
	 * @param string   $status Status.
	 */
	private function set_status( $order, $status ) {
		$order = wc_get_order( $order->get_id() );
		$order->update_status( $status );
	}

	/**
	 * Wallet balance.
	 *
	 * @return float
	 */
	private function balance() {
		return (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
	}

	/**
	 * Ledger rows for the customer.
	 *
	 * @return int
	 */
	private function rows() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d AND deleted = 0", $this->user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	/**
	 * Awaiting-completion notes on the order.
	 *
	 * @param WC_Order $order Order.
	 * @return int
	 */
	private function notes( $order ) {
		$count = 0;
		foreach ( wc_get_order_notes( array( 'order_id' => $order->get_id() ) ) as $note ) {
			if ( 0 === strpos( $note->content, self::NOTE ) ) {
				$this->assertFalse( (bool) $note->customer_note, 'Note must be private.' );
				++$count;
			}
		}
		return $count;
	}

	// Part A.

	/**
	 * Never-saved setting: only the online gateway is offered for a top-up.
	 */
	public function test_unsaved_setting_offers_only_online_gateway() {
		$this->assertSame( array( 'tw_online' ), $this->offered() );
	}

	/**
	 * The settings field lists every gateway but selects only online ones.
	 */
	public function test_settings_field_default_excludes_offline() {
		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php';
		$settings = new Woo_Wallet_Settings( woo_wallet()->settings_api );
		$field    = wp_list_filter( $settings->get_settings_fields()['_wallet_settings_general'], array( 'name' => 'allowed_payment_gateways' ) );
		$field    = reset( $field );

		$this->assertEqualsCanonicalizing( array( 'bacs', 'cheque', 'cod', 'tw_online' ), array_keys( $field['options'] ) );
		$this->assertSame( array( 'tw_online' ), $field['default'] );
	}

	/**
	 * A saved list that includes cod is respected.
	 */
	public function test_saved_setting_with_cod_is_respected() {
		update_option( '_wallet_settings_general', array( 'allowed_payment_gateways' => array( 'cod', 'tw_online' ) ) );
		$this->assertEqualsCanonicalizing( array( 'cod', 'tw_online' ), $this->offered() );
	}

	/**
	 * A custom id added through the filter is left out of the default.
	 */
	public function test_filter_adds_custom_offline_gateway() {
		add_filter(
			'woo_wallet_topup_offline_gateways',
			static function ( $ids ) {
				$ids[] = 'tw_online';
				return $ids;
			}
		);
		$this->assertContains( 'tw_online', WOO_Wallet_Helper::get_topup_offline_gateways() );
		$this->assertSame( array(), $this->offered() );
	}

	/**
	 * A normal checkout still offers every gateway.
	 */
	public function test_normal_checkout_offers_all_gateways() {
		$this->assertEqualsCanonicalizing( array( 'bacs', 'cheque', 'cod', 'tw_online' ), $this->offered( false ) );
	}

	// Part B.

	/**
	 * COD at Processing: nothing credited, one note even after repeated saves;
	 * Completed credits exactly once.
	 */
	public function test_cod_credited_only_on_completed_and_once() {
		$order = $this->topup_order( 'cod' );

		$this->set_status( $order, 'processing' );
		$this->assertSame( 0, $this->rows() );
		$this->assertSame( 1, $this->notes( $order ) );

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );
		$this->assertSame( 0, $this->rows() );
		$this->assertSame( 1, $this->notes( $order ) );

		$this->set_status( $order, 'completed' );
		$this->assertSame( 1, $this->rows() );
		$this->assertEqualsWithDelta( 250.0, $this->balance(), 0.01 );

		woo_wallet()->wallet->wallet_credit_purchase( $order->get_id() );
		$this->assertSame( 1, $this->rows() );
	}

	/**
	 * Online gateway top-up is still credited at Processing.
	 */
	public function test_online_gateway_credited_at_processing() {
		$this->set_status( $this->topup_order( 'tw_online' ), 'processing' );
		$this->assertSame( 1, $this->rows() );
		$this->assertEqualsWithDelta( 250.0, $this->balance(), 0.01 );
	}

	/**
	 * The filter can add bacs to the wait-for-Completed list.
	 */
	public function test_filter_adds_bacs() {
		add_filter(
			'woo_wallet_topup_credit_on_completed_gateways',
			static function ( $ids ) {
				$ids[] = 'bacs';
				return $ids;
			}
		);
		$this->set_status( $this->topup_order( 'bacs' ), 'processing' );
		$this->assertSame( 0, $this->rows() );
	}

	/**
	 * An empty filter list restores crediting COD at Processing.
	 */
	public function test_empty_filter_credits_cod_at_processing() {
		add_filter( 'woo_wallet_topup_credit_on_completed_gateways', '__return_empty_array' );
		$this->set_status( $this->topup_order( 'cod' ), 'processing' );
		$this->assertSame( 1, $this->rows() );
	}

	/**
	 * Cancelling a COD top-up that was never credited debits nothing.
	 */
	public function test_cancel_uncredited_cod_topup_debits_nothing() {
		$order = $this->topup_order( 'cod' );
		$this->set_status( $order, 'processing' );
		$this->set_status( $order, 'cancelled' );

		$this->assertSame( 0, $this->rows() );
		$this->assertEqualsWithDelta( 0.0, $this->balance(), 0.01 );
	}
}
