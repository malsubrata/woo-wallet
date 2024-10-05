<?php
/**
 * WooCommerce checkout block partial payment
 *
 * @package StandaloneTech
 */

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

/**
 * Class for integrating with WooCommerce Blocks
 */
class WOO_Wallet_Partial_Payment_Blocks implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'partial-payment';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$this->register_main_integration();
	}

	/**
	 * Registers the main JS file required to add filters and Slot/Fills.
	 */
	public function register_main_integration() {
		$script_path = '/build/partial-payment/index.js';
		$style_path  = '/build/partial-payment/style-index.css';

		$script_url = plugins_url( $script_path, WOO_WALLET_PLUGIN_FILE );
		$style_url  = plugins_url( $style_path, WOO_WALLET_PLUGIN_FILE );

		$script_asset_path = dirname( WOO_WALLET_PLUGIN_FILE ) . '/build/partial-payment/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_path ),
			);

		wp_enqueue_style(
			'partial-payment-blocks-integration',
			$style_url,
			array(),
			$this->get_file_version( $style_path )
		);

		wp_register_script(
			'partial-payment-blocks-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_set_script_translations(
			'partial-payment-blocks-integration',
			'woo-wallet',
			WOO_WALLET_ABSPATH . 'languages/'
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'partial-payment-blocks-integration' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'partial-payment-blocks-integration' );
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$is_enable  = false;
		$cart_total = get_woowallet_cart_total();
		if ( ! is_wallet_rechargeable_cart() && is_user_logged_in() && 'on' !== woo_wallet()->settings_api->get_option( 'is_auto_deduct_for_partial_payment', '_wallet_settings_general' ) && $cart_total > woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) ) {
			$is_enable = true;
		}
		$data = array(
			'active'                 => apply_filters( 'is_enable_wallet_partial_payment', $is_enable ),
			'balance'                => woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ),
			'partial_payment_amount' => ! is_null( wc()->session ) && woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) >= wc()->session->get( 'partial_payment_amount', 0 ) ? wc()->session->get( 'partial_payment_amount', 0 ) : woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ),
			'currency_symbol'        => get_woocommerce_currency_symbol(),
			'decimal_separator'      => wc_get_price_decimal_separator(),
			'thousand_separator'     => wc_get_price_thousand_separator(),
			'decimals'               => wc_get_price_decimals(),
		);

		return $data;

	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}
		return WOO_WALLET_PLUGIN_VERSION;
	}
}
