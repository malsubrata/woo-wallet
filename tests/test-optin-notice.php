<?php
/**
 * Email opt-in banner tests.
 *
 * The invariant that matters: this plugin must never transmit anything to
 * standalonetech.com unless an administrator ticked the consent box and
 * submitted the form. Everything here exists to make a regression on that
 * point fail loudly.
 *
 *  - no consent flag        → no HTTP request at all;
 *  - invalid email          → no HTTP request at all;
 *  - missing capability     → no HTTP request at all;
 *  - accepted (2xx)         → exactly one request, expected payload, flag set;
 *  - rejected (5xx/WP_Error)→ flag NOT set, so the banner comes back.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Optin_Notice
 */
class Test_Optin_Notice extends WP_Ajax_UnitTestCase {

	/**
	 * Requests captured by the pre_http_request short-circuit.
	 *
	 * @var array
	 */
	protected $requests = array();

	/**
	 * Canned response returned to wp_remote_post, or a WP_Error.
	 *
	 * @var array|WP_Error
	 */
	protected $canned;

	/**
	 * Instance under test.
	 *
	 * @var Woo_Wallet_Optin_Notice
	 */
	protected $notice;

	/**
	 * Load the class under test and intercept all outbound HTTP.
	 */
	public function set_up() {
		parent::set_up();

		require_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-optin-notice.php';

		// The plugin only loads this file on admin requests, and WP_UnitTestCase
		// restores $wp_filter between tests — so re-register a known-clean
		// instance for every case rather than relying on the file's own bootstrap.
		remove_all_actions( 'wp_ajax_woo_wallet_optin_subscribe' );
		remove_all_actions( 'wp_ajax_woo_wallet_optin_dismiss' );
		$this->notice = new Woo_Wallet_Optin_Notice();

		$this->requests = array();
		$this->canned   = array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'body'     => '{"ok":true}',
		);

