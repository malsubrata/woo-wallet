<?php
/**
 * TeraWallet exporter class file.
 *
 * @package StandaloneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * TeraWallet Exporter class.
 */
class TeraWallet_CSV_Exporter {

	/**
	 * The delimiter parameter sets the field delimiter (one character only).
	 *
	 * @var string
	 */
	protected $delimiter = ',';
	/**
	 * Export type.
	 *
	 * @var string
	 */
	public $export_type = 'transactions';
	/**
	 * Column ro export.
	 *
	 * @var array
	 */
	protected $selected_columns = array();
	/**
	 * Users to export.
	 *
	 * @var array
	 */
	protected $selected_users = array();
	/**
	 * Start date.
	 *
	 * @var string
	 */
	protected $start_date = '';
	/**
	 * End date.
	 *
	 * @var string
	 */
	protected $end_date = '';
	/**
	 * File name.
	 *
	 * @var string
	 */
	protected $filename = 'terawallet-export.csv';
	/**
	 * Current step.
	 *
	 * @var integer
	 */
	protected $step = 1;
	/**
	 * Per page to export records.
	 *
	 * @var integer
	 */
	protected $per_page = 50;
	/**
	 * Column to export.
	 *
	 * @var array
	 */
	protected $columns = array();

	/**
	 * Return the delimiter to use in CSV file
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_delimiter() {
		return apply_filters( "terawallet_{$this->export_type}_export_delimiter", $this->delimiter );
	}
	/**
	 * Get default columns.
	 */
	public function get_default_column_names() {
		if ( 'transactions' === $this->export_type ) {
			$default_column_names = array(
				'transaction_id' => __( 'ID', 'woo-wallet' ),
				'user_id'        => __( 'User ID', 'woo-wallet' ),
				'email'          => __( 'Email', 'woo-wallet' ),
				'type'           => __( 'Type', 'woo-wallet' ),
				'currency'       => __( 'Currency', 'woo-wallet' ),
				'amount'         => __( 'Amount', 'woo-wallet' ),
				'details'        => __( 'Details', 'woo-wallet' ),
				'created_by'     => __( 'Added by', 'woo-wallet' ),
				'date'           => __( 'Date', 'woo-wallet' ),
			);
		} else {
			$default_column_names = array(
				'user_id' => __( 'User ID', 'woo-wallet' ),
				'email'   => __( 'Email', 'woo-wallet' ),
				'amount'  => __( 'Amount', 'woo-wallet' ),
			);
		}
		return apply_filters( "terawallet_{$this->export_type}_exporter_default_column_names", $default_column_names );
	}
	/**
	 * Set exporter type
	 *
	 * @param string $type type.
	 * @return void
	 */
	public function set_export_type( $type ) {
		$this->export_type = $type;
	}
	/**
	 * Set columns.
	 *
	 * @param array $columns columns.
	 */
	public function set_columns_to_export( $columns ) {
		$this->columns = $columns;
	}
	/**
	 * Get Which column to export.
	 */
	public function get_columns_to_export() {
		return $this->columns ? $this->columns : array_keys( $this->get_default_column_names() );
	}
	/**
	 * Set user ids to export.
	 *
	 * @param arry $users users.
	 * @return void
	 */
	public function set_users_to_export( $users ) {
		$this->selected_users = $users;
	}
	/**
	 * Set start date.
	 *
	 * @param string $start_date start_date.
	 * @return void
	 */
	public function set_start_date( $start_date ) {
		$this->start_date = $start_date;
	}
	/**
	 * Set end date.
	 *
	 * @param string $end_date end_date.
	 * @return void
	 */
	public function set_end_date( $end_date ) {
		$this->end_date = $end_date;
	}
	/**
	 * Set file name.
	 *
	 * @param string $filename filename.
	 * @return void
	 */
	public function set_filename( $filename ) {
		$this->filename = sanitize_file_name( str_replace( '.csv', '', $filename ) . '.csv' );
	}
	/**
	 * Get export type.
	 *
	 * @return string
	 */
	public function get_export_type() : string {
		return $this->export_type;
	}
	/**
	 * Get file name
	 *
	 * @return string
	 */
	public function get_filename() {
		return $this->filename;
	}
	/**
	 * Set current step
	 *
	 * @param integer $step step.
	 * @return void
	 */
	public function set_step( $step ) {
		$this->step = $step;
	}
	/**
	 * Get current step.
	 *
	 * @return integer
	 */
	public function get_step() {
		return $this->step;
	}

