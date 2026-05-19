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
					<p><?php esc_html_e( 'You are about to delete transaction records for the selected users. Choose how the records and the resulting balance should be handled.', 'woo-wallet' ); ?></p>
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Delete mode', 'woo-wallet' ); ?></th>
								<td>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="woo_wallet_delete_mode" value="soft" checked />
										<strong><?php esc_html_e( 'Soft delete', 'woo-wallet' ); ?></strong>
										<em>&mdash; <?php esc_html_e( 'recoverable, rows are flagged deleted=1 but kept in the database', 'woo-wallet' ); ?></em>
									</label>
									<label style="display:block;">
										<input type="radio" name="woo_wallet_delete_mode" value="hard" />
										<strong><?php esc_html_e( 'Hard delete', 'woo-wallet' ); ?></strong>
										<em>&mdash; <?php esc_html_e( 'permanent, rows and their meta are removed from the database', 'woo-wallet' ); ?></em>
									</label>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Balance handling', 'woo-wallet' ); ?></th>
								<td>
									<label style="display:block;margin-bottom:6px;">
										<input type="radio" name="woo_wallet_balance_handling" value="keep" checked />
										<strong><?php esc_html_e( 'Keep current balance', 'woo-wallet' ); ?></strong>
										<em>&mdash; <?php esc_html_e( 'insert a single balancing entry so the user\'s balance is unchanged after the delete', 'woo-wallet' ); ?></em>
									</label>
									<label style="display:block;">
										<input type="radio" name="woo_wallet_balance_handling" value="wipe" />
										<strong><?php esc_html_e( 'Wipe balance to zero', 'woo-wallet' ); ?></strong>
										<em>&mdash; <?php esc_html_e( 'no balancing entry; the user\'s balance becomes 0', 'woo-wallet' ); ?></em>
									</label>
								</td>
							</tr>
						</tbody>
					</table>
				</article>
				<footer>
					<div class="inner">
						<button type="button" class="button button-primary" id="woo-wallet-confirm-delete-log"><?php esc_html_e( 'Delete', 'woo-wallet' ); ?></button>
					</div>
				</footer>
			</section>
		</div>
		<div class="wc-backbone-modal-backdrop modal-close"></div>
	</div>
</script>
