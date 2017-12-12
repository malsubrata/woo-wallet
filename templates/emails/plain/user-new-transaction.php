<?php
/**
 * Customer wallet transaction email
 *
 * This template can be overridden by copying it to yourtheme/wc-wallet/user-new-transaction.php.
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
$currency = get_woocommerce_currency_symbol();
echo "= " . $email_heading . " =\n\n";
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
if ($type == 'credit') { 
    echo "Thank you for using your wallet. {$currency} {$amount} has been credited to your wallet.";
}
if ($type == 'debit') {
    echo "Thank you for using your wallet. {$currency} {$amount} has been debited to your wallet.";
}
echo "\n=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=\n\n";
echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) );