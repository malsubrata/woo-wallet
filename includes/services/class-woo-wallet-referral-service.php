<?php
/**
 * Referral service.
 *
 * The transactional core of the referral earning action. `Action_Referrals`
 * keeps cookie capture, referrer resolution and WordPress hook wiring; this
 * service owns everything that touches the `woo_wallet_referrals` table and the
 * wallet ledger:
 *   - recording a credited visitor referral,
 *   - recording a sign-up referral (pending or immediately credited),
 *   - crediting a pending sign-up once its minimum-spend gate is met,
 *   - rejecting a row when a period limit is hit or the referrer is gone.
 *
 * Period limits are evaluated as a `COUNT` of completed rows in a rolling
 * window — the table is the source of truth, so the count survives object-cache
 * flushes and self-corrects when a row is later rejected. This replaces the
 * pre-1.6.2 transient counters.
 *
 * The action object is passed in so the service can read the action's settings
 * and re-fire the long-standing extension hooks (`woo_wallet_after_referral_*`)
 * with their original signatures.
 *
 * @package StandaleneTech
 * @since   1.6.2
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WooWallet_Referral_Service' ) ) {

	/**
	 * Referral service.
	 */
	class WooWallet_Referral_Service {

		/**
		 * Record a credited visitor referral.
		 *
		 * The 24h per-referrer dedup cookie is checked and set by the action
		 * (it owns `$_COOKIE`); this method only enforces the configured period
		 * limit, credits the referrer in the store base currency and writes the
		 * completed referral row. Blocked visits are not persisted — visits are
		 * high-frequency and rejected rows would only add noise.
		 *
		 * @param Action_Referrals $action   The referral action (settings source).
		 * @param WP_User          $referrer The user being rewarded.
		 * @param string           $code     The referral code that was used.
		 * @return array Envelope: is_valid + (referral_id, transaction_id) | (code, message).
		 */
		public static function record_visit( $action, $referrer, $code = '' ) {
			$referrer_id = (int) $referrer->ID;

			$amount = (float) apply_filters( 'woo_wallet_referring_visitor_amount', $action->settings['referring_visitors_amount'], $referrer_id );
			if ( $amount <= 0 ) {
				return self::fail( 'zero_amount', __( 'Visitor referral reward amount is zero.', 'woo-wallet' ) );
			}

			$currency        = self::base_currency();
			$referred_user_id = get_current_user_id();

			// Serialise concurrent visits per referrer so the period-limit
			// COUNT + insert + credit cannot race. The cookie dedup in the
			// action layer is best-effort; this lock is the authoritative gate.
			$lock = self::acquire_referrer_lock( $referrer_id );
			if ( ! $lock ) {
				return self::fail( 'lock_busy', __( 'Could not acquire referral lock.', 'woo-wallet' ) );
			}

			try {
				if ( ! self::within_period_limit( $referrer_id, 'visit', $action->settings['referring_visitors_limit_duration'], $action->settings['referring_visitors_limit'] ) ) {
					return self::fail( 'limit_reached', __( 'Visitor referral reward limit reached for this period.', 'woo-wallet' ) );
				}

				// DB-level dedup: at most one completed visit credit per
				// (referrer, referred user) inside the period window. Survives
				// cookie clearing / private browsing.
				if ( $referred_user_id && self::has_visit_in_period( $referrer_id, $referred_user_id, self::period_seconds( $action->settings['referring_visitors_limit_duration'] ) ) ) {
					return self::fail( 'already_credited', __( 'A visit referral for this user was already credited in this period.', 'woo-wallet' ) );
				}

				// Write the referral row BEFORE crediting: a referral credit must
				// never exist without its audit row. If the row cannot be written
				// (e.g. the table is missing) the visit is not credited at all.
				$referral_id = self::insert_row(
					array(
						'referrer_id'      => $referrer_id,
						'referred_user_id' => $referred_user_id,
						'type'             => 'visit',
						'referral_code'    => (string) $code,
						'status'           => 'pending',
						'amount'           => $amount,
						'currency'         => $currency,
					)
				);
				if ( ! $referral_id ) {
					return self::fail( 'record_failed', __( 'Could not record the referral.', 'woo-wallet' ) );
				}

				// The configured amount is stored in the base currency, so credit it
				// against the base currency to skip active-currency conversion.
				$transaction_id = woo_wallet()->wallet->credit( $referrer_id, $amount, $action->settings['referring_visitors_description'], array( 'currency' => $currency ) );
				if ( ! $transaction_id ) {
					self::update_row( $referral_id, array( 'status' => 'rejected', 'reject_reason' => 'credit_failed' ) );
					return self::fail( 'credit_failed', __( 'Could not credit the referral reward.', 'woo-wallet' ) );
				}

				self::update_row(
					$referral_id,
					array(
						'status'         => 'completed',
						'transaction_id' => (int) $transaction_id,
						'date_credited'  => current_time( 'mysql' ),
					)
				);

				do_action( 'woo_wallet_after_referral_visit', $transaction_id, $action );

				return array(
					'is_valid'       => true,
					'referral_id'    => $referral_id,
					'transaction_id' => (int) $transaction_id,
				);
			} finally {
				self::release_referrer_lock( $referrer_id );
			}
		}

		/**
		 * Record a sign-up referral.
		 *
		 * Always writes a `pending` row — it is the permanent attribution link
		 * between the referred user and the referrer. The caller credits it
		 * immediately (via credit_signup()) when no minimum-spend gate applies;
		 * otherwise the deferred order-status path credits it later.
		 *
		 * Idempotent: at most one sign-up row per referred user, ever.
		 *
		 * @param Action_Referrals $action           The referral action.
		 * @param WP_User          $referrer         The user being rewarded.
		 * @param int              $referred_user_id The newly-registered user.
		 * @param string           $code             The referral code that was used.
		 * @return array Envelope: is_valid + (referral_id, status) | (code, message).
		 */
		public static function record_signup( $action, $referrer, $referred_user_id, $code = '' ) {
			$referred_user_id = (int) $referred_user_id;

			// Per-referred-user lock so the dedup SELECT and the INSERT cannot
			// race. The multi-hook signup-drain (`drain_request_pending` +
			// `drain_current_user` + `drain_user_on_login`) can fire two
			// concurrent process() calls for the same user; without this lock
			// both would pass the existence check and write duplicate pending
			// rows, which `credit_signup()` could later credit twice.
			$lock = self::acquire_referred_user_lock( $referred_user_id );
			if ( ! $lock ) {
				return self::fail( 'lock_busy', __( 'Could not acquire signup referral lock.', 'woo-wallet' ) );
			}

			try {
				$existing = get_wallet_referrals(
					array(
						'referred_user_id' => $referred_user_id,
						'type'             => 'signup',
						'limit'            => 1,
					)
				);
				if ( ! empty( $existing ) ) {
					return self::fail( 'already_recorded', __( 'A sign-up referral is already recorded for this user.', 'woo-wallet' ) );
				}

				$referral_id = self::insert_row(
					array(
						'referrer_id'      => (int) $referrer->ID,
						'referred_user_id' => $referred_user_id,
						'type'             => 'signup',
						'referral_code'    => (string) $code,
						'status'           => 'pending',
						// Store the configured amount so a pending row shows a
						// meaningful preview; the final value is re-resolved (and
						// filtered) when the row is credited.
						'amount'           => (float) $action->settings['referring_signups_amount'],
						'currency'         => self::base_currency(),
					)
				);
				if ( ! $referral_id ) {
					return self::fail( 'record_failed', __( 'Could not record the referral.', 'woo-wallet' ) );
				}

				return array(
					'is_valid'    => true,
					'referral_id' => $referral_id,
					'status'      => 'pending',
				);
			} finally {
				self::release_referred_user_lock( $referred_user_id );
			}
		}

		/**
		 * Credit a pending sign-up referral.
		 *
		 * Called immediately after record_signup() when no minimum spend is
		 * configured, and from the order-status hook once the referred user's
		 * lifetime spend clears the gate. Safe to call repeatedly: once the row
		 * is `completed` no pending row remains, so a replay is a no-op.
		 *
		 * @param Action_Referrals $action           The referral action.
		 * @param int              $referred_user_id The referred user.
		 * @param int              $order_id         Optional qualifying order id.
		 * @return array Envelope: is_valid + (referral_id, transaction_id) | (code, message).
		 */
		public static function credit_signup( $action, $referred_user_id, $order_id = 0 ) {
			$referred_user_id = (int) $referred_user_id;
			$order_id         = (int) $order_id;

			$pending = self::find_pending_signup( $action, $referred_user_id );
			if ( ! $pending ) {
				return self::fail( 'no_pending_referral', __( 'No pending sign-up referral to credit.', 'woo-wallet' ) );
			}

			// Guard against a deleted referrer — new WP_User() on a missing id
			// yields user id 0, which would credit the wrong account.
			$referrer = get_user_by( 'id', $pending->referrer_id );
			if ( ! $referrer ) {
				self::update_row( $pending->referral_id, array( 'status' => 'rejected', 'reject_reason' => 'referrer_deleted' ) );
				return self::fail( 'referrer_deleted', __( 'The referrer account no longer exists.', 'woo-wallet' ) );
			}

			// Serialise concurrent credits per referrer so the period-limit
			// check + credit + completion update cannot race.
			$lock = self::acquire_referrer_lock( (int) $referrer->ID );
			if ( ! $lock ) {
				return self::fail( 'lock_busy', __( 'Could not acquire referral lock.', 'woo-wallet' ) );
			}

			try {
				// Re-fetch under the lock: another concurrent caller may have
				// already moved this row to completed/rejected.
				$fresh = self::find_pending_signup( $action, $referred_user_id );
				if ( ! $fresh || (int) $fresh->referral_id !== (int) $pending->referral_id ) {
					return self::fail( 'no_pending_referral', __( 'No pending sign-up referral to credit.', 'woo-wallet' ) );
				}

				if ( ! self::within_period_limit( (int) $referrer->ID, 'signup', $action->settings['referring_signups_limit_duration'], $action->settings['referring_signups_limit'] ) ) {
					self::update_row( $pending->referral_id, array( 'status' => 'rejected', 'reject_reason' => 'limit_reached' ) );
					return self::fail( 'limit_reached', __( 'Sign-up referral reward limit reached for this period.', 'woo-wallet' ) );
				}

				$amount = (float) apply_filters( 'woo_wallet_referring_signup_amount', $action->settings['referring_signups_amount'], $referrer->ID, $referred_user_id, $order_id );
				if ( $amount <= 0 ) {
					self::update_row( $pending->referral_id, array( 'status' => 'rejected', 'reject_reason' => 'zero_amount' ) );
					return self::fail( 'zero_amount', __( 'Sign-up referral reward amount is zero.', 'woo-wallet' ) );
				}

				$currency       = self::base_currency();
				$transaction_id = woo_wallet()->wallet->credit( $referrer->ID, $amount, $action->settings['referring_signups_description'], array( 'currency' => $currency ) );
				if ( ! $transaction_id ) {
					return self::fail( 'credit_failed', __( 'Could not credit the referral reward.', 'woo-wallet' ) );
				}

				self::update_row(
					$pending->referral_id,
					array(
						'status'         => 'completed',
						'amount'         => $amount,
						'currency'       => $currency,
						'transaction_id' => (int) $transaction_id,
						'order_id'       => $order_id,
						'date_credited'  => current_time( 'mysql' ),
					)
				);

				do_action( 'woo_wallet_after_referral_signup', $transaction_id, $referred_user_id, $action, $order_id );

				return array(
					'is_valid'       => true,
					'referral_id'    => (int) $pending->referral_id,
					'transaction_id' => (int) $transaction_id,
				);
			} finally {
				self::release_referrer_lock( (int) $referrer->ID );
			}
		}

		/**
		 * Mark a referral row rejected.
		 *
		 * @param int    $referral_id Row id.
		 * @param string $reason      Machine-readable reason.
		 * @return void
		 */
		public static function reject( $referral_id, $reason ) {
			self::update_row( (int) $referral_id, array( 'status' => 'rejected', 'reject_reason' => (string) $reason ) );
		}

		/**
		 * Locate the pending sign-up row for a referred user.
		 *
		 * Includes a one-release back-compat shim: a user referred under
		 * pre-1.6.2 code has its attribution only in `_referral_user_id` user
		 * meta and no table row. When such a user has not yet been credited and
		 * no sign-up row exists, a pending row is synthesised from the meta so
		 * the minimum-spend gate still pays out after the upgrade.
		 *
		 * @param Action_Referrals $action           The referral action.
		 * @param int              $referred_user_id The referred user.
		 * @return object|null The pending row, or null.
		 */
		private static function find_pending_signup( $action, $referred_user_id ) {
			$rows = get_wallet_referrals(
				array(
					'referred_user_id' => $referred_user_id,
					'type'             => 'signup',
					'status'           => 'pending',
					'limit'            => 1,
				)
			);
			if ( ! empty( $rows ) ) {
				return $rows[0];
			}

			// Back-compat shim for pre-1.6.2 pending sign-ups.
			$legacy_referrer = (int) get_user_meta( $referred_user_id, '_referral_user_id', true );
			if ( ! $legacy_referrer || get_user_meta( $referred_user_id, '_woo_wallet_referral_signup_credited', true ) ) {
				return null;
			}
			// Only synthesise when no sign-up row exists at all (a completed or
			// rejected row means this user was already processed under 1.6.2+).
			$any = get_wallet_referrals(
				array(
					'referred_user_id' => $referred_user_id,
					'type'             => 'signup',
					'limit'            => 1,
				)
			);
			if ( ! empty( $any ) ) {
				return null;
			}

			self::insert_row(
				array(
					'referrer_id'      => $legacy_referrer,
					'referred_user_id' => (int) $referred_user_id,
					'type'             => 'signup',
					'status'           => 'pending',
					'amount'           => (float) $action->settings['referring_signups_amount'],
					'currency'         => self::base_currency(),
				)
			);

			$rows = get_wallet_referrals(
				array(
					'referred_user_id' => $referred_user_id,
					'type'             => 'signup',
					'status'           => 'pending',
					'limit'            => 1,
				)
			);
			return ! empty( $rows ) ? $rows[0] : null;
		}

		/**
		 * Whether the referrer is still under the configured period limit.
		 *
		 * No duration configured → unlimited. With a duration set, the cap is
		 * the configured count of completed rows in the rolling window; a
		 * configured count of 0 therefore blocks all rewards, matching the
		 * pre-1.6.2 transient behaviour.
		 *
		 * @param int    $referrer_id    Referrer.
		 * @param string $type           'visit' | 'signup'.
		 * @param string $limit_duration '0' | 'day' | 'week' | 'month'.
		 * @param int    $limit_count    Configured cap.
		 * @return bool
		 */
		private static function within_period_limit( $referrer_id, $type, $limit_duration, $limit_count ) {
			$period = self::period_seconds( $limit_duration );
			if ( ! $period ) {
				return true;
			}
			return self::count_in_period( $referrer_id, $type, $period ) < (int) $limit_count;
		}

		/**
		 * Count completed referral rows for a referrer inside a rolling window.
		 *
		 * Uses the database clock for both the column default and the window
		 * boundary, so it is correct regardless of the server timezone.
		 *
		 * @param int    $referrer_id    Referrer.
		 * @param string $type           'visit' | 'signup'.
		 * @param int    $period_seconds Window length in seconds.
		 * @return int
		 */
		private static function count_in_period( $referrer_id, $type, $period_seconds ) {
			global $wpdb;
			$table = $wpdb->base_prefix . 'woo_wallet_referrals';
			return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE referrer_id = %d AND type = %s AND status = 'completed' AND date_created >= ( NOW() - INTERVAL %d SECOND )",
					$referrer_id,
					$type,
					$period_seconds
				)
			);
		}

		/**
		 * Period length in seconds for a limit-duration setting.
		 *
		 * @param string $duration '0' | 'day' | 'week' | 'month'.
		 * @return int 0 when no limit applies.
		 */
		private static function period_seconds( $duration ) {
			switch ( $duration ) {
				case 'day':
					return DAY_IN_SECONDS;
				case 'week':
					return WEEK_IN_SECONDS;
				case 'month':
					return MONTH_IN_SECONDS;
			}
			return 0;
		}

		/**
		 * Store base currency.
		 *
		 * Mirrors WooWalletAction::get_base_currency() (which is protected and
		 * therefore not reachable from here).
		 *
		 * @return string ISO 4217 code.
		 */
		private static function base_currency() {
			if ( class_exists( 'Woo_Wallet_Currency_Manager' ) ) {
				return Woo_Wallet_Currency_Manager::instance()->get_base_currency();
			}
			$currency = get_option( 'woocommerce_currency' );
			return is_string( $currency ) && '' !== $currency ? strtoupper( $currency ) : 'USD';
		}

		/**
		 * Insert a referral row.
		 *
		 * `date_created` is intentionally omitted so the column default
		 * (CURRENT_TIMESTAMP) populates it from the database clock.
		 *
		 * @param array $data Column => value pairs (see defaults below).
		 * @return int The new referral_id, or 0 when the insert failed.
		 */
		private static function insert_row( array $data ) {
			global $wpdb;
			$row = wp_parse_args(
				$data,
				array(
					'blog_id'          => get_current_blog_id(),
					'referrer_id'      => 0,
					'referred_user_id' => 0,
					'type'             => 'visit',
					'referral_code'    => '',
					'status'           => 'pending',
					'amount'           => 0,
					'currency'         => '',
					'transaction_id'   => 0,
					'order_id'         => 0,
					'reject_reason'    => null,
					'date_credited'    => null,
				)
			);
			// Check the insert return value, not insert_id: a failed query
			// leaves insert_id holding the id from a previous successful insert.
			$inserted = $wpdb->insert( $wpdb->base_prefix . 'woo_wallet_referrals', $row, self::formats_for( $row ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			if ( false === $inserted ) {
				return 0;
			}
			return (int) $wpdb->insert_id;
		}

		/**
		 * Update a referral row by id.
		 *
		 * @param int   $referral_id Row id.
		 * @param array $data        Column => value pairs.
		 * @return void
		 */
		private static function update_row( $referral_id, array $data ) {
			global $wpdb;
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->base_prefix . 'woo_wallet_referrals',
				$data,
				array( 'referral_id' => (int) $referral_id ),
				self::formats_for( $data ),
				array( '%d' )
			);
		}

		/**
		 * Build an ordered $wpdb format list for the given column => value map.
		 *
		 * @param array $data Column => value pairs.
		 * @return array Ordered list of %d/%f/%s placeholders.
		 */
		private static function formats_for( array $data ) {
			$map = array(
				'blog_id'          => '%d',
				'referrer_id'      => '%d',
				'referred_user_id' => '%d',
				'type'             => '%s',
				'referral_code'    => '%s',
				'status'           => '%s',
				'amount'           => '%f',
				'currency'         => '%s',
				'transaction_id'   => '%d',
				'order_id'         => '%d',
				'reject_reason'    => '%s',
				'date_created'     => '%s',
				'date_credited'    => '%s',
			);
			$formats = array();
			foreach ( array_keys( $data ) as $column ) {
				$formats[] = isset( $map[ $column ] ) ? $map[ $column ] : '%s';
			}
			return $formats;
		}

		/**
		 * Acquire a MySQL named lock for a referrer.
		 *
		 * Serialises concurrent referral processing for the same referrer so
		 * the period-limit COUNT + insert + credit cannot race. The lock is
		 * advisory and per-connection; pair every successful acquire with a
		 * release_referrer_lock() call in a `finally`.
		 *
		 * @param int $referrer_id Referrer.
		 * @return bool True on success, false on timeout or error.
		 */
		private static function acquire_referrer_lock( $referrer_id ) {
			global $wpdb;
			$got = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', 'woo_wallet_referral_lock_' . (int) $referrer_id, 5 )
			);
			return '1' === (string) $got;
		}

		/**
		 * Release the referrer lock acquired by acquire_referrer_lock().
		 *
		 * @param int $referrer_id Referrer.
		 * @return void
		 */
		private static function release_referrer_lock( $referrer_id ) {
			global $wpdb;
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'woo_wallet_referral_lock_' . (int) $referrer_id )
			);
		}

		/**
		 * Acquire a MySQL named lock for a referred user.
		 *
		 * Used by record_signup() to serialise the dedup SELECT + INSERT pair so
		 * concurrent signup-drain hooks for the same user cannot both pass the
		 * existence check and insert duplicate pending rows. The lock namespace
		 * is intentionally distinct from `woo_wallet_referral_lock_<id>` (per
		 * referrer) so no caller holds both at once.
		 *
		 * @param int $referred_user_id Referred user.
		 * @return bool
		 */
		private static function acquire_referred_user_lock( $referred_user_id ) {
			global $wpdb;
			$timeout = (int) apply_filters( 'woo_wallet_db_lock_timeout', 5, $referred_user_id );
			$got     = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', 'woo_wallet_referral_signup_lock_' . (int) $referred_user_id, $timeout )
			);
			return '1' === (string) $got;
		}

		/**
		 * Release the referred-user lock acquired by acquire_referred_user_lock().
		 *
		 * @param int $referred_user_id Referred user.
		 * @return void
		 */
		private static function release_referred_user_lock( $referred_user_id ) {
			global $wpdb;
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', 'woo_wallet_referral_signup_lock_' . (int) $referred_user_id )
			);
		}

		/**
		 * Whether a completed visit credit already exists for (referrer, referred)
		 * within the current limit window.
		 *
		 * DB-level dedup that survives cookie clearing / private browsing.
		 * A zero or missing window collapses to a per-day bucket so a tampered
		 * client cannot bypass dedup by toggling the limit duration setting.
		 *
		 * @param int $referrer_id     Referrer.
		 * @param int $referred_user_id Referred user (must be > 0).
		 * @param int $period_seconds  Window length in seconds; 0 → fall back to DAY_IN_SECONDS.
		 * @return bool
		 */
		private static function has_visit_in_period( $referrer_id, $referred_user_id, $period_seconds ) {
			global $wpdb;
			$table  = $wpdb->base_prefix . 'woo_wallet_referrals';
			$window = $period_seconds > 0 ? (int) $period_seconds : DAY_IN_SECONDS;
			$count  = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE referrer_id = %d AND referred_user_id = %d AND type = 'visit' AND status = 'completed' AND date_created >= ( NOW() - INTERVAL %d SECOND )",
					(int) $referrer_id,
					(int) $referred_user_id,
					$window
				)
			);
			return $count > 0;
		}

		/**
		 * Build a structured failure tuple.
		 *
		 * @param string $code    Machine-readable code.
		 * @param string $message Human-readable message.
		 * @return array
		 */
		private static function fail( $code, $message ) {
			return array(
				'is_valid' => false,
				'code'     => $code,
				'message'  => $message,
			);
		}
	}
}
