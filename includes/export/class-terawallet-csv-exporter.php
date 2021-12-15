<?php

if (!defined('ABSPATH')) {
    exit;
}

class TeraWallet_CSV_Exporter {

    /**
     * The delimiter parameter sets the field delimiter (one character only).
     *
     * @var string
     */
    protected $delimiter = ',';
    public $export_type = 'transactions';
    protected $selected_columns = array();
    protected $selected_users = array();
    protected $start_date = '';
    protected $end_date = '';
    protected $filename = 'terawallet-export.csv';
    protected $step = 1;
    protected $per_page = 50;
    protected $columns = array();

    /**
     * Return the delimiter to use in CSV file
     *
     * @since 1.0.0
     * @return string
     */
    public function get_delimiter() {
        return apply_filters("terawallet_{$this->export_type}_export_delimiter", $this->delimiter);
    }

    public function get_default_column_names() {
        $default_column_names = array(
            'transaction_id' => __('ID', 'woo-wallet'),
            'user_id' => __('User ID', 'woo-wallet'),
            'email' => __('Email', 'woo-wallet'),
            'type' => __('Type', 'woo-wallet'),
            'currency' => __('Currency', 'woo-wallet'),
            'amount' => __('Amount', 'woo-wallet'),
            'details' => __('Details', 'woo-wallet'),
            'created_by' => __('Added by', 'woo-wallet'),
            'date' => __('Date', 'woo-wallet')
        );
        return apply_filters('terawallet_exporter_default_column_names', $default_column_names);
    }

    public function set_columns_to_export($columns) {
        $this->columns = $columns;
    }

    public function get_columns_to_export() {
        return $this->columns ? $this->columns : array_keys($this->get_default_column_names());
    }

    public function set_users_to_export($users) {
        $this->selected_users = $users;
    }

    public function set_start_date($start_date) {
        $this->start_date = $start_date;
    }

    public function set_end_date($end_date) {
        $this->end_date = $end_date;
    }

    public function set_filename($filename) {
        $this->filename = sanitize_file_name(str_replace('.csv', '', $filename) . '.csv');
    }

    public function get_filename() {
        return $this->filename;
    }

