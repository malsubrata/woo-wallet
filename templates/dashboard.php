<?php
/**
 * The Template for displaying wallet dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/dashboard.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.6.4
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$ww_txn_limit = (int) apply_filters( 'woo_wallet_transactions_count', 10 );
$transactions = get_wallet_transactions( array( 'limit' => $ww_txn_limit ) );

$ww_stat_cards = function_exists( 'woo_wallet_get_dashboard_stat_cards' )
	? woo_wallet_get_dashboard_stat_cards( get_current_user_id() )
	: array();
?>
<?php if ( ! empty( $ww_stat_cards ) ) : ?>
	<!-- Stat cards -->
	<div class="woo-wallet-stats-grid">
		<?php
		foreach ( $ww_stat_cards as $ww_card ) :
			$ww_card = wp_parse_args(
				$ww_card,
				array(
					'tone'  => 'neutral',
					'icon'  => 'wallet',
					'label' => '',
					'value' => '',
				)
			);
			?>
			<div class="woo-wallet-stat-card woo-wallet-stat-card--<?php echo esc_attr( $ww_card['tone'] ); ?>">
				<span class="woo-wallet-stat-card__icon"><?php echo woo_wallet_get_stat_card_icon( $ww_card['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns sanitised markup. ?></span>
				<span class="woo-wallet-stat-card__value"><?php echo wp_kses_post( $ww_card['value'] ); ?></span>
				<span class="woo-wallet-stat-card__label"><?php echo esc_html( $ww_card['label'] ); ?></span>
			</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
<!-- Recent Transactions -->
<div class="woo-wallet-transactions-list">
	<div class="ww-txn-head">
		<h3 class="ww-txn-title"><?php esc_html_e( 'Recent transactions', 'woo-wallet' ); ?></h3>
		<?php if ( ! empty( $transactions ) ) : ?>
			<span class="ww-txn-meta">
				<?php
				/* translators: %d: number of transactions shown. */
				echo esc_html( sprintf( _n( 'Last %d', 'Last %d', count( $transactions ), 'woo-wallet' ), count( $transactions ) ) );
				?>
			</span>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $transactions ) ) { ?>
		<?php
		$active_currency = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance()->get_active_currency() : strtoupper( (string) get_woocommerce_currency() );

		// Map each semantic category to a badge tone. Unknown categories fall
		// back to the credit/debit type below, so third-party slugs stay sane.
		$ww_tone_map = array(
			'topup'               => 'brand',
			'cashback'            => 'success',
			'cashback_adjustment' => 'success',
			'cashback_refund'     => 'teal',
			'refund'              => 'success',
			'partial_payment'     => 'danger',
			'purchase'            => 'danger',
			'transfer'            => 'info',
			'adjustment'          => 'neutral',
			'vendor_commission'   => 'teal',
			'withdrawal'          => 'warning',
			'coupon'              => 'accent',
		);
		?>
		<div class="ww-txn-table" role="table" aria-label="<?php esc_attr_e( 'Recent transactions', 'woo-wallet' ); ?>">
			<div class="ww-txn-row ww-txn-row--head" role="row">
				<span class="ww-txn-cell ww-txn-cell--date" role="columnheader"><?php esc_html_e( 'Date', 'woo-wallet' ); ?></span>
				<span class="ww-txn-cell ww-txn-cell--desc" role="columnheader"><?php esc_html_e( 'Description', 'woo-wallet' ); ?></span>
				<span class="ww-txn-cell ww-txn-cell--type" role="columnheader"><?php esc_html_e( 'Type', 'woo-wallet' ); ?></span>
				<span class="ww-txn-cell ww-txn-cell--amount" role="columnheader"><?php esc_html_e( 'Amount', 'woo-wallet' ); ?></span>
			</div>
			<?php
			foreach ( $transactions as $transaction ) :
				$is_credit = 'credit' === $transaction->type;
				$category  = isset( $transaction->category ) && '' !== $transaction->category ? $transaction->category : 'other';

				// Resolve the badge label: a registered category name, or the
				// plain Credit/Debit type for generic/unknown rows.
				if ( 'other' === $category || ! function_exists( 'woo_wallet_get_transaction_type_label' ) ) {
					$type_label = $is_credit ? __( 'Credit', 'woo-wallet' ) : __( 'Debit', 'woo-wallet' );
				} else {
					$type_label = woo_wallet_get_transaction_type_label( $category );
				}

				$tone = isset( $ww_tone_map[ $category ] ) ? $ww_tone_map[ $category ] : ( $is_credit ? 'success' : 'neutral' );
				?>
				<div class="ww-txn-row" role="row">
					<span class="ww-txn-cell ww-txn-cell--date" role="cell" data-label="<?php esc_attr_e( 'Date', 'woo-wallet' ); ?>">
						<?php echo esc_html( wc_string_to_datetime( $transaction->date )->date_i18n( wc_date_format() ) ); ?>
					</span>
					<span class="ww-txn-cell ww-txn-cell--desc" role="cell" data-label="<?php esc_attr_e( 'Description', 'woo-wallet' ); ?>">
						<?php echo wp_kses_post( $transaction->details ); ?>
					</span>
					<span class="ww-txn-cell ww-txn-cell--type" role="cell" data-label="<?php esc_attr_e( 'Type', 'woo-wallet' ); ?>">
						<span class="ww-txn-badge ww-txn-badge--<?php echo esc_attr( $tone ); ?>"><?php echo esc_html( $type_label ); ?></span>
					</span>
					<span class="ww-txn-cell ww-txn-cell--amount <?php echo $is_credit ? 'credit' : 'debit'; ?>" role="cell" data-label="<?php esc_attr_e( 'Amount', 'woo-wallet' ); ?>">
						<?php
						echo $is_credit ? '+' : '&minus;';
						echo wp_kses_post( wc_price( apply_filters( 'woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id ), woo_wallet_wc_price_args( $transaction->user_id, array( 'currency' => $active_currency ) ) ) );
						?>
					</span>
				</div>
			<?php endforeach; ?>
		</div>
		<?php
	} else {
		echo '<p class="ww-txn-empty">' . esc_html__( 'No transactions found', 'woo-wallet' ) . '</p>';
	}
	?>
</div>
