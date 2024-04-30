<?php
/**
 * Wallet balance details WP_List_Table
 *
 * Display wallet balance details page.
 *
 * @package StandaleneTech
 */

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}
/**
 * Wallet transaction details wp table class.
 */
class Woo_Wallet_Balance_Details extends WP_List_Table {

	/**
	 * Class constuctor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'transaction',
				'plural'   => 'transactions',
				'ajax'     => false,
				'screen'   => 'woo-wallet',
			)
		);
		add_action( 'admin_footer', array( $this, 'add_js_scripts' ) );
	}
	/**
	 * Get columns.
	 */
	public function get_columns() {
		$columns = array(
			'cb'             => __( 'cb', 'woo-wallet' ),
			'username'       => __( 'Username', 'woo-wallet' ),
			'email'          => __( 'Email', 'woo-wallet' ),
			'total_deposits' => __( 'Total Deposits', 'woo-wallet' ),
			'total_spent'    => __( 'Total Spent', 'woo-wallet' ),
			'cashbak_earned' => __( 'Cashback Earned', 'woo-wallet' ),
			'balance'        => __( 'Remaining balance', 'woo-wallet' ),
			'status'         => __( 'Status', 'woo-wallet' ),
			'id'             => __( 'ID', 'woo-wallet' ),
		);
		if ( 'off' === woo_wallet()->settings_api->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'off' ) ) {
			unset( $columns['cashbak_earned'] );
		}
		return apply_filters( 'woo_wallet_balance_details_columns', $columns );
	}

	/**
	 * Output 'no users' message.
	 *
	 * @since 3.1.0
	 */
	public function no_items() {
		esc_html_e( 'No users found.', 'woo-wallet' );
	}
	/**
	 * Prepare the items for the table to process
	 */
	public function prepare_items() {
		$usersearch     = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$users_per_page = $this->get_items_per_page( 'users_per_page', 15 );
		$paged          = $this->get_pagenum();
		$columns        = $this->get_columns();
		$hidden         = $this->get_hidden_columns();
		$sortable       = $this->get_sortable_columns();
		$args           = array(
			'blog_id' => get_current_blog_id(),
			'number'  => $users_per_page,
			'offset'  => ( $paged - 1 ) * $users_per_page,
			'search'  => $usersearch,
			'fields'  => 'all_with_meta',
		);
		if ( '' !== $args['search'] ) {
			$args['search'] = '*' . $args['search'] . '*';
		}

		$args['role'] = isset( $_GET['role'] ) ? sanitize_text_field( wp_unslash( $_GET['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		if ( isset( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['orderby'] = sanitize_text_field( wp_unslash( $_REQUEST['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		if ( isset( $_REQUEST['order'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$args['order'] = sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ); // phpcs:ignore WordPress.Security.NonceVerification
		}

		if ( isset( $args['orderby'] ) ) {
			if ( 'balance' === $args['orderby'] ) {
				$args = array_merge(
					$args,
					array(
						'meta_key' => '_current_woo_wallet_balance', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'orderby'  => 'meta_value_num',
					)
				);
			}
		}
		$args = apply_filters( 'woo_wallet_users_list_table_query_args', $args );

		// Query the user IDs for this page.
		$wp_user_search = new WP_User_Query( $args );
		$data           = array();
		foreach ( $wp_user_search->get_results() as $user ) {
			$data[] = apply_filters(
				'woo_wallet_balance_details_list_table_item_data',
				array(
					'id'       => $user->ID,
					'username' => $user->data->user_login,
					'name'     => $user->data->display_name,
					'email'    => $user->data->user_email,
					'balance'  => woo_wallet()->wallet->get_wallet_balance( $user->ID ),
					'actions'  => '',
				),
				$user
			);
		}
		$this->_column_headers = array( $columns, $hidden, $sortable );
		$this->items           = $data;
		$this->set_pagination_args(
			array(
				'total_items' => $wp_user_search->get_total(),
				'per_page'    => $users_per_page,
			)
		);
		$this->process_bulk_actions();
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

		$wp_roles = wp_roles();

		$count_users = ! wp_is_large_user_count();

		$url = 'admin.php?page=woo-wallet';

		$role_links   = array();
		$avail_roles  = array();
		$all_text     = __( 'All' );
		$selcted_role = isset( $_REQUEST['role'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['role'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification

		if ( $count_users ) {
			$users_of_blog = count_users();

			$total_users = $users_of_blog['total_users'];
			$avail_roles =& $users_of_blog['avail_roles'];
			unset( $users_of_blog );

			$all_text = sprintf(
				/* translators: %s: Number of users. */
				_nx(
					'All <span class="count">(%s)</span>',
					'All <span class="count">(%s)</span>',
					$total_users,
					'users'
				),
				number_format_i18n( $total_users )
			);
		}

		$role_links['all'] = array(
			'url'     => $url,
			'label'   => $all_text,
			'current' => empty( $selcted_role ),
		);

		foreach ( $wp_roles->get_names() as $this_role => $name ) {
			if ( $count_users && ! isset( $avail_roles[ $this_role ] ) ) {
				continue;
			}

			$name = translate_user_role( $name );
			if ( $count_users ) {
				$name = sprintf(
					/* translators: 1: User role name, 2: Number of users. */
					__( '%1$s <span class="count">(%2$s)</span>' ),
					$name,
					number_format_i18n( $avail_roles[ $this_role ] )
				);
			}

			$role_links[ $this_role ] = array(
				'url'     => esc_url( add_query_arg( 'role', $this_role, $url ) ),
				'label'   => $name,
				'current' => $this_role === $selcted_role,
			);
		}

		if ( ! empty( $avail_roles['none'] ) ) {

			$name = __( 'No role' );
			$name = sprintf(
				/* translators: 1: User role name, 2: Number of users. */
				__( '%1$s <span class="count">(%2$s)</span>' ),
				$name,
				number_format_i18n( $avail_roles['none'] )
			);

			$role_links['none'] = array(
				'url'     => esc_url( add_query_arg( 'role', 'none', $url ) ),
				'label'   => $name,
				'current' => 'none' === $selcted_role,
			);
		}

		return $this->get_views_links( $role_links );
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
		if ( 'top' === $which ) {
			/* translators: WooCommerce currency */
			echo( sprintf( "<label class='alignleft actions bulkactions'>%s(%s): <input name='amount' type='number' step='0.01' id='amount'></input></label>", esc_html__( 'Amount', 'woo-wallet' ), get_woocommerce_currency_symbol() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo( sprintf( "<label class='alignleft actions bulkactions'>%s: <input name='description' type='text' id='description'></input></label>", esc_html__( 'Description', 'woo-wallet' ) ) );
		}
		do_action( 'woo_wallet_users_list_extra_tablenav', $which );
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
			'email'    => array( 'email', false ),
		);
		return apply_filters( 'woo_wallet_balance_details_sortable_columns', $sortable_columns );
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
			case 'id':
			case 'name':
			case 'email':
				return esc_html( $item[ $column_name ] );
			default:
				return apply_filters( 'woo_wallet_balance_details_column_default', esc_html( print_r( $item, true ) ), $column_name, $item ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		}
	}
	/**
	 * Display column checkbox.
	 *
	 * @param array $item Item.
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="users[]" value="%s" />', $item['id'] );
	}

	/**
	 * Display balance column.
	 *
	 * @param array $item Item.
	 */
	protected function column_balance( $item ) {
		echo $item['balance'] ? wp_kses_post( $item['balance'] ) : '<span class="na">&ndash;</span>';
	}
	/**
	 * Display username column.
	 *
	 * @param array $item item.
	 * @return string
	 */
	protected function column_username( $item ) {
		$user_object = new WP_User( $item['id'] );

		$link = add_query_arg(
			array(
				'page'    => 'woo-wallet-transactions',
				'user_id' => $item['id'],
			),
			admin_url( 'admin.php' )
		);

		$edit_balance_link = add_query_arg(
			array(
				'action'    => 'get_edit_wallet_balance_template',
				'security'  => wp_create_nonce( 'woo-wallet-edit-balance-template' ),
				'user_id'   => $item['id'],
				'TB_iframe' => false,
				'width'     => 450,
				'height'    => 450,
			),
			admin_url( 'admin-ajax.php' )
		);

		$avatar  = get_avatar( $user_object->ID, 32 );
		$output  = '';
		$output .= $avatar;
		/* translators: 1: user_login */
		$output .= '<strong><a href="' . esc_url( $link ) . '" class="row-title">' . esc_html( $user_object->user_login ) . '</a></strong>';

		// Get actions.
		$actions = array(
			'edit' => '<a href="' . esc_url( $edit_balance_link ) . '" class="thickbox">' . esc_html__( 'Edit Balance', 'woo-wallet' ) . '</a>',
		);

		if ( is_wallet_account_locked( $item['id'] ) ) {
			unset( $actions['edit'] );
		}

		$row_actions = array();

		foreach ( $actions as $action => $link ) {
			$row_actions[] = '<span class="' . esc_attr( $action ) . '">' . $link . '</span>';
		}
		$output .= '<br>';
		$output .= '<div class="row-actions">' . implode( ' | ', $row_actions ) . '</div>';

		return $output;
	}
	/**
	 * Display total deposits column
	 *
	 * @param array $item item.
	 * @return string
	 */
	protected function column_total_deposits( $item ) {
		$args           = array(
			'user_id'    => $item['id'],
			'where'      => array(
				array(
					'key'   => 'type',
					'value' => 'credit',
				),
			),
			'where_meta' => array(
				array(
					'key'   => '_type',
					'value' => 'credit_purchase',
				),
			),
		);
		$transactions   = get_wallet_transactions( $args );
		$total_deposits = array_sum( wp_list_pluck( $transactions, 'amount' ) );
		return wc_price( $total_deposits, woo_wallet_wc_price_args() );
	}
	/**
	 * Render total spent column
	 *
	 * @param array $item item.
	 * @return string
	 */
	protected function column_total_spent( $item ) {
		$args                  = array(
			'user_id'    => $item['id'],
			'where'      => array(
				array(
					'key'   => 'type',
					'value' => 'debit',
				),
			),
			'where_meta' => array(
				array(
					'key'   => '_type',
					'value' => 'purchase',
				),
			),
		);
		$transactions          = get_wallet_transactions( $args );
		$total_spent_by_wallet = array_sum( wp_list_pluck( $transactions, 'amount' ) );
		$args                  = array(
			'user_id'    => $item['id'],
			'where'      => array(
				array(
					'key'   => 'type',
					'value' => 'debit',
				),
			),
			'where_meta' => array(
				array(
					'key'   => '_type',
					'value' => 'partial_payment',
				),
			),
		);
		$transactions          = get_wallet_transactions( $args );
		$total_partial_payment = array_sum( wp_list_pluck( $transactions, 'amount' ) );
		return wc_price( $total_spent_by_wallet + $total_partial_payment, woo_wallet_wc_price_args() );
	}
	/**
	 * Render cashback earned column
	 *
	 * @param array $item item.
	 * @return string
	 */
	protected function column_cashbak_earned( $item ) {
		$args           = array(
			'user_id'    => $item['id'],
			'where'      => array(
				array(
					'key'   => 'type',
					'value' => 'credit',
				),
			),
			'where_meta' => array(
				array(
					'key'   => '_type',
					'value' => 'cashback',
				),
			),
		);
		$transactions   = get_wallet_transactions( $args );
		$total_cashback = array_sum( wp_list_pluck( $transactions, 'amount' ) );
		return wc_price( $total_cashback, woo_wallet_wc_price_args() );
	}
	/**
	 * Render status column
	 *
	 * @param array $item item.
	 * @return void
	 */
	protected function column_status( $item ) {
		$is_locked = is_wallet_account_locked( $item['id'] );
		?>
		<span style="color: <?php echo $is_locked ? '#D63638' : '#135E96'; ?>;">
			<?php if ( $is_locked ) { ?>
				<span class="dashicons dashicons-lock" title="<?php echo esc_attr( __( 'Locked', 'woo-wallet' ) ); ?>"></span>
			<?php } else { ?>
				<span class="dashicons dashicons-yes" title="<?php echo esc_attr( __( 'Active', 'woo-wallet' ) ); ?>"></span>
			<?php } ?>
		</span>
		<?php
	}
	/**
	 * Process Bulk actions.
	 */
	private function process_bulk_actions() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-transactions' ) ) {
			if ( 'credit' === $this->current_action() ) {
				$credit_ids  = isset( $_REQUEST['users'] ) ? array_map( 'intval', (array) $_REQUEST['users'] ) : array();
				$amount      = isset( $_POST['amount'] ) ? floatval( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
				$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
				if ( $amount && $credit_ids ) {
					foreach ( $credit_ids as $id ) {
						woo_wallet()->wallet->credit( $id, $amount, $description );
					}
				}
				header( 'Refresh: 0' );
			}

			if ( 'debit' === $this->current_action() ) {
				$debit_ids   = isset( $_REQUEST['users'] ) ? array_map( 'intval', (array) $_REQUEST['users'] ) : array();
				$amount      = isset( $_POST['amount'] ) ? floatval( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
				$description = isset( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
				if ( $amount && $debit_ids ) {
					foreach ( $debit_ids as $id ) {
						woo_wallet()->wallet->debit( $id, $amount, $description );
					}
				}
				header( 'Refresh: 0' );
			}

			if ( 'delete_log' === $this->current_action() ) {
				$delete_ids = isset( $_REQUEST['users'] ) ? array_map( 'intval', (array) $_REQUEST['users'] ) : array();
				if ( $delete_ids ) {
					foreach ( $delete_ids as $id ) {
						$current_balance = woo_wallet()->wallet->get_wallet_balance( $id, 'edit' );
						delete_user_wallet_transactions( $id, true );
						if ( $current_balance && apply_filters( 'woo_wallet_credit_user_after_delete_log', true ) ) {
							woo_wallet()->wallet->credit( $id, $current_balance, __( 'Balance after deleting transaction logs', 'woo-wallet' ) );
						}
					}
				}
				header( 'Refresh: 0' );
			}

			do_action( 'woo_wallet_balance_details_process_bulk_actions', $this->current_action() );
		}
	}
	/**
	 * Get bulk options.
	 */
	protected function get_bulk_actions() {
		$actions = apply_filters(
			'woo_wallet_balance_details_bulk_actions',
			array(
				'credit'     => __( 'Credit', 'woo-wallet' ),
				'debit'      => __( 'Debit', 'woo-wallet' ),
				'delete_log' => __( 'Delete Log', 'woo-wallet' ),
			)
		);
		return $actions;
	}
	/**
	 * Remove bulk options from table footer.
	 *
	 * @param string $which which.
	 */
	protected function bulk_actions( $which = '' ) {
		if ( 'bottom' === $which ) {
			return;
		}
		parent::bulk_actions( $which );
	}
	/**
	 * Add js for this page.
	 */
	public function add_js_scripts() {
		$bulk_delete_log_msg = __( 'You are about to delete transaction records from database for selected users.', 'woo-wallet' );
		?>
		<script type="text/javascript">
			jQuery(function ($) {
				$('.toplevel_page_woo-wallet #posts-filter').submit(function(){
					if($('[name="action"]').val()=='delete_log' || $('[name="action2"]').val()=='delete_log'){
						return confirm('<?php echo esc_html( $bulk_delete_log_msg ); ?>');
					}
					return true;
				});
			});
		</script>
		<?php

	}

}
