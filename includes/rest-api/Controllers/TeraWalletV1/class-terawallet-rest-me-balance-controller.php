<?php
/**
 * GET /terawallet/v1/me/balance
 *
 * Lightweight balance read for the React dashboard's header pill — separate
 * from /me so the SPA can poll it cheaply without re-fetching the full
 * profile snapshot.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'TeraWallet_REST_Me_Balance_Controller' ) ) {

	/**
	 * Balance controller.
	 */
	class TeraWallet_REST_Me_Balance_Controller extends TeraWallet_REST_Me_Controller_Base {

		/**
		 * REST base.
		 *
		 * @var string
		 */
		protected $rest_base = 'me/balance';

		/**
		 * Register the route.
		 */
		public function register_routes() {
			register_rest_route(
				$this->namespace,
				'/' . $this->rest_base,
				array(
					array(
						'methods'             => WP_REST_Server::READABLE,
						'callback'            => array( $this, 'get_item' ),
						'permission_callback' => array( $this, 'check_me_permissions' ),
						'args'                => array(
							'context' => $this->get_context_param( array( 'default' => 'view' ) ),
						),
					),
					'schema' => array( $this, 'get_public_item_schema' ),
				)
			);
		}

		/**
		 * Read balance for the calling user.
		 *
		 * Response shape (additive — the legacy `amount`, `currency`, `formatted`
		 * fields are preserved so existing SPA builds keep working):
		 *
		 *   {
		 *     amount, currency, formatted,                  // legacy: active-currency view
		 *     base_currency, base_amount, base_formatted,   // canonical, never converted
		 *     mode,                                         // 'single_base' | 'per_currency'
		 *     balances: [ { currency, amount, formatted }, ... ]  // always >= 1 entry
		 *   }
		 *
		 * In single_base mode the wallet has one canonical balance and `balances`
		 * holds a single row scoped to the active currency (the same value the
		 * legacy `amount` field reports). In per_currency mode `balances` enumerates
		 * every distinct currency the user has rows in, plus base if missing.
		 *
		 * @param WP_REST_Request $request Request.
		 * @return WP_REST_Response
		 */
		public function get_item( $request ) {
			$user_id = $this->current_user_id();
			$wallet  = woo_wallet()->wallet;

			$mode = $this->resolve_mode();
			$base_currency   = $this->resolve_base_currency();
			$active_currency = $this->resolve_active_currency( $base_currency );

			// `amount` (legacy field) preserves 1.5.x semantics in single_base mode
			// and reports the active-currency sub-balance in per_currency mode.
			$display_currency = 'per_currency' === $mode ? $active_currency : $active_currency;
			$amount = (float) $wallet->get_wallet_balance( $user_id, 'edit', $display_currency );

			// `base_amount` always reflects the canonical/base ledger sum so SPAs
			// can show "you have X USD across all sub-balances" without juggling
			// rate maps client-side. In single_base mode it equals `amount`.
			$base_amount = 'per_currency' === $mode
				? $this->sum_base_value( $user_id, $base_currency )
				: $amount;

			$balances = $this->build_balances_array( $user_id, $mode, $base_currency, $active_currency, $amount );

			$data = array(
				'amount'          => $amount,
				'currency'        => $display_currency,
				'formatted'       => wp_strip_all_tags( wc_price( $amount, woo_wallet_wc_price_args( $user_id, array( 'currency' => $display_currency ) ) ) ),
				'base_currency'   => $base_currency,
				'base_amount'     => $base_amount,
				'base_formatted'  => wp_strip_all_tags( wc_price( $base_amount, woo_wallet_wc_price_args( $user_id, array( 'currency' => $base_currency ) ) ) ),
				'mode'            => $mode,
				'balances'        => $balances,
			);

			$data = apply_filters( 'terawallet_rest_me_balance', $data, $user_id, $request );

			$response = rest_ensure_response( $data );
			$response->add_link( 'self', rest_url( $this->namespace . '/me/balance' ) );
			return $this->private_no_store( $response );
		}

		/**
		 * Resolve the ledger storage mode. Mirrors Woo_Wallet_Wallet::get_currency_mode()
		 * — kept here as a private helper so the controller doesn't reach into
		 * private wallet internals via reflection.
		 *
		 * @return string 'single_base' | 'per_currency'
		 */
		protected function resolve_mode() {
			if ( ! apply_filters( 'woo_wallet_enable_per_currency_mode', false ) ) {
				return 'single_base';
			}
			$setting = woo_wallet()->settings_api->get_option( 'wallet_currency_mode', '_wallet_settings_general', 'single_base' );
			return 'per_currency' === $setting ? 'per_currency' : 'single_base';
		}

		/**
		 * Active storefront currency (provider-aware, with WC base fallback).
		 *
		 * @param string $base Resolved base currency.
		 * @return string
		 */
		protected function resolve_active_currency( $base ) {
			if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
				$active = Woo_Wallet_Currency_Manager::instance()->get_active_currency();
				if ( is_string( $active ) && '' !== $active ) {
					return strtoupper( $active );
				}
			}
			return $base;
		}

		/**
		 * Base / shop currency.
		 *
		 * @return string
		 */
		protected function resolve_base_currency() {
			if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
				$base = Woo_Wallet_Currency_Manager::instance()->get_base_currency();
				if ( is_string( $base ) && '' !== $base ) {
					return strtoupper( $base );
				}
			}
			$wc_base = get_option( 'woocommerce_currency' );
			return is_string( $wc_base ) && '' !== $wc_base ? strtoupper( $wc_base ) : 'USD';
		}

		/**
		 * Sum every per-currency sub-balance back to base via the active provider.
		 *
		 * Used only in per_currency mode. Falls open to the simple SUM (treating
		 * unknown rates as 1:1) when no provider can convert a row's currency —
		 * the alternative (silently dropping it from the total) would mis-report
		 * the wallet as smaller than it actually is.
		 *
		 * @param int    $user_id User id.
		 * @param string $base    Base currency.
		 * @return float
		 */
		protected function sum_base_value( $user_id, $base ) {
			global $wpdb;
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT currency, SUM(CASE WHEN type='credit' THEN amount ELSE -amount END) AS net FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0 GROUP BY currency", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( empty( $rows ) ) {
				return 0.0;
			}
			$total   = 0.0;
			$manager = class_exists( 'Woo_Wallet_Currency_Manager' ) ? Woo_Wallet_Currency_Manager::instance() : null;
			foreach ( $rows as $row ) {
				$row_currency = isset( $row->currency ) && '' !== $row->currency ? strtoupper( $row->currency ) : $base;
				$net          = (float) $row->net;
				if ( $row_currency === $base || ! $manager ) {
					$total += $net;
					continue;
				}
				$total += (float) $manager->convert( $net, $row_currency, $base );
			}
			return $total;
		}

		/**
		 * Enumerate per-currency sub-balances. In single_base mode this returns a
		 * single row in the active currency to keep the response shape consistent
		 * for SPA renderers that always iterate `balances[]`.
		 *
		 * @param int    $user_id          User id.
		 * @param string $mode             Resolved mode.
		 * @param string $base_currency    Base currency.
		 * @param string $active_currency  Active currency.
		 * @param float  $active_amount    Pre-computed active-currency amount.
		 * @return array
		 */
		protected function build_balances_array( $user_id, $mode, $base_currency, $active_currency, $active_amount ) {
			if ( 'per_currency' !== $mode ) {
				return array(
					array(
						'currency'  => $active_currency,
						'amount'    => (float) $active_amount,
						'formatted' => wp_strip_all_tags( wc_price( $active_amount, woo_wallet_wc_price_args( $user_id, array( 'currency' => $active_currency ) ) ) ),
					),
				);
			}

			global $wpdb;
			$codes = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT currency FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id=%d AND deleted=0 AND currency <> ''", $user_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$codes = array_map( 'strtoupper', (array) $codes );
			if ( ! in_array( $base_currency, $codes, true ) ) {
				$codes[] = $base_currency;
			}
			$codes = array_values( array_unique( $codes ) );

			$wallet = woo_wallet()->wallet;
			$out    = array();
			foreach ( $codes as $code ) {
				$amt   = (float) $wallet->get_wallet_balance( $user_id, 'edit', $code );
				$out[] = array(
					'currency'  => $code,
					'amount'    => $amt,
					'formatted' => wp_strip_all_tags( wc_price( $amt, woo_wallet_wc_price_args( $user_id, array( 'currency' => $code ) ) ) ),
				);
			}
			return $out;
		}

		/**
		 * Schema.
		 *
		 * @return array
		 */
		public function get_item_schema() {
			$schema = array(
				'$schema'    => 'http://json-schema.org/draft-04/schema#',
				'title'      => 'terawallet_balance',
				'type'       => 'object',
				'properties' => array(
					'amount'         => array(
						'description' => __( 'Balance in the active storefront currency. In single_base mode this is the canonical balance.', 'woo-wallet' ),
						'type'        => 'number',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'currency'       => array(
						'description' => __( 'ISO code of the currency `amount` is denominated in.', 'woo-wallet' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'formatted'      => array(
						'description' => __( 'wc_price-formatted string for `amount`.', 'woo-wallet' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'base_currency'  => array(
						'description' => __( 'Shop base currency.', 'woo-wallet' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'base_amount'    => array(
						'description' => __( 'Total wallet value normalised to base currency.', 'woo-wallet' ),
						'type'        => 'number',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'base_formatted' => array(
						'description' => __( 'wc_price-formatted string for `base_amount`.', 'woo-wallet' ),
						'type'        => 'string',
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'mode'           => array(
						'description' => __( 'Storage mode: single_base | per_currency.', 'woo-wallet' ),
						'type'        => 'string',
						'enum'        => array( 'single_base', 'per_currency' ),
						'context'     => array( 'view' ),
						'readonly'    => true,
					),
					'balances'       => array(
						'description' => __( 'Per-currency sub-balances. Always at least one entry.', 'woo-wallet' ),
						'type'        => 'array',
						'context'     => array( 'view' ),
						'readonly'    => true,
						'items'       => array(
							'type'       => 'object',
							'properties' => array(
								'currency'  => array( 'type' => 'string' ),
								'amount'    => array( 'type' => 'number' ),
								'formatted' => array( 'type' => 'string' ),
							),
						),
					),
				),
			);
			return $this->add_additional_fields_schema( $schema );
		}
	}
}
