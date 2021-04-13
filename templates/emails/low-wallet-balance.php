<?php
/**
 * Customer wallet transaction email
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/emails/low-wallet-balance.php.
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

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );

?>
<p><?php echo sprintf(__('Your %s wallet balance is low.', 'woo-wallet'), wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES )); ?></p>
<p><b><?php echo sprintf(__('Current Balance: %s', 'woo-wallet'), woo_wallet()->wallet->get_wallet_balance($user->ID)); ?></b></p>
<p><?php echo sprintf(__('Please recharge you wallet now to avoid any disruption.', 'woo-wallet')); ?></p>
<?php
/**
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
