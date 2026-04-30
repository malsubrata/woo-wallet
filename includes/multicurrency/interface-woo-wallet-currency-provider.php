<?php
/**
 * Currency provider interface.
 *
 * Each multi-currency integration (WOOCS, WCML, CURCY, Aelia, etc.) implements
 * this interface so the core wallet can ask conversion / state questions
 * without knowing the third-party plugin's API.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! interface_exists( 'Woo_Wallet_Currency_Provider' ) ) {
	/**
	 * Contract every multi-currency provider must satisfy.
	 *
	 * Method signatures intentionally omit scalar / return typehints so the
	 * plugin keeps its declared `php >=5.6` floor (composer.json).
	 */
	interface Woo_Wallet_Currency_Provider {

		/**
		 * Stable provider id (e.g. 'woocs', 'wcml', 'curcy', 'aelia', 'generic').
		 *
		 * @return string
		 */
		public function get_id();

		/**
		 * Human-readable label, used in admin diagnostics.
		 *
		 * @return string
		 */
		public function get_label();

		/**
		 * True when the underlying plugin is active and usable in the
		 * current request. Implementations should be cheap (class_exists,
		 * global var checks) — this can be called from hot paths.
		 *
		 * @return bool
		 */
		public function is_available();

		/**
		 * The currency the customer currently sees on the storefront.
		 * Falls back to the WooCommerce base currency when unknown.
		 *
		 * @return string ISO 4217 code (uppercase, 3 letters).
		 */
		public function get_active_currency();

		/**
		 * The WooCommerce base / shop currency.
		 *
		 * @return string ISO 4217 code.
		 */
		public function get_base_currency();

		/**
		 * Currencies the provider can convert to/from.
		 * Always includes the base currency.
		 *
		 * @return string[] List of ISO 4217 codes.
		 */
		public function get_supported_currencies();

		/**
		 * Convert an amount between two currencies.
		 *
		 * Returning null signals "unknown / cannot convert" — the caller
		 * (currency manager) decides whether to fail open (return original
		 * amount) or hard-fail. Providers must NEVER throw.
		 *
		 * @param float  $amount Amount in $from currency.
		 * @param string $from   Source ISO code.
		 * @param string $to     Target ISO code.
		 * @return float|null Converted amount, or null if unknown.
		 */
		public function convert( $amount, $from, $to );

		/**
		 * Exchange rate between two currencies (1 unit of $from in $to).
		 *
		 * Returning null signals unknown rate. Used for audit (writing
		 * `original_rate` to the ledger) — not the primary conversion path.
		 *
		 * @param string $from Source ISO code.
		 * @param string $to   Target ISO code.
		 * @return float|null
		 */
		public function get_rate( $from, $to );
	}
}
