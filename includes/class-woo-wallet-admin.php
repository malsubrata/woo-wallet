<?php
/**
 * Wallet Admin file.
 *
 * @package StandaleneTech
 */

use Automattic\WooCommerce\Utilities\OrderUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
if ( ! class_exists( 'Woo_Wallet_Admin' ) ) {
	/**
	 * Wallet admin class.
	 */
	class Woo_Wallet_Admin {

		/**
		 * The single instance of the class.
		 *
		 * @var Woo_Wallet_Admin
		 * @since 1.1.10
		 */
		protected static $_instance = null;

		/**
		 * Woo_Wallet_Transaction_Details Class Object
		 *
		 * @var Woo_Wallet_Transaction_Details
		 */
		public $transaction_details_table = null;

		/**
		 * Woo_Wallet_Balance_Details Class Object
		 *
		 * @var Woo_Wallet_Balance_Details
		 */
		public $balance_details_table = null;

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
			add_action( 'admin_init', array( $this, 'admin_init' ) );
			add_action( 'in_admin_header', array( $this, 'suppress_reports_admin_notices' ), 1000 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ), 10 );
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 50 );
			add_action( 'admin_post_woo_wallet_export_referrals', array( $this, 'export_referrals_csv' ) );
			if ( 'on' === woo_wallet()->settings_api->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'off' ) && 'product' === woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' ) ) {
				add_filter( 'woocommerce_product_data_tabs', array( $this, 'woocommerce_product_data_tabs' ) );
				add_action( 'woocommerce_product_data_panels', array( $this, 'woocommerce_product_data_panels' ) );
				add_action( 'save_post_product', array( $this, 'save_post_product' ) );

				add_action( 'woocommerce_variation_options_pricing', array( $this, 'woocommerce_variation_options_pricing' ), 10, 3 );
				add_action( 'woocommerce_save_product_variation', array( $this, 'woocommerce_save_product_variation' ), 10, 2 );
			}
			add_action( 'woocommerce_admin_order_totals_after_tax', array( $this, 'add_wallet_payment_amount' ), 10, 1 );

			add_action( 'woocommerce_coupon_options', array( $this, 'add_coupon_option_for_cashback' ) );
			add_action( 'woocommerce_coupon_options_save', array( $this, 'save_coupon_data' ) );

			add_filter( 'admin_footer_text', array( $this, 'admin_footer_text' ), 5 );

			if ( 'on' === woo_wallet()->settings_api->get_option( 'is_enable_cashback_reward_program', '_wallet_settings_credit', 'off' ) && 'product_cat' === woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' ) ) {
				add_action( 'product_cat_add_form_fields', array( $this, 'add_product_cat_cashback_field' ) );
				add_action( 'product_cat_edit_form_fields', array( $this, 'edit_product_cat_cashback_field' ) );
				add_action( 'created_term', array( $this, 'save_product_cashback_field' ), 10, 3 );
				add_action( 'edit_term', array( $this, 'save_product_cashback_field' ), 10, 3 );
			}
			add_filter( 'woocommerce_custom_nav_menu_items', array( $this, 'woocommerce_custom_nav_menu_items' ) );

			add_filter( 'manage_users_columns', array( $this, 'manage_users_columns' ) );
			add_filter( 'manage_users_custom_column', array( $this, 'manage_users_custom_column' ), 10, 3 );
			add_filter( 'set-screen-option', array( $this, 'set_wallet_screen_options' ), 10, 3 );
			add_filter( 'woocommerce_screen_ids', array( $this, 'woocommerce_screen_ids_callback' ) );
			add_action( 'woocommerce_after_order_fee_item_name', array( $this, 'woocommerce_after_order_fee_item_name_callback' ), 10, 2 );
			add_action( 'woocommerce_new_order', array( $this, 'woocommerce_new_order' ) );
			add_filter( 'woocommerce_order_actions', array( $this, 'woocommerce_order_actions' ) );
			add_action( 'woocommerce_order_action_recalculate_order_cashback', array( $this, 'recalculate_order_cashback' ) );

			// Not an admin notice: the promo is page content, emitted only by
			// TeraWallet's own screens through their `woo_wallet_admin_page_header`
			// hook. See show_promotions().
			add_action( 'woo_wallet_admin_page_header', array( $this, 'show_promotions' ) );
			add_action( 'admin_notices', array( $this, 'show_161_notices' ) );
			add_action( 'admin_notices', array( $this, 'show_purge_errors' ) );
			add_action( 'wp_ajax_woowallet_dismiss_161_notice', array( $this, 'dismiss_161_notice' ) );
			// Redirect old ?page=woo-wallet-actions bookmarks to the unified settings page.
			add_action( 'admin_init', array( $this, 'redirect_legacy_actions_page' ) );
			add_filter( 'woocommerce_settings_pages', array( $this, 'add_woocommerce_account_endpoint_settings' ) );

			add_action( 'woocommerce_after_dashboard_status_widget', array( $this, 'add_wallet_topup_report' ) );

			add_action( 'edit_user_profile', array( $this, 'add_wallet_management_fields' ) );
			add_action( 'show_user_profile', array( $this, 'add_wallet_management_fields' ) );

			add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );

			add_action( 'current_screen', array( $this, 'remove_woocommerce_help_tabs' ), 999 );
		}
		/**
		 * Remove all WooCommerce help tabs
		 *
		 * @return void
		 */
		public function remove_woocommerce_help_tabs(): void {
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}
			if ( woo_wallet_get_screen_id( 'woo-wallet-actions' ) === $screen->id ) {
				$screen->remove_help_tabs();
			}
		}

		/**
		 * Show row meta on the plugin screen.
		 *
		 * @param mixed $links Plugin Row Meta.
		 * @param mixed $file  Plugin Base file.
		 *
		 * @return array
		 */
		public static function plugin_row_meta( $links, $file ) {
			if ( plugin_basename( WOO_WALLET_PLUGIN_FILE ) !== $file ) {
				return $links;
			}

			/**
			 * The TeraWallet API documentation URL.
			 *
			 * @since 1.4.6
			 */
			$docs_url = apply_filters( 'terawallet_apidocs_url', 'https://docs.standalonetech.com/' );

			/**
			 * The community TeraWallet support URL.
			 *
			 * @since 1.4.6
			 */
			$community_support_url = apply_filters( 'terawallet_community_support_url', 'https://standalonetech.com/support-forum/' );

			$row_meta = array(
				'plugins' => '<a style="font-weight: 600;" href="' . esc_url(
					woo_wallet_pro_url(
						'plugins-row',
						array(
							'utm_medium'  => 'plugin_page',
							'utm_site_id' => md5( home_url( '/' ) ),
						)
					)
				) . '" aria-label="' . esc_attr__( 'View TeraWallet pro plugins', 'woo-wallet' ) . '"><span class="dashicons dashicons-admin-network"></span> ' . esc_html__( 'Upgrade to Pro', 'woo-wallet' ) . '</a>',
				'docs'    => '<a href="' . esc_url( $docs_url ) . '" aria-label="' . esc_attr__( 'View TeraWallet docs', 'woo-wallet' ) . '">' . esc_html__( 'Docs', 'woo-wallet' ) . '</a>',
				'support' => '<a href="' . esc_url( $community_support_url ) . '" aria-label="' . esc_attr__( 'Visit community forums', 'woo-wallet' ) . '">' . esc_html__( 'Support forum', 'woo-wallet' ) . '</a>',
			);

			return array_merge( $links, $row_meta );
		}
		/**
		 * Wallet settings fields on user edit page.
		 *
		 * @param WP_User $user User.
		 */
		public function add_wallet_management_fields( $user ) {
			?>
			<h3 class="heading"><?php esc_html_e( 'Wallet Management', 'woo-wallet' ); ?></h3>
			<table class="form-table">
				<tr>
					<th><label for="contact"><?php esc_html_e( 'Current wallet balance', 'woo-wallet' ); ?></label></th>

					<td>
						<?php echo woo_wallet()->wallet->get_wallet_balance( $user->ID ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>

				</tr>
				<tr>
					<th><label for="contact"><?php esc_html_e( 'Lock / Unlock', 'woo-wallet' ); ?></label></th>

					<td>
						<button type="button" class="button hide-if-no-js lock-unlock-user-wallet" data-user_id="<?php echo esc_attr( $user->ID ); ?>" data-type="<?php echo get_user_meta( $user->ID, '_is_wallet_locked', true ) ? 'unlock' : 'lock'; ?>">
							<?php if ( is_wallet_account_locked( $user->ID ) ) { ?>
								<span class="dashicons dashicons-unlock" style="padding-top: 3px;"></span> <label><?php esc_html_e( 'Unlock', 'woo-wallet' ); ?></label>
							<?php } else { ?>
								<span class="dashicons dashicons-lock" style="padding-top: 3px;"></span> <label><?php esc_html_e( 'Lock', 'woo-wallet' ); ?></label>
							<?php } ?>
						</button>
					</td>

				</tr>
				<?php do_action( 'after_terawallet_management_fields', $user ); ?>
			</table>

			<?php
		}

		/**
		 * Add Total wallet top-up amount
		 * to WooCommerce Status report widget.
		 */
		public function add_wallet_topup_report() {
			if ( current_user_can( 'view_woocommerce_reports' ) ) {
				$hpos_enabled = OrderUtil::custom_orders_table_usage_is_enabled();
				if ( $hpos_enabled ) {
					$wallet_recharge_order_ids = wc_get_orders(
						array(
							'limit'        => -1,
							'meta_query'   => array(
								array(
									'key'   => '_wc_wallet_purchase_credited',
									'value' => true,
								),
							),
							'date_created' => '>=' . gmdate( 'Y-m-01' ),
							'return'       => 'ids',
							'status'       => wc_get_is_paid_statuses(),
						)
					);
				} else {
					$wallet_recharge_order_ids = wc_get_orders(
						array(
							'limit'        => -1,
							'topuporders'  => true,
							'date_created' => '>=' . gmdate( 'Y-m-01' ),
							'return'       => 'ids',
							'status'       => wc_get_is_paid_statuses(),
						)
					);
				}
				$top_up_amount  = 0;
				$wallet_product = get_wallet_rechargeable_product();
				$wallet_prod_id = $wallet_product ? $wallet_product->get_id() : 0;
				foreach ( $wallet_recharge_order_ids as $order_id ) {
					$order = wc_get_order( $order_id );
					if ( ! $order ) {
						continue;
					}
					// Mirrors the credited figure in Woo_Wallet_Wallet::wallet_credit_purchase():
					// the recharge line's post-discount, pre-tax total, so the report matches
					// the ledger.
					$collected = 0.0;
					foreach ( $order->get_items() as $line_item ) {
						if ( $wallet_prod_id !== $line_item->get_product_id() ) {
							continue;
						}
						$collected += (float) $line_item->get_total();
					}
					$recharge_amount = apply_filters( 'woo_wallet_credit_purchase_amount', $collected, $order_id );
					$charge_amount   = $order->get_meta( '_wc_wallet_purchase_gateway_charge' );
					if ( $charge_amount ) {
						$recharge_amount -= $charge_amount;
					}
					$top_up_amount += $recharge_amount;
				}
				?>
				<li class="sales-this-month">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wc-reports&tab=orders&range=month' ) ); ?>">
				<?php
				printf(
						/* translators: %s: wallet top-up */
					esc_html__( '%s wallet top-up this month', 'woo-wallet' ),
					'<strong>' . wp_kses_post( wc_price( $top_up_amount ) ) . '</strong>'
				);
				?>
					</a>
				</li>
				<?php
			}
		}

		/**
		 * Admin init
		 */
		public function admin_init() {
			if ( version_compare( WC_VERSION, '3.4', '<' ) ) {
				add_filter( 'woocommerce_account_settings', array( $this, 'add_woocommerce_account_endpoint_settings' ) );
			}
			$this->download_export_file();
		}
		/**
		 * Download generated export CSV file.
		 */
		public function download_export_file() {
			if ( isset( $_GET['action'], $_GET['nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ) ), 'terawallet-transaction-csv' ) && 'download_export_csv' === sanitize_text_field( wp_unslash( $_GET['action'] ) ) ) {
				$exporter = new TeraWallet_CSV_Exporter();
				if ( ! empty( $_GET['filename'] ) ) {
					$exporter->set_filename( sanitize_text_field( wp_unslash( $_GET['filename'] ) ) );
				}
				$exporter->export();
			}
		}

		/**
		 * Init admin menu
		 */
		public function admin_menu() {
			$reports_cap = apply_filters( 'woo_wallet_reports_capability', 'manage_woocommerce' );

			// Top-level TeraWallet menu now lands on the wallet Dashboard (Reports).
			add_menu_page( __( 'TeraWallet', 'woo-wallet' ), __( 'TeraWallet', 'woo-wallet' ), $reports_cap, 'woo-wallet', array( $this, 'reports_page' ), '', 59 );
			// Explicit label for the auto-generated first submenu (shares the parent slug).
			add_submenu_page( 'woo-wallet', __( 'Dashboard', 'woo-wallet' ), __( 'Dashboard', 'woo-wallet' ), $reports_cap, 'woo-wallet', array( $this, 'reports_page' ) );

			// The former landing page (per-user wallet balances) moves to its own submenu.
			$woo_wallet_users_hook = add_submenu_page( 'woo-wallet', __( 'Wallet Users', 'woo-wallet' ), __( 'Wallet Users', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-users', array( $this, 'wallet_page' ) );
			add_action( "load-$woo_wallet_users_hook", array( $this, 'handle_wallet_balance_adjustment' ) );
			add_action( "load-$woo_wallet_users_hook", array( $this, 'add_woo_wallet_details' ) );

			$woo_wallet_menu_page_hook_view = add_submenu_page( 'null', __( 'Woo Wallet', 'woo-wallet' ), __( 'Woo Wallet', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-transactions', array( $this, 'transaction_details_page' ) );
			add_action( "load-$woo_wallet_menu_page_hook_view", array( $this, 'add_woo_wallet_transaction_details_option' ) );
			// Actions submenu removed — actions are now part of the unified Settings page (React app).

			add_submenu_page( 'null', '', '', get_wallet_user_capability(), 'terawallet-exporter', array( $this, 'terawallet_exporter_page' ) );

			if ( $this->is_referral_action_enabled() ) {
				add_submenu_page( 'woo-wallet', __( 'Referral Report', 'woo-wallet' ), __( 'Referral Report', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-referral-report', array( $this, 'referral_report_page' ) );
			}
		}

		/**
		 * Render the wallet liability Reports screen.
		 *
		 * @return void
		 */
		public function reports_page() {
			include_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-reports.php';
			$reports = new Woo_Wallet_Reports();
			$reports->render();
		}

		/**
		 * Screen ids of the plugin's own admin pages.
		 *
		 * Upsell surfaces are confined to these — the WordPress.org guideline
		 * against hijacking other people's screens means a promo may only render
		 * on pages belonging to this plugin. The Go Pro page is excluded on
		 * purpose: it is itself the upsell, and stacking a promo notice above it
		 * would be noise.
		 *
		 * @since 1.6.11
		 * @return string[]
		 */
		protected function wallet_own_screen_ids() {
			return array(
				woo_wallet_get_screen_id( 'woo-wallet', '' ),
				woo_wallet_get_screen_id( 'woo-wallet-users' ),
				woo_wallet_get_screen_id( 'woo-wallet-settings' ),
				woo_wallet_get_screen_id( 'woo-wallet-referral-report' ),
				woo_wallet_get_screen_id( 'woo-wallet-transactions', 'null' ),
			);
		}

		/**
		 * Strip third-party admin notices from the Wallet Dashboard and Settings
		 * screens — they are clean, self-contained layouts and license/upsell
		 * nags from other plugins break them. Runs on `in_admin_header`, before
		 * notices are output.
		 *
		 * Our own surfaces are re-attached afterwards: the point of this
		 * suppression is to keep *other* plugins out of our layout, not to
		 * silence our own messages. The Pro promo is unaffected either way — it
		 * is page content on `woo_wallet_admin_page_header`, not a notice.
		 *
		 * @return void
		 */
		public function suppress_reports_admin_notices() {
			$screen = get_current_screen();
			if ( $screen && in_array( $screen->id, array( woo_wallet_get_screen_id( 'woo-wallet', '' ), woo_wallet_get_screen_id( 'woo-wallet-settings' ) ), true ) ) {
				remove_all_actions( 'admin_notices' );
				remove_all_actions( 'all_admin_notices' );
				remove_all_actions( 'user_admin_notices' );
				remove_all_actions( 'network_admin_notices' );

				// Re-attach the plugin's own surfaces stripped by the calls above.
				add_action( 'admin_notices', array( $this, 'show_purge_errors' ) );
			}
		}

		/**
		 * Redirect legacy ?page=woo-wallet-actions bookmarks to the unified Settings page.
		 */
		public function redirect_legacy_actions_page() {
			// phpcs:ignore WordPress.Security.NonceVerification
			if ( isset( $_GET['page'] ) && 'woo-wallet-actions' === $_GET['page'] ) {
				wp_safe_redirect( admin_url( 'admin.php?page=woo-wallet-settings' ) );
				exit;
			}
		}
		/**
		 * Load exporter files.
		 *
		 * @return void
		 */
		public function terawallet_exporter_page() {
			include_once WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php';
			include_once WOO_WALLET_ABSPATH . 'templates/admin/html-exporter.php';
		}
		/**
		 * Whether the referrals earning action is enabled.
		 *
		 * @return bool
		 */
		protected function is_referral_action_enabled() {
			$actions = class_exists( 'WOO_Wallet_Actions' ) ? WOO_Wallet_Actions::instance()->actions : array();
			return isset( $actions['referrals'] ) && $actions['referrals']->is_enabled();
		}
		/**
		 * Render the Referral Report admin screen.
		 *
		 * Prints a store-wide summary header (independent of the table filters)
		 * above the filterable WP_List_Table of referral rows.
		 *
		 * @return void
		 */
		public function referral_report_page() {
			if ( ! class_exists( 'Woo_Wallet_Referral_Report' ) ) {
				include_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-referral-report.php';
			}
			$table = new Woo_Wallet_Referral_Report();
			$table->prepare_items();

			// Store-wide summary — deliberately ignores the table filters.
			$total_referrals = get_wallet_referrals_count();
			$total_signups   = get_wallet_referrals_count(
				array(
					'type'   => 'signup',
					'status' => 'completed',
				)
			);
			$base_currency   = get_option( 'woocommerce_currency' );
			$paid            = 0.0;
			foreach ( (array) get_wallet_referrals( array( 'status' => 'completed' ) ) as $row ) {
				$paid += (float) $row->amount;
			}

			// Carry the active filters onto the CSV export link.
			$export_args = array( 'action' => 'woo_wallet_export_referrals' );
			foreach ( array( 'referral_referrer', 'referral_type', 'referral_status', 'referral_after', 'referral_before' ) as $filter_key ) {
				if ( ! empty( $_GET[ $filter_key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					$export_args[ $filter_key ] = sanitize_text_field( wp_unslash( $_GET[ $filter_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				}
			}
			$export_url = wp_nonce_url( add_query_arg( $export_args, admin_url( 'admin-post.php' ) ), 'woo_wallet_export_referrals' );
			?>
			<div class="wrap">
				<h1 class="wp-heading-inline"><?php esc_html_e( 'Referral Report', 'woo-wallet' ); ?></h1>
				<a href="<?php echo esc_url( $export_url ); ?>" class="page-title-action"><?php esc_html_e( 'Download CSV', 'woo-wallet' ); ?></a>
				<hr class="wp-header-end" />
				<?php do_action( 'woo_wallet_admin_page_header' ); ?>
				<p>
					<strong><?php esc_html_e( 'Summary:', 'woo-wallet' ); ?></strong>
					<?php
					/* translators: %s: total number of referral records. */
					echo esc_html( sprintf( _n( '%s referral', '%s referrals', $total_referrals, 'woo-wallet' ), number_format_i18n( $total_referrals ) ) );
					echo ' &middot; ';
					/* translators: %s: number of credited sign-up referrals. */
					echo esc_html( sprintf( _n( '%s credited sign-up', '%s credited sign-ups', $total_signups, 'woo-wallet' ), number_format_i18n( $total_signups ) ) );
					echo ' &middot; ';
					/* translators: %s: total rewards paid. */
					echo wp_kses_post( sprintf( __( '%s paid', 'woo-wallet' ), wc_price( $paid, array( 'currency' => $base_currency ) ) ) );
					?>
				</p>
				<form method="get">
					<input type="hidden" name="page" value="woo-wallet-referral-report" />
					<?php $table->display(); ?>
				</form>
			</div>
			<?php
		}
		/**
		 * Stream the referral report as a CSV download.
		 *
		 * Honours the same filter set as the on-screen report. The reward is
		 * exported as the stored amount + currency (the audited value), not the
		 * display-currency conversion.
		 *
		 * @return void
		 */
		public function export_referrals_csv() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to export referrals.', 'woo-wallet' ) );
			}
			check_admin_referer( 'woo_wallet_export_referrals' );

			if ( ! class_exists( 'Woo_Wallet_Referral_Report' ) ) {
				include_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-referral-report.php';
			}
			$args = Woo_Wallet_Referral_Report::get_filter_args();
			$rows = ( false === $args )
				? array()
				: (array) get_wallet_referrals(
					array_merge(
						$args,
						array(
							'order_by' => 'referral_id',
							'order'    => 'DESC',
						)
					)
				);

			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename=referral-report-' . gmdate( 'Y-m-d' ) . '.csv' );

			$output = fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			fputcsv( $output, array( 'ID', 'Referrer', 'Referred', 'Type', 'Status', 'Reward', 'Currency', 'Order', 'Date created', 'Date credited' ) );
			// Strip leading formula-trigger characters from user-controllable
			// fields so display_name / email containing `=HYPERLINK(...)` etc.
			// cannot execute when the CSV is opened in Excel / LibreOffice.
			$csv_escape = static function ( $value ) {
				if ( null === $value ) {
					return '';
				}
				$value = (string) $value;
				return ltrim( $value, "=+-@\t\r" );
			};
			foreach ( $rows as $row ) {
				fputcsv(
					$output,
					array(
						(int) $row->referral_id,
						$csv_escape( woo_wallet_referral_user_label( $row->referrer_id ) ),
						$csv_escape( woo_wallet_referral_user_label( $row->referred_user_id ) ),
						$csv_escape( $row->type ),
						$csv_escape( $row->status ),
						$row->amount,
						$csv_escape( $row->currency ),
						$row->order_id ? (int) $row->order_id : '',
						$csv_escape( $row->date_created ),
						$row->date_credited ? $csv_escape( $row->date_credited ) : '',
					)
				);
			}
			fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			exit;
		}
		/**
		 * Register and enqueue admin styles and scripts
		 *
		 * @global type $post
		 */
		public function admin_scripts() {
			global $wp_query, $post, $theorder;
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';
			// register styles.
			wp_register_style( 'woo_wallet_admin_styles', woo_wallet()->plugin_url() . '/build/admin/main.css', array(), WOO_WALLET_PLUGIN_VERSION );
			// Add RTL support.
			wp_style_add_data( 'woo_wallet_admin_styles', 'rtl', 'replace' );
			// Register scripts.
			wp_register_script( 'woo_wallet_admin_product', woo_wallet()->plugin_url() . '/build/admin/product.js', array( 'jquery' ), WOO_WALLET_PLUGIN_VERSION, true );
			wp_register_script( 'woo_wallet_admin_order', woo_wallet()->plugin_url() . '/build/admin/order.js', array( 'jquery', 'wc-admin-order-meta-boxes' ), WOO_WALLET_PLUGIN_VERSION, true );

			if ( in_array( $screen_id, array( 'product', 'edit-product' ), true ) ) {
				wp_enqueue_script( 'woo_wallet_admin_product' );
				wp_localize_script(
					'woo_wallet_admin_product',
					'woo_wallet_admin_product_param',
					array(
						'product_id' => get_wallet_rechargeable_product()->get_id(),
						'is_hidden'  => apply_filters(
							'woo_wallet_hide_rechargeable_product',
							true
						),
					)
				);
			}
			if ( in_array( $screen_id, array( 'shop_order', 'woocommerce_page_wc-orders' ), true ) ) {
				$order_id = 0;
				if ( $theorder instanceof WC_Order ) {
					$order_id = $theorder->get_id();
				} elseif ( is_a( $post, 'WP_Post' ) && 'shop_order' === get_post_type( $post ) ) {
					$order_id = $post->ID;
				}
				$order = wc_get_order( $order_id );
				if ( $order ) {
					wp_enqueue_script( 'woo_wallet_admin_order' );
					$order_localizer = array(
						'order_id'       => $order_id,
						'payment_method' => $order->get_payment_method( 'edit' ),
						'default_price'  => wc_price( 0 ),
						'is_refundable'  => apply_filters( 'woo_wallet_is_order_refundable', ( ! is_wallet_rechargeable_order( $order ) && 'wallet' !== $order->get_payment_method( 'edit' ) ) && $order->get_customer_id( 'edit' ), $order ),
						'i18n'           => array(
							'refund'     => __( 'Refund', 'woo-wallet' ),
							'via_wallet' => __( 'to customer wallet', 'woo-wallet' ),
						),
					);
					wp_localize_script( 'woo_wallet_admin_order', 'woo_wallet_admin_order_param', $order_localizer );
				}
			}
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_style( 'woo_wallet_admin_styles' );

			// register exporter styles.
			wp_register_style( 'terawallet-exporter-style', woo_wallet()->plugin_url() . '/build/admin/export.css', array(), WOO_WALLET_PLUGIN_VERSION );
			// Add RTL support.
			wp_style_add_data( 'terawallet-exporter-style', 'rtl', 'replace' );
			// register exporter scripts.
			wp_register_script( 'terawallet-exporter-script', woo_wallet()->plugin_url() . '/build/admin/export.js', array( 'jquery' ), WOO_WALLET_PLUGIN_VERSION, true );
			wp_localize_script(
				'terawallet-exporter-script',
				'terawallet_export_params',
				array(
					'i18n'                => array(
						'inputTooShort' => __( 'Please enter 3 or more characters', 'woo-wallet' ),
						'no_resualt'    => __( 'No results found', 'woo-wallet' ),
						'searching'     => __( 'Searching…', 'woo-wallet' ),
					),
					'export_nonce'        => wp_create_nonce( 'terawallet-exporter-script' ),
					'search_user_nonce'   => wp_create_nonce( 'terawallet-export-search-user' ),
					'export_url'          => '',
					'export_button_title' => __( 'Export', 'woo-wallet' ),
				)
			);

			wp_register_script( 'terawallet_admin', woo_wallet()->plugin_url() . '/build/admin/main.js', array( 'jquery' ), WOO_WALLET_PLUGIN_VERSION, true );
			wp_localize_script(
				'terawallet_admin',
				'terawallet_admin_params',
				apply_filters(
					'terawallet_admin_js_params',
					array(
						'ajax_url'          => admin_url( 'admin-ajax.php' ),
						'export_url'        => add_query_arg( array( 'page' => 'terawallet-exporter' ), admin_url( 'admin.php' ) ),
						'export_title'      => __( 'Export', 'woo-wallet' ),
						'lock_unlock_nonce' => wp_create_nonce( 'lock-unlock-nonce' ),
					)
				)
			);

			if ( in_array( $screen_id, array( 'admin_page_terawallet-exporter' ), true ) ) {
				wp_enqueue_style( 'select2' );
				wp_enqueue_style( 'terawallet-exporter-style' );
			}

			// Wallet Dashboard (Reports) assets — only on the top-level screen.
			if ( woo_wallet_get_screen_id( 'woo-wallet', '' ) === $screen_id ) {
				$reports_asset_path = WOO_WALLET_ABSPATH . 'build/admin/reports.asset.php';
				$reports_asset      = file_exists( $reports_asset_path )
					? include $reports_asset_path
					: array(
						'dependencies' => array(),
						'version'      => WOO_WALLET_PLUGIN_VERSION,
					);

				wp_enqueue_style( 'woo_wallet_reports', woo_wallet()->plugin_url() . '/build/admin/reports.css', array(), $reports_asset['version'] );
				wp_style_add_data( 'woo_wallet_reports', 'rtl', 'replace' );
				wp_enqueue_script( 'woo_wallet_reports', woo_wallet()->plugin_url() . '/build/admin/reports.js', $reports_asset['dependencies'], $reports_asset['version'], true );
				wp_localize_script(
					'woo_wallet_reports',
					'wooWalletReports',
					array(
						'restUrl' => esc_url_raw( rest_url( 'terawallet/v1/admin/reports/summary' ) ),
						'nonce'   => wp_create_nonce( 'wp_rest' ),
						// reports.js builds the count-up figures with these and assigns
						// them via textContent, so both the symbol and the format must
						// be plain text. WooCommerce returns HTML for each: symbols are
						// entities (₹ is "&#8377;") and the "…_space" currency positions
						// use "&nbsp;" as the separator — left encoded, they render as
						// literal "&nbsp;"/"&#8377;" instead of the character.
						'price'   => array(
							'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
							'decimals' => wc_get_price_decimals(),
							'decimal'  => wc_get_price_decimal_separator(),
							'thousand' => wc_get_price_thousand_separator(),
							'format'   => html_entity_decode( get_woocommerce_price_format(), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
						),
						'i18n'    => array(
							'justNow' => __( 'just now', 'woo-wallet' ),
						),
					)
				);
			}

			wp_enqueue_script( 'terawallet_admin' );
		}

		/**
		 * Display user wallet details page
		 */
		public function wallet_page() {
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Users wallet details', 'woo-wallet' ); ?></h2>
				<?php do_action( 'woo_wallet_admin_page_header' ); ?>
				<?php settings_errors(); ?>
				<?php do_action( 'woo_wallet_before_balance_details_table' ); ?>
				<?php $this->balance_details_table->views(); ?>
				<form id="posts-filter" method="post">
					<?php $this->balance_details_table->search_box( __( 'Search Users', 'woo-wallet' ), 'search_id' ); ?>
					<?php $this->balance_details_table->display(); ?>
				</form>
				<script type="text/javascript">
				jQuery(function ($) {
					$('#search-submit').on('click', function (event){
						event.preventDefault();
						var search = $('#search_id-search-input').val();
						var url = new URL(window.location.href); 
						url.searchParams.set('s', search);
						window.location.href = url;
					});
				});
				</script>
				<div id="ajax-response"></div>
				<br class="clear"/>
			</div>
			<?php
		}

		/**
		 * Admin add wallet balance form
		 */
		public function add_balance_to_user_wallet() {
			$user_id       = filter_input( INPUT_GET, 'user_id' );
			$currency      = apply_filters( 'woo_wallet_user_currency', '', $user_id );
			$user          = new WP_User( $user_id );
			$base_currency = class_exists( 'Woo_Wallet_Currency_Manager' )
				? Woo_Wallet_Currency_Manager::instance()->get_base_currency()
				: strtoupper( (string) get_option( 'woocommerce_currency', 'USD' ) );
			?>
			<div class="wrap">
				<?php settings_errors(); ?>
				<h2><?php /* translators: user display name and email */ printf( __( 'Adjust Balance: %1$s (%2$s)', 'woo-wallet' ), $user->display_name, $user->user_email ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a style="text-decoration: none;" href="<?php echo add_query_arg( array( 'page' => 'woo-wallet-users' ), admin_url( 'admin.php' ) ); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
				<p>
					<?php
					esc_html_e( 'Current wallet balance: ', 'woo-wallet' );
					echo woo_wallet()->wallet->get_wallet_balance( $user_id, 'view', $base_currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					?>
				</p>
				<form id="posts-filter" method="post">
					<table class="form-table">
						<tbody>
							<tr>
								<th scope="row"><label for="balance_amount"><?php esc_html_e( 'Amount', 'woo-wallet' ) . ' ( ' . get_woocommerce_currency_symbol( $currency ) . ' )'; ?></label></th>
								<td>
									<input type="number" step="any" name="balance_amount" class="regular-text" />
									<p class="description"><?php esc_html_e( 'Enter Amount', 'woo-wallet' ); ?></p>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="payment_type"><?php esc_html_e( 'Type', 'woo-wallet' ); ?></label></th>
								<td>
									<?php
									$payment_types = apply_filters(
										'woo_wallet_adjust_balance_payment_type',
										array(
											'credit' => __( 'Credit', 'woo-wallet' ),
											'debit'  => __(
												'Debit',
												'woo-wallet'
											),
										)
									);
									?>
									<select class="regular-text" name="payment_type" id="payment_type">
										<?php foreach ( $payment_types as $key => $value ) : ?>
										<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></option>
										<?php endforeach; ?>
									</select>
									<p class="description"><?php esc_html_e( 'Select payment type', 'woo-wallet' ); ?></p>
								</td>
							</tr>
							<?php do_action( 'woo_wallet_after_payment_type_field' ); ?>
							<tr>
								<th scope="row"><label for="payment_description"><?php esc_html_e( 'Description', 'woo-wallet' ); ?></label></th>
								<td>
									<textarea name="payment_description" class="regular-text"></textarea>
									<p class="description"><?php esc_html_e( 'Enter Description', 'woo-wallet' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>" />
					<?php wp_nonce_field( 'woo-wallet-admin-adjust-balance', 'woo-wallet-admin-adjust-balance' ); ?>
					<?php submit_button(); ?>
				</form>
				<div id="ajax-response"></div>
				<br class="clear"/>
			</div>
			<?php
		}

		/**
		 * Display transaction details page
		 */
		public function transaction_details_page() {
			$user_id       = filter_input( INPUT_GET, 'user_id' );
			$base_currency = class_exists( 'Woo_Wallet_Currency_Manager' )
				? Woo_Wallet_Currency_Manager::instance()->get_base_currency()
				: strtoupper( (string) get_option( 'woocommerce_currency', 'USD' ) );
			?>
			<div class="wrap">
				<h2><?php esc_html_e( 'Transaction details', 'woo-wallet' ); ?> <a style="text-decoration: none;" href="<?php echo esc_url( add_query_arg( array( 'page' => 'woo-wallet-users' ), admin_url( 'admin.php' ) ) ); ?>"><span class="dashicons dashicons-editor-break" style="vertical-align: middle;"></span></a></h2>
				<?php do_action( 'woo_wallet_admin_page_header' ); ?>
				<p>
				<?php
				esc_html_e( 'Current wallet balance: ', 'woo-wallet' );
				echo woo_wallet()->wallet->get_wallet_balance( $user_id, 'view', $base_currency ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
				</p>
				<?php do_action( 'before_woo_wallet_transaction_details_page', $user_id ); ?>
				<form id="posts-filter" method="get">
					<?php $this->transaction_details_table->display(); ?>
				</form>
				<div id="ajax-response"></div>
				<br class="clear"/>
			</div>
			<?php
		}

		/**
		 * Wallet details page initialization
		 */
		public function add_woo_wallet_details() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Number of items per page:',
				'default' => 15,
				'option'  => 'users_per_page',
			);
			add_screen_option( $option, $args );
			include_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-balance-details.php';
			$this->balance_details_table = new Woo_Wallet_Balance_Details();
			$this->balance_details_table->prepare_items();
		}

		/**
		 * Handel admin add wallet balance
		 */
		public function handle_wallet_balance_adjustment() {
			if ( isset( $_POST['woo-wallet-admin-adjust-balance'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woo-wallet-admin-adjust-balance'] ) ), 'woo-wallet-admin-adjust-balance' ) ) {
				$transaction_id = null;
				$user_id        = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
				$amount         = isset( $_POST['balance_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['balance_amount'] ) ) : 0;
				$payment_type   = isset( $_POST['payment_type'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_type'] ) ) : '';
				$description    = isset( $_POST['payment_description'] ) ? wp_kses_post( trim( wp_unslash( $_POST['payment_description'] ) ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$response       = array(
					'type'    => 'success',
					'message' => '',
				);
				$user           = new WP_User( $user_id );
				if ( ! $user ) {
					$response = array(
						'type'    => 'error',
						'message' => __( 'Invalid user', 'woo-wallet' ),
					);
				} elseif ( is_null( $amount ) || empty( $amount ) ) {
					$response = array(
						'type'    => 'error',
						'message' => __( 'Please enter amount', 'woo-wallet' ),
					);
				} else {
					$amount  = apply_filters( 'woo_wallet_addjust_balance_amount', number_format( $amount, wc_get_price_decimals(), '.', '' ), $user_id );
					$balance = woo_wallet()->wallet->get_wallet_balance( $user_id, 'edit' );
					if ( 'debit' === $payment_type && apply_filters( 'woo_wallet_disallow_negative_transaction', ( $balance <= 0 || $amount > $balance ), $amount, $balance ) ) {
						$response = array(
							'type'    => 'error',
							/* translators: 1: User login. */
							'message' => sprintf( __( '%s has insufficient balance for debit.', 'woo-wallet' ), $user->user_login ),
						);
					} elseif ( 'debit' === $payment_type ) {
						$transaction_id = woo_wallet()->wallet->debit( $user_id, $amount, $description );
						if ( $transaction_id ) {
							do_action( 'woo_wallet_admin_adjust_balance', $transaction_id );
							$response = array(
								'type'    => 'success',
								'message' => sprintf(
									/* translators: 1: amount name, 2: username, 3: transaction details url. */
									__( '%1$s has been debited from %2$s wallet account. <a href="%3$s">View all transactions&rarr;</a>', 'woo-wallet' ),
									wc_price( $amount, woo_wallet_wc_price_args( $user_id ) ),
									$user->user_login,
									add_query_arg(
										array(
											'page'    => 'woo-wallet-transactions',
											'user_id' => $user_id,
										),
										admin_url( 'admin.php' )
									)
								),
							);
						} else {
							$response = array(
								'type'    => 'error',
								'message' => __( 'There may be some issue with database connection. Please deactivate TeraWallet plugin and activate again.', 'woo-wallet' ),
							);
						}
					} elseif ( 'credit' === $payment_type ) {
						$transaction_id = woo_wallet()->wallet->credit( $user_id, $amount, $description );
						if ( $transaction_id ) {
							do_action( 'woo_wallet_admin_adjust_balance', $transaction_id );
							$response = array(
								'type'    => 'success',
								'message' => sprintf(
									/* translators: 1: amount name, 2: username, 3: transaction details url. */
									__( '%1$s has been credited to %2$s wallet account. <a href="%3$s">View all transactions&rarr;</a>', 'woo-wallet' ),
									wc_price( $amount, woo_wallet_wc_price_args( $user_id ) ),
									$user->user_login,
									add_query_arg(
										array(
											'page'    => 'woo-wallet-transactions',
											'user_id' => $user_id,
										),
										admin_url( 'admin.php' )
									)
								),
							);
						} else {
							$response = array(
								'type'    => 'error',
								'message' => __( 'There may be some issue with database connection. Please deactivate TeraWallet plugin and activate again.', 'woo-wallet' ),
							);
						}
					}
				}
				add_settings_error( '', 'terawallet', $response['message'], $response['type'] );
			}
		}

		/**
		 * Transaction details page initialization
		 */
		public function add_woo_wallet_transaction_details_option() {
			$option = 'per_page';
			$args   = array(
				'label'   => 'Number of items per page:',
				'default' => 10,
				'option'  => 'transactions_per_page',
			);
			add_screen_option( $option, $args );
			include_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-transaction-details.php';
			$this->transaction_details_table = new Woo_Wallet_Transaction_Details();
			$this->transaction_details_table->prepare_items();
		}
		/**
		 * Set Wallet page screen ID.
		 *
		 * @param string $screen_option screen_option.
		 * @param string $option option.
		 * @param string $value value.
		 * @return string
		 */
		public function set_wallet_screen_options( $screen_option, $option, $value ) {
			if ( 'transactions_per_page' === $option ) {
				$screen_option = $value;
			}
			return $screen_option;
		}

		/**
		 * Add wallet cashback tab to product page
		 *
		 * @param array $tabs tab.
		 */
		public function woocommerce_product_data_tabs( $tabs ) {
			$tabs['wallet_cashback'] = array(
				'label'    => __( 'Cashback', 'woo-wallet' ),
				'target'   => 'wallet_cashback_product_data',
				'class'    => array( 'hide_if_variable' ),
				'priority' => 80,
			);
			return $tabs;
		}

		/**
		 * WooCommerce product tab content
		 *
		 * @global object $post
		 */
		public function woocommerce_product_data_panels() {
			global $post;
			?>
			<div id="wallet_cashback_product_data" class="panel woocommerce_options_panel">
				<?php
				woocommerce_wp_select(
					array(
						'id'          => 'wcwp_cashback_type',
						'label'       => __( 'Cashback type', 'woo-wallet' ),
						'description' => __( 'Select cashback type percentage or fixed', 'woo-wallet' ),
						'options'     => array(
							'percent' => __( 'Percentage', 'woo-wallet' ),
							'fixed'   => __( 'Fixed', 'woo-wallet' ),
						),
						'value'       => get_post_meta( $post->ID, '_cashback_type', true ),
					)
				);
				woocommerce_wp_text_input(
					array(
						'id'                => 'wcwp_cashback_amount',
						'type'              => 'number',
						'data_type'         => 'decimal',
						'custom_attributes' => array( 'step' => '0.01' ),
						'label'             => __( 'Cashback Amount', 'woo-wallet' ),
						'description'       => __( 'Enter cashback amount', 'woo-wallet' ),
						'value'             => get_post_meta( $post->ID, '_cashback_amount', true ),
					)
				);
				do_action( 'after_wallet_cashback_product_data' );
				?>
			</div>
			<?php
		}

		/**
		 * Save post meta
		 *
		 * @param int $post_ID Post ID.
		 */
		public function save_post_product( $post_ID ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( isset( $_POST['wcwp_cashback_type'] ) ) {
				update_post_meta( $post_ID, '_cashback_type', sanitize_text_field( wp_unslash( $_POST['wcwp_cashback_type'] ) ) );
			}
			if ( isset( $_POST['wcwp_cashback_amount'] ) ) {
				update_post_meta( $post_ID, '_cashback_amount', sanitize_text_field( wp_unslash( $_POST['wcwp_cashback_amount'] ) ) );
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}
		/**
		 * Add cashback option for variable product.
		 *
		 * @param int    $loop loop.
		 * @param array  $variation_data variation_data.
		 * @param object $variation variation.
		 */
		public function woocommerce_variation_options_pricing( $loop, $variation_data, $variation ) {
			woocommerce_wp_select(
				array(
					'id'            => 'variable_cashback_type[' . $loop . ']',
					'name'          => 'variable_cashback_type[' . $loop . ']',
					'label'         => __( 'Cashback type', 'woo-wallet' ),
					'options'       => array(
						'percent' => __( 'Percentage', 'woo-wallet' ),
						'fixed'   => __( 'Fixed', 'woo-wallet' ),
					),
					'value'         => get_post_meta( $variation->ID, '_cashback_type', true ),
					'wrapper_class' => 'form-row form-row-first',
				)
			);
			woocommerce_wp_text_input(
				array(
					'id'                => 'variable_cashback_amount[' . $loop . ']',
					'name'              => 'variable_cashback_amount[' . $loop . ']',
					'type'              => 'number',
					'data_type'         => 'decimal',
					'custom_attributes' => array(
						'step' => '1',
						'min'  => '0',
					),
					'label'             => __( 'Cashback Amount', 'woo-wallet' ),
					'value'             => get_post_meta( $variation->ID, '_cashback_amount', true ),
					'wrapper_class'     => 'form-row form-row-last',
				)
			);
		}
		/**
		 * Save cashback option for variable product.
		 *
		 * @param int $variation_id variation_id.
		 * @param int $i counter.
		 */
		public function woocommerce_save_product_variation( $variation_id, $i ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			$cashback_type   = isset( $_POST['variable_cashback_type'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['variable_cashback_type'][ $i ] ) ) : null;
			$cashback_amount = isset( $_POST['variable_cashback_amount'][ $i ] ) ? sanitize_text_field( wp_unslash( $_POST['variable_cashback_amount'][ $i ] ) ) : null;
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			update_post_meta( $variation_id, '_cashback_type', esc_attr( $cashback_type ) );
			update_post_meta( $variation_id, '_cashback_amount', esc_attr( $cashback_amount ) );
		}

		/**
		 * Display partial payment and cashback amount in order page
		 *
		 * @param type $order_id order_id.
		 */
		public function add_wallet_payment_amount( $order_id ) {
			$order                 = wc_get_order( $order_id );
			$total_cashback_amount = get_total_order_cashback_amount( $order_id );
			if ( $total_cashback_amount ) {
				?>
				<tr>
					<td class="label"><?php esc_html_e( 'Cashback', 'woo-wallet' ); ?>:</td>
					<td width="1%"></td>
					<td class="via-wallet">
						<?php echo wc_price( $total_cashback_amount, woo_wallet_wc_price_args( $order->get_customer_id() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
					</td>
				</tr>
				<?php
			}
		}

		/**
		 * Add setting to convert coupon to cashback.
		 *
		 * @since 1.0.6
		 */
		public function add_coupon_option_for_cashback() {
			woocommerce_wp_checkbox(
				array(
					'id'          => '_is_coupon_cashback',
					'label'       => __( 'Apply as cashback', 'woo-wallet' ),
					'description' => __( 'Check this box if the coupon should apply as cashback.', 'woo-wallet' ),
				)
			);
		}

		/**
		 * Save coupon data
		 *
		 * @param int $post_id post_id.
		 * @since 1.0.6
		 */
		public function save_coupon_data( $post_id ) {
			$_is_coupon_cashback = isset( $_POST['_is_coupon_cashback'] ) ? 'yes' : 'no'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_post_meta( $post_id, '_is_coupon_cashback', $_is_coupon_cashback );
		}

		/**
		 * Add review link
		 *
		 * @param string $footer_text footer_text.
		 * @return string
		 */
		public function admin_footer_text( $footer_text ) {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				return $footer_text;
			}
			$current_screen = get_current_screen();
			if ( isset( $current_screen->id ) && in_array( $current_screen->id, $this->wallet_own_screen_ids(), true ) ) {
				if ( ! get_option( 'woocommerce_wallet_admin_footer_text_rated' ) ) {
					$footer_text = sprintf(
						/* translators: Plugin name */
						__( 'If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', 'woo-wallet' ),
						sprintf( '<strong>%s</strong>', esc_html__( 'TeraWallet', 'woo-wallet' ) ),
						'<a href="https://wordpress.org/support/plugin/woo-wallet/reviews?rate=5#new-post" target="_blank" class="wc-rating-link" data-rated="' . esc_attr__( 'Thanks :)', 'woo-wallet' ) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
					);
					$script = "
					jQuery( 'a.wc-rating-link' ).click( function() {
						jQuery.post( '" . WC()->ajax_url() . "', { action: 'woocommerce_wallet_rated' } );
						jQuery( this ).parent().text( jQuery( this ).data( 'rated' ) );
					});
				";
					wp_add_inline_script( 'wc-admin-footer-rating', $script );
				} else {
					$footer_text = __( 'Thank you for using TeraWallet.', 'woo-wallet' );
				}
			}
			return $footer_text;
		}

		/**
		 * Wallet endpoins settings
		 *
		 * @param array $settings settings.
		 * @return array
		 */
		public function add_woocommerce_account_endpoint_settings( $settings ) {
			$settings_fields = apply_filters(
				'woo_wallet_endpoint_settings_fields',
				array(
					array(
						'title'    => __( 'My Wallet', 'woo-wallet' ),
						'desc'     => __( 'Endpoint for the "My account &rarr; My Wallet" page.', 'woo-wallet' ),
						'id'       => 'woocommerce_woo_wallet_endpoint',
						'type'     => 'text',
						'default'  => 'my-wallet',
						'desc_tip' => true,
					),
				)
			);

			$walletendpoint_settings = array(
				array(
					'title' => __( 'Wallet endpoints', 'woo-wallet' ),
					'type'  => 'title',
					'desc'  => __( 'Endpoints are appended to your page URLs to handle specific actions on the accounts pages. They should be unique and can be left blank to disable the endpoint.', 'woo-wallet' ),
					'id'    => 'wallet_endpoint_options',
				),
			);
			foreach ( $settings_fields as $settings_field ) {
				$walletendpoint_settings[] = $settings_field;
			}
			$walletendpoint_settings[] = array(
				'type' => 'sectionend',
				'id'   => 'wallet_endpoint_options',
			);

			return array_merge( $settings, $walletendpoint_settings );
		}

		/**
		 * Display product category wise cashback field.
		 */
		public function add_product_cat_cashback_field() {
			?>
			<div class="form-field term-display-type-wrap">
				<label for="woo_product_cat_cashback_type"><?php esc_html_e( 'Cashback type', 'woo-wallet' ); ?></label>
				<select name="woo_product_cat_cashback_type" id="woo_product_cat_cashback_type">
					<option value="percent"><?php esc_html_e( 'Percentage', 'woo-wallet' ); ?></option>
					<option value="fixed"><?php esc_html_e( 'Fixed', 'woo-wallet' ); ?></option>
				</select>
			</div>
			<div class="form-field term-display-type-wrap">
				<label for="woo_product_cat_cashback_amount"><?php esc_html_e( 'Cashback Amount', 'woo-wallet' ); ?></label>
				<input type="number" step="0.01" name="woo_product_cat_cashback_amount" id="woo_product_cat_cashback_amount" value="" placeholder="">
			</div>
			<?php
		}

		/**
		 * Display product category wise cashback field.
		 *
		 * @param object $term term.
		 */
		public function edit_product_cat_cashback_field( $term ) {
			$cashback_type   = get_term_meta( $term->term_id, '_woo_cashback_type', true );
			$cashback_amount = get_term_meta( $term->term_id, '_woo_cashback_amount', true );
			?>
			<tr class="form-field">
				<th scope="row" valign="top"><?php esc_html_e( 'Cashback type', 'woo-wallet' ); ?></th>
				<td>
					<select name="woo_product_cat_cashback_type" id="woo_product_cat_cashback_type">
						<option value="percent" <?php selected( $cashback_type, 'percent' ); ?>><?php esc_html_e( 'Percentage', 'woo-wallet' ); ?></option>
						<option value="fixed" <?php selected( $cashback_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'woo-wallet' ); ?></option>
					</select>
				</td>
			</tr>
			<tr class="form-field">
				<th scope="row" valign="top"><?php esc_html_e( 'Cashback Amount', 'woo-wallet' ); ?></th>
				<td><input type="number" step="0.01" name="woo_product_cat_cashback_amount" id="woo_product_cat_cashback_amount" value="<?php echo esc_attr( $cashback_amount ); ?>" placeholder=""></td>
			</tr>
			<?php
		}

		/**
		 * Save cashback field on category save.
		 *
		 * @param int    $term_id term_id.
		 * @param int    $tt_id tt_id.
		 * @param string $taxonomy taxonomy.
		 */
		public function save_product_cashback_field( $term_id, $tt_id = '', $taxonomy = '' ) {
			// phpcs:disable WordPress.Security.NonceVerification.Missing
			if ( 'product_cat' === $taxonomy ) {
				if ( isset( $_POST['woo_product_cat_cashback_type'] ) ) {
					update_term_meta( $term_id, '_woo_cashback_type', sanitize_text_field( wp_unslash( $_POST['woo_product_cat_cashback_type'] ) ) );
				}
				if ( isset( $_POST['woo_product_cat_cashback_amount'] ) ) {
					update_term_meta( $term_id, '_woo_cashback_amount', sanitize_text_field( wp_unslash( $_POST['woo_product_cat_cashback_amount'] ) ) );
				}
			}
			// phpcs:enable WordPress.Security.NonceVerification.Missing
		}

		/**
		 * Adds wallet endpoint to WooCommerce endpoints menu option.
		 *
		 * @param array $endpoints endpoints.
		 * @return array
		 */
		public function woocommerce_custom_nav_menu_items( $endpoints ) {
			$endpoints[ get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ) ] = __( 'My Wallet', 'woo-wallet' );
			return $endpoints;
		}

		/**
		 * Add column
		 *
		 * @param  array $columns columns.
		 * @return array
		 */
		public function manage_users_columns( $columns ) {
			if ( current_user_can( get_wallet_user_capability() ) ) {
				$columns['current_wallet_balance'] = __( 'Wallet Balance', 'woo-wallet' );
			}
			return $columns;
		}

		/**
		 * Column value
		 *
		 * @param  string $value value.
		 * @param  string $column_name column_name.
		 * @param  int    $user_id user_id.
		 * @return string
		 */
		public function manage_users_custom_column( $value, $column_name, $user_id ) {
			if ( 'current_wallet_balance' === $column_name ) {
				return sprintf( '<a href="%s" title="%s">%s</a>', admin_url( 'admin.php?page=woo-wallet-transactions&user_id=' . $user_id ), __( 'View details', 'woo-wallet' ), woo_wallet()->wallet->get_wallet_balance( $user_id ) );
			}
			return $value;
		}
		/**
		 * Add TeraWallet screen ids to WooCommerce
		 *
		 * @param array $screen_ids screen_ids.
		 * @return array
		 */
		public function woocommerce_screen_ids_callback( $screen_ids ) {
			// woo-wallet-actions submenu removed; Actions are part of the unified Settings page.
			return $screen_ids;
		}
		/**
		 * Add refund button to WooCommerce order page.
		 *
		 * @param int    $item_id item_id.
		 * @param Object $item item.
		 */
		public function woocommerce_after_order_fee_item_name_callback( $item_id, $item ) {
			if ( ! is_partial_payment_order_item( $item_id, $item ) ) {
				return;
			}
			$order_id = wc_get_order_id_by_order_item_id( $item_id );
			$order    = wc_get_order( $order_id );
			if ( $order->get_meta( '_woo_wallet_partial_payment_refunded' ) ) {
				echo '<small class="refunded">' . esc_html__( 'Refunded', 'woo-wallet' ) . '</small>';
			} else {
				echo '<button type="button" class="button refund-partial-payment">' . esc_html__( 'Refund', 'woo-wallet' ) . '</button>';
			}
		}
		/**
		 * Admin new order add cashback.
		 *
		 * @param int $order_id order_id.
		 */
		public function woocommerce_new_order( $order_id ) {
			woo_wallet()->cashback->calculate_cashback( false, $order_id, true );
		}

		/**
		 * Add order action for recalculate order cashback
		 *
		 * @param array $order_actions order_actions.
		 * @return array
		 */
		public function woocommerce_order_actions( $order_actions ) {
			$order_actions['recalculate_order_cashback'] = __( 'Recalculate order cashback', 'woo-wallet' );
			return $order_actions;
		}
		/**
		 * Recalculate and adjust order cashback (R4).
		 *
		 * Replaces the previous direct `update_wallet_transaction(amount=...)`
		 * pattern with a compensating credit/debit row written via
		 * `Woo_Wallet_Wallet::adjust_cashback()`. The original cashback row is
		 * never mutated so the append-only ledger invariant is preserved.
		 *
		 * Short-circuits with an admin notice when the recomputed delta is zero
		 * (L4 fix) so a no-op recalculation does not silently rewrite to 0.
		 *
		 * @param WC_Order $order order.
		 *
		 * @since 1.6.1 Rewrote to use adjust_cashback() (R4).
		 */
		public function recalculate_order_cashback( $order ) {
			$cashback_statuses = apply_filters( 'wallet_cashback_order_status', woo_wallet()->settings_api->get_option( 'process_cashback_status', '_wallet_settings_credit', array( 'processing', 'completed' ) ) );
			if ( ! in_array( $order->get_status(), $cashback_statuses, true ) ) {
				return;
			}

			// Recompute expected cashback from the live order.
			$new_cashback = (float) woo_wallet()->cashback->calculate_cashback( false, $order->get_id(), true );

			// Sum the existing credited rows via the array-aware reader.
			$existing_cashback = (float) get_total_order_cashback_amount( $order->get_id() );

			if ( 0.0 === $existing_cashback ) {
				// No previous cashback exists — run the normal credit path instead.
				woo_wallet()->wallet->wallet_cashback( $order->get_id() );
				return;
			}

			$delta = $new_cashback - $existing_cashback;

			if ( abs( $delta ) < 0.001 ) {
				// No change — surface admin notice instead of writing a no-op row.
				$order->add_order_note( __( 'Cashback recalculation: amount unchanged, no adjustment row written.', 'woo-wallet' ) );
				return;
			}

			$transaction_id = woo_wallet()->wallet->adjust_cashback( $order, $delta, 'manual_recalculate' );
			if ( $transaction_id ) {
				/* translators: 1: formatted amount (positive or negative) */
				$order->add_order_note( sprintf( __( 'Cashback adjusted by %s via manual recalculation.', 'woo-wallet' ), wc_price( $delta, woo_wallet_wc_price_args( $order->get_customer_id() ) ) ) );
			}
		}

		/**
		 * Display one-time dismissible admin notices seeded by the 1.6.1 migration.
		 *
		 * Two notices are seeded when an existing site upgrades:
		 *   tw_161_cashback_refund_notice        — prompts to enable refund clawback.
		 *   tw_161_coupon_cashback_totals_notice  — explains the coupon-cashback totals change.
		 *
		 * Both are dismissed via AJAX (woowallet_dismiss_161_notice action).
		 *
		 * @since 1.6.1
		 */
		public function show_161_notices() {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				return;
			}

			$settings_url = admin_url( 'admin.php?page=woo-wallet-settings#_wallet_settings_credit' );

			if ( get_transient( 'tw_161_cashback_refund_notice' ) ) {
				$nonce = wp_create_nonce( 'woowallet_dismiss_notice' );
				?>
				<div class="notice notice-info is-dismissible" id="tw-161-refund-notice">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: settings page link open, 2: settings page link close */
								__( '<strong>TeraWallet 1.6.1:</strong> Cashback can now be clawed back when an order is refunded. This is <strong>off by default</strong> — %1$senable it in Settings → Wallet Credit → Refund Clawback%2$s if you want it.', 'woo-wallet' ),
								'<a href="' . esc_url( $settings_url ) . '">',
								'</a>'
							)
						);
						?>
					</p>
					<button type="button" class="notice-dismiss tw-161-dismiss" data-notice="cashback_refund" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'woo-wallet' ); ?></span>
					</button>
				</div>
				<script>
				jQuery(document).ready(function($){
					$(document).on('click', '.tw-161-dismiss', function(){
						var notice = $(this).data('notice');
						var nonce  = $(this).data('nonce');
						$(this).closest('.notice').fadeOut();
						wp.ajax.send('woowallet_dismiss_161_notice', { data: { notice: notice, nonce: nonce } });
					});
				});
				</script>
				<?php
			}

			if ( get_transient( 'tw_161_coupon_cashback_totals_notice' ) ) {
				$nonce = wp_create_nonce( 'woowallet_dismiss_notice' );
				?>
				<div class="notice notice-info is-dismissible" id="tw-161-coupon-notice">
					<p>
						<?php
						echo wp_kses_post(
							sprintf(
								/* translators: 1: settings page link open, 2: settings page link close */
								__( '<strong>TeraWallet 1.6.1:</strong> Coupon cashback is now recomputed from the live order at credit time rather than trusting the checkout-frozen meta. For upgraded sites the legacy discount_total/total mutation is preserved via an internal flag. %1$sReview your cashback settings.%2$s', 'woo-wallet' ),
								'<a href="' . esc_url( $settings_url ) . '">',
								'</a>'
							)
						);
						?>
					</p>
					<button type="button" class="notice-dismiss tw-161-dismiss" data-notice="coupon_cashback_totals" data-nonce="<?php echo esc_attr( $nonce ); ?>">
						<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'woo-wallet' ); ?></span>
					</button>
				</div>
				<?php
			}
		}

		/**
		 * AJAX handler to dismiss a 1.6.1 upgrade notice.
		 *
		 * @since 1.6.1
		 */
		public function dismiss_161_notice() {
			check_ajax_referer( 'woowallet_dismiss_notice', 'nonce' );
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( -1 );
			}

			$notice = isset( $_POST['notice'] ) ? sanitize_key( wp_unslash( $_POST['notice'] ) ) : '';

			if ( 'cashback_refund' === $notice ) {
				delete_transient( 'tw_161_cashback_refund_notice' );
				set_transient( 'tw_161_cashback_refund_notice_dismissed', '1', 0 );
			} elseif ( 'coupon_cashback_totals' === $notice ) {
				delete_transient( 'tw_161_coupon_cashback_totals_notice' );
				set_transient( 'tw_161_coupon_cashback_totals_notice_dismissed', '1', 0 );
			}

			wp_send_json_success();
		}
		/**
		 * Render any errors stashed by the Delete Logs bulk action on the
		 * TeraWallet admin screen, then clear them.
		 *
		 * @since 1.6.1
		 */
		public function show_purge_errors() {
			$screen = get_current_screen();
			if ( ! $screen || woo_wallet_get_screen_id( 'woo-wallet', '' ) !== $screen->id ) {
				return;
			}
			$transient_key = 'woo_wallet_purge_error_' . get_current_user_id();
			$errors        = get_transient( $transient_key );
			if ( ! $errors || ! is_array( $errors ) ) {
				return;
			}
			delete_transient( $transient_key );
			echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'TeraWallet: some users could not be purged.', 'woo-wallet' ) . '</strong></p><ul style="list-style:disc;margin-left:20px;">';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}
		/**
		 * Render the Pro upgrade banner.
		 *
		 * Hooked to `woo_wallet_admin_page_header`, which only TeraWallet's own
		 * admin pages fire — so this is page content on our own screens, not a
		 * dashboard notice. That is what lets it be permanent: WordPress.org
		 * guideline 11 requires *site-wide* notices to be dismissible, and this
		 * is never rendered outside our own pages. It disappears on its own once
		 * Pro is installed, which is the only exit condition it needs. The Go Pro
		 * page does not fire the hook — it is itself the upsell.
		 *
		 * @return void
		 */
		public function show_promotions() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( woo_wallet_is_pro_active() ) {
				return;
			}

			$stats   = $this->promo_store_figures();
			$pro_url = add_query_arg( array( 'page' => Woo_Wallet_Go_Pro_Page::MENU_SLUG ), admin_url( 'admin.php' ) );
			?>
			<aside class="tw-promo" role="complementary" aria-labelledby="tw-promo-title">
				<div class="tw-promo__pitch">
					<p class="tw-promo__eyebrow"><?php esc_html_e( 'TeraWallet Pro', 'woo-wallet' ); ?></p>

					<?php if ( $stats ) : ?>
						<div class="tw-promo__statement">
							<span class="tw-promo__figure"><?php echo esc_html( $stats['amount'] ); ?></span>
							<span class="tw-promo__caption">
								<?php
								printf(
									/* translators: %s: number of customers holding a positive wallet balance. */
									esc_html( _n( 'Outstanding wallet credit · %s customer', 'Outstanding wallet credit · %s customers', $stats['wallets'], 'woo-wallet' ) ),
									esc_html( number_format_i18n( $stats['wallets'] ) )
								);
								?>
							</span>
						</div>
						<h2 class="tw-promo__title" id="tw-promo-title"><?php esc_html_e( 'Some of this will never be spent.', 'woo-wallet' ); ?></h2>
						<p class="tw-promo__sub"><?php esc_html_e( 'Pro measures how much, reclaims it on a schedule, and lets the rest leave your store as real payouts.', 'woo-wallet' ); ?></p>
					<?php else : ?>
						<h2 class="tw-promo__title" id="tw-promo-title"><?php esc_html_e( 'Everything the wallet needs once customers actually use it.', 'woo-wallet' ); ?></h2>
						<p class="tw-promo__sub"><?php esc_html_e( 'Money has to be able to leave the wallet, unspent credit has to be reclaimed, and someone will ask what the balance is really worth. Pro answers all three.', 'woo-wallet' ); ?></p>
					<?php endif; ?>
				</div>

				<ul class="tw-promo__ledger">
					<li>
						<span class="tw-promo__item"><?php esc_html_e( 'Withdrawals', 'woo-wallet' ); ?></span>
						<span class="tw-promo__note"><?php esc_html_e( 'Customers cash out via PayPal, Stripe, BACS, Razorpay, Cashfree or Paystack, through an approval queue you control.', 'woo-wallet' ); ?></span>
					</li>
					<li>
						<span class="tw-promo__item"><?php esc_html_e( 'Credit expiry', 'woo-wallet' ); ?></span>
						<span class="tw-promo__note"><?php esc_html_e( 'Oldest credit spends first, reminder emails go out before it lapses, and a daily run clears what has expired.', 'woo-wallet' ); ?></span>
					</li>
					<li>
						<span class="tw-promo__item"><?php esc_html_e( 'Breakage &amp; aging reports', 'woo-wallet' ); ?></span>
						<span class="tw-promo__note"><?php esc_html_e( 'Unlocks the five locked report slots on your Wallet Dashboard, including the number your accountant asks for.', 'woo-wallet' ); ?></span>
					</li>
					<li class="tw-promo__rest">
						<?php esc_html_e( 'Also: spend milestone and birthday bonuses, wallet coupons, bulk CSV import, AffiliateWP payouts.', 'woo-wallet' ); ?>
					</li>
				</ul>

				<div class="tw-promo__act">
					<p class="tw-promo__price">
						<span class="tw-promo__amount"><?php echo esc_html( Woo_Wallet_Go_Pro_Page::PRICE ); ?></span>
						<span class="tw-promo__term"><?php esc_html_e( 'per year, one site', 'woo-wallet' ); ?></span>
					</p>
					<a class="tw-promo__btn" href="<?php echo esc_url( $pro_url ); ?>">
						<?php esc_html_e( 'See what Pro adds', 'woo-wallet' ); ?>
					</a>
					<p class="tw-promo__reassure"><?php esc_html_e( '30-day money-back guarantee', 'woo-wallet' ); ?></p>
				</div>
			</aside>
			<style>
				.tw-promo {
					--tw-promo-ink: #16191d;
					--tw-promo-inset: #1e2329;
					--tw-promo-line: rgba(255, 255, 255, 0.10);
					--tw-promo-text: #f0f0f1;
					--tw-promo-muted: #a7aaad;
					--tw-promo-accent: #b183e0;
					--tw-promo-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;

					display: grid;
					grid-template-columns: minmax(0, 1.15fr) minmax(0, 1.1fr) minmax(0, 0.5fr);
					gap: 0;
					margin: 16px 0 24px;
					border: 1px solid var(--tw-promo-line);
					border-radius: 6px;
					background: var(--tw-promo-ink);
					color: var(--tw-promo-text);
					box-sizing: border-box;
					overflow: hidden;
				}
				.tw-promo * { box-sizing: border-box; }

				/*
				 * The Settings screen mounts a deliberately full-bleed React app
				 * inside a zero-padding flex `.wrap`, so the banner would sit hard
				 * against the admin menu. Give it back the inset the other screens
				 * inherit from their own `.wrap`.
				 */
				.woo-wallet-settings-page .tw-promo { margin: 16px 20px 24px; }

				.tw-promo__pitch { padding: 24px 26px; min-width: 0; }

				.tw-promo__eyebrow {
					margin: 0 0 18px;
					font-family: var(--tw-promo-mono);
					font-size: 11px;
					font-weight: 600;
					letter-spacing: 0.14em;
					text-transform: uppercase;
					color: var(--tw-promo-accent);
				}

				/*
				 * The signature: a balance-sheet line — the figure over a hairline
				 * rule, set in tabular numerals so it reads as a real ledger entry.
				 */
				.tw-promo__statement {
					display: block;
					padding-bottom: 12px;
					margin-bottom: 14px;
					border-bottom: 1px solid var(--tw-promo-line);
				}
				.tw-promo__figure {
					display: block;
					font-family: var(--tw-promo-mono);
					font-size: 34px;
					font-weight: 600;
					line-height: 1.1;
					letter-spacing: -0.02em;
					font-variant-numeric: tabular-nums;
					font-feature-settings: "tnum" 1;
					color: var(--tw-promo-text);
				}
				.tw-promo__caption {
					display: block;
					margin-top: 7px;
					font-family: var(--tw-promo-mono);
					font-size: 11px;
					letter-spacing: 0.05em;
					text-transform: uppercase;
					color: var(--tw-promo-muted);
				}

				.tw-promo__title {
					margin: 0 0 8px;
					padding: 0;
					font-size: 20px;
					font-weight: 600;
					line-height: 1.3;
					color: var(--tw-promo-text);
				}
				.tw-promo__sub {
					margin: 0;
					font-size: 13px;
					line-height: 1.6;
					color: var(--tw-promo-muted);
				}

				.tw-promo__ledger {
					margin: 0;
					padding: 24px 26px;
					list-style: none;
					border-left: 1px solid var(--tw-promo-line);
					min-width: 0;
				}
				.tw-promo__ledger li {
					margin: 0 0 13px;
					padding: 0 0 0 15px;
					position: relative;
					font-size: 13px;
					line-height: 1.55;
				}
				.tw-promo__ledger li::before {
					content: "";
					position: absolute;
					left: 0;
					top: 9px;
					width: 7px;
					height: 1px;
					background: var(--tw-promo-accent);
				}
				.tw-promo__ledger li:last-child { margin-bottom: 0; }
				.tw-promo__item {
					color: var(--tw-promo-text);
					font-weight: 600;
				}
				.tw-promo__note {
					color: var(--tw-promo-muted);
				}
				.tw-promo__note::before { content: " — "; }
				.tw-promo__rest {
					color: var(--tw-promo-muted);
					padding-top: 13px !important;
					border-top: 1px solid var(--tw-promo-line);
					font-size: 12px !important;
				}
				.tw-promo__rest::before { display: none; }

				.tw-promo__act {
					padding: 24px 26px;
					background: var(--tw-promo-inset);
					border-left: 1px solid var(--tw-promo-line);
					display: flex;
					flex-direction: column;
					align-items: flex-start;
					justify-content: center;
					min-width: 0;
				}
				.tw-promo__price {
					margin: 0 0 12px;
					display: flex;
					align-items: baseline;
					gap: 7px;
					flex-wrap: wrap;
				}
				.tw-promo__amount {
					font-family: var(--tw-promo-mono);
					font-size: 26px;
					font-weight: 600;
					letter-spacing: -0.02em;
					font-variant-numeric: tabular-nums;
					color: var(--tw-promo-text);
				}
				.tw-promo__term {
					font-size: 12px;
					color: var(--tw-promo-muted);
				}
				.tw-promo__btn {
					display: inline-block;
					padding: 9px 18px;
					border-radius: 3px;
					background: #7f54b3;
					color: #fff !important;
					font-size: 13px;
					font-weight: 600;
					text-decoration: none;
					transition: background 0.15s ease;
				}
				.tw-promo__btn:hover { background: #6b449b; color: #fff !important; }
				.tw-promo__btn:focus {
					background: #6b449b;
					color: #fff !important;
					outline: 2px solid var(--tw-promo-accent);
					outline-offset: 2px;
					box-shadow: none;
				}
				.tw-promo__reassure {
					margin: 11px 0 0;
					font-size: 11px;
					line-height: 1.5;
					color: var(--tw-promo-muted);
				}

				@media screen and (max-width: 1200px) {
					.tw-promo { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); }
					.tw-promo__act {
						grid-column: 1 / -1;
						border-left: 0;
						border-top: 1px solid var(--tw-promo-line);
						flex-direction: row;
						align-items: center;
						gap: 18px;
						flex-wrap: wrap;
					}
					.tw-promo__price { margin: 0; }
					.tw-promo__reassure { margin: 0; }
				}
				@media screen and (max-width: 782px) {
					.tw-promo { grid-template-columns: minmax(0, 1fr); }
					.tw-promo__ledger {
						border-left: 0;
						border-top: 1px solid var(--tw-promo-line);
					}
					.tw-promo__pitch,
					.tw-promo__ledger,
					.tw-promo__act { padding: 20px; }
					.tw-promo__figure { font-size: 30px; }
					.tw-promo__title { font-size: 18px; }
					.tw-promo__btn { width: 100%; text-align: center; }
				}
			</style>
			<?php
		}

		/**
		 * The store's own wallet figures, for the promo's opening line.
		 *
		 * Returns null unless the store carries enough wallet credit for the
		 * number to be an argument rather than an embarrassment — a fresh install
		 * told it is holding $0.00 across 0 wallets reads as a broken page. Shares
		 * its threshold filter with the Wallet Dashboard nudge and the Go Pro
		 * page, so a store tunes the figure once.
		 *
		 * `get_summary()` is transient-cached, so this costs nothing on repeat
		 * page loads.
		 *
		 * @since 1.6.12
		 * @return array{amount:string,wallets:int}|null
		 */
		private function promo_store_figures() {
			require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';

			$data      = new Woo_Wallet_Reports_Data();
			$summary   = $data->get_summary();
			$liability = isset( $summary['total_liability'] ) ? (float) $summary['total_liability'] : 0.0;
			$wallets   = isset( $summary['positive_wallets'] ) ? (int) $summary['positive_wallets'] : 0;

			/** This filter is documented in includes/admin/class-woo-wallet-reports.php */
			$threshold = (float) apply_filters( 'woo_wallet_pro_liability_nudge_threshold', 1000.0 );

			if ( $liability < $threshold || $wallets < 1 ) {
				return null;
			}

			return array(
				'amount'  => $data->format_amount( $liability ),
				'wallets' => $wallets,
			);
		}
	}

}
Woo_Wallet_Admin::instance();
