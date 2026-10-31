<?php
/**
 * OPT batch (1.7.4): cleared gateway list, uninstall options, export i18n key.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_topup_allowed_gateways
 */
class Test_Opt_Batch extends WP_UnitTestCase {

	/**
	 * A cleared list stays empty, in either stored shape.
	 *
	 * @dataProvider cleared_values
	 *
	 * @param mixed $value Stored value.
	 */
	public function test_cleared_gateway_list_stays_empty( $value ) {
		$this->enable_gateway();
		update_option( '_wallet_settings_general', array( 'allowed_payment_gateways' => $value ) );
		$this->assertSame( array(), WOO_Wallet_Helper::get_topup_allowed_gateways() );
	}

	/**
	 * Stored shapes of a cleared list.
	 *
	 * @return array
	 */
	public function cleared_values() {
		return array(
			'empty array'  => array( array() ),
			'empty string' => array( '' ),
		);
	}

	/**
	 * Never saved: still the default, also when other settings were saved.
	 */
	public function test_never_saved_gateway_list_keeps_default() {
		$this->enable_gateway();
		delete_option( '_wallet_settings_general' );
		$this->assertContains( 'tw_opt', WOO_Wallet_Helper::get_topup_allowed_gateways() );

		update_option( '_wallet_settings_general', array( 'min_topup_amount' => 5 ) );
		$this->assertContains( 'tw_opt', WOO_Wallet_Helper::get_topup_allowed_gateways() );
	}

	/**
	 * Saved list and the option filter behave as before.
	 */
	public function test_saved_list_and_filter_still_apply() {
		update_option( '_wallet_settings_general', array( 'allowed_payment_gateways' => array( 'tw_opt', 'wallet' ) ) );
		$this->assertSame( array( 'tw_opt' ), WOO_Wallet_Helper::get_topup_allowed_gateways() );

		update_option( '_wallet_settings_general', array( 'allowed_payment_gateways' => array() ) );
		$filter = function () {
			return array( 'tw_filtered' );
		};
		add_filter( 'woo_wallet_get_option__wallet_settings_general_allowed_payment_gateways', $filter );
		$this->assertSame( array( 'tw_filtered' ), WOO_Wallet_Helper::get_topup_allowed_gateways() );
		remove_filter( 'woo_wallet_get_option__wallet_settings_general_allowed_payment_gateways', $filter );
	}

	/**
	 * The option cleanup in uninstall.php removes ours and keeps Pro's. Runs only
	 * its DELETE FROM options statements: the file itself would drop the tables.
	 */
	public function test_uninstall_option_cleanup() {
		global $wpdb;
		update_option( 'woo_wallet_db_version', '1.7.4' );
		update_option( 'woo_wallet_optin_done', 1 );
		update_option( 'woo_wallet_pending_topup_description_cleanup', 1 );
		update_option( 'woo_wallet_pro_credit_expire_db_version', '1' );
		update_option( 'woocommerce_currency_keep', 'x' );

		$source = (string) file_get_contents( WOO_WALLET_ABSPATH . 'uninstall.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		preg_match_all( '/\$wpdb->query\( "(DELETE FROM \$wpdb->options [^"]*)"/', $source, $matches );
		$this->assertNotEmpty( $matches[1] );
		foreach ( $matches[1] as $sql ) {
			$wpdb->query( str_replace( '$wpdb->options', $wpdb->options, $sql ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		}
		wp_cache_flush();

		$this->assertFalse( get_option( 'woo_wallet_db_version' ) );
		$this->assertFalse( get_option( 'woo_wallet_optin_done' ) );
		$this->assertFalse( get_option( 'woo_wallet_pending_topup_description_cleanup' ) );
		$this->assertSame( '1', get_option( 'woo_wallet_pro_credit_expire_db_version' ) );
		$this->assertSame( 'x', get_option( 'woocommerce_currency_keep' ) );
		$this->assertStringContainsString( "delete_option( 'woocommerce_wallet_settings' )", $source );
	}

	/**
	 * Export search reads the correct key, and PHP localizes it and the old one.
	 */
	public function test_export_uses_correct_i18n_key() {
		$js = (string) file_get_contents( WOO_WALLET_ABSPATH . 'src/admin/export.js' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( 'i18n.no_result', $js );
		$this->assertStringNotContainsString( 'no_resualt', $js );

		$php = (string) file_get_contents( WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$this->assertStringContainsString( "'no_result'", $php );
		$this->assertStringContainsString( "'no_resualt'", $php );
	}

	/**
	 * Register an enabled online gateway so the default list is not empty.
	 */
	private function enable_gateway() {
		$gateway          = new stdClass();
		$gateway->id      = 'tw_opt';
		$gateway->enabled = 'yes';

		WC()->payment_gateways()->payment_gateways['tw_opt'] = $gateway;
	}

	/**
	 * Drop the fake gateway.
	 */
	public function tear_down() {
		unset( WC()->payment_gateways()->payment_gateways['tw_opt'] );
		parent::tear_down();
	}
}
