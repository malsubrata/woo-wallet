<?php
/**
 * Plugin ajax file
 *
 * @package WooWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'Woo_Wallet_Ajax' ) ) {
	/**
	 * Plugin Ajax class
	 */
	class Woo_Wallet_Ajax {

		/**
		 * The single instance of the class.
		 *
		 * @var Woo_Wallet_Ajax
		 * @since 1.1.10
		 */
		protected static $_instance = null;

		/**
		 * Main instance
		 *
		 * @return class object
		 */
		public static function instance() {
			if ( is_null( self::$_instance ) ) {
				self::$_instance = new self();
			}
			return self::$_instance;
		}

		/**
		 * Class constructor
		 */
		public function __construct() {
			add_action( 'wp_ajax_woo_wallet_order_refund', array( $this, 'woo_wallet_order_refund' ) );
			add_action( 'wp_ajax_woocommerce_wallet_rated', array( $this, 'woocommerce_wallet_rated' ) );
			add_action( 'wp_ajax_woo-wallet-user-search', array( $this, 'woo_wallet_user_search' ) );
			add_action( 'wp_ajax_woo_wallet_partial_payment_update_session', array( $this, 'woo_wallet_partial_payment_update_session' ) );
			add_action( 'wp_ajax_woo_wallet_refund_partial_payment', array( $this, 'woo_wallet_refund_partial_payment' ) );
			add_action( 'wp_ajax_woo-wallet-dismiss-promotional-notice', array( $this, 'woo_wallet_dismiss_promotional_notice' ) );
			add_action( 'wp_ajax_draw_wallet_transaction_details_table', array( $this, 'draw_wallet_transaction_details_table' ) );

			add_action( 'woocommerce_order_after_calculate_totals', array( $this, 'recalculate_order_cashback_after_calculate_totals' ), 10, 2 );

			add_action( 'wp_ajax_terawallet_export_user_search', array( $this, 'terawallet_export_user_search' ) );

			add_action( 'wp_ajax_terawallet_do_ajax_transaction_export', array( $this, 'terawallet_do_ajax_transaction_export' ) );

			add_action( 'wp_ajax_lock_unlock_terawallet', array( $this, 'lock_unlock_terawallet' ) );
		}
		/**
		 * Lock / Unlock user wallet
		 *
		 * @return void
		 */
		public function lock_unlock_terawallet() {
			check_ajax_referer( 'lock-unlock-nonce', 'security' );
			$user_id = isset( $_POST['user_id'] ) ? sanitize_text_field( wp_unslash( $_POST['user_id'] ) ) : 0;
			$type    = isset( $_POST['type'] ) ? sanitize_text_field( wp_unslash( $_POST['type'] ) ) : '';
			if ( ! current_user_can( 'edit_user', $user_id ) ) {
				wp_die( -1 );
			}
			if ( 'lock' === $type ) {
				update_user_meta( $user_id, '_is_wallet_locked', true );
				wp_send_json_success(
					array(
						'type' => 'unlock',
						'text' => __(
							'Unlock',
							'woo-wallet'
						),
					)
				);
			} else {
				delete_user_meta( $user_id, '_is_wallet_locked' );
				wp_send_json_success(
					array(
						'type' => 'lock',
						'text' => __(
							'Lock',
							'woo-wallet'
						),
					)
				);
			}
		}
		/**
		 * Generate export CSV file.
		 */
		public function terawallet_do_ajax_transaction_export() {
			check_ajax_referer( 'terawallet-exporter-script', 'security' );
			include_once WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php';
			$step = isset( $_POST['step'] ) ? absint( $_POST['step'] ) : 1;

			$exporter = new TeraWallet_CSV_Exporter();

			$exporter->set_step( $step );

			if ( ! empty( $_POST['selected_columns'] ) ) {
				$exporter->set_columns_to_export( wp_unslash( $_POST['selected_columns'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			}

			if ( ! empty( $_POST['selected_users'] ) ) {
				$exporter->set_users_to_export( array_map( 'intval', (array) $_POST['selected_users'] ) );
			}

			if ( ! empty( $_POST['start_date'] ) ) {
				$exporter->set_start_date( sanitize_text_field( wp_unslash( $_POST['start_date'] ) ) );
			}

			if ( ! empty( $_POST['end_date'] ) ) {
				$exporter->set_end_date( sanitize_text_field( wp_unslash( $_POST['end_date'] ) ) );
			}

			if ( ! empty( $_POST['filename'] ) ) {
				$exporter->set_filename( sanitize_text_field( wp_unslash( $_POST['filename'] ) ) );
			}
			$exporter->write_to_csv();
			$query_args = array(
				'nonce'    => wp_create_nonce( 'terawallet-transaction-csv' ),
				'action'   => 'download_export_csv',
				'filename' => $exporter->get_filename(),
			);
			if ( $exporter->get_percent_complete() >= 100 ) {
				wp_send_json_success(
					array(
						'step'       => 'done',
						'percentage' => 100,
						'url'        => add_query_arg( $query_args, admin_url( 'admin.php?page=terawallet-exporter' ) ),
					)
				);
			} else {
				wp_send_json_success(
					array(
						'step'       => ++$step,
						'percentage' => $exporter->get_percent_complete(),
						'columns'    => '',
					)
				);
			}
		}

		/**
		 * Search users for export transactions.
		 */
		public function terawallet_export_user_search() {
			check_ajax_referer( 'search-user', 'security' );
			$term    = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
			$return  = array();
			$blog_id = isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : get_current_blog_id();

			$users = get_users(
				array(
					'blog_id'        => $blog_id,
					'search'         => '*' . $term . '*',
					'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
				)
			);

			foreach ( $users as $user ) {
				$return[] = array(
					/* translators: 1: user_login, 2: user_email */
					'label' => sprintf( _x( '%1$s (%2$s)', 'user autocomplete result', 'woo-wallet' ), $user->user_login, $user->user_email ),
					'value' => $user->ID,
				);
			}
			wp_send_json( $return );
		}

		/**
		 * Recalculate and send order cashback.
		 *
		 * @param Bool     $and_taxes Description.
		 * @param WC_Order $order order.
		 */
		public function recalculate_order_cashback_after_calculate_totals( $and_taxes, $order ) {
			$cashback_amount = woo_wallet()->cashback->calculate_cashback( false, $order->get_id(), true );
			$transaction_id  = get_post_meta( $order->get_id(), '_general_cashback_transaction_id', true );
			if ( $transaction_id ) {
				update_wallet_transaction( $transaction_id, $order->get_customer_id(), array( 'amount' => $cashback_amount ), array( '%f' ) );
			}
		}

		/**
		 * Wallet partial payment refund.
		 */
		public function woo_wallet_refund_partial_payment() {
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				wp_die( -1 );
			}
			$response               = array( 'success' => false );
			$order_id               = absint( filter_input( INPUT_POST, 'order_id' ) );
			$order                  = wc_get_order( $order_id );
			$partial_payment_amount = get_order_partial_payment_amount( $order_id );
			$transaction_id         = woo_wallet()->wallet->credit( $order->get_customer_id(), $partial_payment_amount, __( 'Wallet refund #', 'woo-wallet' ) . $order->get_order_number() );
			if ( $transaction_id ) {
				$response['success'] = true;
				/* translators: wallet amount */
				$order->add_order_note( sprintf( __( '%s refunded to customer wallet', 'woo-wallet' ), wc_price( $partial_payment_amount, woo_wallet_wc_price_args( $order->get_customer_id() ) ) ) );
				update_post_meta( $order_id, '_woo_wallet_partial_payment_refunded', true );
				update_post_meta( $order_id, '_partial_payment_refund_id', $transaction_id );
				do_action( 'woo_wallet_partial_order_refunded', $order_id, $transaction_id );
			}
			wp_send_json( $response );
		}

		/**
		 * Process refund through wallet
		 *
		 * @throws Exception To return errors.
		 */
		public function woo_wallet_order_refund() {
			ob_start();
			check_ajax_referer( 'order-item', 'security' );
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				wp_die( -1 );
			}
			$order_id               = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
			$refund_amount          = isset( $_POST['refund_amount'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['refund_amount'] ) ), wc_get_price_decimals() ) : 0;
			$refunded_amount        = isset( $_POST['refunded_amount'] ) ? wc_format_decimal( sanitize_text_field( wp_unslash( $_POST['refunded_amount'] ) ), wc_get_price_decimals() ) : 0;
			$refund_reason          = isset( $_POST['refund_reason'] ) ? sanitize_text_field( wp_unslash( $_POST['refund_reason'] ) ) : '';
			$line_item_qtys         = isset( $_POST['line_item_qtys'] ) ? array_map( 'intval', json_decode( sanitize_text_field( wp_unslash( $_POST['line_item_qtys'] ) ), true ) ) : array();
			$line_item_totals       = isset( $_POST['line_item_totals'] ) ? array_map( 'floatval', json_decode( sanitize_text_field( wp_unslash( $_POST['line_item_totals'] ) ), true ) ) : array();
			$line_item_tax_totals   = isset( $_POST['line_item_tax_totals'] ) ? array_map( 'floatval', json_decode( sanitize_text_field( wp_unslash( $_POST['line_item_tax_totals'] ) ), true ) ) : array();
			$api_refund             = isset( $_POST['api_refund'] ) && 'true' === $_POST['api_refund'];
			$restock_refunded_items = isset( $_POST['restock_refunded_items'] ) && 'true' === $_POST['restock_refunded_items'];
			$refund                 = false;
			$response               = array();
			try {
				$order      = wc_get_order( $order_id );
				$max_refund = wc_format_decimal( $order->get_total() - $order->get_total_refunded(), wc_get_price_decimals() );

				if ( ( ! $refund_amount && ( wc_format_decimal( 0, wc_get_price_decimals() ) !== $refund_amount ) ) || $max_refund < $refund_amount || 0 > $refund_amount ) {
					throw new Exception( __( 'Invalid refund amount', 'woocommerce' ) );
				}

				if ( wc_format_decimal( $order->get_total_refunded(), wc_get_price_decimals() ) !== $refunded_amount ) {
					throw new Exception( __( 'Error processing refund. Please try again.', 'woocommerce' ) );
				}

				// Prepare line items which we are refunding.
				$line_items = array();
				$item_ids   = array_unique( array_merge( array_keys( $line_item_qtys ), array_keys( $line_item_totals ) ) );

				foreach ( $item_ids as $item_id ) {
					$line_items[ $item_id ] = array(
						'qty'          => 0,
						'refund_total' => 0,
						'refund_tax'   => array(),
					);
				}
				foreach ( $line_item_qtys as $item_id => $qty ) {
					$line_items[ $item_id ]['qty'] = max( $qty, 0 );
				}
				foreach ( $line_item_totals as $item_id => $total ) {
					$line_items[ $item_id ]['refund_total'] = wc_format_decimal( $total );
				}
				foreach ( $line_item_tax_totals as $item_id => $tax_totals ) {
					$line_items[ $item_id ]['refund_tax'] = array_filter( array_map( 'wc_format_decimal', $tax_totals ) );
				}

				// Create the refund object.
				$refund = wc_create_refund(
					array(
						'amount'         => $refund_amount,
						'reason'         => $refund_reason,
						'order_id'       => $order_id,
						'line_items'     => $line_items,
						'refund_payment' => $api_refund,
						'restock_items'  => $restock_refunded_items,
					)
				);
				if ( ! is_wp_error( $refund ) ) {
					$transaction_id = woo_wallet()->wallet->credit( $order->get_customer_id(), $refund_amount, $refund_reason );
					if ( ! $transaction_id ) {
						throw new Exception( __( 'Refund not credited to customer', 'woo-wallet' ) );
					} else {
						do_action( 'woo_wallet_order_refunded', $order, $refund, $transaction_id );
					}
				}

				if ( is_wp_error( $refund ) ) {
					throw new Exception( $refund->get_error_message() );
				}

				if ( did_action( 'woocommerce_order_fully_refunded' ) ) {
					$response['status'] = 'fully_refunded';
				}
			} catch ( Exception $e ) {
				wp_send_json_error( array( 'error' => $e->getMessage() ) );
			}
			// wp_send_json_success must be outside the try block not to break phpunit tests.
			wp_send_json_success( $response );
		}

		/**
		 * Mark wallet rated.
		 */
		public function woocommerce_wallet_rated() {
			if ( current_user_can( 'manage_options' ) ) {
				update_option( 'woocommerce_wallet_admin_footer_text_rated', true );
			}
			die;
		}

		/**
		 * Search users
		 */
		public function woo_wallet_user_search() {
			check_ajax_referer( 'search-user', 'security' );
			$return = array();
			$term   = isset( $_POST['term'] ) ? sanitize_text_field( wp_unslash( $_POST['term'] ) ) : '';
			if ( apply_filters( 'woo_wallet_user_search_exact_match', true ) ) {
				$user = get_user_by( apply_filters( 'woo_wallet_user_search_by', 'email' ), $term );
				if ( $user && wp_get_current_user()->user_email !== $user->user_email ) {
					$return[] = array(
						/* translators: 1: user_login, 2: user_email */
						'label' => sprintf( _x( '%1$s (%2$s)', 'user autocomplete result', 'woo-wallet' ), $user->user_login, $user->user_email ),
						'value' => $user->ID,
					);
				}
			} else {
				$blog_id = isset( $_POST['site_id'] ) ? sanitize_text_field( wp_unslash( $_POST['site_id'] ) ) : get_current_blog_id();

				$users = get_users(
					array(
						'blog_id'        => $blog_id,
						'search'         => '*' . $term . '*',
						'exclude'        => array( get_current_user_id() ),
						'search_columns' => array( 'user_login', 'user_nicename', 'user_email' ),
					)
				);

				foreach ( $users as $user ) {
					$return[] = array(
						/* translators: 1: user_login, 2: user_email */
						'label' => sprintf( _x( '%1$s (%2$s)', 'user autocomplete result', 'woo-wallet' ), $user->user_login, $user->user_email ),
						'value' => $user->ID,
					);
				}
			}
			wp_send_json( $return );
		}
		/**
		 * Update partial payment session.
		 *
		 * @return void
		 */
		public function woo_wallet_partial_payment_update_session() {
			if ( isset( $_POST['checked'] ) && 'true' === $_POST['checked'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				update_wallet_partial_payment_session( true );
			} else {
				update_wallet_partial_payment_session();
			}
			wp_die();
		}
		/**
		 * Dismiss wallet promotonal message.
		 *
		 * @return void
		 */
		public function woo_wallet_dismiss_promotional_notice() {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( __( 'You have no permission to do that', 'woo-wallet' ) );
			}

			if ( isset( $_POST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'woo_wallet_admin' ) ) {
				wp_send_json_error( __( 'Invalid nonce', 'woo-wallet' ) );
			}
			update_option( '_woo_wallet_promotion_dismissed', true );
			wp_send_json_success();
		}

		/**
		 * Send wallet transaction AJAX response.
		 */
		public function draw_wallet_transaction_details_table() {
			check_ajax_referer( 'woo-wallet-transactions', 'security' );
			$start  = isset( $_POST['start'] ) ? sanitize_text_field( wp_unslash( $_POST['start'] ) ) : 0;
			$length = isset( $_POST['length'] ) ? sanitize_text_field( wp_unslash( $_POST['length'] ) ) : 10;
			$search = isset( $_POST['search'] ) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['search'] ) ) : '';
			$args   = array(
				'limit' => "$start, $length",
			);
			if ( isset( $search['value'] ) && ! empty( $search['value'] ) ) {
				$args['where'] = array(
					array(
						'key'      => 'date',
						'value'    => $search['value'] . '%',
						'operator' => 'LIKE',
					),
				);
			}
			$transactions = get_wallet_transactions( $args );
			unset( $args['limit'] );
			$records_total = get_wallet_transactions_count( get_current_user_id() );

			$response = array(
				'draw'            => isset( $_POST['draw'] ) ? sanitize_text_field( wp_unslash( $_POST['draw'] ) ) : 1,
				'recordsTotal'    => $records_total,
				'recordsFiltered' => count( get_wallet_transactions( $args ) ),
				'data'            => array(),
			);
			if ( $transactions ) {
				foreach ( $transactions as $transaction ) {
					$response['data'][] = apply_filters(
						'woo_wallet_transactons_datatable_row_data',
						array(
							'id'      => $transaction->transaction_id,
							'credit'  => 'credit' === $transaction->type ? wc_price( apply_filters( 'woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id ), woo_wallet_wc_price_args( $transaction->user_id ) ) : ' - ',
							'debit'   => 'debit' === $transaction->type ? wc_price( apply_filters( 'woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id ), woo_wallet_wc_price_args( $transaction->user_id ) ) : ' - ',
							'details' => $transaction->details,
							'date'    => wc_string_to_datetime( $transaction->date )->date_i18n( wc_date_format() ),
						),
						$transaction
					);
				}
			}
			wp_send_json( $response );
		}

	}

}
Woo_Wallet_Ajax::instance();
