<?php
/**
 * Wallet account statement integration tests.
 *
 * Covers WooWallet_Statement_Service — the customer-facing statement core:
 *  - the opening balance carries in everything dated before the period;
 *  - the running balance walks forward and lands on the closing balance;
 *  - a statement ending today reconciles with get_wallet_balance();
 *  - both range boundaries are inclusive;
 *  - soft-deleted rows are excluded from the opening balance and the rows;
 *  - same-second transactions order deterministically;
 *  - bad ranges clamp instead of erroring;
 *  - the running balance stays continuous across a page break, inside one
 *    currency, while the aggregates read the same on every page.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers WooWallet_Statement_Service
 */
class Test_Statement_Service extends WP_UnitTestCase {

	/**
	 * Customer the test operates on.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Create a fresh customer and load the service under test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-statement-service.php';
	}

	/**
	 * Record a transaction on a specific date.
	 *
	 * @param string $type    'credit' or 'debit'.
	 * @param float  $amount  Amount.
	 * @param string $date    Site-local `Y-m-d H:i:s`.
	 * @param string $details Transaction note.
	 * @return int Transaction id.
	 */
	private function record( $type, $amount, $date, $details = 'Test' ) {
		return woo_wallet()->wallet->{$type}( $this->user_id, $amount, $details, array( 'date' => $date ) );
	}

	/**
	 * Build a statement for the given range.
	 *
	 * @param string $from Start date.
	 * @param string $to   End date.
	 * @return array
	 */
	private function statement( $from, $to ) {
		return WooWallet_Statement_Service::get_statement( $this->user_id, $from, $to );
	}

	/**
	 * Everything dated before the period lands in the opening balance and
	 * nowhere else.
	 */
	public function test_opening_balance_carries_in_earlier_transactions() {
		$this->record( 'credit', 100.00, '2026-01-10 10:00:00' );
		$this->record( 'credit', 25.00, '2026-02-05 10:00:00' );

		$statement = $this->statement( '2026-02-01', '2026-02-28' );

		$this->assertEquals( 100.00, $statement['opening'] );
		$this->assertCount( 1, $statement['rows'] );
		$this->assertEquals( 125.00, $statement['closing'] );
	}

	/**
	 * The running balance on the last row equals the closing balance.
	 */
	public function test_running_balance_lands_on_closing_balance() {
		$this->record( 'credit', 100.00, '2026-03-02 09:00:00' );
		$this->record( 'debit', 30.00, '2026-03-05 09:00:00' );
		$this->record( 'credit', 10.00, '2026-03-09 09:00:00' );

		$statement = $this->statement( '2026-03-01', '2026-03-31' );
		$rows      = $statement['rows'];

		$this->assertCount( 3, $rows );
		$this->assertEquals( 100.00, $rows[0]->balance );
		$this->assertEquals( 70.00, $rows[1]->balance );
		$this->assertEquals( 80.00, $rows[2]->balance );
		$this->assertEquals( 80.00, $statement['closing'] );
		$this->assertEquals( end( $rows )->balance, $statement['closing'] );
	}

	/**
	 * Period totals are reported separately from the net movement.
	 */
	public function test_period_totals() {
		$this->record( 'credit', 60.00, '2026-04-02 09:00:00' );
		$this->record( 'debit', 15.00, '2026-04-03 09:00:00' );
		$this->record( 'debit', 5.00, '2026-04-04 09:00:00' );

		$statement = $this->statement( '2026-04-01', '2026-04-30' );

		$this->assertEquals( 60.00, $statement['totals']['credited'] );
		$this->assertEquals( 20.00, $statement['totals']['debited'] );
		$this->assertEquals( 40.00, $statement['totals']['net'] );
	}

	/**
	 * A statement that ends today must agree with the live wallet balance.
	 * This is the invariant that makes the whole feature trustworthy.
	 */
	public function test_closing_balance_matches_wallet_balance_today() {
		woo_wallet()->wallet->credit( $this->user_id, 200.00, 'Seed' );
		woo_wallet()->wallet->debit( $this->user_id, 45.50, 'Spend' );

		$statement = $this->statement( '2000-01-01', current_time( 'Y-m-d' ) );
		$balance   = (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );

		$this->assertEquals( $balance, $statement['closing'] );
	}

