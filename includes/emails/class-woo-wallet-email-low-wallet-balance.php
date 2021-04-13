<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Woo_Wallet_Email_Low_Wallet_Balance' ) ) {

    class Woo_Wallet_Email_Low_Wallet_Balance extends WC_Email {
        
        public function __construct() {
            $this->id             = 'low_wallet_balance';
            $this->customer_email = true;
            $this->title          = __( 'Low Wallet Balance', 'woo-wallet' );
            $this->description    = __( 'If the wallet balance reaches the lower limit set by admin for notification then the customer will get the notification for low wallet balance.', 'woo-wallet' );
            $this->template_html  = 'emails/low-wallet-balance.php';
            $this->template_plain = 'emails/plain/low-wallet-balance.php';
            $this->template_base  = WOO_WALLET_ABSPATH . 'templates/';
            $this->placeholders   = array(
                '{site_title}'       => $this->get_blogname()
            );
            // Call parent constructor
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
            return __( 'Please recharge your wallet for continue using.', 'woo-wallet' );
        }

        /**
         * Trigger the sending of this email.
         *
         * @param int $transaction_id.
         */
        public function trigger( $user_id, $type ) {
            $current_balance = woo_wallet()->wallet->get_wallet_balance($user_id, 'edit');
            $low_wallet_balance_threshold = $this->get_option('low_wallet_balance_threshold', 0);
            
            if ( 'debit' == $type && $low_wallet_balance_threshold >= $current_balance) {
                $this->setup_locale();

                $user = new WP_User( $user_id );

                if ( is_a( $user, 'WP_User' ) ) {
                    $this->object = $user;
                    $this->recipient = $user->user_email;

                    if ( $this->is_enabled() && $this->get_recipient() ) {
                        $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
                    }
                }
                $this->restore_locale();
            }
            
        }

        /**
         * Get content html.
         *
         * @access public
         * @return string
         */
        public function get_content_html() {
            return wc_get_template_html( $this->template_html, array(
                'user'          => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => false,
                'email'         => $this ), 'woo-wallet', $this->template_base );
        }

        /**
         * Get content plain.
         *
         * @access public
         * @return string
         */
        public function get_content_plain() {
            return wc_get_template_html( $this->template_plain, array(
                'user'          => $this->object,
                'email_heading' => $this->get_heading(),
                'sent_to_admin' => false,
                'plain_text'    => true,
                'email' => $this ), 'woo-wallet', $this->template_base );
        }

        /**
         * Initialise settings form fields.
         */
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'woo-wallet' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable this email notification', 'woo-wallet' ),
                    'default' => 'yes',
                ),
                'low_wallet_balance_threshold' => array(
                    'title' => __('Threshold for low wallet balance', 'woo-wallet'),
                    'type' => 'number',
                    'desc_tip'    => true,
                    'description' => __( 'A minimum balance on which customers will be notified to recharge again the wallet.', 'woo-wallet' ),
                    'default' => 0
                ),
                'subject' => array(
                    'title'       => __( 'Subject', 'woo-wallet' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    /* translators: %s: list of placeholders */
                    'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}</code>' ),
                    'placeholder' => $this->get_default_subject(),
                    'default'     => '',
                ),
                'heading' => array(
                    'title'       => __( 'Email heading', 'woo-wallet' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    /* translators: %s: list of placeholders */
                    'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}</code>' ),
                    'placeholder' => $this->get_default_heading(),
                    'default'     => '',
                ),
                'email_type' => array(
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
