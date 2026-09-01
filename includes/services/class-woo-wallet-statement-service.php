<?php
/**
 * Wallet account statement service.
 *
 * Builds the customer-facing account statement for a date range: opening
 * balance, the transactions in the period, and the closing balance.
 *
 * The ledger stores no running balance, so the opening balance is derived with
 * a single aggregate over everything dated before the period. The
 * `idx_user_date (user_id, date)` index on `woo_wallet_transactions` covers
 * that read.
 *
 * This service is read-only. It never calls credit()/debit()/transfer(), takes
 * no `GET_LOCK` and opens no transaction, so it can never contend with a
 * money-moving write.
 *
 * Two things are deliberate and load-bearing:
 *
 *  - Rows are ordered by `date ASC, transaction_id ASC`. `date` is only
 *    second-resolution, so without the id tiebreak two transactions recorded in
 *    the same second could swap places between two renders of the same
 *    statement and the running balance column would disagree with itself.
 *
 *  - Totals and the closing balance come from their own aggregate query, not
 *    from summing the returned rows. The row list is paginated; the aggregates
 *    are not, so page 7 of a statement still reconciles against the whole
 *    period.
 *
 *  - Every figure is the raw ledger sum. `woo_wallet_current_balance` is NOT
 *    applied, so a statement can read differently from the balance the
 *    dashboard shows on a store running a filter that recomputes the
 *    advertised balance (credit expiry, redeemed totals, marketplace holds).
 *    That is deliberate: the statement's job is to show that opening plus
 *    credited minus debited equals closing, and a closing figure adjusted by a
 *    filter with no matching row in the period would make that arithmetic lie.
 *    The statement is the ledger of record; the dashboard figure is what the
 *    store currently advertises as spendable.
 *
 * @package StandaleneTech
 * @since   1.6.15
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooWallet_Statement_Service' ) ) {

	/**
	 * Statement service.
	 */
	class WooWallet_Statement_Service {

		/**
		 * Widest range a statement may span, in days.
		 */
		const MAX_DAYS = 366;

		/**
		 * Ceiling on `per_page`. A caller may not ask for more rows than this
		 * in one request. Aggregates ignore it.
		 */
		const MAX_ROWS = 1000;

		/**
		 * Rows per page when the caller does not say.
		 */
		const PER_PAGE = 50;

		/**
		 * Build a statement.
		 *
		 * @param int    $user_id  User whose statement this is.
		 * @param string $from     Start date, `Y-m-d`, site-local, inclusive.
		 * @param string $to       End date, `Y-m-d`, site-local, inclusive.
		 * @param string $currency Optional ISO 4217 code. Only consulted in per_currency mode.
		 * @param int    $page     1-based page of the row list.
		 * @param int    $per_page Rows per page, clamped to `max_rows()`.
		 * @return array
		 */
		public static function get_statement( $user_id, $from, $to, $currency = '', $page = 1, $per_page = self::PER_PAGE ) {
			$user_id = absint( $user_id );
			$range   = self::resolve_range( $from, $to );
			$scope   = self::currency_scope( $currency );

			$adjustments = self::get_adjustments( $user_id, $range, $scope );
			$source      = self::rows_source( $user_id, $range, $scope, $adjustments, self::row_column_names() );

			$opening = self::get_opening_balance( $user_id, $range['start'], $scope );
			$totals  = self::get_totals( $source );
			$count   = self::get_row_count( $source );

			$per_page    = min( max( 1, (int) $per_page ), self::max_rows() );
			$total_pages = max( 1, (int) ceil( $count / $per_page ) );
			$page        = min( max( 1, (int) $page ), $total_pages );
			$offset      = ( $page - 1 ) * $per_page;

			// Page 2 onward cannot start its running balance from the period
			// opening -- it has to carry everything the earlier pages already
			// spent or earned. That figure is also what the page prints as its
			// brought-forward line, the way a multi-page bank statement does.
			$carried = $opening + self::balance_before_offset( $source, $offset );
			$rows    = self::get_rows( $user_id, $range, $scope, $per_page, $offset );

			return array(
				'user_id'     => $user_id,
				'from'        => $range['from'],
				'to'          => $range['to'],
				'currency'    => $scope['currency'],
				'scoped'      => $scope['scoped'],
				'opening'     => $opening,
				'brought'     => $carried,
				'rows'        => self::apply_running_balance( $rows, $carried ),
				'closing'     => $opening + $totals['net'],
				'totals'      => $totals,
				'count'       => $count,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => $total_pages,
				'max_rows'    => self::max_rows(),
				'generated'   => current_time( 'mysql' ),
			);
		}

		/**
		 * Convert a statement into the currency the customer is browsing in.
		 *
		 * `get_statement()` returns raw ledger figures. In `single_base` mode
		 * the ledger holds base-currency amounts while the storefront may be
		 * showing any active currency, so every figure has to go through
		 * `woo_wallet_amount` -- the same filter `templates/dashboard.php` and
		 * `Woo_Wallet_Wallet::get_wallet_balance()` use -- before it is
		 * displayed. Skipping it prints base-currency numbers under the active
		 * currency's symbol, which is what a multi-currency plugin's switcher
		 * makes look like "only the symbol changed".
		 *
		 * In `per_currency` mode the rows already are the scoped currency, so
		 * the statement is returned untouched; converting there would move
		 * money that is already denominated correctly.
		 *
		 * Rows, the running balance and the aggregates are all converted, so
		 * opening + credited - debited = closing still reads true on screen.
		 *
		 * @param array $statement A statement from `get_statement()`.
		 * @return array
		 */
		public static function to_display_currency( $statement ) {
			if ( ! empty( $statement['scoped'] ) ) {
				return $statement;
			}

			// Aggregates are sums over base-currency rows, so the base currency
			// is what they have to be converted *from*. Reading the active
			// currency here instead would make every conversion an identity.
			$user_id = isset( $statement['user_id'] ) ? (int) $statement['user_id'] : 0;
			$base    = self::base_currency();
			$convert = static function ( $amount, $from ) use ( $user_id ) {
				return (float) apply_filters( 'woo_wallet_amount', (float) $amount, $from, $user_id );
			};

			$statement['opening'] = $convert( $statement['opening'], $base );
			$statement['brought'] = $convert( $statement['brought'], $base );
			$statement['closing'] = $convert( $statement['closing'], $base );

			foreach ( array( 'credited', 'debited', 'net' ) as $key ) {
				$statement['totals'][ $key ] = $convert( $statement['totals'][ $key ], $base );
			}

			foreach ( $statement['rows'] as $row ) {
				$row->amount  = self::convert_row_amount( $row, $user_id, $base );
				$row->balance = $convert( $row->balance, $base );
			}

			return $statement;
		}

		/**
		 * Convert one row's amount into the active currency.
		 *
		 * The single place that knows a row's amount is denominated in the
		 * currency the row was recorded in -- and that legacy rows predating
		 * the single-currency ledger carry an empty `currency`, which must
		 * fall back to base. `Woo_Wallet_Currency_Manager::convert()`
		 * short-circuits on an empty `$from` and returns the amount
		 * unconverted, so a caller that skips this fallback silently prints
		 * base-currency figures under the active currency's label.
		 *
		 * Both the on-screen statement and the CSV export go through here so
		 * the download cannot drift from the page it was downloaded from.
		 *
		 * @param object $row     Statement row.
		 * @param int    $user_id User ID.
		 * @param string $base    Optional base currency, to save a lookup in a loop.
		 * @return float
		 */
		public static function convert_row_amount( $row, $user_id, $base = '' ) {
			$base         = '' !== $base ? $base : self::base_currency();
			$row_currency = '' !== (string) $row->currency ? (string) $row->currency : $base;

			return (float) apply_filters( 'woo_wallet_amount', (float) $row->amount, $row_currency, (int) $user_id );
		}

		/**
		 * Currency the ledger stores amounts in outside per-currency mode.
		 *
		 * @return string ISO 4217 code.
		 */
		private static function base_currency() {
			if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
				return Woo_Wallet_Currency_Manager::instance()->get_base_currency();
			}
			$base = get_option( 'woocommerce_currency' );
			return is_string( $base ) && '' !== $base ? strtoupper( $base ) : 'USD';
		}

		/**
		 * Sanitise and clamp a requested date range.
		 *
		 * Never errors. A statement page that refuses to render is worse than
		 * one showing a sane period, so every bad input resolves to something
		 * displayable:
		 *  - unparseable dates fall back to the current calendar month;
		 *  - an inverted range is swapped;
		 *  - a future end date is clamped to today;
		 *  - an over-wide range is trimmed back from the end date.
		 *
		 * Boundaries are site-local, because the ledger writes `date` with
		 * `current_time( 'mysql' )`. Building them with gmdate() would shift
		 * every boundary by the site's UTC offset and silently drop or gain a
		 * day's transactions.
		 *
		 * @param string $from Start date, `Y-m-d`.
		 * @param string $to   End date, `Y-m-d`.
		 * @return array
		 */
		public static function resolve_range( $from, $to ) {
			$today = current_time( 'Y-m-d' );
			$from  = self::parse_date( $from );
			$to    = self::parse_date( $to );

			if ( '' === $from || '' === $to ) {
				$from = current_time( 'Y-m-01' );
				$to   = $today;
			}

			// Clamp both ends to today *before* testing for inversion. Clamping
			// only the end date can itself invert an entirely-future range
			// (from = next month, to = next month -> to = today < from), which
			// would then silently report an empty period instead of one.
			if ( $from > $today ) {
				$from = $today;
			}
			if ( $to > $today ) {
				$to = $today;
			}

			if ( $from > $to ) {
				$swap = $from;
				$from = $to;
				$to   = $swap;
			}

			$max_days = self::max_days();
			if ( $max_days > 0 ) {
				$earliest = gmdate( 'Y-m-d', strtotime( $to . ' -' . ( $max_days - 1 ) . ' days' ) );
				if ( $from < $earliest ) {
					$from = $earliest;
				}
			}

			return array(
				'from'  => $from,
				'to'    => $to,
				'start' => $from . ' 00:00:00',
				'end'   => $to . ' 23:59:59',
			);
		}

		/**
		 * Validate a `Y-m-d` date string.
		 *
		 * @param string $date Raw input.
		 * @return string Valid `Y-m-d`, or '' when unparseable.
		 */
		private static function parse_date( $date ) {
			$date = is_string( $date ) ? trim( $date ) : '';
			if ( '' === $date ) {
				return '';
			}
			$parts = date_parse_from_format( 'Y-m-d', $date );
			if ( ! empty( $parts['error_count'] ) || ! checkdate( (int) $parts['month'], (int) $parts['day'], (int) $parts['year'] ) ) {
				return '';
			}
			return sprintf( '%04d-%02d-%02d', $parts['year'], $parts['month'], $parts['day'] );
		}

		/**
		 * Resolve which currency, if any, scopes the statement.
		 *
		 * Mirrors `Woo_Wallet_Wallet::get_wallet_balance()` rather than
		 * inventing statement-specific currency logic, so the statement's
		 * opening and closing always agree with the balance the dashboard
		 * shows.
		 *
		 * @param string $currency Optional requested ISO code.
		 * @return array { @type bool $scoped, @type string $currency }
		 */
		private static function currency_scope( $currency ) {
			$wallet   = woo_wallet()->wallet;
			$currency = is_string( $currency ) ? strtoupper( trim( $currency ) ) : '';
			if ( '' !== $currency && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				$currency = '';
			}
			$resolved = '' !== $currency ? $currency : $wallet->resolve_active_currency();

			return array(
				'scoped'   => 'per_currency' === $wallet->get_currency_mode(),
				'currency' => $resolved,
			);
		}

		/**
		 * Balance carried into the period: everything dated strictly before it.
		 *
		 * @global wpdb $wpdb
		 * @param int    $user_id User ID.
		 * @param string $start   Period start, `Y-m-d H:i:s`.
		 * @param array  $scope   Currency scope.
		 * @return float
		 */
		private static function get_opening_balance( $user_id, $start, $scope ) {
			global $wpdb;

			if ( $scope['scoped'] ) {
				$balance = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CASE WHEN t.type = 'credit' THEN t.amount ELSE -t.amount END) FROM {$wpdb->base_prefix}woo_wallet_transactions AS t WHERE t.user_id = %d AND t.deleted = 0 AND t.date < %s AND t.currency = %s", $user_id, $start, $scope['currency'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$balance = $wpdb->get_var( $wpdb->prepare( "SELECT SUM(CASE WHEN t.type = 'credit' THEN t.amount ELSE -t.amount END) FROM {$wpdb->base_prefix}woo_wallet_transactions AS t WHERE t.user_id = %d AND t.deleted = 0 AND t.date < %s", $user_id, $start ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			/**
			 * Balance movements before the period that are not ledger rows.
			 *
			 * The companion to `woo_wallet_statement_adjustments`: whatever an
			 * add-on has already taken off (or added to) the balance before
			 * this period started has to be carried in, or every statement
			 * after the one containing the movement opens too high.
			 *
			 * Return a signed delta in the ledger's currency -- negative for
			 * credit that has lapsed, positive for anything an add-on grants
			 * outside the ledger.
			 *
			 * @param float  $delta   Running delta, 0.0 by default.
			 * @param int    $user_id User the statement is for.
			 * @param string $start   Period start, `Y-m-d H:i:s`; everything strictly before it counts.
			 * @param array  $scope   Currency scope: `scoped`, `currency`.
			 */
			$delta = (float) apply_filters( 'woo_wallet_statement_opening_adjustment', 0.0, $user_id, $start, $scope );

			return (float) $balance + $delta;
		}

		/**
		 * Credited / debited / net totals for the period.
		 *
		 * @global wpdb $wpdb
		 * @param string $source Row source SQL from `rows_source()`.
		 * @return array
		 */
		private static function get_totals( $source ) {
			global $wpdb;

			$row = $wpdb->get_row( "SELECT SUM(CASE WHEN t.type = 'credit' THEN t.amount ELSE 0 END) AS credited, SUM(CASE WHEN t.type = 'debit' THEN t.amount ELSE 0 END) AS debited FROM {$source}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$credited = $row ? (float) $row->credited : 0.0;
			$debited  = $row ? (float) $row->debited : 0.0;

			return array(
				'credited' => $credited,
				'debited'  => $debited,
				'net'      => $credited - $debited,
			);
		}

		/**
		 * How many rows the period lists.
		 *
		 * @global wpdb $wpdb
		 * @param string $source Row source SQL from `rows_source()`.
		 * @return int
		 */
		private static function get_row_count( $source ) {
			global $wpdb;

			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$source}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}

		/**
		 * Columns the row query selects.
		 *
		 * Add-ons that render an extra statement column need the underlying
		 * field on the row object -- `woo-wallet-pro`'s credit-expiry column
		 * needs `expire_date`, a column that module adds to the table itself.
		 * The core set is always kept, every filtered name goes through
		 * `sanitize_key()`, and anything that is not actually a column on the
		 * table is dropped: a typo in somebody else's filter should cost that
		 * add-on its column, not blank the customer's whole statement with a
		 * failed query.
		 *
		 * @return array Column names, core set first.
		 */
		private static function row_column_names() {
			$core     = array( 'transaction_id', 'type', 'category', 'amount', 'currency', 'details', 'date' );
			$filtered = array_filter( array_map( 'sanitize_key', (array) apply_filters( 'woo_wallet_statement_row_columns', $core ) ) );
			$extra    = array_diff( $filtered, $core );

			if ( $extra ) {
				$extra = array_intersect( $extra, self::table_columns() );
			}

			return array_merge( $core, array_values( $extra ) );
		}

		/**
		 * Balance movements in the period that are not ledger rows.
		 *
		 * Some add-ons change a wallet's balance without writing a
		 * transaction. TeraWallet Pro's credit-expiry module is the case this
		 * exists for: it leaves the original credit row alone and subtracts the
		 * unredeemed remainder of a lapsed lot at balance time, through
		 * `woo_wallet_current_balance`. A statement built purely from ledger
		 * rows therefore closes higher than the balance shown everywhere else,
		 * with nothing on the page to explain the difference.
		 *
		 * Returning a dated adjustment here puts that movement on the statement
		 * as an ordinary line: it sorts into the transaction list at its own
		 * date, walks through the running balance, and counts toward the
		 * period's credited or debited total, so the statement still reconciles
		 * and the customer can see what happened and when.
		 *
		 * Each entry is an array:
		 *
		 *     array(
		 *         'date'     => '2026-08-16 23:59:59', // site-local, inside the period
		 *         'type'     => 'debit',               // or 'credit'
		 *         'amount'   => 8.00,                  // positive; the mover, not the lot
		 *         'category' => 'credit_expired',      // used for the Type label
		 *         'details'  => 'Unused balance from the 15 Aug credit',
		 *         'currency' => 'INR',                 // optional; defaults to the statement's
		 *     )
		 *
		 * The amount must be the sum the balance actually moves by. Where an
		 * add-on tracks partial consumption, that is the remainder, not the
		 * original figure: a lot of 10.00 with 2.00 already spent moves the
		 * balance by 8.00, and the 2.00 is already on the statement as its own
		 * debit row. Reporting 10.00 would take the 2.00 twice.
		 *
		 * Anything the add-on knows about that predates the period belongs in
		 * `woo_wallet_statement_opening_adjustment` instead, so it is carried
		 * in rather than listed again.
		 *
		 * @param int   $user_id User the statement is for.
		 * @param array $range   Resolved range: `from`, `to`, `start`, `end`.
		 * @param array $scope   Currency scope: `scoped`, `currency`.
		 * @return array
		 */
		private static function get_adjustments( $user_id, $range, $scope ) {
			$adjustments = apply_filters( 'woo_wallet_statement_adjustments', array(), $user_id, $range, $scope );
			if ( ! is_array( $adjustments ) || ! $adjustments ) {
				return array();
			}

			$clean = array();
			foreach ( $adjustments as $adjustment ) {
				$adjustment = (array) $adjustment;

				$amount = isset( $adjustment['amount'] ) ? (float) $adjustment['amount'] : 0.0;
				$date   = isset( $adjustment['date'] ) ? (string) $adjustment['date'] : '';
				$type   = isset( $adjustment['type'] ) && 'credit' === $adjustment['type'] ? 'credit' : 'debit';

				// A zero mover is not a movement, and a date outside the period
				// would either duplicate the opening adjustment or leak a future
				// row into a closed statement.
				if ( $amount <= 0 || $date < $range['start'] || $date > $range['end'] ) {
					continue;
				}

				$currency = isset( $adjustment['currency'] ) ? strtoupper( (string) $adjustment['currency'] ) : '';
				if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
					$currency = $scope['currency'];
				}
				// In per-currency mode the statement is one currency's ledger;
				// an adjustment in another currency does not belong in it.
				if ( $scope['scoped'] && $currency !== $scope['currency'] ) {
					continue;
				}

				$clean[] = array(
					'transaction_id' => 0,
					'type'           => $type,
					'category'       => isset( $adjustment['category'] ) ? sanitize_key( $adjustment['category'] ) : 'adjustment',
					'amount'         => $amount,
					'currency'       => $currency,
					'details'        => isset( $adjustment['details'] ) ? wp_strip_all_tags( (string) $adjustment['details'] ) : '',
					'date'           => $date,
				);
			}

			return $clean;
		}

		/**
		 * The set every statement query reads from: the period's ledger rows,
		 * plus any add-on adjustments, as one orderable source.
		 *
		 * Building this once and reusing it for the row list, the count, the
		 * totals and the carried balance is what keeps those four in agreement.
		 * An adjustment folded into the row list but forgotten by the totals
		 * would put a line on the page that the closing balance does not
		 * account for.
		 *
		 * With no adjustments -- the case on every site without such an add-on
		 * -- this is exactly the plain table query it always was.
		 *
		 * @global wpdb $wpdb
		 * @param int   $user_id     User ID.
		 * @param array $range       Resolved range.
		 * @param array $scope       Currency scope.
		 * @param array $adjustments Normalised adjustments.
		 * @param array $columns     Column names to select.
		 * @return string SQL fragment usable as a FROM source, aliased `t`.
		 */
		private static function rows_source( $user_id, $range, $scope, $adjustments, $columns ) {
			global $wpdb;

			$select = 't.' . implode( ', t.', $columns );

			if ( $scope['scoped'] ) {
				$ledger = $wpdb->prepare( "SELECT {$select} FROM {$wpdb->base_prefix}woo_wallet_transactions AS t WHERE t.user_id = %d AND t.deleted = 0 AND t.date >= %s AND t.date <= %s AND t.currency = %s", $user_id, $range['start'], $range['end'], $scope['currency'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			} else {
				$ledger = $wpdb->prepare( "SELECT {$select} FROM {$wpdb->base_prefix}woo_wallet_transactions AS t WHERE t.user_id = %d AND t.deleted = 0 AND t.date >= %s AND t.date <= %s", $user_id, $range['start'], $range['end'] ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			}

			if ( ! $adjustments ) {
				return '( ' . $ledger . ' ) AS t';
			}

			// Every adjustment value is bound, never interpolated. Columns an
			// add-on added through `woo_wallet_statement_row_columns` have no
			// counterpart on a synthetic row, so they come through as NULL.
			$unions = array();
			foreach ( $adjustments as $adjustment ) {
				$parts  = array();
				$values = array();
				foreach ( $columns as $column ) {
					if ( ! array_key_exists( $column, $adjustment ) ) {
						$parts[] = 'NULL';
						continue;
					}
					$parts[]  = 'amount' === $column || 'transaction_id' === $column ? '%f' : '%s';
					$values[] = $adjustment[ $column ];
				}
				if ( 'transaction_id' === $columns[0] ) {
					$parts[0] = '%d';
				}
				$unions[] = $wpdb->prepare( 'SELECT ' . implode( ', ', $parts ), $values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			}

			return '( ' . $ledger . ' UNION ALL ' . implode( ' UNION ALL ', $unions ) . ' ) AS t';
		}

		/**
		 * Column names on the transactions table.
		 *
		 * Read once per request, and only when a filter actually asked for a
		 * column outside the core set.
		 *
		 * @global wpdb $wpdb
		 * @return array
		 */
		private static function table_columns() {
			static $columns = null;

			if ( null === $columns ) {
				global $wpdb;
				$found   = $wpdb->get_col( "SHOW COLUMNS FROM {$wpdb->base_prefix}woo_wallet_transactions" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$columns = is_array( $found ) ? $found : array();
			}

			return $columns;
		}

		/**
		 * Rows in the period, oldest first.
		 *
		 * Public so the CSV handler can stream the same rows in chunks without
		 * going through the on-screen page size.
		 *
		 * Ordering is `date ASC, transaction_id ASC`. Adjustments carry no
		 * transaction id, so one landing on the same second as a real
		 * transaction sorts ahead of it -- deterministic, which is what the
		 * running balance needs.
		 *
		 * @global wpdb $wpdb
		 * @param int   $user_id User ID.
		 * @param array $range   Resolved range.
		 * @param array $scope   Currency scope.
		 * @param int   $limit   Optional LIMIT. 0 for no limit.
		 * @param int   $offset  Optional OFFSET.
		 * @return array
		 */
		public static function get_rows( $user_id, $range, $scope, $limit = 0, $offset = 0 ) {
			global $wpdb;

			$columns = self::row_column_names();
			$source  = self::rows_source( $user_id, $range, $scope, self::get_adjustments( $user_id, $range, $scope ), $columns );

			$limit_sql = '';
			if ( $limit > 0 ) {
				$limit_sql = $wpdb->prepare( ' LIMIT %d OFFSET %d', (int) $limit, max( 0, (int) $offset ) );
			}

			$rows = $wpdb->get_results( "SELECT * FROM {$source} ORDER BY t.date ASC, t.transaction_id ASC" . $limit_sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared

			return is_array( $rows ) ? $rows : array();
		}

		/**
		 * Net movement of the rows the current page skipped over.
		 *
		 * Sums exactly the set the page skips -- same source, same ordering.
		 * Any divergence and the running balance disagrees with itself across a
		 * page break; reading a different source than `get_rows()` would drop
		 * add-on adjustments out of the carried figure while still listing
		 * them.
		 *
		 * @global wpdb $wpdb
		 * @param string $source Row source SQL from `rows_source()`.
		 * @param int    $offset How many rows the page skips.
		 * @return float
		 */
		private static function balance_before_offset( $source, $offset ) {
			global $wpdb;

			$offset = (int) $offset;
			if ( $offset < 1 ) {
				return 0.0;
			}

			$inner = $wpdb->prepare( "SELECT t.type, t.amount FROM {$source} ORDER BY t.date ASC, t.transaction_id ASC LIMIT %d", $offset ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			$net = $wpdb->get_var( "SELECT SUM(CASE WHEN x.type = 'credit' THEN x.amount ELSE -x.amount END) FROM ( {$inner} ) AS x" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

			return (float) $net;
		}

		/**
		 * Walk the rows forward from the opening balance, stamping a running
		 * balance on each.
		 *
		 * @param array $rows    Transaction rows, oldest first.
		 * @param float $opening Opening balance.
		 * @return array
		 */
		private static function apply_running_balance( $rows, $opening ) {
			$balance = (float) $opening;

			foreach ( $rows as $row ) {
				$amount        = (float) $row->amount;
				$balance      += 'credit' === $row->type ? $amount : -$amount;
				$row->balance  = $balance;
			}

			return $rows;
		}

		/**
		 * Widest permitted range, in days. 0 disables the cap.
		 *
		 * @return int
		 */
		public static function max_days() {
			return (int) apply_filters( 'woo_wallet_statement_max_days', self::MAX_DAYS );
		}

		/**
		 * Ceiling on rows per page. 0 disables the cap.
		 *
		 * @return int
		 */
		public static function max_rows() {
			$max = (int) apply_filters( 'woo_wallet_statement_max_rows', self::MAX_ROWS );
			return $max > 0 ? $max : PHP_INT_MAX;
		}
	}
}
