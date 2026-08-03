<?php
/**
 * Helper class file.
 *
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Provides static methods as helpers.
 */
class WOO_Wallet_Helper {
	/**
	 * Save order meta data.
	 *
	 * @param WC_Order|int $order order.
	 * @param string       $key key.
	 * @param mixed        $value value.
	 * @param bool         $do_save do_save.
	 * @return void
	 */
	public static function update_order_meta_data( $order, $key, $value, $do_save = true ) : void {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( is_callable( array( $order, 'update_meta_data' ) ) ) {
			$order->update_meta_data( $key, $value );
		}
		if ( is_callable( array( $order, 'save' ) ) && $do_save ) {
			$order->save();
		}
	}

	/**
	 * Load an order with meta re-read from the datastore.
	 *
	 * Drops in-request / object-cache copies first so a concurrent cancel or
	 * refund that already claimed markers is visible inside a GET_LOCK critical
	 * section (plain `wc_get_order()` often returns the stale cached instance).
	 *
	 * @param int $order_id Order id.
	 * @return WC_Order|false
	 */
	public static function get_order_for_update( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return false;
		}

		wp_cache_delete( 'order-' . $order_id, 'orders' );
		wp_cache_delete( $order_id, 'posts' );

		if ( class_exists( '\Automattic\WooCommerce\Caches\OrderCache' ) && function_exists( 'wc_get_container' ) ) {
			try {
				$order_cache = wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class );
				if ( $order_cache && is_callable( array( $order_cache, 'remove' ) ) ) {
					$order_cache->remove( $order_id );
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Container/cache unavailable — fall through to read_meta_data.
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		if ( is_callable( array( $order, 'read_meta_data' ) ) ) {
			$order->read_meta_data( true );
		}
		return $order;
	}
}