	/**
	 * Get file path to export to.
	 *
	 * @return string
	 */
	protected function get_file_path() {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . $this->get_filename();
	}

	/**
	 * Get the file contents.
	 *
	 * @since 3.1.0
	 * @return string
	 */
	public function get_file() {
		$file = '';
		if ( @file_exists( $this->get_file_path() ) ) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$file = @file_get_contents( $this->get_file_path() ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
		} else {
			@file_put_contents( $this->get_file_path(), '' ); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_file_put_contents, Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
			@chmod( $this->get_file_path(), 0664 ); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.chmod_chmod, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents, Generic.PHP.NoSilencedErrors.Discouraged
		}
		return $file;
	}
	/**
	 * Write data to CSV file.
	 */
	public function write_to_csv() {
		if ( 1 === $this->get_step() ) {
			$this->write_csv_header();
		}

		$this->export_rows();
	}
	/**
	 * Get to record count to export.
	 *
	 * @return integer
	 */
	public function get_percent_complete() {
		return $this->get_tota_record_count() ? floor( ( $this->get_total_exported() / $this->get_tota_record_count() ) * 100 ) : 100;
	}
	/**
	 * Get total exported records.
	 *
	 * @return integer
	 */
	public function get_total_exported() {
		return ( $this->get_step() - 1 ) * $this->per_page;
	}
	/**
	 * Write CSV file header.
	 *
	 * @return void
	 */
	public function write_csv_header() {
		$file  = $this->get_file();
		$file .= $this->export_column_headers();
		@file_put_contents( $this->get_file_path(), $file );
	}
	/**
	 * Get records count to export
	 *
	 * @return integer
	 */
	public function get_tota_record_count() {
		global $wpdb;
		if ( 'transactions' === $this->get_export_type() ) {
			$where = '1 = 1';
			if ( ! empty( $this->selected_users ) ) {
				$user_ids = implode( ', ', $this->selected_users );
				$where   .= " AND transactions.user_id IN ({$user_ids})";
			}
			if ( ! empty( $this->start_date ) || ! empty( $this->end_date ) ) {
				$after  = empty( $this->start_date ) ? '0000-00-00' : $this->start_date;
				$before = empty( $this->end_date ) ? current_time( 'mysql', 1 ) : $this->end_date;
				$where .= " AND ( transactions.date BETWEEN STR_TO_DATE( '" . $after . "', '%Y-%m-%d %H:%i:%s' ) AND STR_TO_DATE( '" . $before . "', '%Y-%m-%d %H:%i:%s' ))";
			}
			$sql = "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions WHERE {$where};";
			return $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		} else {
			if ( ! empty( $this->selected_users ) ) {
				return count( $this->selected_users );
			}
			$users_of_blog = count_users();
			return $users_of_blog['total_users'];
		}
	}
	/**
	 * Get records to export
	 *
	 * @return array
	 */
	public function get_records() {
		global $wpdb;
		if ( 'transactions' === $this->get_export_type() ) {
			$where = '1 = 1';
			if ( ! empty( $this->selected_users ) ) {
				$user_ids = implode( ', ', $this->selected_users );
				$where   .= " AND transactions.user_id IN ({$user_ids})";
			}
			if ( ! empty( $this->start_date ) || ! empty( $this->end_date ) ) {
				$after  = empty( $this->start_date ) ? '0000-00-00' : $this->start_date;
				$before = empty( $this->end_date ) ? current_time( 'mysql', 1 ) : $this->end_date;
				$where .= " AND ( transactions.date BETWEEN STR_TO_DATE( '" . $after . "', '%Y-%m-%d %H:%i:%s' ) AND STR_TO_DATE( '" . $before . "', '%Y-%m-%d %H:%i:%s' ))";
			}
			$offset = $this->per_page * ( $this->get_step() - 1 );
			$sql    = "SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions WHERE {$where} ORDER BY transactions.transaction_id DESC LIMIT {$offset}, {$this->per_page};";
			return $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		} else {
			$args = array(
				'blog_id' => get_current_blog_id(),
				'number'  => $this->per_page,
				'offset'  => $this->per_page * ( $this->get_step() - 1 ),
			);
			if ( ! empty( $this->selected_users ) ) {
				$args['include'] = $this->selected_users;
			}
			// Query the user IDs for this page.
			$wp_user_search = new WP_User_Query( $args );
			$data           = array();
			foreach ( $wp_user_search->get_results() as $user ) {
				$data[] = array(
					'user_id' => $user->ID,
					'email'   => $user->data->user_email,
					'amount'  => woo_wallet()->wallet->get_wallet_balance( $user->ID, 'edit' ),
				);
			}
			return $data;
		}
	}
	/**
	 * Export column header
	 *
	 * @return string
	 */
	protected function export_column_headers() {
		$columns    = $this->get_columns_to_export();
		$export_row = array();
		$buffer     = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		ob_start();

		foreach ( $columns as $column_id ) {
			$export_row[] = str_replace( ' ', '_', strtolower( $this->format_data( $this->get_default_column_names()[ $column_id ] ) ) );
		}

		fputcsv( $buffer, $export_row );

		return ob_get_clean();
	}
	/**
	 * Export record rows.
	 *
	 * @return void
	 */
	protected function export_rows() {
		$records = $this->get_records();
		foreach ( $records as $record ) {
			$file            = $this->get_file();
			$record['email'] = ! isset( $record['email'] ) ? get_userdata( $record['user_id'] )->user_email : $record['email'];
			$record          = apply_filters( 'terawallet_transaction_export_row', $record );
			$file           .= $this->export_row( $record );
			@file_put_contents( $this->get_file_path(), $file );
		}
		$this->set_step( $this->get_step() + 1 );
	}
	/**
	 * Export record row.
	 *
	 * @param array $record record.
	 * @return void
	 */
	protected function export_row( $record ) {
		$columns    = $this->get_columns_to_export();
		$export_row = array();
		$buffer     = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
		ob_start();

		foreach ( $columns as $column_id ) {
			$export_row[] = $this->format_data( $record[ $column_id ] );
		}
		fputcsv( $buffer, $export_row );

		return apply_filters( 'terawallet_row_to_export', ob_get_clean(), $record );
	}

