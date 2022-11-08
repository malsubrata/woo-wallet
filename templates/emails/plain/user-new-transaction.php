<?php
/**
 * Customer wallet transaction email
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/emails/plain/user-new-transaction.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.0.0
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$currency  = get_woocommerce_currency();
$remaining = woo_wallet()->wallet->get_wallet_balance( $user->ID, 'edit' );
$balance   = $currency . $remaining;
echo '= ' . esc_html( $email_heading ) . " =\n\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
if ( 'credit' === $type ) {
	echo esc_html__( 'Thank you for using your wallet.', 'woo-wallet' ) . esc_html( $balance ) . esc_html__( 'has been credited to your wallet.', 'woo-wallet' ) . ' ' . esc_html__( 'Current wallet balance is', 'woo-wallet' ) . esc_html( $balance );
}
if ( 'debit' === $type ) {
	echo esc_html__( 'Thank you for using your wallet.', 'woo-wallet' ) . esc_html( $balance ) . esc_html__( 'has been debited from your wallet.', 'woo-wallet' ) . ' ' . esc_html__( 'Current wallet balance is', 'woo-wallet' ) . esc_html( $balance );
}
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
