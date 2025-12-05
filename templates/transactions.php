<?php
/**
 * The Template for displaying wallet dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/transactions.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.5.15
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
?>
<!-- Transactions -->
<div class="woo-wallet-transactions-list">
	<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Transactions', 'woo-wallet' ); ?></h3>
	<?php do_action( 'woo_wallet_before_transactions_content' ); ?>
	<table id="wc-wallet-transaction-details" class="table"></table>
	<?php do_action( 'woo_wallet_after_transactions_content' ); ?>
</div>