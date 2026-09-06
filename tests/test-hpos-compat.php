<?php
/**
 * HPOS (custom_order_tables) compatibility declaration tests.
 *
 * `woo-wallet.php` declares compatibility with WooCommerce's
 * `custom_order_tables` feature on `before_woocommerce_init`, guarded by
 * `class_exists( FeaturesUtil::class )`. These tests confirm the declaration
 * is actually registered against the live FeaturesController (not just that
 * the code runs without error), and that order-meta CRUD through
 * `WOO_Wallet_Helper::update_order_meta_data()` round-trips regardless of
 * which order storage backend WooCommerce is using.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers ::before_woocommerce_init
 * @covers WOO_Wallet_Helper::update_order_meta_data
 */
class Test_Hpos_Compat extends WP_UnitTestCase {

	/**
	 * Customer id.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Fresh customer per test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	/**
	 * The plugin must appear on the `custom_order_tables` compatible list,
	 * not merely absent from the incompatible one.
	 */
	public function test_declares_custom_order_tables_compatibility() {
		if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class )
			|| ! method_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class, 'get_compatible_plugins_for_feature' )
		) {
			$this->markTestSkipped( 'Installed WooCommerce does not expose FeaturesUtil::get_compatible_plugins_for_feature().' );
		}

		$plugin_basename = plugin_basename( WOO_WALLET_PLUGIN_FILE );
		$info            = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature( 'custom_order_tables' );

		$this->assertIsArray( $info );
		$this->assertArrayHasKey( 'compatible', $info );
		$this->assertContains(
			$plugin_basename,
			$info['compatible'],
			'woo-wallet/woo-wallet.php must be declared compatible with custom_order_tables — a reverted before_woocommerce_init hook would drop it from this list entirely.'
		);
		$this->assertArrayHasKey( 'incompatible', $info );
		$this->assertNotContains( $plugin_basename, $info['incompatible'] );
	}

	/**
	 * Order meta written via the shared helper reads back through the
	 * ordinary WC_Order CRUD API — the round-trip that must hold under
	 * either the posts table or the custom orders table.
	 */
	public function test_order_meta_round_trips_through_wc_order_crud() {
		$order = new WC_Order();
		$order->set_customer_id( $this->user_id );
		$order->set_currency( get_woocommerce_currency() );
		$order->save();

		WOO_Wallet_Helper::update_order_meta_data( $order, '_woo_wallet_hpos_probe', 'sentinel-value' );

		// Force a completely fresh read from the active order data store,
		// bypassing any in-request object identity.
		$fresh = wc_get_order( $order->get_id() );

		$this->assertNotFalse( $fresh );
		$this->assertSame( 'sentinel-value', $fresh->get_meta( '_woo_wallet_hpos_probe' ) );
	}

	/**
	 * Whether HPOS can be forced on inside this harness at all.
	 *
	 * Toggling `custom_orders_table_usage_is_enabled()` to true requires the
	 * custom orders table to exist and the feature to be marked authoritative
	 * through `CustomOrdersTableController`/`DataSynchronizer` — WooCommerce's
	 * own test suite carries helpers for this (e.g. `OrderHelper::toggle_cot`
	 * style fixtures), but that test scaffolding is not part of the
	 * `wp-phpunit`/WooCommerce runtime installed for this plugin's harness
	 * (no `woocommerce/tests` directory ships in this checkout — verified via
	 * `find`). Forcing the option on without running the accompanying schema
	 * setup would not exercise the real HPOS code path, it would just assert
	 * against a flag, so no such test is written. If WooCommerce's dev test
	 * scaffolding is added as a dependency later, add
	 * `test_topup_order_found_by_get_wallet_rechargeable_orders_with_hpos_enabled()`
	 * here using its official toggle, not a raw option write.
	 */
	public function test_hpos_toggle_is_not_available_in_this_harness() {
		$this->markTestSkipped( 'No official WooCommerce HPOS-toggle test helper is available in this harness; see docblock. Not faking it with a raw option write.' );
	}
}
