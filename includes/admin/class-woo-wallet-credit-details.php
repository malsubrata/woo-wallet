<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Woo_Wallet_Credit_Details extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'credit limit',
            'plural' => 'credit limits',
            'ajax' => false,
            'screen' => 'wc-wallet-credit',
        ));
    }

    public function get_columns() {
        return array(
            'date' => __('Date', 'woo-wallet'),
            #'crlim_id' => __('ID', 'woo-wallet'),
	    #'user_id' => __('User', 'woo-wallet'),
	    #'name' => __('Name', 'woo-wallet'),
            'type' => __('Type', 'woo-wallet'),
            'amount' => __('Amount', 'woo-wallet'),
            'description' => __('Description', 'woo-wallet'),
            'approver' => __('Approver', 'woo-wallet')
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
        $credit_limits = woo_wallet()->wallet->get_wallet_credit_details($user_id, 'all');
        if (!empty($credit_limits) && is_array($credit_limits)) {
            foreach ($credit_limits as $key => $credit_limit) {
                $data[] = array(
                    'crlim_id' => $credit_limit->crlim_id,
		    'user_id' => $credit_limit->user_id,
                    'name' => get_user_by('ID', $credit_limit->user_id)->display_name,
		    'type' => $credit_limit->type,
                    'amount' => wc_price($credit_limit->amount*-1),
                    'description' => $credit_limit->description,
                    'approver' => get_user_by('ID', $credit_limit->user_id)->display_name,
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
            case 'date':
            case 'crlim_id':
            case 'name':
            case 'type':
            case 'amount':
            case 'description':
            case 'approver':
                return $item[$column_name];
            default:
                return print_r($item, true);
        }
    }

}
