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

		/**
		 * Normalize a raw `_type` meta value into the REST-facing category enum.
		 * Shared between `wc/v3/wallet` and `terawallet/v1/admin/transactions`
		 * so both namespaces emit identical category labels.
		 *
		 * @param string $raw_type Raw `_type` meta value.
		 * @return string
		 */
		protected function normalize_category( $raw_type ) {
			$known = array(
				'topup'               => 'topup',
				'credit_purchase'     => 'topup',
				'cashback'            => 'cashback',
				'cashback_adjustment' => 'cashback_adjustment',
				'cashback_refund'     => 'cashback_refund',
				'partial_payment'     => 'partial_payment',
				'transfer'            => 'transfer',
				'refund'              => 'refund',
				'adjustment'          => 'adjustment',
			);
			return isset( $known[ $raw_type ] ) ? $known[ $raw_type ] : 'other';
		}

		/**
		 * Project a raw transaction row into the canonical REST array. Concrete
		 * controllers wrap this in `prepare_item_for_response()` so both
		 * namespaces ship an identical row shape (DataView-ready: includes
		 * `formatted` strings and an embedded `user` block).
		 *
		 * @param object          $transaction Raw row from get_wallet_transactions().
		 * @param WP_REST_Request $request     The request (used for context).
		 * @return array
		 */
		protected function build_transaction_data( $transaction, $request ) {
			$category            = 'other';
			$cashback_expires_at = null;
			if ( isset( $transaction->meta ) && is_array( $transaction->meta ) ) {
				foreach ( $transaction->meta as $meta_row ) {
					if ( '_type' === $meta_row->meta_key ) {
						$category = $this->normalize_category( $meta_row->meta_value );
					} elseif ( 'cashback_expires_at' === $meta_row->meta_key ) {
						$ts = (int) $meta_row->meta_value;
						if ( $ts > 0 ) {
							$cashback_expires_at = gmdate( 'Y-m-d\TH:i:s', $ts );
						}
					}
				}
			}

			$user_id  = isset( $transaction->user_id ) ? (int) $transaction->user_id : 0;
			$currency = isset( $transaction->currency ) ? $transaction->currency : get_woocommerce_currency();
			$amount   = isset( $transaction->amount ) ? (float) $transaction->amount : 0.0;
			$original_amount   = isset( $transaction->original_amount ) && null !== $transaction->original_amount ? (float) $transaction->original_amount : null;
			$original_currency = isset( $transaction->original_currency ) && null !== $transaction->original_currency ? (string) $transaction->original_currency : null;
			$type     = isset( $transaction->type ) ? $transaction->type : '';

			$price_args = function_exists( 'woo_wallet_wc_price_args' )
				? woo_wallet_wc_price_args( $user_id, array( 'currency' => $currency ) )
				: array( 'currency' => $currency );
			$formatted = array(
				'amount'          => function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $amount, $price_args ) ) : (string) $amount,
				'original_amount' => null,
				'date'            => '',
				'type_label'      => 'credit' === $type ? __( 'Credit', 'woo-wallet' ) : ( 'debit' === $type ? __( 'Debit', 'woo-wallet' ) : '' ),
				'category_label'  => $this->category_label( $category ),
			);
			if ( null !== $original_amount && null !== $original_currency ) {
				$oprice_args                  = function_exists( 'woo_wallet_wc_price_args' )
					? woo_wallet_wc_price_args( $user_id, array( 'currency' => $original_currency ) )
					: array( 'currency' => $original_currency );
				$formatted['original_amount'] = function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $original_amount, $oprice_args ) ) : (string) $original_amount;
			}
			if ( isset( $transaction->date ) && function_exists( 'wc_string_to_datetime' ) ) {
				$formatted['date'] = wc_string_to_datetime( $transaction->date )->date_i18n( wc_date_format() . ' ' . wc_time_format() );
			}

			return array(
				'id'                  => isset( $transaction->transaction_id ) ? (int) $transaction->transaction_id : 0,
				'user_id'             => $user_id,
				'user'                => $this->resolve_user_block( $user_id ),
				'type'                => $type,
				'amount'              => $amount,
				'currency'            => $currency,
				'original_amount'     => $original_amount,
				'original_currency'   => $original_currency,
				'original_rate'       => isset( $transaction->original_rate ) && null !== $transaction->original_rate ? (float) $transaction->original_rate : null,
				'mode'                => isset( $transaction->mode ) ? (int) $transaction->mode : 0,
				'details'             => isset( $transaction->details ) ? $transaction->details : '',
				'date'                => isset( $transaction->date ) ? mysql_to_rfc3339( $transaction->date ) : '',
				'created_by'          => isset( $transaction->created_by ) ? (int) $transaction->created_by : 0,
				'deleted'             => isset( $transaction->deleted ) ? (bool) $transaction->deleted : false,
				'category'            => $category,
				'cashback_expires_at' => $cashback_expires_at,
				'formatted'           => $formatted,
			);
		}

		/**
		 * Lightweight per-request user cache so a paginated transaction list
		 * doesn't N+1 on wp_users.
		 *
		 * @var array<int, array>
		 */
		private static $user_block_cache = array();

		/**
		 * Resolve the embedded user block for a transaction row.
		 *
		 * @param int $user_id User id.
		 * @return array|null
		 */
		protected function resolve_user_block( $user_id ) {
			$user_id = (int) $user_id;
			if ( ! $user_id ) {
				return null;
			}
			if ( isset( self::$user_block_cache[ $user_id ] ) ) {
				return self::$user_block_cache[ $user_id ];
			}
			$user = get_user_by( 'ID', $user_id );
			if ( ! $user ) {
				return self::$user_block_cache[ $user_id ] = null;
			}
			return self::$user_block_cache[ $user_id ] = array(
				'id'           => $user_id,
				'login'        => $user->user_login,
				'email'        => $user->user_email,
				'display_name' => $user->display_name,
				'avatar_url'   => get_avatar_url( $user_id ),
			);
		}

		/**
		 * Map a normalized category slug to a human label.
		 *
		 * @param string $category Category slug.
		 * @return string
		 */
		protected function category_label( $category ) {
			$labels = array(
				'topup'               => __( 'Top-up', 'woo-wallet' ),
				'cashback'            => __( 'Cashback', 'woo-wallet' ),
				'cashback_adjustment' => __( 'Cashback adjustment', 'woo-wallet' ),
				'cashback_refund'     => __( 'Cashback refund', 'woo-wallet' ),
				'partial_payment'     => __( 'Partial payment', 'woo-wallet' ),
				'transfer'            => __( 'Transfer', 'woo-wallet' ),
				'refund'              => __( 'Refund', 'woo-wallet' ),
				'adjustment'          => __( 'Adjustment', 'woo-wallet' ),
				'other'               => __( 'Other', 'woo-wallet' ),
			);
			return isset( $labels[ $category ] ) ? $labels[ $category ] : $labels['other'];
		}
	}
}
