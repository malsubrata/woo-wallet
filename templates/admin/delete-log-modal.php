<?php
/**
 * Admin View: Delete Logs bulk-action modal.
 *
 * Lets the admin pick delete mode (soft / hard) and balance handling
 * (keep / wipe) before the bulk `delete_log` action submits.
 *
 * @package StandaloneTech
 * @since 1.6.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<script type="text/template" id="tmpl-woo-wallet-modal-delete-log">
	<div class="wc-backbone-modal woo-wallet-delete-log">
		<div class="wc-backbone-modal-content">
			<section class="wc-backbone-modal-main" role="main">
				<header class="wc-backbone-modal-header">
					<h1><?php esc_html_e( 'Delete transaction logs', 'woo-wallet' ); ?></h1>
					<button class="modal-close modal-close-link dashicons dashicons-no-alt">
						<span class="screen-reader-text"><?php esc_html_e( 'Close modal panel', 'woo-wallet' ); ?></span>
					</button>
				</header>
				<article>
					<p>
						<?php esc_html_e( 'This deletes the wallet transaction history of the selected users.', 'woo-wallet' ); ?>
						<strong class="woo-wallet-delete-log-count"></strong>
					</p>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Delete mode', 'woo-wallet' ); ?></th>
								<td>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="woo_wallet_delete_mode" value="soft" checked />
										<strong><?php esc_html_e( 'Hide transactions', 'woo-wallet' ); ?></strong>
										&mdash; <?php esc_html_e( 'removed from wallet history and reports. The records stay in your database, but they cannot be restored from the dashboard.', 'woo-wallet' ); ?>
									</label>
									<label style="display:block;">
										<input type="radio" name="woo_wallet_delete_mode" value="hard" />
										<strong><?php esc_html_e( 'Delete permanently', 'woo-wallet' ); ?></strong>
										&mdash; <?php esc_html_e( 'erased from your database. This cannot be undone.', 'woo-wallet' ); ?>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Balance handling', 'woo-wallet' ); ?></th>
								<td>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="woo_wallet_balance_handling" value="keep" checked />
										<strong><?php esc_html_e( 'Keep current balance', 'woo-wallet' ); ?></strong>
										&mdash; <?php esc_html_e( 'each customer keeps the balance they have now. One entry is added to their history to carry it over.', 'woo-wallet' ); ?>
									</label>
									<label style="display:block;">
										<input type="radio" name="woo_wallet_balance_handling" value="wipe" />
										<strong><?php esc_html_e( 'Wipe balance to zero', 'woo-wallet' ); ?></strong>
										&mdash; <?php esc_html_e( 'each customer\'s balance becomes 0.', 'woo-wallet' ); ?>
									</label>
									<div class="notice notice-error inline woo-wallet-delete-log-wipe-warning" role="alert" hidden style="margin:10px 0 0;">
										<p><strong><?php esc_html_e( 'Customers will lose their wallet balance.', 'woo-wallet' ); ?></strong> <?php esc_html_e( 'Everything the selected users have in their wallets will be gone and they will not be able to spend it.', 'woo-wallet' ); ?></p>
									</div>
								</td>
							</tr>
						</tbody>
					</table>
				</article>
				<footer>
					<div class="inner">
						<button type="button" class="button button-primary woo-wallet-button-destructive" id="woo-wallet-confirm-delete-log"><?php esc_html_e( 'Delete logs', 'woo-wallet' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
		<div class="wc-backbone-modal-backdrop modal-close"></div>
	</div>
</script>
