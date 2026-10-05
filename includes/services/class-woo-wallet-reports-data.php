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
			// Derived from the canonical registry rather than a second hard-coded
			// map: the local copy silently missed cashback_refund,
			// cashback_adjustment and vendor_commission, which then displayed as
			// a ucwords() of their slug.
			$labels = array();
			if ( function_exists( 'woo_wallet_get_transaction_types' ) ) {
				foreach ( woo_wallet_get_transaction_types() as $slug => $type ) {
					$labels[ $slug ] = $type['label'];
				}
			}

			return apply_filters( 'woo_wallet_reports_category_labels', $labels );
		}

		/**
		 * Store base currency code, as the currency manager sees it (a switcher
		 * can filter get_woocommerce_currency() to the visitor's currency).
		 *
		 * @return string
		 */
		public function base_currency() {
			if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
				return Woo_Wallet_Currency_Manager::instance()->get_base_currency();
			}
			$base = get_option( 'woocommerce_currency' );
			return is_string( $base ) && '' !== $base ? strtoupper( $base ) : 'USD';
		}

		/**
		 * Convert an amount stored in $currency to the base currency. An empty
		 * currency is treated as base (legacy rows).
		 *
		 * @param float  $amount   Amount.
		 * @param string $currency Row currency ('' = base).
		 * @return float
		 */
		protected function to_base( $amount, $currency ) {
			$base = $this->base_currency();
			$from = '' !== (string) $currency ? strtoupper( (string) $currency ) : $base;
			if ( $from === $base || ! class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
				return (float) $amount;
			}
			return (float) Woo_Wallet_Currency_Manager::instance()->convert( $amount, $from, $base );
		}

		/**
		 * Base currency plus the current rate of every currency in the ledger.
		 * Part of the summary cache key: switchers auto-update rates without a
		 * ledger write, and the cached totals must not outlive the rate they
		 * were converted at.
		 *
		 * @return string
		 */
		protected function rates_fingerprint() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$currencies = $wpdb->get_col( "SELECT DISTINCT currency FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE deleted = 0" );
			$rates      = array( 'base' => $this->base_currency() );
			foreach ( (array) $currencies as $currency ) {
				$rates[ (string) $currency ] = $this->to_base( 1, $currency );
			}
			return (string) wp_json_encode( $rates );
		}

		/**
		 * Total outstanding liability: SUM(credit) - SUM(debit) over live rows,
		 * each currency group converted to the base currency.
		 *
		 * @return float
		 */
		public function total_liability() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows  = $wpdb->get_results(
				"SELECT currency, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) AS net
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE deleted = 0
				 GROUP BY currency"
			);
			$total = 0.0;
			foreach ( (array) $rows as $row ) {
				$total += $this->to_base( $row->net, $row->currency );
			}
			return $total;
		}

		/**
		 * Count of users whose net balance, converted to base, is strictly positive.
		 *
		 * @return int
		 */
		public function positive_wallets_count() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows  = $wpdb->get_results(
				"SELECT user_id, currency, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) AS net
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE deleted = 0
				 GROUP BY user_id, currency"
			);
			$users = array();
			foreach ( (array) $rows as $row ) {
				$uid           = (int) $row->user_id;
				$users[ $uid ] = ( $users[ $uid ] ?? 0.0 ) + $this->to_base( $row->net, $row->currency );
			}
			$count = 0;
			foreach ( $users as $net ) {
				// Round away conversion dust so a zeroed wallet isn't counted.
				if ( round( $net, 6 ) > 0 ) {
					++$count;
				}
			}
			return $count;
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
		 * SUM(amount) for one transaction type over live rows, in base currency.
		 *
		 * @param string $type 'credit' or 'debit'.
		 * @return float
		 */
		protected function sum_by_type( $type ) {
			global $wpdb;
			$type = ( 'debit' === $type ) ? 'debit' : 'credit';
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT currency, SUM(amount) AS total
					 FROM {$wpdb->base_prefix}woo_wallet_transactions
					 WHERE deleted = 0 AND type = %s
					 GROUP BY currency",
					$type
				)
			);
			$total = 0.0;
			foreach ( (array) $rows as $row ) {
				$total += $this->to_base( $row->total, $row->currency );
			}
			return $total;
		}

		/**
		 * Net liability contribution grouped by the `category` column, in base
		 * currency. The rows sum to total_liability(). Slugs are mapped to
		 * friendly labels.
		 *
		 * @return array<int,array{slug:string,label:string,amount:float}>
		 */
		public function liability_by_category() {
			global $wpdb;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$rows = $wpdb->get_results(
				"SELECT category, currency, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) AS net
				 FROM {$wpdb->base_prefix}woo_wallet_transactions
				 WHERE deleted = 0
				 GROUP BY category, currency"
			);

			$nets = array();
			foreach ( (array) $rows as $row ) {
				$slug          = $row->category ? $row->category : 'other';
				$nets[ $slug ] = ( $nets[ $slug ] ?? 0.0 ) + $this->to_base( $row->net, $row->currency );
			}
			arsort( $nets );

			$labels = $this->category_labels();
			$out    = array();
			foreach ( $nets as $slug => $net ) {
				$out[] = array(
					'slug'   => $slug,
					'label'  => isset( $labels[ $slug ] ) ? $labels[ $slug ] : ucwords( str_replace( '_', ' ', $slug ) ),
					'amount' => (float) $net,
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
			$cache_key = 'woo_wallet_reports_summary_' . $version . '_' . md5( wp_json_encode( $args ) . $this->rates_fingerprint() );
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
				// wc_price() returns the currency symbol as an HTML entity
				// (&#8377; for ₹). Every caller escapes this string before
				// printing it, which would re-encode the ampersand and render
				// the entity literally, so decode it here — at the single point
				// all of them route through.
				return html_entity_decode(
					wp_strip_all_tags( wc_price( (float) $amount, array( 'currency' => $this->base_currency() ) ) ),
					ENT_QUOTES,
					'UTF-8'
				);
			}
			return (string) $amount;
		}
	}
}
