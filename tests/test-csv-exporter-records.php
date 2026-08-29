<?php
/**
 * CSV exporter record-selection tests.
 *
 * Covers two export defects that made a wallet un-migratable:
 *
 *  - rows were emitted newest-first, so an importer replaying the file
 *    through credit()/debit() saw every debit before the credits it
 *    consumed
 *  - soft-deleted rows (`deleted = 1`) were exported, resurrecting as
 *    live transactions on the destination ledger
 *
 * @package WooWallet\Tests
 */

class Test_CSV_Exporter_Records extends WP_UnitTestCase {

	private $user_id;

	public function set_up() {
		parent::set_up();
		include_once WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php';
		$this->user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
	}

	private function exporter() {
		$exporter = new TeraWallet_CSV_Exporter();
		$exporter->set_export_type( 'transactions' );
		$exporter->set_users_to_export( array( $this->user_id ) );
		return $exporter;
	}

	/**
	 * Credit, debit, credit — the order they must come back in.
	 *
	 * @return array transaction ids in creation order.
	 */
	private function seed_ledger() {
		$ids   = array();
		$ids[] = woo_wallet()->wallet->credit( $this->user_id, 100, 'first credit' );
		$ids[] = woo_wallet()->wallet->debit( $this->user_id, 40, 'a debit' );
		$ids[] = woo_wallet()->wallet->credit( $this->user_id, 25, 'second credit' );
		return $ids;
	}

	private function soft_delete( $transaction_id ) {
		global $wpdb;
		$wpdb->update(
			"{$wpdb->base_prefix}woo_wallet_transactions",
			array( 'deleted' => 1 ),
			array( 'transaction_id' => $transaction_id ),
			array( '%d' ),
			array( '%d' )
		);
	}

	/**
	 * Records come back oldest-first so a replaying importer sees credits
	 * before the debits that consumed them.
	 */
	public function test_records_are_ascending_by_transaction_id() {
		$ids = $this->seed_ledger();

		$got = wp_list_pluck( $this->exporter()->get_records(), 'transaction_id' );
		$got = array_map( 'absint', $got );

		$this->assertSame( $ids, $got, 'Export must be ascending by transaction_id.' );
	}

	/**
	 * A soft-deleted row is in neither the record set nor the count that
	 * drives pagination.
	 */
	public function test_soft_deleted_rows_are_excluded_from_records_and_count() {
		$ids = $this->seed_ledger();
		$this->soft_delete( $ids[1] );

		$exporter = $this->exporter();

		$got = array_map( 'absint', wp_list_pluck( $exporter->get_records(), 'transaction_id' ) );
		$this->assertSame( array( $ids[0], $ids[2] ), $got, 'Soft-deleted row must not be exported.' );

		$this->assertSame( 2, (int) $exporter->get_tota_record_count(), 'Count must match the exported rows.' );
	}

	/**
	 * A multi-step export writes each row exactly once, in order, under one
	 * header — the file is appended to, not rebuilt per row.
	 */
	public function test_multi_step_export_appends_each_row_once() {
		$ids = $this->seed_ledger();

		// per_page 2 over 3 records: two steps, so the second step must append
		// to what the first wrote rather than replace or duplicate it.
		$exporter = new class() extends TeraWallet_CSV_Exporter {
			protected $per_page = 2;

			public function file_path() {
				return $this->get_file_path();
			}
		};
		$exporter->set_export_type( 'transactions' );
		$exporter->set_users_to_export( array( $this->user_id ) );
		$exporter->set_filename( 'phpunit-export-' . $this->user_id . '.csv' );
		$path = $exporter->file_path();

		$exporter->set_step( 1 );
		$exporter->write_to_csv();
		$exporter->write_to_csv();

		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local test fixture, not a remote request.
		$lines    = array_values( array_filter( explode( "\n", $contents ), 'strlen' ) );
		@unlink( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink, Generic.PHP.NoSilencedErrors.Discouraged -- Test cleanup of a temp file.

		$this->assertCount( 4, $lines, 'One header plus three rows, each written once.' );
		$this->assertStringStartsWith( 'id,', $lines[0], 'Header written once, at the top.' );

		$exported_ids = array();
		foreach ( array_slice( $lines, 1 ) as $line ) {
			$cells          = str_getcsv( $line );
			$exported_ids[] = (int) $cells[0];
		}
		$this->assertSame( $ids, $exported_ids, 'Rows appear once each, in ledger order.' );
	}
}
