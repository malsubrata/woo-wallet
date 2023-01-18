<?php
/**
 * Wallet plugin installation file
 *
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Woo_Wallet_Install Class
 */
class Woo_Wallet_Install {
	/**
	 * Version updates
	 *
	 * @var array
	 */
	private static $db_updates = array(
		'1.0.8'  => array(
			'woo_wallet_update_108_db_column',
		),
		'1.1.0'  => array(
			'woo_wallet_update_110_db_column',
		),
		'1.1.7'  => array(
			'woo_wallet_update_117_db_column',
		),
		'1.3.10' => array(
			'woo_wallet_update_1310_db_column',
		),
		'1.3.12' => array(
			'woo_wallet_update_1312_db_column',
		),
		'1.3.21' => array(
			'woo_wallet_update_1321_db_column',
		),
	);
	/**
	 * Class constructor.
	 */
	public function __construct() {
		self::update();
	}

	/**
	 * Plugin install
	 *
	 * @return void
	 */
	public static function install() {
		if ( ! is_blog_installed() ) {
			return;
		}
		self::create_tables();
		self::cteate_product_if_not_exist();
	}

	/**
	 * Plugins table creation
	 *
	 * @global object $wpdb
	 */
	private static function create_tables() {
		global $wpdb;
		$wpdb->hide_errors();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( self::get_schema() );
	}

	/**
	 * Plugin table schema
	 *
	 * @global object $wpdb
	 * @return string
	 */
	private static function get_schema() {
		global $wpdb;
		$collate = '';

		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}
		$tables = "CREATE TABLE IF NOT EXISTS {$wpdb->base_prefix}woo_wallet_transactions (
            transaction_id BIGINT UNSIGNED NOT NULL auto_increment,
            blog_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type ENUM('credit', 'debit') NOT NULL,
            amount DECIMAL( 16,8 ) NOT NULL,
            balance DECIMAL( 16,8 ) NOT NULL,
            currency varchar(20 ) NOT NULL,
            details longtext NULL,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 1,
            deleted tinyint(1 ) NOT NULL DEFAULT 0,
            date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (transaction_id ),
            KEY user_id (user_id )
        ) $collate;
        CREATE TABLE {$wpdb->base_prefix}woo_wallet_transaction_meta (
            meta_id BIGINT UNSIGNED NOT NULL auto_increment,
            transaction_id BIGINT UNSIGNED NOT NULL,
            meta_key varchar(255) default NULL,
            meta_value longtext NULL,
            PRIMARY KEY  (meta_id ),
            KEY transaction_id (transaction_id ),
            KEY meta_key (meta_key(32 ) )
        ) $collate;";
		return $tables;
	}
	/**
	 * Create rechargeable product if not exist
	 */
	public static function cteate_product_if_not_exist() {
		if ( ! wc_get_product( get_option( '_woo_wallet_recharge_product' ) ) ) {
			self::create_product();
		}
	}

	/**
	 * Create rechargeable product
	 */
	private static function create_product() {
		$product_args = array(
			'post_title'   => wc_clean( 'Wallet Topup' ),
			'post_status'  => 'private',
			'post_type'    => 'product',
			'post_excerpt' => '',
			'post_content' => stripslashes( html_entity_decode( 'Auto generated product for wallet recharge please do not delete or update.', ENT_QUOTES, 'UTF-8' ) ),
			'post_author'  => get_current_user_id(),
		);
		$product_id   = wp_insert_post( $product_args );
		if ( ! is_wp_error( $product_id ) ) {
			$product = wc_get_product( $product_id );
			wp_set_object_terms( $product_id, 'simple', 'product_type' );
			update_post_meta( $product_id, '_stock_status', 'instock' );
			update_post_meta( $product_id, 'total_sales', '0' );
			update_post_meta( $product_id, '_downloadable', 'no' );
			update_post_meta( $product_id, '_virtual', 'yes' );
			update_post_meta( $product_id, '_regular_price', '' );
			update_post_meta( $product_id, '_sale_price', '' );
			update_post_meta( $product_id, '_purchase_note', '' );
			update_post_meta( $product_id, '_featured', 'no' );
			update_post_meta( $product_id, '_weight', '' );
			update_post_meta( $product_id, '_length', '' );
			update_post_meta( $product_id, '_width', '' );
			update_post_meta( $product_id, '_height', '' );
			update_post_meta( $product_id, '_sku', '' );
			update_post_meta( $product_id, '_product_attributes', array() );
			update_post_meta( $product_id, '_sale_price_dates_from', '' );
			update_post_meta( $product_id, '_sale_price_dates_to', '' );
			update_post_meta( $product_id, '_price', '' );
			update_post_meta( $product_id, '_sold_individually', 'yes' );
			update_post_meta( $product_id, '_manage_stock', 'no' );
			update_post_meta( $product_id, '_backorders', 'no' );
			update_post_meta( $product_id, '_stock', '' );
			if ( version_compare( WC_VERSION, '3.0', '>=' ) ) {
				$product->set_reviews_allowed( false );
				$product->set_catalog_visibility( 'hidden' );
				$product->save();
			}

			update_option( '_woo_wallet_recharge_product', $product_id );
		}
	}

	/**
	 * Get list of DB update callbacks.
	 *
	 * @since  1.0.8
	 * @return array
	 */
	public static function get_db_update_callbacks() {
		return self::$db_updates;
	}

	/**
	 * Update plugin
	 */
	private static function update() {
		$current_db_version = get_option( 'woo_wallet_db_version' );
		if ( version_compare( WOO_WALLET_PLUGIN_VERSION, $current_db_version, '=' ) ) {
			return;
		}
		foreach ( self::get_db_update_callbacks() as $version => $update_callbacks ) {
			if ( version_compare( $current_db_version, $version, '<' ) ) {
				foreach ( $update_callbacks as $update_callback ) {
					call_user_func( $update_callback );
				}
			}
		}
		self::update_db_version();
	}

	/**
	 * Update DB version to current.
	 *
	 * @param string|null $version New WooCommerce DB version or null.
	 */
	public static function update_db_version( $version = null ) {
		delete_option( 'woo_wallet_db_version' );
		add_option( 'woo_wallet_db_version', is_null( $version ) ? WOO_WALLET_PLUGIN_VERSION : $version );
	}

}

new Woo_Wallet_Install();
