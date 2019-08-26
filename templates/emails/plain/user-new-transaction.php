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
 * @author 	Subrata Mal
 * @version     1.0.0
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$currency  = get_woocommerce_currency_symbol();
$remaining = woo_wallet()->wallet->get_wallet_balance( $user->ID, 'edit' );
echo "= " . $email_heading . " =\n\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
if ( $type == 'credit' ) { 
    echo __( 'Thank you for using your wallet.', 'woo-wallet' )." {$currency} {$amount} ". __( 'has been credited to your wallet.', 'woo-wallet' ). " " . __( 'Current wallet balance is', 'woo-wallet' )." {$currency} {$remaining}";
}
if ( $type == 'debit' ) {
    echo __( 'Thank you for using your wallet.', 'woo-wallet' )." {$currency} {$amount} ". __( 'has been debited from your wallet.', 'woo-wallet' ). " " . __( 'Current wallet balance is', 'woo-wallet' )." {$currency} {$remaining}";
}
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );