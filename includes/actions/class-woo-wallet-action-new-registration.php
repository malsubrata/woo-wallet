<?php
/**
 * Action_New_Registration class file.
 *
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Action_New_Registration class.
 *
 * Handles automatic wallet credit upon new user registration.
 */
class Action_New_Registration extends WooWalletAction {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id           = 'new_registration';
		$this->action_title = __( 'New user registration', 'woo-wallet' );
		$this->description  = __( 'Set credit upon new user registration', 'woo-wallet' );
		$this->init_form_fields();
		$this->init_settings();
		// Note: the `user_register` credit is dispatched by
		// Woo_Wallet_Signup_Handler so it also fires for SSO / programmatic
		// signups created before this action class is instantiated.
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters(
			'woo_wallet_action_new_registration_form_fields',
			array(
				'enabled'     => array(
					'title'   => __( 'Enable/Disable', 'woo-wallet' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable auto credit upon user registration', 'woo-wallet' ),
					'default' => 'no',
				),
				'amount'      => array(
					'title'       => __( 'Amount', 'woo-wallet' ),
					'type'        => 'price',
					'description' => __( 'Enter amount which will be credited to the user wallet after registration.', 'woo-wallet' ),
					'default'     => '10',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woo-wallet' ),
					'type'        => 'textarea',
					'description' => __( 'Wallet transaction description that will display as transaction note.', 'woo-wallet' ),
					'default'     => __( 'Balance credited for becoming a member.', 'woo-wallet' ),
					'desc_tip'    => true,
				),
			)
		);
	}
	/**
	 * Process new registration credit.
	 *
	 * @param int $user_id User ID.
	 */
	public function woo_wallet_new_user_registration_credit( $user_id ) {
		if ( $this->is_enabled() && $this->settings['amount'] && apply_filters( 'woo_wallet_new_user_registration_credit', true, $user_id ) ) {
			// Security Improvement: Prevent duplicate credits (Idempotency).
			$already_credited = get_user_meta( $user_id, '_woo_wallet_new_registration_credited', true );
			if ( $already_credited ) {
				return;
			}

			$amount = apply_filters( 'woo_wallet_new_user_registration_credit_amount', $this->settings['amount'], $user_id );

			// Security Improvement: Validate amount.
			$amount = floatval( $amount );
			if ( $amount <= 0 ) {
				return;
			}

			// The configured amount is saved in the store base currency, so
			// credit it against the base currency to skip active-currency
			// conversion in the ledger.
			$transaction_id = woo_wallet()->wallet->credit(
				$user_id,
				$amount,
				sanitize_textarea_field( $this->settings['description'] ),
				array( 'currency' => $this->get_base_currency() )
			);
			if ( $transaction_id ) {
				// Record that the credit has been applied.
				update_user_meta( $user_id, '_woo_wallet_new_registration_credited', 'yes' );
				do_action( 'woo_wallet_action_new_registration_credited', $transaction_id, $user_id, $this );
			}
		}
	}
}
