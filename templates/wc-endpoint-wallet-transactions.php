<?php
/**
 * The Template for displaying transaction history
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/wc-endpoint-wallet-transactions.php.
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
	exit; // Exit if accessed directly.
}

$transactions = get_wallet_transactions();
do_action( 'woo_wallet_before_transaction_details_content' );
?>
<p><?php esc_html_e( 'Current balance :', 'woo-wallet' ); ?> <?php echo woo_wallet()->wallet->get_wallet_balance( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a href="<?php echo is_account_page() ? esc_url( wc_get_account_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'woo-wallet' ) ) ) : esc_url( get_permalink() ); ?>"><span class="dashicons dashicons-editor-break"></span></a></p>
<table id="wc-wallet-transaction-details" class="table"></table>
<?php
do_action( 'woo_wallet_after_transaction_details_content' );