		add_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10, 3 );

		delete_option( Woo_Wallet_Optin_Notice::OPT_DONE );
		delete_option( Woo_Wallet_Optin_Notice::OPT_DISMISSED );
	}

	/**
	 * Drop the interceptor.
	 */
	public function tear_down() {
		remove_filter( 'pre_http_request', array( $this, 'intercept_http' ), 10 );
		parent::tear_down();
	}

	/**
	 * Short-circuit every outbound request and record it.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request arguments.
	 * @param string $url     Request URL.
	 * @return array|WP_Error
	 */
	public function intercept_http( $preempt, $args, $url ) {
		// Only the opt-in endpoint. WordPress's own update checks run during an
		// AJAX dispatch and would choke on a canned body meant for us.
		if ( Woo_Wallet_Optin_Notice::ENDPOINT !== $url ) {
			return $preempt;
		}

		$this->requests[] = array(
			'url'  => $url,
			'args' => $args,
		);
		return $this->canned;
	}

	/**
	 * Populate $_POST for a subscribe call as an administrator.
	 *
	 * @param array $overrides Field overrides.
	 * @return void
	 */
	protected function prepare_post( $overrides = array() ) {
		$_POST = array_merge(
			array(
				'action'      => 'woo_wallet_optin_subscribe',
				'nonce'       => wp_create_nonce( Woo_Wallet_Optin_Notice::NONCE ),
				'consent'     => 1,
				'consent_age' => 4,
				'email'       => 'owner@example.com',
			),
			$overrides
		);
	}

	/**
	 * Run the AJAX action and return the decoded JSON response.
	 *
	 * @param string $action Action name.
	 * @return array
	 */
	protected function dispatch( $action = 'woo_wallet_optin_subscribe' ) {
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// wp_send_json_* always dies; dieHandler() has already drained the
			// output buffer into $this->_last_response by this point.
		} catch ( WPAjaxDieStopException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Same, for a wp_die() with no body — the 403 capability path.
		}

		return json_decode( (string) $this->_last_response, true );
	}

	/**
	 * Without the consent flag nothing may leave the site.
	 */
	public function test_no_request_without_consent() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->prepare_post( array( 'consent' => '' ) );

		$response = $this->dispatch();

		$this->assertSame( array(), $this->requests, 'A request was sent without consent.' );
		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_option( Woo_Wallet_Optin_Notice::OPT_DONE ) );
	}

	/**
	 * An invalid email is rejected before any request is made.
	 */
	public function test_no_request_with_invalid_email() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->prepare_post( array( 'email' => 'not-an-email' ) );

		$response = $this->dispatch();

		$this->assertSame( array(), $this->requests, 'A request was sent with an invalid email.' );
		$this->assertFalse( $response['success'] );
		$this->assertFalse( get_option( Woo_Wallet_Optin_Notice::OPT_DONE ) );
	}

	/**
	 * A user without manage_options cannot trigger the request.
	 */
	public function test_no_request_without_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->prepare_post();

		$this->dispatch();

		$this->assertSame( array(), $this->requests, 'A request was sent by a user lacking manage_options.' );
		$this->assertFalse( get_option( Woo_Wallet_Optin_Notice::OPT_DONE ) );
	}

	/**
	 * A consented submit sends exactly one request with the documented payload.
	 */
	public function test_successful_optin_sends_expected_payload() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->prepare_post();

		$response = $this->dispatch();

		$this->assertTrue( $response['success'] );
		$this->assertCount( 1, $this->requests, 'Expected exactly one outbound request.' );

		$request = $this->requests[0];
		$this->assertSame( Woo_Wallet_Optin_Notice::ENDPOINT, $request['url'] );
		$this->assertSame( 10, $request['args']['timeout'] );
		$this->assertNotSame( false, $request['args']['blocking'] );

		$body = json_decode( $request['args']['body'], true );
		$this->assertSame(
			array( 'consent_timestamp', 'email', 'plugin_version', 'site_url' ),
			$this->sorted_keys( $body ),
			'The payload must carry exactly the four disclosed fields.'
		);
		$this->assertSame( 'owner@example.com', $body['email'] );
		$this->assertSame( home_url(), $body['site_url'] );
		$this->assertSame( WOO_WALLET_PLUGIN_VERSION, $body['plugin_version'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $body['consent_timestamp'] );

		$this->assertSame( 'yes', get_option( Woo_Wallet_Optin_Notice::OPT_DONE ) );
	}

	/**
	 * A server error leaves the opt-in unrecorded so the banner returns.
	 */
	public function test_server_error_does_not_record_optin() {
		$this->canned = array(
			'response' => array(
				'code'    => 500,
				'message' => 'Internal Server Error',
			),
			'body'     => '',
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->prepare_post();

		$response = $this->dispatch();

		$this->assertFalse( $response['success'] );
		$this->assertCount( 1, $this->requests, 'The handler must not retry.' );
		$this->assertFalse( get_option( Woo_Wallet_Optin_Notice::OPT_DONE ) );
	}

	/**
	 * A transport failure behaves the same way.
	 */
	public function test_transport_error_does_not_record_optin() {
		$this->canned = new WP_Error( 'http_request_failed', 'Connection timed out' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->prepare_post();

		$response = $this->dispatch();

		$this->assertFalse( $response['success'] );
		$this->assertCount( 1, $this->requests, 'The handler must not retry.' );
		$this->assertFalse( get_option( Woo_Wallet_Optin_Notice::OPT_DONE ) );
	}

	/**
	 * Dismissal is permanent and sends nothing.
	 */
	public function test_dismiss_is_permanent_and_silent() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$_POST = array(
			'action' => 'woo_wallet_optin_dismiss',
			'nonce'  => wp_create_nonce( Woo_Wallet_Optin_Notice::NONCE ),
		);

		$response = $this->dispatch( 'woo_wallet_optin_dismiss' );

		$this->assertTrue( $response['success'] );
		$this->assertSame( array(), $this->requests );
		$this->assertSame( 'yes', get_option( Woo_Wallet_Optin_Notice::OPT_DISMISSED ) );
	}

	/**
	 * Either stored flag suppresses the banner, as does a missing capability.
	 */
	public function test_should_render_gates() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$this->assertFalse( $this->notice->should_render(), 'Rendered without manage_options.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		update_option( Woo_Wallet_Optin_Notice::OPT_DONE, 'yes' );
		$this->assertFalse( $this->notice->should_render(), 'Rendered after opting in.' );
		delete_option( Woo_Wallet_Optin_Notice::OPT_DONE );

		update_option( Woo_Wallet_Optin_Notice::OPT_DISMISSED, 'yes' );
		$this->assertFalse( $this->notice->should_render(), 'Rendered after dismissal.' );
	}

	/**
	 * Sorted top-level keys of an array.
	 *
	 * @param array $data Array to inspect.
	 * @return array
	 */
	protected function sorted_keys( $data ) {
		$keys = array_keys( (array) $data );
		sort( $keys );
		return $keys;
	}
}
