<?php
/**
 * PHPUnit bootstrap for the TeraWallet integration test suite.
 *
 * Boots the WordPress test framework (shipped by the wp-phpunit Composer
 * package), loads WooCommerce from the sibling plugin directory and this
 * plugin, and installs both plugins' custom tables into the dedicated test
 * database.
 *
 * @package WooWallet\Tests
 */

$_plugin_dir = dirname( __DIR__ );

// Composer autoloader — provides PHPUnit and yoast/phpunit-polyfills.
if ( ! file_exists( $_plugin_dir . '/vendor/autoload.php' ) ) {
	echo 'Run `composer install` before running the test suite.' . PHP_EOL;
	exit( 1 );
}
require_once $_plugin_dir . '/vendor/autoload.php';

// Tell the WP test framework where the polyfills live (installed via Composer).
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_plugin_dir . '/vendor/yoast/phpunit-polyfills' );
}

// Point the test framework at the generated config (DB creds + reused WP core).
if ( ! defined( 'WP_TESTS_CONFIG_FILE_PATH' ) ) {
	define( 'WP_TESTS_CONFIG_FILE_PATH', $_plugin_dir . '/wp-tests-config.php' );
}
if ( ! file_exists( WP_TESTS_CONFIG_FILE_PATH ) ) {
	echo 'wp-tests-config.php not found. Run `bash bin/install-wp-tests.sh` first.' . PHP_EOL;
	exit( 1 );
}

// Resolve the wp-phpunit test framework directory.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = $_plugin_dir . '/vendor/wp-phpunit/wp-phpunit';
}
if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find the WordPress test library at {$_tests_dir}." . PHP_EOL;
	exit( 1 );
}

// Gives access to tests_add_filter().
require_once $_tests_dir . '/includes/functions.php';

/**
 * Load WooCommerce and TeraWallet before WordPress finishes loading.
 */
function _woo_wallet_tests_load_plugins() {
	$plugin_dir  = dirname( __DIR__ );
	$woocommerce = dirname( $plugin_dir ) . '/woocommerce/woocommerce.php';

	if ( ! file_exists( $woocommerce ) ) {
		echo "WooCommerce not found at {$woocommerce}." . PHP_EOL;
		exit( 1 );
	}

	require $woocommerce;

	// Skip legacy DB-migration replay. Woo_Wallet_Install's constructor runs
	// update() on load; on a fresh test database (empty woo_wallet_db_version)
	// that replays every historical column-add migration — against a table
	// that does not exist yet — emitting malformed-SQL noise. The test
	// database is created with the current schema by create_tables() instead,
	// so seeding a high version short-circuits the replay harmlessly.
	update_option( 'woo_wallet_db_version', '99.0.0' );

	require $plugin_dir . '/woo-wallet.php';
}
tests_add_filter( 'muplugins_loaded', '_woo_wallet_tests_load_plugins' );

// Boot the WordPress test environment.
require $_tests_dir . '/includes/bootstrap.php';

/**
 * Install the WooCommerce and TeraWallet schema.
 *
 * Runs after the WP test framework has fully booted WordPress (and therefore
 * WooCommerce) so the product data store is registered — installing earlier,
 * e.g. on `setup_theme`, makes `wc_get_product()` return false and the wallet
 * top-up product creation fatal. CREATE TABLE statements commit implicitly,
 * so the tables created here survive the per-test transaction rollback that
 * WP_UnitTestCase performs.
 *
 * The wallet tables are dropped first so every run starts from an empty
 * ledger: Woo_Wallet_Wallet::transfer() runs its own START TRANSACTION/
 * COMMIT, which defeats WP_UnitTestCase's per-test rollback isolation —
 * without this drop, committed transfer rows would accumulate across runs.
 * The referral table is dropped for the same reason: recording a referral
 * credits the wallet, and that credit's COMMIT also commits the referral row.
 */
global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}woo_wallet_transaction_meta" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}woo_wallet_referrals" ); // phpcs:ignore WordPress.DB

if ( class_exists( 'WC_Install' ) ) {
	WC_Install::install();
}
if ( class_exists( 'Woo_Wallet_Install' ) ) {
	Woo_Wallet_Install::install();
}
