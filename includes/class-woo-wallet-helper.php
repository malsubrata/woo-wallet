<?php
/**
 * Helper class file.
 *
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/**
 * Provides static methods as helpers.
 */
class WOO_Wallet_Helper {
	/**
	 * Order line-item meta on a top-up line created from 1.7.2 on. Such a line
	 * credits the amount the customer typed (pre-discount, tax-inclusive); a
	 * line without it predates 1.7.2 and keeps its original credit rule.
	 */
	const TOPUP_GROSS_META = '_woo_wallet_topup_gross';

	/**
	 * Net (pre-tax) price for a top-up whose typed amount is tax-inclusive, so
	 * the customer pays exactly the typed amount once tax is added back.
	 *
	 * @param float      $gross   Typed, tax-inclusive amount.
	 * @param WC_Product $product Top-up product.
	 * @param array      $rates   Tax rates that will apply (WC_Tax::get_rates() shape).
	 * @return float
	 */
	public static function get_topup_net_price( float $gross, WC_Product $product, array $rates ): float {
		if ( ! wc_tax_enabled() || ! $product->is_taxable() || empty( $rates ) ) {
			return $gross;
		}
		return $gross - array_sum( WC_Tax::calc_tax( $gross, $rates, true ) );
	}

	/**
	 * Wallet value a top-up order credits, before any gateway charge.
	 *
	 * From 1.7.2 a top-up line credits its pre-discount, tax-inclusive amount —
	 * the figure the customer typed. Older lines (no TOPUP_GROSS_META) keep the
	 * pre-1.7.2 rule — post-discount, pre-tax — so orders still pending at
	 * upgrade credit exactly what they would have.
	 *
	 * @param WC_Order $order      Top-up order.
	 * @param int      $product_id Top-up product id.
	 * @return float
	 */
	public static function get_topup_credit_amount( WC_Order $order, int $product_id ): float {
		$amount = 0.0;
		foreach ( $order->get_items() as $item ) {
			if ( $product_id !== $item->get_product_id() ) {
				continue;
			}
			$amount += $item->get_meta( self::TOPUP_GROSS_META )
				? (float) $item->get_subtotal() + (float) $item->get_subtotal_tax()
				: (float) $item->get_total();
		}
		return $amount;
	}

	/**
	 * Store base currency symbol (optionally with the ISO code) for admin amount fields.
	 *
	 * Reads the symbol table directly instead of get_woocommerce_currency_symbol(),
	 * whose filter multi-currency switchers hijack to return the active currency.
	 *
	 * @param bool $with_code Append the ISO code, e.g. "₹ INR".
	 * @return string Plain text (no HTML entities).
	 */
	public static function get_base_currency_symbol( bool $with_code = false ): string {
		if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
			$code = Woo_Wallet_Currency_Manager::instance()->get_base_currency();
		} else {
			$code = strtoupper( (string) get_option( 'woocommerce_currency' ) );
		}
		$code    = '' !== $code ? $code : 'USD';
		$symbols = get_woocommerce_currency_symbols();
		$symbol  = html_entity_decode( $symbols[ $code ] ?? $code, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return ( $with_code && $symbol !== $code ) ? $symbol . ' ' . $code : $symbol;
	}

	/**
	 * Name shown for the other party on a wallet transfer row: first + last
	 * name, else a display name that is not the username, else a masked email
	 * (j***@example.com). Never the username or the full email.
	 *
	 * @param WP_User $user User.
	 * @return string
	 */
	public static function get_transfer_party_name( WP_User $user ): string {
		// Names can hold markup when written straight to user meta; keep plain text only.
		$name    = trim( wp_strip_all_tags( $user->first_name . ' ' . $user->last_name ) );
		$display = trim( wp_strip_all_tags( $user->display_name ) );
		if ( '' === $name && '' !== $display && $display !== $user->user_login && $display !== $user->user_email ) {
			$name = $display;
		}
		if ( '' === $name ) {
			$parts = explode( '@', (string) $user->user_email, 2 );
			$name  = substr( $parts[0], 0, 1 ) . '***' . ( isset( $parts[1] ) ? '@' . $parts[1] : '' );
		}
		return $name;
	}

