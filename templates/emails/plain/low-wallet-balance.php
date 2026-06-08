<?php
/**
 * Low wallet balance email (plain text).
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/emails/plain/low-wallet-balance.php.
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
 * @var string  $current_balance    Current wallet balance (plain number).
 * @var string  $topup_url          Wallet top-up URL.
 * @var string  $additional_content Store-owner additional content.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$currency = get_woocommerce_currency_symbol();

echo '= ' . esc_html( $email_heading ) . " =\n\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: site name */
echo esc_html( sprintf( __( 'Your %s wallet balance is running low.', 'woo-wallet' ), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ) ) ) . "\n";

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";

/* translators: %s: current wallet balance */
echo esc_html( sprintf( __( 'Current balance: %s', 'woo-wallet' ), $currency . ' ' . $current_balance ) ) . "\n\n";

echo esc_html__( 'Please recharge your wallet now to avoid any disruption.', 'woo-wallet' ) . "\n";

if ( ! empty( $topup_url ) ) {
	/* translators: %s: wallet top-up URL */
	echo esc_html( sprintf( __( 'Recharge your wallet: %s', 'woo-wallet' ), $topup_url ) ) . "\n";
}

if ( ! empty( $additional_content ) ) {
	echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
	echo esc_html( wp_strip_all_tags( wptexturize( $additional_content ) ) ) . "\n";
}

echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo wp_kses_post( apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ) );
