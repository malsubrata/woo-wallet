<?php
/**
 * Time-limited Pro offer (Black Friday 2026).
 *
 * One place that knows the end date and prices. No hooks, no options, no database,
 * no requests. The offer starts the moment a site installs this version and has
 * no start date. The end date stays as a safety switch: many sites install updates
 * weeks late or never, and without the check they would keep advertising the sale
 * price after the store has gone back to the regular one.
 *
 * @package WooWallet
 * @since   1.7.5
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Wallet_Pro_Campaign' ) ) {

	/**
	 * Black Friday campaign state.
	 */
	class Woo_Wallet_Pro_Campaign {

		const END          = '2026-12-01 23:59:59'; // UTC.
		const END_LABEL_TS = 1796169599;            // = END, UTC; printed as the end date.
		const SALE_PRICE   = '$55';
		const REGULAR      = '$79';
		const SAVING       = '$24';
		const PERCENT      = 30;
		const UTM_CAMPAIGN = 'bf2026';

		/**
		 * Is the offer on right now?
		 *
		 * @return bool
		 */
		public static function is_active(): bool {
			if ( function_exists( 'woo_wallet_is_pro_active' ) && woo_wallet_is_pro_active() ) {
				return false;
			}

			/**
			 * Current time (UTC timestamp) used by the campaign. For tests and screenshots.
			 *
			 * @since 1.7.5
			 * @param int $now Unix timestamp.
			 */
			$now    = (int) apply_filters( 'woo_wallet_pro_campaign_now', time() );
			$active = $now <= self::END_LABEL_TS;

			/**
			 * Let a store owner switch the campaign surfaces off.
			 *
			 * @since 1.7.5
			 * @param bool $active Whether the campaign is active.
			 */
			return (bool) apply_filters( 'woo_wallet_pro_campaign_active', $active );
		}

		/**
		 * Whole UTC days between today and the last day (0 on the last day).
		 *
		 * @return int
		 */
		public static function days_left(): int {
			$now = (int) apply_filters( 'woo_wallet_pro_campaign_now', time() );
			return max( 0, (int) floor( self::END_LABEL_TS / DAY_IN_SECONDS ) - (int) floor( $now / DAY_IN_SECONDS ) );
		}

		/**
		 * Printed end date, e.g. "1 December", translated.
		 *
		 * @return string
		 */
		public static function end_date(): string {
			/* translators: date format for the offer end date, e.g. "1 December" (day, full month name). */
			return (string) wp_date( __( 'j F', 'woo-wallet' ), self::END_LABEL_TS, new DateTimeZone( 'UTC' ) );
		}

		/**
		 * Plugins-screen row link while the offer runs.
		 *
		 * @return string
		 */
		public static function row_link_text(): string {
			return sprintf(
				/* translators: 1: discount percent number, e.g. 30. 2: short offer end date, e.g. "1 Dec". */
				__( 'Upgrade to Pro — %1$d%% off until %2$s', 'woo-wallet' ),
				self::PERCENT,
				wp_date( _x( 'j M', 'short date format for the offer end date', 'woo-wallet' ), self::END_LABEL_TS, new DateTimeZone( 'UTC' ) )
			);
		}

		/**
		 * Short urgency pill text: only in the last week, else ''.
		 *
		 * @return string
		 */
		public static function urgency_text(): string {
			$days = self::days_left();
			if ( $days > 7 ) {
				return '';
			}
			if ( 0 === $days ) {
				return __( 'Ends today', 'woo-wallet' );
			}
			if ( 1 === $days ) {
				return __( 'Ends tomorrow', 'woo-wallet' );
			}
			/* translators: %s: number of days left. */
			return sprintf( _n( '%s day left', '%s days left', $days, 'woo-wallet' ), number_format_i18n( $days ) );
		}

		/**
		 * "Ends 1 December · 3 days left" / "Ends tomorrow" / "Ends today".
		 *
		 * @return string
		 */
		public static function deadline_text(): string {
			$days = self::days_left();
			if ( 0 === $days ) {
				return __( 'Ends today', 'woo-wallet' );
			}
			if ( 1 === $days ) {
				return __( 'Ends tomorrow', 'woo-wallet' );
			}
			return sprintf(
				/* translators: 1: end date, e.g. "1 December". 2: number of days left. */
				_n( 'Ends %1$s · %2$s day left', 'Ends %1$s · %2$s days left', $days, 'woo-wallet' ),
				self::end_date(),
				number_format_i18n( $days )
			);
		}
	}
}
