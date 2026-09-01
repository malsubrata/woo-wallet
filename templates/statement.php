<?php
/**
 * The template for displaying the wallet account statement.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/statement.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version 1.6.15
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WooWallet_Statement_Service' ) ) {
	include_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-statement-service.php';
}

$ww_user_id = get_current_user_id();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only view state; the date range is validated and clamped by the service, the currency is pattern-checked there, and the page number is clamped to the row count.
$ww_from     = isset( $_GET['from'] ) ? sanitize_text_field( wp_unslash( $_GET['from'] ) ) : '';
$ww_to       = isset( $_GET['to'] ) ? sanitize_text_field( wp_unslash( $_GET['to'] ) ) : '';
$ww_currency = isset( $_GET['currency'] ) ? sanitize_text_field( wp_unslash( $_GET['currency'] ) ) : '';
$ww_page     = isset( $_GET['ww_page'] ) ? absint( wp_unslash( $_GET['ww_page'] ) ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$ww_statement = WooWallet_Statement_Service::to_display_currency(
	WooWallet_Statement_Service::get_statement( $ww_user_id, $ww_from, $ww_to, $ww_currency, $ww_page )
);

/*
 * Every link off this page carries the resolved currency, not the currency
 * that happens to be active when the link is followed. Without it, switching
 * the storefront currency between rendering page 1 and clicking page 2 -- or
 * Download CSV -- silently moves the reader to a different ledger, mid
 * reconciliation. Outside per-currency mode the service ignores the argument.
 */
$ww_price_args = woo_wallet_wc_price_args( $ww_user_id, array( 'currency' => $ww_statement['currency'] ) );

$ww_csv_url = wp_nonce_url(
	add_query_arg(
		array(
			'woo_wallet_statement_csv' => 1,
			'from'                     => $ww_statement['from'],
			'to'                       => $ww_statement['to'],
			'currency'                 => $ww_statement['currency'],
		),
		wc_get_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ), 'statement', wc_get_page_permalink( 'myaccount' ) )
	),
	'woo_wallet_statement_csv'
);

/*
 * Columns are filtered so an add-on can append its own (the Pro credit-expiry
 * date, say) or relabel one, and the matching per-row cells are filtered
 * below. `ww_stmt_money_columns` are the three the summary rows line up
 * against; anything an add-on adds is treated as an ordinary column.
 */
$ww_columns = apply_filters(
	'woo_wallet_statement_columns',
	array(
		'date'    => array(
			'label' => __( 'Date', 'woo-wallet' ),
			'class' => 'ww-stmt-date',
		),
		'type'    => array(
			'label' => __( 'Type', 'woo-wallet' ),
			'class' => 'ww-stmt-type',
		),
		'details' => array(
			'label' => __( 'Details', 'woo-wallet' ),
			'class' => 'ww-stmt-details',
		),
		'credit'  => array(
			'label' => __( 'Credit', 'woo-wallet' ),
			'class' => 'ww-stmt-num is-credit',
		),
		'debit'   => array(
			'label' => __( 'Debit', 'woo-wallet' ),
			'class' => 'ww-stmt-num is-debit',
		),
		'balance' => array(
			'label' => __( 'Balance', 'woo-wallet' ),
			'class' => 'ww-stmt-num ww-stmt-running',
		),
	)
);

$ww_keys        = array_keys( $ww_columns );
$ww_balance_at  = array_search( 'balance', $ww_keys, true );
$ww_first_money = false;
foreach ( array( 'credit', 'debit', 'balance' ) as $ww_money_key ) {
	$ww_at = array_search( $ww_money_key, $ww_keys, true );
	if ( false !== $ww_at && ( false === $ww_first_money || $ww_at < $ww_first_money ) ) {
		$ww_first_money = $ww_at;
	}
}

$ww_pager = $ww_statement['total_pages'] > 1 ? paginate_links(
	array(
		'base'      => add_query_arg( 'ww_page', '%#%' ),
		'format'    => '',
		'current'   => $ww_statement['page'],
		'total'     => $ww_statement['total_pages'],
		'type'      => 'list',
		'mid_size'  => 1,
		'prev_text' => __( 'Previous', 'woo-wallet' ),
		'next_text' => __( 'Next', 'woo-wallet' ),
	)
) : '';