	/**
	 * Format an amount for an order note in the ORDER's currency — not the
	 * currency of whoever triggers the note (admin cookie, cron, webhook).
	 *
	 * @param float    $amount Amount in the order currency.
	 * @param WC_Order $order  Order.
	 * @return string
	 */
	public static function order_note_price( $amount, $order ): string {
		return wc_price( $amount, woo_wallet_wc_price_args( $order->get_customer_id(), array( 'currency' => $order->get_currency() ) ) );
	}

	/**
	 * Save order meta data.
	 *
	 * @param WC_Order|int $order order.
	 * @param string       $key key.
	 * @param mixed        $value value.
	 * @param bool         $do_save do_save.
	 * @return void
	 */
	public static function update_order_meta_data( $order, $key, $value, $do_save = true ) : void {
		if ( ! $order instanceof WC_Order ) {
			$order = wc_get_order( $order );
		}
		if ( is_callable( array( $order, 'update_meta_data' ) ) ) {
			$order->update_meta_data( $key, $value );
		}
		if ( is_callable( array( $order, 'save' ) ) && $do_save ) {
			$order->save();
		}
	}

	/**
	 * Load an order with meta re-read from the datastore.
	 *
	 * Drops in-request / object-cache copies first so a concurrent cancel or
	 * refund that already claimed markers is visible inside a GET_LOCK critical
	 * section (plain `wc_get_order()` often returns the stale cached instance).
	 *
	 * @param int $order_id Order id.
	 * @return WC_Order|false
	 */
	public static function get_order_for_update( $order_id ) {
		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return false;
		}

		wp_cache_delete( 'order-' . $order_id, 'orders' );
		wp_cache_delete( $order_id, 'posts' );

		if ( class_exists( '\Automattic\WooCommerce\Caches\OrderCache' ) && function_exists( 'wc_get_container' ) ) {
			try {
				$order_cache = wc_get_container()->get( \Automattic\WooCommerce\Caches\OrderCache::class );
				if ( $order_cache && is_callable( array( $order_cache, 'remove' ) ) ) {
					$order_cache->remove( $order_id );
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Container/cache unavailable — fall through to read_meta_data.
			}
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return false;
		}
		if ( is_callable( array( $order, 'read_meta_data' ) ) ) {
			$order->read_meta_data( true );
		}
		return $order;
	}

	/**
	 * Hold a select field to the values it actually offers.
	 *
	 * The settings UI posts every field in a section, falling back to the
	 * field's declared `default` for anything the admin never touched — so a
	 * select with no `default` in its schema arrives as an empty string while
	 * the browser displays its first option as though it were selected. An
	 * enum field has no empty state, so an empty or unknown value is resolved
	 * to the declared default rather than persisted.
	 *
	 * @param mixed $value Raw posted value.
	 * @param array $field Field definition.
	 * @return string
	 */
	public static function constrain_select_value( $value, array $field ): string {
		$value   = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
		$options = array_map( 'strval', array_keys( (array) ( $field['options'] ?? array() ) ) );

		if ( in_array( $value, $options, true ) ) {
			return $value;
		}

		if ( isset( $field['default'] ) && is_scalar( $field['default'] ) ) {
			return (string) $field['default'];
		}

		return isset( $options[0] ) ? $options[0] : '';
	}

	/**
	 * Order statuses that trigger cashback, in the shape the rest of the
	 * plugin speaks.
	 *
	 * The `process_cashback_status` setting offers `wc_get_order_statuses()`
	 * as its options, whose keys are prefixed (`wc-processing`), while the
	 * field default, the `woocommerce_order_status_{$status}` hook name and
	 * `WC_Order::get_status()` are all unprefixed. A store that never saved
	 * the setting therefore ran on the correct shape and a store that saved
	 * it once did not. Both readers go through here so they cannot drift.
	 *
	 * The `wallet_cashback_order_status` filter keeps its existing contract:
	 * it still receives the stored value and still decides the final list.
	 * Normalisation runs after it, and is a no-op on unprefixed input.
	 *
	 * @return array Unprefixed order statuses.
	 */
	public static function get_cashback_order_statuses(): array {
		$statuses = woo_wallet()->settings_api->get_option(
			'process_cashback_status',
			'_wallet_settings_credit',
			array( 'processing', 'completed' )
		);

		return self::normalize_order_statuses( apply_filters( 'wallet_cashback_order_status', $statuses ) );
	}

