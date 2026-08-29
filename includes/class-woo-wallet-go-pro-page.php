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
		 * Lowest licence price. Hard-coded on purpose: this page must render
		 * fully offline, so it never fetches pricing from the store.
		 *
		 * Only the entry price is stored, never the full tier list: this plugin
		 * ships and then freezes on the store that installed it, so a price list
		 * baked in today would still be on screen long after it changed. "From"
		 * the floor stays true when the higher tiers move.
		 */
		const PRICE = '$79';
		const API_KEYS_URL   = 'https://standalonetech.com/my-account/';
		const DOCS_URL       = 'https://docs.standalonetech.com/';
		const SUPPORT_URL    = 'https://standalonetech.com/support-forum/';

		/**
		 * Price wording shared by every promo surface, in one place.
		 *
		 * @return string
		 */
		public static function price_label() {
			/* translators: %s: lowest licence price, e.g. $79. */
			$label = sprintf( __( 'from %s', 'woo-wallet' ), self::PRICE );

			/**
			 * Filter the Pro price label shown across the admin.
			 *
			 * @since 1.6.14
			 *
			 * @param string $label Price label.
			 */
			return apply_filters( 'woo_wallet_pro_price_label', $label );
		}

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
				<div class="tw-section__head">
					<h2 class="tw-section__title"><?php esc_html_e( 'What this is worth on your store', 'woo-wallet' ); ?></h2>
					<span class="tw-section__meta"><?php esc_html_e( 'Your figures', 'woo-wallet' ); ?></span>
				</div>
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
				<div class="tw-hero__lead">
					<p class="tw-eyebrow"><?php esc_html_e( 'TeraWallet — Upgrade', 'woo-wallet' ); ?></p>
					<h1><?php esc_html_e( 'Everything your wallet needs once customers actually use it.', 'woo-wallet' ); ?></h1>
					<p class="tw-hero__subtitle">
						<?php esc_html_e( 'Withdrawals, credit expiry, milestone and birthday bonuses, wallet coupons, bulk imports, breakage reporting and AffiliateWP payouts.', 'woo-wallet' ); ?>
					</p>
					<div class="tw-hero__cta">
						<a class="tw-btn tw-btn--light" href="<?php echo esc_url( woo_wallet_pro_url( 'hero' ) ); ?>" target="_blank" rel="noopener noreferrer">
							<?php esc_html_e( 'Get TeraWallet Pro', 'woo-wallet' ); ?>
							<span class="tw-btn__arrow" aria-hidden="true">&rarr;</span>
						</a>
						<p class="tw-hero__reassure">
							<?php esc_html_e( '30-day money-back guarantee', 'woo-wallet' ); ?><br />
							<?php esc_html_e( 'One year of updates and priority support', 'woo-wallet' ); ?>
						</p>
					</div>
				</div>
				<div class="tw-hero__panel">
					<?php // Composed rather than printed through price_label(): the design sets the amount apart from its qualifiers. self::PRICE stays the one source of the number. ?>
					<p class="tw-price">
						<span class="tw-price__from"><?php esc_html_e( 'from', 'woo-wallet' ); ?></span>
						<span class="tw-price__amount"><?php echo esc_html( self::PRICE ); ?></span>
						<span class="tw-price__term"><?php esc_html_e( '/ year', 'woo-wallet' ); ?></span>
					</p>
					<div class="tw-hero__rule" aria-hidden="true"></div>
					<div class="tw-hero__licence">
						<p><?php esc_html_e( 'One licence covers one site.', 'woo-wallet' ); ?></p>
						<p>
							<?php
							printf(
								/* translators: %s: link to the pricing page, link text "pricing page". */
								esc_html__( '5-site and 25-site licences are on the %s.', 'woo-wallet' ),
								'<a href="' . esc_url( woo_wallet_pro_url( 'multisite-licence' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'pricing page', 'woo-wallet' ) . '</a>'
							);
							?>
						</p>
						<p class="tw-hero__meta">
							<?php
							printf(
								/* translators: %s: TeraWallet free plugin version number. */
								esc_html__( 'Version %s · Free plugin active', 'woo-wallet' ),
								esc_html( WOO_WALLET_PLUGIN_VERSION )
							);
							?>
						</p>
					</div>
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
			$features = $this->pro_features();
			$count    = count( $features );
			$index    = 0;
			?>
			<section class="tw-section">
				<div class="tw-section__head">
					<h2 class="tw-section__title"><?php esc_html_e( 'What Pro adds to your store', 'woo-wallet' ); ?></h2>
					<span class="tw-section__meta">
						<?php
						printf(
							/* translators: %s: number of Pro modules, zero-padded. */
							esc_html( _n( '%s module', '%s modules', $count, 'woo-wallet' ) ),
							esc_html( str_pad( (string) $count, 2, '0', STR_PAD_LEFT ) )
						);
						?>
					</span>
				</div>
				<div class="tw-features">
					<?php foreach ( $features as $feature ) : ?>
						<?php ++$index; ?>
						<div class="tw-feature">
							<span class="tw-feature__index" aria-hidden="true"><?php echo esc_html( str_pad( (string) $index, 2, '0', STR_PAD_LEFT ) ); ?></span>
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
								<span class="tw-btn__arrow" aria-hidden="true">&rarr;</span>
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
				<div class="tw-section__head tw-section__head--stacked">
					<h2 class="tw-section__title"><?php esc_html_e( 'Free vs Pro', 'woo-wallet' ); ?></h2>
					<p class="tw-section__intro"><?php esc_html_e( 'The free plugin is a complete wallet. Pro is what you need once that wallet is holding real money.', 'woo-wallet' ); ?></p>
				</div>
				<div class="tw-compare__card">
					<div class="tw-compare__scroll">
						<table class="tw-compare">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Feature', 'woo-wallet' ); ?></th>
									<th class="tw-compare__cell"><?php esc_html_e( 'Free', 'woo-wallet' ); ?></th>
									<th class="tw-compare__cell tw-compare__cell--pro"><?php esc_html_e( 'Pro', 'woo-wallet' ); ?></th>
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
						<div class="tw-compare__foot">
							<span>
								<?php
								printf(
									/* translators: %s: price label, e.g. "from $79". */
									esc_html__( 'Everything in the Pro column, %s per year.', 'woo-wallet' ),
									esc_html( self::price_label() )
								);
								?>
							</span>
							<a href="<?php echo esc_url( woo_wallet_pro_url( 'comparison-table' ) ); ?>" target="_blank" rel="noopener noreferrer">
								<?php esc_html_e( 'Upgrade to Pro', 'woo-wallet' ); ?>
								<span class="tw-btn__arrow" aria-hidden="true">&rarr;</span>
							</a>
						</div>
					<?php endif; ?>
				</div>
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
					'title' => __( 'Multi-vendor marketplace', 'woo-wallet' ),
					'copy'  => __( 'Vendor commissions already land in the wallet on Dokan, WCFM and WC Marketplace. Withdrawals are what lets vendors actually take that money out — to PayPal, Stripe or their bank — with an approval queue and per-gateway fees you control.', 'woo-wallet' ),
					'uses'  => __( 'Wallet Withdrawals, withdrawal reports.', 'woo-wallet' ),
				),
				array(
					'title' => __( 'Loyalty and repeat purchase', 'woo-wallet' ),
					'copy'  => __( 'Free cashback rewards the order a customer has already placed. Milestone and birthday bonuses reward the next one — credit that appears when someone crosses a spend threshold, or on their birthday, with an expiry date attached so it prompts a visit rather than sitting there.', 'woo-wallet' ),
					'uses'  => __( 'Spend milestone bonus, birthday bonus, credit expiry, wallet coupons.', 'woo-wallet' ),
				),
				array(
					'title' => __( 'Controlling wallet liability', 'woo-wallet' ),
					'copy'  => __( 'Every credit you issue is money you owe. Expiry caps how long you carry it, per category, with reminder emails so customers are told before it lapses — and breakage and aging reports show what is about to expire and what already has.', 'woo-wallet' ),
					'uses'  => __( 'Credit expiry, breakage, aging and expiry-trend reports.', 'woo-wallet' ),
				),
				array(
					'title' => __( 'Migrating or running a campaign', 'woo-wallet' ),
					'copy'  => __( 'Moving off another credit system, or issuing credit to a segment, means hundreds of adjustments. Import them from a CSV in one pass — with expiry and currency per row — or generate a batch of unique coupon codes that redeem into the wallet.', 'woo-wallet' ),
					'uses'  => __( 'Bulk CSV importer, wallet coupons, coupon REST API.', 'woo-wallet' ),
				),
			);
			?>
			<section class="tw-section">
				<div class="tw-section__head">
					<h2 class="tw-section__title"><?php esc_html_e( 'Built for every wallet use case', 'woo-wallet' ); ?></h2>
				</div>
				<div class="tw-usecases">
					<?php foreach ( $cases as $case ) : ?>
						<div class="tw-usecase">
							<h3><?php echo esc_html( $case['title'] ); ?></h3>
							<p><?php echo esc_html( $case['copy'] ); ?></p>
							<p class="tw-usecase__uses">
								<span class="tw-usecase__uses-label"><?php echo esc_html_x( 'Uses', 'label before the Pro features a use case relies on', 'woo-wallet' ); ?></span>
								<span class="tw-btn__arrow" aria-hidden="true">&rarr;</span>
								<?php echo esc_html( $case['uses'] ); ?>
							</p>
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
					'a' => __( 'One licence activates Pro on one site and includes every Pro feature — no per-module or per-gateway extras. 5-site and 25-site licences are available on the pricing page.', 'woo-wallet' ),
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
					'a' => __( 'Compatibility releases for WooCommerce and WordPress, new module features, security fixes, and direct email support from the team that maintains the plugin. The licence renews automatically each year until you cancel.', 'woo-wallet' ),
				),
			);
			?>
			<section class="tw-section tw-faq-section">
				<div class="tw-faq__rail">
					<h2 class="tw-section__title"><?php esc_html_e( 'Before you buy', 'woo-wallet' ); ?></h2>
					<p class="tw-faq__rail-copy"><?php esc_html_e( 'Licence, renewal and support questions, answered plainly.', 'woo-wallet' ); ?></p>
					<a class="tw-faq__cta" href="<?php echo esc_url( woo_wallet_pro_url( 'faq' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'See full licence and purchase details', 'woo-wallet' ); ?>
						<span class="tw-btn__arrow" aria-hidden="true">&rarr;</span>
					</a>
				</div>
				<div class="tw-faq">
					<?php foreach ( $faq as $item ) : ?>
						<details class="tw-faq__item">
							<summary>
								<?php echo esc_html( $item['q'] ); ?>
								<span class="tw-faq__chev" aria-hidden="true">+</span>
							</summary>
							<p><?php echo esc_html( $item['a'] ); ?></p>
						</details>
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
				<div class="tw-bottom-cta__copy">
					<h2><?php esc_html_e( 'Ready to upgrade?', 'woo-wallet' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: %s: price label, e.g. "from $79". */
							esc_html__( 'Everything in Free, plus withdrawals, credit expiry, milestone and birthday bonuses, wallet coupons, bulk imports, breakage reporting and AffiliateWP payouts — %s per year.', 'woo-wallet' ),
							esc_html( self::price_label() )
						);
						?>
					</p>
				</div>
				<div class="tw-bottom-cta__act">
					<a class="tw-btn tw-btn--light" href="<?php echo esc_url( woo_wallet_pro_url( 'footer' ) ); ?>" target="_blank" rel="noopener noreferrer">
						<?php esc_html_e( 'Get TeraWallet Pro', 'woo-wallet' ); ?>
						<span class="tw-btn__arrow" aria-hidden="true">&rarr;</span>
					</a>
					<p class="tw-bottom-cta__reassure"><?php esc_html_e( '30-day money-back guarantee.', 'woo-wallet' ); ?></p>
				</div>
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
				return '<span class="tw-tick tw-tick--yes" aria-hidden="true">&#10003;</span><span class="screen-reader-text">' . esc_html__( 'Included', 'woo-wallet' ) . '</span>';
			}
			return '<span class="tw-tick tw-tick--no" aria-hidden="true">&#8211;</span><span class="screen-reader-text">' . esc_html__( 'Not included', 'woo-wallet' ) . '</span>';
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
				/*
				 * Design system for this screen, kept deliberately local: hairline
				 * rules on a light ground, one dark panel at each end, mono type for
				 * numbers and labels. No webfont is loaded — a WordPress.org plugin
				 * must not pull assets from a third-party host, so the design's
				 * Public Sans / IBM Plex Mono pairing is rendered with the admin's
				 * own system stacks.
				 */
				.woo-wallet-go-pro-wrap {
					--tw-ink: #2a1942;
					--tw-accent: #623e96;
					--tw-accent-hover: #4a237a;
					--tw-accent-soft: #6f4fa1;
					--tw-line: #dedce2;
					--tw-line-soft: #f0eef2;
					--tw-text: #1c1a1f;
					--tw-text-soft: #4a4551;
					--tw-muted: #6d6875;
					--tw-surface: #fff;
					--tw-surface-alt: #faf9fb;
					--tw-group: #f6f4f9;
					--tw-on-dark: #dad3e9;
					--tw-on-dark-muted: #c1b8d4;
					--tw-on-dark-faint: #aa9dc5;
					--tw-eyebrow: #beaede;
					--tw-mono: ui-monospace, SFMono-Regular, "SF Mono", Menlo, Consolas, "Liberation Mono", monospace;

					max-width: 1320px;
					margin: 20px auto 48px;
					color: var(--tw-text);
				}
				.woo-wallet-go-pro-wrap * { box-sizing: border-box; }
				.woo-wallet-go-pro-wrap h1,
				.woo-wallet-go-pro-wrap h2,
				.woo-wallet-go-pro-wrap h3 { color: inherit; }

				.tw-eyebrow,
				.tw-section__meta,
				.tw-hero__meta,
				.tw-usecase__uses,
				.tw-price__amount,
				.tw-storecase__value,
				.tw-compare thead th,
				.tw-faq__chev {
					font-family: var(--tw-mono);
				}

				/* ---------- Hero ---------- */

				.tw-hero {
					display: grid;
					grid-template-columns: minmax(0, 1.15fr) minmax(0, 0.85fr);
					background: var(--tw-ink);
					color: #fff;
					border-radius: 6px;
					overflow: hidden;
					margin: 0 0 8px;
				}
				.tw-hero__lead { padding: 48px 46px 44px; }
				.tw-eyebrow {
					margin: 0;
					font-size: 12px;
					letter-spacing: .14em;
					text-transform: uppercase;
					color: var(--tw-eyebrow);
				}
				.tw-hero h1 {
					margin: 18px 0 0;
					padding: 0;
					font-size: 38px;
					line-height: 1.06;
					font-weight: 700;
					letter-spacing: -.025em;
					color: #fff;
				}
				.tw-hero__subtitle {
					margin: 18px 0 0;
					max-width: 50ch;
					font-size: 15px;
					line-height: 1.6;
					color: var(--tw-on-dark);
				}
				.tw-hero__cta {
					display: flex;
					align-items: center;
					gap: 20px;
					margin-top: 30px;
					flex-wrap: wrap;
				}
				.tw-hero__reassure {
					margin: 0;
					font-size: 13px;
					line-height: 1.5;
					color: var(--tw-on-dark-muted);
				}
				.tw-hero__panel {
					display: flex;
					flex-direction: column;
					justify-content: center;
					padding: 48px 40px;
					border-left: 1px solid rgba(255,255,255,.14);
					background: rgba(0,0,0,.12);
				}
				.tw-price { display: flex; align-items: baseline; gap: 10px; margin: 0; flex-wrap: wrap; }
				.tw-price__from,
				.tw-price__term { font-size: 15px; color: var(--tw-on-dark-muted); }
				.tw-price__amount { font-size: 48px; font-weight: 500; line-height: 1; letter-spacing: -.03em; }
				.tw-hero__rule { height: 1px; background: rgba(255,255,255,.16); margin: 24px 0; }
				.tw-hero__licence { display: grid; gap: 10px; font-size: 13.5px; line-height: 1.5; color: var(--tw-on-dark); }
				.tw-hero__licence p { margin: 0; }
				.tw-hero__licence a { color: #fff; text-decoration: underline; text-underline-offset: 3px; }
				.tw-hero__licence a:hover, .tw-hero__licence a:focus { color: #fff; }
				.tw-hero__meta {
					padding-top: 4px;
					font-size: 12px;
					letter-spacing: .08em;
					text-transform: uppercase;
					color: var(--tw-on-dark-faint);
				}

				/* ---------- Buttons and links ---------- */

				.tw-btn {
					display: inline-flex;
					align-items: center;
					gap: 10px;
					padding: 13px 26px;
					border: 1px solid transparent;
					border-radius: 4px;
					font-size: 15px;
					font-weight: 700;
					line-height: 1;
					text-decoration: none;
					cursor: pointer;
				}
				.tw-btn--light { background: #fff; color: var(--tw-ink); }
				.tw-btn--light:hover, .tw-btn--light:focus { background: #ede8f7; color: var(--tw-ink); text-decoration: none; }
				.tw-btn--primary { background: var(--tw-accent); color: #fff; border-color: var(--tw-accent); }
				.tw-btn--primary:hover, .tw-btn--primary:focus { background: var(--tw-accent-hover); border-color: var(--tw-accent-hover); color: #fff; }
				.tw-btn:focus { outline: 2px solid var(--tw-accent-soft); outline-offset: 2px; box-shadow: none; }
				.tw-btn--light:focus { outline-color: #fff; }
				.tw-btn__arrow { font-family: var(--tw-mono); }

				.woo-wallet-go-pro-wrap a { color: var(--tw-accent); text-decoration: none; }
				.woo-wallet-go-pro-wrap a:hover,
				.woo-wallet-go-pro-wrap a:focus { color: var(--tw-accent-hover); text-decoration: underline; }

				/* ---------- Section framing ---------- */

				.tw-section { margin: 48px 0 0; }
				.tw-section__head {
					display: flex;
					align-items: baseline;
					justify-content: space-between;
					gap: 24px;
					margin: 0 0 16px;
					flex-wrap: wrap;
				}
				.tw-section__head--stacked { display: block; }
				.tw-section__title { margin: 0; padding: 0; font-size: 21px; font-weight: 700; letter-spacing: -.015em; }
				.tw-section__meta { font-size: 12px; color: var(--tw-muted); }
				.tw-section__intro { margin: 6px 0 0; font-size: 14px; color: #5c5666; max-width: 76ch; }

				/* ---------- Feature grid ---------- */

				/*
				 * Hairline grid. The rules are drawn as a 1px ring on each card
				 * rather than by showing a coloured container through the gaps:
				 * seven cards never fill the last row evenly, and a container
				 * background would leave a grey block where the eighth would be.
				 * The container's own border clips the outer rings, so edges stay
				 * 1px rather than doubling.
				 */
				.tw-features {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
					gap: 1px;
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-radius: 6px;
					overflow: hidden;
				}
				.tw-feature {
					display: flex;
					flex-direction: column;
					background: var(--tw-surface);
					box-shadow: 0 0 0 1px var(--tw-line);
					padding: 24px 24px 22px;
				}
				.tw-feature__index {
					font-family: var(--tw-mono);
					font-size: 11px;
					letter-spacing: .12em;
					color: var(--tw-accent-soft);
				}
				.tw-feature h3 { margin: 10px 0 8px; font-size: 16.5px; font-weight: 700; }
				.tw-feature__outcome { margin: 0 0 14px; font-size: 13.5px; line-height: 1.6; color: var(--tw-text-soft); }
				.tw-feature__list {
					display: grid;
					gap: 9px;
					margin: 0 0 18px;
					padding: 0;
					list-style: none;
					font-size: 13px;
					line-height: 1.55;
					color: #56505e;
				}
				.tw-feature__list li { margin: 0; padding-left: 14px; border-left: 2px solid #e6e3ea; }
				.tw-feature__link { margin-top: auto; font-size: 13px; font-weight: 700; }

				/* ---------- Free vs Pro ---------- */

				.tw-compare__card {
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-radius: 6px;
					overflow: hidden;
				}
				.tw-compare__scroll { overflow-x: auto; }
				.tw-compare { width: 100%; min-width: 520px; border-collapse: separate; border-spacing: 0; }
				.tw-compare th, .tw-compare td { padding: 11px 22px; text-align: left; border-bottom: 1px solid var(--tw-line-soft); }
				.tw-compare thead th {
					padding: 14px 22px;
					background: var(--tw-surface-alt);
					border-bottom: 1px solid #e6e3ea;
					font-size: 11px;
					font-weight: 400;
					letter-spacing: .12em;
					text-transform: uppercase;
					color: var(--tw-muted);
				}
				.tw-compare thead .tw-compare__cell--pro { color: var(--tw-accent); }
				.tw-compare__group th {
					padding: 13px 22px;
					background: var(--tw-group);
					border-top: 1px solid #e6e3ea;
					border-bottom: 1px solid #e6e3ea;
					font-size: 12px;
					font-weight: 700;
					letter-spacing: .1em;
					text-transform: uppercase;
					color: #3b3644;
				}
				.tw-compare tbody td { font-size: 13.5px; color: #2b2731; }
				.tw-compare tbody:last-child tr:last-child td { border-bottom: 0; }
				.tw-compare__cell { width: 120px; text-align: center; }
				.tw-tick { font-size: 15px; line-height: 1; }
				.tw-tick--yes { color: #1f8a45; }
				.tw-tick--no { color: #b9b4c0; }
				.tw-compare__foot {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 20px;
					flex-wrap: wrap;
					padding: 18px 22px;
					background: var(--tw-surface-alt);
					border-top: 1px solid var(--tw-line-soft);
					font-size: 13.5px;
					color: var(--tw-text-soft);
				}
				.tw-compare__foot a { font-size: 13px; font-weight: 700; }

				/* ---------- Store case ---------- */

				.tw-storecase__figures {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
					gap: 1px;
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-radius: 6px;
					overflow: hidden;
					margin: 0 0 14px;
				}
				.tw-storecase__figure { background: var(--tw-surface); box-shadow: 0 0 0 1px var(--tw-line); padding: 22px 24px; }
				.tw-storecase__value { display: block; font-size: 30px; font-weight: 500; line-height: 1.1; letter-spacing: -.02em; }
				.tw-storecase__label { display: block; margin-top: 8px; font-size: 13px; line-height: 1.5; color: var(--tw-text-soft); }
				.tw-storecase__copy { margin: 0; font-size: 13.5px; line-height: 1.6; color: var(--tw-text-soft); max-width: 80ch; }

				/* ---------- Use cases ---------- */

				.tw-usecases {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
					gap: 16px;
				}
				.tw-usecase {
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-top: 3px solid var(--tw-accent);
					border-radius: 5px;
					padding: 22px 22px 20px;
				}
				.tw-usecase h3 { margin: 0 0 10px; font-size: 15.5px; font-weight: 700; }
				.tw-usecase p { margin: 0 0 14px; font-size: 13px; line-height: 1.6; color: var(--tw-text-soft); }
				.tw-usecase__uses { margin: 0 !important; font-size: 11.5px; line-height: 1.6; color: var(--tw-muted); }
				.tw-usecase__uses-label { text-transform: uppercase; letter-spacing: .08em; }

				/* ---------- FAQ ---------- */

				.tw-faq-section { display: grid; grid-template-columns: 260px minmax(0, 1fr); gap: 40px; align-items: start; }
				.tw-faq__rail-copy { margin: 8px 0 14px; font-size: 13.5px; line-height: 1.6; color: #5c5666; }
				.tw-faq__cta { font-size: 13px; font-weight: 700; }
				.tw-faq {
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-radius: 6px;
					overflow: hidden;
				}
				.tw-faq__item { border-bottom: 1px solid var(--tw-line-soft); }
				.tw-faq__item:last-child { border-bottom: 0; }
				.tw-faq__item summary {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 16px;
					padding: 16px 22px;
					font-size: 14.5px;
					font-weight: 600;
					cursor: pointer;
					list-style: none;
				}
				.tw-faq__item summary::-webkit-details-marker { display: none; }
				.tw-faq__item summary:hover { background: var(--tw-surface-alt); }
				.tw-faq__item summary:focus-visible { outline: 2px solid var(--tw-accent); outline-offset: -2px; }
				.tw-faq__chev { flex: 0 0 auto; color: var(--tw-accent); transition: transform .18s ease; }
				.tw-faq__item[open] .tw-faq__chev { transform: rotate(45deg); }
				.tw-faq__item p { margin: 0; padding: 0 22px 18px; font-size: 13.5px; line-height: 1.65; color: var(--tw-text-soft); max-width: 70ch; }

				/* ---------- Bottom CTA ---------- */

				.tw-bottom-cta {
					display: flex;
					align-items: center;
					justify-content: space-between;
					gap: 32px;
					flex-wrap: wrap;
					margin: 44px 0 0;
					padding: 32px 40px;
					background: var(--tw-ink);
					color: #fff;
					border-radius: 6px;
				}
				.tw-bottom-cta__copy { max-width: 62ch; }
				.tw-bottom-cta h2 { margin: 0; padding: 0; font-size: 20px; font-weight: 700; letter-spacing: -.015em; color: #fff; }
				.tw-bottom-cta p { margin: 8px 0 0; font-size: 13.5px; line-height: 1.6; color: var(--tw-on-dark); }
				.tw-bottom-cta__act { display: flex; flex-direction: column; align-items: flex-start; gap: 8px; }
				.tw-bottom-cta__reassure { margin: 0 !important; font-size: 12px; color: var(--tw-on-dark-muted) !important; }

				/* ---------- License states ---------- */

				.tw-card {
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-radius: 6px;
					padding: 22px 24px;
					margin: 0 0 20px;
				}
				.tw-notice { display: flex; gap: 14px; align-items: flex-start; }
				.tw-notice .dashicons { font-size: 26px; width: 26px; height: 26px; flex: 0 0 26px; margin-top: 2px; }
				.tw-notice h2 { margin: 0 0 4px; padding: 0; font-size: 17px; }
				.tw-notice p { margin: 0 0 8px; color: var(--tw-text-soft); }
				.tw-notice--warning { border-left: 3px solid #dba617; }
				.tw-notice--warning .dashicons { color: #dba617; }
				.tw-notice--success { border-left: 3px solid #1f8a45; }
				.tw-notice--success .dashicons { color: #1f8a45; }

				.tw-inline-form { margin-top: 10px; }

				.tw-license h2 { margin: 0 0 14px; padding: 0; font-size: 18px; }
				.tw-license__form label { display: block; font-weight: 600; margin: 0 0 6px; }
				.tw-license__form input[type="text"] {
					width: 100%;
					max-width: 480px;
					padding: 10px 12px;
					border: 1px solid #c3c4c7;
					border-radius: 4px;
					font-family: var(--tw-mono);
					font-size: 14px;
				}
				.tw-license__form input[type="text"]:focus {
					outline: none;
					border-color: var(--tw-accent);
					box-shadow: 0 0 0 2px rgba(98,62,150,.25);
				}
				.tw-license__help { margin: 8px 0 18px; color: var(--tw-text-soft); font-size: 13px; }

				.tw-quicklinks {
					display: grid;
					grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
					gap: 16px;
					margin: 20px 0;
				}
				.woo-wallet-go-pro-wrap a.tw-quicklink {
					display: block;
					background: var(--tw-surface);
					border: 1px solid var(--tw-line);
					border-top: 3px solid var(--tw-accent);
					border-radius: 5px;
					padding: 22px;
					color: var(--tw-text);
				}
				.woo-wallet-go-pro-wrap a.tw-quicklink:hover,
				.woo-wallet-go-pro-wrap a.tw-quicklink:focus { color: var(--tw-text); text-decoration: none; background: var(--tw-surface-alt); }
				.tw-quicklink .dashicons { font-size: 24px; width: 24px; height: 24px; color: var(--tw-accent); margin-bottom: 10px; }
				.tw-quicklink h3 { margin: 0 0 6px; font-size: 15.5px; font-weight: 700; }
				.tw-quicklink p { margin: 0; font-size: 13px; line-height: 1.5; color: var(--tw-text-soft); }

				/* ---------- Responsive ---------- */

				@media (max-width: 1100px) {
					.tw-faq-section { grid-template-columns: 1fr; gap: 20px; }
				}

				@media (max-width: 960px) {
					.tw-hero { grid-template-columns: 1fr; }
					.tw-hero__panel { border-left: 0; border-top: 1px solid rgba(255,255,255,.14); padding: 32px 34px; }
					.tw-hero__lead { padding: 38px 34px 32px; }
				}

				@media (max-width: 782px) {
					.woo-wallet-go-pro-wrap { margin: 12px auto 32px; }
					.tw-hero h1 { font-size: 28px; }
					.tw-price__amount { font-size: 38px; }
					.tw-hero__cta .tw-btn { width: 100%; justify-content: center; }
					.tw-features, .tw-usecases, .tw-storecase__figures { grid-template-columns: 1fr; }
					.tw-compare th, .tw-compare td { padding: 11px 14px; }
					.tw-compare__cell { width: 72px; }
					.tw-bottom-cta { padding: 26px 24px; }
					.tw-bottom-cta__act, .tw-bottom-cta__act .tw-btn { width: 100%; }
					.tw-bottom-cta__act .tw-btn { justify-content: center; }
				}

				@media (max-width: 600px) {
					.tw-hero__lead { padding: 30px 22px 26px; }
					.tw-hero__panel { padding: 26px 22px; }
					.tw-hero h1 { font-size: 24px; }
					.tw-section__title { font-size: 19px; }
					.tw-feature { padding: 20px; }
					.tw-compare th, .tw-compare td { padding: 10px 12px; }
					.tw-compare__cell { width: 64px; }
				}

				@media (prefers-reduced-motion: reduce) {
					.tw-faq__chev { transition: none; }
				}
			</style>
			<?php
		}
	}

endif;

new Woo_Wallet_Go_Pro_Page();
