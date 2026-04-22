<?php
/**
 * TeraWallet Go Pro admin page.
 *
 * Replaces the legacy Extensions page with a single conversion-focused screen
 * that showcases Pro features, compares Free vs Pro, and (when the Pro plugin
 * is installed) hosts the license activation UI.
 *
 * The menu slug `woo-wallet-extensions` is intentionally preserved so that
 * license inactive notices in woo-wallet-pro and the legacy standalone
 * plugins (withdrawal, importer, coupons, credit-expiry) still link here.
 *
 * @package TeraWallet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Go_Pro_Page' ) ) :

	/**
	 * TeraWallet "Go Pro" admin page.
	 */
	class Woo_Wallet_Go_Pro_Page {

		const MENU_SLUG      = 'woo-wallet-extensions';
		const PRO_BASENAME   = 'woo-wallet-pro/woo-wallet-pro.php';
		const LICENSE_OPTION = '_wallet_settings_extensions_woo_wallet_pro_license';
		const LICENSE_FLAG   = 'woo_wallet_pro_license_activated';
		const UPGRADE_URL    = 'https://standalonetech.com/product/woocommerce-wallet-pro/?utm_source=free_plugin&utm_medium=go_pro_page&utm_campaign=upgrade';
		const API_KEYS_URL   = 'https://standalonetech.com/my-account/';
		const DOCS_URL       = 'https://docs.standalonetech.com/';
		const SUPPORT_URL    = 'https://standalonetech.com/support-forum/';

		/**
		 * Class constructor.
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 65 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'admin_init', array( $this, 'handle_license_submit' ) );
			add_action( 'admin_head', array( $this, 'highlight_go_pro_menu' ) );
		}

		/**
		 * Print CSS that highlights the "Go Pro" submenu in the admin sidebar
		 * with a gold pill + sparkle. Skipped once a valid license is active.
		 */
		public function highlight_go_pro_menu() {
			if ( 'licensed' === $this->get_pro_state() ) {
				return;
			}
			?>
			<style id="tw-go-pro-menu-highlight">
				#adminmenu #toplevel_page_woo-wallet .wp-submenu a[href$="page=<?php echo esc_attr( self::MENU_SLUG ); ?>"] {
					color: #fbbf24 !important;
					font-weight: 600 !important;
					position: relative;
				}
				#adminmenu #toplevel_page_woo-wallet .wp-submenu a[href$="page=<?php echo esc_attr( self::MENU_SLUG ); ?>"]:hover,
				#adminmenu #toplevel_page_woo-wallet .wp-submenu a[href$="page=<?php echo esc_attr( self::MENU_SLUG ); ?>"]:focus {
					color: #fcd34d !important;
					outline: 0;
				}
			</style>
			<?php
		}

		/**
		 * Register the Go Pro submenu.
		 */
		public function admin_menu() {
			add_submenu_page(
				'woo-wallet',
				'licensed' === $this->get_pro_state() ? __( 'Pro License', 'woo-wallet' ) : __( 'Upgrade to Pro', 'woo-wallet' ),
				'licensed' === $this->get_pro_state() ? __( 'Pro License', 'woo-wallet' ) : __( 'Upgrade to Pro', 'woo-wallet' ),
				get_wallet_user_capability(),
				self::MENU_SLUG,
				array( $this, 'plugin_page' )
			);
		}

		/**
		 * Enqueue dashicons on our screen.
		 */
		public function admin_enqueue_scripts() {
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';
			$prefix    = sanitize_title( __( 'TeraWallet', 'woo-wallet' ) );
			if ( "{$prefix}_page_" . self::MENU_SLUG === $screen_id ) {
				wp_enqueue_style( 'dashicons' );
			}
		}

		/**
		 * Determine runtime state of the Pro plugin.
		 *
		 * @return string One of: 'not_installed' | 'unlicensed' | 'licensed'.
		 */
		private function get_pro_state() {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( ! is_plugin_active( self::PRO_BASENAME ) ) {
				return 'not_installed';
			}
			return get_option( self::LICENSE_FLAG ) ? 'licensed' : 'unlicensed';
		}

		/**
		 * Handle license form submission. Writes to the same option the Pro
		 * plugin's license class listens on, so its existing activation hook
		 * performs the WC AM API call.
		 */
		public function handle_license_submit() {
			if ( empty( $_POST['woo_wallet_go_pro_license_nonce'] ) ) {
				return;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			if ( ! wp_verify_nonce(
				sanitize_key( wp_unslash( $_POST['woo_wallet_go_pro_license_nonce'] ) ),
				'woo_wallet_go_pro_license'
			) ) {
				return;
			}

			$value = array(
				'licence_key' => isset( $_POST['licence_key'] ) ? sanitize_text_field( wp_unslash( $_POST['licence_key'] ) ) : '',
				'is_activate' => ! empty( $_POST['is_activate'] ) ? 'on' : 'off',
				'nonce_rand'  => wp_generate_password( 10, false ),
			);

			// The Pro plugin's license class only listens on update_option_<option>.
			// If the option doesn't exist yet, update_option() internally falls
			// through to add_option() and the hook never fires — so pre-seed it
			// to guarantee the update path is taken.
			if ( false === get_option( self::LICENSE_OPTION ) ) {
				add_option( self::LICENSE_OPTION, array() );
			}
			update_option( self::LICENSE_OPTION, $value );

			set_transient( 'woo_wallet_go_pro_settings_errors', get_settings_errors(), 30 );

			wp_safe_redirect(
				add_query_arg(
					array( 'settings-updated' => 'true' ),
					admin_url( 'admin.php?page=' . self::MENU_SLUG )
				)
			);
			exit;
		}

		/**
		 * Main page renderer.
		 */
		public function plugin_page() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-wallet' ) );
			}

			$state = $this->get_pro_state();

			$stored_errors = get_transient( 'woo_wallet_go_pro_settings_errors' );
			if ( is_array( $stored_errors ) ) {
				foreach ( $stored_errors as $err ) {
					add_settings_error(
						isset( $err['setting'] ) ? $err['setting'] : '',
						isset( $err['code'] ) ? $err['code'] : '',
						isset( $err['message'] ) ? $err['message'] : '',
						isset( $err['type'] ) ? $err['type'] : 'error'
					);
				}
				delete_transient( 'woo_wallet_go_pro_settings_errors' );
			}

			echo '<div class="wrap woo-wallet-go-pro-wrap"><h1></h1>';
			$this->render_styles();
			settings_errors();

			switch ( $state ) {
				case 'licensed':
					$this->render_licensed();
					break;
				case 'unlicensed':
					$this->render_unlicensed();
					break;
				case 'not_installed':
				default:
					$this->render_marketing();
					break;
			}

			echo '</div>';
		}

		// -----------------------------------------------------------------
		// State renderers.
		// -----------------------------------------------------------------

		/**
		 * CASE 1 — Pro not installed. Full marketing page.
		 */
		private function render_marketing() {
			$this->render_hero();
			$this->render_features();
			$this->render_comparison();
			$this->render_use_cases();
			$this->render_bottom_cta();
		}

		/**
		 * CASE 2 — Pro installed but not licensed.
		 */
		private function render_unlicensed() {
			?>
			<div class="tw-card tw-notice tw-notice--warning">
				<span class="dashicons dashicons-warning" aria-hidden="true"></span>
				<div>
					<h2><?php esc_html_e( 'Activate your license', 'woo-wallet' ); ?></h2>
					<p><?php esc_html_e( 'TeraWallet Pro is installed. Activate your license to receive automatic updates and priority support.', 'woo-wallet' ); ?></p>
				</div>
			</div>
			<?php
			$this->render_license_form();
			$this->render_comparison();
		}

		/**
		 * CASE 3 — Pro installed and licensed.
		 */
		private function render_licensed() {
			$options = get_option( self::LICENSE_OPTION, array() );
			$key     = isset( $options['licence_key'] ) ? (string) $options['licence_key'] : '';
			$masked  = $this->mask_key( $key );
			?>
			<div class="tw-card tw-notice tw-notice--success">
				<span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
				<div>
					<h2><?php esc_html_e( 'TeraWallet Pro is active and licensed', 'woo-wallet' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: masked license key. */
							esc_html__( 'License key: %s', 'woo-wallet' ),
							'<code>' . esc_html( $masked ) . '</code>'
						);
						?>
					</p>
					<form method="post" class="tw-inline-form">
						<?php wp_nonce_field( 'woo_wallet_go_pro_license', 'woo_wallet_go_pro_license_nonce' ); ?>
						<input type="hidden" name="licence_key" value="<?php echo esc_attr( $key ); ?>" />
						<input type="hidden" name="is_activate" value="on" />
						<button type="submit" class="button button-secondary">
							<?php esc_html_e( 'Deactivate License', 'woo-wallet' ); ?>
						</button>
					</form>
				</div>
			</div>

			<div class="tw-quicklinks">
				<a class="tw-quicklink" href="<?php echo esc_url( admin_url( 'admin.php?page=woo-wallet-settings' ) ); ?>">
					<span class="dashicons dashicons-admin-settings" aria-hidden="true"></span>
					<h3><?php esc_html_e( 'Settings', 'woo-wallet' ); ?></h3>
					<p><?php esc_html_e( 'Configure wallet behavior, payments, cashback, and rewards.', 'woo-wallet' ); ?></p>
				</a>
				<a class="tw-quicklink" href="<?php echo esc_url( self::DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-book" aria-hidden="true"></span>
					<h3><?php esc_html_e( 'Documentation', 'woo-wallet' ); ?></h3>
					<p><?php esc_html_e( 'Step-by-step guides for every Pro feature.', 'woo-wallet' ); ?></p>
				</a>
				<a class="tw-quicklink" href="<?php echo esc_url( self::SUPPORT_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<span class="dashicons dashicons-sos" aria-hidden="true"></span>
					<h3><?php esc_html_e( 'Support', 'woo-wallet' ); ?></h3>
					<p><?php esc_html_e( 'Get priority help from our support team.', 'woo-wallet' ); ?></p>
				</a>
			</div>
			<?php
		}

		// -----------------------------------------------------------------
		// Section renderers.
		// -----------------------------------------------------------------

		/**
		 * Hero / top banner.
		 */
		private function render_hero() {
			?>
			<section class="tw-hero">
				<div class="tw-hero__inner">
					<h1><?php esc_html_e( 'Unlock the Full Power of TeraWallet Pro', 'woo-wallet' ); ?></h1>
					<p class="tw-hero__subtitle">
						<?php esc_html_e( 'Withdrawals, coupons, bulk imports, credit expiry and AffiliateWP payouts — everything you need to run a complete wallet economy, in one premium upgrade.', 'woo-wallet' ); ?>
					</p>
					<div class="tw-hero__cta">
						<a class="tw-btn tw-btn--primary" href="<?php echo esc_url( self::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Upgrade to Pro', 'woo-wallet' ); ?>
						</a>
						<a class="tw-btn tw-btn--ghost" href="<?php echo esc_url( self::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'View Pricing', 'woo-wallet' ); ?>
						</a>
					</div>
				</div>
			</section>
			<?php
		}

		/**
		 * Feature grid.
		 */
		private function render_features() {
			$features = array(
				array(
					'icon'  => 'dashicons-money-alt',
					'title' => __( 'Wallet Withdrawal', 'woo-wallet' ),
					'desc'  => __( 'Let customers cash out wallet balance to bank, PayPal, Stripe, Razorpay, Cashfree, or Paystack.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-tag',
					'title' => __( 'Wallet Coupons', 'woo-wallet' ),
					'desc'  => __( 'Create redeemable coupon codes that drop credit straight into customer wallets.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-upload',
					'title' => __( 'Bulk Importer', 'woo-wallet' ),
					'desc'  => __( 'Top-up, debit, or adjust balances for hundreds of users in a single CSV upload.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-clock',
					'title' => __( 'Credit Expiry (FIFO)', 'woo-wallet' ),
					'desc'  => __( 'Automatically expire old credits first with a precise FIFO ledger and scheduled cleanup.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-groups',
					'title' => __( 'AffiliateWP Integration', 'woo-wallet' ),
					'desc'  => __( 'Pay affiliate commissions directly into the partner wallet — no bank round-trip required.', 'woo-wallet' ),
				),
			);
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'Everything you get with Pro', 'woo-wallet' ); ?></h2>
				<div class="tw-features">
					<?php foreach ( $features as $f ) : ?>
						<div class="tw-feature">
							<span class="tw-feature__icon dashicons <?php echo esc_attr( $f['icon'] ); ?>" aria-hidden="true"></span>
							<h3><?php echo esc_html( $f['title'] ); ?></h3>
							<p><?php echo esc_html( $f['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		/**
		 * Free vs Pro comparison table.
		 */
		private function render_comparison() {
			$rows = array(
				array( __( 'Wallet System', 'woo-wallet' ), true, true ),
				array( __( 'Withdrawal', 'woo-wallet' ), false, true ),
				array( __( 'Coupons', 'woo-wallet' ), false, true ),
				array( __( 'Importer', 'woo-wallet' ), false, true ),
				array( __( 'Credit Expiry', 'woo-wallet' ), false, true ),
				array( __( 'Affiliate Integration', 'woo-wallet' ), false, true ),
			);
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'Free vs Pro', 'woo-wallet' ); ?></h2>
				<table class="tw-compare">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Feature', 'woo-wallet' ); ?></th>
							<th><?php esc_html_e( 'Free', 'woo-wallet' ); ?></th>
							<th><?php esc_html_e( 'Pro', 'woo-wallet' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td><?php echo esc_html( $row[0] ); ?></td>
								<td class="tw-compare__cell"><?php echo $this->tick( $row[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
								<td class="tw-compare__cell"><?php echo $this->tick( $row[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</section>
			<?php
		}

		/**
		 * Use cases tiles.
		 */
		private function render_use_cases() {
			$cases = array(
				array( 'dashicons-store', __( 'Marketplace payouts', 'woo-wallet' ) ),
				array( 'dashicons-awards', __( 'Cashback rewards', 'woo-wallet' ) ),
				array( 'dashicons-networking', __( 'Affiliate commissions', 'woo-wallet' ) ),
				array( 'dashicons-update', __( 'Store credit automation', 'woo-wallet' ) ),
			);
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'Built for every wallet use case', 'woo-wallet' ); ?></h2>
				<div class="tw-usecases">
					<?php foreach ( $cases as $c ) : ?>
						<div class="tw-usecase">
							<span class="dashicons <?php echo esc_attr( $c[0] ); ?>" aria-hidden="true"></span>
							<span><?php echo esc_html( $c[1] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		/**
		 * Bottom CTA.
		 */
		private function render_bottom_cta() {
			?>
			<section class="tw-bottom-cta">
				<h2><?php esc_html_e( 'Ready to upgrade?', 'woo-wallet' ); ?></h2>
				<p><?php esc_html_e( 'Join thousands of stores running a full-featured wallet economy on TeraWallet Pro.', 'woo-wallet' ); ?></p>
				<a class="tw-btn tw-btn--primary" href="<?php echo esc_url( self::UPGRADE_URL ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Upgrade to Pro Now', 'woo-wallet' ); ?>
				</a>
			</section>
			<?php
		}

		/**
		 * License form (CASE 2).
		 */
		private function render_license_form() {
			$options = get_option( self::LICENSE_OPTION, array() );
			$key     = isset( $options['licence_key'] ) ? (string) $options['licence_key'] : '';
			?>
			<section class="tw-card tw-license">
				<h2><?php esc_html_e( 'License activation', 'woo-wallet' ); ?></h2>
				<form method="post" class="tw-license__form">
					<?php wp_nonce_field( 'woo_wallet_go_pro_license', 'woo_wallet_go_pro_license_nonce' ); ?>
					<label for="tw-licence-key"><?php esc_html_e( 'API License Key', 'woo-wallet' ); ?></label>
					<input
						type="text"
						id="tw-licence-key"
						name="licence_key"
						value="<?php echo esc_attr( $key ); ?>"
						placeholder="XXXX-XXXX-XXXX-XXXX"
						autocomplete="off"
						spellcheck="false"
					/>
					<p class="tw-license__help">
						<?php
						printf(
							/* translators: %s: URL to the customer API-keys page. */
							wp_kses_post( __( 'Your license key is available at <a href="%s" target="_blank" rel="noopener noreferrer">standalonetech.com &rarr; My Account &rarr; API Keys</a>.', 'woo-wallet' ) ),
							esc_url( self::API_KEYS_URL )
						);
						?>
					</p>
					<button type="submit" class="tw-btn tw-btn--primary">
						<?php esc_html_e( 'Activate License', 'woo-wallet' ); ?>
					</button>
				</form>
			</section>
			<?php
		}

		// -----------------------------------------------------------------
		// Helpers.
		// -----------------------------------------------------------------

		/**
		 * Tick / cross cell.
		 *
		 * @param bool $yes yes.
		 * @return string
		 */
		private function tick( $yes ) {
			if ( $yes ) {
				return '<span class="tw-tick tw-tick--yes dashicons dashicons-yes" aria-label="' . esc_attr__( 'Included', 'woo-wallet' ) . '"></span>';
			}
			return '<span class="tw-tick tw-tick--no dashicons dashicons-no-alt" aria-label="' . esc_attr__( 'Not included', 'woo-wallet' ) . '"></span>';
		}

		/**
		 * Mask a license key for display.
		 *
		 * @param string $key key.
		 * @return string
		 */
		private function mask_key( $key ) {
			$key = (string) $key;
			if ( strlen( $key ) <= 4 ) {
				return str_repeat( '•', max( 0, strlen( $key ) ) );
			}
			return str_repeat( '•', 8 ) . substr( $key, -4 );
		}

		/**
		 * Inline page styles.
		 */
		private function render_styles() {
			?>
			<style>
				.woo-wallet-go-pro-wrap { max-width: 1160px; margin: 20px auto 40px; }
				.woo-wallet-go-pro-wrap * { box-sizing: border-box; }

				.tw-hero {
					background: linear-gradient(135deg, #674399 0%, #4a2e73 100%);
					color: #fff;
					border-radius: 10px;
					padding: 48px 32px;
					margin: 0 0 24px;
					text-align: center;
				}
				.tw-hero__inner { max-width: 760px; margin: 0 auto; }
				.tw-hero h1 { color: #fff; font-size: 32px; line-height: 1.2; margin: 0 0 12px; font-weight: 600; }
				.tw-hero__subtitle { font-size: 16px; line-height: 1.6; opacity: .92; margin: 0 0 28px; }
				.tw-hero__cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }

				.tw-btn {
					display: inline-block;
					padding: 12px 28px;
					border-radius: 6px;
					font-weight: 600;
					text-decoration: none;
					line-height: 1;
					border: 2px solid transparent;
					transition: transform .1s ease, box-shadow .15s ease;
				}
				.tw-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(255,255,255,.4); }
				.tw-btn--primary { background: #fff; color: #674399; border-color: #fff; }
				.tw-btn--primary:hover { background: #f6f2ff; color: #4a2e73; }
				.tw-btn--ghost { background: transparent; color: #fff; border-color: rgba(255,255,255,.7); }
				.tw-btn--ghost:hover { background: rgba(255,255,255,.12); color: #fff; }

				.tw-section { margin: 32px 0; }
				.tw-section__title { font-size: 22px; font-weight: 600; margin: 0 0 16px; color: #1d2327; }

				.tw-features {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
					gap: 16px;
				}
				.tw-feature {
					background: #fff;
					border: 1px solid #e0dce8;
					border-radius: 8px;
					padding: 22px;
					transition: box-shadow .15s ease, transform .15s ease;
				}
				.tw-feature:hover { box-shadow: 0 6px 18px rgba(103,67,153,.12); transform: translateY(-2px); }
				.tw-feature__icon {
					font-size: 28px; width: 28px; height: 28px;
					color: #674399; margin-bottom: 10px;
				}
				.tw-feature h3 { margin: 0 0 6px; font-size: 16px; color: #1d2327; }
				.tw-feature p { margin: 0; color: #50575e; font-size: 13px; line-height: 1.55; }

				.tw-compare {
					width: 100%;
					border-collapse: separate;
					border-spacing: 0;
					background: #fff;
					border: 1px solid #e0dce8;
					border-radius: 8px;
					overflow: hidden;
				}
				.tw-compare th, .tw-compare td {
					padding: 14px 18px;
					text-align: left;
					border-bottom: 1px solid #f0edf5;
				}
				.tw-compare thead th { background: #faf8ff; color: #1d2327; font-weight: 600; }
				.tw-compare tbody tr:last-child td { border-bottom: 0; }
				.tw-compare__cell { text-align: center; width: 120px; }
				.tw-tick { font-size: 20px; width: 20px; height: 20px; }
				.tw-tick--yes { color: #1f8a45; }
				.tw-tick--no  { color: #c1c5cc; }

				.tw-usecases {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
					gap: 12px;
				}
				.tw-usecase {
					display: flex; align-items: center; gap: 10px;
					background: #faf8ff;
					border: 1px solid #e8e1f4;
					border-radius: 6px;
					padding: 14px 16px;
					color: #1d2327; font-weight: 500;
				}
				.tw-usecase .dashicons { color: #674399; }

				.tw-bottom-cta {
					background: #faf8ff;
					border: 1px solid #e8e1f4;
					border-radius: 10px;
					padding: 36px 24px;
					text-align: center;
					margin: 32px 0 0;
				}
				.tw-bottom-cta h2 { margin: 0 0 8px; font-size: 22px; color: #1d2327; }
				.tw-bottom-cta p { margin: 0 0 20px; color: #50575e; }
				.tw-bottom-cta .tw-btn--primary {
					background: #674399; color: #fff; border-color: #674399;
				}
				.tw-bottom-cta .tw-btn--primary:hover { background: #4a2e73; border-color: #4a2e73; color: #fff; }

				.tw-card {
					background: #fff;
					border: 1px solid #e0dce8;
					border-radius: 8px;
					padding: 22px 24px;
					margin: 0 0 20px;
				}
				.tw-notice {
					display: flex; gap: 14px; align-items: flex-start;
				}
				.tw-notice .dashicons { font-size: 28px; width: 28px; height: 28px; flex: 0 0 28px; margin-top: 2px; }
				.tw-notice h2 { margin: 0 0 4px; font-size: 17px; }
				.tw-notice p { margin: 0 0 8px; color: #50575e; }
				.tw-notice--warning { border-left: 4px solid #dba617; }
				.tw-notice--warning .dashicons { color: #dba617; }
				.tw-notice--success { border-left: 4px solid #1f8a45; }
				.tw-notice--success .dashicons { color: #1f8a45; }

				.tw-inline-form { margin-top: 10px; }

				.tw-license h2 { margin: 0 0 14px; font-size: 18px; color: #1d2327; }
				.tw-license__form label { display: block; font-weight: 600; margin: 0 0 6px; color: #1d2327; }
				.tw-license__form input[type="text"] {
					width: 100%; max-width: 480px;
					padding: 10px 12px;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
					font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
					font-size: 14px;
				}
				.tw-license__form input[type="text"]:focus {
					outline: none; border-color: #674399; box-shadow: 0 0 0 2px rgba(103,67,153,.25);
				}
				.tw-license__help { margin: 8px 0 18px; color: #50575e; font-size: 13px; }
				.tw-license .tw-btn--primary { background: #674399; color: #fff; border-color: #674399; cursor: pointer; }
				.tw-license .tw-btn--primary:hover { background: #4a2e73; border-color: #4a2e73; }

				.tw-quicklinks {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
					gap: 16px;
					margin: 20px 0;
				}
				.tw-quicklink {
					display: block;
					background: #fff;
					border: 1px solid #e0dce8;
					border-radius: 8px;
					padding: 22px;
					text-decoration: none;
					color: #1d2327;
					transition: box-shadow .15s ease, transform .15s ease;
				}
				.tw-quicklink:hover {
					box-shadow: 0 6px 18px rgba(103,67,153,.12);
					transform: translateY(-2px);
					color: #1d2327;
				}
				.tw-quicklink .dashicons { font-size: 26px; width: 26px; height: 26px; color: #674399; margin-bottom: 10px; }
				.tw-quicklink h3 { margin: 0 0 6px; font-size: 16px; }
				.tw-quicklink p { margin: 0; color: #50575e; font-size: 13px; line-height: 1.5; }

				@media (max-width: 600px) {
					.tw-hero { padding: 32px 20px; }
					.tw-hero h1 { font-size: 24px; }
					.tw-compare th, .tw-compare td { padding: 10px 12px; }
					.tw-compare__cell { width: 70px; }
				}
			</style>
			<?php
		}
	}

endif;

new Woo_Wallet_Go_Pro_Page();
