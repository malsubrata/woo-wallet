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
		$base_currency  = $this->resolve_base_currency();
		foreach ( $wp_user_search->get_results() as $user ) {
			$data[] = apply_filters(
				'woo_wallet_balance_details_list_table_item_data',
				array(
					'id'       => $user->ID,
					'username' => $user->data->user_login,
					'name'     => $user->data->display_name,
					'email'    => $user->data->user_email,
					'balance'  => woo_wallet()->wallet->get_wallet_balance( $user->ID, 'view', $base_currency ),
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
		// Amount + description for the Credit / Debit bulk actions are now
		// captured in the WCBackboneModal opened by add_js_scripts() — see
		// templates/admin/credit-debit-modal.php. Kept this hook for the
		// `woo_wallet_users_list_extra_tablenav` extension surface.
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
			'edit' => '<a href="#" class="edit-wallet-balance" data-user-id="' . $user_object->ID . '">' . esc_html__( 'Edit Balance', 'woo-wallet' ) . '</a>',
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
			'user_id' => $item['id'],
			'where'   => array(
				array(
					'key'   => 'type',
					'value' => 'credit',
				),
				array(
					'key'   => 'category',
					'value' => 'topup',
				),
			),
		);
		$transactions   = get_wallet_transactions( $args );
		$base           = $this->resolve_base_currency();
		$total_deposits = $this->sum_transactions_in_base( $transactions, $base );
		return wc_price( $total_deposits, woo_wallet_wc_price_args( $item['id'], array( 'currency' => $base ) ) );
	}
	/**
	 * Render total spent column
	 *
	 * @param array $item item.
	 * @return string
	 */
	protected function column_total_spent( $item ) {
		$base         = $this->resolve_base_currency();
		// Since 1.6.3 both legacy `_type='purchase'` and `_type='partial_payment'`
		// rows are canonicalised to `category='partial_payment'` (see the
		// 1.6.3 backfill + the write-path alias map). A single query suffices.
		$args         = array(
			'user_id' => $item['id'],
			'where'   => array(
				array(
					'key'   => 'type',
					'value' => 'debit',
				),
				array(
					'key'   => 'category',
					'value' => 'partial_payment',
				),
			),
		);
		$transactions = get_wallet_transactions( $args );
		$total_spent  = $this->sum_transactions_in_base( $transactions, $base );
		return wc_price( $total_spent, woo_wallet_wc_price_args( $item['id'], array( 'currency' => $base ) ) );
	}
	/**
	 * Render cashback earned column
	 *
	 * @param array $item item.
	 * @return string
	 */
	protected function column_cashbak_earned( $item ) {
		$args           = array(
			'user_id' => $item['id'],
			'where'   => array(
				array(
					'key'   => 'type',
					'value' => 'credit',
				),
				array(
					'key'   => 'category',
					'value' => 'cashback',
				),
			),
		);
		$transactions   = get_wallet_transactions( $args );
		$base           = $this->resolve_base_currency();
		$total_cashback = $this->sum_transactions_in_base( $transactions, $base );
		return wc_price( $total_cashback, woo_wallet_wc_price_args( $item['id'], array( 'currency' => $base ) ) );
	}

	/**
	 * Resolve the shop base currency for the totals columns, with a defensive
	 * fallback when the currency manager isn't available (e.g. plugin loaded
	 * out of order during activation).
	 *
	 * @return string ISO 4217 code.
	 */
	private function resolve_base_currency() {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			return Woo_Wallet_Currency_Manager::instance()->get_base_currency();
		}
		$base = get_option( 'woocommerce_currency' );
		return is_string( $base ) && '' !== $base ? strtoupper( $base ) : 'USD';
	}

	/**
	 * Sum a list of transaction rows after normalizing each row's amount to
	 * the shop base currency. Rows already stored in base (the single_base
	 * mode default) cost nothing — `Woo_Wallet_Currency_Manager::convert()`
	 * short-circuits when `$from === $to`. On vanilla single-currency sites
	 * every row's currency equals base, so this collapses to the previous
	 * `array_sum( wp_list_pluck( ... ) )` semantics.
	 *
	 * @param array  $transactions Result of `get_wallet_transactions()`.
	 * @param string $base         Base currency ISO code.
	 * @return float
	 */
	private function sum_transactions_in_base( $transactions, $base ) {
		if ( empty( $transactions ) ) {
			return 0.0;
		}
		$manager = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;
		$total   = 0.0;
		foreach ( $transactions as $row ) {
			$row_currency = isset( $row->currency ) && '' !== $row->currency ? strtoupper( $row->currency ) : $base;
			$total       += $manager ? (float) $manager->convert( $row->amount, $row_currency, $base ) : (float) $row->amount;
		}
		return $total;
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
				$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
				if ( $amount && $credit_ids ) {
					foreach ( $credit_ids as $id ) {
						woo_wallet()->wallet->credit( $id, $amount, $description, array( 'category' => 'adjustment' ) );
					}
				}
				header( 'Refresh: 0' );
			}

			if ( 'debit' === $this->current_action() ) {
				$debit_ids   = isset( $_REQUEST['users'] ) ? array_map( 'intval', (array) $_REQUEST['users'] ) : array();
				$amount      = isset( $_POST['amount'] ) ? floatval( sanitize_text_field( wp_unslash( $_POST['amount'] ) ) ) : 0;
				$description = isset( $_POST['description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['description'] ) ) : '';
				if ( $amount && $debit_ids ) {
					foreach ( $debit_ids as $id ) {
						woo_wallet()->wallet->debit( $id, $amount, $description, array( 'category' => 'adjustment' ) );
					}
				}
				header( 'Refresh: 0' );
			}

			if ( 'delete_log' === $this->current_action() ) {
				$delete_ids       = isset( $_REQUEST['users'] ) ? array_map( 'intval', (array) $_REQUEST['users'] ) : array();
				$delete_mode      = isset( $_POST['delete_mode'] ) && 'hard' === $_POST['delete_mode'] ? 'hard' : 'soft';
				$balance_handling = isset( $_POST['balance_handling'] ) && 'wipe' === $_POST['balance_handling'] ? 'wipe' : 'keep';
				$errors           = array();
				if ( $delete_ids ) {
					foreach ( $delete_ids as $id ) {
						$result = woo_wallet_purge_user_transactions( $id, $delete_mode, $balance_handling );
						if ( is_wp_error( $result ) ) {
							$errors[] = $result->get_error_message();
						}
					}
				}
				if ( $errors ) {
					set_transient( 'woo_wallet_purge_error_' . get_current_user_id(), $errors, 30 );
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
		$screen = get_current_screen();
		if ( 'toplevel_page_woo-wallet' === $screen->id ) {
			ob_start();
			woo_wallet()->get_template( 'admin/edit-balance.php' );
			woo_wallet()->get_template( 'admin/delete-log-modal.php' );
			woo_wallet()->get_template( 'admin/credit-debit-modal.php' );
			echo ob_get_clean(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			wp_enqueue_script( 'wc-backbone-modal' );
		}
		?>
		<script type="text/javascript">
			jQuery(function ($) {
				var $listForm = $('.toplevel_page_woo-wallet #posts-filter');

				// Pick the action that's actually selected — bulkactions dropdowns
				// duplicate the control above + below the table, so either one may
				// hold the user's choice.
				function selectedBulkAction() {
					var top    = $listForm.find('[name="action"]').val();
					var bottom = $listForm.find('[name="action2"]').val();
					if (top && '-1' !== top) {
						return top;
					}
					if (bottom && '-1' !== bottom) {
						return bottom;
					}
					return '';
				}

				$listForm.on('submit', function (e) {
					var action = selectedBulkAction();
					if ('delete_log' === action) {
						if ($(this).data('wooWalletDeleteConfirmed')) {
							return true;
						}
						e.preventDefault();
						$(this).WCBackboneModal({ template: 'woo-wallet-modal-delete-log' });
						return false;
					}
					if ('credit' === action || 'debit' === action) {
						if ($(this).data('wooWalletCreditDebitConfirmed')) {
							return true;
						}
						// Need at least one row selected.
						var checked = $listForm.find('input[name="users[]"]:checked').length;
						if (!checked) {
							return true; // Let WP's native "no items selected" handling run.
						}
						e.preventDefault();
						$(this).data('wooWalletPendingAction', action);
						$(this).WCBackboneModal({ template: 'woo-wallet-modal-credit-debit' });
						// Set the modal title + button label to match the chosen action.
						var $modal = $('.woo-wallet-credit-debit');
						if ('credit' === action) {
							$modal.find('#woo-wallet-credit-debit-title').text('<?php echo esc_js( __( 'Credit wallet balance', 'woo-wallet' ) ); ?>');
							$modal.find('#woo-wallet-confirm-credit-debit').text('<?php echo esc_js( __( 'Credit', 'woo-wallet' ) ); ?>');
						} else {
							$modal.find('#woo-wallet-credit-debit-title').text('<?php echo esc_js( __( 'Debit wallet balance', 'woo-wallet' ) ); ?>');
							$modal.find('#woo-wallet-confirm-credit-debit').text('<?php echo esc_js( __( 'Debit', 'woo-wallet' ) ); ?>');
						}
						return false;
					}
					return true;
				});
				$(document).on('click', '#woo-wallet-confirm-delete-log', function (e) {
					e.preventDefault();
					var $modal    = $(this).closest('.wc-backbone-modal');
					var mode      = $modal.find('input[name="woo_wallet_delete_mode"]:checked').val() || 'soft';
					var handling  = $modal.find('input[name="woo_wallet_balance_handling"]:checked').val() || 'keep';
					$listForm.find('input[name="delete_mode"], input[name="balance_handling"]').remove();
					$listForm.append($('<input>').attr({ type: 'hidden', name: 'delete_mode', value: mode }));
					$listForm.append($('<input>').attr({ type: 'hidden', name: 'balance_handling', value: handling }));
					$listForm.data('wooWalletDeleteConfirmed', true);
					$('.wc-backbone-modal-backdrop.modal-close').trigger('click');
					$listForm[0].submit();
				});
				$(document).on('click', '#woo-wallet-confirm-credit-debit', function (e) {
					e.preventDefault();
					var $modal      = $(this).closest('.wc-backbone-modal');
					var amount      = parseFloat($modal.find('#woo-wallet-bulk-amount').val());
					var description = $modal.find('#woo-wallet-bulk-description').val() || '';
					if (isNaN(amount) || amount <= 0) {
						$modal.find('#woo-wallet-bulk-amount').focus();
						return false;
					}
					$listForm.find('input[name="amount"], input[name="description"]').remove();
					$listForm.append($('<input>').attr({ type: 'hidden', name: 'amount', value: amount }));
					$listForm.append($('<input>').attr({ type: 'hidden', name: 'description', value: description }));
					$listForm.data('wooWalletCreditDebitConfirmed', true);
					$('.wc-backbone-modal-backdrop.modal-close').trigger('click');
					$listForm[0].submit();
				});
				$(document).on('click', '.toplevel_page_woo-wallet .edit-wallet-balance', function (event) {
					event.preventDefault();
					var self = $(this);
					self.html('<?php echo esc_js( __( 'Loading...', 'woo-wallet' ) ); ?>');
					var $user_id = $(this).data('userId');
					$.ajax({
						url:     '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>',
						data:    {
							user_id: $user_id,
							action  : 'get_edit_wallet_balance_template_data',
							security: '<?php echo esc_js( wp_create_nonce( 'woo-wallet-edit-balance-template-data' ) ); ?>'
						},
						type:    'GET',
						success: function( response ) {
							if ( response.success ) {
								$( this ).WCBackboneModal({
									template: 'woo-wallet-modal-edit-balance',
									variable : response.data
								});
							}
							self.html('<?php echo esc_js( __( 'Edit Balance', 'woo-wallet' ) ); ?>');
						}
					});
				});
			});
		</script>
		<?php
	}
}