	/**
	 * `woo_wallet_current_balance` reshapes the balance the store advertises as
	 * spendable -- credit expiry and marketplace holds use it. The statement
	 * must ignore it: a closing figure moved by a filter, with no row in the
	 * period to account for the movement, would break the one thing the page
	 * exists to show, that opening + credited - debited = closing.
	 */
	public function test_statement_ignores_the_advertised_balance_filter() {
		woo_wallet()->wallet->credit( $this->user_id, 200.00, 'Seed' );
		woo_wallet()->wallet->debit( $this->user_id, 50.00, 'Spend' );

		add_filter( 'woo_wallet_current_balance', array( $this, 'shrink_advertised_balance' ) );
		$statement  = $this->statement( '2000-01-01', current_time( 'Y-m-d' ) );
		$advertised = (float) woo_wallet()->wallet->get_wallet_balance( $this->user_id, 'edit' );
		remove_filter( 'woo_wallet_current_balance', array( $this, 'shrink_advertised_balance' ) );

		$this->assertEquals( 40.00, $advertised );
		$this->assertEquals( 150.00, $statement['closing'] );
		$this->assertEquals(
			$statement['opening'] + $statement['totals']['credited'] - $statement['totals']['debited'],
			$statement['closing']
		);
	}

	/**
	 * Filter callback: advertise less than the ledger holds, as a credit-expiry
	 * add-on does.
	 *
	 * @return float
	 */
	public function shrink_advertised_balance() {
		return 40.00;
	}

	/**
	 * Both ends of the range are inclusive: a transaction at 00:00:00 on the
	 * start date and one at 23:59:59 on the end date are both in the period.
	 */
	public function test_range_boundaries_are_inclusive() {
		$this->record( 'credit', 10.00, '2026-05-01 00:00:00' );
		$this->record( 'credit', 20.00, '2026-05-31 23:59:59' );
		$this->record( 'credit', 40.00, '2026-06-01 00:00:00' );

		$statement = $this->statement( '2026-05-01', '2026-05-31' );

		$this->assertCount( 2, $statement['rows'] );
		$this->assertEquals( 30.00, $statement['totals']['credited'] );
		$this->assertEquals( 0.00, $statement['opening'] );
	}

