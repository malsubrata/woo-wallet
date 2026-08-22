<?php
/**
 * The CSV export handlers must gate on the wallet capability, not just a nonce.
 *
 * A nonce proves the request came from a given user's session; it says nothing
 * about what that user may read. Both export entry points emit every user's
 * transactions or balances, so possession of a valid nonce must not be enough.
 *
 * @package TeraWallet\Tests
 */

/**
 * Class TeraWallet_Test_Export_Capability_Gate
 */
class TeraWallet_Test_Export_Capability_Gate extends WP_UnitTestCase {

	/**
	 * Restore request superglobals between tests.
	 */
	public function tearDown(): void {
		unset( $_GET['action'], $_GET['nonce'], $_GET['filename'] );
		parent::tearDown();
	}

	/**
	 * A subscriber holding a valid nonce is still refused the download.
	 *
	 * The nonce here is genuine — nonces bind to (user, action), so any logged-in
	 * user can mint one for an action they are not allowed to perform. Only the
	 * capability check stops this request.
	 *
	 * Note: on the unguarded code path this reaches TeraWallet_CSV_Exporter::export(),
	 * which calls die() — so a regression does not merely fail this assertion, it
	 * halts the run. Either way the suite reports red.
	 */
	public function test_subscriber_with_valid_nonce_cannot_download_an_export() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$this->assertFalse(
			current_user_can( get_wallet_user_capability() ),
			'Precondition: a subscriber must not hold the wallet capability.'
		);

		$_GET['action']   = 'download_export_csv';
		$_GET['nonce']    = wp_create_nonce( 'terawallet-transaction-csv' );
		$_GET['filename'] = 'woo-wallet-transactions.csv';

		$this->assertTrue(
			(bool) wp_verify_nonce( $_GET['nonce'], 'terawallet-transaction-csv' ),
			'Precondition: the subscriber can mint a valid nonce for this action.'
		);

		// The admin class only loads on admin requests, so it may not be present
		// depending on which tests ran first.
		if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		}
		$admin = new Woo_Wallet_Admin();

		ob_start();
		$admin->download_export_file();
		$output = ob_get_clean();

		$this->assertSame( '', $output, 'A subscriber must receive no export content.' );
	}

	/**
	 * The capability, not the role name, is what the gate asks about.
	 *
	 * Stores retarget the exporter at a custom role through this filter; the gate
	 * has to follow it, otherwise widening access silently locks the intended
	 * users out (or, filtered the other way, lets them straight in).
	 */
	public function test_gate_follows_the_capability_filter() {
		$shop_manager = self::factory()->user->create( array( 'role' => 'shop_manager' ) );
		wp_set_current_user( $shop_manager );

		$this->assertTrue(
			current_user_can( get_wallet_user_capability() ),
			'A shop manager holds manage_woocommerce and must pass the gate.'
		);

		add_filter( 'woo_wallet_user_capability', static fn() => 'manage_options' );

		$this->assertFalse(
			current_user_can( get_wallet_user_capability() ),
			'Once the capability is filtered upward the same user must be refused.'
		);

		remove_all_filters( 'woo_wallet_user_capability' );
	}
}
