<?php
/**
 * Wallet Updates
 *
 * Functions for updating data.
 *
 * @package StandaleneTech
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
/**
 * DB update 1518: add covering indexes and drop redundant balance column.
 *
 * Indexes added:
 *   idx_user_deleted (user_id, deleted) — covers the common WHERE user_id=? AND deleted=0 pattern.
 *   idx_user_date    (user_id, date)    — covers date-range filters and ORDER BY date.
 *
 * The `balance` column stored a per-row snapshot that was never read back; balance is always
 * recomputed from SUM(amount) so the column was purely redundant.
 */
function woo_wallet_update_1518_db_schema() {
	global $wpdb;
	$table = $wpdb->base_prefix . 'woo_wallet_transactions';

	if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}

	// Add composite index on (user_id, deleted) if not already present.
	$has_user_deleted = $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, 'idx_user_deleted' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_user_deleted ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX idx_user_deleted (user_id, deleted)', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	// Add composite index on (user_id, date) if not already present.
	$has_user_date = $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, 'idx_user_date' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_user_date ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX idx_user_date (user_id, date)', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	// Drop the redundant balance column if it still exists.
	$has_balance = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'balance' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( $has_balance ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i DROP COLUMN `balance`', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}
/**
 * DB update 1.6.0: per-row currency audit trail + per-currency covering index.
 *
 * Adds four nullable columns and one composite index. Pre-1.6 rows leave the
 * new columns NULL and `mode=0` (= single_base, the legacy semantics).
 *
 *   original_amount   — the amount the user actually saw on the source surface
 *                       (e.g. €90 on the order page) before normalization.
 *   original_currency — ISO of that source surface.
 *   original_rate     — rate snapshot at write time, so historical conversions
 *                       are reproducible after admin changes provider rates.
 *   mode              — 0 = single_base (row stored in shop base, originals
 *                       carry the source-surface presentation), 1 = per_currency
 *                       (row stored in its own currency; originals == canonical).
 *   idx_user_currency — covers per-currency SUM reads and DISTINCT currency
 *                       enumeration in mode=1.
 *
 * Idempotent — each ALTER is gated by a SHOW guard.
 */
function woo_wallet_update_160_db_schema() {
	global $wpdb;
	$table = $wpdb->base_prefix . 'woo_wallet_transactions';

	if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return;
	}

	$has_original_amount = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'original_amount' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_original_amount ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN `original_amount` DECIMAL(16,8) NULL AFTER `amount`', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	$has_original_currency = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'original_currency' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_original_currency ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN `original_currency` VARCHAR(20) NULL AFTER `original_amount`', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	$has_original_rate = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'original_rate' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_original_rate ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN `original_rate` DECIMAL(20,10) NULL AFTER `original_currency`', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	$has_mode = $wpdb->get_var( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table, 'mode' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_mode ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN `mode` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `original_rate`', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}

	$has_user_currency_idx = $wpdb->get_var( $wpdb->prepare( 'SHOW INDEX FROM %i WHERE Key_name = %s', $table, 'idx_user_currency' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	if ( ! $has_user_currency_idx ) {
		$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD INDEX idx_user_currency (user_id, currency, deleted)', $table ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
	}
}

/**
 * DB update 1.6.1: cashback subsystem hardening settings migration.
 *
 * Responsibilities:
 *  (a) Set `max_cashback_scope` to `per_item` on upgrades (preserves existing per-line behaviour).
 *      Fresh installs land on `per_order` (the new default, set by the settings field's `default`
 *      key) — this callback only runs when the option already existed, meaning it is an upgrade.
 *  (b) Set `woo_wallet_legacy_coupon_cashback_total_mutation` to `yes` on upgrades so sites that
 *      already rely on the discount_total/total rewrite continue to behave as before.
 *  (c) Seed dismissible admin-notice transients so merchants are nudged to review the two new
 *      opt-in settings (refund clawback, allow-negative clawback).
 *
 * Idempotent — all writes are conditional.
 *
 * @since 1.6.1
 */
function woo_wallet_update_161_db_schema() {
	$settings = get_option( '_wallet_settings_credit', null );

	// Only runs on true upgrades (option already existed before 1.6.1).
	if ( ! is_null( $settings ) ) {
		// (a) Preserve per-item cap scope for existing sites.
		if ( ! isset( $settings['max_cashback_scope'] ) ) {
			$settings['max_cashback_scope'] = 'per_item';
		}

		// (b) Gate coupon-cashback total mutation behind a legacy flag so upgrading
		//     merchants' revenue reports are not surprised.
		if ( ! isset( $settings['woo_wallet_legacy_coupon_cashback_total_mutation'] ) ) {
			$settings['woo_wallet_legacy_coupon_cashback_total_mutation'] = 'yes';
		}

		update_option( '_wallet_settings_credit', $settings );

		// (c) Seed admin-notice transients for the two new opt-in features.
		if ( false === get_transient( 'tw_161_cashback_refund_notice_dismissed' ) ) {
			set_transient( 'tw_161_cashback_refund_notice', '1', 0 ); // 0 = no expiry; dismissed via AJAX.
		}
		if ( false === get_transient( 'tw_161_coupon_cashback_totals_notice_dismissed' ) ) {
			set_transient( 'tw_161_coupon_cashback_totals_notice', '1', 0 );
		}
	}
}
