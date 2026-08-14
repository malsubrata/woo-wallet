<?php
/**
 * Contextual TeraWallet Pro feature gates.
 *
 * Pro features are simply absent from the free plugin, so a free user has no
 * way to discover that withdrawals, credit expiry or bulk import exist. These
 * gates make them visible but clearly locked, at the point in the UI where the
 * feature would live.
 *
 * Everything here is additive: no existing free functionality is removed,
 * disabled or blocked. Every surface is registered only when Pro is absent, so
 * a Pro site never renders any of it — and the real Pro screens take the same
 * slugs without collision.
 *
 * @package TeraWallet
 * @since 1.6.11
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Woo_Wallet_Pro_Gates' ) ) :

	/**
	 * Registers the free plugin's locked Pro feature gates.
	 */
	class Woo_Wallet_Pro_Gates {

		/**
		 * Withdrawals gate menu slug. Matches the slug Pro registers, so the
		 * locked page is replaced rather than duplicated once Pro is active.
		 */
		const WITHDRAWALS_SLUG = 'woo-wallet-withdrawals';

		/**
		 * Class constructor. Registers nothing at all when Pro is active.
		 */
		public function __construct() {
			if ( woo_wallet_is_pro_active() ) {
				return;
			}

			add_action( 'admin_menu', array( $this, 'register_withdrawals_gate' ), 60 );
			add_action( 'woo_wallet_before_balance_details_table', array( $this, 'render_bulk_import_gate' ) );
			add_filter( 'woo_wallet_settings_fields', array( $this, 'add_credit_expiry_gate_field' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		}

		/**
		 * Shared styling for every locked gate.
		 *
		 * Inlined against an already-enqueued core handle rather than shipped as
		 * a new stylesheet: it is a few rules used on three screens, and adding a
		 * build entry for it would cost more than it saves.
		 *
		 * @return void
		 */
		public function enqueue_styles() {
			if ( ! function_exists( 'get_current_screen' ) ) {
				return;
			}
			$screen = get_current_screen();
			if ( ! $screen ) {
				return;
			}

			$screens = array(
				woo_wallet_get_screen_id( 'woo-wallet-users' ),
				woo_wallet_get_screen_id( self::WITHDRAWALS_SLUG ),
			);
			if ( ! in_array( $screen->id, $screens, true ) ) {
				return;
			}

			wp_enqueue_style( 'dashicons' );
			wp_add_inline_style( 'dashicons', $this->gate_css() );
		}

		/**
		 * CSS shared by the gate surfaces.
		 *
		 * @return string
		 */
		private function gate_css() {
			return '
			.tw-gate-btn{opacity:.55;cursor:not-allowed;position:relative;}
			.tw-gate-btn .dashicons{font-size:15px;width:15px;height:15px;vertical-align:text-bottom;}
			.tw-gate-note{display:inline-block;margin-left:8px;font-size:12px;color:#646970;vertical-align:middle;}
			.tw-gate-note a{text-decoration:underline;}
			.tw-gate-panel{max-width:720px;margin:24px 0;padding:28px 32px;background:#fff;border:1px solid #dcdcde;border-radius:10px;}
			.tw-gate-panel__badge{display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:999px;background:#f0eefc;color:#5b21b6;font-size:12px;font-weight:600;}
			.tw-gate-panel__badge .dashicons{font-size:14px;width:14px;height:14px;}
			.tw-gate-panel h2{margin:14px 0 6px;font-size:20px;}
			.tw-gate-panel p{margin:0 0 14px;font-size:14px;color:#3c434a;max-width:60ch;}
			.tw-gate-panel ul{margin:0 0 20px 18px;list-style:disc;color:#3c434a;font-size:14px;}
			.tw-gate-panel li{margin-bottom:6px;}
			.tw-gate-panel__cta{display:inline-block;padding:9px 20px;border-radius:6px;background:#4f46e5;color:#fff;font-weight:600;text-decoration:none;}
			.tw-gate-panel__cta:hover,.tw-gate-panel__cta:focus{background:#4338ca;color:#fff;}
			';
		}

		// -----------------------------------------------------------------
		// A1. Withdrawals submenu gate.
		// -----------------------------------------------------------------

		/**
		 * Register the locked Withdrawals submenu.
		 *
		 * Priority 60 places it above the Go Pro item (65) and below the core
		 * TeraWallet pages (50).
		 *
		 * @return void
		 */
		public function register_withdrawals_gate() {
			add_submenu_page(
				'woo-wallet',
				__( 'Withdrawals', 'woo-wallet' ),
				__( 'Withdrawals', 'woo-wallet' ),
				get_wallet_user_capability(),
				self::WITHDRAWALS_SLUG,
				array( $this, 'render_withdrawals_gate' )
			);
		}

		/**
		 * Render the locked Withdrawals screen.
		 *
		 * @return void
		 */
		public function render_withdrawals_gate() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				wp_die( esc_html__( 'You do not have permission to access this page.', 'woo-wallet' ) );
			}

			$features = array(
				__( 'Customers request payouts from their wallet balance in My Account.', 'woo-wallet' ),
				__( 'Pay out via PayPal, Stripe, Razorpay, BACS or a manual method.', 'woo-wallet' ),
				__( 'Approve, reject and track every request from one admin queue.', 'woo-wallet' ),
				__( 'Set minimum amounts, withdrawal charges and per-user limits.', 'woo-wallet' ),
			);

			echo '<div class="wrap">';
			echo '<h1>' . esc_html__( 'Withdrawals', 'woo-wallet' ) . '</h1>';
			$this->render_gate_panel(
				__( 'Let customers cash out their wallet balance', 'woo-wallet' ),
				__( 'Wallet credit is a liability until it is spent or paid out. Withdrawals give customers a way to take their balance out, which is a requirement for marketplaces, refunds-to-wallet and cashback programs in many jurisdictions.', 'woo-wallet' ),
				$features,
				'withdrawals-gate'
			);
			echo '</div>';
		}

		// -----------------------------------------------------------------
		// A2. Credit Expiry settings row gate.
		// -----------------------------------------------------------------

		/**
		 * Add a locked "Credit Expiry" row to the Credit Options settings tab.
		 *
		 * Rendered through the existing `html` field type, so it needs no new
		 * React field component and no JS rebuild. It is display-only: it
		 * submits no value and the settings save path never sees it.
		 *
		 * @param array $fields Registered settings fields, keyed by section id.
		 * @return array
		 */
		public function add_credit_expiry_gate_field( $fields ) {
			if ( ! isset( $fields['_wallet_settings_credit'] ) || ! is_array( $fields['_wallet_settings_credit'] ) ) {
				return $fields;
			}

			$url = woo_wallet_pro_url( 'settings-expiry-row' );

			$html = sprintf(
				'<div class="tw-gate-row" style="padding:14px 16px;border:1px dashed #c3c4c7;border-radius:8px;background:#fbfbfc;">
					<strong style="display:block;margin-bottom:4px;color:#1d2327;">
						<span class="dashicons dashicons-lock" style="font-size:15px;width:15px;height:15px;vertical-align:text-bottom;color:#8c8f94;"></span>
						%1$s
					</strong>
					<span style="display:block;margin-bottom:8px;">%2$s</span>
					<a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a>
				</div>',
				esc_html__( 'Credit Expiry', 'woo-wallet' ),
				esc_html__( 'Automatically expire wallet balance that has gone unused for a set period, with reminder emails before it lapses. Reclaims outstanding liability and brings dormant customers back. Available in TeraWallet Pro.', 'woo-wallet' ),
				esc_url( $url ),
				esc_html__( 'Learn about Credit Expiry', 'woo-wallet' )
			);

			$fields['_wallet_settings_credit'][] = array(
				'name'              => 'pro_credit_expiry_gate',
				'label'             => '',
				'type'              => 'html',
				'html'              => $html,
				'group'             => 'wallet_credit_expiry',
				'group_title'       => __( 'Credit Expiry', 'woo-wallet' ),
				'group_description' => __( 'Reclaim wallet credit that is never spent', 'woo-wallet' ),
			);

			return $fields;
		}

		// -----------------------------------------------------------------
		// A3. Bulk Import button gate.
		// -----------------------------------------------------------------

		/**
		 * Render a disabled "Bulk Import" button above the Wallet Users table.
		 *
		 * Hooks the pre-existing `woo_wallet_before_balance_details_table`
		 * action, so the Wallet Users screen itself is untouched.
		 *
		 * @return void
		 */
		public function render_bulk_import_gate() {
			if ( ! current_user_can( get_wallet_user_capability() ) ) {
				return;
			}

			printf(
				'<p><button type="button" class="button tw-gate-btn" disabled aria-disabled="true">
					<span class="dashicons dashicons-lock" aria-hidden="true"></span> %1$s
				</button><span class="tw-gate-note">%2$s <a href="%3$s" target="_blank" rel="noopener noreferrer">%4$s</a></span></p>',
				esc_html__( 'Bulk Import', 'woo-wallet' ),
				esc_html__( 'Credit or debit hundreds of wallets at once from a CSV.', 'woo-wallet' ),
				esc_url( woo_wallet_pro_url( 'bulk-import-gate' ) ),
				esc_html__( 'Available in TeraWallet Pro', 'woo-wallet' )
			);
		}

		// -----------------------------------------------------------------
		// Shared renderer.
		// -----------------------------------------------------------------

		/**
		 * Render a full-page locked feature panel.
		 *
		 * @param string   $title    Panel heading.
		 * @param string   $intro    Lead paragraph.
		 * @param string[] $features Bullet list of capabilities.
		 * @param string   $content  utm_content placement id.
		 * @return void
		 */
		private function render_gate_panel( $title, $intro, $features, $content ) {
			echo '<div class="tw-gate-panel">';
			echo '<span class="tw-gate-panel__badge"><span class="dashicons dashicons-lock" aria-hidden="true"></span>' . esc_html__( 'TeraWallet Pro', 'woo-wallet' ) . '</span>';
			echo '<h2>' . esc_html( $title ) . '</h2>';
			echo '<p>' . esc_html( $intro ) . '</p>';

			if ( $features ) {
				echo '<ul>';
				foreach ( $features as $feature ) {
					echo '<li>' . esc_html( $feature ) . '</li>';
				}
				echo '</ul>';
			}

			printf(
				'<a class="tw-gate-panel__cta" href="%1$s" target="_blank" rel="noopener noreferrer">%2$s</a>',
				esc_url( woo_wallet_pro_url( $content ) ),
				esc_html__( 'See TeraWallet Pro', 'woo-wallet' )
			);
			echo '</div>';
		}
	}

endif;

new Woo_Wallet_Pro_Gates();
