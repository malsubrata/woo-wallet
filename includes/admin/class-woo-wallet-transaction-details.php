<?php
/**
 * Wallet transaction details WP_List_Table
 *
 * Display wallet transaction details page.
 *
 * @package WooWallet
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Woo_Wallet_Transaction_Details extends WP_List_Table {

	/**
	 * Total number of found users for the current query
	 *
	 * @since 3.1.0
	 * @var int
	 */
	private $total_count = 0;
	/**
	 * Class constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'transaction',
				'plural'   => 'transactions',
				'ajax'     => false,
				'screen'   => 'wc-wallet-transactions',
			)
		);
	}
	/**
	 * Get all columns.
	 */
	public function get_columns() {
		return apply_filters(
			'manage_woo_wallet_transactions_columns',
			array(
				'name'           => __( 'Name', 'woo-wallet' ),
				'type'           => __( 'Type', 'woo-wallet' ),
				'amount'         => __( 'Amount', 'woo-wallet' ),
				'details'        => __( 'Details', 'woo-wallet' ),
				'created_by'     => __( 'Created By', 'woo-wallet' ),
				'date'           => __( 'Date', 'woo-wallet' ),
				'transaction_id' => __( 'ID', 'woo-wallet' ),
			)
		);
	}

	/**
	 * Prepare the items for the table to process
	 */
	public function prepare_items() {
		$columns  = $this->get_columns();
		$hidden   = $this->get_hidden_columns();
		$sortable = $this->get_sortable_columns();

		$per_page     = $this->get_items_per_page( 'transactions_per_page', 10 );
		$current_page = $this->get_pagenum();

		$data                  = $this->table_data( ( $current_page - 1 ) * $per_page, $per_page );
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;

		$this->set_pagination_args(
			array(
				'total_items' => $this->total_count,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Output 'no users' message.
	 *
	 * @since 3.1.0
	 */
	public function no_items() {
		esc_html_e( 'No transactions found.', 'woo-wallet' );
	}

	/**
	 * Define which columns are hidden
	 *
	 * @return Array
	 */
	public function get_hidden_columns() {
		return array( 'transaction_id' );
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
	 * @param int $lower lower.
	 * @param int $uper uper.
	 * @return Array
	 */
	private function table_data( $lower = 0, $uper = 10 ) {
		global $wpdb;
		$data    = array();
		$user_id = filter_input( INPUT_GET, 'user_id' );
		if ( null === $user_id ) {
			return $data;
		}
		$transactions      = get_wallet_transactions(
			array(
				'user_id' => $user_id,
				'limit'   => $lower . ',' . $uper,
			)
		);
		$this->total_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		if ( ! empty( $transactions ) && is_array( $transactions ) ) {
			foreach ( $transactions as $key => $transaction ) {
				$data[] = array(
					'transaction_id' => $transaction->transaction_id,
					'name'           => get_user_by( 'ID', $transaction->user_id )->display_name,
					'type'           => ( 'credit' === $transaction->type ) ? __( 'Credit', 'woo-wallet' ) : __( 'Debit', 'woo-wallet' ),
					'amount'         => wc_price( apply_filters( 'woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id ), woo_wallet_wc_price_args( $transaction->user_id ) ),
					'details'        => $transaction->details,
					'created_by'     => get_user_by( 'ID', $transaction->created_by ) ? '<a href="' . add_query_arg( 'user_id', $transaction->created_by, self_admin_url( 'user-edit.php' ) ) . '">' . get_user_by( 'ID', $transaction->created_by )->display_name . '</a>' : '-',
					'date'           => wc_string_to_datetime( $transaction->date )->date_i18n( wc_date_format() ),
				);
			}
		}
		return $data;
	}

	/**
	 * Define what data to show on each column of the table
	 *
	 * @param  Array  $item        Data.
	 * @param  String $column_name - Current column name.
	 *
	 * @return Mixed
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'transaction_id':
			case 'name':
			case 'type':
			case 'amount':
			case 'details':
			case 'created_by':
			case 'date':
				return $item[ $column_name ];
			default:
				return apply_filters( 'woo_wallet_transaction_details_column_default', print_r( $item, true ), $column_name, $item );
		}
	}

}
