<?php
/**
 * Main wallet calss
 *
 * @package StandaloneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * Main wallet calss
 */
final class WooWallet {

	/**
	 * The single instance of the class.
	 *
	 * @var WooWallet
	 * @since 1.0.0
	 */
	protected static $_instance = null;

	/**
	 * Setting API instance
	 *
	 * @var Woo_Wallet_Settings_API
	 */
	public $settings_api = null;

	/**
	 * Wallet instance.
	 *
	 * @var Woo_Wallet_Wallet
	 */
	public $wallet = null;
	/**
	 * Cashback instance.
	 *
	 * @var Woo_Wallet_Cashback
	 */
	public $cashback = null;
	/**
	 * Wallet REST API
	 *
	 * @var WooWallet_API
	 */
	public $rest_api = null;

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
		if ( Woo_Wallet_Dependencies::is_woocommerce_active() ) {
			$this->includes();
			$this->init_hooks();
			do_action( 'woo_wallet_loaded' );
		} else {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
			deactivate_plugins( plugin_basename( WOO_WALLET_PLUGIN_FILE ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
		}
	}

	/**
	 * Check request
	 *
	 * @param string $type Type.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();
			case 'ajax':
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' );
		}
	}

	/**
	 * Load plugin files
	 */
	public function includes() {
		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-helper.php';
		include_once WOO_WALLET_ABSPATH . 'includes/helper/woo-wallet-util.php';
		include_once WOO_WALLET_ABSPATH . 'includes/helper/woo-wallet-update-functions.php';
		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-install.php';

		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings-api.php';
		$this->settings_api = new Woo_Wallet_Settings_API();

		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-wallet.php';
		$this->wallet = new Woo_Wallet_Wallet();

		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-cashback.php';
		$this->cashback = new Woo_Wallet_Cashback();

		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-widgets.php';

		if ( $this->is_request( 'admin' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/export/class-terawallet-csv-exporter.php';
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php';
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-extensions.php';
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-admin.php';
		}
		if ( $this->is_request( 'frontend' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-frontend.php';
		}
		if ( $this->is_request( 'ajax' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-ajax.php';
		}
	}

	/**
	 * Plugin url
	 *
	 * @return string path
	 */
	public function plugin_url() {
		return untrailingslashit( plugins_url( '/', WOO_WALLET_PLUGIN_FILE ) );
	}

	/**
	 * Plugin init
	 */
	private function init_hooks() {
		register_activation_hook( WOO_WALLET_PLUGIN_FILE, array( 'Woo_Wallet_Install', 'install' ) );
		register_deactivation_hook( WOO_WALLET_PLUGIN_FILE, array( $this, 'deactivate_plugin' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WOO_WALLET_PLUGIN_FILE ), array( $this, 'plugin_action_links' ) );
		add_action( 'init', array( $this, 'init' ), 5 );
		add_action( 'widgets_init', array( $this, 'woo_wallet_widget_init' ) );
		add_action( 'woocommerce_loaded', array( $this, 'woocommerce_loaded_callback' ) );
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
		// Registers WooCommerce Blocks integration.
		add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'add_woocommerce_block_support' ) );
		do_action( 'woo_wallet_init' );
	}

	/**
	 * Plugin init
	 */
	public function init() {
		$this->load_plugin_textdomain();
		include_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-payment-method.php';
		$this->add_marketplace_support();
		$this->add_multicurrency_support();
		add_filter( 'woocommerce_email_classes', array( $this, 'woocommerce_email_classes' ), 999 );
		add_filter( 'woocommerce_template_directory', array( $this, 'woocommerce_template_directory' ), 10, 2 );
		add_filter( 'woocommerce_payment_gateways', array( $this, 'load_gateway' ) );

		foreach ( apply_filters( 'wallet_credit_purchase_order_status', array( 'processing', 'completed' ) ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this->wallet, 'wallet_credit_purchase' ) );
		}

		add_action( 'woocommerce_checkout_order_processed', array( $this->wallet, 'woocommerce_order_processed' ), 99 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this->wallet, 'woocommerce_order_processed' ), 99 );

		foreach ( apply_filters( 'wallet_cashback_order_status', $this->settings_api->get_option( 'process_cashback_status', '_wallet_settings_credit', array( 'processing', 'completed' ) ) ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( $this->wallet, 'wallet_cashback' ), 12 );
		}

		add_action( 'woocommerce_order_status_cancelled', array( $this->wallet, 'process_cancelled_order' ) );

		add_filter( 'woocommerce_reports_get_order_report_query', array( $this, 'woocommerce_reports_get_order_report_query' ) );
		add_filter( 'woocommerce_analytics_revenue_query_args', array( $this, 'remove_wallet_rechargable_order_from_analytics' ) );
		add_filter( 'woocommerce_analytics_orders_stats_query_args', array( $this, 'remove_wallet_rechargable_order_from_analytics' ) );

		add_filter( 'woocommerce_analytics_orders_select_query', array( $this, 'woocommerce_analytics_orders_select_query_callback' ) );

		add_action( 'woocommerce_new_order_item', array( $this, 'woocommerce_new_order_item' ), 10, 2 );

		add_action( 'deleted_user', array( $this, 'delete_user_transaction_records' ) );

		add_action( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'filter_wallet_topup_orders' ), 10, 2 );

