<?php
/**
 * The Template for displaying referral page.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/woo-wallet-referrals.php.
 *
 * @author  Subrata Mal
 * @version     1.3.5
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
$user_id                = get_current_user_id();
$user                   = new WP_User( $user_id );
$referral_url_by_userid = 'id' === $settings['referal_link'] ? true : false;
$referral_url           = add_query_arg( $referral->referral_handel, $user->user_login, wc_get_page_permalink( 'myaccount' ) );
if ( $referral_url_by_userid ) {
	$referral_url = add_query_arg( $referral->referral_handel, $user->ID, wc_get_page_permalink( 'myaccount' ) );
}
$referring_visitor = get_user_meta( $user_id, '_woo_wallet_referring_visitor', true ) ? get_user_meta( $user_id, '_woo_wallet_referring_visitor', true ) : 0;
$referring_signup  = get_user_meta( $user_id, '_woo_wallet_referring_signup', true ) ? get_user_meta( $user_id, '_woo_wallet_referring_signup', true ) : 0;
$referring_earning = get_user_meta( $user_id, '_woo_wallet_referring_earning', true ) ? get_user_meta( $user_id, '_woo_wallet_referring_earning', true ) : 0;
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

	<!-- Statistics Card -->
	<div class="woo-wallet-referral-stats">
		<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Referral Statistics', 'woo-wallet' ); ?></h3>
		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Visitors', 'woo-wallet' ); ?></th>
					<th><?php esc_html_e( 'Signups', 'woo-wallet' ); ?></th>
					<th><?php esc_html_e( 'Earnings', 'woo-wallet' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><?php echo esc_html( $referring_visitor ); ?></td>
					<td><?php echo esc_html( $referring_signup ); ?></td>
					<td><?php echo wc_price( $referring_earning, woo_wallet_wc_price_args() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			</tbody>
		</table>
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
