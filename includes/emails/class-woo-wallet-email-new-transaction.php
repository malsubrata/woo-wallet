<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'Woo_Wallet_Email_New_Transaction' ) ) {

    class Woo_Wallet_Email_New_Transaction extends WC_Email {
        public $transaction_id;
        public $type;
        public $amount = 0;
        public $details;
        public function __construct() {
            $this->id             = 'new_wallet_transaction';
            $this->title          = __( 'New wallet transaction', 'woo-wallet' );
            $this->description    = __( 'New wallet transaction emails are sent to user when a wallet transaction received.', 'woo-wallet' );
            $this->template_html  = 'emails/user-new-transaction.php';
            $this->template_plain = 'emails/plain/user-new-transaction.php';
            $this->template_base  = WOO_WALLET_ABSPATH . 'templates/';
            $this->placeholders   = array(
                '{site_title}'       => $this->get_blogname(),
                '{transaction_date}' => '',
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
            return __( 'Your {site_title} wallet transaction from {transaction_date}', 'woo-wallet' );
        }

        /**
         * Get email heading.
         *
         * @since  3.1.0
         * @return string
         */
        public function get_default_heading() {
            return __( 'Thank you for using wallet', 'woo-wallet' );
        }

        /**
         * Trigger the sending of this email.
         *
         * @param int $transaction_id.
         */
        public function trigger( $transaction_id ) {
            
            $transaction = get_wallet_transaction( $transaction_id );
            if ( $transaction ) {
                $this->setup_locale();

                $user = new WP_User( $transaction->user_id );

                if ( is_a( $user, 'WP_User' ) ) {
                    $this->object = $user;
                    $this->transaction_id = $transaction->transaction_id;
                    $this->type = $transaction->type;
                    $this->amount = $transaction->amount;
                    $this->details = $transaction->details;
                    $this->recipient = $user->user_email;
                    $this->placeholders['{transaction_date}'] = date_i18n( wc_date_format() );

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
                'transaction_id' => $this->transaction_id,
                'type'          => $this->type,
                'amount'        => $this->amount,
                'details'       => $this->details,
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
                'transaction_id' => $this->transaction_id,
                'type'          => $this->type,
                'amount'        => number_format( $this->amount, wc_get_price_decimals(), '.', '' ),
                'details'       => $this->details,
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
                'subject' => array(
                    'title'       => __( 'Subject', 'woo-wallet' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    /* translators: %s: list of placeholders */
                    'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}, {transaction_date}</code>' ),
                    'placeholder' => $this->get_default_subject(),
                    'default'     => '',
                ),
                'heading' => array(
                    'title'       => __( 'Email heading', 'woo-wallet' ),
                    'type'        => 'text',
                    'desc_tip'    => true,
                    /* translators: %s: list of placeholders */
                    'description' => sprintf( __( 'Available placeholders: %s', 'woo-wallet' ), '<code>{site_title}, {transaction_date}</code>' ),
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

return new Woo_Wallet_Email_New_Transaction();
