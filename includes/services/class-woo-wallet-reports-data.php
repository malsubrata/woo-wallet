<?php
/**
 * Wallet liability reporting — data service.
 *
 * Read-only aggregate queries over the append-only ledger
 * (`{prefix}woo_wallet_transactions`). Computes store-wide liability metrics
 * with a single grouped query per metric — never a per-user PHP loop. The
 * summary payload is cached in a transient with a filterable TTL.
 *
 * This service never writes to the ledger.
 *
 * @package StandaleneTech
 * @since   1.6.6
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Wallet_Reports_Data' ) ) {

	/**
	 * Aggregate liability reporting queries.
	 */
	class Woo_Wallet_Reports_Data {

		/**
		 * Friendly labels for the known `category` column slugs. Unknown slugs
		 * are title-cased on the fly, so a Pro/third-party category still shows.
		 *
		 * @return array<string,string>
		 */
		protected function category_labels() {
			return apply_filters(
				'woo_wallet_reports_category_labels',
				array(
					'topup'           => __( 'Top-ups', 'woo-wallet' ),
					'cashback'        => __( 'Cashback', 'woo-wallet' ),
					'refund'          => __( 'Refunds', 'woo-wallet' ),
					'purchase'        => __( 'Purchases', 'woo-wallet' ),
					'partial_payment' => __( 'Partial payments', 'woo-wallet' ),
					'transfer'        => __( 'Transfers', 'woo-wallet' ),
					'adjustment'      => __( 'Adjustments', 'woo-wallet' ),
					'other'           => __( 'Other', 'woo-wallet' ),
				)
			);
		}

		/**
		 * Store base currency code.
		 *
		 * @return string
		 */
		public function base_currency() {
			return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : 'USD';
		}

		/**
		 * Total outstanding liability: SUM(credit) - SUM(debit) over live rows.
		 *
		 * ponytail: sums raw `amount` across currencies; correct for the common
		 * single-currency store. Multi-currency normalisation lives in Pro.
		 *
		 * @return float
		 */
		public function total_liability() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (float) $wpdb->get_var(
				"SELECT COALESCE(SUM(CASE WHEN type='credit' THEN amount ELSE -amount END), 0)
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE deleted = 0"
			);
		}

		/**
		 * Count of users whose net balance is strictly positive.
		 *
		 * @return int
		 */
		public function positive_wallets_count() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM (
					SELECT user_id
					FROM {$wpdb->base_prefix}woo_wallet_transactions
					WHERE deleted = 0
					GROUP BY user_id
					HAVING SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) > 0
				) AS positive_wallets"
			);
		}

		/**
		 * Lifetime total credited (live rows).
		 *
		 * @return float
		 */
		public function lifetime_credited() {
			return $this->sum_by_type( 'credit' );
		}

		/**
		 * Lifetime total debited (live rows).
		 *
		 * @return float
		 */
		public function lifetime_debited() {
			return $this->sum_by_type( 'debit' );
		}

		/**
		 * SUM(amount) for one transaction type over live rows.
		 *
		 * @param string $type 'credit' or 'debit'.
		 * @return float
		 */
		protected function sum_by_type( $type ) {
			global $wpdb;
			$type = ( 'debit' === $type ) ? 'debit' : 'credit';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (float) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COALESCE(SUM(amount), 0)
					 FROM {$wpdb->base_prefix}woo_wallet_transactions
					 WHERE deleted = 0 AND type = %s",
					$type
				)
			);
		}

		/**
		 * Net liability contribution grouped by the `category` column. The rows
		 * sum to total_liability(). Slugs are mapped to friendly labels.
		 *
		 * @return array<int,array{slug:string,label:string,amount:float}>
		 */
		public function liability_by_category() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				"SELECT category, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) AS net
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE deleted = 0
				 GROUP BY category
				 ORDER BY net DESC"
			);

			$labels = $this->category_labels();
			$out    = array();
			foreach ( (array) $rows as $row ) {
				$slug  = $row->category ? $row->category : 'other';
				$out[] = array(
					'slug'   => $slug,
					'label'  => isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( '_', ' ', $slug ) ),
					'amount' => (float) $row->net,
				);
			}
			return $out;
		}

		/**
		 * Assemble the full summary payload, cached in a transient.
		 *
		 * @param array $args Query args (threaded through `woo_wallet_reports_query_args`).
		 * @return array
		 */
		public function get_summary( $args = array() ) {
			$args = apply_filters( 'woo_wallet_reports_query_args', (array) $args );

			// Version-namespaced key: bumped on every ledger write (see
			// Woo_Wallet::flush_reports_cache), so a page reload after any wallet
			// activity misses the stale transient and recomputes. Old keys age out
			// via TTL.
			$version   = (int) get_option( 'woo_wallet_reports_cache_version', 0 );
			$cache_key = 'woo_wallet_reports_summary_' . $version . '_' . md5( wp_json_encode( $args ) );
			$cached    = get_transient( $cache_key );
			if ( false !== $cached && is_array( $cached ) && ! isset( $args['nocache'] ) ) {
				return $cached;
			}

			$base = $this->base_currency();
			$data = array(
				'base_currency'     => $base,
				'total_liability'   => $this->total_liability(),
				'positive_wallets'  => $this->positive_wallets_count(),
				'lifetime_credited' => $this->lifetime_credited(),
				'lifetime_debited'  => $this->lifetime_debited(),
				'composition'       => $this->liability_by_category(),
				'generated_at'      => current_time( 'mysql' ),
			);

			$ttl = (int) apply_filters( 'woo_wallet_reports_cache_ttl', 15 * MINUTE_IN_SECONDS );
			set_transient( $cache_key, $data, max( 0, $ttl ) );

			return $data;
		}

		/**
		 * Format an amount in the store base currency, stripped to plain text
		 * for use in a server-rendered card.
		 *
		 * @param float $amount Amount.
		 * @return string
		 */
		public function format_amount( $amount ) {
			if ( function_exists( 'wc_price' ) ) {
				return wp_strip_all_tags( wc_price( (float) $amount, array( 'currency' => $this->base_currency() ) ) );
			}
			return (string) $amount;
		}
	}
}
