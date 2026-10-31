<?php
/**
 * S2 (1.7.4): client IP for referral checks ignores forwarded headers unless
 * REMOTE_ADDR is a trusted proxy (filter, or a private/reserved address).
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WOO_Wallet_Helper::get_client_ip
 * @covers WooWallet_Referral_Service::is_self_referral
 */
class Test_Client_Ip extends WP_UnitTestCase {

	/**
	 * $_SERVER keys touched by the tests.
	 *
	 * @var array
	 */
	private $saved = array();

	/**
	 * Snapshot $_SERVER.
	 */
	public function set_up() {
		parent::set_up();
		foreach ( array( 'REMOTE_ADDR', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP' ) as $key ) {
			$this->saved[ $key ] = $_SERVER[ $key ] ?? null;
			unset( $_SERVER[ $key ] );
		}
	}

	/**
	 * Restore $_SERVER and filters.
	 */
	public function tear_down() {
		foreach ( $this->saved as $key => $value ) {
			if ( null === $value ) {
				unset( $_SERVER[ $key ] );
			} else {
				$_SERVER[ $key ] = $value;
			}
		}
		remove_all_filters( 'woo_wallet_trusted_proxies' );
		remove_all_filters( 'woo_wallet_client_ip' );
		parent::tear_down();
	}

	/**
	 * Set request headers.
	 *
	 * @param string      $remote REMOTE_ADDR.
	 * @param string|null $xff    X-Forwarded-For.
	 * @param string|null $real   X-Real-IP.
	 */
	private function request( $remote, $xff = null, $real = null ) {
		$_SERVER['REMOTE_ADDR'] = $remote;
		if ( null !== $xff ) {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = $xff;
		}
		if ( null !== $real ) {
			$_SERVER['HTTP_X_REAL_IP'] = $real;
		}
	}

	/**
	 * Trust the given proxies.
	 *
	 * @param array $proxies IPs / CIDRs.
	 */
	private function trust( array $proxies ) {
		add_filter(
			'woo_wallet_trusted_proxies',
			function () use ( $proxies ) {
				return $proxies;
			}
		);
	}

	/**
	 * Public REMOTE_ADDR: forwarded headers are ignored. Fails on 1.7.3
	 * (WC_Geolocation returned the header value).
	 */
	public function test_public_remote_ignores_headers() {
		$this->request( '203.0.113.7', '198.51.100.1', '198.51.100.2' );
		$this->assertSame( '203.0.113.7', WOO_Wallet_Helper::get_client_ip() );
	}

	/**
	 * Trusted proxy (filter, exact IP and CIDR): X-Forwarded-For is used,
	 * right-most non-proxy address.
	 */
	public function test_trusted_proxy_uses_forwarded_for() {
		$this->trust( array( '203.0.113.7' ) );
		$this->request( '203.0.113.7', '198.51.100.99, 198.51.100.1' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip() );

		remove_all_filters( 'woo_wallet_trusted_proxies' );
		$this->trust( array( '203.0.113.0/24', '2001:db8::/32' ) );
		$this->request( '203.0.113.50', '198.51.100.99, 198.51.100.1, 203.0.113.9' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip(), 'CIDR proxy hop skipped.' );

		$this->request( '2001:db8::5', '[2001:db9::1]:443' );
		$this->assertSame( '2001:db9::1', WOO_Wallet_Helper::get_client_ip(), 'IPv6 CIDR + bracketed header.' );
	}

	/**
	 * Private/reserved REMOTE_ADDR (local reverse proxy): headers trusted.
	 */
	public function test_private_remote_is_local_proxy() {
		$this->request( '10.0.0.2', '198.51.100.1' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip() );

		$this->request( '127.0.0.1', '198.51.100.99, 198.51.100.1, 172.16.0.3' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip(), 'Private hops skipped.' );

		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		$this->request( '192.168.1.1', null, '198.51.100.3:8080' );
		$this->assertSame( '198.51.100.3', WOO_Wallet_Helper::get_client_ip(), 'X-Real-IP fallback with port.' );

		unset( $_SERVER['HTTP_X_REAL_IP'] );
		$this->request( '192.168.1.1', '10.1.1.1, 10.2.2.2' );
		$this->assertSame( '10.1.1.1', WOO_Wallet_Helper::get_client_ip(), 'All hops private: client on the LAN.' );
	}

	/**
	 * Invalid values are handled without error.
	 */
	public function test_invalid_values() {
		$this->request( 'not-an-ip', '198.51.100.1' );
		$this->assertSame( '', WOO_Wallet_Helper::get_client_ip(), 'Invalid REMOTE_ADDR: unknown, header not used.' );

		$this->request( '10.0.0.2', 'garbage, <script>, 999.1.1.1', 'also bad' );
		$this->assertSame( '10.0.0.2', WOO_Wallet_Helper::get_client_ip(), 'Bad headers: REMOTE_ADDR.' );

		$this->trust( array( 'nope', '203.0.113.0/abc', '203.0.113.0/99', '', '203.0.113.0/' ) );
		$this->request( '203.0.113.7', '198.51.100.1' );
		$this->assertSame( '203.0.113.7', WOO_Wallet_Helper::get_client_ip(), 'Invalid proxy entries ignored.' );

		unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'] );
		$this->assertSame( '', WOO_Wallet_Helper::get_client_ip(), 'No REMOTE_ADDR.' );
	}

	/**
	 * IPv4-mapped IPv6 REMOTE_ADDR is handled as IPv4: a public mapped address
	 * is not a proxy, a private one is. Fails on round-1 code.
	 */
	public function test_ipv4_mapped_remote() {
		$this->request( '::ffff:203.0.113.7', '198.51.100.1', '198.51.100.2' );
		$this->assertSame( '203.0.113.7', WOO_Wallet_Helper::get_client_ip() );

		$this->request( '::ffff:10.0.0.2', '198.51.100.1' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip(), 'Private mapped address is a local proxy.' );

		$this->trust( array( '203.0.113.0/24' ) );
		$this->request( '::FFFF:203.0.113.7', '198.51.100.1' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip(), 'IPv4 range matches a mapped address.' );
	}

	/**
	 * An invalid X-Forwarded-For hop stops the walk: REMOTE_ADDR is used, not
	 * an address to its left. Fails on round-1 code.
	 */
	public function test_invalid_hop_stops_walk() {
		$this->request( '10.0.0.2', '198.51.100.1, unknown' );
		$this->assertSame( '10.0.0.2', WOO_Wallet_Helper::get_client_ip() );

		$this->request( '10.0.0.2', '198.51.100.1, unknown', '198.51.100.9' );
		$this->assertSame( '10.0.0.2', WOO_Wallet_Helper::get_client_ip(), 'X-Real-IP not used when X-Forwarded-For is present.' );

		$this->request( '10.0.0.2', 'junk, 198.51.100.1' );
		$this->assertSame( '198.51.100.1', WOO_Wallet_Helper::get_client_ip(), 'Junk left of the client is never reached.' );
	}

	/**
	 * Addresses are returned in canonical form, so one address gives one hash.
	 */
	public function test_canonical_form() {
		$this->request( '2001:0DB8:0000:0000::0001' );
		$this->assertSame( '2001:db8::1', WOO_Wallet_Helper::get_client_ip() );
	}

	/**
	 * Final value goes through woo_wallet_client_ip.
	 */
	public function test_client_ip_filter() {
		$this->request( '203.0.113.7' );
		add_filter(
			'woo_wallet_client_ip',
			function ( $ip, $remote ) {
				return $remote . '-x';
			},
			10,
			2
		);
		$this->assertSame( '203.0.113.7-x', WOO_Wallet_Helper::get_client_ip() );
	}

	/**
	 * Self-referral IP check: the same REMOTE_ADDR with an untrusted
	 * X-Forwarded-For / X-Real-IP is still a match. Fails on 1.7.3.
	 */
	public function test_self_referral_ignores_untrusted_forwarded_header() {
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-referral-service.php';
		$referrer = self::factory()->user->create();
		$referred = self::factory()->user->create();

		$this->request( '203.0.113.7' );
		WooWallet_Referral_Service::remember_browser_user( $referrer );
		unset( $_COOKIE[ WooWallet_Referral_Service::BROWSER_COOKIE ] );

		$this->request( '203.0.113.7', '198.51.100.1', '198.51.100.2' );
		$this->assertTrue( WooWallet_Referral_Service::is_self_referral( $referrer, $referred ) );

		$this->request( '198.51.100.1' );
		$this->assertFalse( WooWallet_Referral_Service::is_self_referral( $referrer, $referred ), 'Different IP: not a match.' );
	}
}
