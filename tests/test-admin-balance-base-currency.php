<?php
/**
 * User profile "Wallet Management" balance and the WP Users "Wallet Balance"
 * column are shown in the store base currency, not the admin's storefront
 * (switcher) currency.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Admin::add_wallet_management_fields
 * @covers Woo_Wallet_Admin::manage_users_custom_column
 */
class Test_Admin_Balance_Base_Currency extends WP_UnitTestCase {

	/**
	 * @var Woo_Wallet_Admin
	 */
	private $admin;

	/**
	 * Base GBP; a provider whose active (storefront) currency is USD.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		}
		$this->admin = Woo_Wallet_Admin::instance();
		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-admin-balance';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return 'USD';
				}
			},
			1
		);
	}

	/**
	 * Remove the fake provider.
	 */
	public function tear_down() {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-admin-balance' );
		parent::tear_down();
	}

	/**
	 * Both admin balance outputs carry the GBP symbol, never USD's.
	 */
	public function test_profile_and_users_column_use_base_currency() {
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $user_id, 50, 'seed', array( 'currency' => 'GBP' ) );
		$gbp = get_woocommerce_currency_symbol( 'GBP' );
		$usd = get_woocommerce_currency_symbol( 'USD' );

		ob_start();
		$this->admin->add_wallet_management_fields( get_user_by( 'id', $user_id ) );
		$profile = ob_get_clean();

		$column = $this->admin->manage_users_custom_column( '', 'current_wallet_balance', $user_id );

		foreach ( array( $profile, $column ) as $html ) {
			$this->assertStringContainsString( $gbp, $html );
			$this->assertStringNotContainsString( $usd, $html );
			$this->assertStringContainsString( '50.00', $html );
		}
	}
}
