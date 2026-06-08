<?php
/**
 * Abstract base class for TeraWallet emails.
 *
 * Shared scaffolding for the wallet email classes: the standard WooCommerce
 * "Additional content" setting, the WooCommerce 10.3+ email grouping, and the
 * common template arguments (heading, additional content, wallet call-to-action
 * URLs) passed to every wallet email template.
 *
 * @package    StandaloneTech\TeraWallet
 * @subpackage Emails
 * @since      1.6.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Email' ) && class_exists( 'WC_Email' ) ) {

	/**
	 * Abstract wallet email.
	 */
	abstract class Woo_Wallet_Email extends WC_Email {

		/**
		 * Constructor.
		 *
		 * Concrete classes should set their id/title/templates first, then call
		 * parent::__construct(). The email group is assigned here so it applies
		 * to every wallet email; it is harmless on WooCommerce versions that do
		 * not yet support grouping.
		 */
		public function __construct() {
			if ( '' === $this->email_group ) {
				$this->email_group = 'payments';
			}
			parent::__construct();
		}

		/**
		 * Default additional content shown above the email footer.
		 *
		 * @since  1.6.4
		 * @return string
		 */
		public function get_default_additional_content() {
			return '';
		}

		/**
		 * Get the standard "Additional content" settings field.
		 *
		 * Concrete classes merge this into their own form fields so store owners
		 * get the same Additional content box WooCommerce core emails provide.
		 *
		 * @since  1.6.4
		 * @return array
		 */
		protected function get_additional_content_field() {
			return array(
				'title'       => __( 'Additional content', 'woo-wallet' ),
				'description' => __( 'Text to appear below the main email content.', 'woo-wallet' ),
				'css'         => 'width:400px; height: 75px;',
				'placeholder' => __( 'N/A', 'woo-wallet' ),
				'type'        => 'textarea',
				'default'     => $this->get_default_additional_content(),
				'desc_tip'    => true,
			);
		}

		/**
		 * Template arguments shared by every wallet email.
		 *
		 * @since  1.6.4
		 * @param  bool $plain_text Whether the plain text template is rendering.
		 * @return array
		 */
		protected function get_common_template_args( $plain_text = false ) {
			return array(
				'user'               => $this->object,
				'email_heading'      => $this->get_heading(),
				'additional_content' => $this->get_additional_content(),
				'wallet_url'         => function_exists( 'woo_wallet_get_dashboard_url' ) ? woo_wallet_get_dashboard_url() : '',
				'topup_url'          => function_exists( 'woo_wallet_get_topup_url' ) ? woo_wallet_get_topup_url() : '',
				'sent_to_admin'      => false,
				'plain_text'         => $plain_text,
				'email'              => $this,
			);
		}
	}
}
