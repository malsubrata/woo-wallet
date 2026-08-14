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
		const UPGRADE_URL    = 'https://standalonetech.com/product/woocommerce-wallet-pro/';

		/**
		 * Displayed licence price. Hard-coded on purpose: this page must render
		 * fully offline, so it never fetches pricing from the store.
		 */
		const PRICE = '$79';
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
			$screen = get_current_screen();
			if ( $screen && woo_wallet_get_screen_id( self::MENU_SLUG ) === $screen->id ) {
				wp_enqueue_style( 'dashicons' );
			}
		}

		/**
		 * Determine runtime state of the Pro plugin.
		 *
		 * @return string One of: 'not_installed' | 'unlicensed' | 'licensed'.
		 */
		private function get_pro_state() {
			return woo_wallet_pro_state();
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
			$this->render_store_case();
			$this->render_features();
			$this->render_comparison();
			$this->render_use_cases();
			$this->render_faq();
			$this->render_bottom_cta();
		}

		/**
		 * Make the Pro case from the store's own figures.
		 *
		 * Renders nothing at all unless the store has meaningful wallet activity:
		 * a fresh install would otherwise be told it is carrying $0.00 across 0
		 * wallets, which reads as a broken page rather than an argument. Shares
		 * the threshold filter with the Wallet Dashboard nudge so a store tunes
		 * it once.
		 */
		private function render_store_case() {
			// Loaded on demand: the reports data service is only pulled in by the
			// Reports screen and the reports REST controller.
			require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';

			$data    = new Woo_Wallet_Reports_Data();
			$summary = $data->get_summary();

			$liability = isset( $summary['total_liability'] ) ? (float) $summary['total_liability'] : 0.0;
			$wallets   = isset( $summary['positive_wallets'] ) ? (int) $summary['positive_wallets'] : 0;

			/** This filter is documented in includes/admin/class-woo-wallet-reports.php */
			$threshold = (float) apply_filters( 'woo_wallet_pro_liability_nudge_threshold', 1000.0 );

			if ( $liability < $threshold || $wallets < 1 ) {
				return;
			}
			?>
			<section class="tw-section tw-storecase">
				<h2 class="tw-section__title"><?php esc_html_e( 'What this is worth on your store', 'woo-wallet' ); ?></h2>
				<div class="tw-storecase__figures">
					<div class="tw-storecase__figure">
						<span class="tw-storecase__value"><?php echo esc_html( $data->format_amount( $liability ) ); ?></span>
						<span class="tw-storecase__label"><?php esc_html_e( 'Outstanding wallet credit you owe customers today', 'woo-wallet' ); ?></span>
					</div>
					<div class="tw-storecase__figure">
						<span class="tw-storecase__value"><?php echo esc_html( number_format_i18n( $wallets ) ); ?></span>
						<span class="tw-storecase__label"><?php esc_html_e( 'Customers holding a positive wallet balance', 'woo-wallet' ); ?></span>
					</div>
				</div>
				<p class="tw-storecase__copy">
					<?php esc_html_e( 'Credit expiry reclaims the part of that balance nobody is ever going to spend, and breakage reporting tells you how much of it that is. Milestone and birthday bonuses give the customers already holding credit a reason to come back and spend it.', 'woo-wallet' ); ?>
				</p>
			</section>
			<?php
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
		 *
		 * One CTA only: the old "View Pricing" button sent people off-site at
		 * peak interest, so the price is stated here instead.
		 */
		private function render_hero() {
			?>
			<section class="tw-hero">
				<div class="tw-hero__inner">
					<h1><?php esc_html_e( 'TeraWallet Pro', 'woo-wallet' ); ?></h1>
					<p class="tw-hero__subtitle">
						<?php esc_html_e( 'Withdrawals, credit expiry, milestone and birthday bonuses, wallet coupons, bulk imports, breakage reporting and AffiliateWP payouts — everything your wallet needs once customers actually start using it.', 'woo-wallet' ); ?>
					</p>
					<p class="tw-hero__price">
						<span class="tw-hero__amount"><?php echo esc_html( self::PRICE ); ?></span>
						<span class="tw-hero__term"><?php esc_html_e( 'per year, one site', 'woo-wallet' ); ?></span>
					</p>
					<div class="tw-hero__cta">
						<a class="tw-btn tw-btn--primary" href="<?php echo esc_url( woo_wallet_pro_url( 'hero' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get TeraWallet Pro', 'woo-wallet' ); ?>
						</a>
					</div>
					<p class="tw-hero__reassure">
						<?php esc_html_e( '30-day money-back guarantee. Includes one year of updates and priority support.', 'woo-wallet' ); ?>
					</p>
				</div>
			</section>
			<?php
		}

		/**
		 * Single source of truth for the Pro feature set.
		 *
		 * Every marketing section on this page reads from here so the feature
		 * list, the comparison table and the CTAs can never drift apart. Each
		 * entry carries a merchant *outcome* (what the store gets) alongside the
		 * capability bullets, and its own `utm_content` slug for attribution.
		 *
		 * @return array<int,array<string,mixed>>
		 */
		private function pro_features() {
			return array(
				array(
					'id'      => 'withdrawals',
					'icon'    => 'dashicons-money-alt',
					'title'   => __( 'Wallet Withdrawals', 'woo-wallet' ),
					'outcome' => __( 'Unblocks marketplace and affiliate payouts — money can finally leave the wallet without you touching a bank portal.', 'woo-wallet' ),
					'bullets' => array(
						__( 'Payouts via PayPal, Stripe, bank transfer (BACS), Razorpay, Cashfree and Paystack.', 'woo-wallet' ),
						__( 'Review, approve or cancel every request from an admin queue, with customer-facing and private notes.', 'woo-wallet' ),
						__( 'Per-gateway processing fees: percentage, fixed, or both — deducted from the payout.', 'woo-wallet' ),
						__( 'Idempotent payouts plus signed provider webhooks, so re-approving a request can never pay twice.', 'woo-wallet' ),
					),
				),
				array(
					'id'      => 'credit-expiry',
					'icon'    => 'dashicons-clock',
					'title'   => __( 'Credit Expiry', 'woo-wallet' ),
					'outcome' => __( 'Reclaims unspent wallet liability instead of carrying it on your books forever.', 'woo-wallet' ),
					'bullets' => array(
						__( 'A global expiry period, with per-category overrides so cashback can expire faster than paid top-ups.', 'woo-wallet' ),
						__( 'FIFO redemption: the oldest credit is always spent first, per currency.', 'woo-wallet' ),
						__( 'Reminder emails a configurable number of days before credit expires.', 'woo-wallet' ),
						__( 'Expired credit drops out of the spendable balance automatically on a daily scheduled run.', 'woo-wallet' ),
					),
				),
				array(
					'id'      => 'earning-actions',
					'icon'    => 'dashicons-buddicons-activity',
					'title'   => __( 'Spend Milestone &amp; Birthday Bonuses', 'woo-wallet' ),
					'outcome' => __( 'Drives repeat purchases: customers earn a reason to come back before they have thought about leaving.', 'woo-wallet' ),
					'bullets' => array(
						__( 'Spend milestone bonus: credit the wallet every time lifetime spend crosses another multiple of a threshold you set.', 'woo-wallet' ),
						__( 'Birthday bonus: credit the wallet once a year, automatically.', 'woo-wallet' ),
						__( 'Adds a birthdate field to My Account and to the WordPress user profile screen.', 'woo-wallet' ),
						__( 'Both appear alongside the free earning actions in Settings — nothing new to learn.', 'woo-wallet' ),
					),
				),
				array(
					'id'      => 'reports',
					'icon'    => 'dashicons-chart-area',
					'title'   => __( 'Breakage, Aging &amp; Payout Reports', 'woo-wallet' ),
					'outcome' => __( 'Tells you how much of your outstanding liability is never going to be spent — the number your accountant keeps asking for.', 'woo-wallet' ),
					'bullets' => array(
						__( 'Unlocks the five locked report slots on your Wallet Dashboard: breakage, aging, expiry trend, withdrawals and coupons.', 'woo-wallet' ),
						__( 'See how much credit is about to expire, and how much already has.', 'woo-wallet' ),
						__( 'Withdrawal and coupon activity broken out instead of lumped into "Other".', 'woo-wallet' ),
					),
				),
				array(
					'id'      => 'coupons',
					'icon'    => 'dashicons-tag',
					'title'   => __( 'Wallet Coupons', 'woo-wallet' ),
					'outcome' => __( 'Turns a discount campaign into stored credit that has to be spent with you.', 'woo-wallet' ),
					'bullets' => array(
						__( 'Coupon codes that top up the wallet instead of discounting one order.', 'woo-wallet' ),
						__( 'Bulk-generate hundreds of unique codes for a campaign in one screen.', 'woo-wallet' ),
						__( 'Per-coupon currency, redeemed by the customer from My Account.', 'woo-wallet' ),
					),
				),
				array(
					'id'      => 'importer',
					'icon'    => 'dashicons-upload',
					'title'   => __( 'Bulk CSV Importer', 'woo-wallet' ),
					'outcome' => __( 'Migrates an existing credit programme in one upload instead of hundreds of manual adjustments.', 'woo-wallet' ),
					'bullets' => array(
						__( 'Credit or debit any number of customers from a single CSV.', 'woo-wallet' ),
						__( 'Optional per-row expiry date and currency columns.', 'woo-wallet' ),
						__( 'Progress and per-row errors reported as the import runs.', 'woo-wallet' ),
					),
				),
				array(
					'id'      => 'affiliatewp',
					'icon'    => 'dashicons-groups',
					'title'   => __( 'AffiliateWP Payouts', 'woo-wallet' ),
					'outcome' => __( 'Pays commission as store credit, so affiliate earnings come back to you as orders instead of leaving as cash.', 'woo-wallet' ),
					'bullets' => array(
						__( 'Adds the wallet as an AffiliateWP payout method.', 'woo-wallet' ),
						__( 'Affiliates opt in from their own dashboard.', 'woo-wallet' ),
						__( 'Commissions land in the wallet with no bank round-trip.', 'woo-wallet' ),
					),
				),
			);
		}

		/**
		 * Feature grid.
		 */
		private function render_features() {
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'What Pro adds to your store', 'woo-wallet' ); ?></h2>
				<div class="tw-features">
					<?php foreach ( $this->pro_features() as $feature ) : ?>
						<div class="tw-feature">
							<span class="tw-feature__icon dashicons <?php echo esc_attr( $feature['icon'] ); ?>" aria-hidden="true"></span>
							<h3><?php echo wp_kses( $feature['title'], array() ); ?></h3>
							<p class="tw-feature__outcome"><?php echo esc_html( $feature['outcome'] ); ?></p>
							<ul class="tw-feature__list">
								<?php foreach ( $feature['bullets'] as $bullet ) : ?>
									<li><?php echo esc_html( $bullet ); ?></li>
								<?php endforeach; ?>
							</ul>
							<a class="tw-feature__link" href="<?php echo esc_url( woo_wallet_pro_url( 'feature-' . $feature['id'] ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php
								printf(
									/* translators: %s: Pro feature name. */
									esc_html__( 'Get %s', 'woo-wallet' ),
									esc_html( wp_strip_all_tags( html_entity_decode( $feature['title'], ENT_QUOTES, 'UTF-8' ) ) )
								);
								?>
							</a>
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
			$groups = $this->comparison_groups();
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'Free vs Pro', 'woo-wallet' ); ?></h2>
				<p class="tw-section__intro"><?php esc_html_e( 'The free plugin is a complete wallet. Pro is what you need once that wallet is holding real money.', 'woo-wallet' ); ?></p>
				<div class="tw-compare__scroll">
					<table class="tw-compare">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Feature', 'woo-wallet' ); ?></th>
								<th class="tw-compare__cell"><?php esc_html_e( 'Free', 'woo-wallet' ); ?></th>
								<th class="tw-compare__cell"><?php esc_html_e( 'Pro', 'woo-wallet' ); ?></th>
							</tr>
						</thead>
						<?php foreach ( $groups as $group ) : ?>
							<tbody>
								<tr class="tw-compare__group">
									<th colspan="3" scope="colgroup"><?php echo esc_html( $group['title'] ); ?></th>
								</tr>
								<?php foreach ( $group['rows'] as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row[0] ); ?></td>
										<td class="tw-compare__cell"><?php echo $this->tick( $row[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
										<td class="tw-compare__cell"><?php echo $this->tick( $row[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						<?php endforeach; ?>
					</table>
				</div>
				<?php // The table itself is a factual feature list, but its CTA is an upsell: never rendered for someone who already has Pro. ?>
				<?php if ( ! woo_wallet_is_pro_active() ) : ?>
					<p class="tw-compare__cta">
						<a href="<?php echo esc_url( woo_wallet_pro_url( 'comparison-table' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php
							printf(
								/* translators: %s: licence price, e.g. $79. */
								esc_html__( 'Get everything in the Pro column — %s per year', 'woo-wallet' ),
								esc_html( self::PRICE )
							);
							?>
						</a>
					</p>
				<?php endif; ?>
			</section>
			<?php
		}

		/**
		 * Comparison rows, grouped by area of the plugin.
		 *
		 * Free rows are the free plugin's actual shipped feature set — the point
		 * is that Free reads as generous and clearly bounded, not as a crippled
		 * demo.
		 *
		 * @return array<int,array{title:string,rows:array<int,array{0:string,1:bool,2:bool}>}>
		 */
		private function comparison_groups() {
			return array(
				array(
					'title' => __( 'Wallet core', 'woo-wallet' ),
					'rows'  => array(
						array( __( 'Wallet ledger with credit, debit and full transaction history', 'woo-wallet' ), true, true ),
						array( __( 'Customer top-ups through any WooCommerce gateway', 'woo-wallet' ), true, true ),
						array( __( 'Pay for an order fully from the wallet', 'woo-wallet' ), true, true ),
						array( __( 'Partial payment: wallet balance plus another gateway', 'woo-wallet' ), true, true ),
						array( __( 'Peer-to-peer transfers between customers', 'woo-wallet' ), true, true ),
						array( __( 'Manual admin credit and debit, wallet lock/unlock', 'woo-wallet' ), true, true ),
						array( __( 'Multi-currency support (WOOCS, WPML, CURCY, YayCurrency, Aelia)', 'woo-wallet' ), true, true ),
						array( __( 'WooCommerce Blocks checkout support', 'woo-wallet' ), true, true ),
					),
				),
				array(
					'title' => __( 'Rewards and earning', 'woo-wallet' ),
					'rows'  => array(
						array( __( 'Cashback engine: cart, product and category rules', 'woo-wallet' ), true, true ),
						array( __( 'Signup bonus', 'woo-wallet' ), true, true ),
						array( __( 'Daily visit reward', 'woo-wallet' ), true, true ),
						array( __( 'Product review reward', 'woo-wallet' ), true, true ),
						array( __( 'Referral rewards', 'woo-wallet' ), true, true ),
						array( __( 'Spend milestone bonus', 'woo-wallet' ), false, true ),
						array( __( 'Birthday bonus, with a birthdate field on My Account', 'woo-wallet' ), false, true ),
					),
				),
				array(
					'title' => __( 'Getting money out', 'woo-wallet' ),
					'rows'  => array(
						array( __( 'Customer withdrawal requests with an admin approval queue', 'woo-wallet' ), false, true ),
						array( __( 'Payouts via PayPal, Stripe, BACS, Razorpay, Cashfree, Paystack', 'woo-wallet' ), false, true ),
						array( __( 'Per-gateway withdrawal processing fees', 'woo-wallet' ), false, true ),
						array( __( 'AffiliateWP commissions paid as wallet credit', 'woo-wallet' ), false, true ),
					),
				),
				array(
					'title' => __( 'Liability and operations', 'woo-wallet' ),
					'rows'  => array(
						array( __( 'Wallet Dashboard: outstanding liability, wallet count, composition', 'woo-wallet' ), true, true ),
						array( __( 'Credit expiry with FIFO redemption', 'woo-wallet' ), false, true ),
						array( __( 'Per-category expiry periods and pre-expiry reminder emails', 'woo-wallet' ), false, true ),
						array( __( 'Breakage, aging and expiry-trend reports', 'woo-wallet' ), false, true ),
						array( __( 'Withdrawal and coupon reports', 'woo-wallet' ), false, true ),
						array( __( 'Bulk CSV import of balances', 'woo-wallet' ), false, true ),
						array( __( 'Wallet coupons and bulk coupon generation', 'woo-wallet' ), false, true ),
					),
				),
				array(
					'title' => __( 'Integrations and developers', 'woo-wallet' ),
					'rows'  => array(
						array( __( 'Marketplace support: Dokan, WCFM and WC Marketplace', 'woo-wallet' ), true, true ),
						array( __( 'REST API for balances and transactions', 'woo-wallet' ), true, true ),
						array( __( 'Hooks and filters for custom wallet logic', 'woo-wallet' ), true, true ),
						array( __( 'Coupon REST API', 'woo-wallet' ), false, true ),
						array( __( 'Automatic updates and priority support', 'woo-wallet' ), false, true ),
					),
				),
			);
		}

		/**
		 * Use cases tiles.
		 */
		private function render_use_cases() {
			$cases = array(
				array(
					'icon'  => 'dashicons-store',
					'title' => __( 'Multi-vendor marketplace', 'woo-wallet' ),
					'copy'  => __( 'Vendor commissions already land in the wallet on Dokan, WCFM and WC Marketplace. Withdrawals are what lets vendors actually take that money out — to PayPal, Stripe or their bank — with an approval queue and per-gateway fees you control.', 'woo-wallet' ),
					'uses'  => __( 'Uses: Wallet Withdrawals, withdrawal reports.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-awards',
					'title' => __( 'Loyalty and repeat purchase', 'woo-wallet' ),
					'copy'  => __( 'Free cashback rewards the order a customer has already placed. Milestone and birthday bonuses reward the next one — credit that appears when someone crosses a spend threshold, or on their birthday, with an expiry date attached so it prompts a visit rather than sitting there.', 'woo-wallet' ),
					'uses'  => __( 'Uses: Spend milestone bonus, birthday bonus, credit expiry, wallet coupons.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-chart-bar',
					'title' => __( 'Controlling wallet liability', 'woo-wallet' ),
					'copy'  => __( 'Every credit you issue is money you owe. Expiry caps how long you carry it, per category, with reminder emails so customers are told before it lapses — and breakage and aging reports show what is about to expire and what already has.', 'woo-wallet' ),
					'uses'  => __( 'Uses: Credit expiry, breakage, aging and expiry-trend reports.', 'woo-wallet' ),
				),
				array(
					'icon'  => 'dashicons-migrate',
					'title' => __( 'Migrating or running a campaign', 'woo-wallet' ),
					'copy'  => __( 'Moving off another credit system, or issuing credit to a segment, means hundreds of adjustments. Import them from a CSV in one pass — with expiry and currency per row — or generate a batch of unique coupon codes that redeem into the wallet.', 'woo-wallet' ),
					'uses'  => __( 'Uses: Bulk CSV importer, wallet coupons, coupon REST API.', 'woo-wallet' ),
				),
			);
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'Built for every wallet use case', 'woo-wallet' ); ?></h2>
				<div class="tw-usecases">
					<?php foreach ( $cases as $case ) : ?>
						<div class="tw-usecase">
							<h3>
								<span class="dashicons <?php echo esc_attr( $case['icon'] ); ?>" aria-hidden="true"></span>
								<?php echo esc_html( $case['title'] ); ?>
							</h3>
							<p><?php echo esc_html( $case['copy'] ); ?></p>
							<p class="tw-usecase__uses"><?php echo esc_html( $case['uses'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</section>
			<?php
		}

		/**
		 * Purchase FAQ.
		 *
		 * Native <details> elements: no JavaScript, and every answer is readable
		 * with the accordion collapsed by a screen reader.
		 */
		private function render_faq() {
			$faq = array(
				array(
					'q' => __( 'What does the licence cover?', 'woo-wallet' ),
					'a' => sprintf(
						/* translators: %s: licence price, e.g. $79. */
						__( '%s per year covers one site, and includes every Pro feature — no per-module or per-gateway extras.', 'woo-wallet' ),
						self::PRICE
					),
				),
				array(
					'q' => __( 'Does Pro replace the free plugin?', 'woo-wallet' ),
					'a' => __( 'No — Pro extends it. TeraWallet Pro is an add-on and requires this free plugin to stay installed and active. Nothing is removed or replaced: your existing wallets, transactions and settings carry straight over, and the Pro features appear alongside what you already have.', 'woo-wallet' ),
				),
				array(
					'q' => __( 'Is there a refund policy?', 'woo-wallet' ),
					'a' => __( 'Yes — 30 days, no questions asked.', 'woo-wallet' ),
				),
				array(
					'q' => __( 'What happens if I do not renew?', 'woo-wallet' ),
					'a' => __( 'Pro keeps working. An expired licence stops automatic updates and support until you renew; it does not switch any feature off or touch your wallet data.', 'woo-wallet' ),
				),
				array(
					'q' => __( 'What do updates and support include?', 'woo-wallet' ),
					'a' => __( 'The licence includes one year of automatic updates and priority support, and renews automatically each year until you cancel.', 'woo-wallet' ),
				),
			);
			?>
			<section class="tw-section">
				<h2 class="tw-section__title"><?php esc_html_e( 'Before you buy', 'woo-wallet' ); ?></h2>
				<div class="tw-faq">
					<?php foreach ( $faq as $item ) : ?>
						<details class="tw-faq__item">
							<summary><?php echo esc_html( $item['q'] ); ?></summary>
							<p><?php echo esc_html( $item['a'] ); ?></p>
						</details>
					<?php endforeach; ?>
				</div>
				<p class="tw-faq__cta">
					<a href="<?php echo esc_url( woo_wallet_pro_url( 'faq' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'See full licence and purchase details', 'woo-wallet' ); ?>
					</a>
				</p>
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
				<p>
					<?php
					printf(
						/* translators: %s: licence price, e.g. $79. */
						esc_html__( 'Everything in Free, plus withdrawals, credit expiry, milestone and birthday bonuses, wallet coupons, bulk imports, breakage reporting and AffiliateWP payouts — %s per year for one site.', 'woo-wallet' ),
						esc_html( self::PRICE )
					);
					?>
				</p>
				<a class="tw-btn tw-btn--primary" href="<?php echo esc_url( woo_wallet_pro_url( 'footer' ) ); ?>" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Get TeraWallet Pro', 'woo-wallet' ); ?>
				</a>
				<p class="tw-bottom-cta__reassure"><?php esc_html_e( '30-day money-back guarantee.', 'woo-wallet' ); ?></p>
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
				.woo-wallet-go-pro-wrap { margin: 20px auto 40px; }
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
				.tw-hero__subtitle { font-size: 16px; line-height: 1.6; opacity: .92; margin: 0 0 24px; }
				.tw-hero__price { margin: 0 0 20px; display: flex; align-items: baseline; justify-content: center; gap: 8px; flex-wrap: wrap; }
				.tw-hero__amount { font-size: 40px; font-weight: 700; line-height: 1; }
				.tw-hero__term { font-size: 15px; opacity: .9; }
				.tw-hero__cta { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
				.tw-hero__reassure { margin: 16px 0 0; font-size: 13px; opacity: .88; }

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
				.tw-feature__outcome { font-weight: 600; color: #1d2327 !important; }
				.tw-feature__list {
					margin: 10px 0 0; padding: 0 0 0 18px;
					color: #50575e; font-size: 13px; line-height: 1.55;
					list-style: disc;
				}
				.tw-feature__list li { margin: 0 0 4px; }
				.tw-feature__link {
					display: inline-block; margin-top: 12px;
					font-size: 13px; font-weight: 600;
					color: #674399; text-decoration: none;
				}
				.tw-feature__link:hover, .tw-feature__link:focus { color: #4a2e73; text-decoration: underline; }
				.tw-feature__link::after { content: " \2192"; }

				.tw-section__intro { margin: -8px 0 16px; color: #50575e; font-size: 14px; max-width: 720px; }

				.tw-compare__scroll { overflow-x: auto; }
				.tw-compare {
					width: 100%;
					min-width: 480px;
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
				.tw-compare tbody:last-child tr:last-child td { border-bottom: 0; }
				.tw-compare__group th {
					background: #f6f7f7;
					font-size: 12px;
					font-weight: 700;
					text-transform: uppercase;
					letter-spacing: .04em;
					color: #50575e;
					padding: 10px 18px;
				}
				.tw-compare__cta { margin: 14px 0 0; font-size: 14px; font-weight: 600; }
				.tw-compare__cta a { color: #674399; text-decoration: none; }
				.tw-compare__cta a:hover, .tw-compare__cta a:focus { color: #4a2e73; text-decoration: underline; }
				.tw-compare__cta a::after { content: " \2192"; }
				.tw-compare__cell { text-align: center; width: 120px; }
				.tw-tick { font-size: 20px; width: 20px; height: 20px; }
				.tw-tick--yes { color: #1f8a45; }
				.tw-tick--no  { color: #c1c5cc; }

				.tw-storecase__figures {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
					gap: 16px;
					margin: 0 0 14px;
				}
				.tw-storecase__figure {
					background: #faf8ff;
					border: 1px solid #e8e1f4;
					border-radius: 8px;
					padding: 20px 22px;
				}
				.tw-storecase__value { display: block; font-size: 28px; font-weight: 700; color: #1d2327; line-height: 1.2; }
				.tw-storecase__label { display: block; margin-top: 6px; color: #50575e; font-size: 13px; line-height: 1.5; }
				.tw-storecase__copy { margin: 0; color: #50575e; font-size: 14px; line-height: 1.6; max-width: 760px; }

				.tw-usecases {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
					gap: 16px;
				}
				.tw-usecase {
					background: #faf8ff;
					border: 1px solid #e8e1f4;
					border-radius: 8px;
					padding: 20px 22px;
					color: #1d2327;
				}
				.tw-usecase h3 { display: flex; align-items: center; gap: 8px; margin: 0 0 8px; font-size: 16px; }
				.tw-usecase p { margin: 0; color: #50575e; font-size: 13px; line-height: 1.6; }
				.tw-usecase__uses { margin-top: 10px !important; font-weight: 600; color: #1d2327 !important; }
				.tw-usecase .dashicons { color: #674399; }

				.tw-faq { display: grid; gap: 8px; max-width: 820px; }
				.tw-faq__item {
					background: #fff;
					border: 1px solid #e0dce8;
					border-radius: 8px;
					padding: 14px 18px;
				}
				.tw-faq__item summary {
					cursor: pointer;
					font-weight: 600;
					color: #1d2327;
					font-size: 14px;
				}
				.tw-faq__item summary:focus { outline: 2px solid #674399; outline-offset: 2px; }
				.tw-faq__item p { margin: 10px 0 0; color: #50575e; font-size: 13px; line-height: 1.6; }
				.tw-faq__cta { margin: 14px 0 0; font-size: 13px; }
				.tw-faq__cta a { color: #674399; text-decoration: none; font-weight: 600; }
				.tw-faq__cta a:hover, .tw-faq__cta a:focus { color: #4a2e73; text-decoration: underline; }

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
				.tw-bottom-cta__reassure { margin: 16px 0 0; color: #50575e; font-size: 13px; }

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

				@media (max-width: 782px) {
					.woo-wallet-go-pro-wrap { margin: 12px auto 32px; }
					.tw-hero { padding: 36px 22px; }
					.tw-hero h1 { font-size: 26px; }
					.tw-hero__amount { font-size: 34px; }
					.tw-hero__cta .tw-btn { width: 100%; text-align: center; }
					.tw-features, .tw-usecases, .tw-storecase__figures { grid-template-columns: 1fr; }
					.tw-compare th, .tw-compare td { padding: 12px 14px; }
					.tw-compare__cell { width: 72px; }
				}

				@media (max-width: 600px) {
					.tw-hero { padding: 28px 18px; }
					.tw-hero h1 { font-size: 22px; }
					.tw-section__title { font-size: 19px; }
					.tw-compare th, .tw-compare td { padding: 10px 12px; }
					.tw-compare__cell { width: 64px; }
				}
			</style>
			<?php
		}
	}

endif;

new Woo_Wallet_Go_Pro_Page();
