<?php
/**
 * REST API: terawallet/v1/admin/reports
 *
 * Read-only store-wide wallet liability summary, mirroring the metrics shown
 * on the `Woo_Wallet_Reports` admin page:
 *   - GET /admin/reports/summary   liability totals + composition
 *
 * The payload is passed through `woo_wallet_reports_summary_data` so Pro can
 * inject extra fields, exactly as the admin page passes cards through
 * `woo_wallet_reports_metrics`.
 *
 * @package StandaleneTech
 * @since   1.6.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin reports controller.
 */
class TeraWallet_REST_Admin_Reports_Controller extends TeraWallet_REST_Admin_Controller_Base {

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'admin/reports';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/summary',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_summary' ),
					'permission_callback' => array( $this, 'permissions_read' ),
				),
			)
		);
	}

	/**
	 * Reports honour the filterable reports capability (default manage_woocommerce).
	 *
	 * @param WP_REST_Request $request The request.
	 * @return true|WP_Error
	 */
	public function permissions_read( $request ) {
		return $this->check_capability( 'read', $request, apply_filters( 'woo_wallet_reports_capability', 'manage_woocommerce' ) );
	}

	/**
	 * GET /admin/reports/summary.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response
	 */
	public function get_summary( $request ) {
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';

		// The caller (e.g. the dashboard Refresh button) opts out of the transient
		// by sending ?nocache=1; otherwise the cached summary is served.
		$args = array();
		if ( $request->get_param( 'nocache' ) ) {
			$args['nocache'] = true;
		}
		$args = apply_filters( 'woo_wallet_reports_query_args', $args );
		$data = ( new Woo_Wallet_Reports_Data() )->get_summary( $args );

		/** Mirror the page's extensibility: let Pro add fields to the payload. */
		$data = apply_filters( 'woo_wallet_reports_summary_data', $data, $args );

		return new WP_REST_Response( $data, 200 );
	}
}
