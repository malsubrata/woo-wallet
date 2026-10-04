<?php
/**
 * Edit Balance modal: the "current balance" it loads is in the store base
 * currency, not the admin's storefront (switcher) currency.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Ajax::edit_wallet_balance_template_data
 */
class Test_Edit_Balance_Modal_Currency extends WP_Ajax_UnitTestCase {

	/**
	 * Set up an admin, base GBP, and a provider whose active currency is USD.
	 */
	public function setUp(): void {
		parent::setUp();
		update_option( 'woocommerce_currency', 'GBP' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		if ( ! class_exists( 'Woo_Wallet_Ajax' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php';
		}
		new Woo_Wallet_Ajax();
		Woo_Wallet_Currency_Manager::instance()->register_provider(
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
					return 'USD';
				}
			},
			1
		);
	}

	/**
	 * Remove the fake provider.
	 */
	public function tearDown(): void {
		Woo_Wallet_Currency_Manager::instance()->unregister_provider( 'test-fx' );
		unset( $_REQUEST['user_id'], $_REQUEST['security'], $_POST['user_id'], $_POST['security'] );
		parent::tearDown();
	}

	/**
	 * Balance is formatted in GBP even though the active currency is USD.
	 */
	public function test_modal_balance_uses_base_currency() {
		$user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		woo_wallet()->wallet->credit( $user_id, 50, 'seed', array( 'currency' => 'GBP' ) );

		$_POST['user_id']  = $user_id;
		$_POST['security'] = wp_create_nonce( 'woo-wallet-edit-balance-template-data' );
		$_REQUEST          = array_merge( $_REQUEST, $_POST );
		try {
			$this->_handleAjax( 'get_edit_wallet_balance_template_data' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// wp_send_json() always dies; the response is already captured.
		}
		$response = json_decode( (string) $this->_last_response, true );

		$html = html_entity_decode( wp_strip_all_tags( $response['data']['current_balance'] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$this->assertSame( '£50.00', $html );
	}
}
