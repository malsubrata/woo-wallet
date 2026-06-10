<?php
/**
 * New wallet transaction email.
 *
 * @package    StandaloneTech\TeraWallet
 * @subpackage Emails
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/abstracts/abstract-woo-wallet-email.php';

if ( ! class_exists( 'Woo_Wallet_Email_New_Transaction' ) ) {

	/**
	 * New wallet transaction email.
	 */
	class Woo_Wallet_Email_New_Transaction extends Woo_Wallet_Email {

		/**
		 * Transaction ID.
		 *
		 * @var int
		 */
		public $transaction_id;

		/**
		 * Transaction type (credit|debit).
		 *
		 * @var string
		 */
		public $type;

		/**
		 * Transaction amount.
		 *
		 * @var float
		 */
		public $amount = 0;

		/**
		 * Transaction details.
		 *
		 * @var string
		 */
		public $details;

		/**
		 * Class constructor.
		 */
		public function __construct() {
			$this->id             = 'new_wallet_transaction';
			$this->customer_email = true;
			$this->title          = __( 'New wallet transaction', 'woo-wallet' );
			$this->description    = __( 'New wallet transaction emails are sent to the customer when their wallet is credited or debited.', 'woo-wallet' );
			$this->template_html  = 'emails/user-new-transaction.php';
			$this->template_plain = 'emails/plain/user-new-transaction.php';
			$this->template_base  = WOO_WALLET_ABSPATH . 'templates/';
			$this->placeholders   = array(
				'{site_title}'       => $this->get_blogname(),
				'{transaction_date}' => '',
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
			return __( 'Your {site_title} wallet transaction from {transaction_date}', 'woo-wallet' );
		}

		/**
		 * Get email heading.
		 *
		 * @since  3.1.0
		 * @return string
		 */
		public function get_default_heading() {
			return __( 'Your wallet has a new transaction', 'woo-wallet' );
		}

		/**
		 * Trigger the sending of this email.
		 *
		 * @param int $transaction_id Transaction ID.
		 */
		public function trigger( $transaction_id ) {

			$transaction = get_wallet_transaction( $transaction_id );
			if ( ! $transaction ) {
				return;
			}

			$this->setup_locale();

			$user = new WP_User( $transaction->user_id );

			if ( is_a( $user, 'WP_User' ) ) {
				$this->object                             = $user;
				$this->transaction_id                     = $transaction->transaction_id;
				$this->type                               = $transaction->type;
				$this->amount                             = $transaction->amount;
				$this->details                            = $transaction->details;
				$this->recipient                          = $user->user_email;
				$this->placeholders['{transaction_date}'] = date_i18n( wc_date_format() );

				if ( $this->is_enabled() && $this->get_recipient() ) {
					$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
				}
			}

			$this->restore_locale();
		}

		/**
		 * Transaction-specific template arguments.
		 *
		 * @since  1.6.4
		 * @param  bool $plain_text Whether the plain text template is rendering.
		 * @return array
		 */
		protected function get_transaction_template_args( $plain_text = false ) {
			$user_id         = isset( $this->object->ID ) ? $this->object->ID : 0;
			$current_balance = $user_id ? woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' ) : 0;

			$args = array(
				'transaction_id'  => $this->transaction_id,
				'type'            => $this->type,
				'details'         => $this->details,
				'current_balance' => $current_balance,
			);

			if ( $plain_text ) {
				$args['amount']          = number_format( (float) $this->amount, wc_get_price_decimals(), '.', '' );
				$args['current_balance'] = number_format( (float) $current_balance, wc_get_price_decimals(), '.', '' );
			} else {
				$args['amount'] = $this->amount;
			}

			return array_merge( $this->get_common_template_args( $plain_text ), $args );
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
				$this->get_transaction_template_args( false ),
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
				$this->get_transaction_template_args( true ),
				'woo-wallet',
				$this->template_base
			);
		}

		/**
		 * Initialise settings form fields.
		 */
		public function init_form_fields() {
			$this->form_fields = array(
				'enabled'            => array(
					'title'   => __( 'Enable/Disable', 'woo-wallet' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable this email notification', 'woo-wallet' ),
					'default' => 'yes',
				),
				'subject'            => array(
					'title'       => __( 'Subject', 'woo-wallet' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of available placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}, {transaction_date}</code>' ),
					'placeholder' => $this->get_default_subject(),
					'default'     => '',
				),
				'heading'            => array(
					'title'       => __( 'Email heading', 'woo-wallet' ),
					'type'        => 'text',
					'desc_tip'    => true,
					/* translators: %s: list of available placeholders */
					'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}, {transaction_date}</code>' ),
					'placeholder' => $this->get_default_heading(),
					'default'     => '',
				),
				'additional_content' => $this->get_additional_content_field(),
				'email_type'         => array(
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

return new Woo_Wallet_Email_New_Transaction();
