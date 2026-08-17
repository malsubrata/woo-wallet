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

	/**
	 * Order statuses that trigger cashback, in the shape the rest of the
	 * plugin speaks.
	 *
	 * The `process_cashback_status` setting offers `wc_get_order_statuses()`
	 * as its options, whose keys are prefixed (`wc-processing`), while the
	 * field default, the `woocommerce_order_status_{$status}` hook name and
	 * `WC_Order::get_status()` are all unprefixed. A store that never saved
	 * the setting therefore ran on the correct shape and a store that saved
	 * it once did not. Both readers go through here so they cannot drift.
	 *
	 * The `wallet_cashback_order_status` filter keeps its existing contract:
	 * it still receives the stored value and still decides the final list.
	 * Normalisation runs after it, and is a no-op on unprefixed input.
	 *
	 * @return array Unprefixed order statuses.
	 */
	public static function get_cashback_order_statuses(): array {
		$statuses = woo_wallet()->settings_api->get_option(
			'process_cashback_status',
			'_wallet_settings_credit',
			array( 'processing', 'completed' )
		);

		return self::normalize_order_statuses( apply_filters( 'wallet_cashback_order_status', $statuses ) );
	}

	/**
	 * Drop WooCommerce's `wc-` status prefix.
	 *
	 * Anchored and applied once: a status slug that legitimately contains
	 * `wc-` further along keeps it, and `wc-wc-shipped` becomes `wc-shipped`.
	 *
	 * @param mixed $statuses Order statuses, in either shape. A scalar from a
	 *                        third-party filter is tolerated.
	 * @return array Unprefixed, de-duplicated order statuses.
	 */
	public static function normalize_order_statuses( $statuses ): array {
		$normalized = array();

		foreach ( (array) $statuses as $status ) {
			if ( ! is_scalar( $status ) ) {
				continue;
			}
			$status = preg_replace( '/^wc-/', '', (string) $status );
			if ( '' !== $status && ! in_array( $status, $normalized, true ) ) {
				$normalized[] = $status;
			}
		}

		return $normalized;
	}
}
