<?php
/**
 * Abstract base controller for TeraWallet REST endpoints.
 *
 * Provides shared helpers for capability checks, error responses, and
 * HATEOAS self-links. Concrete controllers (transactions, settings) extend
 * this and implement get_item_schema() / prepare_item_for_response().
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Controller_Base' ) ) {

	/**
	 * Base controller for TeraWallet REST endpoints.
	 */
	abstract class TeraWallet_REST_Controller_Base extends WC_REST_Controller {

		/**
		 * Run a capability check, then route the result through the legacy
		 * `woo_wallet_rest_check_permissions` filter (mirrors WooCommerce's
		 * own `woocommerce_rest_check_permissions` extension point).
		 *
		 * @param string          $context Capability context: read|create|edit|delete.
		 * @param WP_REST_Request $request The request being authorized.
		 * @param string          $cap     Capability to check (default manage_woocommerce).
		 * @return true|WP_Error
		 */
		protected function check_capability( $context, $request, $cap = 'manage_woocommerce' ) {
			$allowed = apply_filters(
				'woo_wallet_rest_check_permissions',
				current_user_can( $cap ),
				$context,
				$request
			);
			if ( ! $allowed ) {
				return $this->error(
					'woocommerce_rest_cannot_' . $context,
					sprintf(
						/* translators: %s: action context (read|create|edit|delete) */
						__( 'Sorry, you are not allowed to %s wallet resources.', 'woo-wallet' ),
						$context
					),
					rest_authorization_required_code()
				);
			}
			return true;
		}

		/**
		 * Build a WP_Error with a status header.
		 *
		 * @param string $code    Error code.
		 * @param string $message Human-readable message.
		 * @param int    $status  HTTP status code.
		 * @return WP_Error
		 */
		protected function error( $code, $message, $status ) {
			return new WP_Error( $code, $message, array( 'status' => (int) $status ) );
		}

		/**
		 * Add the standard `_links.self` HATEOAS link to a response.
		 *
		 * @param WP_REST_Response $response  Response to mutate.
		 * @param string           $rest_base REST base path (e.g. "wallet").
		 * @param int|string       $id        Resource id.
		 * @return WP_REST_Response
		 */
		protected function add_self_link( WP_REST_Response $response, $rest_base, $id ) {
			$response->add_link(
				'self',
				rest_url( sprintf( '%s/%s/%s', $this->namespace, $rest_base, $id ) )
			);
			return $response;
		}

		/**
		 * Add pagination headers (X-WP-Total, X-WP-TotalPages) and Link rels.
		 *
		 * @param WP_REST_Response $response Response to mutate.
		 * @param int              $total    Total item count.
		 * @param int              $page     Current page (1-based).
		 * @param int              $per_page Items per page.
		 * @return WP_REST_Response
		 */
		protected function add_pagination_headers( WP_REST_Response $response, $total, $page, $per_page ) {
			$total       = (int) $total;
			$per_page    = max( 1, (int) $per_page );
			$page        = max( 1, (int) $page );
			$total_pages = (int) ceil( $total / $per_page );
			$response->header( 'X-WP-Total', (string) $total );
			$response->header( 'X-WP-TotalPages', (string) $total_pages );
			return $response;
		}
	}
}
