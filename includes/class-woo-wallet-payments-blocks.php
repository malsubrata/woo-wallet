<?php
/**
 * WooCommerce block checkout support.
 *
 * @package StandaloneTech
 */

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * Wallet Payments Blocks integration
 *
 * @since 1.0.3
 */
final class WOO_Wallet_Payments_Blocks extends AbstractPaymentMethodType {

	/**
	 * The gateway instance.
	 *
	 * @var Woo_Gateway_Wallet_payment
	 */
	private $gateway;

	/**
	 * Payment method name/id/slug.
	 *
	 * @var string
	 */
	protected $name = 'wallet';

	/**
	 * Initializes the payment method type.
	 */
	public function initialize() {
		$this->settings = get_option( 'woocommerce_wallet_settings', array() );
		$this->gateway  = new Woo_Gateway_Wallet_payment();
	}

	/**
	 * Returns if this payment method should be active. If false, the scripts will not be enqueued.
	 *
	 * @return boolean
	 */
	public function is_active() {
		return $this->gateway->is_available();
	}

	/**
	 * Returns an array of scripts/handles to be registered for this payment method.
	 *
	 * @return array
	 */
	public function get_payment_method_script_handles() {
		$script_path       = '/build/payment-method/index.js';
		$script_asset_path = WOO_WALLET_ABSPATH . 'build/payment-method/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => WOO_WALLET_PLUGIN_VERSION,
			);
		$script_url        = woo_wallet()->plugin_url() . $script_path;

		wp_register_script(
			'woo-wallet-payments-blocks',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		if ( function_exists( 'wp_set_script_translations' ) ) {
			wp_set_script_translations( 'woo-wallet-payments-blocks', 'woo-wallet', WOO_WALLET_ABSPATH . 'languages/' );
		}

		return array( 'woo-wallet-payments-blocks' );
	}

	/**
	 * Returns an array of key=>value pairs of data made available to the payment methods script.
	 *
	 * @return array
	 */
	public function get_payment_method_data() {
		return array(
			'title'          => $this->get_setting( 'title' ),
			'description'    => $this->get_setting( 'description' ),
			'supports'       => array_filter( $this->gateway->supports, array( $this->gateway, 'supports' ) ),
			'balance'        => woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ),
			'canMakePayment' => apply_filters( 'woo_wallet_payment_is_available', ( $this->is_active() && is_full_payment_through_wallet() && is_user_logged_in() && ! is_enable_wallet_partial_payment() && ! is_wallet_account_locked() ) ),
		);
	}
}
