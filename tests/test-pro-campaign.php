<?php
/**
 * Black Friday Pro campaign: window, kill switch, attribution, wording.
 * Touches no money path.
 *
 * @package WooWallet\Tests
 * @covers Woo_Wallet_Pro_Campaign
 */

class Test_Pro_Campaign extends WP_UnitTestCase {

	private function set_now( string $utc ): void {
		$ts = strtotime( $utc . ' UTC' );
		add_filter( 'woo_wallet_pro_campaign_now', static function () use ( $ts ) {
			return $ts;
		} );
	}

	public function tear_down() {
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		remove_all_filters( 'woo_wallet_pro_campaign_active' );
		parent::tear_down();
	}

	public function test_window_edges() {
		$this->set_now( '2026-11-17 12:00:00' );
		$this->assertTrue( Woo_Wallet_Pro_Campaign::is_active() );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2026-11-19 00:00:00' );
		$this->assertTrue( Woo_Wallet_Pro_Campaign::is_active() );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2026-12-01 23:59:58' );
		$this->assertTrue( Woo_Wallet_Pro_Campaign::is_active() );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2026-12-02 00:00:00' );
		$this->assertFalse( Woo_Wallet_Pro_Campaign::is_active() );
	}

	public function test_constants_match_strings() {
		$this->assertSame( strtotime( Woo_Wallet_Pro_Campaign::END . ' UTC' ), Woo_Wallet_Pro_Campaign::END_LABEL_TS );
	}

	public function test_kill_switch() {
		$this->set_now( '2026-11-25 12:00:00' );
		add_filter( 'woo_wallet_pro_campaign_active', '__return_false' );
		$this->assertFalse( Woo_Wallet_Pro_Campaign::is_active() );
	}

	public function test_pro_url_campaign_only_while_active() {
		$this->set_now( '2026-11-25 12:00:00' );
		$this->assertStringContainsString( 'utm_campaign=bf2026', woo_wallet_pro_url( 'x' ) );
		$this->assertStringContainsString( 'utm_campaign=mine', woo_wallet_pro_url( 'x', array( 'utm_campaign' => 'mine' ) ) );
		$this->assertStringNotContainsString( 'bf2026', woo_wallet_pro_url( 'x', array( 'utm_campaign' => 'mine' ) ) );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2026-12-02 00:00:00' );
		$this->assertStringContainsString( 'utm_campaign=upgrade', woo_wallet_pro_url( 'x' ) );
	}

	public function test_price_label_is_plain_price() {
		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-go-pro-page.php';
		$this->assertSame( '$79', Woo_Wallet_Go_Pro_Page::price_label() );
	}

	public function test_days_left_and_deadline_text() {
		$this->set_now( '2026-12-01 08:00:00' );
		$this->assertSame( 0, Woo_Wallet_Pro_Campaign::days_left() );
		$this->assertSame( 'Ends today', Woo_Wallet_Pro_Campaign::deadline_text() );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2026-11-30 08:00:00' );
		$this->assertSame( 'Ends tomorrow', Woo_Wallet_Pro_Campaign::deadline_text() );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2026-11-24 08:00:00' );
		$this->assertSame( 7, Woo_Wallet_Pro_Campaign::days_left() );
		$this->assertSame( 'Ends 1 December · 7 days left', Woo_Wallet_Pro_Campaign::deadline_text() );
	}

	public function test_last_second_is_inclusive_and_days_left_clamps() {
		$this->set_now( '2026-12-01 23:59:59' );
		$this->assertTrue( Woo_Wallet_Pro_Campaign::is_active() );
		remove_all_filters( 'woo_wallet_pro_campaign_now' );
		$this->set_now( '2027-03-01 00:00:00' );
		$this->assertFalse( Woo_Wallet_Pro_Campaign::is_active() );
		$this->assertSame( 0, Woo_Wallet_Pro_Campaign::days_left() );
	}

	public function test_inactive_when_pro_is_active() {
		$this->set_now( '2026-11-25 12:00:00' );
		add_filter(
			'option_active_plugins',
			static function ( $plugins ) {
				$plugins   = (array) $plugins;
				$plugins[] = 'woo-wallet-pro/woo-wallet-pro.php';
				return $plugins;
			}
		);
		$this->assertFalse( Woo_Wallet_Pro_Campaign::is_active() );
		remove_all_filters( 'option_active_plugins' );
		$this->assertTrue( Woo_Wallet_Pro_Campaign::is_active() );
	}

	public function test_urgency_text() {
		$cases = array(
			'2026-11-18 08:00:00' => '',
			'2026-11-24 08:00:00' => '7 days left',
			'2026-11-30 08:00:00' => 'Ends tomorrow',
			'2026-12-01 08:00:00' => 'Ends today',
		);
		foreach ( $cases as $when => $expected ) {
			remove_all_filters( 'woo_wallet_pro_campaign_now' );
			$this->set_now( $when );
			$this->assertSame( $expected, Woo_Wallet_Pro_Campaign::urgency_text(), $when );
		}
	}

	public function test_row_link_text() {
		$this->assertSame( 'Upgrade to Pro — 30% off until 1 Dec', Woo_Wallet_Pro_Campaign::row_link_text() );
	}
}
