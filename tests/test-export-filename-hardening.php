<?php
/**
 * The export filename must not be attacker- or client-predictable.
 *
 * The download URL is protected by a nonce + capability check, but on hosts
 * where .htaccess directives are ignored (nginx, or Apache 2.4 without
 * mod_access_compat) the file sitting in uploads/woo-wallet-exports/ is only
 * as safe as its name is hard to guess. Step 1 of the AJAX export must mint
 * that name server-side, ignoring whatever the client sent.
 *
 * @package TeraWallet\Tests
 */

/**
 * Class TeraWallet_Test_Export_Filename_Hardening
 */
class TeraWallet_Test_Export_Filename_Hardening extends WP_Ajax_UnitTestCase {

	/**
	 * An admin with the wallet capability.
	 *
	 * @var int
	 */
	protected $admin_id;

	/**
	 * Create an authorized user and load the AJAX handler class.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->admin_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $this->admin_id );

		if ( ! class_exists( 'Woo_Wallet_Ajax' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php';
		}
		new Woo_Wallet_Ajax();
	}

	/**
	 * Restore request superglobals between tests.
	 */
	public function tearDown(): void {
		unset( $_POST['filename'], $_POST['step'], $_POST['export_type'] );
		parent::tearDown();
	}

	/**
	 * Run the AJAX action and return the decoded JSON response.
	 *
	 * @return array
	 */
	protected function dispatch() {
		try {
			$this->_handleAjax( 'terawallet_do_ajax_transaction_export' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// wp_send_json_success() always dies; the response is already captured.
		}

		return json_decode( (string) $this->_last_response, true );
	}

	/**
	 * Step 1 discards the client-supplied filename and mints its own token.
	 */
	public function test_step_one_ignores_client_supplied_filename() {
		$_POST['step']        = 1;
		$_POST['export_type'] = 'transactions';
		$_POST['filename']    = 'terawallet-transaction-export-11-9-2026-1000.csv';
		$_POST['security']    = wp_create_nonce( 'terawallet-exporter-script' );

		$response = $this->dispatch();

		$this->assertTrue( $response['success'] );
		$this->assertNotSame(
			$_POST['filename'],
			$response['data']['filename'],
			'The server must not trust a client-chosen export filename.'
		);
		$this->assertMatchesRegularExpression(
			'/^terawallet-export-[A-Za-z0-9]{20}\.csv$/',
			$response['data']['filename'],
			'The minted filename must carry a 20-character random token.'
		);
	}

	/**
	 * Two exports started moments apart do not collide on the same filename.
	 */
	public function test_successive_exports_get_different_filenames() {
		$_POST['step']        = 1;
		$_POST['export_type'] = 'transactions';
		$_POST['security']    = wp_create_nonce( 'terawallet-exporter-script' );

		$first  = $this->dispatch();
		$second = $this->dispatch();

		$this->assertNotSame( $first['data']['filename'], $second['data']['filename'] );
	}
}
