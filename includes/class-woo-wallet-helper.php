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
}
