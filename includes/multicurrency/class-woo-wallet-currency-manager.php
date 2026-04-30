<?php
/**
 * Currency manager / registry.
 *
 * Singleton that owns the list of registered providers, picks an active
 * provider for the current request, and offers a fail-open `convert()`
 * helper that the rest of the plugin can call without caring which
 * third-party multi-currency plugin is installed.
 *
 * @package StandaloneTech
 * @since 1.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
	/**
	 * Provider registry.
	 */
	class Woo_Wallet_Currency_Manager {

		/**
		 * Singleton instance.
		 *
		 * @var Woo_Wallet_Currency_Manager|null
		 */
		private static $instance = null;

		/**
		 * Registered providers, keyed by id.
		 *
		 * Each entry: array( 'provider' => Woo_Wallet_Currency_Provider, 'priority' => int ).
		 *
		 * @var array<string, array>
		 */
		private $providers = array();

		/**
		 * Cached active provider id (null = not resolved yet).
		 *
		 * @var string|null
		 */
		private $active_provider_id = null;

		/**
		 * Singleton accessor.
		 *
		 * @return Woo_Wallet_Currency_Manager
		 */
		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private constructor — use `instance()`.
		 */
		private function __construct() {}

		/**
		 * Register a provider.
		 *
		 * Lower priority numbers win — built-in adapters use 10, the generic
		 * fallback uses 100. Third parties can register at priority < 10 to
		 * override a built-in adapter.
		 *
		 * @param Woo_Wallet_Currency_Provider $provider Provider instance.
		 * @param int                          $priority Selection priority (default 10).
		 * @return void
		 */
		public function register_provider( $provider, $priority = 10 ) {
			if ( ! ( $provider instanceof Woo_Wallet_Currency_Provider ) ) {
				return;
			}
			$id = $provider->get_id();
			if ( '' === $id ) {
				return;
			}
			$this->providers[ $id ] = array(
				'provider' => $provider,
				'priority' => (int) $priority,
			);
			// Invalidate the cached active id when the registry changes.
			$this->active_provider_id = null;
		}

		/**
		 * Unregister a provider by id. Mainly used in tests / on plugin
		 * deactivation.
		 *
		 * @param string $id Provider id.
		 * @return void
		 */
		public function unregister_provider( $id ) {
			unset( $this->providers[ $id ] );
			$this->active_provider_id = null;
		}

		/**
		 * Return all registered providers (regardless of availability).
		 *
		 * @return Woo_Wallet_Currency_Provider[] Indexed by id.
		 */
		public function get_providers() {
			$out = array();
			foreach ( $this->providers as $id => $entry ) {
				$out[ $id ] = $entry['provider'];
			}
			return $out;
		}

		/**
		 * Get a single provider by id.
		 *
		 * @param string $id Provider id.
		 * @return Woo_Wallet_Currency_Provider|null
		 */
		public function get_provider( $id ) {
			return isset( $this->providers[ $id ] ) ? $this->providers[ $id ]['provider'] : null;
		}

		/**
		 * Pick the active provider for this request.
		 *
		 * Selects the available provider with the lowest priority number.
		 * Falls back to the generic provider if nothing else is available.
		 * Result is cached per-request and invalidated whenever providers
		 * are (un)registered.
		 *
		 * Filter: `woo_wallet_active_currency_provider` — return a provider
		 * id to force a specific selection (admin override, debugging).
		 *
		 * @return Woo_Wallet_Currency_Provider|null
		 */
		public function get_active_provider() {
			if ( null !== $this->active_provider_id && isset( $this->providers[ $this->active_provider_id ] ) ) {
				return $this->providers[ $this->active_provider_id ]['provider'];
			}

			$forced = apply_filters( 'woo_wallet_active_currency_provider', null );
			if ( is_string( $forced ) && isset( $this->providers[ $forced ] ) ) {
				$provider = $this->providers[ $forced ]['provider'];
				if ( $provider->is_available() ) {
					$this->active_provider_id = $forced;
					return $provider;
				}
			}

			$candidates = array();
			foreach ( $this->providers as $id => $entry ) {
				if ( $entry['provider']->is_available() ) {
					$candidates[] = array(
						'id'       => $id,
						'priority' => $entry['priority'],
					);
				}
			}

			if ( empty( $candidates ) ) {
				$this->active_provider_id = null;
				return null;
			}

			usort(
				$candidates,
				function ( $a, $b ) {
					if ( $a['priority'] === $b['priority'] ) {
						return strcmp( $a['id'], $b['id'] );
					}
					return $a['priority'] - $b['priority'];
				}
			);

			$this->active_provider_id = $candidates[0]['id'];
			return $this->providers[ $this->active_provider_id ]['provider'];
		}

		/**
		 * Active provider id, or empty string if none.
		 *
		 * @return string
		 */
		public function get_active_provider_id() {
			$provider = $this->get_active_provider();
			return $provider ? $provider->get_id() : '';
		}

		/**
		 * Currency the storefront is currently displaying.
		 *
		 * @return string ISO 4217 code.
		 */
		public function get_active_currency() {
			$provider = $this->get_active_provider();
			if ( $provider ) {
				return $provider->get_active_currency();
			}
			$base = get_option( 'woocommerce_currency' );
			return is_string( $base ) && '' !== $base ? strtoupper( $base ) : 'USD';
		}

		/**
		 * WooCommerce base / shop currency.
		 *
		 * @return string ISO 4217 code.
		 */
		public function get_base_currency() {
			$provider = $this->get_active_provider();
			if ( $provider ) {
				return $provider->get_base_currency();
			}
			$base = get_option( 'woocommerce_currency' );
			return is_string( $base ) && '' !== $base ? strtoupper( $base ) : 'USD';
		}

		/**
		 * Convert an amount via the active provider.
		 *
		 * Fail-open: if the provider returns null (unknown rate) or no
		 * provider is available, return the original amount unchanged and
		 * log a warning. Rationale: silently zeroing a balance is far worse
		 * than letting an unconverted amount through; the per-row audit
		 * columns shipped in PR2 (`original_currency`, `original_rate`)
		 * make these cases recoverable.
		 *
		 * @param float  $amount Amount in $from currency.
		 * @param string $from   Source ISO code.
		 * @param string $to     Target ISO code.
		 * @return float
		 */
		public function convert( $amount, $from, $to ) {
			$amount = (float) $amount;
			$from   = strtoupper( (string) $from );
			$to     = strtoupper( (string) $to );

			if ( $from === $to || '' === $from || '' === $to ) {
				return $amount;
			}

			$provider = $this->get_active_provider();
			if ( ! $provider ) {
				$this->log_conversion_warning( 'no provider available', $amount, $from, $to );
				return $amount;
			}

			$converted = $provider->convert( $amount, $from, $to );
			if ( null === $converted || ! is_numeric( $converted ) ) {
				$this->log_conversion_warning(
					'provider ' . $provider->get_id() . ' returned null',
					$amount,
					$from,
					$to
				);
				return $amount;
			}

			return (float) $converted;
		}

		/**
		 * Exchange rate via the active provider, or null if unknown.
		 *
		 * @param string $from Source ISO code.
		 * @param string $to   Target ISO code.
		 * @return float|null
		 */
		public function get_rate( $from, $to ) {
			$provider = $this->get_active_provider();
			if ( ! $provider ) {
				return null;
			}
			return $provider->get_rate( $from, $to );
		}

		/**
		 * Emit a one-line warning to the WooCommerce logger (channel
		 * `woo-wallet-currency`) when conversion falls open.
		 *
		 * @param string $reason Human reason.
		 * @param float  $amount Amount.
		 * @param string $from   Source ISO.
		 * @param string $to     Target ISO.
		 * @return void
		 */
		private function log_conversion_warning( $reason, $amount, $from, $to ) {
			if ( ! function_exists( 'wc_get_logger' ) ) {
				return;
			}
			$logger = wc_get_logger();
			if ( ! $logger ) {
				return;
			}
			$logger->warning(
				sprintf(
					'Currency conversion fell open (%s) for %s %s -> %s',
					$reason,
					number_format( (float) $amount, 4, '.', '' ),
					$from,
					$to
				),
				array( 'source' => 'woo-wallet-currency' )
			);
		}
	}
}
