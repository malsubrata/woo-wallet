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
				'label'           => __( 'Liability composition by source', 'woo-wallet' ),
				'raw'             => $summary['composition'],
				'render_callback' => array( $this, 'render_composition_card' ),
			);

			// Locked Pro placeholder cards. Pro replaces each by registering a
			// card with the same `id` and `pro => false`.
			foreach ( $this->pro_placeholders() as $id => $label ) {
				$metrics[] = array(
					'id'    => $id,
					'label' => $label,
					'pro'   => true,
				);
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
			foreach ( $this->placeholder_tabs() as $id => $label ) {
				$tabs[ $id ] = array(
					'id'     => $id,
					'label'  => $label,
					'locked' => true,
				);
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
						if ( ! empty( $tab['locked'] ) ) {
							$classes .= ' is-locked';
							$label   .= ' <span class="dashicons dashicons-lock" aria-hidden="true"></span>';
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
			echo '</div>';
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
		 * A locked Pro placeholder card (upsell surface).
		 *
		 * @param array $metric Metric definition.
		 * @return void
		 */
		protected function render_pro_card( $metric ) {
			echo '<div class="twr-pro__card twr-reveal" data-metric="' . esc_attr( $metric['id'] ) . '">';
			echo '<span class="twr-pro__label">' . esc_html( $metric['label'] ) . '</span>';
			echo '<span class="twr-pro__badge"><span class="dashicons dashicons-lock"></span>' . esc_html__( 'TeraWallet Pro', 'woo-wallet' ) . '</span>';
			echo '</div>';
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
			foreach ( $rows as $row ) {
				if ( $row['amount'] > 0 ) {
					$positive += (float) $row['amount'];
				}
			}

			// Segmented bar (positive contributors only).
			echo '<div class="twr-bar" role="img" aria-label="' . esc_attr__( 'Liability composition by source', 'woo-wallet' ) . '">';
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

			// Legend (all rows, colour-matched to the bar).
			echo '<ul class="twr-legend">';
			$i = 0;
			foreach ( $rows as $row ) {
				$is_pos = $row['amount'] > 0;
				$color  = $is_pos ? $palette[ $i % count( $palette ) ] : '#cbd5e1';
				$share  = ( $is_pos && $positive > 0 ) ? round( ( (float) $row['amount'] / $positive ) * 100 ) : 0;
				echo '<li class="twr-legend__item" data-slug="' . esc_attr( $row['slug'] ) . '">';
				echo '<span class="twr-legend__dot" style="--c:' . esc_attr( $color ) . '"></span>';
				echo '<span class="twr-legend__label">' . esc_html( $row['label'] ) . '</span>';
				echo '<span class="twr-legend__amt' . ( $is_pos ? '' : ' is-negative' ) . '">' . esc_html( $this->data->format_amount( $row['amount'] ) ) . '</span>';
				if ( $is_pos ) {
					echo '<span class="twr-legend__share">' . esc_html( $share . '%' ) . '</span>';
				}
				echo '</li>';
				if ( $is_pos ) {
					++$i;
				}
			}
			echo '</ul>';
		}

		/**
		 * Render a locked Pro tab body (upsell).
		 *
		 * @param array $context Render context.
		 * @return void
		 */
		public function render_locked_tab( $context ) {
			echo '<div class="twr-upsell">';
			echo '<span class="dashicons dashicons-lock" aria-hidden="true"></span>';
			echo '<p>' . esc_html__( 'This report is available in TeraWallet Pro.', 'woo-wallet' ) . '</p>';
			echo '</div>';
		}
	}
}
