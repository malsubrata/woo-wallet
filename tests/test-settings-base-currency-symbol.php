<?php
/**
 * Settings amount-field prefixes always use the store base currency.
 *
 * Multi-currency switchers hook `woocommerce_currency_symbol` (and sometimes
 * `woocommerce_currency`) to return the storefront's active currency, which is
 * wrong for settings amounts that are stored and used in the base currency.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_base_currency_symbol
 * @covers Woo_Wallet_Settings::get_settings_fields
 */
class Test_Settings_Base_Currency_Symbol extends WP_UnitTestCase {

	/**
	 * Base INR, active EUR switcher filters.
	 */
	public function set_up() {
		parent::set_up();
		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php';
		update_option( 'woocommerce_currency', 'INR' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter( 'woocommerce_currency_symbol', array( $this, 'euro_symbol' ), 99 );
		add_filter( 'woocommerce_currency', array( $this, 'euro_code' ), 99 );
	}

	/**
	 * Remove switcher filters.
	 */
	public function tear_down() {
		remove_filter( 'woocommerce_currency_symbol', array( $this, 'euro_symbol' ), 99 );
		remove_filter( 'woocommerce_currency', array( $this, 'euro_code' ), 99 );
		parent::tear_down();
	}

	/**
	 * Fake switcher symbol.
	 *
	 * @return string
	 */
	public function euro_symbol() {
		return '€';
	}

	/**
	 * Fake switcher code.
	 *
	 * @return string
	 */
	public function euro_code() {
		return 'EUR';
	}

	/**
	 * Collect every prefix in a field tree.
	 *
	 * @param array $data Schema.
	 * @return string[]
	 */
	private function prefixes( array $data ): array {
		$found = array();
		array_walk_recursive(
			$data,
			static function ( $v, $k ) use ( &$found ) {
				if ( 'prefix' === $k ) {
					$found[] = $v;
				}
			}
		);
		return $found;
	}

	/**
	 * Helper ignores both switcher filters.
	 */
	public function test_helper_ignores_switcher_filters() {
		$this->assertSame( '₹', WOO_Wallet_Helper::get_base_currency_symbol() );
		$this->assertSame( '₹ INR', WOO_Wallet_Helper::get_base_currency_symbol( true ) );
	}

	/**
	 * Unknown code falls back to the code itself, without a duplicated suffix.
	 */
	public function test_helper_unknown_code_falls_back_to_code() {
		update_option( 'woocommerce_currency', 'ZZZ' );
		$this->assertSame( 'ZZZ', WOO_Wallet_Helper::get_base_currency_symbol() );
		$this->assertSame( 'ZZZ', WOO_Wallet_Helper::get_base_currency_symbol( true ) );
	}

	/**
	 * General, Credit and Actions schema prefixes show the base symbol.
	 */
	public function test_schema_prefixes_use_base_currency() {
		$settings = new Woo_Wallet_Settings( woo_wallet()->settings_api );
		$fields   = $settings->get_settings_fields();
		foreach ( array( '_wallet_settings_general', '_wallet_settings_credit' ) as $tab ) {
			$p = $this->prefixes( $fields[ $tab ] );
			$this->assertNotEmpty( $p, $tab );
			$this->assertSame( array( '₹ INR' ), array_values( array_unique( $p ) ), $tab );
		}
		$p = $this->prefixes( $settings->get_actions_settings_fields() );
		$this->assertNotEmpty( $p, 'actions' );
		$this->assertSame( array( '₹ INR' ), array_values( array_unique( $p ) ), 'actions' );
	}

	/**
	 * REST response exposes base symbol and code, and field prefixes use it.
	 */
	public function test_rest_settings_uses_base_currency() {
		do_action( 'rest_api_init' );
		$data = rest_do_request( new WP_REST_Request( 'GET', '/terawallet/v1/settings' ) )->get_data();
		$this->assertSame( '₹', $data['context']['currencySymbol'] );
		$this->assertSame( 'INR', $data['context']['baseCurrency'] );
		$p = $this->prefixes( $data['fields'] );
		$this->assertNotEmpty( $p );
		$this->assertSame( array( '₹ INR' ), array_values( array_unique( $p ) ) );
	}

	/**
	 * Admin Edit Balance: the amount is labelled in the base currency, so it must
	 * be stored as typed even when the admin's storefront currency differs.
	 */
	public function test_admin_adjust_balance_amount_is_in_base_currency() {
		$manager = Woo_Wallet_Currency_Manager::instance();
		$manager->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx';
				}
				public function get_label() {
					return 'Test FX';
				}
				public function is_available() {
					return true;
				}
				public function get_active_currency() {
					return 'EUR';
				}
				public function get_rate( $from, $to ) {
					return $from === $to ? 1.0 : 0.01;
				}
			},
			1
		);
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );

		$_POST = array(
			'woo-wallet-admin-adjust-balance' => wp_create_nonce( 'woo-wallet-admin-adjust-balance' ),
			'user_id'                         => $user_id,
			'balance_amount'                  => '100',
			'payment_type'                    => 'credit',
			'payment_description'             => 'test',
		);
		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		( new Woo_Wallet_Admin() )->handle_wallet_balance_adjustment();
		$_POST = array();
		$manager->unregister_provider( 'test-fx' );

		$this->assertEquals( 100.0, woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' ) );
	}
}