	/**
	 * Soft-deleted rows must not appear in the period and must not be carried
	 * into the opening balance either.
	 */
	public function test_soft_deleted_rows_are_excluded_everywhere() {
		global $wpdb;

		$before = $this->record( 'credit', 100.00, '2026-07-01 09:00:00' );
		$inside = $this->record( 'credit', 50.00, '2026-08-10 09:00:00' );
		$this->record( 'credit', 7.00, '2026-08-11 09:00:00' );

		$wpdb->update( $wpdb->base_prefix . 'woo_wallet_transactions', array( 'deleted' => 1 ), array( 'transaction_id' => $before ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update( $wpdb->base_prefix . 'woo_wallet_transactions', array( 'deleted' => 1 ), array( 'transaction_id' => $inside ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$statement = $this->statement( '2026-08-01', '2026-08-31' );

		$this->assertEquals( 0.00, $statement['opening'] );
		$this->assertCount( 1, $statement['rows'] );
		$this->assertEquals( 7.00, $statement['closing'] );
	}

	/**
	 * An empty period still reports a reconciling opening and closing balance
	 * rather than rendering nothing.
	 */
	public function test_empty_period_reports_carried_balance() {
		$this->record( 'credit', 90.00, '2026-05-01 09:00:00' );

		$statement = $this->statement( '2026-06-01', '2026-06-30' );

		$this->assertSame( array(), $statement['rows'] );
		$this->assertEquals( 90.00, $statement['opening'] );
		$this->assertEquals( 90.00, $statement['closing'] );
		$this->assertEquals( 0.00, $statement['totals']['net'] );
	}

	/**
	 * `date` is only second-resolution, so rows recorded in the same second
	 * must fall back to transaction_id order or the running balance column
	 * would disagree with itself between renders.
	 */
	public function test_same_second_rows_order_by_transaction_id() {
		$first  = $this->record( 'credit', 10.00, '2026-02-02 09:00:00' );
		$second = $this->record( 'credit', 20.00, '2026-02-02 09:00:00' );
		$third  = $this->record( 'debit', 5.00, '2026-02-02 09:00:00' );

		$statement = $this->statement( '2026-02-01', '2026-02-28' );
		$ids       = wp_list_pluck( $statement['rows'], 'transaction_id' );

		$this->assertSame( array( (string) $first, (string) $second, (string) $third ), array_map( 'strval', $ids ) );
		$this->assertEquals( 25.00, $statement['closing'] );
	}

	/**
	 * An inverted range is swapped rather than returning an empty statement.
	 */
	public function test_inverted_range_is_swapped() {
		$range = WooWallet_Statement_Service::resolve_range( '2026-06-30', '2026-06-01' );

		$this->assertSame( '2026-06-01', $range['from'] );
		$this->assertSame( '2026-06-30', $range['to'] );
	}

	/**
	 * Unparseable dates fall back to the current calendar month.
	 */
	public function test_invalid_dates_fall_back_to_current_month() {
		$range = WooWallet_Statement_Service::resolve_range( 'not-a-date', '2026-02-30' );

		$this->assertSame( current_time( 'Y-m-01' ), $range['from'] );
		$this->assertSame( current_time( 'Y-m-d' ), $range['to'] );
	}

	/**
	 * An end date in the future clamps to today.
	 */
	public function test_future_end_date_clamps_to_today() {
		$future = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +30 days' ) );
		$range  = WooWallet_Statement_Service::resolve_range( current_time( 'Y-m-01' ), $future );

		$this->assertSame( current_time( 'Y-m-d' ), $range['to'] );
	}

	/**
	 * A range that lies entirely in the future collapses to today rather than
	 * inverting. Clamping only the end date used to leave `from` after `to`,
	 * which reported an empty period for a perfectly ordinary mis-click.
	 */
	public function test_entirely_future_range_collapses_to_today() {
		$start = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +10 days' ) );
		$end   = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' +40 days' ) );

		$range = WooWallet_Statement_Service::resolve_range( $start, $end );

		$this->assertSame( current_time( 'Y-m-d' ), $range['from'] );
		$this->assertSame( current_time( 'Y-m-d' ), $range['to'] );
		$this->assertLessThanOrEqual( $range['end'], $range['start'] );
	}

	/**
	 * An over-wide range is trimmed back from the end date, not rejected.
	 */
	public function test_over_wide_range_is_trimmed() {
		$range = WooWallet_Statement_Service::resolve_range( '2000-01-01', '2026-06-30' );
		$days  = ( strtotime( $range['to'] ) - strtotime( $range['from'] ) ) / DAY_IN_SECONDS + 1;

		$this->assertSame( '2026-06-30', $range['to'] );
		$this->assertEquals( WooWallet_Statement_Service::max_days(), $days );
	}

	/**
	 * Build one page of a statement.
	 *
	 * @param string $from     Start date.
	 * @param string $to       End date.
	 * @param int    $page     1-based page.
	 * @param int    $per_page Rows per page.
	 * @return array
	 */
	private function page( $from, $to, $page, $per_page ) {
		return WooWallet_Statement_Service::get_statement( $this->user_id, $from, $to, '', $page, $per_page );
	}

	/**
	 * Seed a run of dated credits, one per day.
	 *
	 * @param int    $count  How many.
	 * @param string $start  First date, `Y-m-d`.
	 * @param float  $amount Amount per row.
	 */
	private function seed_daily_credits( $count, $start, $amount = 10.00 ) {
		for ( $i = 0; $i < $count; $i++ ) {
			$this->record( 'credit', $amount, gmdate( 'Y-m-d', strtotime( $start . " +{$i} days" ) ) . ' 09:00:00' );
		}
	}

	/**
	 * The running balance must not restart at the period opening on page 2.
	 * This is the whole reason `balance_before_offset()` exists.
	 */
	public function test_running_balance_continues_across_a_page_break() {
		$this->record( 'credit', 100.00, '2025-02-01 09:00:00' );
		$this->seed_daily_credits( 5, '2025-03-01' );

		$page_one = $this->page( '2025-03-01', '2025-03-31', 1, 2 );
		$page_two = $this->page( '2025-03-01', '2025-03-31', 2, 2 );

		$last_of_one  = end( $page_one['rows'] )->balance;
		$first_of_two = $page_two['rows'][0];

		$this->assertEquals( 120.00, $last_of_one );
		$this->assertEquals( $last_of_one, $page_two['brought'] );
		$this->assertEquals( $last_of_one + 10.00, $first_of_two->balance );
	}

	/**
	 * The last row of the last page lands exactly on the closing balance, the
	 * same invariant the single-page statement has.
	 */
	public function test_last_page_lands_on_closing_balance() {
		$this->seed_daily_credits( 5, '2025-04-01' );

		$last = $this->page( '2025-04-01', '2025-04-30', 3, 2 );

		$this->assertSame( 3, $last['total_pages'] );
		$this->assertCount( 1, $last['rows'] );
		$this->assertEquals( 50.00, $last['closing'] );
		$this->assertEquals( $last['closing'], end( $last['rows'] )->balance );
	}

	/**
	 * Opening, closing and the period totals are aggregates over the whole
	 * period, so they must read the same on every page.
	 */
	public function test_aggregates_are_identical_on_every_page() {
		$this->record( 'credit', 40.00, '2025-04-15 09:00:00' );
		$this->seed_daily_credits( 5, '2025-05-01' );
		$this->record( 'debit', 7.00, '2025-05-20 09:00:00' );

		$first = $this->page( '2025-05-01', '2025-05-31', 1, 2 );
		$third = $this->page( '2025-05-01', '2025-05-31', 3, 2 );

		$this->assertEquals( $first['opening'], $third['opening'] );
		$this->assertEquals( $first['closing'], $third['closing'] );
		$this->assertEquals( $first['totals'], $third['totals'] );
		$this->assertSame( $first['count'], $third['count'] );
		$this->assertEquals( 40.00, $first['opening'] );
		$this->assertEquals( 83.00, $first['closing'] );
	}

	/**
	 * Page numbers are clamped, not trusted: a page past the end returns the
	 * last page rather than an empty list, and page 0 or a negative page
	 * returns the first.
	 */
	public function test_page_number_is_clamped_to_the_available_pages() {
		$this->seed_daily_credits( 3, '2025-06-01' );

		$too_high = $this->page( '2025-06-01', '2025-06-30', 99, 2 );
		$too_low  = $this->page( '2025-06-01', '2025-06-30', 0, 2 );

		$this->assertSame( 2, $too_high['page'] );
		$this->assertCount( 1, $too_high['rows'] );
		$this->assertSame( 1, $too_low['page'] );
		$this->assertCount( 2, $too_low['rows'] );
	}

	/**
	 * An empty period is still one page, not zero.
	 */
	public function test_empty_period_reports_a_single_page() {
		$statement = $this->page( '2025-07-01', '2025-07-31', 1, 10 );

		$this->assertSame( 1, $statement['total_pages'] );
		$this->assertSame( 1, $statement['page'] );
		$this->assertSame( 0, $statement['count'] );
	}

	/**
	 * `per_page` may not exceed the row ceiling, whatever a caller asks for.
	 */
	public function test_per_page_is_clamped_to_max_rows() {
		add_filter( 'woo_wallet_statement_max_rows', array( $this, 'cap_rows_at_two' ) );

		$this->seed_daily_credits( 3, '2025-08-01' );
		$statement = $this->page( '2025-08-01', '2025-08-31', 1, 500 );

		remove_filter( 'woo_wallet_statement_max_rows', array( $this, 'cap_rows_at_two' ) );

		$this->assertSame( 2, $statement['per_page'] );
		$this->assertSame( 2, $statement['total_pages'] );
		$this->assertCount( 2, $statement['rows'] );
		$this->assertSame( 3, $statement['count'] );
	}

	/**
	 * Rows sharing a second must split across a page boundary in the same
	 * order the single-page statement puts them in, or the carried balance
	 * would be computed over a different set of rows than the page shows.
	 */
	public function test_same_second_rows_split_consistently_across_pages() {
		$this->record( 'credit', 10.00, '2025-09-02 09:00:00' );
		$this->record( 'credit', 20.00, '2025-09-02 09:00:00' );
		$this->record( 'credit', 30.00, '2025-09-02 09:00:00' );
		$this->record( 'credit', 40.00, '2025-09-02 09:00:00' );

		$whole = $this->page( '2025-09-01', '2025-09-30', 1, 10 );
		$one   = $this->page( '2025-09-01', '2025-09-30', 1, 2 );
		$two   = $this->page( '2025-09-01', '2025-09-30', 2, 2 );

		$paged = array_merge( $one['rows'], $two['rows'] );

		$this->assertSame(
			array_map( 'strval', wp_list_pluck( $whole['rows'], 'transaction_id' ) ),
			array_map( 'strval', wp_list_pluck( $paged, 'transaction_id' ) )
		);
		$this->assertEquals( 30.00, $two['brought'] );
		$this->assertEquals( 100.00, end( $two['rows'] )->balance );
	}

	/**
	 * An add-on may ask the row query for a column the statement itself does
	 * not read, and the core columns survive the filter.
	 */
	public function test_row_columns_filter_adds_a_column() {
		add_filter( 'woo_wallet_statement_row_columns', array( $this, 'add_original_currency_column' ) );

		$this->record( 'credit', 10.00, '2025-10-01 09:00:00' );
		$statement = $this->page( '2025-10-01', '2025-10-31', 1, 10 );

		remove_filter( 'woo_wallet_statement_row_columns', array( $this, 'add_original_currency_column' ) );

		$row = $statement['rows'][0];
		$this->assertTrue( property_exists( $row, 'original_currency' ) );
		$this->assertTrue( property_exists( $row, 'amount' ) );
		$this->assertTrue( property_exists( $row, 'transaction_id' ) );
	}

	/**
	 * A filter asking for a column that is not on the table costs that add-on
	 * its column and nothing else. The statement still renders.
	 */
	public function test_row_columns_filter_ignores_an_unknown_column() {
		add_filter( 'woo_wallet_statement_row_columns', array( $this, 'add_unknown_column' ) );

		$this->record( 'credit', 10.00, '2025-10-01 09:00:00' );
		$statement = $this->page( '2025-10-01', '2025-10-31', 1, 10 );

		remove_filter( 'woo_wallet_statement_row_columns', array( $this, 'add_unknown_column' ) );

		$this->assertCount( 1, $statement['rows'] );
		$this->assertEquals( 10.00, $statement['rows'][0]->balance );
		$this->assertFalse( property_exists( $statement['rows'][0], 'no_such_column' ) );
	}

	/**
	 * Filter callback: request a real extra column.
	 *
	 * @param array $columns Column names.
	 * @return array
	 */
	public function add_original_currency_column( $columns ) {
		$columns[] = 'original_currency';
		return $columns;
	}

	/**
	 * Filter callback: request a column that does not exist.
	 *
	 * @param array $columns Column names.
	 * @return array
	 */
	public function add_unknown_column( $columns ) {
		$columns[] = 'no_such_column';
		return $columns;
	}

	/**
	 * Filter callback: cap rows per page at two.
	 *
	 * @return int
	 */
	public function cap_rows_at_two() {
		return 2;
	}

	/**
	 * In per-currency mode every page -- rows, carried balance and aggregates
	 * alike -- must stay inside the requested currency. A carried balance that
	 * forgot the currency predicate would silently mix the two ledgers.
	 */
	public function test_pagination_stays_inside_one_currency() {
		add_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		add_filter( 'woo_wallet_get_option__wallet_settings_general_wallet_currency_mode', array( $this, 'force_per_currency_mode' ) );

		$this->record_in( 'credit', 100.00, '2025-11-01 09:00:00', 'USD' );
		$this->record_in( 'credit', 10.00, '2025-12-01 09:00:00', 'USD' );
		$this->record_in( 'credit', 20.00, '2025-12-02 09:00:00', 'USD' );
		$this->record_in( 'credit', 30.00, '2025-12-03 09:00:00', 'USD' );
		// Noise in another currency, interleaved by date on purpose.
		$this->record_in( 'credit', 5000.00, '2025-11-15 09:00:00', 'EUR' );
		$this->record_in( 'credit', 7000.00, '2025-12-02 12:00:00', 'EUR' );

		$one = WooWallet_Statement_Service::get_statement( $this->user_id, '2025-12-01', '2025-12-31', 'USD', 1, 2 );
		$two = WooWallet_Statement_Service::get_statement( $this->user_id, '2025-12-01', '2025-12-31', 'USD', 2, 2 );

		remove_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		remove_filter( 'woo_wallet_get_option__wallet_settings_general_wallet_currency_mode', array( $this, 'force_per_currency_mode' ) );

		$this->assertSame( 3, $one['count'] );
		$this->assertEquals( 100.00, $one['opening'] );
		$this->assertEquals( 160.00, $one['closing'] );

		// 100 opening + 10 + 20, with the 7000 EUR row dated between them excluded.
		$this->assertEquals( 130.00, $two['brought'] );
		$this->assertEquals( 160.00, end( $two['rows'] )->balance );
		$this->assertSame( array( 'USD' ), array_unique( wp_list_pluck( array_merge( $one['rows'], $two['rows'] ), 'currency' ) ) );
	}

	/**
	 * Record a transaction in an explicit currency.
	 *
	 * @param string $type     'credit' or 'debit'.
	 * @param float  $amount   Amount.
	 * @param string $date     Site-local `Y-m-d H:i:s`.
	 * @param string $currency ISO 4217 code.
	 * @return int Transaction id.
	 */
	private function record_in( $type, $amount, $date, $currency ) {
		return woo_wallet()->wallet->{$type}(
			$this->user_id,
			$amount,
			'Test',
			array(
				'date'     => $date,
				'currency' => $currency,
			)
		);
	}

	/**
	 * Filter callback: report per-currency ledger mode.
	 *
	 * @return string
	 */
	public function force_per_currency_mode() {
		return 'per_currency';
	}

	/**
	 * The CSV export walks get_rows() in fixed chunks until a short page ends
	 * the loop. On a row count that is an exact multiple of the chunk size the
	 * loop takes one extra turn, and that turn must come back empty -- if it
	 * repeated the last chunk instead, the export would double-count real money.
	 */
	public function test_chunked_paging_covers_every_row_exactly_once() {
		$this->seed_daily_credits( 6, '2025-01-01', 5.00 );

		$range = WooWallet_Statement_Service::resolve_range( '2025-01-01', '2025-01-31' );
		$scope = array(
			'scoped'   => false,
			'currency' => get_woocommerce_currency(),
		);

		$chunk   = 3;
		$offset  = 0;
		$seen    = array();
		$balance = 0.0;

		do {
			$rows = WooWallet_Statement_Service::get_rows( $this->user_id, $range, $scope, $chunk, $offset );
			foreach ( $rows as $row ) {
				$seen[]   = (int) $row->transaction_id;
				$balance += 'credit' === $row->type ? (float) $row->amount : -(float) $row->amount;
			}
			$offset += $chunk;
		} while ( count( $rows ) === $chunk );

		$statement = $this->page( '2025-01-01', '2025-01-31', 1, 10 );

		$this->assertCount( 6, $seen );
		$this->assertSame( $seen, array_unique( $seen ) );
		$this->assertEquals( $statement['closing'], $balance );
	}

	/**
	 * Outside per-currency mode the ledger holds base-currency amounts while
	 * the storefront may be showing any active currency. Every displayed
	 * figure must go through `woo_wallet_amount`, the same filter the
	 * dashboard and the balance use -- otherwise a multi-currency switcher
	 * moves the symbol and leaves the number, which reads as a wrong balance.
	 */
	public function test_display_currency_converts_every_figure() {
		$this->record( 'credit', 100.00, '2025-02-01 09:00:00' );
		$this->record( 'credit', 60.00, '2025-03-02 09:00:00' );
		$this->record( 'debit', 20.00, '2025-03-03 09:00:00' );

		add_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );
		$display = WooWallet_Statement_Service::to_display_currency( $this->page( '2025-03-01', '2025-03-31', 1, 10 ) );
		remove_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );

		$this->assertEquals( 50.00, $display['opening'] );
		$this->assertEquals( 30.00, $display['totals']['credited'] );
		$this->assertEquals( 10.00, $display['totals']['debited'] );
		$this->assertEquals( 70.00, $display['closing'] );
		$this->assertEquals( 30.00, $display['rows'][0]->amount );
		$this->assertEquals( 80.00, $display['rows'][0]->balance );
		$this->assertEquals( 70.00, end( $display['rows'] )->balance );

		// The page exists to show this sum. It has to survive conversion.
		$this->assertEquals(
			$display['opening'] + $display['totals']['credited'] - $display['totals']['debited'],
			$display['closing']
		);
	}

