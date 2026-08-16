<?php
/**
 * Wallet liability Reports — admin dashboard page.
 *
 * The default TeraWallet admin landing screen. Server-rendered (plain PHP, no
 * React) store-wide liability dashboard, enhanced by a small vanilla JS bundle
 * (`build/admin/reports.js`) for count-up, the interactive composition bar and
 * a live Refresh. The page is fully usable with JS disabled.
 *
 * It is assembled from two registries so the Pro plugin can inject cards, whole
 * tabs and data with zero build step:
 *
 *   - metric cards  → filter `woo_wallet_reports_metrics`
 *   - tabs          → filter `woo_wallet_reports_tabs`
 *                     + action `woo_wallet_reports_render_tab_{id}`
 *
 * Free registers its own cards/tabs through these same hooks, so the free and
 * Pro extension paths are identical and proven. Read-only — no ledger writes.
 *
 * @package StandaleneTech
 * @since   1.6.6
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Woo_Wallet_Reports' ) ) {

	/**
	 * Reports page controller + default card/tab registry.
	 */
	class Woo_Wallet_Reports {

		/**
		 * Data service.
		 *
		 * @var Woo_Wallet_Reports_Data
		 */
		protected $data;

		/**
		 * Constructor. Registers free's own cards and tabs through the public
		 * hooks (same mechanism Pro uses).
		 */
		public function __construct() {
			require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';
			$this->data = new Woo_Wallet_Reports_Data();

			add_filter( 'woo_wallet_reports_metrics', array( $this, 'register_default_metrics' ), 10, 2 );
			add_filter( 'woo_wallet_reports_tabs', array( $this, 'register_default_tabs' ), 10, 2 );
			add_action( 'woo_wallet_reports_render_tab_summary', array( $this, 'render_summary_tab' ) );
			// Locked Pro placeholders share one upsell renderer. Pro modules clear
			// this renderer (remove_all_actions) and install their own when active.
			foreach ( array_keys( $this->placeholder_tabs() ) as $tab_id ) {
				add_action( "woo_wallet_reports_render_tab_{$tab_id}", array( $this, 'render_locked_tab' ) );
			}
		}

		/**
		 * Capability required to view reports (and the REST endpoint).
		 *
		 * @return string
		 */
		public static function capability() {
			return apply_filters( 'woo_wallet_reports_capability', 'manage_woocommerce' );
		}

		/**
		 * Colour palette for composition segments / legend swatches.
		 *
		 * @return string[]
		 */
		protected function palette() {
			return array( '#5b5bd6', '#8487e0', '#0f9488', '#2563c9', '#15976a', '#8b5cf6', '#ec4899', '#14b8a6' );
		}

		/**
		 * Current `$args` for the page (date range / segment), filterable so Pro
		 * can add filtering.
		 *
		 * @return array
		 */
		protected function query_args() {
			return apply_filters( 'woo_wallet_reports_query_args', array() );
		}

		/**
		 * Free's own metric cards, registered through the public filter.
		 *
		 * @param array $metrics Existing cards.
		 * @param array $args    Query args.
		 * @return array
		 */
		public function register_default_metrics( $metrics, $args ) {
			$summary = $this->data->get_summary( $args );

			$metrics[] = array(
				'id'       => 'total_liability',
				'label'    => __( 'Total outstanding liability', 'woo-wallet' ),
				'value'    => $this->data->format_amount( $summary['total_liability'] ),
				'raw'      => $summary['total_liability'],
				'format'   => 'currency',
				'headline' => true,
				'note'     => __( 'What your store currently owes customers across all wallets.', 'woo-wallet' ),
			);
			$metrics[] = array(
				'id'     => 'positive_wallets',
				'label'  => __( 'Wallets with a positive balance', 'woo-wallet' ),
				'value'  => number_format_i18n( $summary['positive_wallets'] ),
				'raw'    => $summary['positive_wallets'],
				'format' => 'int',
			);
			$metrics[] = array(
				'id'      => 'lifetime_credited',
				'label'   => __( 'Lifetime credited', 'woo-wallet' ),
				'value'   => $this->data->format_amount( $summary['lifetime_credited'] ),
				'raw'     => $summary['lifetime_credited'],
				'format'  => 'currency',
				'variant' => 'credit',
			);
			$metrics[] = array(
				'id'      => 'lifetime_debited',
				'label'   => __( 'Lifetime debited', 'woo-wallet' ),
				'value'   => $this->data->format_amount( $summary['lifetime_debited'] ),
				'raw'     => $summary['lifetime_debited'],
				'format'  => 'currency',
				'variant' => 'debit',
			);
			$metrics[] = array(
				'id'              => 'composition',
				'label'           => __( 'Where wallet credit came from', 'woo-wallet' ),
				'raw'             => $summary['composition'],
				'render_callback' => array( $this, 'render_composition_card' ),
			);

			// Locked Pro placeholder cards. Skipped entirely when Pro is active —
			// Pro registers the real cards under the same ids.
			if ( ! woo_wallet_is_pro_active() ) {
				foreach ( $this->pro_placeholders() as $id => $label ) {
					$metrics[] = array(
						'id'    => $id,
						'label' => $label,
						'pro'   => true,
					);
				}
			}

			return $metrics;
		}

		/**
		 * Pro-only report slots: id => label.
		 *
		 * @return array<string,string>
		 */
		protected function pro_placeholders() {
			return array(
				'breakage' => __( 'Breakage', 'woo-wallet' ),
				'aging'    => __( 'Aging', 'woo-wallet' ),
				'trend'    => __( 'Expiry trend', 'woo-wallet' ),
			);
		}

		/**
		 * Pro-only report slots advertised as locked *tabs only* (no metric card):
		 * id => label. Pro registers these as full tabs when the owning module is
		 * enabled, or downgrades them to a nudge when installed-but-disabled.
		 *
		 * @return array<string,string>
		 */
		protected function pro_tab_placeholders() {
			return array(
				'withdrawal' => __( 'Withdrawals', 'woo-wallet' ),
				'coupons'    => __( 'Coupons', 'woo-wallet' ),
			);
		}

		/**
		 * Every locked Pro tab slot (card-backed + tab-only), id => label.
		 *
		 * @return array<string,string>
		 */
		protected function placeholder_tabs() {
			return array_merge( $this->pro_placeholders(), $this->pro_tab_placeholders() );
		}

		/**
		 * Free's default tabs. Summary is real; the rest are locked upsell slots
		 * Pro replaces by re-registering the same `id` without `locked`.
		 *
		 * @param array  $tabs    Existing tabs.
		 * @param string $current Current tab id.
		 * @return array
		 */
		public function register_default_tabs( $tabs, $current ) {
			$tabs['summary'] = array(
				'id'    => 'summary',
				'label' => __( 'Summary', 'woo-wallet' ),
			);
			if ( ! woo_wallet_is_pro_active() ) {
				foreach ( $this->placeholder_tabs() as $id => $label ) {
					$tabs[ $id ] = array(
						'id'     => $id,
						'label'  => $label,
						'locked' => true,
					);
				}
			}
			return $tabs;
		}

		/**
		 * Resolve the current tab id from the request.
		 *
		 * @param array $tabs Registered tabs.
		 * @return string
		 */
		protected function current_tab( $tabs ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$requested = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'summary';
			return isset( $tabs[ $requested ] ) ? $requested : 'summary';
		}

		/**
		 * Render the whole page. Invoked from `Woo_Wallet_Admin::reports_page()`.
		 *
		 * @return void
		 */
		public function render() {
			if ( ! current_user_can( self::capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to view wallet reports.', 'woo-wallet' ) );
			}

			$args     = $this->query_args();
			$probe    = apply_filters( 'woo_wallet_reports_tabs', array(), 'summary' );
			$current  = $this->current_tab( $probe );
			$tabs     = apply_filters( 'woo_wallet_reports_tabs', array(), $current );
			$context  = array(
				'args'        => $args,
				'current_tab' => $current,
				'data'        => $this->data,
			);
			$base_url = admin_url( 'admin.php?page=woo-wallet' );
			?>
			<div class="wrap woo-wallet-reports" id="twr-app">
				<h2></h2>
				<?php
				/**
				 * Fires at the top of every TeraWallet admin page, inside `.wrap`.
				 *
				 * TeraWallet's own page-content hook — deliberately not
				 * `admin_notices`, so anything attached here (the Pro banner) stays
				 * on this plugin's screens and never reaches the wider dashboard.
				 *
				 * @since 1.6.12
				 */
				do_action( 'woo_wallet_admin_page_header' );
				?>
				<header class="twr-topbar">
					<div class="twr-brand">
						<div class="twr-brand__text">
							<h2 class="twr-title"><?php esc_html_e( 'Wallet Dashboard', 'woo-wallet' ); ?></h2>
							<p class="twr-subtitle"><?php esc_html_e( 'Store-wide wallet liability at a glance.', 'woo-wallet' ); ?></p>
						</div>
					</div>
					<div class="twr-actions">
						<span class="twr-updated" data-updated>
							<span class="twr-updated__label"><?php esc_html_e( 'Updated', 'woo-wallet' ); ?></span>
							<time><?php echo esc_html( $this->data->get_summary( $args )['generated_at'] ); ?></time>
						</span>
						<button type="button" class="button twr-refresh">
							<?php esc_html_e( 'Refresh', 'woo-wallet' ); ?>
						</button>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'terawallet-exporter' ), admin_url( 'admin.php' ) ) ); ?>" class="button twr-export">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export', 'woo-wallet' ); ?>
						</a>
						<?php
						/**
						 * Fires in the reports topbar action area, right before Refresh.
						 *
						 * Pro hooks the Import button here. Echo button markup
						 * (use the `button` class to match the topbar styling).
						 *
						 * @since 1.6.6
						 *
						 * @param array $context Render context (args, current_tab, data).
						 */
						do_action( 'woo_wallet_reports_actions', $context );
						?>
					</div>
				</header>

				<?php do_action( 'woo_wallet_reports_page_top' ); ?>

				<nav class="twr-tabs">
					<?php
					foreach ( $tabs as $tab ) {
						$classes = 'twr-tab' . ( $tab['id'] === $current ? ' is-active' : '' );
						$label   = esc_html( $tab['label'] );

						// A locked tab opens its explainer modal instead of
						// navigating to an empty report.
						if ( ! empty( $tab['locked'] ) ) {
							printf(
								'<button type="button" class="%1$s is-locked" data-twr-pro-modal="%2$s" aria-haspopup="dialog">%3$s <span class="dashicons dashicons-lock" aria-hidden="true"></span></button>',
								esc_attr( $classes ),
								esc_attr( $tab['id'] ),
								wp_kses_post( $label )
							);
							continue;
						}

						printf(
							'<a href="%s" class="%s">%s</a>',
							esc_url( add_query_arg( 'tab', $tab['id'], $base_url ) ),
							esc_attr( $classes ),
							wp_kses_post( $label )
						);
					}
					?>
				</nav>

				<div class="twr-body">
					<?php do_action( "woo_wallet_reports_render_tab_{$current}", $context ); ?>
				</div>

				<p class="twr-disclaimer">
					<?php esc_html_e( 'Indicative reporting. Not a substitute for your accounting records.', 'woo-wallet' ); ?>
				</p>

				<?php do_action( 'woo_wallet_reports_page_bottom' ); ?>
				<?php $this->render_pro_modals(); ?>
			</div>
			<?php
		}

		/**
		 * Render the Summary tab body. Buckets the filtered metric cards into the
		 * dashboard layout (headline / composition / stat grid / locked Pro),
		 * while keeping every card filter-driven so Pro injections appear.
		 *
		 * @param array $context Render context.
		 * @return void
		 */
		public function render_summary_tab( $context ) {
			$args    = isset( $context['args'] ) ? $context['args'] : array();
			$metrics = apply_filters( 'woo_wallet_reports_metrics', array(), $args );

			$headline    = null;
			$composition = null;
			$cards       = array();
			$pro         = array();
			foreach ( $metrics as $metric ) {
				if ( ! empty( $metric['headline'] ) && null === $headline ) {
					$headline = $metric;
				} elseif ( 'composition' === $metric['id'] || isset( $metric['render_callback'] ) ) {
					$composition = $metric;
				} elseif ( ! empty( $metric['pro'] ) ) {
					$pro[] = $metric;
				} else {
					$cards[] = $metric;
				}
			}

			do_action( 'woo_wallet_reports_before_summary', $context );

			echo '<div class="twr-summary">';

			if ( $headline ) {
				$this->render_headline( $headline );
			}

			if ( $composition ) {
				echo '<section class="twr-composition twr-reveal">';
				echo '<div class="twr-composition__head">';
				echo '<h2 class="twr-composition__title">' . esc_html( $composition['label'] ) . '</h2>';
				echo '</div>';
				if ( isset( $composition['render_callback'] ) && is_callable( $composition['render_callback'] ) ) {
					call_user_func( $composition['render_callback'], $composition, $context );
				}
				echo '</section>';
			}

			if ( $cards ) {
				echo '<div class="twr-cards">';
				foreach ( $cards as $metric ) {
					$this->render_card( $metric, $context );
				}
				echo '</div>';
			}

			if ( $pro ) {
				echo '<div class="twr-pro">';
				foreach ( $pro as $metric ) {
					$this->render_pro_card( $metric );
				}
				echo '</div>';
			}

			echo '</div>';

			do_action( 'woo_wallet_reports_after_summary', $context );
		}

		/**
		 * Headline (total liability) card.
		 *
		 * @param array $metric Metric definition.
		 * @return void
		 */
		protected function render_headline( $metric ) {
			$raw_attr = isset( $metric['raw'] ) ? ' data-raw="' . esc_attr( $metric['raw'] ) . '" data-format="' . esc_attr( isset( $metric['format'] ) ? $metric['format'] : 'currency' ) . '"' : '';
			echo '<div class="twr-headline twr-reveal" data-metric="' . esc_attr( $metric['id'] ) . '">';
			echo '<span class="twr-headline__label">' . esc_html( $metric['label'] ) . '</span>';
			// data-field lets the live Refresh target this figure; value is the
			// no-JS fallback and the count-up start text.
			echo '<span class="twr-headline__value" data-field="' . esc_attr( $metric['id'] ) . '"' . $raw_attr . '>' . esc_html( $metric['value'] ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $raw_attr is pre-escaped above.
			if ( ! empty( $metric['note'] ) ) {
				echo '<span class="twr-headline__note">' . esc_html( $metric['note'] ) . '</span>';
			}
			$this->render_liability_nudge( $metric );
			echo '</div>';
		}

		/**
		 * Make the Pro case from the store's own liability figure.
		 *
		 * Only renders above a threshold: on a store carrying a trivial balance
		 * the pitch is noise, and quoting a two-figure sum makes the case worse
		 * rather than better.
		 *
		 * @since 1.6.11
		 * @param array $metric Headline metric (total liability).
		 * @return void
		 */
		protected function render_liability_nudge( $metric ) {
			if ( woo_wallet_is_pro_active() ) {
				return;
			}
			if ( ! isset( $metric['raw'] ) ) {
				return;
			}

			$liability = (float) $metric['raw'];

			/**
			 * Minimum outstanding liability, in the store's base currency, before
			 * the Pro nudge appears beneath the headline figure.
			 *
			 * ponytail: a flat default cannot be right for every currency — 1000
			 * means something different in USD and in IDR. Filter it per store
			 * rather than trying to infer a purchasing-power conversion here.
			 *
			 * @since 1.6.11
			 * @param float $threshold Default 1000 in base currency.
			 */
			$threshold = (float) apply_filters( 'woo_wallet_pro_liability_nudge_threshold', 1000.0 );

			if ( $liability < $threshold ) {
				return;
			}

			echo '<span class="twr-headline__nudge">';
			printf(
				/* translators: %s: formatted outstanding wallet liability, e.g. $4,820.00 */
				esc_html__( "You're carrying %s in outstanding wallet credit. Pro's credit expiry reclaims unspent balance automatically, and breakage reporting shows you how much of it is never coming back.", 'woo-wallet' ),
				'<strong>' . esc_html( $this->data->format_amount( $liability ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline.
			);
			printf(
				' <a href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( woo_wallet_pro_url( 'dashboard-liability' ) ),
				esc_html__( 'See how', 'woo-wallet' )
			);
			echo '</span>';
		}

		/**
		 * A secondary stat card.
		 *
		 * @param array $metric  Metric definition.
		 * @param array $context Render context.
		 * @return void
		 */
		protected function render_card( $metric, $context ) {
			$classes = 'twr-card twr-reveal';
			if ( ! empty( $metric['variant'] ) ) {
				$classes .= ' twr-card--' . sanitize_html_class( $metric['variant'] );
			}
			echo '<div class="' . esc_attr( $classes ) . '" data-metric="' . esc_attr( $metric['id'] ) . '">';
			echo '<span class="twr-card__label">' . esc_html( $metric['label'] ) . '</span>';

			if ( isset( $metric['render_callback'] ) && is_callable( $metric['render_callback'] ) ) {
				call_user_func( $metric['render_callback'], $metric, $context );
			} else {
				$raw_attr = isset( $metric['raw'] ) ? ' data-raw="' . esc_attr( $metric['raw'] ) . '" data-format="' . esc_attr( isset( $metric['format'] ) ? $metric['format'] : 'currency' ) . '"' : '';
				echo '<span class="twr-card__value" data-field="' . esc_attr( $metric['id'] ) . '"' . $raw_attr . '>' . esc_html( isset( $metric['value'] ) ? $metric['value'] : '' ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $raw_attr is pre-escaped above.
			}
			echo '</div>';
		}

		/**
		 * Copy and sample shape for every locked Pro slot.
		 *
		 * `sample` is an illustrative sparkline, deliberately not derived from
		 * the store's real data — the card is rendered blurred and labelled as a
		 * sample so it cannot be mistaken for a real figure.
		 *
		 * @since 1.6.11
		 * @return array<string,array>
		 */
		protected function pro_slot_copy() {
			return array(
				'breakage'   => array(
					'benefit'  => __( 'See how much wallet credit is never spent — and reclaim it.', 'woo-wallet' ),
					'body'     => __( 'Breakage tracks the share of issued wallet credit that customers never redeem. It tells you what your cashback and refund-to-wallet programs actually cost, rather than what they nominally cost.', 'woo-wallet' ),
					'bullets'  => array(
						__( 'Breakage rate by month, cohort and credit source.', 'woo-wallet' ),
						__( 'Separates genuinely dormant credit from credit still in play.', 'woo-wallet' ),
						__( 'Exports alongside your existing wallet CSV.', 'woo-wallet' ),
					),
					'sample'   => array( 18, 24, 21, 32, 38, 35, 44, 52 ),
					'sample_v' => '12.4%',
				),
				'aging'      => array(
					'benefit'  => __( 'Know how long credit has been sitting in customer wallets.', 'woo-wallet' ),
					'body'     => __( 'Aging buckets every open wallet balance by how long it has gone untouched, so you can see the difference between credit issued last week and credit that has been dormant for a year.', 'woo-wallet' ),
					'bullets'  => array(
						__( '0–30, 31–90, 91–180 and 180+ day buckets.', 'woo-wallet' ),
						__( 'Drill into the customers holding the oldest balances.', 'woo-wallet' ),
						__( 'Pairs with Credit Expiry to target reminders.', 'woo-wallet' ),
					),
					'sample'   => array( 60, 44, 38, 27, 22, 16, 13, 9 ),
					'sample_v' => '94 days',
				),
				'trend'      => array(
					'benefit'  => __( 'Project how much liability expires, and when.', 'woo-wallet' ),
					'body'     => __( 'The expiry trend projects forward from your current balances and expiry rules, so you can see what is due to lapse next month rather than discovering it after the fact.', 'woo-wallet' ),
					'bullets'  => array(
						__( 'Forward projection of expiring balance by month.', 'woo-wallet' ),
						__( 'Compare projected against actually expired.', 'woo-wallet' ),
						__( 'Feeds the reminder emails sent before credit lapses.', 'woo-wallet' ),
					),
					'sample'   => array( 12, 19, 16, 28, 24, 37, 33, 46 ),
					'sample_v' => '₹ — /mo',
				),
				'withdrawal' => array(
					'benefit'  => __( 'Let customers cash out, and track every payout.', 'woo-wallet' ),
					'body'     => __( 'Withdrawals give customers a way to take their wallet balance out via PayPal, Stripe, Razorpay, BACS or a manual method, with an approval queue and a full audit trail on the admin side.', 'woo-wallet' ),
					'bullets'  => array(
						__( 'Approve, reject and track requests from one queue.', 'woo-wallet' ),
						__( 'Minimum amounts, withdrawal charges and per-user limits.', 'woo-wallet' ),
						__( 'Required for most marketplace and cashback setups.', 'woo-wallet' ),
					),
					'sample'   => array( 22, 28, 25, 34, 30, 41, 46, 43 ),
					'sample_v' => '—',
				),
				'coupons'    => array(
					'benefit'  => __( 'Issue redeemable top-up codes for campaigns.', 'woo-wallet' ),
					'body'     => __( 'Wallet coupons are redeemable codes that load credit straight into a customer wallet — useful for promotions, gift campaigns, service recovery and offline redemption.', 'woo-wallet' ),
					'bullets'  => array(
						__( 'Bulk-generate codes with usage and expiry limits.', 'woo-wallet' ),
						__( 'Track redemption rate per campaign.', 'woo-wallet' ),
						__( 'Credit lands in the wallet through the normal ledger.', 'woo-wallet' ),
					),
					'sample'   => array( 8, 15, 26, 22, 34, 31, 42, 49 ),
					'sample_v' => '—',
				),
			);
		}

		/**
		 * A locked Pro placeholder card (upsell surface).
		 *
		 * Renders a blurred sample of the report shape plus a benefit line, and
		 * is itself a button that opens the slot's explainer modal. Previously
		 * this was an inert hatched box with a lock chip, which advertised that
		 * something was missing without ever explaining what.
		 *
		 * @param array $metric Metric definition.
		 * @return void
		 */
		protected function render_pro_card( $metric ) {
			$copy = $this->pro_slot_copy();
			$slot = isset( $copy[ $metric['id'] ] ) ? $copy[ $metric['id'] ] : array();

			printf(
				'<button type="button" class="twr-pro__card twr-reveal" data-metric="%1$s" data-twr-pro-modal="%1$s" aria-haspopup="dialog">',
				esc_attr( $metric['id'] )
			);
			echo '<span class="twr-pro__label">' . esc_html( $metric['label'] ) . '</span>';

			if ( ! empty( $slot['sample'] ) ) {
				echo '<span class="twr-pro__sample" aria-hidden="true">';
				echo '<span class="twr-pro__sample-value">' . esc_html( isset( $slot['sample_v'] ) ? $slot['sample_v'] : '' ) . '</span>';
				echo $this->sparkline( $slot['sample'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- self-contained SVG built from a hard-coded numeric series.
				echo '</span>';
			}

			if ( ! empty( $slot['benefit'] ) ) {
				echo '<span class="twr-pro__benefit">' . esc_html( $slot['benefit'] ) . '</span>';
			}

			echo '<span class="twr-pro__badge"><span class="dashicons dashicons-lock"></span>' . esc_html__( 'TeraWallet Pro', 'woo-wallet' ) . '</span>';
			echo '</button>';
		}

		/**
		 * Build an inline sparkline SVG from a numeric series.
		 *
		 * Inline rather than charted by a library: it is decorative sample data
		 * and the dashboard ships no charting dependency.
		 *
		 * @since 1.6.11
		 * @param int[] $series Values.
		 * @return string SVG markup.
		 */
		protected function sparkline( $series ) {
			$series = array_values( array_map( 'floatval', (array) $series ) );
			$count  = count( $series );
			if ( $count < 2 ) {
				return '';
			}

			$max    = max( $series );
			$min    = min( $series );
			$range  = ( $max - $min ) > 0 ? ( $max - $min ) : 1;
			$points = array();
			foreach ( $series as $i => $value ) {
				$x        = ( $i / ( $count - 1 ) ) * 100;
				$y        = 30 - ( ( $value - $min ) / $range ) * 26;
				$points[] = round( $x, 2 ) . ',' . round( $y, 2 );
			}
			$path = implode( ' ', $points );

			return sprintf(
				'<svg class="twr-spark" viewBox="0 0 100 32" preserveAspectRatio="none" focusable="false" aria-hidden="true">
					<polyline points="%1$s" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
					<polygon points="%1$s 100,32 0,32" fill="currentColor" opacity="0.12" />
				</svg>',
				esc_attr( $path )
			);
		}

		/**
		 * Render one explainer dialog per locked Pro slot.
		 *
		 * Native <dialog> elements, server-rendered and escaped; the JS only
		 * calls showModal()/close(). Nothing renders when Pro is active.
		 *
		 * @since 1.6.11
		 * @return void
		 */
		public function render_pro_modals() {
			if ( woo_wallet_is_pro_active() ) {
				return;
			}

			$labels = $this->placeholder_tabs();
			foreach ( $this->pro_slot_copy() as $id => $slot ) {
				if ( ! isset( $labels[ $id ] ) ) {
					continue;
				}
				printf( '<dialog class="twr-modal" id="twr-pro-modal-%s">', esc_attr( $id ) );
				echo '<div class="twr-modal__inner">';
				printf(
					'<button type="button" class="twr-modal__close" data-twr-modal-close aria-label="%s">&times;</button>',
					esc_attr__( 'Close', 'woo-wallet' )
				);
				echo '<span class="twr-modal__badge"><span class="dashicons dashicons-lock" aria-hidden="true"></span>' . esc_html__( 'TeraWallet Pro', 'woo-wallet' ) . '</span>';
				echo '<h2 class="twr-modal__title">' . esc_html( $labels[ $id ] ) . '</h2>';
				echo '<p class="twr-modal__body">' . esc_html( $slot['body'] ) . '</p>';

				if ( ! empty( $slot['bullets'] ) ) {
					echo '<ul class="twr-modal__list">';
					foreach ( $slot['bullets'] as $bullet ) {
						echo '<li>' . esc_html( $bullet ) . '</li>';
					}
					echo '</ul>';
				}

				printf(
					'<a class="twr-modal__cta" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
					esc_url( woo_wallet_pro_url( 'dashboard-' . str_replace( '_', '-', $id ) ) ),
					esc_html__( 'See TeraWallet Pro', 'woo-wallet' )
				);
				echo '</div></dialog>';
			}
		}

		/**
		 * Render callback for the composition card: the interactive liability
		 * bar + legend. Positive net categories compose the bar; negative ones
		 * (net debits) are listed in the legend only.
		 *
		 * @param array $metric  Metric definition (raw = composition rows).
		 * @param array $context Render context.
		 * @return void
		 */
		public function render_composition_card( $metric, $context ) {
			$rows = isset( $metric['raw'] ) && is_array( $metric['raw'] ) ? $metric['raw'] : array();
			if ( empty( $rows ) ) {
				echo '<p class="description">' . esc_html__( 'No wallet activity yet.', 'woo-wallet' ) . '</p>';
				return;
			}

			$palette  = $this->palette();
			$positive = 0.0;
			$negative = 0.0;
			foreach ( $rows as $row ) {
				if ( $row['amount'] > 0 ) {
					$positive += (float) $row['amount'];
				} else {
					$negative += (float) $row['amount'];
				}
			}

			// State the denominator. The shares below are of the net-positive
			// categories only — rows come from liability_by_category(), which
			// nets credit against debit per category, so a category that nets
			// negative (cashback after clawback) drops out of $positive and is
			// listed under "Reduced by" instead. Calling this "credited to
			// wallets" would overstate it; that figure is lifetime_credited.
			echo '<p class="twr-composition__basis">';
			printf(
				/* translators: %s: net wallet credit by source, formatted */
				esc_html__( 'Share of %s — net credit by source. Debits are listed separately below.', 'woo-wallet' ),
				'<strong class="twr-composition__basis-value">' . esc_html( $this->data->format_amount( $positive ) ) . '</strong>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped inline.
			);
			echo '</p>';

			// Segmented bar (credit sources only).
			echo '<div class="twr-bar" role="img" aria-label="' . esc_attr__( 'Wallet credit by source', 'woo-wallet' ) . '">';
			$i = 0;
			foreach ( $rows as $row ) {
				if ( $row['amount'] <= 0 || $positive <= 0 ) {
					continue;
				}
				$share = ( (float) $row['amount'] / $positive ) * 100;
				$color = $palette[ $i % count( $palette ) ];
				printf(
					'<div class="twr-bar__seg" data-slug="%1$s" style="--w:%2$s%%;--c:%3$s" title="%4$s"></div>',
					esc_attr( $row['slug'] ),
					esc_attr( number_format( $share, 2, '.', '' ) ),
					esc_attr( $color ),
					esc_attr( sprintf( '%1$s — %2$s (%3$s%%)', $row['label'], $this->data->format_amount( $row['amount'] ), round( $share ) ) )
				);
				++$i;
			}
			echo '</div>';

			// Legend — credit sources only, colour-matched to the bar.
			echo '<ul class="twr-legend">';
			$i = 0;
			foreach ( $rows as $row ) {
				if ( $row['amount'] <= 0 ) {
					continue;
				}
				$color = $palette[ $i % count( $palette ) ];
				$share = $positive > 0 ? round( ( (float) $row['amount'] / $positive ) * 100 ) : 0;
				echo '<li class="twr-legend__item" data-slug="' . esc_attr( $row['slug'] ) . '">';
				echo '<span class="twr-legend__dot" style="--c:' . esc_attr( $color ) . '"></span>';
				echo '<span class="twr-legend__label">' . esc_html( $row['label'] ) . '</span>';
				echo '<span class="twr-legend__amt">' . esc_html( $this->data->format_amount( $row['amount'] ) ) . '</span>';
				echo '<span class="twr-legend__share">' . esc_html( $share . '%' ) . '</span>';
				echo '</li>';
				++$i;
			}
			echo '</ul>';

			// Debit categories are not components of the liability — they are
			// what has already been drawn down against it. Listing them in the
			// same legend, negative and share-less, read as broken data.
			$debits = array();
			foreach ( $rows as $row ) {
				if ( $row['amount'] < 0 ) {
					$debits[] = $row;
				}
			}

			if ( $debits ) {
				echo '<h3 class="twr-composition__subhead">' . esc_html__( 'Reduced by', 'woo-wallet' ) . '</h3>';
				echo '<ul class="twr-legend twr-legend--negative">';
				foreach ( $debits as $row ) {
					echo '<li class="twr-legend__item" data-slug="' . esc_attr( $row['slug'] ) . '">';
					echo '<span class="twr-legend__dot" style="--c:#cbd5e1"></span>';
					echo '<span class="twr-legend__label">' . esc_html( $row['label'] ) . '</span>';
					echo '<span class="twr-legend__amt is-negative">' . esc_html( $this->data->format_amount( $row['amount'] ) ) . '</span>';
					echo '</li>';
				}
				echo '</ul>';
			}

			// Reconcile to the headline figure, so the card visibly ties out to
			// the number above it.
			echo '<p class="twr-composition__net">';
			echo '<span>' . esc_html__( 'Net outstanding', 'woo-wallet' ) . '</span>';
			echo '<strong class="twr-composition__net-value">' . esc_html( $this->data->format_amount( $positive + $negative ) ) . '</strong>';
			echo '</p>';
		}

		/**
		 * Render a locked Pro tab body (upsell).
		 *
		 * @param array $context Render context.
		 * @return void
		 */
		public function render_locked_tab( $context ) {
			$current = isset( $context['current_tab'] ) ? $context['current_tab'] : '';
			$copy    = $this->pro_slot_copy();
			$labels  = $this->placeholder_tabs();

			echo '<div class="twr-upsell">';
			echo '<span class="dashicons dashicons-lock" aria-hidden="true"></span>';

			if ( isset( $copy[ $current ], $labels[ $current ] ) ) {
				echo '<h2>' . esc_html( $labels[ $current ] ) . '</h2>';
				echo '<p>' . esc_html( $copy[ $current ]['benefit'] ) . '</p>';
				printf(
					'<button type="button" class="button button-primary" data-twr-pro-modal="%1$s" aria-haspopup="dialog">%2$s</button>',
					esc_attr( $current ),
					esc_html__( 'What this report shows', 'woo-wallet' )
				);
			} else {
				echo '<p>' . esc_html__( 'This report is available in TeraWallet Pro.', 'woo-wallet' ) . '</p>';
			}

			echo '</div>';
		}
	}
}
