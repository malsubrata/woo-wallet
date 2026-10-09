<?php
/**
 * HF-2: Woo_Wallet_Install::update() safety net, and fresh installs skip the
 * migrations.
 *
 * @package WooWallet\Tests
 */

/**
 * Test migration step that succeeds.
 */
function tw_test_db_update_ok() {
	++$GLOBALS['tw_test_db_update_calls']['ok'];
}

/**
 * Test migration step that throws.
 *
 * @throws Error Always.
 */
function tw_test_db_update_throws() {
	++$GLOBALS['tw_test_db_update_calls']['throws'];
	throw new Error( 'step broke' );
}

/**
 * Test migration step that must not run after a failure.
 */
function tw_test_db_update_after() {
	++$GLOBALS['tw_test_db_update_calls']['after'];
}

/**
 * @covers Woo_Wallet_Install::update
 * @covers Woo_Wallet_Install::install
 * @covers Woo_Wallet_Admin::show_db_update_failed_notice
 */
class Test_Install_Update extends WP_UnitTestCase {

	/**
	 * Original $db_updates, restored in tear_down.
	 *
	 * @var array
	 */
	private $saved_updates;

	/**
	 * Reflection handle on Woo_Wallet_Install::$db_updates.
	 *
	 * @var ReflectionProperty
	 */
	private $prop;

	/**
	 * Reset call counters and remember the real migration list.
	 */
	public function set_up() {
		parent::set_up();
		$GLOBALS['tw_test_db_update_calls'] = array(
			'ok'     => 0,
			'throws' => 0,
			'after'  => 0,
		);
		$this->prop = new ReflectionProperty( 'Woo_Wallet_Install', 'db_updates' );
		$this->prop->setAccessible( true );
		$this->saved_updates = $this->prop->getValue();
		delete_transient( 'woo_wallet_db_update_failed' );
	}

	/**
	 * Restore the real migration list.
	 */
	public function tear_down() {
		$this->prop->setValue( null, $this->saved_updates );
		remove_all_filters( 'query' );
		parent::tear_down();
	}

	/**
	 * A throwing step does not escape; earlier groups are stamped, the failed
	 * group is not, and retries pause while the transient exists.
	 */
	public function test_failing_step_is_caught_and_paused() {
		$this->prop->setValue(
			null,
			array(
				'1.7.0' => array( 'tw_test_db_update_ok' ),
				'1.7.1' => array( 'tw_test_db_update_throws', 'tw_test_db_update_after' ),
				'1.7.2' => array( 'tw_test_db_update_after' ),
			)
		);
		update_option( 'woo_wallet_db_version', '1.6.9' );

		Woo_Wallet_Install::update();

		$this->assertSame( '1.7.0', get_option( 'woo_wallet_db_version' ) );
		$this->assertSame( 1, $GLOBALS['tw_test_db_update_calls']['ok'] );
		$this->assertSame( 1, $GLOBALS['tw_test_db_update_calls']['throws'] );
		$this->assertSame( 0, $GLOBALS['tw_test_db_update_calls']['after'] );
		$failed = get_transient( 'woo_wallet_db_update_failed' );
		$this->assertSame( 'tw_test_db_update_throws', $failed['callback'] );
		$this->assertSame( 'step broke', $failed['message'] );

		Woo_Wallet_Install::update();

		$this->assertSame( 1, $GLOBALS['tw_test_db_update_calls']['throws'], 'Retried inside the pause.' );
		$this->assertSame( '1.7.0', get_option( 'woo_wallet_db_version' ) );
	}

	/**
	 * The notice shows the failed step to store managers only.
	 */
	public function test_notice_shows_step_to_managers_only() {
		set_transient(
			'woo_wallet_db_update_failed',
			array(
				'callback' => 'tw_test_db_update_throws',
				'message'  => 'step broke',
			),
			HOUR_IN_SECONDS
		);
		if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		}
		$admin = Woo_Wallet_Admin::instance();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		ob_start();
		$admin->show_db_update_failed_notice();
		$this->assertSame( '', ob_get_clean() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		$admin->show_db_update_failed_notice();
		$this->assertStringContainsString( '(step: tw_test_db_update_throws)', ob_get_clean() );
	}

	/**
	 * Normal upgrade from 1.6.4 runs every later group and ends current.
	 */
	public function test_normal_upgrade_from_164() {
		update_option( 'woo_wallet_db_version', '1.6.4' );

		Woo_Wallet_Install::update();

		$this->assertSame( WOO_WALLET_PLUGIN_VERSION, get_option( 'woo_wallet_db_version' ) );
		$this->assertFalse( get_transient( 'woo_wallet_db_update_failed' ) );
		$this->assertEquals( 1, get_option( 'woo_wallet_pending_topup_description_cleanup' ) );
	}

	/**
	 * A fresh install stamps the current version, so update() runs nothing.
	 */
	public function test_fresh_install_skips_migrations() {
		delete_option( 'woo_wallet_db_version' );
		// Make the ledger table look absent, as on a brand-new site.
		add_filter(
			'query',
			static function ( $query ) {
				return 0 === strpos( $query, 'SHOW TABLES LIKE' ) && false !== strpos( $query, 'transactions' ) ? "SHOW TABLES LIKE 'tw_no_such_table'" : $query;
			}
		);

		Woo_Wallet_Install::install();
		remove_all_filters( 'query' );

		$this->assertSame( WOO_WALLET_PLUGIN_VERSION, get_option( 'woo_wallet_db_version' ) );

		$this->prop->setValue( null, array( '1.0.8' => array( 'tw_test_db_update_ok' ) ) );
		Woo_Wallet_Install::update();
		$this->assertSame( 0, $GLOBALS['tw_test_db_update_calls']['ok'] );
	}

	/**
	 * Existing tables without a version (very old site) are not stamped, so
	 * update() still runs every migration.
	 */
	public function test_existing_tables_without_version_are_not_stamped() {
		delete_option( 'woo_wallet_db_version' );

		Woo_Wallet_Install::install();

		$this->assertFalse( get_option( 'woo_wallet_db_version' ) );
	}

	/**
	 * Reactivating an existing site keeps its stored version.
	 */
	public function test_reactivation_keeps_version() {
		update_option( 'woo_wallet_db_version', '1.6.4' );

		Woo_Wallet_Install::install();

		$this->assertSame( '1.6.4', get_option( 'woo_wallet_db_version' ) );
	}
}
