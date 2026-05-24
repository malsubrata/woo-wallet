<?php
/**
 * The Template for displaying the customer referral page.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/referrals.php.
 *
 * @author  Subrata Mal
 * @version 1.6.2
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$user_id      = get_current_user_id();
$user         = new WP_User( $user_id );
$referral_url = add_query_arg(
	$referral->referral_handel,
	'id' === $settings['referal_link'] ? $user->ID : $user->user_login,
	wc_get_page_permalink( 'myaccount' )
);

$summary   = woo_wallet_get_referral_summary( $user_id );
$per_page  = (int) apply_filters( 'woo_wallet_referral_list_per_page', 10 );
$paged     = isset( $_GET['referral_page'] ) ? max( 1, absint( wp_unslash( $_GET['referral_page'] ) ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$total     = get_wallet_referrals_count( array( 'referrer_id' => $user_id ) );
$referrals = get_wallet_referrals(
	array(
		'referrer_id' => $user_id,
		'limit'       => ( ( $paged - 1 ) * $per_page ) . ',' . $per_page,
		'order_by'    => 'referral_id',
		'order'       => 'DESC',
	)
);

$type_labels   = array(
	'visit'  => __( 'Visit', 'woo-wallet' ),
	'signup' => __( 'Sign-up', 'woo-wallet' ),
);
$status_labels = array(
	'pending'   => __( 'Pending', 'woo-wallet' ),
	'completed' => __( 'Credited', 'woo-wallet' ),
	'rejected'  => __( 'Rejected', 'woo-wallet' ),
);
?>

<div class="woo-wallet-referral-container">

	<!-- Referral Link Card -->
	<div class="woo-wallet-referral-link-card">
		<h3><?php esc_html_e( 'Your Referral URL', 'woo-wallet' ); ?></h3>
		<div class="woo-wallet-referral-input-group">
			<div class="woo-wallet-referral-input-wrapper">
				<span class="dashicons dashicons-admin-links"></span>
				<input type="text" readonly="" id="woo_wallet_referral_url" value="<?php echo esc_url( $referral_url ); ?>" />
			</div>
			<button class="woo-wallet-copy-btn" onclick="wooWalletCopyReferral(this)" data-tooltip="<?php esc_attr_e( 'Copy to clipboard', 'woo-wallet' ); ?>">
				<?php esc_html_e( 'Copy URL', 'woo-wallet' ); ?>
			</button>
		</div>
	</div>

	<!-- Summary -->
	<div class="woo-wallet-referral-stats">
		<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Referral Summary', 'woo-wallet' ); ?></h3>
		<div class="woo-wallet-referral-summary">
			<div class="woo-wallet-referral-summary-card">
				<span class="woo-wallet-referral-summary-value"><?php echo esc_html( number_format_i18n( $summary['visitors'] ) ); ?></span>
				<span class="woo-wallet-referral-summary-label"><?php esc_html_e( 'Visitors', 'woo-wallet' ); ?></span>
			</div>
			<div class="woo-wallet-referral-summary-card">
				<span class="woo-wallet-referral-summary-value"><?php echo esc_html( number_format_i18n( $summary['signups'] ) ); ?></span>
				<span class="woo-wallet-referral-summary-label"><?php esc_html_e( 'Sign-ups', 'woo-wallet' ); ?></span>
			</div>
			<?php if ( $summary['pending'] ) : ?>
				<div class="woo-wallet-referral-summary-card">
					<span class="woo-wallet-referral-summary-value"><?php echo esc_html( number_format_i18n( $summary['pending'] ) ); ?></span>
					<span class="woo-wallet-referral-summary-label"><?php esc_html_e( 'Pending', 'woo-wallet' ); ?></span>
				</div>
			<?php endif; ?>
			<div class="woo-wallet-referral-summary-card woo-wallet-referral-summary-earned">
				<span class="woo-wallet-referral-summary-value"><?php echo wp_kses_post( woo_wallet_referral_format_amount( $summary['earned'], $summary['currency'], $user_id ) ); ?></span>
				<span class="woo-wallet-referral-summary-label"><?php esc_html_e( 'Total earned', 'woo-wallet' ); ?></span>
			</div>
		</div>
		<?php if ( $summary['legacy_earned'] > 0 ) : ?>
			<p class="woo-wallet-referral-legacy">
				<?php
				printf(
					/* translators: %s: legacy referral earnings amount. */
					esc_html__( 'Legacy earnings (recorded before referral history tracking began): %s', 'woo-wallet' ),
					wp_kses_post( woo_wallet_referral_format_amount( $summary['legacy_earned'], $summary['currency'], $user_id ) )
				);
				?>
			</p>
		<?php endif; ?>
	</div>

	<!-- Referral history -->
	<div class="woo-wallet-referral-list">
		<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Referral History', 'woo-wallet' ); ?></h3>
		<?php if ( empty( $referrals ) ) : ?>
			<p class="woo-wallet-referral-empty"><?php esc_html_e( 'No referrals yet. Share your referral URL to start earning.', 'woo-wallet' ); ?></p>
		<?php else : ?>
			<table>
				<thead>
					<tr>
						<th><?php esc_html_e( 'Referred', 'woo-wallet' ); ?></th>
						<th><?php esc_html_e( 'Type', 'woo-wallet' ); ?></th>
						<th><?php esc_html_e( 'Date', 'woo-wallet' ); ?></th>
						<th><?php esc_html_e( 'Reward', 'woo-wallet' ); ?></th>
						<th><?php esc_html_e( 'Status', 'woo-wallet' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $referrals as $row ) : ?>
						<tr>
							<td><?php echo esc_html( woo_wallet_referral_user_label( $row->referred_user_id ) ); ?></td>
							<td><?php echo esc_html( isset( $type_labels[ $row->type ] ) ? $type_labels[ $row->type ] : $row->type ); ?></td>
							<td><?php echo esc_html( wc_string_to_datetime( $row->date_created )->date_i18n( wc_date_format() ) ); ?></td>
							<td><?php echo wp_kses_post( woo_wallet_referral_format_amount( $row->amount, $row->currency, $user_id ) ); ?></td>
							<td>
								<span class="woo-wallet-referral-badge woo-wallet-referral-badge-<?php echo esc_attr( $row->status ); ?>">
									<?php echo esc_html( isset( $status_labels[ $row->status ] ) ? $status_labels[ $row->status ] : $row->status ); ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
			$page_links = paginate_links(
				array(
					'base'      => add_query_arg( 'referral_page', '%#%' ),
					'format'    => '',
					'current'   => $paged,
					'total'     => (int) ceil( $total / $per_page ),
					'type'      => 'list',
					'prev_text' => __( '&larr; Previous', 'woo-wallet' ),
					'next_text' => __( 'Next &rarr;', 'woo-wallet' ),
				)
			);
			if ( $page_links ) :
				?>
				<nav class="woo-wallet-referral-pagination"><?php echo wp_kses_post( $page_links ); ?></nav>
				<?php
			endif;
			?>
		<?php endif; ?>
	</div>
</div>

<script type="text/javascript">
	function wooWalletCopyReferral(btn) {
		var copyText = document.getElementById("woo_wallet_referral_url");
		copyText.select();
		copyText.setSelectionRange(0, 99999); /* For mobile devices */
		document.execCommand("copy");

		var originalText = btn.getAttribute('data-tooltip');
		btn.setAttribute('data-tooltip', "<?php esc_html_e( 'Copied!', 'woo-wallet' ); ?>");

		// Reset tooltip text after 2 seconds
		setTimeout(function() {
			btn.setAttribute('data-tooltip', originalText);
		}, 2000);
	}
</script>
