<?php
/**
 * Wallet transfer service.
 *
 * The body of `Woo_Wallet_Frontend::do_wallet_transfer()` (post-nonce-check)
 * lives here so the form handler and the REST `POST /me/transfer` handler
 * share one execution path. That way:
 *   - the per-user rate limit (`woo_wallet_transfer_rate_limit_per_minute`)
 *     applies to both surfaces,
 *   - all `apply_filters` extension points
 *     (`woo_wallet_transfer_charge_amount`, `woo_wallet_transfer_credit_amount`,
 *     `woo_wallet_transfer_debit_amount`, `woo_wallet_transfer_credit_transaction_note`,
 *     `woo_wallet_transfer_debit_transaction_note`, `woo_wallet_transfer_user_id`)
 *     fire identically,
 *   - any future fix flows through one place.
 *
 * The form handler keeps nonce + `$_POST` parsing; this service takes
 * already-parsed inputs.
 *
 * @package StandaleneTech
 * @since   2.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooWallet_Transfer_Service' ) ) {

	/**
	 * Transfer service.
	 */
	class WooWallet_Transfer_Service {

		/**
		 * Execute a wallet-to-wallet transfer.
		 *
		 * Returns an associative array with at minimum:
		 *   - is_valid (bool)
		 *   - message  (string, translatable)
		 *   - code     (string, machine-readable error code; absent on success)
		 * On success, also includes:
		 *   - debit_id  (int)
		 *   - credit_id (int)
		 *   - charge    (float, computed transfer charge applied to debit)
		 *
		 * @param int    $from_user_id Sender (must equal current user from caller's perspective).
		 * @param int    $to_user_id   Recipient.
		 * @param float  $amount       Transfer amount (gross, before charge).
		 * @param string $note         Optional credit-side note. Empty → default sender-email note.
		 * @param string $currency     Optional ISO 4217 code. In per_currency mode this scopes
		 *                             both the balance check and the resulting ledger rows. In
		 *                             single_base mode it is forwarded to recode_transaction()
		 *                             so the row's `original_currency` audit column reflects
		 *                             what the customer actually requested.
		 * @return array
		 */
		public static function execute( $from_user_id, $to_user_id, $amount, $note = '', $currency = '' ) {
			$from_user_id = (int) $from_user_id;
			$to_user_id   = (int) absint( apply_filters( 'woo_wallet_transfer_user_id', $to_user_id ) );
			$amount       = (float) $amount;
			$note         = is_string( $note ) ? sanitize_text_field( $note ) : '';
			$currency     = is_string( $currency ) ? strtoupper( trim( $currency ) ) : '';
			if ( '' !== $currency && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
				return self::fail( 'rest_invalid_currency', __( 'Invalid currency code.', 'woo-wallet' ) );
			}

			if ( ! $from_user_id ) {
				return self::fail( 'rest_not_logged_in', __( 'You must be logged in to transfer funds.', 'woo-wallet' ) );
			}

			// Per-user soft rate limit (default 5/min). Mirrors the form handler.
			$rate_limit = (int) apply_filters( 'woo_wallet_transfer_rate_limit_per_minute', 5, $from_user_id );
			$rate_key   = 'woo_wallet_xfer_rate_' . $from_user_id;
			$rate_count = (int) get_transient( $rate_key );
			if ( $rate_limit > 0 && $rate_count >= $rate_limit ) {
				return self::fail( 'rest_rate_limited', __( 'Too many transfers in a short time. Please wait a minute and try again.', 'woo-wallet' ), 429 );
			}
			set_transient( $rate_key, $rate_count + 1, MINUTE_IN_SECONDS );

			if ( $amount <= 0 ) {
				return self::fail( 'rest_invalid_amount', __( 'Transfer amount must be greater than zero.', 'woo-wallet' ) );
			}

			$whom = $to_user_id ? get_userdata( $to_user_id ) : false;
			if ( ! $whom ) {
				return self::fail( 'rest_invalid_recipient', __( 'Invalid user', 'woo-wallet' ) );
			}
			if ( $from_user_id === (int) $whom->ID ) {
				return self::fail( 'rest_invalid_recipient', __( 'Invalid user', 'woo-wallet' ) );
			}

			$min_transfer_amount = (float) woo_wallet()->settings_api->get_option( 'min_transfer_amount', '_wallet_settings_general', 0 );
			if ( $min_transfer_amount && $min_transfer_amount > $amount ) {
				return self::fail(
					'rest_amount_below_minimum',
					/* translators: Min transfer amount */
					sprintf( __( 'Minimum transfer amount is %s', 'woo-wallet' ), wc_price( $min_transfer_amount, woo_wallet_wc_price_args() ) )
				);
			}
			$max_transfer_amount = (float) woo_wallet()->settings_api->get_option( 'max_transfer_amount', '_wallet_settings_general', 0 );
			if ( $max_transfer_amount && $max_transfer_amount < $amount ) {
				return self::fail(
					'rest_amount_above_maximum',
					/* translators: Max transfer amount */
					sprintf( __( 'Maximum transfer amount is %s', 'woo-wallet' ), wc_price( $max_transfer_amount, woo_wallet_wc_price_args() ) )
				);
			}

			$current_user_obj = get_userdata( $from_user_id );

			/* translators: %s: sender email */
			$credit_note = '' !== $note ? $note : sprintf( __( 'Wallet funds received from %s', 'woo-wallet' ), $current_user_obj->user_email );
			/* translators: %s: recipient email */
			$debit_note  = sprintf( __( 'Wallet funds transfer to %s', 'woo-wallet' ), $whom->user_email );
			$credit_note = apply_filters( 'woo_wallet_transfer_credit_transaction_note', $credit_note, $whom, $amount );
			$debit_note  = apply_filters( 'woo_wallet_transfer_debit_transaction_note', $debit_note, $whom, $amount );

			$transfer_charge_type   = woo_wallet()->settings_api->get_option( 'transfer_charge_type', '_wallet_settings_general', 'percent' );
			$transfer_charge_amount = (float) woo_wallet()->settings_api->get_option( 'transfer_charge_amount', '_wallet_settings_general', 0 );
			if ( 'percent' === $transfer_charge_type ) {
				$transfer_charge = ( $amount * $transfer_charge_amount ) / 100;
			} else {
				$transfer_charge = $transfer_charge_amount;
			}
			$transfer_charge = (float) apply_filters( 'woo_wallet_transfer_charge_amount', $transfer_charge, $whom );
			$credit_amount   = (float) apply_filters( 'woo_wallet_transfer_credit_amount', $amount, $whom );
			$debit_amount    = (float) apply_filters( 'woo_wallet_transfer_debit_amount', $amount + $transfer_charge, $whom );

			if ( $debit_amount <= 0 ) {
				return self::fail( 'rest_invalid_amount', __( 'Transfer amount must be greater than zero.', 'woo-wallet' ) );
			}

			$transfer_args = array();
			if ( '' !== $currency ) {
				$transfer_args['currency'] = $currency;
			}
			$result = woo_wallet()->wallet->transfer( $from_user_id, (int) $whom->ID, $debit_amount, $debit_note, $credit_note, $credit_amount, $transfer_args );
			if ( ! $result ) {
				return self::fail( 'rest_insufficient_balance', __( 'Entered amount is greater than current wallet amount.', 'woo-wallet' ), 422 );
			}

			update_wallet_transaction_meta( $result['debit'], '_wallet_transfer_charge', $transfer_charge, $from_user_id );
			do_action( 'woo_wallet_transfer_amount_debited', $result['debit'], $from_user_id, $whom->ID );
			if ( ! empty( $result['credit'] ) ) {
				do_action( 'woo_wallet_transfer_amount_credited', $result['credit'], $whom->ID, $from_user_id );
			}

			return array(
				'is_valid'  => true,
				'message'   => __( 'Amount transferred successfully!', 'woo-wallet' ),
				'debit_id'  => (int) $result['debit'],
				'credit_id' => isset( $result['credit'] ) ? (int) $result['credit'] : 0,
				'charge'    => (float) $transfer_charge,
			);
		}

		/**
		 * Build a structured failure tuple. `status` is the HTTP status the REST
		 * controller will project; the form handler ignores it.
		 *
		 * @param string $code    Machine-readable code.
		 * @param string $message Human-readable message.
		 * @param int    $status  HTTP status (default 400).
		 * @return array
		 */
		private static function fail( $code, $message, $status = 400 ) {
			return array(
				'is_valid' => false,
				'code'     => $code,
				'message'  => $message,
				'status'   => $status,
			);
		}
	}
}
