<?php
/**
 * Daily visits action class.
 *
 * @package woo-wallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Action_Daily_Visits extends WooWalletAction {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id           = 'daily_visits';
		$this->action_title = __( 'Daily visits', 'woo-wallet' );
		$this->description  = __( 'Set credit for daily visits', 'woo-wallet' );
		$this->init_form_fields();
		$this->init_settings();
		// Actions.
		add_action( 'wp', array( $this, 'woo_wallet_site_visit_credit' ), 100 );
		add_action( 'wp_footer', array( $this, 'render_notice' ) );
		add_action( 'woo_wallet_before_my_wallet_content', array( $this, 'render_promo' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'      => array(
				'title'   => __( 'Enable/Disable', 'woo-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable credit for daily visits.', 'woo-wallet' ),
				'default' => 'no',
			),
			'amount'       => array(
				'title'       => __( 'Amount', 'woo-wallet' ),
				'type'        => 'price',
				'description' => __( 'Credited once per customer per day. Leave empty to reward nothing.', 'woo-wallet' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'exclude_role' => array(
				'title'       => __( 'Exclude user role', 'woo-wallet' ),
				'description' => __( 'This option lets you limit which user role you want to exclude.', 'woo-wallet' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'css'         => 'min-width: 350px;',
				'desc_tip'    => true,
				'options'     => $this->get_editable_role_options(),
			),
			'require_paid_order' => array(
				'title'   => __( 'First purchase', 'woo-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Only reward customers with at least one paid order', 'woo-wallet' ),
				'default' => 'no',
				'show_if' => array(
					'field'  => 'enabled',
					'equals' => 'on',
				),
			),
			'cap_amount'   => array(
				'title'       => __( 'Reward cap', 'woo-wallet' ),
				'type'        => 'price',
				'description' => __( 'Maximum daily-visit rewards per customer. Leave empty for no cap.', 'woo-wallet' ),
				'default'     => '',
				'desc_tip'    => true,
				'half'        => true,
				'show_if'     => array(
					'field'  => 'enabled',
					'equals' => 'on',
				),
			),
			'cap_period'   => array(
				'title'       => __( 'Cap period', 'woo-wallet' ),
				'type'        => 'select',
				'description' => __( 'When the cap starts over.', 'woo-wallet' ),
				'default'     => 'month',
				'desc_tip'    => true,
				'half'        => true,
				'options'     => array(
					'month'    => __( 'Per calendar month', 'woo-wallet' ),
					'lifetime' => __( 'Lifetime', 'woo-wallet' ),
				),
				'show_if'     => array(
					'field'  => 'enabled',
					'equals' => 'on',
				),
			),
			'description'  => array(
				'title'       => __( 'Description', 'woo-wallet' ),
				'type'        => 'textarea',
				'description' => __( 'Wallet transaction description that will display as transaction note.', 'woo-wallet' ),
				'default'     => __( 'Balance credited visiting site.', 'woo-wallet' ),
				'desc_tip'    => true,
			),
		);
	}
	/**
	 * Get all editable roles.
	 */
	public function get_editable_role_options() {
		$role_options   = array();
		$editable_roles = array_reverse( wp_roles()->roles );
		foreach ( $editable_roles as $role => $details ) {
			$name                  = translate_user_role( $details['name'] );
			$role_options[ $role ] = $name;
		}
		return $role_options;
	}
	/**
	 * Configured reward in the store base currency; 0 when disabled or unset.
	 *
	 * @return float
	 */
	private function get_reward_amount(): float {
		if ( ! $this->is_enabled() ) {
			return 0.0;
		}
		return max( 0.0, (float) ( $this->settings['amount'] ?? 0 ) );
	}

	/**
	 * Today's date in the store timezone.
	 *
	 * @return string Y-m-d.
	 */
	private function today(): string {
		return wp_date( 'Y-m-d', null, wp_timezone() );
	}

	/**
	 * User meta key holding the earned total for the active cap period.
	 *
	 * @return string
	 */
	private function earned_meta_key(): string {
		if ( 'lifetime' === ( $this->settings['cap_period'] ?? 'month' ) ) {
			return '_woo_wallet_daily_visit_earned_lifetime';
		}
		return '_woo_wallet_daily_visit_earned_' . wp_date( 'Y-m', null, wp_timezone() );
	}

	/**
	 * Would paying $amount push the user over the configured cap?
	 *
	 * @param int   $user_id User ID.
	 * @param float $amount  Reward amount.
	 * @return bool
	 */
	private function exceeds_cap( int $user_id, float $amount ): bool {
		$cap = (float) ( $this->settings['cap_amount'] ?? 0 );
		if ( $cap <= 0 ) {
			return false;
		}
		$earned = (float) get_user_meta( $user_id, $this->earned_meta_key(), true );
		// Round away float dust so 0.1 + 0.2 vs 0.3 doesn't flip the result.
		return round( $earned + $amount, 6 ) > round( $cap, 6 );
	}

	/**
	 * Has the user got a paid, non-top-up order? Only a positive result is cached.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	private function has_paid_order( int $user_id ): bool {
		if ( 'yes' === get_user_meta( $user_id, '_woo_wallet_daily_visit_qualified', true ) ) {
			return true;
		}
		$page = 1;
		do {
			$orders = wc_get_orders(
				array(
					'customer_id' => $user_id,
					'status'      => wc_get_is_paid_statuses(),
					'limit'       => 20,
					'page'        => $page++,
					'orderby'     => 'date',
					'order'       => 'ASC',
				)
			);
			foreach ( $orders as $order ) {
				if ( ! is_wallet_rechargeable_order( $order ) ) {
					update_user_meta( $user_id, '_woo_wallet_daily_visit_qualified', 'yes' );
					return true;
				}
			}
		} while ( count( $orders ) === 20 );
		return false;
	}

	/**
	 * Role, purchase-rule and cap checks (not the once-a-day check).
	 *
	 * @param int   $user_id User ID.
	 * @param float $amount  Reward amount.
	 * @return bool
	 */
	private function is_eligible( int $user_id, float $amount ): bool {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}
		$eligible = true;
		if ( ! empty( $this->settings['exclude_role'] ) && ! array_diff( $user->roles, (array) $this->settings['exclude_role'] ) ) {
			$eligible = false;
		} elseif ( 'yes' === ( $this->settings['require_paid_order'] ?? 'no' ) && ! $this->has_paid_order( $user_id ) ) {
			$eligible = false;
		} elseif ( $this->exceeds_cap( $user_id, $amount ) ) {
			$eligible = false;
		}
		return (bool) apply_filters( 'woo_wallet_daily_visit_is_eligible', $eligible, $user_id );
	}

	/**
	 * Credit site visit: once per user per store-timezone calendar day.
	 */
	public function woo_wallet_site_visit_credit() {
		$amount = $this->get_reward_amount();
		if ( $amount <= 0 || ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		$today   = $this->today();
		if ( get_user_meta( $user_id, '_woo_wallet_daily_visit_last', true ) === $today ) {
			return; // Cheap exit for the common case; re-checked under the lock.
		}

		global $wpdb;
		$lock = 'woo_wallet_daily_visit_' . $user_id;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK( %s, 0 )', $lock ) ) ) {
			return; // Another request is rewarding this user right now.
		}
		try {
			wp_cache_delete( $user_id, 'user_meta' );
			if ( get_user_meta( $user_id, '_woo_wallet_daily_visit_last', true ) === $today ) {
				return;
			}
			if ( ! $this->is_eligible( $user_id, $amount ) || ! apply_filters( 'woo_wallet_site_visit_credit', true ) ) {
				return;
			}
			// The configured amount is saved in the store base currency, so
			// credit it against the base currency to skip active-currency
			// conversion in the ledger.
			$transaction_id = woo_wallet()->wallet->credit(
				$user_id,
				$amount,
				sanitize_textarea_field( $this->settings['description'] ?? '' ),
				array(
					'currency' => $this->get_base_currency(),
					'category' => 'engagement_reward',
				)
			);
			if ( $transaction_id ) {
				update_user_meta( $user_id, '_woo_wallet_daily_visit_last', $today );
				$key = $this->earned_meta_key();
				update_user_meta( $user_id, $key, round( (float) get_user_meta( $user_id, $key, true ) + $amount, 6 ) );
				update_user_meta( $user_id, '_woo_wallet_daily_visit_notice', $amount );
			}
		} finally {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK( %s )', $lock ) );
		}
	}

	/**
	 * Show the one-time "you earned" toast, then clear the flag.
	 */
	public function render_notice() {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		$amount  = (float) get_user_meta( $user_id, '_woo_wallet_daily_visit_notice', true );
		if ( $amount <= 0 || ! apply_filters( 'woo_wallet_show_daily_visit_notice', true, $user_id ) ) {
			return;
		}
		delete_user_meta( $user_id, '_woo_wallet_daily_visit_notice' );
		woo_wallet()->get_template(
			'daily-visit-notice.php',
			array(
				'mode'       => 'toast',
				'amount'     => wc_price( $amount, woo_wallet_wc_price_args( $user_id ) ),
				'wallet_url' => wc_get_account_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ) ),
			)
		);
	}

	/**
	 * Promote the reward at the top of the My Wallet page.
	 */
	public function render_promo() {
		$amount = $this->get_reward_amount();
		if ( $amount <= 0 || ! is_user_logged_in() ) {
			return;
		}
		$user_id = get_current_user_id();
		// Cap reached, excluded role or no purchase yet: say nothing.
		if ( ! $this->is_eligible( $user_id, $amount ) ) {
			return;
		}
		woo_wallet()->get_template(
			'daily-visit-notice.php',
			array(
				'mode'   => get_user_meta( $user_id, '_woo_wallet_daily_visit_last', true ) === $this->today() ? 'collected' : 'pending',
				'amount' => wc_price( $amount, woo_wallet_wc_price_args( $user_id ) ),
			)
		);
	}
}
