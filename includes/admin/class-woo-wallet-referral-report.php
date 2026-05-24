<?php
/**
 * Referral report WP_List_Table.
 *
 * Admin screen over the `woo_wallet_referrals` table — every recorded visitor
 * and sign-up referral, filterable by referrer, type, status and date range.
 * The reward amount is reconverted to the active display currency, matching the
 * customer-facing figures.
 *
 * @package StandaleneTech
 * @since   1.6.2
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Referral report list table.
 */
class Woo_Wallet_Referral_Report extends WP_List_Table {

	/**
	 * Total rows for the current query.
	 *
	 * @var int
	 */
	private $total_count = 0;

	/**
	 * Class constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'referral',
				'plural'   => 'referrals',
				'ajax'     => false,
				'screen'   => 'woo-wallet-referral-report',
			)
		);
	}

	/**
	 * Translate the current $_GET filters into get_wallet_referrals() args.
	 *
	 * Shared by the list table and the CSV exporter so both honour the same
	 * filter set. Returns false when a referrer was searched but not found —
	 * the caller should then render an empty result.
	 *
	 * @return array|false
	 */
	public static function get_filter_args() {
		$args = array();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['referral_type'] ) ? sanitize_key( wp_unslash( $_GET['referral_type'] ) ) : '';
		$status = isset( $_GET['referral_status'] ) ? sanitize_key( wp_unslash( $_GET['referral_status'] ) ) : '';
		$after  = isset( $_GET['referral_after'] ) ? sanitize_text_field( wp_unslash( $_GET['referral_after'] ) ) : '';
		$before = isset( $_GET['referral_before'] ) ? sanitize_text_field( wp_unslash( $_GET['referral_before'] ) ) : '';
		$who    = isset( $_GET['referral_referrer'] ) ? sanitize_text_field( wp_unslash( $_GET['referral_referrer'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( in_array( $type, array( 'visit', 'signup' ), true ) ) {
			$args['type'] = $type;
		}
		if ( in_array( $status, array( 'pending', 'completed', 'rejected' ), true ) ) {
			$args['status'] = $status;
		}
		if ( '' !== $after && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $after ) ) {
			$args['after'] = $after . ' 00:00:00';
		}
		if ( '' !== $before && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $before ) ) {
			$args['before'] = $before . ' 23:59:59';
		}
		if ( '' !== $who ) {
			$referrer_id = self::resolve_user( $who );
			if ( ! $referrer_id ) {
				return false;
			}
			$args['referrer_id'] = $referrer_id;
		}
		return $args;
	}

