<?php
/**
 * Transfer recipient search: results never expose another user's login or
 * stored email; partial search has a server-side minimum and limit.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Ajax::woo_wallet_user_search
 */
class Test_Transfer_User_Search extends WP_Ajax_UnitTestCase {

	/**
	 * Logged-in sender.
	 *
	 * @var int
	 */
	private $sender;

	/**
	 * Load the AJAX class and log in a customer.
	 */
	public function setUp(): void {
		parent::setUp();
		if ( ! class_exists( 'Woo_Wallet_Ajax' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php';
		}
		new Woo_Wallet_Ajax();
		$this->sender = self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_login' => 'sender_login',
				'user_email' => 'sender@example.test',
			)
		);
		wp_set_current_user( $this->sender );
	}

	/**
	 * Reset request globals and filters.
	 */
	public function tearDown(): void {
		unset( $_POST['term'], $_POST['security'], $_POST['site_id'], $_REQUEST['term'], $_REQUEST['security'], $_REQUEST['site_id'] );
		remove_all_filters( 'woo_wallet_user_search_exact_match' );
		remove_all_filters( 'woo_wallet_user_search_by' );
		parent::tearDown();
	}

	/**
	 * Run the search and return [ decoded results, raw body ].
	 *
	 * @param string $term  Search term.
	 * @param array  $extra Extra POST fields.
	 * @return array
	 */
	private function search( $term, array $extra = array() ) {
		$this->_last_response = '';
		$_POST                = array_merge(
			array(
				'term'     => $term,
				'security' => wp_create_nonce( 'search-user' ),
			),
			$extra
		);
		$_REQUEST             = array_merge( $_REQUEST, $_POST );
		try {
			$this->_handleAjax( 'woo-wallet-user-search' );
		} catch ( WPAjaxDieContinueException $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// wp_send_json() always dies; the response is already captured.
		}
		return array( json_decode( (string) $this->_last_response, true ), (string) $this->_last_response );
	}

	/**
	 * Recipient with a first/last name.
	 *
	 * @return int
	 */
	private function named_recipient() {
		return self::factory()->user->create(
			array(
				'role'       => 'customer',
				'user_login' => 'secretlogin_bob',
				'user_email' => 'bob.private@example.test',
				'first_name' => 'Bob',
				'last_name'  => 'Builder',
			)
		);
	}

	/**
	 * Exact mode: name + the email the sender typed, never the login.
	 */
	public function test_exact_label_is_name_and_typed_email() {
		$bob = $this->named_recipient();

		list( $results, $raw ) = $this->search( 'bob.private@example.test' );

		$this->assertCount( 1, $results );
		$this->assertSame( $bob, (int) $results[0]['value'] );
		$this->assertSame( 'Bob Builder (bob.private@example.test)', $results[0]['label'] );
		$this->assertStringNotContainsString( 'secretlogin_bob', $raw );
	}

	/**
	 * Exact mode by login (filter): label is the name only — the stored email
	 * is never returned.
	 */
	public function test_exact_by_login_label_has_no_email() {
		$this->named_recipient();
		add_filter(
			'woo_wallet_user_search_by',
			function () {
				return 'login';
			}
		);

		list( $results, $raw ) = $this->search( 'secretlogin_bob' );

		$this->assertCount( 1, $results );
		$this->assertSame( 'Bob Builder', $results[0]['label'] );
		$this->assertStringNotContainsString( 'bob.private', $raw );
	}

	/**
	 * Exact mode: the sender's own account is still excluded.
	 */
	public function test_exact_excludes_self() {
		list( $results ) = $this->search( 'sender@example.test' );
		$this->assertSame( array(), $results );
	}

	/**
	 * Partial mode: under 3 characters returns nothing (including empty).
	 */
	public function test_partial_requires_three_characters() {
		add_filter( 'woo_wallet_user_search_exact_match', '__return_false' );
		$this->named_recipient();

		foreach ( array( '', 'b', 'bo', '**', '***', '*b*', '**bo', '*****' ) as $term ) {
			list( $results ) = $this->search( $term );
			$this->assertSame( array(), $results, "term '$term'" );
		}
	}

	/**
	 * Partial mode: at most 10 results, labels carry no login or stored
	 * email, the sender is excluded and a posted site_id is ignored.
	 */
	public function test_partial_limit_labels_and_site() {
		add_filter( 'woo_wallet_user_search_exact_match', '__return_false' );
		for ( $i = 0; $i < 12; $i++ ) {
			self::factory()->user->create(
				array(
					'role'       => 'customer',
					'user_login' => "zzmatch_login_$i",
					'user_email' => "zzmatch$i@example.test",
				)
			);
		}

		list( $results, $raw ) = $this->search( 'zzmatch', array( 'site_id' => 999 ) );

		$this->assertCount( 10, $results );
		$this->assertStringNotContainsString( 'zzmatch_login_', $raw );
		foreach ( $results as $row ) {
			$this->assertMatchesRegularExpression( '/^z\*\*\*@example\.test$/', $row['label'] );
			$this->assertNotSame( $this->sender, (int) $row['value'] );
		}
	}
}