		add_filter( 'woocommerce_get_query_vars', array( $this, 'add_woocommerce_query_vars' ) );

		add_action( 'woocommerce_order_item_fee_after_calculate_taxes', array( $this, 'woocommerce_order_item_fee_after_calculate_taxes_callback' ), 10, 2 );

		$is_active = get_option( 'woo_wallet_is_active', false );

		if ( false === $is_active ) {
			update_option( 'woo_wallet_is_active', true );
			flush_rewrite_rules();
			do_action( 'woo_wallet_activated' );
		}
	}

	/**
	 * Add WooCommerce query vars.
	 *
	 * @param type $query_vars query_vars.
	 * @return type
	 */
	public function add_woocommerce_query_vars( $query_vars ) {
		$query_vars['woo-wallet']              = get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' );
		$query_vars['woo-wallet-transactions'] = get_option( 'woocommerce_woo_wallet_transactions_endpoint', 'wallet-transactions' );
		return $query_vars;
	}

	/**
	 * Runs the required processes when the plugin is deactivated.
	 *
	 * @since 1.5.0
	 */
	public function deactivate_plugin() {
		delete_option( 'woo_wallet_is_active' );
		flush_rewrite_rules();
		do_action( 'woo_wallet_deactivated' );
	}
	/**
	 * WooWallet init widget
	 */
	public function woo_wallet_widget_init() {
		register_widget( 'Woo_Wallet_Topup' );
	}
	/**
	 * Override WooCommerce email template directory.
	 *
	 * @param string $template_dir template dir.
	 * @param string $template Template.
	 */
	public function woocommerce_template_directory( $template_dir, $template ) {
		if ( in_array( $template, array( 'emails/low-wallet-balance.php', 'emails/user-new-transaction.php' ), true ) ) {
			$template_dir = 'woo-wallet';
		}
		return $template_dir;
	}

	/**
	 * Load WooCommerce dependent class file.
	 */
	public function woocommerce_loaded_callback() {
		include_once WOO_WALLET_ABSPATH . 'includes/abstracts/abstract-woo-wallet-actions.php';
		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-actions.php';
		include_once WOO_WALLET_ABSPATH . '/includes/class-woo-wallet-api.php';
		$this->rest_api = new WooWallet_API();
	}

	/**
	 * WP REST API init.
	 */
	public function rest_api_init() {
		include_once WOO_WALLET_ABSPATH . 'includes/api/class-woo-wallet-rest-controller.php';
		$rest_controller = new WOO_Wallet_REST_Controller();
		$rest_controller->register_routes();
	}
	/**
	 * Add settings link to plugin list.
	 *
	 * @param array $links links.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$action_links = array(
			'settings' => '<a href="' . admin_url( 'admin.php?page=woo-wallet-settings' ) . '" aria-label="' . esc_attr__( 'View Wallet settings', 'woo-wallet' ) . '">' . esc_html__( 'Settings', 'woo-wallet' ) . '</a>',
		);

		return array_merge( $action_links, $links );
	}

	/**
	 * Text Domain loader
	 */
	public function load_plugin_textdomain() {
		$locale = determine_locale();
		$locale = apply_filters( 'plugin_locale', $locale, 'woo-wallet' );

		unload_textdomain( 'woo-wallet', true );
		load_textdomain( 'woo-wallet', WP_LANG_DIR . '/woo-wallet/woo-wallet-' . $locale . '.mo' );
		load_plugin_textdomain( 'woo-wallet', false, plugin_basename( dirname( WOO_WALLET_PLUGIN_FILE ) ) . '/languages' );
	}

	/**
	 * WooCommerce wallet payment gateway loader
	 *
	 * @param array $load_gateways load_gateways.
	 * @return array
	 */
	public function load_gateway( $load_gateways ) {
		$load_gateways[] = 'Woo_Gateway_Wallet_payment';
		return $load_gateways;
	}

	/**
	 * WooCommerce email loader
	 *
	 * @param array $emails emails.
	 * @return array
	 */
	public function woocommerce_email_classes( $emails ) {
		$emails['Woo_Wallet_Email_New_Transaction']    = include WOO_WALLET_ABSPATH . 'includes/emails/class-woo-wallet-email-new-transaction.php';
		$emails['Woo_Wallet_Email_Low_Wallet_Balance'] = include WOO_WALLET_ABSPATH . 'includes/emails/class-woo-wallet-email-low-wallet-balance.php';
		return $emails;
	}

	/**
	 * Exclude rechargable orders from admin report
	 *
	 * @param array $query query.
	 * @return array
	 */
	public function woocommerce_reports_get_order_report_query( $query ) {
		$rechargable_orders = get_wallet_rechargeable_orders();
		if ( ! empty( $rechargable_orders ) && apply_filters( 'woo_wallet_exclude_wallet_rechargeable_orders_from_report', true ) ) {
			$exclude_orders  = implode( ', ', $rechargable_orders );
			$query['where'] .= " AND posts.ID NOT IN ({$exclude_orders})";
		}
		return $query;
	}
	/**
	 * Exclude rechargable orders from admin analytics
	 *
	 * @param array $query_vars query_vars.
	 * @return array
	 */
	public function remove_wallet_rechargable_order_from_analytics( $query_vars ) {
		if ( get_wallet_rechargeable_product() && apply_filters( 'woo_wallet_exclude_wallet_rechargeable_orders_from_report', true ) ) {
			$query_vars['product_excludes'][] = get_wallet_rechargeable_product()->get_id();
		}

		return $query_vars;
	}
	/**
	 * Remove wallet rechargeable product from analytics.
	 *
	 * @param object $results results.
	 * @return object
	 */
	public function woocommerce_analytics_orders_select_query_callback( $results ) {
		if ( $results && isset( $results->data ) && ! empty( $results->data ) ) {
			foreach ( $results->data as $key => $result ) {
				$order = wc_get_order( $result['order_id'] );
				if ( is_wallet_rechargeable_order( $order ) ) {
					unset( $results->data[ $key ] );
				}
			}
		}
		return $results;
	}
	/**
	 * Load marketplace supported file.
	 */
	public function add_marketplace_support() {
		if ( class_exists( 'WCMp' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/marketplace/wc-merketplace/class-woo-wallet-wcmp-gateway.php';
			include_once WOO_WALLET_ABSPATH . 'includes/marketplace/wc-merketplace/class-woo-wallet-wcmp.php';
		}
		if ( class_exists( 'WeDevs_Dokan' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/marketplace/dokan/class-woo-wallet-dokan.php';
		}
		if ( class_exists( 'WCFMmp' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/marketplace/wcfmmp/class-woo-wallet-wcfmmp.php';
		}
	}
	/**
	 * Load multicurrency supported file.
	 */
	public function add_multicurrency_support() {
		if ( class_exists( 'WOOCS' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/multicurrency/woocommerce-currency-switcher/class-wallet-multi-currency.php';
		}
		if ( class_exists( 'WCML_Multi_Currency' ) ) {
			include_once WOO_WALLET_ABSPATH . 'includes/multicurrency/woocommerce-multilingual/class-wallet-wpml-multi-currency.php';
		}
	}
	/**
	 * Store fee key to order item meta.
	 *
	 * @param Int               $item_id ItemId.
	 * @param WC_Order_Item_Fee $item Item.
	 */
	public function woocommerce_new_order_item( $item_id, $item ) {
		if ( 'fee' === $item->get_type() && property_exists( $item, 'legacy_fee_key' ) ) {
			update_metadata( 'order_item', $item_id, '_legacy_fee_key', $item->legacy_fee_key );
		}
	}
	/**
	 * Delete user transaction records.
	 *
	 * @param Int $id Transaction ID.
	 */
	public function delete_user_transaction_records( $id ) {
		global $wpdb;
		if ( apply_filters( 'woo_wallet_delete_transaction_records', true ) ) {
			$wpdb->query( $wpdb->prepare( "DELETE t.*, tm.* FROM {$wpdb->base_prefix}woo_wallet_transactions t JOIN {$wpdb->base_prefix}woo_wallet_transaction_meta tm ON t.transaction_id = tm.transaction_id WHERE t.user_id = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}
	}

	/**
	 * Filter wallet topup orders.
	 *
	 * @param array $query query.
	 * @param array $query_vars query_vars.
	 * @return array
	 */
	public function filter_wallet_topup_orders( $query, $query_vars ) {
		if ( ! empty( $query_vars['topuporders'] ) && $query_vars['topuporders'] ) {
			$query['meta_query'][] = array(
				'key'   => '_wc_wallet_purchase_credited',
				'value' => true,
			);
		}

		return $query;
	}

	/**
	 * Registers WooCommerce Blocks integration.
	 */
	public static function add_woocommerce_block_support() {
		if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
			require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-payments-blocks.php';
			add_action(
				'woocommerce_blocks_payment_method_type_registration',
				function ( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
					$payment_method_registry->register( new WOO_Wallet_Payments_Blocks() );
				}
			);
		}

		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-partial-payment-blocks.php';
		add_action(
			'woocommerce_blocks_cart_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new WOO_Wallet_Partial_Payment_Blocks() );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new WOO_Wallet_Partial_Payment_Blocks() );
			}
		);

		woocommerce_store_api_register_update_callback(
			array(
				'namespace' => 'apply-partial-payment',
				'callback'  => function ( $data ) {
					if ( ! is_null( wc()->session ) ) {
						wc()->session->set( 'partial_payment_amount', $data['amount'] );
					}
				},
			)
		);
	}

	/**
	 * Set wallet partial amount tax.
	 *
	 * @param WC_Order_Item_Fee $item item.
	 * @param array             $calculate_tax_for calculate_tax_for.
	 * @return void
	 */
	public function woocommerce_order_item_fee_after_calculate_taxes_callback( $item, $calculate_tax_for ) {
		if ( ! isset( $calculate_tax_for['tax_class'] ) ) {
			return;
		}
		if ( '_via_wallet_partial_payment' !== $item->legacy_fee_key ) {
			return;
		}
		$item->set_taxes( false );
	}

	/**
	 * Load template
	 *
	 * @param string $template_name Tempate Name.
	 * @param array  $args args.
	 * @param string $template_path Template Path.
	 * @param string $default_path Default path.
	 */
	public function get_template( $template_name, $args = array(), $template_path = '', $default_path = '' ) {
		if ( $args && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore
		}
		$located = $this->locate_template( $template_name, $template_path, $default_path );
		include $located;
	}

	/**
	 * Locate template file
	 *
	 * @param string $template_name template_name.
	 * @param string $template_path template_path.
	 * @param string $default_path default_path.
	 * @return string
	 */
	public function locate_template( $template_name, $template_path = '', $default_path = '' ) {
		$default_path = apply_filters( 'woo_wallet_template_path', $default_path );
		if ( ! $template_path ) {
			$template_path = 'woo-wallet';
		}
		if ( ! $default_path ) {
			$default_path = WOO_WALLET_ABSPATH . 'templates/';
		}
		// Look within passed path within the theme - this is priority.
		$template = locate_template( array( trailingslashit( $template_path ) . $template_name, $template_name ) );
		// Add support of third perty plugin.
		$template = apply_filters( 'woo_wallet_locate_template', $template, $template_name, $template_path, $default_path );
		// Get default template.
		if ( ! $template ) {
			$template = $default_path . $template_name;
		}
		return $template;
	}

	/**
	 * Display admin notice
	 */
	public function admin_notices() {                   ?>
		<div class="error">
			<p>
				<?php echo esc_html_e( 'TeraWallet plugin requires', 'woo-wallet' ); ?>
				<a href="https://wordpress.org/plugins/woocommerce/">WooCommerce</a> <?php echo esc_html_e( 'plugins to be active!', 'woo-wallet' ); ?>
			</p>
		</div>
		<?php
	}
}
