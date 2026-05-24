<?php
/**
 * Signup handler.
 *
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Woo_Wallet_Signup_Handler class.
 *
 * The earning-action classes (new registration, referrals) register their
 * `user_register` listeners inside constructors that only run on
 * `woocommerce_init`. SSO / SAML / social-login plugins create the WordPress
 * user earlier in the request than `woocommerce_init` fires, so those
 * listeners miss the event entirely and the signup bonuses are never credited.
 *
 * This handler is instantiated at plugin-load time — long before
 * `woocommerce_init` — and registers a WooCommerce-independent `user_register`
 * capture. The actual crediting is deferred to a point where the earning-action
 * registry is guaranteed to be loaded, so a bonus is credited regardless of
 * which code path created the user (front-end form, SSO, REST API, WP-CLI or a
 * programmatic `wp_insert_user()`).
 */
class Woo_Wallet_Signup_Handler {

	/**
	 * User IDs registered during the current request that still need processing.
	 *
	 * @var int[]
	 */
	private $pending = array();

	/**
	 * Constructor — registers the capture and drain hooks.
	 */
	public function __construct() {
		add_action( 'user_register', array( $this, 'capture_signup' ), 1 );
		// Drains in-request captures once `woocommerce_loaded_callback` (priority
		// 10 on the same hook) has built the earning-action registry.
		add_action( 'woocommerce_init', array( $this, 'drain_request_pending' ), 99 );
		// Cross-request safety net for SSO requests that `exit` early, plus
		// REST / WP-CLI signups — the bonus lands on the user's next page view.
		add_action( 'wp_login', array( $this, 'drain_user_on_login' ), 10, 2 );
		add_action( 'wp', array( $this, 'drain_current_user' ) );
		add_action( 'admin_init', array( $this, 'drain_current_user' ) );
	}

	/**
	 * Capture a freshly-registered user.
	 *
	 * Runs on `user_register` at priority 1, registered at plugin-load time, so
	 * it fires for every user-creation path including SSO and programmatic
	 * `wp_insert_user()` calls.
	 *
	 * @param int $user_id New user ID.
	 */
	public function capture_signup( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		// Mark the user as awaiting earning-action processing.
		update_user_meta( $user_id, '_woo_wallet_signup_pending', time() );

		// The referral cookie is only reliably present in this request; persist
		// it so a cross-request drain can still resolve the referrer.
		if ( isset( $_COOKIE['woo_wallet_referral'] ) && '' !== $_COOKIE['woo_wallet_referral'] ) {
			update_user_meta( $user_id, '_woo_wallet_referral_at_signup', sanitize_text_field( wp_unslash( $_COOKIE['woo_wallet_referral'] ) ) );
		}

		$this->pending[] = $user_id;

		// Fast path: if the earning-action registry is already available
		// (normal front-end registration), credit immediately.
		if ( $this->registry_ready() ) {
			$this->process( $user_id );
		}
	}

	/**
	 * Drain users captured earlier in this request, once `woocommerce_init`
	 * has loaded the earning-action registry.
	 */
	public function drain_request_pending() {
		foreach ( $this->pending as $user_id ) {
			$this->process( $user_id );
		}
	}

	/**
	 * Process a pending signup on login — covers SSO requests that `exit`
	 * before the rest of the request runs.
	 *
	 * @param string       $user_login Username.
	 * @param WP_User|null $user       Logged-in user object.
	 */
	public function drain_user_on_login( $user_login, $user = null ) {
		if ( $user instanceof WP_User ) {
			$this->process( $user->ID );
		}
	}

	/**
	 * Process a pending signup for the current logged-in user — the
	 * cross-request safety net for REST / WP-CLI / early-exit SSO requests.
	 */
	public function drain_current_user() {
		$user_id = get_current_user_id();
		if ( $user_id ) {
			$this->process( $user_id );
		}
	}

	/**
	 * Whether the earning-action registry can be used.
	 *
	 * @return bool
	 */
	private function registry_ready() {
		return class_exists( 'WC_Settings_API' ) && class_exists( 'WOO_Wallet_Actions' );
	}

	/**
	 * Run the earning-action handlers for a pending user.
	 *
	 * Idempotent: the underlying handlers guard against duplicate credits, and
	 * the `_woo_wallet_signup_pending` marker is cleared once processed.
	 *
	 * @param int $user_id User ID.
	 */
	public function process( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return;
		}

		// Only process users still flagged as pending.
		if ( ! get_user_meta( $user_id, '_woo_wallet_signup_pending', true ) ) {
			return;
		}

		if ( ! $this->registry_ready() ) {
			return;
		}

		$actions = WOO_Wallet_Actions::instance()->actions;
		if ( ! is_array( $actions ) ) {
			return;
		}

		if ( isset( $actions['new_registration'] ) && is_callable( array( $actions['new_registration'], 'woo_wallet_new_user_registration_credit' ) ) ) {
			$actions['new_registration']->woo_wallet_new_user_registration_credit( $user_id );
		}

		if ( isset( $actions['referrals'] ) && is_callable( array( $actions['referrals'], 'woo_wallet_referring_signup' ) ) ) {
			$actions['referrals']->woo_wallet_referring_signup( $user_id );
		}

		delete_user_meta( $user_id, '_woo_wallet_signup_pending' );

		do_action( 'woo_wallet_signup_processed', $user_id );
	}
}
