<?php

/**
 * Wallet Updates
 *
 * Functions for updating data.
 *
 * @version 1.0.8
 */
if (!defined('ABSPATH')) {
    exit;
}

function woo_wallet_update_108_db_column() {
    global $wpdb;
    $table_name = $wpdb->prefix.'woo_wallet_transactions';
    if ($table_name === $wpdb->get_var("SHOW TABLES LIKE '$table_name'") && !$wpdb->get_var("SHOW COLUMNS FROM `{$table_name}` LIKE 'currency';")) {
        $wpdb->query("ALTER TABLE {$table_name} ADD `currency` varchar(20) NOT NULL DEFAULT 0;");
    }
}
