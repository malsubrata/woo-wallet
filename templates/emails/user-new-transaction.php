<?php
/**
 * Customer wallet transaction email (HTML).
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/emails/user-new-transaction.php.
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
 * @var string   $type               Transaction type (credit|debit).
 * @var float    $amount             Transaction amount.
 * @var float    $current_balance    Current wallet balance.
 * @var string   $details            Transaction details.
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

<?php if ( 'credit' === $type ) : ?>
	<p>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: credited amount */
				__( 'Good news! %s has been credited to your wallet.', 'woo-wallet' ),
				wc_price( $amount, $price_args )
			)
		);
		?>
	</p>
<?php else : ?>
	<p>
		<?php
		echo wp_kses_post(
			sprintf(
				/* translators: %s: debited amount */
				__( '%s has been debited from your wallet.', 'woo-wallet' ),
				wc_price( $amount, $price_args )
			)
		);
		?>
	</p>
<?php endif; ?>

<table cellspacing="0" cellpadding="6" border="1" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
	<tbody>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Type', 'woo-wallet' ); ?></th>
			<td><?php echo 'credit' === $type ? esc_html__( 'Credit', 'woo-wallet' ) : esc_html__( 'Debit', 'woo-wallet' ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Amount', 'woo-wallet' ); ?></th>
			<td><?php echo wp_kses_post( wc_price( $amount, $price_args ) ); ?></td>
		</tr>
		<?php if ( ! empty( $details ) ) : ?>
			<tr>
				<th scope="row" style="text-align:left;"><?php esc_html_e( 'Details', 'woo-wallet' ); ?></th>
				<td><?php echo esc_html( $details ); ?></td>
			</tr>
		<?php endif; ?>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Date', 'woo-wallet' ); ?></th>
			<td><?php echo esc_html( date_i18n( wc_date_format() ) ); ?></td>
		</tr>
		<tr>
			<th scope="row" style="text-align:left;"><?php esc_html_e( 'Current balance', 'woo-wallet' ); ?></th>
			<td><strong><?php echo wp_kses_post( wc_price( $current_balance, $price_args ) ); ?></strong></td>
		</tr>
	</tbody>
</table>

<?php if ( ! empty( $wallet_url ) ) : ?>
	<p>
		<a class="link" href="<?php echo esc_url( $wallet_url ); ?>" style="display:inline-block; padding:10px 18px; background:#2c2d33; color:#ffffff; text-decoration:none; border-radius:4px;">
			<?php esc_html_e( 'View your wallet', 'woo-wallet' ); ?>
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