	/**
	 * The carried balance on a later page is converted too, or page 2 would
	 * open on a base-currency figure under the active currency's symbol.
	 */
	public function test_display_currency_converts_the_carried_balance() {
		$this->seed_daily_credits( 4, '2025-04-01', 10.00 );

		add_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );
		$display = WooWallet_Statement_Service::to_display_currency( $this->page( '2025-04-01', '2025-04-30', 2, 2 ) );
		remove_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );

		$this->assertEquals( 10.00, $display['brought'] );
		$this->assertEquals( 15.00, $display['rows'][0]->balance );
		$this->assertEquals( 20.00, $display['closing'] );
	}

	/**
	 * In per-currency mode the rows already are the currency they claim to be,
	 * so conversion must not touch them -- doing so would move money that is
	 * already denominated correctly.
	 */
	public function test_display_currency_leaves_a_per_currency_statement_alone() {
		add_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		add_filter( 'woo_wallet_get_option__wallet_settings_general_wallet_currency_mode', array( $this, 'force_per_currency_mode' ) );

		$this->record_in( 'credit', 80.00, '2025-06-02 09:00:00', 'USD' );

		add_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );
		$raw     = WooWallet_Statement_Service::get_statement( $this->user_id, '2025-06-01', '2025-06-30', 'USD', 1, 10 );
		$display = WooWallet_Statement_Service::to_display_currency( $raw );
		remove_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );

		remove_filter( 'woo_wallet_enable_per_currency_mode', '__return_true' );
		remove_filter( 'woo_wallet_get_option__wallet_settings_general_wallet_currency_mode', array( $this, 'force_per_currency_mode' ) );

		$this->assertEquals( 80.00, $display['closing'] );
		$this->assertEquals( 80.00, $display['rows'][0]->amount );
	}

	/**
	 * Filter callback: stand in for a multi-currency provider at rate 0.5.
	 *
	 * @param float $amount Amount in the stored currency.
	 * @return float
	 */
	public function halve_amount( $amount ) {
		return (float) $amount / 2;
	}

	/**
	 * An add-on that moves the balance without writing a transaction -- credit
	 * expiry is the case this exists for -- puts a dated line on the statement
	 * that walks the running balance and counts toward the period totals, so
	 * the closing figure matches the balance shown everywhere else.
	 */
	public function test_adjustment_becomes_a_dated_line_and_moves_the_closing_balance() {
		$this->record( 'credit', 100.00, '2026-08-04 09:00:00' );
		$this->record( 'credit', 10.00, '2026-08-15 10:00:00' );
		$this->record( 'debit', 5.00, '2026-08-20 09:00:00' );

		add_filter( 'woo_wallet_statement_adjustments', array( $this, 'expire_eight_on_the_sixteenth' ) );
		$statement = $this->page( '2026-08-01', '2026-08-31', 1, 50 );
		remove_filter( 'woo_wallet_statement_adjustments', array( $this, 'expire_eight_on_the_sixteenth' ) );

		$this->assertCount( 4, $statement['rows'] );
		$this->assertSame( 4, $statement['count'] );

		// Sorted into place by its own date, between the 15th and the 20th.
		$this->assertEquals( 'credit_expired', $statement['rows'][2]->category );
		$this->assertEquals( 8.00, $statement['rows'][2]->amount );
		$this->assertEquals( 102.00, $statement['rows'][2]->balance );

		$this->assertEquals( 110.00, $statement['totals']['credited'] );
		$this->assertEquals( 13.00, $statement['totals']['debited'] );
		$this->assertEquals( 97.00, $statement['closing'] );
		$this->assertEquals( $statement['closing'], end( $statement['rows'] )->balance );
	}

	/**
	 * An adjustment must be carried across a page break like any other row --
	 * counted in the page maths and included in the balance brought forward.
	 */
	public function test_adjustment_is_carried_across_a_page_break() {
		$this->record( 'credit', 100.00, '2026-08-04 09:00:00' );
		$this->record( 'credit', 10.00, '2026-08-15 10:00:00' );
		$this->record( 'debit', 5.00, '2026-08-20 09:00:00' );

		add_filter( 'woo_wallet_statement_adjustments', array( $this, 'expire_eight_on_the_sixteenth' ) );
		$page_two = $this->page( '2026-08-01', '2026-08-31', 2, 2 );
		remove_filter( 'woo_wallet_statement_adjustments', array( $this, 'expire_eight_on_the_sixteenth' ) );

		$this->assertSame( 2, $page_two['total_pages'] );
		$this->assertEquals( 110.00, $page_two['brought'] );
		$this->assertEquals( 8.00, $page_two['rows'][0]->amount );
		$this->assertEquals( 102.00, $page_two['rows'][0]->balance );
		$this->assertEquals( 97.00, end( $page_two['rows'] )->balance );
	}

	/**
	 * Whatever an add-on took off the balance before the period has to be
	 * carried in, or every later statement opens too high.
	 */
	public function test_opening_adjustment_is_carried_in() {
		$this->record( 'credit', 100.00, '2026-07-10 09:00:00' );
		$this->record( 'credit', 20.00, '2026-08-05 09:00:00' );

		add_filter( 'woo_wallet_statement_opening_adjustment', array( $this, 'carry_in_minus_eight' ) );
		$statement = $this->page( '2026-08-01', '2026-08-31', 1, 50 );
		remove_filter( 'woo_wallet_statement_opening_adjustment', array( $this, 'carry_in_minus_eight' ) );

		$this->assertEquals( 92.00, $statement['opening'] );
		$this->assertEquals( 112.00, $statement['closing'] );
		$this->assertEquals( $statement['closing'], end( $statement['rows'] )->balance );
	}

	/**
	 * Adjustments outside the period, of zero value, or in another currency
	 * while the statement is scoped to one, are all dropped rather than
	 * silently distorting the period.
	 */
	public function test_invalid_adjustments_are_dropped() {
		$this->record( 'credit', 50.00, '2026-08-05 09:00:00' );

		add_filter( 'woo_wallet_statement_adjustments', array( $this, 'return_invalid_adjustments' ) );
		$statement = $this->page( '2026-08-01', '2026-08-31', 1, 50 );
		remove_filter( 'woo_wallet_statement_adjustments', array( $this, 'return_invalid_adjustments' ) );

		$this->assertCount( 1, $statement['rows'] );
		$this->assertEquals( 50.00, $statement['closing'] );
	}

	/**
	 * An adjustment is displayed in the browsing currency like every other
	 * figure, so a multi-currency store does not show one unconverted line.
	 */
	public function test_adjustment_is_converted_for_display() {
		$this->record( 'credit', 100.00, '2026-08-04 09:00:00' );
		$this->record( 'credit', 10.00, '2026-08-15 10:00:00' );

		add_filter( 'woo_wallet_statement_adjustments', array( $this, 'expire_eight_on_the_sixteenth' ) );
		add_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );
		$display = WooWallet_Statement_Service::to_display_currency( $this->page( '2026-08-01', '2026-08-31', 1, 50 ) );
		remove_filter( 'woo_wallet_amount', array( $this, 'halve_amount' ) );
		remove_filter( 'woo_wallet_statement_adjustments', array( $this, 'expire_eight_on_the_sixteenth' ) );

		$this->assertEquals( 4.00, $display['rows'][2]->amount );
		$this->assertEquals( 51.00, $display['closing'] );
	}

	/**
	 * Filter callback: an 8.00 remainder lapsing on 16 August.
	 *
	 * @param array $adjustments Existing adjustments.
	 * @return array
	 */
	public function expire_eight_on_the_sixteenth( $adjustments ) {
		$adjustments[] = array(
			'date'     => '2026-08-16 23:59:59',
			'type'     => 'debit',
			'amount'   => 8.00,
			'category' => 'credit_expired',
			'details'  => 'Unused balance from the 15 Aug credit',
		);
		return $adjustments;
	}

	/**
	 * Filter callback: carry -8.00 into the period.
	 *
	 * @return float
	 */
	public function carry_in_minus_eight() {
		return -8.00;
	}

	/**
	 * Filter callback: adjustments that must all be rejected.
	 *
	 * @param array $adjustments Existing adjustments.
	 * @return array
	 */
	public function return_invalid_adjustments( $adjustments ) {
		$adjustments[] = array(
			'date'   => '2026-07-15 09:00:00',
			'type'   => 'debit',
			'amount' => 5.00,
		);
		$adjustments[] = array(
			'date'   => '2026-09-15 09:00:00',
			'type'   => 'debit',
			'amount' => 5.00,
		);
		$adjustments[] = array(
			'date'   => '2026-08-15 09:00:00',
			'type'   => 'debit',
			'amount' => 0,
		);
		return $adjustments;
	}

	/**
	 * Paging through get_rows() returns the period in stable order, which is
	 * what the chunked CSV export relies on.
	 */
	public function test_get_rows_pages_in_stable_order() {
		$this->record( 'credit', 1.00, '2025-01-01 09:00:00' );
		$this->record( 'credit', 2.00, '2025-01-02 09:00:00' );
		$this->record( 'credit', 3.00, '2025-01-03 09:00:00' );

		$range = WooWallet_Statement_Service::resolve_range( '2025-01-01', '2025-01-31' );
		$scope = array(
			'scoped'   => false,
			'currency' => get_woocommerce_currency(),
		);

		$page_one = WooWallet_Statement_Service::get_rows( $this->user_id, $range, $scope, 2, 0 );
		$page_two = WooWallet_Statement_Service::get_rows( $this->user_id, $range, $scope, 2, 2 );

		$this->assertCount( 2, $page_one );
		$this->assertCount( 1, $page_two );
		$this->assertEquals( 1.00, $page_one[0]->amount );
		$this->assertEquals( 3.00, $page_two[0]->amount );
	}
}
