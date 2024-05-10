<?php
/**
 * Plugin Name: TeraWallet
 * Plugin URI: https://standalonetech.com/
 * Description: The leading wallet plugin for WooCommerce with partial payment, refunds, cashbacks and what not!
 * Version: 1.5.4
 * Requires Plugins: woocommerce
 * Author: StandaloneTech
 * Author URI: https://standalonetech.com/
 * Text Domain: woo-wallet
 * Domain Path: /languages/
 * Requires at least: 6.4
 * Tested up to: 6.5
 *
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define WOO_WALLET_PLUGIN_FILE.
if ( ! defined( 'WOO_WALLET_PLUGIN_FILE' ) ) {
	define( 'WOO_WALLET_PLUGIN_FILE', __FILE__ );
}

// Define WOO_WALLET_ABSPATH.
if ( ! defined( 'WOO_WALLET_ABSPATH' ) ) {
	define( 'WOO_WALLET_ABSPATH', dirname( WOO_WALLET_PLUGIN_FILE ) . '/' );
}

// Define WOO_WALLET_PLUGIN_VERSION.
if ( ! defined( 'WOO_WALLET_PLUGIN_VERSION' ) ) {
	define( 'WOO_WALLET_PLUGIN_VERSION', '1.5.4' );
}

// include dependencies file.
if ( ! class_exists( 'Woo_Wallet_Dependencies' ) ) {
	include_once __DIR__ . '/includes/class-woo-wallet-dependencies.php';
}

// Include the main class.
if ( ! class_exists( 'WooWallet' ) ) {
	include_once __DIR__ . '/includes/class-woo-wallet.php';
}
/**
 * Returns the main instance of WooWallet.
 *
 * @since  1.1.0
 * @return WooWallet
 */
function woo_wallet() {
	return WooWallet::instance();
}

$GLOBALS['woo_wallet'] = woo_wallet();
