<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Action_Referrals extends WooWalletAction {

	/**
	 * Referral base.
	 *
	 * @var string
	 */
	public $referral_handel = null;
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id           = 'referrals';
		$this->action_title = __( 'Referrals', 'woo-wallet' );
		$this->description  = __( 'Reward customers who refer visitors and new sign-ups.', 'woo-wallet' );
		$this->init_form_fields();
		$this->init_settings();
		// Actions.
		add_action( 'wp_loaded', array( $this, 'load_woo_wallet_referral' ) );
		// Note: the `user_register` signup bonus is dispatched by
		// Woo_Wallet_Signup_Handler so it also fires for SSO / programmatic
		// signups created before this action class is instantiated.
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters(
			'woo_wallet_action_referrals_form_fields',
			array(
				'enabled'                           => array(
					'title'   => __( 'Enable referral rewards', 'woo-wallet' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable referral rewards', 'woo-wallet' ),
					'default' => 'no',
				),
				array(
					'title' => __( 'How referral rewards work', 'woo-wallet' ),
					'type'  => 'title',
					'desc'  => __( 'Reward existing customers when people they refer visit your store or sign up. Customers share a personal link from their Wallet → Referrals page. Only the referrer is rewarded.', 'woo-wallet' ),
					'id'    => 'referral_intro',
				),
				array(
					'title' => __( 'Reward for referred visits', 'woo-wallet' ),
					'type'  => 'title',
					'desc'  => __( 'Credit the referrer when someone who used their link visits your store.', 'woo-wallet' ),
					'id'    => 'referring_visitors',
				),
				'referring_visitors_amount'         => array(
					'title'       => __( 'Reward amount', 'woo-wallet' ),
					'type'        => 'price',
					'description' => __( 'Credited to the referrer for each referred visitor, in your store base currency.', 'woo-wallet' ),
					'default'     => '10',
					'desc_tip'    => true,
				),
				'referring_visitors_limit_duration' => array(
					'title'       => __( 'Limit period', 'woo-wallet' ),
					'type'        => 'select',
					'description' => __( 'How often the rewards cap resets.', 'woo-wallet' ),
					'desc_tip'    => true,
					'options'     => array(
						'0'     => __( 'No limit', 'woo-wallet' ),
						'day'   => __( 'Per day', 'woo-wallet' ),
						'week'  => __( 'Per week', 'woo-wallet' ),
						'month' => __( 'Per month', 'woo-wallet' ),
					),
					'half'        => true,
				),
				'referring_visitors_limit'          => array(
					'title'       => __( 'Maximum rewards', 'woo-wallet' ),
					'type'        => 'number',
					'description' => __( 'Most visit rewards one referrer can earn per period.', 'woo-wallet' ),
					'desc_tip'    => true,
					'default'     => 0,
					'half'        => true,
					'show_if'     => array(
						'field'  => 'referring_visitors_limit_duration',
						'equals' => array( 'day', 'week', 'month' ),
					),
				),
				'referring_visitors_description'    => array(
					'title'       => __( 'Transaction note', 'woo-wallet' ),
					'type'        => 'textarea',
					'description' => __( 'Shown to the referrer on this wallet transaction.', 'woo-wallet' ),
					'default'     => __( 'Balance credited for referring a visitor', 'woo-wallet' ),
					'desc_tip'    => true,
				),
				array(
					'title' => __( 'Reward for referred sign-ups', 'woo-wallet' ),
					'type'  => 'title',
					'desc'  => __( 'Credit the referrer when someone who used their link creates an account.', 'woo-wallet' ),
					'id'    => 'referring_signups',
				),
				'referring_signups_amount'          => array(
					'title'       => __( 'Reward amount', 'woo-wallet' ),
					'type'        => 'price',
					'description' => __( 'Credited to the referrer for each referred sign-up, in your store base currency.', 'woo-wallet' ),
					'default'     => '10',
					'desc_tip'    => true,
				),
				'referral_order_amount'             => array(
					'title'             => __( 'Minimum Spend', 'woo-wallet' ),
					'type'              => 'number',
					'description'       => __( "Credit the referrer only after the referred customer's total lifetime spend reaches this amount. Leave 0 to credit the referrer immediately on signup.", 'woo-wallet' ),
					'default'           => 0,
					'desc_tip'          => true,
					'custom_attributes' => array( 'min' => 0 ),
				),
				'referring_signups_limit_duration'  => array(
					'title'       => __( 'Limit period', 'woo-wallet' ),
					'type'        => 'select',
					'description' => __( 'How often the rewards cap resets.', 'woo-wallet' ),
					'desc_tip'    => true,
					'options'     => array(
						'0'     => __( 'No limit', 'woo-wallet' ),
						'day'   => __( 'Per day', 'woo-wallet' ),
						'week'  => __( 'Per week', 'woo-wallet' ),
						'month' => __( 'Per month', 'woo-wallet' ),
					),
					'half'        => true,
				),
				'referring_signups_limit'           => array(
					'title'       => __( 'Maximum rewards', 'woo-wallet' ),
					'type'        => 'number',
					'description' => __( 'Most sign-up rewards one referrer can earn per period.', 'woo-wallet' ),
					'desc_tip'    => true,
					'default'     => 0,
					'half'        => true,
					'show_if'     => array(
						'field'  => 'referring_signups_limit_duration',
						'equals' => array( 'day', 'week', 'month' ),
					),
				),
				'referring_signups_description'     => array(
					'title'       => __( 'Transaction note', 'woo-wallet' ),
					'type'        => 'textarea',
					'description' => __( 'Shown to the referrer on this wallet transaction.', 'woo-wallet' ),
					'default'     => __( 'Balance credited for referring a new member', 'woo-wallet' ),
					'desc_tip'    => true,
				),
				array(
					'title' => __( 'Referral link format', 'woo-wallet' ),
					'type'  => 'title',
					'desc'  => __( "Choose what a customer's referral link contains.", 'woo-wallet' ),
					'id'    => 'referring_links',
				),
				'referal_link'                      => array(
					'title'       => __( 'Link identifier', 'woo-wallet' ),
					'type'        => 'select',
					'description' => __( 'Numeric ID is safest. Usernames are friendlier but expose the username publicly.', 'woo-wallet' ),
					'desc_tip'    => true,
					'options'     => array(
						'id'       => __( 'Numeric referral ID', 'woo-wallet' ),
						'username' => __( 'Usernames as referral ID', 'woo-wallet' ),
					),
				),
			)
		);
	}
	/**
	 * Load wallet referrals.
	 */
	public function load_woo_wallet_referral() {
		if ( $this->is_enabled() ) {
			$this->referral_handel = apply_filters( 'woo_wallet_referral_handel', 'wwref' );
			add_filter( 'woo_wallet_nav_menu_items', array( $this, 'add_referral_nav_menu' ), 10, 2 );
			add_action( 'woo_wallet_referrals_content', array( $this, 'woo_wallet_referrals_content' ) );
			$this->init_referrals();
			add_action( 'wp', array( $this, 'init_referral_visit' ), 105 );
			add_action( 'woocommerce_order_status_changed', array( $this, 'woo_wallet_credit_referring_signup' ), 100 );
		}
	}
	/**
	 * Add referals in the nav menu
	 *
	 * @param array $nav_menu nav_menu.
	 * @param bool  $is_rendred_from_myaccount is_rendred_from_myaccount.
	 * @return array
	 */
	public function add_referral_nav_menu( $nav_menu, $is_rendred_from_myaccount ) {
		$nav_menu['referrals'] = array(
			'title' => apply_filters( 'woo_wallet_account_referrals_menu_title', __( 'Referrals', 'woo-wallet' ) ),
			'url'   => $is_rendred_from_myaccount ? esc_url( wc_get_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ), 'referrals', wc_get_page_permalink( 'myaccount' ) ) ) : add_query_arg( 'wallet_action', 'referrals' ),
			'icon'  => 'dashicons dashicons-groups',
		);
		return $nav_menu;
	}
	/**
	 * Referral page content.
	 *
	 * @return void
	 */
	public function woo_wallet_referrals_content() {
		global $wp;
		if ( apply_filters( 'woo_wallet_is_enable_referrals', true ) && ( ( isset( $wp->query_vars['woo-wallet'] ) && 'referrals' === $wp->query_vars['woo-wallet'] ) || ( isset( $_GET['wallet_action'] ) && 'referrals' === $_GET['wallet_action'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			woo_wallet()->get_template(
				'referrals.php',
				array(
					'settings' => $this->settings,
					'referral' => $this,
				)
			);
		}
	}
	/**
	 * Init referral options.
	 *
	 * @return void
	 */
	public function init_referrals() {
		if ( isset( $_GET[ $this->referral_handel ] ) && ! empty( $_GET[ $this->referral_handel ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! headers_sent() && did_action( 'wp_loaded' ) ) {
				wc_setcookie( 'woo_wallet_referral', sanitize_text_field( wp_unslash( $_GET[ $this->referral_handel ] ) ), time() + DAY_IN_SECONDS ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
	}
	/**
	 * Get referral user.
	 *
	 * Resolves the referrer from the live `woo_wallet_referral` cookie. When a
	 * signup user ID is supplied (deferred / cross-request signup processing,
	 * where the cookie may already be gone) the referral code captured at
	 * registration time — `_woo_wallet_referral_at_signup` user meta — is used
	 * as a fallback.
	 *
	 * @param int $signup_user_id Optional. The newly-registered user being
	 *                            processed, used for self-referral checks and
	 *                            the stored-code fallback.
	 * @return bool|WP_User
	 */
	public function get_referral_user( $signup_user_id = 0 ) {
		$signup_user_id = absint( $signup_user_id );
		$referral_code  = '';

		if ( isset( $_COOKIE['woo_wallet_referral'] ) && '' !== $_COOKIE['woo_wallet_referral'] ) {
			$referral_code = sanitize_text_field( wp_unslash( $_COOKIE['woo_wallet_referral'] ) );
		} elseif ( $signup_user_id ) {
			$stored = get_user_meta( $signup_user_id, '_woo_wallet_referral_at_signup', true );
			if ( $stored ) {
				$referral_code = sanitize_text_field( $stored );
			}
		}

		if ( '' === $referral_code ) {
			return false;
		}

		if ( 'id' === $this->settings['referal_link'] ) {
			$user = get_user_by( 'ID', $referral_code );
		} else {
			$user = get_user_by( 'login', $referral_code );
		}

		// Reject a missing referrer and self-referral.
		$self_id = $signup_user_id ? $signup_user_id : get_current_user_id();
		if ( ! $user || $self_id === $user->ID ) {
			return false;
		}

		return apply_filters( 'woo_wallet_referral_user', $user, $this );
	}
	/**
	 * Init referral visitor.
	 *
	 * @return void
	 */
	public function init_referral_visit() {
		$referral_user = $this->get_referral_user();
		if ( ! $referral_user ) {
			return;
		}
		$referral_visit_amount = apply_filters( 'woo_wallet_referring_visitor_amount', $this->settings['referring_visitors_amount'], $referral_user->ID );
		if ( $referral_visit_amount && $this->get_referral_user() ) {
			if ( apply_filters( 'woo_wallet_restrict_referral_visit_by_cookie', isset( $_COOKIE[ 'woo_wallet_referral_visit_credited_' . $referral_user->ID ] ), $this ) ) {
				return;
			}
			$limit                        = $this->settings['referring_visitors_limit_duration'];
			$referral_visitor_count       = get_user_meta( $referral_user->ID, '_woo_wallet_referring_visitor', true ) ? get_user_meta( $referral_user->ID, '_woo_wallet_referring_visitor', true ) : 0;
			$woo_wallet_referring_earning = get_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', true ) ? get_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', true ) : 0;
			if ( $limit ) {
				$woo_wallet_referral_visit_count = get_transient( 'woo_wallet_referral_visit_' . $referral_user->ID ) ? get_transient( 'woo_wallet_referral_visit_' . $referral_user->ID ) : 0;
				if ( $woo_wallet_referral_visit_count < $this->settings['referring_visitors_limit'] ) {
					if ( ! headers_sent() && did_action( 'wp_loaded' ) ) {
						$transiant_duration = DAY_IN_SECONDS;
						if ( 'week' === $limit ) {
							$transiant_duration = WEEK_IN_SECONDS;
						} elseif ( 'month' === $limit ) {
							$transiant_duration = MONTH_IN_SECONDS;
						}
						set_transient( 'woo_wallet_referral_visit_' . $referral_user->ID, $woo_wallet_referral_visit_count + 1, $transiant_duration );
						// The configured amount is saved in the store base
						// currency, so credit it against the base currency to
						// skip active-currency conversion in the ledger.
						$transaction_id = woo_wallet()->wallet->credit( $referral_user->ID, $referral_visit_amount, $this->settings['referring_visitors_description'], array( 'currency' => $this->get_base_currency() ) );
						update_user_meta( $referral_user->ID, '_woo_wallet_referring_visitor', $referral_visitor_count + 1 );
						update_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', $woo_wallet_referring_earning + $referral_visit_amount );
						do_action( 'woo_wallet_after_referral_visit', $transaction_id, $this );
					}
				}
			} else {
				// Configured amount is in base currency — credit in base.
				$transaction_id = woo_wallet()->wallet->credit( $referral_user->ID, $referral_visit_amount, $this->settings['referring_visitors_description'], array( 'currency' => $this->get_base_currency() ) );
				update_user_meta( $referral_user->ID, '_woo_wallet_referring_visitor', $referral_visitor_count + 1 );
				update_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', $woo_wallet_referring_earning + $referral_visit_amount );
				do_action( 'woo_wallet_after_referral_visit', $transaction_id, $this );
			}
			wc_setcookie( 'woo_wallet_referral_visit_credited_' . $referral_user->ID, true, time() + DAY_IN_SECONDS );
		}
	}
	/**
	 * Process wallet referral signup.
	 *
	 * @param int $user_id user_id.
	 * @return void
	 */
	public function woo_wallet_referring_signup( $user_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		$referral_user = $this->get_referral_user( $user_id );
		if ( ! $referral_user ) {
			return;
		}
		// Always record attribution so the signup-limit and minimum-spend
		// gating never loses the referrer link.
		if ( ! get_user_meta( $user_id, '_referral_user_id', true ) ) {
			update_user_meta( $user_id, '_referral_user_id', $referral_user->ID );
		}
		$minimum_spent = isset( $this->settings['referral_order_amount'] ) ? $this->settings['referral_order_amount'] : 0;
		if ( ! $minimum_spent ) {
			$this->credit_referring_signup( $user_id );
		}
	}
	/**
	 * Credit referral signup.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function woo_wallet_credit_referring_signup( $order_id ) {
		$order            = wc_get_order( $order_id );
		$customer_id      = $order->get_customer_id();
		$referral_user_id = get_user_meta( $customer_id, '_referral_user_id', true );
		if ( ! $referral_user_id || get_user_meta( $customer_id, '_woo_wallet_referral_signup_credited', true ) ) {
			return;
		}
		$customer_total_spent = wc_get_customer_total_spent( $customer_id );
		$minimum_spent        = isset( $this->settings['referral_order_amount'] ) ? $this->settings['referral_order_amount'] : 0;
		if ( $order->is_paid() && $customer_total_spent >= $minimum_spent ) {
			$this->credit_referring_signup( $customer_id, $order_id );
		}
	}
	/**
	 * Credit referrals.
	 *
	 * @param integer $customer_id customer_id.
	 * @param integer $order_id order_id.
	 * @return void
	 */
	public function credit_referring_signup( $customer_id, $order_id = 0 ) {
		$referral_user_id = get_user_meta( $customer_id, '_referral_user_id', true );
		if ( ! $referral_user_id || get_user_meta( $customer_id, '_woo_wallet_referral_signup_credited', true ) ) {
			return;
		}
		// Guard against a deleted referrer — `new WP_User()` on a missing ID
		// yields a user with ID 0, which would credit the wrong account.
		$referral_user = get_user_by( 'id', $referral_user_id );
		if ( ! $referral_user ) {
			return;
		}
		// Signup limit — gate on actual credits, not registrations.
		$limit         = $this->settings['referring_signups_limit_duration'];
		$transient_key = 'woo_wallet_referral_signup_' . $referral_user->ID;
		if ( $limit && (int) get_transient( $transient_key ) >= (int) $this->settings['referring_signups_limit'] ) {
			return;
		}
		$referral_signup_count        = get_user_meta( $referral_user->ID, '_woo_wallet_referring_signup', true ) ? get_user_meta( $referral_user->ID, '_woo_wallet_referring_signup', true ) : 0;
		$woo_wallet_referring_earning = get_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', true ) ? get_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', true ) : 0;
		$referral_signup_amount       = apply_filters( 'woo_wallet_referring_signup_amount', $this->settings['referring_signups_amount'], $referral_user->ID, $customer_id, $order_id );
		if ( $referral_signup_amount ) {
			// Configured amount is in base currency — credit in base.
			$transaction_id = woo_wallet()->wallet->credit( $referral_user->ID, $referral_signup_amount, $this->settings['referring_signups_description'], array( 'currency' => $this->get_base_currency() ) );
			update_user_meta( $referral_user->ID, '_woo_wallet_referring_signup', $referral_signup_count + 1 );
			update_user_meta( $referral_user->ID, '_woo_wallet_referring_earning', $woo_wallet_referring_earning + $referral_signup_amount );
			update_user_meta( $customer_id, '_woo_wallet_referral_signup_credited', true );
			// Count this credited signup against the configured limit.
			if ( $limit ) {
				$transiant_duration = DAY_IN_SECONDS;
				if ( 'week' === $limit ) {
					$transiant_duration = WEEK_IN_SECONDS;
				} elseif ( 'month' === $limit ) {
					$transiant_duration = MONTH_IN_SECONDS;
				}
				set_transient( $transient_key, (int) get_transient( $transient_key ) + 1, $transiant_duration );
			}
			do_action( 'woo_wallet_after_referral_signup', $transaction_id, $customer_id, $this, $order_id );
		}
	}
}
