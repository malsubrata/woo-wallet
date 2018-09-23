<?php

if (!class_exists('WP_List_Table')) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Woo_Wallet_Balance_Details extends WP_List_Table {

    public function __construct() {
        parent::__construct(array(
            'singular' => 'transaction',
            'plural' => 'transactions',
            'ajax' => false,
            'screen' => 'woo-wallet',
        ));
    }

    public function get_columns() {
        return apply_filters('woo_wallet_balance_details_columns', array(
            'id' => __('ID', 'woo-wallet'),
            'username' => __('Username', 'woo-wallet'),
            'name' => __('Name', 'woo-wallet'),
            'email' => __('Email', 'woo-wallet'),
            'balance' => __('Remaining balance', 'woo-wallet'),
            'actions' => __('Actions', 'woo-wallet'),
        ));
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
        usort($data, array(&$this, 'sort_data'));
        $perPage = $this->get_items_per_page('users_per_page', 15);
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
        return array('id');
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'username' => array('username', false),
            'balance' => array('balance', false),
        );
        return apply_filters('woo_wallet_balance_details_sortable_columns', $sortable_columns);
    }

    /**
     * Get the table data
     *
     * @return Array
     */
    private function table_data() {
        $data = array();
        $args = apply_filters('woo_wallet_balance_details_args', array(
            'blog_id' => $GLOBALS['blog_id'],
            'role' => '',
            'role__in' => array(),
            'role__not_in' => array(),
            'meta_key' => '',
            'meta_value' => '',
            'meta_compare' => '',
            'meta_query' => array(),
            'date_query' => array(),
            'include' => array(),
            'exclude' => array(),
            'orderby' => 'login',
            'order' => 'ASC',
            'offset' => '',
            'search' => isset($_POST['s']) ? '*' . $_POST['s'] . '*' : '',
            'number' => '',
            'count_total' => false,
            'fields' => 'all',
            'who' => '',
        ));
        $users = get_users($args);

        foreach ($users as $key => $user) {
            $data[] = array(
                'id' => $user->ID,
                'username' => $user->data->user_login,
                'name' => $user->data->display_name,
                'email' => $user->data->user_email,
                'balance' => woo_wallet()->wallet->get_wallet_balance($user->ID),
                'actions' => ''
            );
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
            case 'id':
            case 'username':
            case 'name':
            case 'email':
            case 'balance':
                return $item[$column_name];
            case 'actions':
                return '<p><a href="' . add_query_arg(array('page' => 'woo-wallet-add', 'user_id' => $item['id']), admin_url('admin.php')) . '" class="button tips wallet-manage"></a> <a class="button tips wallet-view" href="' . add_query_arg(array('page' => 'woo-wallet-transactions', 'user_id' => $item['id']), admin_url('admin.php')) . '"></a></p>';
            default:
                return print_r($item, true);
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data($a, $b) {
        // Set defaults
        $orderby = 'username';
        $order = 'asc';
        // If orderby is set, use this as the sort column
        if (!empty($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
        }
        // If order is set use this as the order
        if (!empty($_GET['order'])) {
            $order = $_GET['order'];
        }
        $result = strcmp($a[$orderby], $b[$orderby]);
        if ($order === 'asc') {
            return $result;
        }
        return -$result;
    }

}