	/**
	 * Offline gateway ids, left out of the default "Allowed Payment Gateways"
	 * for top-ups. An admin can still select them on purpose.
	 *
	 * @since 1.7.3
	 * @return array Gateway ids.
	 */
	public static function get_topup_offline_gateways(): array {
		$gateways  = array( 'bacs', 'cheque', 'cod' );
		$providers = 'Automattic\WooCommerce\Internal\Admin\Settings\PaymentsProviders';
		// WooCommerce internal class, newer WooCommerce only — read it when present.
		if ( class_exists( $providers ) && defined( $providers . '::OFFLINE_METHODS' ) ) {
			$gateways = array_merge( $gateways, (array) constant( $providers . '::OFFLINE_METHODS' ) );
		}
		return array_values( array_unique( (array) apply_filters( 'woo_wallet_topup_offline_gateways', $gateways ) ) );
	}

	/**
	 * Gateway ids allowed to pay a wallet top-up ("Allowed Payment Gateways").
	 * One list for cart checkout, the order-pay page and the REST top-up.
	 * The wallet gateway is never allowed.
	 *
	 * @since 1.7.4
	 * @return array Gateway ids.
	 */
	public static function get_topup_allowed_gateways(): array {
		$default = array();
		$offline = self::get_topup_offline_gateways();
		foreach ( WC()->payment_gateways()->payment_gateways as $gateway ) {
			if ( 'yes' === $gateway->enabled && 'wallet' !== $gateway->id && ! in_array( $gateway->id, $offline, true ) ) {
				$default[] = $gateway->id;
			}
		}
		$allowed = (array) woo_wallet()->settings_api->get_option( 'allowed_payment_gateways', '_wallet_settings_general', $default );
		return array_values( array_diff( $allowed, array( 'wallet' ) ) );
	}

	/**
	 * Drop WooCommerce's `wc-` status prefix.
	 *
	 * Anchored and applied once: a status slug that legitimately contains
	 * `wc-` further along keeps it, and `wc-wc-shipped` becomes `wc-shipped`.
	 *
	 * @param mixed $statuses Order statuses, in either shape. A scalar from a
	 *                        third-party filter is tolerated.
	 * @return array Unprefixed, de-duplicated order statuses.
	 */
	public static function normalize_order_statuses( $statuses ): array {
		$normalized = array();

		foreach ( (array) $statuses as $status ) {
			if ( ! is_scalar( $status ) ) {
				continue;
			}
			$status = preg_replace( '/^wc-/', '', (string) $status );
			if ( '' !== $status && ! in_array( $status, $normalized, true ) ) {
				$normalized[] = $status;
			}
		}

		return $normalized;
	}

