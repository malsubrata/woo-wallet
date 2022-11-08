<?php
/**
 * Wallet Updates
 *
 * Functions for updating data.
 *
 * @package WooWallet
 * @version 1.0.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * DB update 108.
 */
function woo_wallet_update_108_db_column() {
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'woo_wallet_transactions';
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `currency`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s ADD `currency` varchar(20 ) NOT NULL DEFAULT 0;', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
/**
 * DB update 110.
 */
function woo_wallet_update_110_db_column() {
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'woo_wallet_transactions';
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `blog_id`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s ADD `blog_id` BIGINT UNSIGNED NOT NULL DEFAULT 1;', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
/**
 * DB update 117.
 */
function woo_wallet_update_117_db_column() {
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'woo_wallet_transactions';
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `deleted`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s ADD `deleted` tinyint(1 ) NOT NULL DEFAULT 0;', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
/**
 * DB update 1310.
 */
function woo_wallet_update_1310_db_column() {
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'woo_wallet_transactions';
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `amount`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s MODIFY COLUMN `amount` decimal(16,8);', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `balance`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s MODIFY COLUMN `balance` decimal(16,8);', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
/**
 * DB update 1312.
 */
function woo_wallet_update_1312_db_column() {
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'woo_wallet_transactions';
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && ! $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `created_by`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s ADD `created_by` BIGINT UNSIGNED NOT NULL DEFAULT 1;', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
/**
 * DB update 1321.
 */
function woo_wallet_update_1321_db_column() {
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'woo_wallet_transactions';
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `amount`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s MODIFY COLUMN `amount` decimal(16,8);', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
	if ( $table_name === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) && $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM `%s` LIKE `balance`;', $table_name ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %s MODIFY COLUMN `balance` decimal(16,8);', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}
}
