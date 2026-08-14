<?php
/**
 * TeraWallet email opt-in.
 *
 * An explicit, opt-in-only invitation to receive setup tips and wallet strategy
 * content. It lives on the plugin's own admin screens — it is deliberately not
 * an admin notice, so it never appears on a screen that does not belong to this
 * plugin.
 *
 * Privacy contract, and the reason this file has no other network code:
 *
 *  - Nothing is transmitted at page-render time. No script, no image, no
 *    prefetch, no beacon. Rendering this card makes zero outbound requests.
 *  - The consent box is unchecked, the email field is empty, and neither is
 *    pre-filled from the WordPress user. There is no pre-selection to undo.
 *  - The only outbound request in this class happens inside the submit handler,
 *    after a nonce check, a capability check, and an explicit consent checkbox.
 *  - Dismissing it sends nothing at all.
 *
 * @package TeraWallet
 * @since 1.6.11
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Email_Optin' ) ) :

	/**
	 * Renders and handles the email opt-in card.
	 */
	class Woo_Wallet_Email_Optin {

		/**
		 * User meta recording that this user subscribed or dismissed.
		 */
		const META_KEY = '_woo_wallet_email_optin_state';

		/**
		 * Privacy policy shown next to the consent checkbox.
		 */
		const PRIVACY_URL = 'https://standalonetech.com/privacy-policy/';

		/**
		 * Class constructor.
		 */
		public function __construct() {
			if ( woo_wallet_is_pro_active() ) {
				return;
			}

			add_action( 'woo_wallet_reports_page_bottom', array( $this, 'render' ) );
			add_action( 'woo_wallet_go_pro_page_bottom', array( $this, 'render' ) );
			add_action( 'admin_post_woo_wallet_email_optin', array( $this, 'handle_submit' ) );
			add_action( 'admin_post_woo_wallet_email_optin_dismiss', array( $this, 'handle_dismiss' ) );
			add_action( 'admin_notices', array( $this, 'show_result_notice' ) );
		}

		/**
		 * The subscription endpoint.
		 *
		 * @return string
		 */
		private function endpoint() {
			/**
			 * Endpoint the opt-in posts to. Requests are only ever made from
			 * handle_submit(), after explicit consent.
			 *
			 * @since 1.6.11
			 * @param string $url Endpoint URL.
			 */
			return apply_filters( 'terawallet_email_optin_endpoint', 'https://standalonetech.com/wp-json/terawallet/v1/subscribe' );
		}

		/**
		 * Whether the current user has already subscribed or dismissed.
		 *
		 * @return bool
		 */
		private function is_resolved() {
			return (bool) get_user_meta( get_current_user_id(), self::META_KEY, true );
		}

		/**
		 * Render the opt-in card.
		 *
		 * @return void
		 */
		public function render() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				return;
			}
			if ( $this->is_resolved() ) {
				return;
			}

			$action_url = admin_url( 'admin-post.php' );
			?>
			<div class="tw-optin">
				<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="tw-optin__form">
					<input type="hidden" name="action" value="woo_wallet_email_optin" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $this->current_url() ); ?>" />
					<?php wp_nonce_field( 'woo_wallet_email_optin' ); ?>

					<h2 class="tw-optin__title"><?php esc_html_e( 'Get wallet setup tips by email', 'woo-wallet' ); ?></h2>
					<p class="tw-optin__lede">
						<?php esc_html_e( 'Occasional email about configuring cashback, reducing outstanding liability, and running a wallet program that pays for itself. Unsubscribe any time.', 'woo-wallet' ); ?>
					</p>

					<p class="tw-optin__row">
						<label class="screen-reader-text" for="tw-optin-email"><?php esc_html_e( 'Email address', 'woo-wallet' ); ?></label>
						<input
							type="email"
							id="tw-optin-email"
							name="optin_email"
							class="regular-text"
							value=""
							autocomplete="off"
							placeholder="<?php esc_attr_e( 'you@example.com', 'woo-wallet' ); ?>"
							required
						/>
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Send me tips', 'woo-wallet' ); ?></button>
					</p>

					<p class="tw-optin__consent">
						<label for="tw-optin-consent">
							<input type="checkbox" id="tw-optin-consent" name="optin_consent" value="1" />
							<?php
							printf(
								/* translators: %s: link to the privacy policy */
								esc_html__( 'Yes, email me TeraWallet tips. My address is sent to StandaloneTech only when I submit this form, and is used for nothing else. %s', 'woo-wallet' ),
								sprintf(
									'<a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
									esc_url( self::PRIVACY_URL ),
									esc_html__( 'Privacy policy', 'woo-wallet' )
								)
							); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- both parts escaped inline.
							?>
						</label>
					</p>
				</form>

				<form method="post" action="<?php echo esc_url( $action_url ); ?>" class="tw-optin__dismiss-form">
					<input type="hidden" name="action" value="woo_wallet_email_optin_dismiss" />
					<input type="hidden" name="redirect_to" value="<?php echo esc_attr( $this->current_url() ); ?>" />
					<?php wp_nonce_field( 'woo_wallet_email_optin_dismiss' ); ?>
					<button type="submit" class="tw-optin__dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'woo-wallet' ); ?>">
						<?php esc_html_e( 'No thanks', 'woo-wallet' ); ?>
					</button>
				</form>
			</div>
			<style>
				.tw-optin{position:relative;max-width:720px;margin:26px 0 0;padding:26px 30px;border:1px solid #dcdcde;border-radius:14px;background:#fff;}
				.tw-optin__title{margin:0 0 6px;font-size:17px;}
				.tw-optin__lede{margin:0 0 16px;font-size:13.5px;color:#50575e;max-width:60ch;}
				.tw-optin__row{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin:0 0 10px;}
				.tw-optin__consent{margin:0;font-size:12.5px;color:#50575e;max-width:70ch;}
				.tw-optin__consent label{display:flex;gap:8px;align-items:flex-start;line-height:1.5;}
				.tw-optin__dismiss-form{position:absolute;top:18px;right:20px;margin:0;}
				.tw-optin__dismiss{border:0;background:none;color:#8c8f94;font-size:12.5px;cursor:pointer;text-decoration:underline;padding:0;}
				.tw-optin__dismiss:hover,.tw-optin__dismiss:focus{color:#1d2327;}
			</style>
			<?php
		}

		/**
		 * Handle the opt-in submission.
		 *
		 * This is the only place in the plugin that contacts the vendor, and it
		 * runs only after nonce, capability and explicit consent all pass.
		 *
		 * @return void
		 */
		public function handle_submit() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'woo-wallet' ) );
			}
			check_admin_referer( 'woo_wallet_email_optin' );

			// No consent, no request. Bail before touching the network.
			if ( empty( $_POST['optin_consent'] ) ) {
				$this->redirect_back( 'consent' );
			}

			$email = isset( $_POST['optin_email'] ) ? sanitize_email( wp_unslash( $_POST['optin_email'] ) ) : '';
			if ( ! is_email( $email ) ) {
				$this->redirect_back( 'invalid' );
			}

			$response = wp_remote_post(
				$this->endpoint(),
				array(
					'timeout' => 10,
					'body'    => array(
						'email'   => $email,
						'source'  => 'woo-wallet',
						'version' => WOO_WALLET_PLUGIN_VERSION,
					),
				)
			);

			if ( is_wp_error( $response ) || 300 <= (int) wp_remote_retrieve_response_code( $response ) ) {
				$this->redirect_back( 'failed' );
			}

			update_user_meta( get_current_user_id(), self::META_KEY, 'subscribed' );
			$this->redirect_back( 'subscribed' );
		}

		/**
		 * Handle dismissal. Sends nothing anywhere.
		 *
		 * @return void
		 */
		public function handle_dismiss() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to do that.', 'woo-wallet' ) );
			}
			check_admin_referer( 'woo_wallet_email_optin_dismiss' );

			update_user_meta( get_current_user_id(), self::META_KEY, 'dismissed' );
			$this->redirect_back( '' );
		}

		/**
		 * Show the result of a submission.
		 *
		 * @return void
		 */
		public function show_result_notice() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$result = isset( $_GET['tw_optin'] ) ? sanitize_key( wp_unslash( $_GET['tw_optin'] ) ) : '';
			if ( ! $result ) {
				return;
			}

			$messages = array(
				'subscribed' => array( 'success', __( 'Thanks — you are subscribed to TeraWallet tips.', 'woo-wallet' ) ),
				'consent'    => array( 'warning', __( 'Please tick the consent box before subscribing.', 'woo-wallet' ) ),
				'invalid'    => array( 'warning', __( 'That email address does not look valid.', 'woo-wallet' ) ),
				'failed'     => array( 'error', __( 'Could not reach the subscription service. Nothing was saved — please try again later.', 'woo-wallet' ) ),
			);

			if ( ! isset( $messages[ $result ] ) ) {
				return;
			}

			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $messages[ $result ][0] ),
				esc_html( $messages[ $result ][1] )
			);
		}

		/**
		 * URL of the screen the form was submitted from.
		 *
		 * @return string
		 */
		private function current_url() {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'woo-wallet';
			return add_query_arg( array( 'page' => $page ), admin_url( 'admin.php' ) );
		}

		/**
		 * Redirect back to the originating screen with a result code.
		 *
		 * @param string $result Result code, or '' for none.
		 * @return void
		 */
		private function redirect_back( $result ) {
			$target = isset( $_POST['redirect_to'] ) ? esc_url_raw( wp_unslash( $_POST['redirect_to'] ) ) : admin_url( 'admin.php?page=woo-wallet' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- callers verify the nonce first.

			if ( $result ) {
				$target = add_query_arg( 'tw_optin', $result, $target );
			}

			wp_safe_redirect( $target );
			exit;
		}
	}

endif;

new Woo_Wallet_Email_Optin();
