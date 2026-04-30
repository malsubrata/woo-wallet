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
		 * Register hooks.
		 *
		 * Currency hooks register only when a real provider (not the generic
		 * fallback) is active. The WPML product-id translation shim is a
		 * separate concern and registers whenever WPML's `wpml_object_id`
		 * filter exists, regardless of the currency provider.
		 */
		public function __construct() {
			$manager  = Woo_Wallet_Currency_Manager::instance();
			$provider = $manager->get_active_provider();

			if ( $provider && 'generic' !== $provider->get_id() ) {
				add_filter( 'woo_wallet_amount', array( $this, 'filter_woo_wallet_amount' ), 10, 3 );
				add_filter( 'woo_wallet_rechargeable_amount', array( $this, 'filter_woo_wallet_rechargeable_amount' ) );
				add_filter( 'woo_wallet_current_balance', array( $this, 'filter_woo_wallet_current_balance' ), 10, 2 );
				add_filter( 'woo_wallet_get_option__wallet_settings_general_max_topup_amount', array( $this, 'filter_settings_option' ) );
				add_filter( 'woo_wallet_get_option__wallet_settings_general_min_topup_amount', array( $this, 'filter_settings_option' ) );
				add_filter( 'woo_wallet_get_option__wallet_settings_general_min_transfer_amount', array( $this, 'filter_settings_option' ) );
				add_filter( 'woo_wallet_form_cart_cashback_amount', array( $this, 'filter_cashback_amount' ) );
			}

			if ( has_filter( 'wpml_object_id' ) ) {
				add_filter( 'woo_wallet_rechargeable_product_id', array( $this, 'filter_rechargeable_product_id' ) );
			}
		}

		/**
		 * Convert a stored amount into the storefront's active currency.
		 *
		 * @param float       $amount   Amount in $currency.
		 * @param string      $currency Source ISO code.
		 * @param int|null    $user_id  Customer id (unused — kept for filter signature compatibility).
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
		 * Recompute wallet balance by re-summing each row in the active
		 * currency.
		 *
		 * Workaround for the mixed-currency `SUM()` issue in
		 * `Woo_Wallet_Wallet::get_wallet_balance()` (which sums credits and
		 * debits without filtering on `currency`). PR2 fixes the underlying
		 * read; this filter then becomes redundant and can go away.
		 *
		 * @param float $balance Current SUM-based balance.
		 * @param int   $user_id User id.
		 * @return float
		 */
		public function filter_woo_wallet_current_balance( $balance, $user_id ) {
			unset( $balance );
			$manager = Woo_Wallet_Currency_Manager::instance();
			$active  = $manager->get_active_currency();

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
