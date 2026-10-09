<?php
/**
 * F12 / HF-1: the top-up product's admin-only "do not delete" text is removed
 * from the product description, which block cart/checkout shows to customers.
 *
 * Since 1.7.3 the 1.7.2 migration only sets a flag (saving a post during
 * plugins_loaded fataled with plugins that build permalinks on save); the
 * cleanup itself runs once on admin_init.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers ::woo_wallet_update_172_clear_topup_product_description
 * @covers ::woo_wallet_maybe_clear_topup_product_description
 */
class Test_Topup_Product_Description extends WP_UnitTestCase {

	const OLD_TEXT = 'Auto generated product for wallet recharge please do not delete or update.';
	const FLAG     = 'woo_wallet_pending_topup_description_cleanup';

	/**
	 * The drain runs for store managers; act as an administrator.
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * Point the top-up option at a fresh product with the given description.
	 *
	 * @param string $content Description.
	 * @return int Product id.
	 */
	private function topup_product( $content ) {
		$id = self::factory()->post->create(
			array(
				'post_type'    => 'product',
				'post_status'  => 'private',
				'post_content' => $content,
			)
		);
		update_option( '_woo_wallet_recharge_product', $id );
		return $id;
	}

	/**
	 * The 1.7.2 migration only sets the flag: no post is saved.
	 */
	public function test_migration_sets_flag_without_saving_post() {
		$id           = $this->topup_product( self::OLD_TEXT );
		$post_updated = did_action( 'post_updated' );
		$save_post    = did_action( 'save_post' );

		woo_wallet_update_172_clear_topup_product_description();

		$this->assertSame( $post_updated, did_action( 'post_updated' ) );
		$this->assertSame( $save_post, did_action( 'save_post' ) );
		$this->assertSame( self::OLD_TEXT, get_post( $id )->post_content );
		$this->assertEquals( 1, get_option( self::FLAG ) );
	}

	/**
	 * Regression: update() from 1.7.1 survives a save hook that fatals because
	 * $wp_rewrite does not exist yet, and finishes on the current db version.
	 */
	public function test_update_from_171_survives_fatal_save_hook() {
		global $wp_rewrite;
		$this->topup_product( self::OLD_TEXT );
		update_option( 'woo_wallet_db_version', '1.7.1' );
		$throw = static function () {
			throw new Error( 'get_page_permastruct() on null' );
		};
		add_action( 'post_updated', $throw );
		$saved_rewrite = $wp_rewrite;
		$wp_rewrite    = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		try {
			Woo_Wallet_Install::update();
		} finally {
			$wp_rewrite = $saved_rewrite; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			remove_action( 'post_updated', $throw );
		}

		$this->assertSame( WOO_WALLET_PLUGIN_VERSION, get_option( 'woo_wallet_db_version' ) );
		$this->assertEquals( 1, get_option( self::FLAG ) );
	}

	/**
	 * The drain clears the original auto-generated text and deletes the flag.
	 */
	public function test_drain_clears_default_text() {
		$id = $this->topup_product( self::OLD_TEXT );
		update_option( self::FLAG, 1, false );

		woo_wallet_maybe_clear_topup_product_description();

		$this->assertSame( '', get_post( $id )->post_content );
		$this->assertFalse( get_option( self::FLAG ) );
	}

	/**
	 * A description the store wrote itself is kept; the flag is still deleted.
	 */
	public function test_drain_keeps_custom_description() {
		$id = $this->topup_product( 'Add money to your wallet.' );
		update_option( self::FLAG, 1, false );

		woo_wallet_maybe_clear_topup_product_description();

		$this->assertSame( 'Add money to your wallet.', get_post( $id )->post_content );
		$this->assertFalse( get_option( self::FLAG ) );
	}

	/**
	 * A throwing save hook does not escape, and the flag is gone so it is
	 * tried only once.
	 */
	public function test_drain_swallows_throwing_save_hook() {
		$this->topup_product( self::OLD_TEXT );
		update_option( self::FLAG, 1, false );
		$throw = static function () {
			throw new Error( 'third-party save hook failed' );
		};
		add_action( 'post_updated', $throw );

		try {
			woo_wallet_maybe_clear_topup_product_description();
		} finally {
			remove_action( 'post_updated', $throw );
		}

		$this->assertFalse( get_option( self::FLAG ) );
	}

	/**
	 * A request without a store manager (e.g. logged-out admin-post.php) leaves
	 * the flag for the next admin page load.
	 */
	public function test_drain_waits_for_store_manager() {
		$id = $this->topup_product( self::OLD_TEXT );
		update_option( self::FLAG, 1, false );
		wp_set_current_user( 0 );

		woo_wallet_maybe_clear_topup_product_description();

		$this->assertSame( self::OLD_TEXT, get_post( $id )->post_content );
		$this->assertEquals( 1, get_option( self::FLAG ) );
	}

	/**
	 * Without the flag the drain does nothing (a second call is a no-op).
	 */
	public function test_drain_runs_only_once() {
		$id = $this->topup_product( self::OLD_TEXT );
		update_option( self::FLAG, 1, false );
		woo_wallet_maybe_clear_topup_product_description();

		wp_update_post(
			array(
				'ID'           => $id,
				'post_content' => self::OLD_TEXT,
			)
		);
		$post_updated = did_action( 'post_updated' );
		woo_wallet_maybe_clear_topup_product_description();

		$this->assertSame( $post_updated, did_action( 'post_updated' ) );
		$this->assertSame( self::OLD_TEXT, get_post( $id )->post_content );
	}
}
