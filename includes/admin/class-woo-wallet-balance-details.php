<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Woo_Wallet_Balance_Details extends WP_List_Table {

    public function __construct() {
        parent::__construct( array(
            'singular' => 'transaction',
            'plural'   => 'transactions',
            'ajax'     => false,
            'screen'   => 'woo-wallet',
        ) );
    }

    public function get_columns() {
        return apply_filters( 'woo_wallet_balance_details_columns', array(
            'id'       => __( 'ID', 'woo-wallet' ),
            'username' => __( 'Username', 'woo-wallet' ),
            'name'     => __( 'Name', 'woo-wallet' ),
            'email'    => __( 'Email', 'woo-wallet' ),
            'balance'  => __( 'Remaining balance', 'woo-wallet' ),
            'actions'  => __( 'Actions', 'woo-wallet' ),
        ) );
    }

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items() {
        $usersearch     = isset( $_REQUEST['s'] ) ? wp_unslash( trim( $_REQUEST['s'] ) ) : '';
        $users_per_page = $this->get_items_per_page( 'users_per_page', 15);
        $paged          = $this->get_pagenum();
        $columns        = $this->get_columns();
        $hidden         = $this->get_hidden_columns();
        $sortable       = $this->get_sortable_columns();
        $args           = array(
            'blog_id' => $GLOBALS['blog_id'],
            'number'  => $users_per_page,
            'offset'  => ( $paged - 1 ) * $users_per_page,
            'search'  => $usersearch,
            'fields'  => 'all_with_meta'
        );

        if ( '' !== $args['search'] ) {
            $args['search'] = '*' . $args['search'] . '*';
        }
        
        if( isset($_REQUEST['role'])){
            $args['role'] = $_REQUEST['role'];
        }
        
        if ( isset( $_REQUEST['orderby'] ) ) {
            $args['orderby'] = $_REQUEST['orderby'];
        }

        if ( isset( $_REQUEST['order'] ) ) {
            $args['order'] = $_REQUEST['order'];
        }
        
        if (isset($args['orderby'])) {
            if ('balance' === $args['orderby']) {
                $args = array_merge(
                        $args, array(
                            // phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_key
                            'meta_key' => '_current_woo_wallet_balance',
                            'orderby' => 'meta_value_num',
                        )
                );
            }
        }

        $args = apply_filters( 'woo_wallet_users_list_table_query_args', $args );

        // Query the user IDs for this page
        $wp_user_search = new WP_User_Query( $args );
        $data = array();
        foreach ( $wp_user_search->get_results() as $user) {
            $data[] = apply_filters('woo_wallet_balance_details_list_table_item_data', array(
                'id'       => $user->ID,
                'username' => $user->data->user_login,
                'name'     => $user->data->display_name,
                'email'    => $user->data->user_email,
                'balance'  => woo_wallet()->wallet->get_wallet_balance( $user->ID ),
                'actions'  => ''
            ), $user);
        }
        $this->_column_headers = array( $columns, $hidden, $sortable );
        $this->items           = $data;
        $this->set_pagination_args( array(
            'total_items' => $wp_user_search->get_total(),
            'per_page'    => $users_per_page,
        ) );
    }
    /**
    * Output 'no users' message.
    *
    * @since 3.1.0
    */
    public function no_items() {
        _e( 'No users found.' );
    }
    /**
    * Return an associative array listing all the views that can be used
    * with this table.
    *
    * Provides a list of roles and user count for that role for easy
    * Filtersing of the user table.
    *
    * @since  1.3.8
    *
    * @global string $role
    *
    * @return array An array of HTML links, one for each view.
    */
    protected function get_views() {
        global $role;

        $wp_roles = wp_roles();

        $url           = 'admin.php?page=woo-wallet';
        $users_of_blog = count_users();

        $total_users = $users_of_blog['total_users'];
        $avail_roles =& $users_of_blog['avail_roles'];
        unset( $users_of_blog );

	$current_link = ( !empty($_REQUEST['role']) ? $_REQUEST['role'] : 'all');
        $current_link_attributes = ($current_link === 'all') ? ' class="current" aria-current="page"' : '';

        $role_links        = array();
        $role_links['all'] = "<a href='$url'$current_link_attributes>" . sprintf( _nx( 'All <span class="count">(%s)</span>', 'All <span class="count">(%s)</span>', $total_users, 'users' ), number_format_i18n( $total_users ) ) . '</a>';
        foreach ( $wp_roles->get_names() as $this_role => $name ) {
            if ( ! isset( $avail_roles[ $this_role ] ) ) {
                    continue;
            }
			
            $current_link_attributes = '';
            $current_link_attributes = ($current_link == $this_role ? ' class="current" aria-current="page"' : '');

            $name = translate_user_role( $name );
            /* translators: User role name with count */
            $name                     = sprintf( __( '%1$s <span class="count">(%2$s)</span>' ), $name, number_format_i18n( $avail_roles[ $this_role ] ) );
            $role_links[ $this_role ] = "<a href='" . esc_url( add_query_arg( 'role', $this_role, $url ) ) . "'$current_link_attributes>$name</a>";
        }

        if ( ! empty( $avail_roles['none'] ) ) {

                $current_link_attributes = '';

                if ( 'none' === $role ) {
                        $current_link_attributes = ' class="current" aria-current="page"';
                }

                $name = __( 'No role' );
                /* translators: User role name with count */
                $name               = sprintf( __( '%1$s <span class="count">(%2$s)</span>' ), $name, number_format_i18n( $avail_roles['none'] ) );
                $role_links['none'] = "<a href='" . esc_url( add_query_arg( 'role', 'none', $url ) ) . "'$current_link_attributes>$name</a>";

        }

        return $role_links;
    }
    /**
    * Output extra table controls.
    *
    * @since 1.3.8
    *
    * @param string $which Whether this is being invoked above ("top")
    *                      or below the table ("bottom").
    */
    protected function extra_tablenav( $which ) {
        do_action('woo_wallet_users_list_extra_tablenav', $which);
    }
    

    /**
     * Define which columns are hidden
     *
     * @return Array
     */
    public function get_hidden_columns() {
        return array( 'id' );
    }

    /**
     * Define the sortable columns
     *
     * @return Array
     */
    public function get_sortable_columns() {
        $sortable_columns = array(
            'username' => array( 'login', false ),
            'balance'  => array( 'balance', false ),
            'email' => array( 'email', false ),
        );
        return apply_filters( 'woo_wallet_balance_details_sortable_columns', $sortable_columns );
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  Array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name ) {
        switch ( $column_name ) {
            case 'id':
            case 'username':
            case 'name':
            case 'email':
            case 'balance':
                return $item[$column_name];
            case 'actions':
                return '<p><a href="' . add_query_arg( array( 'page' => 'woo-wallet-add', 'user_id' => $item['id'] ), admin_url( 'admin.php' ) ) . '" class="button tips wallet-manage"></a> <a class="button tips wallet-view" href="' . add_query_arg( array( 'page' => 'woo-wallet-transactions', 'user_id' => $item['id'] ), admin_url( 'admin.php' ) ) . '"></a></p>';
            default:
                return apply_filters('woo_wallet_balance_details_column_default', print_r( $item, true ), $column_name, $item);
        }
    }

    /**
     * Allows you to sort the data by the variables set in the $_GET
     *
     * @return Mixed
     */
    private function sort_data( $a, $b ) {
        // Set defaults
        $orderby = 'username';
        $order   = 'asc';
        // If orderby is set, use this as the sort column
        if ( ! empty( $_GET['orderby'] ) ) {
            $orderby = $_GET['orderby'];
        }
        // If order is set use this as the order
        if ( ! empty( $_GET['order'] ) ) {
            $order = $_GET['order'];
        }
        $result = strcmp( $a[$orderby], $b[$orderby] );
        if ( $order === 'asc' ) {
            return $result;
        }
        return -$result;
    }

}
