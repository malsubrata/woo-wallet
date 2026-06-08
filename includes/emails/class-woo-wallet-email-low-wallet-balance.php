<?php
/**
 * Low wallet balance email.
 *
 * @package    StandaloneTech\TeraWallet
 * @subpackage Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/abstracts/abstract-woo-wallet-email.php';

if ( ! class_exists( 'Woo_Wallet_Email_Low_Wallet_Balance' ) ) {

	/**
	 * Low wallet balance email.
	 */
	class Woo_Wallet_Email_Low_Wallet_Balance extends Woo_Wallet_Email {

		/**
		 * Class constructor.
		 */
		public function __construct() {
			$this->id             = 'low_wallet_balance';
			$this->customer_email = true;
			$this->title          = __( 'Low Wallet Balance', 'woo-wallet' );
			$this->description    = __( 'Notifies the customer to recharge when their wallet balance drops to the threshold set by the admin.', 'woo-wallet' );
			$this->template_html  = 'emails/low-wallet-balance.php';
			$this->template_plain = 'emails/plain/low-wallet-balance.php';
			$this->template_base  = WOO_WALLET_ABSPATH . 'templates/';
			$this->placeholders   = array(
				'{site_title}' => $this->get_blogname(),
			);

			// Call parent constructor.
			parent::__construct();
		}

		/**
		 * Get email subject.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_subject() {
			return __( 'Your {site_title} wallet balance is low.', 'woo-wallet' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Please recharge your wallet to continue using it', 'woo-wallet' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * Only fires when a debit makes the balance *cross* the configured
		 * threshold (i.e. it was above the threshold before this debit and is at
		 * or below it now). This avoids re-notifying the customer on every
		 * subsequent debit while the balance is already low.
		 *
		 * @param int    $user_id User ID.
		 * @param string $type   Transaction type (credit|debit).
		 * @param float  $amount Amount that was just debited. Defaults to 0 for
		 *                       back-compatibility with older callers.
		 */
		public function trigger( $user_id, $type, $amount = 0 ) {
			if ( 'debit' !== $type ) {
				return;
			}

			$current_balance  = woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
			$threshold        = (float) $this->get_option( 'low_wallet_balance_threshold', 0 );
			$previous_balance = $current_balance + (float) $amount;

			/**
			 * Whether the low balance notification should be sent.
			 *
			 * By default it is sent only when the debit crosses the threshold.
			 * When $amount is unknown (legacy callers passing 0) the previous
			 * balance equals the current balance, so the crossing test reduces
			 * to a simple "at or below threshold" check.
			 *
			 * @since 1.6.4
			 * @param bool  $should_send      Whether to send.
			 * @param int   $user_id          User ID.
			 * @param float $current_balance  Balance after the debit.
			 * @param float $threshold        Configured low-balance threshold.
			 */
			$should_send = apply_filters(
				'woo_wallet_should_send_low_balance_email',
				( $current_balance <= $threshold && $previous_balance > $threshold ),
				$user_id,
				$current_balance,
				$threshold
			);

			if ( ! $should_send ) {
				return;
			}

			$this->setup_locale();

			$user = new WP_User( $user_id );

			if ( is_a( $user, 'WP_User' ) ) {
				$this->object    = $user;
				$this->recipient = $user->user_email;

				if ( $this->is_enabled() && $this->get_recipient() ) {
					$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
				}
			}

			$this->restore_locale();
		}

		/**
		 * Low-balance template arguments.
		 *
		 * @since  1.6.4
		 * @param  bool $plain_text Whether the plain text template is rendering.
		 * @return array
		 */
		protected function get_low_balance_template_args( $plain_text = false ) {
			$user_id         = isset( $this->object->ID ) ? $this->object->ID : 0;
			$current_balance = $user_id ? woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' ) : 0;

			if ( $plain_text ) {
				$current_balance = number_format( (float) $current_balance, wc_get_price_decimals(), '.', '' );
			}

			return array_merge(
				$this->get_common_template_args( $plain_text ),
				array(
					'current_balance' => $current_balance,
				)
			);
		}

		/**
		 * Get content html.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_html() {
			return wc_get_template_html(
				$this->template_html,
				$this->get_low_balance_template_args( false ),
				'woo-wallet',
				$this->template_base
			);
		}

		/**
		 * Get content plain.
		 *
		 * @access public
		 * @return string
		 */
		public function get_content_plain() {
			return wc_get_template_html(
				$this->template_plain,
				$this->get_low_balance_template_args( true ),
				'woo-wallet',
				$this->template_base
			);
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'                      => array(
					'title'   => __( 'Enable/Disable', 'woo-wallet' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'woo-wallet' ),
					'default' => 'yes',
				),
				'low_wallet_balance_threshold' => array(
					'title'       => __( 'Threshold for low wallet balance', 'woo-wallet' ),
					'type'        => 'number',
					'desc_tip'    => true,
					'description' => __( 'A minimum balance at or below which customers will be notified to recharge their wallet.', 'woo-wallet' ),
					'default'     => 0,
				),
				'subject'                      => array(
					'title'       => __( 'Subject', 'woo-wallet' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of available placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}</code>' ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'                      => array(
					'title'       => __( 'Email heading', 'woo-wallet' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of available placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}</code>' ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content'           => $this->get_additional_content_field(),
				'email_type'                   => array(
					'title'       => __( 'Email type', 'woo-wallet' ),
					'type'        => 'select',
					'description' => __( 'Choose which format of email to send.', 'woo-wallet' ),
					'default'     => 'html',
					'class'       => 'email_type wc-enhanced-select',
					'options'     => $this->get_email_type_options(),
					'desc_tip'    => true,
				),
			);
		}
	}
}

return new Woo_Wallet_Email_Low_Wallet_Balance();