	/**
	 * Client IP address, without trusting headers the visitor can set.
	 *
	 * Uses REMOTE_ADDR. A forwarded header is read only when REMOTE_ADDR is a
	 * trusted proxy: listed in the `woo_wallet_trusted_proxies` filter, or a
	 * private/reserved address (a reverse proxy on the same network). Then
	 * X-Forwarded-For (right-most address that is not a proxy), else X-Real-IP.
	 * A fingerprint for abuse checks, not an authoritative address.
	 *
	 * @since 1.7.4
	 * @return string IP address, or '' when unknown.
	 */
	public static function get_client_ip(): string {
		$remote = self::clean_ip( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by clean_ip().
		/**
		 * Proxy addresses (IPs or CIDR ranges) whose forwarded headers are
		 * trusted for the client IP. Private and reserved addresses are always
		 * treated as proxies. A public proxy or CDN in front of the site (e.g.
		 * Cloudflare, a public load balancer) must be listed here, unless the
		 * server already restores the visitor address in REMOTE_ADDR; otherwise
		 * every visitor is seen as the proxy. The proxy must append to (or
		 * overwrite) X-Forwarded-For. Invalid entries are ignored.
		 *
		 * @since 1.7.4
		 *
		 * @param string[] $proxies IPs or CIDR ranges. Default empty.
		 */
		$trusted   = array_filter( array_map( 'strval', (array) apply_filters( 'woo_wallet_trusted_proxies', array() ) ) );
		$is_proxy  = function ( $ip ) use ( $trusted ) {
			return ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) || self::ip_in_ranges( $ip, $trusted );
		};
		$client_ip = $remote;
		if ( $remote && $is_proxy( $remote ) ) {
			$found = '';
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				// Right to left: the first address that is not a proxy is the client.
				// Addresses a visitor adds sit to the left of it and are never reached.
				// An invalid hop stops the walk (REMOTE_ADDR is used).
				$hops = array_reverse( explode( ',', wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by clean_ip().
				$last = '';
				foreach ( $hops as $raw_hop ) {
					$hop = self::clean_ip( $raw_hop );
					if ( '' === $hop ) {
						$last = '';
						break;
					}
					$last = $hop;
					if ( ! $is_proxy( $hop ) ) {
						$found = $hop;
						break;
					}
				}
				if ( '' === $found ) {
					$found = $last; // Every hop is a proxy: the client is on the private network.
				}
			}
			if ( '' === $found && empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) {
				$found = self::clean_ip( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- validated by clean_ip().
			}
			$client_ip = '' !== $found ? $found : $remote;
		}
		/**
		 * Filters the client IP address used by the plugin's checks.
		 *
		 * @since 1.7.4
		 *
		 * @param string $client_ip   Client IP ('' when unknown).
		 * @param string $remote_addr Validated REMOTE_ADDR ('' when invalid).
		 */
		return (string) apply_filters( 'woo_wallet_client_ip', $client_ip, $remote );
	}

	/**
	 * A valid IP address from a header value, port and brackets removed, in
	 * canonical form (IPv4-mapped IPv6 becomes IPv4), or ''.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function clean_ip( $value ): string {
		$value = trim( is_string( $value ) ? $value : '' );
		// '1.2.3.4:80', '[2001:db8::1]', '[2001:db8::1]:80'.
		$value = (string) preg_replace( '/^(\d+\.\d+\.\d+\.\d+):\d+$|^\[([^\]]+)\](?::\d+)?$/', '$1$2', $value );
		if ( ! filter_var( $value, FILTER_VALIDATE_IP ) ) {
			return '';
		}
		$bin = inet_pton( $value );
		if ( 16 === strlen( $bin ) && "\0\0\0\0\0\0\0\0\0\0\xff\xff" === substr( $bin, 0, 12 ) ) {
			$bin = substr( $bin, 12 );
		}
		return (string) inet_ntop( $bin );
	}

	/**
	 * Whether an IP is in any of the given IPs / CIDR ranges.
	 *
	 * @param string   $ip     Valid IP address.
	 * @param string[] $ranges IPs or CIDR ranges; invalid entries are ignored.
	 * @return bool
	 */
	private static function ip_in_ranges( string $ip, array $ranges ): bool {
		$ip_bin = inet_pton( $ip );
		foreach ( $ranges as $range ) {
			$parts = explode( '/', trim( $range ), 2 );
			if ( ! filter_var( $parts[0], FILTER_VALIDATE_IP ) ) {
				continue;
			}
			$net_bin = inet_pton( $parts[0] );
			$max     = strlen( $net_bin ) * 8;
			if ( strlen( $net_bin ) !== strlen( $ip_bin ) || ( isset( $parts[1] ) && ( ! preg_match( '/^\d{1,3}$/', $parts[1] ) || (int) $parts[1] > $max ) ) ) {
				continue;
			}
			$bits  = isset( $parts[1] ) ? (int) $parts[1] : $max;
			$bytes = intdiv( $bits, 8 );
			$rest  = $bits % 8;
			if ( substr( $ip_bin, 0, $bytes ) === substr( $net_bin, 0, $bytes )
				&& ( ! $rest || ( ord( $ip_bin[ $bytes ] ) >> ( 8 - $rest ) ) === ( ord( $net_bin[ $bytes ] ) >> ( 8 - $rest ) ) ) ) {
				return true;
			}
		}
		return false;
	}
}