    public function set_step($step) {
        $this->step = $step;
    }

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
        return trailingslashit($upload_dir['basedir']) . $this->get_filename();
    }

    /**
     * Get the file contents.
     *
     * @since 3.1.0
     * @return string
     */
    public function get_file() {
        $file = '';
        if (@file_exists($this->get_file_path())) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
            $file = @file_get_contents($this->get_file_path()); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents, WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
        } else {
            @file_put_contents($this->get_file_path(), ''); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_file_put_contents, Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
            @chmod($this->get_file_path(), 0664); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.chmod_chmod, WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents, Generic.PHP.NoSilencedErrors.Discouraged
        }
        return $file;
    }

    public function write_to_csv() {
        if (1 === $this->get_step()) {
            $this->write_csv_header();
        }

        $this->export_rows();
    }

    public function get_percent_complete() {
        return $this->get_tota_record_count() ? floor(( $this->get_total_exported() / $this->get_tota_record_count() ) * 100) : 100;
    }

    public function get_total_exported() {
        return ($this->get_step() - 1 ) * $this->per_page;
    }

    public function write_csv_header() {
        $file = $this->get_file();
        $file .= $this->export_column_headers();
        @file_put_contents($this->get_file_path(), $file);
    }

    public function get_tota_record_count() {
        global $wpdb;
        $where = "1 = 1";
        if (!empty($this->selected_users)) {
            $user_ids = implode(', ', $this->selected_users);
            $where .= " AND transactions.user_id IN ({$user_ids})";
        }
        if (!empty($this->start_date) || !empty($this->end_date)) {
            $after = empty($this->start_date) ? '0000-00-00' : $this->start_date;
            $before = empty($this->end_date) ? current_time('mysql', 1) : $this->end_date;
            $where .= " AND ( transactions.date BETWEEN STR_TO_DATE( '" . $after . "', '%Y-%m-%d %H:%i:%s' ) AND STR_TO_DATE( '" . $before . "', '%Y-%m-%d %H:%i:%s' ))";
        }
        $sql = "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions WHERE {$where};";
        return $wpdb->get_var($sql);
    }

    public function get_records() {
        global $wpdb;
        $where = "1 = 1";
        if (!empty($this->selected_users)) {
            $user_ids = implode(', ', $this->selected_users);
            $where .= " AND transactions.user_id IN ({$user_ids})";
        }
        if (!empty($this->start_date) || !empty($this->end_date)) {
            $after = empty($this->start_date) ? '0000-00-00' : $this->start_date;
            $before = empty($this->end_date) ? current_time('mysql', 1) : $this->end_date;
            $where .= " AND ( transactions.date BETWEEN STR_TO_DATE( '" . $after . "', '%Y-%m-%d %H:%i:%s' ) AND STR_TO_DATE( '" . $before . "', '%Y-%m-%d %H:%i:%s' ))";
        }
        $offset = $this->per_page * ($this->get_step() - 1);
        $sql = "SELECT * FROM {$wpdb->base_prefix}woo_wallet_transactions AS transactions WHERE {$where} ORDER BY transactions.transaction_id DESC LIMIT {$offset}, {$this->per_page};";

        return $wpdb->get_results($sql, ARRAY_A);
    }

    protected function export_column_headers() {
        $columns = $this->get_columns_to_export();
        $export_row = array();
        $buffer = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
        ob_start();

        foreach ($columns as $column_id) {
            $export_row[] = str_replace(' ', '_', strtolower($this->format_data($this->get_default_column_names()[$column_id])));
        }

        fputcsv($buffer, $export_row);

        return ob_get_clean();
    }

    protected function export_rows() {
        $records = $this->get_records();
        foreach ($records as $record) {
            $file = $this->get_file();
            $record['email'] = get_userdata($record['user_id'])->user_email;
            $record = apply_filters('terawallet_transaction_export_row', $record);
            $file .= $this->export_row($record);
            @file_put_contents($this->get_file_path(), $file);
        }
        $this->set_step($this->get_step() + 1);
    }

    protected function export_row($record) {
        $columns = $this->get_columns_to_export();
        $export_row = array();
        $buffer = fopen('php://output', 'w'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
        ob_start();

        foreach ($columns as $column_id) {
            $export_row[] = $this->format_data($record[$column_id]);
        }
        fputcsv($buffer, $export_row);

        return apply_filters('terawallet_row_to_export', ob_get_clean(), $record);
    }

    /**
     * Format and escape data ready for the CSV file.
     *
     * @since 3.1.0
     * @param  string $data Data to format.
     * @return string
     */
    public function format_data($data) {
        if (!is_scalar($data)) {
            if (is_a($data, 'WC_Datetime')) {
                $data = $data->date('Y-m-d G:i:s');
            } else {
                $data = ''; // Not supported.
            }
        } elseif (is_bool($data)) {
            $data = $data ? 1 : 0;
        }

        $use_mb = function_exists('mb_convert_encoding');

        if ($use_mb) {
            $encoding = mb_detect_encoding($data, 'UTF-8, ISO-8859-1', true);
            $data = 'UTF-8' === $encoding ? $data : utf8_encode($data);
        }

        return $this->escape_data($data);
    }

    public function escape_data($data) {
        $active_content_triggers = array('=', '+', '-', '@');

        if (in_array(mb_substr($data, 0, 1), $active_content_triggers, true)) {
            $data = "'" . $data;
        }

        return $data;
    }

    public function export() {
        $this->send_headers();
        $this->send_content($this->get_file());
        @unlink($this->get_file_path()); // phpcs:ignore WordPress.VIP.FileSystemWritesDisallow.file_ops_unlink, Generic.PHP.NoSilencedErrors.Discouraged
        die();
    }

    /**
     * Set the export headers.
     *
     * @since 3.1.0
     */
    public function send_headers() {
        if (function_exists('gc_enable')) {
            gc_enable(); // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.gc_enableFound
        }
        if (function_exists('apache_setenv')) {
            @apache_setenv('no-gzip', 1); // @codingStandardsIgnoreLine
        }
        @ini_set('zlib.output_compression', 'Off'); // @codingStandardsIgnoreLine
        @ini_set('output_buffering', 'Off'); // @codingStandardsIgnoreLine
        @ini_set('output_handler', ''); // @codingStandardsIgnoreLine
        ignore_user_abort(true);
        wc_set_time_limit(0);
        wc_nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $this->get_filename());
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    /**
     * Set the export content.
     *
     * @since 3.1.0
     * @param string $csv_data All CSV content.
     */
    public function send_content($csv_data) {
        echo $csv_data; // @codingStandardsIgnoreLine
    }

}
