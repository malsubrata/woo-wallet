<?php
/**
 * Email opt-in banner.
 *
 * Offers the store administrator a setup guide in exchange for an email
 * address. Nothing is transmitted anywhere unless a human ticks the consent
 * box and presses the button — there is no telemetry, no phone-home on
 * activation or page load, and no background request of any kind.
 *
 * Rendered on `woo_wallet_admin_page_header`, an action only TeraWallet's own
 * screens fire, rather than `admin_notices`: the Dashboard and Settings screens
 * run `remove_all_actions( 'admin_notices' )` (see
 * Woo_Wallet_Admin::suppress_reports_admin_notices()), which would silently
 * delete this banner on the two screens it most needs to appear on. Hooking our
 * own action also makes it structurally impossible for the banner to leak onto
 * another plugin's page.
 *
 * Self-contained on purpose: markup, styles, script and both AJAX handlers live
 * in this one file, so retiring the feature is a matter of deleting it and its
 * single `include_once`.
 *
 * @package TeraWallet
 * @since 1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Optin_Notice' ) ) :

	/**
	 * Dismissible email opt-in banner for TeraWallet's own admin screens.
	 */
	class Woo_Wallet_Optin_Notice {

		/**
		 * Lead capture endpoint.
		 */
		const ENDPOINT = 'https://standalonetech.com/wp-json/terawallet-leads/v1/subscribe';

		/**
		 * Option set once a subscription has been confirmed by the endpoint.
		 */
		const OPT_DONE = 'woo_wallet_optin_done';

		/**
		 * Option set when the banner is dismissed. Permanent.
		 */
		const OPT_DISMISSED = 'woo_wallet_optin_dismissed';

		/**
		 * Nonce action shared by both AJAX endpoints.
		 */
		const NONCE = 'woo_wallet_optin';

		/**
		 * Privacy policy URL disclosed in readme.txt and linked from the consent label.
		 */
		const PRIVACY_URL = 'https://standalonetech.com/privacy-policy/';

		/**
		 * Terms URL disclosed in readme.txt and linked from the consent label.
		 */
		const TERMS_URL = 'https://standalonetech.com/terms-and-conditions/';

		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'woo_wallet_admin_page_header', array( $this, 'render' ), 5 );
			add_action( 'wp_ajax_woo_wallet_optin_subscribe', array( $this, 'ajax_subscribe' ) );
			add_action( 'wp_ajax_woo_wallet_optin_dismiss', array( $this, 'ajax_dismiss' ) );
		}

		/**
		 * Whether the banner should appear on the current request.
		 *
		 * @return bool
		 */
		public function should_render() {
			if ( ! current_user_can( 'manage_options' ) ) {
				return false;
			}

			if ( get_option( self::OPT_DONE ) || get_option( self::OPT_DISMISSED ) ) {
				return false;
			}

			if ( ! function_exists( 'get_current_screen' ) ) {
				return false;
			}

			$screen = get_current_screen();
			if ( ! $screen ) {
				return false;
			}

			// Wallet Dashboard, Wallet Users and Settings only. The header action
			// also fires on the Referral Report and Transaction details screens.
			$allowed = array(
				woo_wallet_get_screen_id( 'woo-wallet', '' ),
				woo_wallet_get_screen_id( 'woo-wallet-users' ),
				woo_wallet_get_screen_id( 'woo-wallet-settings' ),
			);

			return in_array( $screen->id, $allowed, true );
		}

		/**
		 * Render the banner.
		 *
		 * @return void
		 */
		public function render() {
			if ( ! $this->should_render() ) {
				return;
			}

			$nonce = wp_create_nonce( self::NONCE );

			/*
			 * Deliberately not a `div.notice`. WordPress's common.js hoists every
			 * `div.notice` to sit after `.wp-header-end`; the Settings screen has
			 * no heading and no `.wp-header-end`, so jQuery's insertAfter() on an
			 * empty set silently *detaches* the notice. An <aside> is invisible to
			 * that hoisting and lets the banner carry the Pro promo's card
			 * treatment instead of core's white notice chrome.
			 */
			?>
			<aside class="tw-optin" id="woo-wallet-optin" aria-labelledby="tw-optin-title" data-nonce="<?php echo esc_attr( $nonce ); ?>">
				<div class="tw-optin__body">
					<div class="tw-optin__pitch">
						<p class="tw-optin__eyebrow"><?php esc_html_e( 'TeraWallet setup guide', 'woo-wallet' ); ?></p>
						<h2 class="tw-optin__title" id="tw-optin-title"><?php esc_html_e( 'Store credit is easy to switch on and easy to get wrong.', 'woo-wallet' ); ?></h2>
						<p class="tw-optin__sub"><?php esc_html_e( 'The setup guide walks through top-ups, cashback and partial payments, followed by occasional notes on running wallet credit well.', 'woo-wallet' ); ?></p>
					</div>

					<div class="tw-optin__form">
						<label class="screen-reader-text" for="woo-wallet-optin-email"><?php esc_html_e( 'Email address', 'woo-wallet' ); ?></label>
						<input
							type="email"
							id="woo-wallet-optin-email"
							class="tw-optin__email"
							value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>"
							autocomplete="email"
							spellcheck="false"
							placeholder="<?php esc_attr_e( 'you@yourstore.com', 'woo-wallet' ); ?>"
						/>

						<label class="tw-optin__consent" for="woo-wallet-optin-consent">
							<input type="checkbox" id="woo-wallet-optin-consent" />
							<span>
								<?php
								echo wp_kses(
									sprintf(
										/* translators: 1: opening privacy policy link, 2: closing link, 3: opening terms link, 4: closing link. */
										__( 'I agree to receive occasional emails about TeraWallet. See the %1$sprivacy policy%2$s and %3$sterms%4$s.', 'woo-wallet' ),
										'<a href="' . esc_url( self::PRIVACY_URL ) . '" target="_blank" rel="noopener noreferrer">',
										'</a>',
										'<a href="' . esc_url( self::TERMS_URL ) . '" target="_blank" rel="noopener noreferrer">',
										'</a>'
									),
									array(
										'a' => array(
											'href'   => array(),
											'target' => array(),
											'rel'    => array(),
										),
									)
								);
								?>
							</span>
						</label>

						<button type="button" class="tw-optin__btn" data-error="<?php echo esc_attr__( 'Could not reach the server just now. Please try again.', 'woo-wallet' ); ?>" disabled>
							<?php esc_html_e( 'Send me the guide', 'woo-wallet' ); ?>
						</button>

						<p class="tw-optin__message" role="alert"></p>
					</div>
				</div>

				<button type="button" class="tw-optin__dismiss" title="<?php esc_attr_e( 'Dismiss', 'woo-wallet' ); ?>">
					<span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice', 'woo-wallet' ); ?></span>
					<span aria-hidden="true">&times;</span>
				</button>
			</aside>
			<style>
				/*
				 * Native admin palette on purpose: this card asks for something,
				 * so it has to read as part of WordPress rather than as an ad.
				 * The Pro promo next to it keeps its dark treatment — that one is
				 * a pitch and is allowed to look like one.
				 */
				.tw-optin {
					--tw-optin-ink: #ffffff;
					--tw-optin-inset: #f6f7f7;
					--tw-optin-line: #dcdcde;
					--tw-optin-text: #1d2327;
					--tw-optin-muted: #646970;
					--tw-optin-accent: #2271b1;
					--tw-optin-label: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;

					position: relative;
					margin: 16px 20px 20px 0;
					border: 1px solid var(--tw-optin-line);
					border-radius: 4px;
					background: var(--tw-optin-ink);
					color: var(--tw-optin-text);
					box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
					overflow: hidden;
				}
				.tw-optin * { box-sizing: border-box; }

				/*
				 * The Settings screen mounts a full-bleed React app inside a
				 * zero-padding flex `.wrap`, so the card would sit hard against the
				 * admin menu. Give it back the inset the other screens inherit from
				 * their own `.wrap`.
				 */
				.woo-wallet-settings-page .tw-optin { margin: 16px 20px 20px; }

				.tw-optin__body {
					display: grid;
					grid-template-columns: minmax(0, 1fr) minmax(300px, 360px);
				}

				.tw-optin__pitch { padding: 20px 46px 20px 24px; min-width: 0; }

				.tw-optin__eyebrow {
					margin: 0 0 12px;
					font-family: var(--tw-optin-label);
					font-size: 11px;
					font-weight: 700;
					letter-spacing: 0.08em;
					text-transform: uppercase;
					color: var(--tw-optin-accent);
				}

				.tw-optin__title {
					margin: 0 0 6px;
					padding: 0;
					font-size: 16px;
					font-weight: 600;
					line-height: 1.35;
					color: var(--tw-optin-text);
				}

				.tw-optin__sub {
					margin: 0;
					max-width: 52ch;
					font-size: 13px;
					line-height: 1.6;
					color: var(--tw-optin-muted);
				}

				.tw-optin__form {
					padding: 20px 44px 20px 24px;
					background: var(--tw-optin-inset);
					border-left: 1px solid var(--tw-optin-line);
					min-width: 0;
				}

				.tw-optin input.tw-optin__email {
					display: block;
					width: 100%;
					margin: 0 0 10px;
					padding: 7px 10px;
					border: 1px solid #8c8f94;
					border-radius: 3px;
					background: #fff;
					color: #2c3338;
					font-size: 13px;
					line-height: 1.5;
					box-shadow: none;
				}
				.tw-optin input.tw-optin__email::placeholder { color: #646970; }
				.tw-optin input.tw-optin__email:focus {
					border-color: #2271b1;
					outline: 2px solid transparent;
					box-shadow: 0 0 0 1px #2271b1;
				}

				.tw-optin__consent {
					display: flex;
					align-items: flex-start;
					gap: 8px;
					margin: 0 0 12px;
					font-size: 12px;
					line-height: 1.5;
					color: var(--tw-optin-muted);
					cursor: pointer;
				}
				.tw-optin .tw-optin__consent input[type="checkbox"] {
					flex: 0 0 auto;
					margin: 2px 0 0;
					border-color: var(--tw-optin-muted);
					background: var(--tw-optin-ink);
				}
				.tw-optin .tw-optin__consent input[type="checkbox"]:checked {
					background: #2271b1;
					border-color: #2271b1;
				}
				.tw-optin .tw-optin__consent input[type="checkbox"]:checked::before {
					margin: -3px 0 0 -4px;
					color: #fff;
				}
				.tw-optin .tw-optin__consent input[type="checkbox"]:focus {
					outline: 2px solid var(--tw-optin-accent);
					outline-offset: 1px;
					box-shadow: none;
				}
				.tw-optin__consent a { color: #2271b1; }
				.tw-optin__consent a:hover,
				.tw-optin__consent a:focus { color: #135e96; }

				.tw-optin button.tw-optin__btn {
					display: inline-block;
					padding: 9px 18px;
					border: 1px solid transparent;
					border-radius: 3px;
					background: #2271b1;
					color: #fff;
					font-size: 13px;
					font-weight: 600;
					line-height: 1.2;
					cursor: pointer;
					transition: background 0.15s ease;
				}
				.tw-optin button.tw-optin__btn:hover:not( :disabled ) { background: #135e96; }
				.tw-optin button.tw-optin__btn:focus {
					outline: 2px solid var(--tw-optin-accent);
					outline-offset: 2px;
					box-shadow: none;
				}
				.tw-optin button.tw-optin__btn:disabled {
					background: #f6f7f7;
					border: 1px solid #dcdcde;
					color: #a7aaad;
					cursor: not-allowed;
				}

				.tw-optin__message {
					margin: 10px 0 0;
					font-size: 12px;
					line-height: 1.5;
					/*
					 * WP's #d63638 measures 4.40:1 on the #f6f7f7 form column and
					 * misses the 4.5 floor; core's darker error red clears it.
					 */
					color: #b32d2e;
				}
				.tw-optin__message:empty { display: none; }

				.tw-optin__thanks {
					margin: 0;
					padding: 20px 46px 20px 24px;
					font-size: 14px;
					line-height: 1.5;
					color: var(--tw-optin-text);
				}

				.tw-optin__dismiss {
					position: absolute;
					top: 6px;
					right: 8px;
					width: 28px;
					height: 28px;
					padding: 0;
					border: 0;
					border-radius: 3px;
					background: transparent;
					color: #787c82;
					font-size: 20px;
					line-height: 1;
					cursor: pointer;
				}
				.tw-optin__dismiss:hover { color: #1d2327; }
				.tw-optin__dismiss:focus {
					color: var(--tw-optin-text);
					outline: 2px solid var(--tw-optin-accent);
					outline-offset: -2px;
				}

				@media screen and (max-width: 960px) {
					.tw-optin__body { grid-template-columns: minmax(0, 1fr); }
					.tw-optin__form {
						padding: 20px 24px;
						border-left: 0;
						border-top: 1px solid var(--tw-optin-line);
					}
				}
				@media screen and (max-width: 782px) {
					.tw-optin { margin: 12px 12px 16px 0; }
					.woo-wallet-settings-page .tw-optin { margin: 12px; }
					.tw-optin__pitch,
					.tw-optin__thanks { padding: 18px 44px 18px 18px; }
					.tw-optin__form { padding: 18px; }
					.tw-optin__title { font-size: 15px; }
					.tw-optin__btn { width: 100%; }
				}
				@media ( prefers-reduced-motion: reduce ) {
					.tw-optin__btn { transition: none; }
				}
			</style>
			<script>
			jQuery( function ( $ ) {
				var $notice   = $( '#woo-wallet-optin' );
				if ( ! $notice.length ) {
					return;
				}
				var $consent  = $notice.find( '#woo-wallet-optin-consent' );
				var $submit   = $notice.find( '.tw-optin__btn' );
				var $message  = $notice.find( '.tw-optin__message' );
				var nonce     = $notice.data( 'nonce' );
				var tickedAt  = 0;

				$consent.on( 'change', function () {
					var checked = $( this ).is( ':checked' );
					// Stamped when they tick, sent as an age so the server keeps
					// the authoritative clock.
					tickedAt = checked ? Date.now() : 0;
					$submit.prop( 'disabled', ! checked );
				} );

				$submit.on( 'click', function () {
					if ( ! $consent.is( ':checked' ) ) {
						return;
					}

					$submit.prop( 'disabled', true );
					$message.text( '' );

					$.post( window.ajaxurl, {
						action: 'woo_wallet_optin_subscribe',
						nonce: nonce,
						consent: 1,
						consent_age: Math.round( ( Date.now() - tickedAt ) / 1000 ),
						email: $notice.find( '.tw-optin__email' ).val()
					} ).done( function ( response ) {
						if ( response && response.success ) {
							$notice.find( '.tw-optin__body' ).replaceWith(
								$( '<p class="tw-optin__thanks" />' ).text( response.data.message )
							);
							return;
						}
						$message.text( ( response && response.data && response.data.message ) || '' );
						$submit.prop( 'disabled', false );
					} ).fail( function () {
						$message.text( $submit.data( 'error' ) || '' );
						$submit.prop( 'disabled', false );
					} );
				} );

				$notice.on( 'click', '.tw-optin__dismiss', function () {
					$notice.remove();
					$.post( window.ajaxurl, {
						action: 'woo_wallet_optin_dismiss',
						nonce: nonce
					} );
				} );
			} );
			</script>
			<?php
		}

		/**
		 * AJAX: submit the opt-in.
		 *
		 * @return void
		 */
		public function ajax_subscribe() {
			check_ajax_referer( self::NONCE, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'woo-wallet' ) ), 403 );
			}

			// Hard gate: no consent, no request. Nothing below this line runs
			// unless the administrator ticked the box.
			if ( empty( $_POST['consent'] ) ) {
				wp_send_json_error( array( 'message' => __( 'Please tick the consent box first.', 'woo-wallet' ) ) );
			}

			$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
			if ( ! is_email( $email ) ) {
				wp_send_json_error( array( 'message' => __( 'Please enter a valid email address.', 'woo-wallet' ) ) );
			}

			// Age of the consent tick in seconds, clamped to an hour. The
			// timestamp is derived from the server clock, not the browser's.
			$age = isset( $_POST['consent_age'] ) ? absint( wp_unslash( $_POST['consent_age'] ) ) : 0;
			$age = min( $age, HOUR_IN_SECONDS );

			$response = wp_remote_post(
				self::ENDPOINT,
				array(
					'timeout' => 10,
					'headers' => array( 'Content-Type' => 'application/json' ),
					'body'    => wp_json_encode(
						array(
							'email'             => $email,
							'site_url'          => home_url(),
							'plugin_version'    => WOO_WALLET_PLUGIN_VERSION,
							'consent_timestamp' => gmdate( 'Y-m-d H:i:s', time() - $age ),
						)
					),
				)
			);

			$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

			// Failure leaves the banner in place so they can retry by hand. No
			// automatic retry, no scheduled re-attempt.
			if ( $code < 200 || $code >= 300 ) {
				wp_send_json_error( array( 'message' => __( 'Could not reach the server just now. Please try again.', 'woo-wallet' ) ) );
			}

			update_option( self::OPT_DONE, 'yes', false );

			wp_send_json_success( array( 'message' => __( 'Thanks — the guide is on its way shortly.', 'woo-wallet' ) ) );
		}

		/**
		 * AJAX: dismiss the banner permanently.
		 *
		 * @return void
		 */
		public function ajax_dismiss() {
			check_ajax_referer( self::NONCE, 'nonce' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_send_json_error( array( 'message' => __( 'You are not allowed to do that.', 'woo-wallet' ) ), 403 );
			}

			update_option( self::OPT_DISMISSED, 'yes', false );

			wp_send_json_success();
		}
	}

endif;

new Woo_Wallet_Optin_Notice();
