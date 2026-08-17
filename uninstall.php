<?php
/**
 * WooCommerce wallet Uninstall
 *
 * Uninstalling WooCommerce wallet product, tables, and options.
 *
 * @author      Subrata Mal
 * @version 1.0.1
 *
 * @package StandaleneTech
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb, $wp_version;

// Remove rechargable product.
wp_delete_post( get_option( '_woo_wallet_recharge_product' ), true );
delete_option( '_woo_wallet_recharge_product' );

/*
 * Only remove ALL plugins data if WALLET_REMOVE_ALL_DATA constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'WALLET_REMOVE_ALL_DATA' ) && true === WALLET_REMOVE_ALL_DATA ) {
	// Tables. Must stay in sync with every CREATE TABLE in Woo_Wallet_Install — the
	// "uninstall.php drops every table install creates" CI check enforces this.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}woo_wallet_transactions" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}woo_wallet_transaction_meta" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->base_prefix}woo_wallet_referrals" );

	// Delete the balance cache user meta — the ledger it mirrored no longer exists.
	$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key = '_current_woo_wallet_balance'" );

	// Delete options.
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_wallet\_%';" );
	$wpdb->query( "DELETE FROM $wpdb->options WHERE option_name LIKE '_woo_wallet\_%';" );

	// Clear any cached data that has been removed.
	wp_cache_flush();
}
