<?php
/**
 * Daily visit reward message: a toast after a reward, or a line on the My Wallet page.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/daily-visit-notice.php.
 *
 * @package StandaleneTech
 * @version 1.7.1
 *
 * @var string $mode       'toast', 'pending' or 'collected'.
 * @var string $amount     Formatted reward amount (HTML).
 * @var string $wallet_url My Wallet URL (toast only).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$allowed = array(
	'span' => array( 'class' => true ),
	'bdi'  => array(),
);

if ( 'toast' !== $mode ) :
	?>
	<p class="woo-wallet-daily-visit-promo" style="margin:0 0 1em;padding:.6em .9em;border-inline-start:3px solid currentColor;background:rgba(127,127,127,.12);">
		<?php
		if ( 'collected' === $mode ) {
			/* translators: %s: reward amount. */
			printf( esc_html__( "You've collected today's %s reward. Come back tomorrow!", 'woo-wallet' ), wp_kses( $amount, $allowed ) );
		} else {
			/* translators: %s: reward amount. */
			printf( esc_html__( 'Visit every day to earn %s.', 'woo-wallet' ), wp_kses( $amount, $allowed ) );
		}
		?>
	</p>
	<?php
	return;
endif;
?>
<style>
	.woo-wallet-daily-visit-toast{position:fixed;inset-block-end:16px;inset-inline-end:16px;z-index:99999;box-sizing:border-box;max-width:min(360px,calc(100vw - 32px));display:flex;gap:12px;align-items:flex-start;padding:12px 14px;border-radius:8px;background:#1d2327;color:#fff;font-size:15px;line-height:1.4;box-shadow:0 4px 16px rgba(0,0,0,.35)}
	.woo-wallet-daily-visit-toast[hidden]{display:none}
	.woo-wallet-daily-visit-toast a{color:#fff;font-weight:600;text-decoration:underline}
	.woo-wallet-daily-visit-toast button{flex:none;min-width:32px;min-height:32px;margin:-6px -8px 0 0;padding:0;border:0;background:none;color:#fff;font-size:22px;line-height:1;cursor:pointer}
	.woo-wallet-daily-visit-toast a:focus-visible,.woo-wallet-daily-visit-toast button:focus-visible{outline:2px solid #fff;outline-offset:2px}
	@media (prefers-reduced-motion:no-preference){.woo-wallet-daily-visit-toast{animation:wwdv-in .25s ease-out}@keyframes wwdv-in{from{opacity:0}}}
</style>
<div class="woo-wallet-daily-visit-toast" id="woo-wallet-daily-visit-toast" role="status">
	<div>
		<?php
		/* translators: %s: reward amount. */
		printf( esc_html__( 'You earned %s for visiting today.', 'woo-wallet' ), wp_kses( $amount, $allowed ) );
		?>
		<a href="<?php echo esc_url( $wallet_url ); ?>"><?php esc_html_e( 'View wallet', 'woo-wallet' ); ?></a>
	</div>
	<button type="button" aria-label="<?php esc_attr_e( 'Dismiss', 'woo-wallet' ); ?>">&times;</button>
</div>
<script>
	( function () {
		var t = document.getElementById( 'woo-wallet-daily-visit-toast' );
		function close() { t.hidden = true; document.removeEventListener( 'keydown', onKey ); }
		function onKey( e ) { if ( 'Escape' === e.key ) { close(); } }
		t.querySelector( 'button' ).addEventListener( 'click', close );
		document.addEventListener( 'keydown', onKey );
	}() );
</script>
