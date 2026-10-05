<?php
/**
 * Single "Edit Balance" adjustment (Wallet Users → Adjust Balance):
 * - rows are stored with category 'adjustment' (bulk credit/debit already is);
 * - the insufficient-balance check compares in the currency the debit is
 *   written in (base), not the admin's storefront currency.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Admin::handle_wallet_balance_adjustment
 */
class Test_Admin_Adjust_Balance extends WP_UnitTestCase {

	/**
	 * @var int
	 */
	private $customer;

	/**
	 * Base GBP; storefront currency USD at 1 GBP = 1.324 USD.
	 */
	public function set_up() {
		parent::set_up();
		update_option( 'woocommerce_currency', 'GBP' );
		if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->customer = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $this->customer, 28, 'seed', array( 'currency' => 'GBP' ) );

		Woo_Wallet_Currency_Manager::instance()->register_provider(
			new class() extends Woo_Wallet_Abstract_Currency_Provider {
				public function get_id() {
					return 'test-fx-adjust';
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
				public function convert( $amount, $from, $to ) {
					if ( $from === $to ) {
						return (float) $amount;
					}
					return 'GBP' === $from ? (float) $amount * 1.324 : (float) $amount / 1.324;
				}
			},
			1
		);
		// What the multicurrency integration does on a live switcher store:
		// a balance asked for in USD comes back converted to USD.
		add_filter( 'woo_wallet_current_balance', array( $this, 'balance_in_usd' ), 10, 3 );
		$GLOBALS['wp_settings_errors'] = array();
	}

	/**
	 * Remove fakes and request state.
	 */
	public function tear_down() {
		remove_filter( 'woo_wallet_current_balance', array( $this, 'balance_in_usd' ), 10 );
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx-adjust' );
		$_POST = array();
		parent::tear_down();
	}

	/**
	 * Fake conversion filter.
	 *
	 * @param float  $balance  Balance.
	 * @param int    $user_id  User.
	 * @param string $currency Requested currency.
	 * @return float
	 */
	public function balance_in_usd( $balance, $user_id, $currency = '' ) {
		return 'USD' === strtoupper( (string) $currency ) ? (float) $balance * 1.324 : (float) $balance;
	}

	/**
	 * Submit the Adjust Balance form.
	 *
	 * @param string $type   credit|debit.
	 * @param float  $amount Amount.
	 * @return array Last settings error (the response notice).
	 */
	private function submit( $type, $amount ) {
		$_POST = array(
			'woo-wallet-admin-adjust-balance' => wp_create_nonce( 'woo-wallet-admin-adjust-balance' ),
			'user_id'                         => $this->customer,
			'balance_amount'                  => (string) $amount,
			'payment_type'                    => $type,
			'payment_description'             => 'test',
		);
		Woo_Wallet_Admin::instance()->handle_wallet_balance_adjustment();
		$errors = get_settings_errors();
		return end( $errors );
	}

	/**
	 * Category of the newest ledger row for the customer.
	 *
	 * @return string
	 */
	private function last_category() {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT category FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d ORDER BY transaction_id DESC LIMIT 1", $this->customer ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Credit and debit are both stored as 'adjustment'.
	 */
	public function test_adjustments_use_adjustment_category() {
		$this->assertSame( 'success', $this->submit( 'credit', 5 )['type'] );
		$this->assertSame( 'adjustment', $this->last_category() );

		$this->assertSame( 'success', $this->submit( 'debit', 3 )['type'] );
		$this->assertSame( 'adjustment', $this->last_category() );
	}

	/**
	 * £28 balance reads as $37.07 in USD; a £30 debit must still be refused
	 * as insufficient balance (not slip past the check), and write nothing.
	 */
	public function test_debit_check_uses_base_currency() {
		$response = $this->submit( 'debit', 30 );
		$this->assertSame( 'error', $response['type'] );
		$this->assertStringContainsString( 'insufficient balance', $response['message'] );
		$this->assertEquals( 28.0, woo_wallet()->wallet->get_wallet_balance( $this->customer, 'edit', 'GBP' ) );
	}
}