?>
<div class="woo-wallet-statement">

	<header class="ww-stmt-masthead">
		<div class="ww-stmt-identity">
			<h3 class="ww-stmt-title"><?php esc_html_e( 'Account statement', 'woo-wallet' ); ?></h3>
			<p class="ww-stmt-period">
				<?php
				printf(
					/* translators: 1: period start date, 2: period end date */
					esc_html__( '%1$s – %2$s', 'woo-wallet' ),
					esc_html( date_i18n( wc_date_format(), strtotime( $ww_statement['from'] ) ) ),
					esc_html( date_i18n( wc_date_format(), strtotime( $ww_statement['to'] ) ) )
				);
				?>
			</p>
		</div>

		<div class="ww-stmt-actions">
			<a class="ww-stmt-ghost" href="<?php echo esc_url( $ww_csv_url ); ?>"><?php esc_html_e( 'Download CSV', 'woo-wallet' ); ?></a>
			<button type="button" class="ww-stmt-ghost" onclick="window.print();"><?php esc_html_e( 'Print', 'woo-wallet' ); ?></button>
		</div>
	</header>

	<form class="ww-stmt-filter" method="get">
		<?php if ( ! get_option( 'permalink_structure' ) ) : ?>
			<?php foreach ( $_GET as $ww_key => $ww_value ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<?php if ( ! in_array( $ww_key, array( 'from', 'to' ), true ) && is_scalar( $ww_value ) ) : ?>
					<input type="hidden" name="<?php echo esc_attr( $ww_key ); ?>" value="<?php echo esc_attr( wp_unslash( $ww_value ) ); ?>" />
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>

		<div class="ww-stmt-field">
			<label for="woo-wallet-statement-from"><?php esc_html_e( 'From', 'woo-wallet' ); ?></label>
			<input type="date" id="woo-wallet-statement-from" name="from" value="<?php echo esc_attr( $ww_statement['from'] ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
		</div>

		<div class="ww-stmt-field">
			<label for="woo-wallet-statement-to"><?php esc_html_e( 'To', 'woo-wallet' ); ?></label>
			<input type="date" id="woo-wallet-statement-to" name="to" value="<?php echo esc_attr( $ww_statement['to'] ); ?>" max="<?php echo esc_attr( current_time( 'Y-m-d' ) ); ?>" />
		</div>

		<button type="submit" class="ww-stmt-apply"><?php esc_html_e( 'Update', 'woo-wallet' ); ?></button>
	</form>

	<?php
	/*
	 * The summary is laid out as the arithmetic that produced the closing
	 * balance -- opening, plus credited, minus debited, equals closing -- rather
	 * than four unrelated figures. Reconciling that sum is the only reason a
	 * customer opens a statement, so the operators are content, not decoration.
	 * They are aria-hidden because the cell labels already carry the meaning.
	 */
	?>
	<ol class="ww-stmt-equation">
		<li class="ww-stmt-term">
			<span class="ww-stmt-term-label"><?php esc_html_e( 'Opening', 'woo-wallet' ); ?></span>
			<span class="ww-stmt-term-value"><?php echo wp_kses_post( wc_price( $ww_statement['opening'], $ww_price_args ) ); ?></span>
		</li>
		<li class="ww-stmt-op" aria-hidden="true">+</li>
		<li class="ww-stmt-term">
			<span class="ww-stmt-term-label"><?php esc_html_e( 'Credited', 'woo-wallet' ); ?></span>
			<span class="ww-stmt-term-value is-credit"><?php echo wp_kses_post( wc_price( $ww_statement['totals']['credited'], $ww_price_args ) ); ?></span>
		</li>
		<li class="ww-stmt-op" aria-hidden="true">&minus;</li>
		<li class="ww-stmt-term">
			<span class="ww-stmt-term-label"><?php esc_html_e( 'Debited', 'woo-wallet' ); ?></span>
			<span class="ww-stmt-term-value is-debit"><?php echo wp_kses_post( wc_price( $ww_statement['totals']['debited'], $ww_price_args ) ); ?></span>
		</li>
		<li class="ww-stmt-op" aria-hidden="true">=</li>
		<li class="ww-stmt-term is-closing">
			<span class="ww-stmt-term-label"><?php esc_html_e( 'Closing', 'woo-wallet' ); ?></span>
			<span class="ww-stmt-term-value"><?php echo wp_kses_post( wc_price( $ww_statement['closing'], $ww_price_args ) ); ?></span>
		</li>
	</ol>

	<?php if ( empty( $ww_statement['rows'] ) ) : ?>
		<p class="ww-stmt-note">
			<?php
			printf(
				/* translators: %s: the balance, unchanged across the period */
				wp_kses_post( __( 'No transactions in these dates. The balance stayed at %s throughout.', 'woo-wallet' ) ),
				wp_kses_post( wc_price( $ww_statement['closing'], $ww_price_args ) )
			);
			?>
		</p>
	<?php else : ?>
		<div class="ww-stmt-tablewrap">
			<table class="ww-stmt-table">
				<thead>
					<tr>
						<?php foreach ( $ww_columns as $ww_key => $ww_col ) : ?>
							<th scope="col" class="<?php echo esc_attr( false !== strpos( $ww_col['class'], 'ww-stmt-num' ) ? 'ww-stmt-num' : '' ); ?>"><?php echo esc_html( $ww_col['label'] ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<?php
					/*
					 * Page 1 opens on the period's opening balance. Later pages
					 * open on what the earlier pages carried into them, so the
					 * first running balance below always follows from the line
					 * above it -- the brought-forward / carried-forward pair a
					 * multi-page bank statement uses.
					 */
					$ww_is_first = 1 === $ww_statement['page'];
					$ww_is_last  = $ww_statement['page'] === $ww_statement['total_pages'];
					?>
					<tr class="ww-stmt-rule">
						<th scope="row" colspan="<?php echo esc_attr( false !== $ww_balance_at ? max( 1, $ww_balance_at ) : count( $ww_columns ) ); ?>"><?php echo $ww_is_first ? esc_html__( 'Opening balance', 'woo-wallet' ) : esc_html__( 'Brought forward', 'woo-wallet' ); ?></th>
						<?php if ( false !== $ww_balance_at ) : ?>
							<td class="ww-stmt-num" data-label="<?php esc_attr_e( 'Balance', 'woo-wallet' ); ?>"><?php echo wp_kses_post( wc_price( $ww_is_first ? $ww_statement['opening'] : $ww_statement['brought'], $ww_price_args ) ); ?></td>
							<?php for ( $ww_i = $ww_balance_at + 1; $ww_i < count( $ww_columns ); $ww_i++ ) : ?>
								<td></td>
							<?php endfor; ?>
						<?php endif; ?>
					</tr>
					<?php foreach ( $ww_statement['rows'] as $ww_row ) : ?>
						<?php
						/*
						 * The sign is markup rather than a CSS ::before so it appears
						 * only on the cell that actually holds an amount. In the
						 * stacked layout there are no Credit and Debit columns left to
						 * say which direction the money moved, and colour must not be
						 * the only thing that does.
						 */
						$ww_cells = array(
							'date'    => esc_html( date_i18n( wc_date_format(), strtotime( $ww_row->date ) ) ) . '<span class="ww-stmt-time">' . esc_html( date_i18n( wc_time_format(), strtotime( $ww_row->date ) ) ) . '</span>',
							'type'    => esc_html( woo_wallet_get_transaction_type_label( $ww_row->category ) ),
							'details' => esc_html( wp_strip_all_tags( (string) $ww_row->details ) ),
							'credit'  => 'credit' === $ww_row->type ? '<span class="ww-stmt-sign" aria-hidden="true">+</span>' . wp_kses_post( wc_price( $ww_row->amount, $ww_price_args ) ) : '<span class="ww-stmt-empty" aria-hidden="true">&mdash;</span>',
							'debit'   => 'debit' === $ww_row->type ? '<span class="ww-stmt-sign" aria-hidden="true">&minus;</span>' . wp_kses_post( wc_price( $ww_row->amount, $ww_price_args ) ) : '<span class="ww-stmt-empty" aria-hidden="true">&mdash;</span>',
							'balance' => wp_kses_post( wc_price( $ww_row->balance, $ww_price_args ) ),
						);

						// Cells arrive already escaped and are printed as markup, so a
						// filter that returns raw input is trusting its own add-on.
						$ww_cells = apply_filters( 'woo_wallet_statement_row_cells', $ww_cells, $ww_row, $ww_statement );
						?>
						<tr>
							<?php foreach ( $ww_columns as $ww_key => $ww_col ) : ?>
								<td class="<?php echo esc_attr( $ww_col['class'] ); ?>" data-label="<?php echo esc_attr( $ww_col['label'] ); ?>"><?php echo isset( $ww_cells[ $ww_key ] ) ? wp_kses_post( $ww_cells[ $ww_key ] ) : ''; ?></td>
							<?php endforeach; ?>
						</tr>
					<?php endforeach; ?>
					<?php
					/*
					 * Only the last page can close the period. Earlier pages
					 * carry their last running balance forward instead -- the
					 * period totals belong beside the closing figure they add
					 * up to, not beside a balance halfway through.
					 */
					$ww_last_row = end( $ww_statement['rows'] );
					?>
					<tr class="ww-stmt-rule">
						<th scope="row" colspan="<?php echo esc_attr( false !== $ww_first_money ? max( 1, $ww_first_money ) : count( $ww_columns ) ); ?>"><?php echo $ww_is_last ? esc_html__( 'Closing balance', 'woo-wallet' ) : esc_html__( 'Carried forward', 'woo-wallet' ); ?></th>
						<?php
						if ( false !== $ww_first_money ) :
							$ww_summary = $ww_is_last
								? array(
									'credit'  => $ww_statement['totals']['credited'],
									'debit'   => $ww_statement['totals']['debited'],
									'balance' => $ww_statement['closing'],
								)
								: array( 'balance' => $ww_last_row->balance );
							foreach ( array_slice( $ww_keys, $ww_first_money ) as $ww_key ) :
								?>
								<?php if ( isset( $ww_summary[ $ww_key ] ) ) : ?>
									<td class="<?php echo esc_attr( $ww_columns[ $ww_key ]['class'] ); ?>" data-label="<?php echo esc_attr( $ww_columns[ $ww_key ]['label'] ); ?>"><?php echo wp_kses_post( wc_price( $ww_summary[ $ww_key ], $ww_price_args ) ); ?></td>
								<?php else : ?>
									<?php // No figure for this column on this row -- and no data-label either, or the stacked layout prints a heading with nothing under it. ?>
									<td></td>
								<?php endif; ?>
								<?php
							endforeach;
						endif;
						?>
					</tr>
				</tbody>
			</table>
		</div>

		<?php if ( $ww_pager ) : ?>
			<nav class="ww-stmt-pager" aria-label="<?php esc_attr_e( 'Statement pages', 'woo-wallet' ); ?>">
				<p class="ww-stmt-pager-count">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages, 3: number of transactions in the period */
						esc_html__( 'Page %1$s of %2$s — %3$s transactions in this period', 'woo-wallet' ),
						esc_html( number_format_i18n( $ww_statement['page'] ) ),
						esc_html( number_format_i18n( $ww_statement['total_pages'] ) ),
						esc_html( number_format_i18n( $ww_statement['count'] ) )
					);
					?>
				</p>
				<?php echo wp_kses_post( $ww_pager ); ?>
			</nav>
		<?php endif; ?>
	<?php endif; ?>

	<p class="ww-stmt-generated">
		<?php
		printf(
			/* translators: %s: date and time the statement was generated */
			esc_html__( 'Generated on %s', 'woo-wallet' ),
			esc_html( date_i18n( wc_date_format() . ' ' . wc_time_format(), strtotime( $ww_statement['generated'] ) ) )
		);
		?>
	</p>
</div>
