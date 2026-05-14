<?php
/**
 * Unified multi-currency integration.
 *
 * Owns every multi-currency hook the wallet exposes (`woo_wallet_amount`,
 * `woo_wallet_current_balance`, `woo_wallet_rechargeable_amount`,
 * the three settings option filters, and `woo_wallet_form_cart_cashback_amount`)
 * and routes conversion through `Woo_Wallet_Currency_Manager`.
 *
 * Replaces the per-plugin adapter classes that previously lived under
 * `includes/multicurrency/woocommerce-currency-switcher/` and
 * `includes/multicurrency/woocommerce-multilingual/`. Behavior is preserved
 * — the hooks fire the same way, with the same arithmetic — but the
 * conversion math no longer assumes a specific third-party plugin.
 *
 * Activation: skipped entirely on vanilla single-currency sites (where the
 * active provider is the generic fallback). On a vanilla site every hook
 * here would be a no-op anyway, so we save the cycles.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Multicurrency_Integration' ) ) {
	/**
	 * Hook registrations + handlers backed by the currency manager.
	 */
	class Woo_Wallet_Multicurrency_Integration {

		/**
		 * Idempotency guard. The wallet's bootstrap path is supposed to
		 * instantiate this class exactly once per request, but `init` actions
		 * that are re-fired by other plugins (or duplicate plugin loads) can
		 * trigger a second construction. Hook registrations are global state
		 * — registering them twice produces compounded conversions on every
		 * `apply_filters('woo_wallet_*')` call. Tracking this with a static
		 * flag means the second-and-subsequent constructions are silent
		 * no-ops.
		 *
		 * @var bool
		 */
		private static $hooks_registered = false;

		/**
		 * Register hooks.
		 *
		 * Currency hooks register only when a real provider (not the generic
		 * fallback) is active. The WPML product-id translation shim is a
		 * separate concern and registers whenever WPML's `wpml_object_id`
		 * filter exists, regardless of the currency provider.
		 *
		 * Some multi-currency plugins (notably YayCurrency, but the same
		 * pattern applies to any third party) ship their own TeraWallet
		 * compatibility modules that hook the same `woo_wallet_*` filters this
		 * integration owns. Stacking both produces compounded conversions
		 * (e.g. 10 EUR → 1111.11 → 123,456.79), which is how this manifests as
		 * a wildly inflated topup order amount. Before registering our own
		 * hooks we sweep any known third-party copies via
		 * `unregister_third_party_wallet_hooks()` so the manager-backed
		 * abstraction is the sole owner.
		 */
		public function __construct() {
			if ( self::$hooks_registered ) {
				return;
			}
			self::$hooks_registered = true;

			$manager  = Woo_Wallet_Currency_Manager::instance();
			$provider = $manager->get_active_provider();

			if ( $provider && 'generic' !== $provider->get_id() ) {
				$this->unregister_third_party_wallet_hooks();

				add_filter( 'woo_wallet_amount', array( $this, 'filter_woo_wallet_amount' ), 10, 3 );
				add_filter( 'woo_wallet_rechargeable_amount', array( $this, 'filter_woo_wallet_rechargeable_amount' ) );
				add_filter( 'woo_wallet_current_balance', array( $this, 'filter_woo_wallet_current_balance' ), 10, 3 );
				add_filter( 'woo_wallet_get_option__wallet_settings_general_max_topup_amount', array( $this, 'filter_settings_option' ) );
				add_filter( 'woo_wallet_get_option__wallet_settings_general_min_topup_amount', array( $this, 'filter_settings_option' ) );
				add_filter( 'woo_wallet_get_option__wallet_settings_general_min_transfer_amount', array( $this, 'filter_settings_option' ) );
				add_filter( 'woo_wallet_form_cart_cashback_amount', array( $this, 'filter_cashback_amount' ) );
				add_filter( 'woo_wallet_form_order_cashback_amount', array( $this, 'filter_order_cashback_amount' ), 10, 2 );
			}

			if ( has_filter( 'wpml_object_id' ) ) {
				add_filter( 'woo_wallet_rechargeable_product_id', array( $this, 'filter_rechargeable_product_id' ) );
			}
		}

		/**
		 * Drop wallet-side hooks registered by third-party multi-currency
		 * plugins so our manager-backed handlers are the only ones that fire.
		 *
		 * Currently sweeps:
		 *  - YayCurrency's `\Yay_Currency\Engine\Compatibles\WooCommerceTeraWallet`
		 *  - CURCY's `WOOMULTI_CURRENCY_F_Plugin_Woo_Wallet`
		 *
		 * The second one is instantiated anonymously by VillaTheme's
		 * `vi_include_folder()` autoloader, so we don't have a singleton
		 * handle to call `remove_filter` against — instead we walk the
		 * `$wp_filter` callback list for each wallet hook and rip any
		 * callback whose owner is one of the known compat classes.
		 * Add new entries to `$known_compat_classes` when another MC
		 * plugin's adapter is found stacking on these filters.
		 *
		 * @return void
		 */
		private function unregister_third_party_wallet_hooks() {
			$known_compat_classes = array(
				'\Yay_Currency\Engine\Compatibles\WooCommerceTeraWallet',
				'WOOMULTI_CURRENCY_F_Plugin_Woo_Wallet',
			);
			$wallet_hooks         = array(
				'woo_wallet_rechargeable_amount',
				'woo_wallet_amount',
				'woo_wallet_current_balance',
				'woo_wallet_form_cart_cashback_amount',
				'woo_wallet_credit_purchase_amount',
				'woo_wallet_get_option__wallet_settings_general_max_topup_amount',
				'woo_wallet_get_option__wallet_settings_general_min_topup_amount',
				'woo_wallet_get_option__wallet_settings_general_min_transfer_amount',
				'woo_wallet_new_user_registration_credit_amount',
				'woo_wallet_cashback_notice_text',
			);

			global $wp_filter;
			foreach ( $wallet_hooks as $hook ) {
				if ( empty( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks ) ) {
					continue;
				}
				foreach ( $wp_filter[ $hook ]->callbacks as $priority => $callbacks ) {
					foreach ( $callbacks as $cb_entry ) {
						$fn = isset( $cb_entry['function'] ) ? $cb_entry['function'] : null;
						if ( ! is_array( $fn ) || ! is_object( $fn[0] ) ) {
							continue;
						}
						foreach ( $known_compat_classes as $compat_class ) {
							if ( is_a( $fn[0], ltrim( $compat_class, '\\' ) ) ) {
								remove_filter( $hook, $fn, $priority );
								break;
							}
						}
					}
				}
			}
		}

		/**
		 * Convert a stored amount into the storefront's active currency.
		 *
		 * @param float    $amount   Amount in $currency.
		 * @param string   $currency Source ISO code.
		 * @param int|null $user_id  Customer id (unused — kept for filter signature compatibility).
		 * @return float
		 */
		public function filter_woo_wallet_amount( $amount, $currency, $user_id = null ) {
			unset( $user_id );
			$manager = Woo_Wallet_Currency_Manager::instance();
			return (float) $manager->convert( $amount, $currency, $manager->get_active_currency() );
		}

		/**
		 * Convert an amount entered in the active currency back to the base
		 * currency for top-up order creation.
		 *
		 * @param float $amount Amount in active currency.
		 * @return float
		 */
		public function filter_woo_wallet_rechargeable_amount( $amount ) {
			$manager = Woo_Wallet_Currency_Manager::instance();
			return (float) $manager->convert( $amount, $manager->get_active_currency(), $manager->get_base_currency() );
		}

		/**
		 * Recompute wallet balance for display in the active storefront currency.
		 *
		 * Pre-1.6 the underlying `SUM(...)` in `Woo_Wallet_Wallet::get_wallet_balance()`
		 * had no `currency` filter, so on stores with mixed-currency rows the raw
		 * SUM was meaningless. This filter re-summed each row through the converter
		 * to coerce the result into a single (active) currency.
		 *
		 * After 1.6:
		 *  - In per_currency mode, the raw SUM is already scoped to a single
		 *    currency (the one the caller passed via `$balance_currency`), so we
		 *    pass it through. Re-summing across all currencies would silently
		 *    blend sub-balances and give a wrong answer.
		 *  - In single_base mode, every new row is normalized to base on write
		 *    so the raw SUM is the balance in base. We still need to convert to
		 *    the active currency for the storefront display, which is what the
		 *    per-row loop does — and it also remains correct for legacy pre-1.6
		 *    rows that may carry a non-base `currency` because of the asymmetry
		 *    bugs the line-level fixes in this PR address.
		 *
		 * @param float  $balance          Current SUM-based balance.
		 * @param int    $user_id          User id.
		 * @param string $balance_currency Currency the balance is denominated in
		 *                                 (passed through as the third filter arg
		 *                                 by `get_wallet_balance()` from 1.6).
		 * @return float
		 */
		public function filter_woo_wallet_current_balance( $balance, $user_id, $balance_currency = '' ) {
			$manager = Woo_Wallet_Currency_Manager::instance();
			$active  = '' !== $balance_currency ? strtoupper( (string) $balance_currency ) : $manager->get_active_currency();

			// per_currency mode: raw SUM is already scoped to $balance_currency.
			if ( apply_filters( 'woo_wallet_enable_per_currency_mode', false )
				&& 'per_currency' === woo_wallet()->settings_api->get_option( 'wallet_currency_mode', '_wallet_settings_general', 'single_base' ) ) {
				return (float) $balance;
			}

			unset( $balance );

			$credit_total = 0;
			$debit_total  = 0;

			$credits = get_wallet_transactions(
				array(
					'user_id' => $user_id,
					'where'   => array(
						array(
							'key'   => 'type',
							'value' => 'credit',
						),
					),
				)
			);
			foreach ( $credits as $row ) {
				$credit_total += (float) $manager->convert( $row->amount, $row->currency, $active );
			}

			$debits = get_wallet_transactions(
				array(
					'user_id' => $user_id,
					'where'   => array(
						array(
							'key'   => 'type',
							'value' => 'debit',
						),
					),
				)
			);
			foreach ( $debits as $row ) {
				$debit_total += (float) $manager->convert( $row->amount, $row->currency, $active );
			}

			return max( 0, $credit_total - $debit_total );
		}

		/**
		 * Convert a numeric setting (min/max top-up, min transfer) from base
		 * to active currency for frontend display.
		 *
		 * @param mixed $option_value Setting value as stored.
		 * @return mixed Converted value, or the original if non-numeric / admin context.
		 */
		public function filter_settings_option( $option_value ) {
			if ( is_admin() || ! is_numeric( $option_value ) ) {
				return $option_value;
			}
			$manager = Woo_Wallet_Currency_Manager::instance();
			return $manager->convert( (float) $option_value, $manager->get_base_currency(), $manager->get_active_currency() );
		}

		/**
		 * Cart-rule cashback in the active currency.
		 *
		 * Arithmetic mirrors the previous WOOCS / WCML adapters verbatim
		 * (down to the 10-vs-0 default mismatch on `min_cart_amount` —
		 * preserved here intentionally so PR1 stays behavior-neutral; the
		 * cashback path is revisited in PR2).
		 *
		 * @param float $cashback_amount Default cashback amount.
		 * @return float
		 */
		public function filter_cashback_amount( $cashback_amount ) {
			$cashback_rule         = woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' );
			$global_cashbak_type   = woo_wallet()->settings_api->get_option( 'cashback_type', '_wallet_settings_credit', 'percent' );
			$global_cashbak_amount = woo_wallet()->settings_api->get_option( 'cashback_amount', '_wallet_settings_credit', 0 );

			$manager = Woo_Wallet_Currency_Manager::instance();
			$base    = $manager->get_base_currency();
			$active  = $manager->get_active_currency();

			$max_cashback_amount = $manager->convert(
				floatval( woo_wallet()->settings_api->get_option( 'max_cashback_amount', '_wallet_settings_credit', 0 ) ),
				$base,
				$active
			);

			if ( 'cart' !== $cashback_rule ) {
				return $cashback_amount;
			}

			$cashback_amount      = 0;
			$min_cart_existence   = woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 10 );
			$min_cart_threshold   = woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 0 );
			$cart_subtotal_active = $manager->convert( WC()->cart->get_subtotal( 'edit' ), $base, $active );

			if ( 0 != $min_cart_existence && $cart_subtotal_active >= $min_cart_threshold ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
				if ( 'percent' === $global_cashbak_type ) {
					$percent_cashback = WC()->cart->get_subtotal( 'edit' ) * ( $global_cashbak_amount / 100 );
					if ( $max_cashback_amount && $percent_cashback > $max_cashback_amount ) {
						$cashback_amount += $max_cashback_amount;
					} else {
						$cashback_amount += $percent_cashback;
					}
				} else {
					$cashback_amount += (float) $manager->convert( floatval( $global_cashbak_amount ), $base, $active );
				}
			}

			return $cashback_amount;
		}

		/**
		 * Convert order-side cashback settings from base currency to the order's
		 * currency so `min_cart_amount` and `max_cashback_amount` are evaluated
		 * against the correct amount for non-base orders.
		 *
		 * Mirrors `filter_cashback_amount()` (cart-side) for the order path.
		 *
		 * @param float $cashback_amount Default cashback amount computed for the order.
		 * @param int   $order_id        Order id.
		 * @return float
		 *
		 * @since 1.6.1 (R5)
		 */
		public function filter_order_cashback_amount( $cashback_amount, $order_id ) {
			$cashback_rule = woo_wallet()->settings_api->get_option( 'cashback_rule', '_wallet_settings_credit', 'cart' );
			if ( 'cart' !== $cashback_rule ) {
				// Per-product / per-category rules compute per-line amounts that are
				// not directly comparable to a currency-converted threshold, so the
				// post-loop cap is handled in calculate_cashback_form_order() for now.
				return $cashback_amount;
			}

			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				return $cashback_amount;
			}

			$manager        = Woo_Wallet_Currency_Manager::instance();
			$base           = $manager->get_base_currency();
			$order_currency = $order->get_currency( 'edit' );

			if ( $order_currency === $base ) {
				return $cashback_amount;
			}

			$global_cashbak_type   = woo_wallet()->settings_api->get_option( 'cashback_type', '_wallet_settings_credit', 'percent' );
			$global_cashbak_amount = woo_wallet()->settings_api->get_option( 'cashback_amount', '_wallet_settings_credit', 0 );

			$max_cashback_amount = $manager->convert(
				floatval( woo_wallet()->settings_api->get_option( 'max_cashback_amount', '_wallet_settings_credit', 0 ) ),
				$base,
				$order_currency
			);

			$min_cart_existence = woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 10 );
			$min_cart_threshold = $manager->convert(
				floatval( woo_wallet()->settings_api->get_option( 'min_cart_amount', '_wallet_settings_credit', 0 ) ),
				$base,
				$order_currency
			);

			$order_total = (float) $order->get_total( 'edit' );

			if ( 0 == $min_cart_existence || $order_total < $min_cart_threshold ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual
				return 0;
			}

			$recomputed = 0;
			if ( 'percent' === $global_cashbak_type ) {
				$percent_cashback = $order_total * ( (float) $global_cashbak_amount / 100 );
				if ( $max_cashback_amount && $percent_cashback > $max_cashback_amount ) {
					$recomputed = $max_cashback_amount;
				} else {
					$recomputed = $percent_cashback;
				}
			} else {
				$recomputed = $manager->convert( floatval( $global_cashbak_amount ), $base, $order_currency );
			}

			return $recomputed;
		}

		/**
		 * Translate the wallet top-up product id into the current language.
		 *
		 * Not strictly a currency concern — it lived in the old WCML
		 * adapter alongside the conversion code, so keeping it here keeps
		 * everything WPML-shaped in one file.
		 *
		 * @param int $product_id Default product id.
		 * @return int Possibly-translated product id.
		 */
		public function filter_rechargeable_product_id( $product_id ) {
			$language = apply_filters( 'wpml_current_language', null );
			return (int) apply_filters( 'wpml_object_id', $product_id, 'product', true, $language );
		}
	}
}