	/**
	 * Format and escape data ready for the CSV file.
	 *
	 * @since 3.1.0
	 * @param  string $data Data to format.
	 * @return string
	 */
	public function format_data( $data ) {
		if ( ! is_scalar( $data ) ) {
			if ( is_a( $data, 'WC_Datetime' ) ) {
				$data = $data->date( 'Y-m-d G:i:s' );
			} else {
				$data = ''; // Not supported.
			}
		} elseif ( is_bool( $data ) ) {
			$data = $data ? 1 : 0;
		}

		$use_mb = function_exists( 'mb_convert_encoding' );

		if ( $use_mb ) {
			$encoding = mb_detect_encoding( $data, 'UTF-8, ISO-8859-1', true );
			$data     = 'UTF-8' === $encoding ? $data : utf8_encode( $data );
		}

		return $this->escape_data( $data );
	}
	/**
	 * Escape data.
	 *
	 * @param string $data data.
	 * @return string
	 */
	public function escape_data( $data ) {
		$active_content_triggers = array( '=', '+', '-', '@' );

		if ( in_array( mb_substr( $data, 0, 1 ), $active_content_triggers, true ) ) {
			$data = "'" . $data;
		}

		return $data;
	}
	/**
	 * Export the file to browser.
	 *
	 * @return void
	 */
	public function export() {
		$this->send_headers();
		$this->send_content( $this->get_file() );
		@unlink( $this->get_file_path() ); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_unlink, Generic.PHP.NoSilencedErrors.Discouraged
		die();
	}

	/**
	 * Set the export headers.
	 *
	 * @since 3.1.0
	 */
	public function send_headers() {
		if ( function_exists( 'gc_enable' ) ) {
			gc_enable(); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.gc_enableFound
		}
		if ( function_exists( 'apache_setenv' ) ) {
            @apache_setenv('no-gzip', 1); // @codingStandardsIgnoreLine
		}
        @ini_set('zlib.output_compression', 'Off'); // @codingStandardsIgnoreLine
        @ini_set('output_buffering', 'Off'); // @codingStandardsIgnoreLine
        @ini_set('output_handler', ''); // @codingStandardsIgnoreLine
		ignore_user_abort( true );
		wc_set_time_limit( 0 );
		wc_nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $this->get_filename() );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
	}

	/**
	 * Set the export content.
	 *
	 * @since 3.1.0
	 * @param string $csv_data All CSV content.
	 */
	public function send_content( $csv_data ) {
        echo $csv_data; // @codingStandardsIgnoreLine
	}

}
