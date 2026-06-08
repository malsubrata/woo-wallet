<?php
/**
 * Customer wallet transaction email (plain text).
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/emails/plain/user-new-transaction.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author      Subrata Mal
 * @version     1.6.4
 * @package     StandaloneTech\TeraWallet\Templates\Emails
 *
 * @var string  $email_heading      Email heading.
 * @var WP_User $user               Wallet owner.
 * @var string  $type               Transaction type (credit|debit).
 * @var string  $amount             Transaction amount (plain number).
 * @var string  $current_balance    Current wallet balance (plain number).
 * @var string  $details            Transaction details.
 * @var string  $wallet_url         My Account wallet URL.
 * @var string  $additional_content Store-owner additional content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currency = get_woocommerce_currency_symbol();

echo '= ' . esc_html( $email_heading ) . " =\n\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

if ( 'credit' === $type ) {
	/* translators: %s: credited amount */
	echo esc_html( sprintf( __( 'Good news! %s has been credited to your wallet.', 'woo-wallet' ), $currency . $amount ) ) . "\n";
} else {
	/* translators: %s: debited amount */
	echo esc_html( sprintf( __( '%s has been debited from your wallet.', 'woo-wallet' ), $currency . $amount ) ) . "\n";
}

echo "\n";
/* translators: %s: transaction type label */
echo esc_html( sprintf( __( 'Type: %s', 'woo-wallet' ), 'credit' === $type ? __( 'Credit', 'woo-wallet' ) : __( 'Debit', 'woo-wallet' ) ) ) . "\n";

if ( ! empty( $details ) ) {
	/* translators: %s: transaction details */
	echo esc_html( sprintf( __( 'Details: %s', 'woo-wallet' ), $details ) ) . "\n";
}

/* translators: %s: transaction date */
echo esc_html( sprintf( __( 'Date: %s', 'woo-wallet' ), date_i18n( wc_date_format() ) ) ) . "\n";
/* translators: %s: current wallet balance */
echo esc_html( sprintf( __( 'Current balance: %s', 'woo-wallet' ), $currency . $current_balance ) ) . "\n";

if ( ! empty( $wallet_url ) ) {
	echo "\n";
	/* translators: %s: wallet URL */
	echo esc_html( sprintf( __( 'View your wallet: %s', 'woo-wallet' ), $wallet_url ) ) . "\n";
}

if ( ! empty( $additional_content ) ) {
	echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
