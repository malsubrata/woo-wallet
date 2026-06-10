<?php
/**
 * Low wallet balance email (HTML).
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/emails/low-wallet-balance.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author      Subrata Mal
 * @version     1.6.4
 * @package     StandaloneTech\TeraWallet\Templates\Emails
 *
 * @var string   $email_heading      Email heading.
 * @var WP_User  $user               Wallet owner.
 * @var float    $current_balance    Current wallet balance.
 * @var string   $wallet_url         My Account wallet URL.
 * @var string   $topup_url          Wallet top-up URL.
 * @var string   $additional_content Store-owner additional content.
 * @var WC_Email $email              Email object.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$price_args = function_exists( 'woo_wallet_wc_price_args' ) ? woo_wallet_wc_price_args( $user->ID ) : array();

/**
 * WooCommerce email header.
 *
 * @hooked WC_Emails::email_header() Output the email header.
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p>
	<?php
	echo esc_html(
		sprintf(
			/* translators: %s: site name */
			__( 'Your %s wallet balance is running low.', 'woo-wallet' ),
			wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES )
		)
	);
	?>
</p>

<p>
	<strong>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: current wallet balance */
				__( 'Current balance: %s', 'woo-wallet' ),
				wc_price( $current_balance, $price_args )
			)
		);
		?>
	</strong>
</p>

<p><?php esc_html_e( 'Please recharge your wallet now to avoid any disruption.', 'woo-wallet' ); ?></p>

<?php if ( ! empty( $topup_url ) ) : ?>
	<p>
		<a class="link" href="<?php echo esc_url( $topup_url ); ?>" style="display:inline-block; padding:10px 18px; background:#2c2d33; color:#ffffff; text-decoration:none; border-radius:4px;">
			<?php esc_html_e( 'Recharge your wallet now', 'woo-wallet' ); ?>
		</a>
	</p>
<?php endif; ?>

<?php
/**
 * Show user-defined additional content - this is set in each email's settings.
 */
if ( ! empty( $additional_content ) ) {
	echo wp_kses_post( wpautop( wptexturize( $additional_content ) ) );
}

/**
 * WooCommerce email footer.
 *
 * @hooked WC_Emails::email_footer() Output the email footer.
 */
do_action( 'woocommerce_email_footer', $email );