	/**
	 * Resolve a referrer search string (id, login or email) to a user id.
	 *
	 * @param string $who Search string.
	 * @return int User id, or 0 when not found.
	 */
	private static function resolve_user( $who ) {
		if ( is_numeric( $who ) ) {
			$user = get_user_by( 'id', absint( $who ) );
			return $user ? (int) $user->ID : 0;
		}
		$user = get_user_by( 'login', $who );
		if ( ! $user ) {
			$user = get_user_by( 'email', $who );
		}
		return $user ? (int) $user->ID : 0;
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'referrer' => __( 'Referrer', 'woo-wallet' ),
			'referred' => __( 'Referred', 'woo-wallet' ),
			'type'     => __( 'Type', 'woo-wallet' ),
			'amount'   => __( 'Reward', 'woo-wallet' ),
			'status'   => __( 'Status', 'woo-wallet' ),
			'order'    => __( 'Order', 'woo-wallet' ),
			'date'     => __( 'Date', 'woo-wallet' ),
		);
	}

	/**
	 * Empty-state message.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No referrals found.', 'woo-wallet' );
	}

	/**
	 * Prepare items.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), array() );

		$per_page = 20;
		$current  = $this->get_pagenum();
		$args     = self::get_filter_args();

		if ( false === $args ) {
			$this->items = array();
			$this->set_pagination_args(
				array(
					'total_items' => 0,
					'per_page'    => $per_page,
				)
			);
			return;
		}

		$this->total_count = get_wallet_referrals_count( $args );
		$this->items       = (array) get_wallet_referrals(
			array_merge(
				$args,
				array(
					'limit'    => ( ( $current - 1 ) * $per_page ) . ',' . $per_page,
					'order_by' => 'referral_id',
					'order'    => 'DESC',
				)
			)
		);

		$this->set_pagination_args(
			array(
				'total_items' => $this->total_count,
				'per_page'    => $per_page,
			)
		);
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item        Referral row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'referrer':
				return esc_html( woo_wallet_referral_user_label( $item->referrer_id ) );
			case 'referred':
				return esc_html( woo_wallet_referral_user_label( $item->referred_user_id ) );
			case 'type':
				return esc_html( ucfirst( $item->type ) );
			case 'amount':
				return wp_kses_post( wc_price( (float) $item->amount, array( 'currency' => $item->currency ? $item->currency : get_option( 'woocommerce_currency' ) ) ) );
			case 'status':
				return esc_html( ucfirst( $item->status ) );
			case 'order':
				if ( ! $item->order_id ) {
					return '&ndash;';
				}
				$order = wc_get_order( $item->order_id );
				return $order
					? '<a href="' . esc_url( $order->get_edit_order_url() ) . '">#' . esc_html( $item->order_id ) . '</a>'
					: '#' . esc_html( $item->order_id );
			case 'date':
				return esc_html( wc_string_to_datetime( $item->date_created )->date_i18n( wc_date_format() . ' ' . wc_time_format() ) );
		}
		return '';
	}

	/**
	 * Filter controls above the table.
	 *
	 * @param string $which 'top' | 'bottom'.
	 * @return void
	 */
	public function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$type   = isset( $_GET['referral_type'] ) ? sanitize_key( wp_unslash( $_GET['referral_type'] ) ) : '';
		$status = isset( $_GET['referral_status'] ) ? sanitize_key( wp_unslash( $_GET['referral_status'] ) ) : '';
		$after  = isset( $_GET['referral_after'] ) ? sanitize_text_field( wp_unslash( $_GET['referral_after'] ) ) : '';
		$before = isset( $_GET['referral_before'] ) ? sanitize_text_field( wp_unslash( $_GET['referral_before'] ) ) : '';
		$who    = isset( $_GET['referral_referrer'] ) ? sanitize_text_field( wp_unslash( $_GET['referral_referrer'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		?>
		<div class="alignleft actions">
			<input type="hidden" name="page" value="woo-wallet-referral-report" />
			<input type="search" name="referral_referrer" value="<?php echo esc_attr( $who ); ?>" placeholder="<?php esc_attr_e( 'Referrer ID, login or email', 'woo-wallet' ); ?>" />
			<select name="referral_type">
				<option value=""><?php esc_html_e( 'All types', 'woo-wallet' ); ?></option>
				<option value="visit" <?php selected( $type, 'visit' ); ?>><?php esc_html_e( 'Visit', 'woo-wallet' ); ?></option>
				<option value="signup" <?php selected( $type, 'signup' ); ?>><?php esc_html_e( 'Sign-up', 'woo-wallet' ); ?></option>
			</select>
			<select name="referral_status">
				<option value=""><?php esc_html_e( 'All statuses', 'woo-wallet' ); ?></option>
				<option value="completed" <?php selected( $status, 'completed' ); ?>><?php esc_html_e( 'Credited', 'woo-wallet' ); ?></option>
				<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'woo-wallet' ); ?></option>
				<option value="rejected" <?php selected( $status, 'rejected' ); ?>><?php esc_html_e( 'Rejected', 'woo-wallet' ); ?></option>
			</select>
			<input type="date" name="referral_after" value="<?php echo esc_attr( $after ); ?>" title="<?php esc_attr_e( 'From date', 'woo-wallet' ); ?>" />
			<input type="date" name="referral_before" value="<?php echo esc_attr( $before ); ?>" title="<?php esc_attr_e( 'To date', 'woo-wallet' ); ?>" />
			<?php submit_button( __( 'Filter', 'woo-wallet' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}
}
