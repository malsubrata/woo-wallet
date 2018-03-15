<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Woo_Wallet_Transaction_Details extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'transaction',
            'plural' => 'transactions',
            'ajax' => false,
            'screen' => 'wc-wallet-transactions',
        ));
    }

    public function get_columns() {
        return array(
            'transaction_id' => __('ID', 'woo-wallet'),
            'name' => __('Name', 'woo-wallet'),
            'type' => __('Type', 'woo-wallet'),
            'amount' => __('Amount', 'woo-wallet'),
            'details' => __('Details', 'woo-wallet'),
            'date' => __('Date', 'woo-wallet')
        );
    }

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items() {
        $columns = $this->get_columns();
        $hidden = $this->get_hidden_columns();
        $sortable = $this->get_sortable_columns();
        $data = $this->table_data();
        $perPage = 10;
        $currentPage = $this->get_pagenum();
        $totalItems = count($data);
        $this->set_pagination_args(array(
            'total_items' => $totalItems,
            'per_page' => $perPage
        ));
        $data = array_slice($data, (($currentPage - 1) * $perPage), $perPage);
        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array('transaction_id');
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        return array();
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data() {
        $data = array();
        $user_id = filter_input(INPUT_GET, 'user_id');
        if ($user_id == NULL) {
            return $data;
        }
        $transactions = get_wallet_transactions();
        if (!empty($transactions) && is_array($transactions)) {
            foreach ($transactions as $key => $transaction) {
                $data[] = array(
                    'transaction_id' => $transaction->transaction_id,
                    'name' => get_user_by('ID', $transaction->user_id)->display_name,
                    'type' => ('credit'===$transaction->type) ? __('Credit', 'woo-wallet') : __('Debit', 'woo-wallet'),
                    'amount' => wc_price(apply_filters('woo_wallet_amount', $transaction->amount, $transaction->currency)),
                    'details' => $transaction->details,
                    'date' => wc_string_to_datetime($transaction->date)->date(wc_date_format())
                );
            }
        }
        return $data;
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default($item, $column_name) {
        switch ($column_name) {
            case 'transaction_id':
            case 'name':
            case 'type':
            case 'amount':
            case 'details':
            case 'date':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

}
