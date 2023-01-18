<?php
/**
 * Woo Wallet settings
 *
 * @author Subrata Mal
 */

if ( ! class_exists( 'Woo_Wallet_Extensions_Settings' ) ) :
	/**
	 * Wallet extention page class.
	 */
	class Woo_Wallet_Extensions_Settings {
		/**
		 * Settings API
		 *
		 * @var object
		 */
		private $settings_api;

		/**
		 * Class constructor
		 *
		 * @param object $settings_api settings_api.
		 */
		public function __construct( $settings_api ) {
			$this->settings_api = $settings_api;
			add_action( 'admin_init', array( $this, 'plugin_settings_page_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 65 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
			add_action( 'woo_wallet_form_bottom__wallet_settings_extensions_general', array( $this, 'display_extensions' ) );
		}

		/**
		 * WC wallet menu
		 */
		public function admin_menu() {
			add_submenu_page( 'woo-wallet', __( 'Extensions', 'woo-wallet' ), __( 'Extensions', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-extensions', array( $this, 'plugin_page' ) );
		}

		/**
		 * Admin init.
		 */
		public function plugin_settings_page_init() {
			// set the settings.
			$this->settings_api->set_sections( $this->get_settings_sections() );
			foreach ( $this->get_settings_sections() as $section ) {
				if ( method_exists( $this, "update_option_{$section['id']}_callback" ) ) {
					add_action( "update_option_{$section['id']}", array( $this, "update_option_{$section['id']}_callback" ), 10, 3 );
				}
			}
			$this->settings_api->set_fields( $this->get_settings_fields() );
			// initialize settings.
			$this->settings_api->admin_init();
		}

		/**
		 * Enqueue scripts and styles
		 */
		public function admin_enqueue_scripts() {
			$screen    = get_current_screen();
			$screen_id = $screen ? $screen->id : '';
			$suffix    = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
			wp_register_script( 'woo-wallet-admin-settings', woo_wallet()->plugin_url() . '/assets/js/admin/admin-settings' . $suffix . '.js', array( 'jquery' ), WOO_WALLET_PLUGIN_VERSION, true );
			$woo_wallet_screen_id = sanitize_title( __( 'TeraWallet', 'woo-wallet' ) );
			if ( in_array( $screen_id, array( "{$woo_wallet_screen_id}_page_woo-wallet-extensions" ), true ) ) {
				wp_enqueue_style( 'dashicons' );
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_style( 'woo_wallet_admin_styles' );
				wp_add_inline_style( 'woo_wallet_admin_styles', 'tr.licence_key_nonce{ display:none; }' );
				wp_enqueue_media();
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'woo-wallet-admin-settings' );
				$localize_param = array(
					'screen_id' => $screen_id,
				);
				wp_localize_script( 'woo-wallet-admin-settings', 'woo_wallet_admin_settings_param', $localize_param );
			}
		}

		/**
		 * Setting sections
		 *
		 * @return array
		 */
		public function get_settings_sections() {
			$sections = array(
				array(
					'id'    => '_wallet_settings_extensions_general',
					'title' => __( 'Extensions', 'woo-wallet' ),
					'icon'  => 'dashicons-admin-plugins',
				),
			);
			return apply_filters( 'woo_wallet_extensions_settings_sections', $sections );
		}

		/**
		 * Returns all the settings fields
		 *
		 * @return array settings fields
		 */
		public function get_settings_fields() {
			$settings_fields = array();
			return apply_filters( 'woo_wallet_extensions_settings_filds', $settings_fields );
		}

		/**
		 * Display plugin settings page
		 */
		public function plugin_page() {
			echo '<div class="wrap wc_addons_wrap">';
			settings_errors();
			echo '<div class="wallet-settings-extensions-wrap">';
			$this->settings_api->show_navigation();
			$this->settings_api->show_forms();
			echo '</div>';
			echo '</div>';
		}
		/**
		 * Display extension page HTML.
		 */
		public function display_extensions() {
			?>
			<style type="text/css">
				div#_wallet_settings_extensions_general h2 {
					display: none;
				}
				.wc_addons_wrap .addons-column{
					padding: 0 !important;
				}
								.wc_addons_wrap {
					max-width: 1200px;
				}
				.wc_addons_wrap .addons-featured {
					margin: 0;
				}
				.wc_addons_wrap .addons-banner-block, .wc_addons_wrap .addons-wcs-banner-block {
					background: #fff;
					border: 1px solid #ddd;
					margin: 0 0 1em 0;
					padding: 2em 2em 1em;
				}
				.wc_addons_wrap .addons-banner-block p {
					margin: 0 0 20px;
				}
				.wc_addons_wrap .addons-banner-block-item:nth-child(-n+3) {
					display: block;
				}
				.wc_addons_wrap .addons-banner-block-item, .wc_addons_wrap .addons-column-block-item {
					display: none;
				}
				.wc_addons_wrap .addons-banner-block-item {
					border: 1px solid #c5c5c5;
					border-radius: 3px;
					-webkit-box-flex: 1;
					flex: 1;
					margin: 1em;
					min-width: 200px;
					width: 30%;
				}

				.wc_addons_wrap .addons-banner-block-item-icon {
					height: 143px;
					border-bottom: 1px solid #e6e6e6;
				}
				.wc_addons_wrap .addons-banner-block-item-icon, .wc_addons_wrap .addons-column-block-item-icon {
					-webkit-box-align: center;
					align-items: center;
					display: -webkit-box;
					display: flex;
					-webkit-box-pack: center;
					justify-content: center;
				}
				.wc_addons_wrap .addons-banner-block-items {
					display: -webkit-box;
					display: flex;
					-webkit-box-orient: horizontal;
					-webkit-box-direction: normal;
					flex-direction: row;
					flex-wrap: wrap;
					justify-content: space-around;
					margin: 0 -10px 0 -10px;
				}
				.wc_addons_wrap .addons-banner-block img {
					height: 120px;
				}
				.wc_addons_wrap .addons-banner-block-item-content {
					display: -webkit-box;
					display: flex;
					-webkit-box-orient: vertical;
					-webkit-box-direction: normal;
					flex-direction: column;
					height: 184px;
					-webkit-box-pack: justify;
					justify-content: space-between;
					padding: 24px;
				}
				.wc_addons_wrap .addons-banner-block-item-content h3 {
					margin-top: 0;
				}
				.wc_addons_wrap .addons-banner-block-item-content p {
					margin: 0 0 auto;
				}
				.wc_addons_wrap .addons-column-section {
					display: -webkit-box;
					-webkit-box-orient: horizontal;
					-webkit-box-direction: normal;
					flex-direction: row;
					flex-wrap: wrap;
					justify-content: space-around;
				}
				.wc_addons_wrap .addons-column {
					padding: 0 !important;
				}
				.wc_addons_wrap .addons-column-block, .wc_addons_wrap .addons-small-light-block {
					background: #fff;
				}
				.wc_addons_wrap .addons-column-block, .wc_addons_wrap .addons-small-dark-block, .wc_addons_wrap .addons-small-light-block {
					box-sizing: border-box;
					border: 1px solid #ddd;
					margin: 0 0 1em;
					padding: 20px;
				}
				.wc_addons_wrap .addons-column-block-item:nth-of-type(-n+3) {
					display: -webkit-box;
					display: flex;
				}
				.wc_addons_wrap .addons-column-block-item {
					border-top: 2px solid #f9f9f9;
					-webkit-box-orient: horizontal;
					-webkit-box-direction: normal;
					flex-direction: row;
					flex-wrap: wrap;
					-webkit-box-pack: justify;
					justify-content: space-between;
					margin: 0 -20px;
					padding: 20px;
				}

				.wc_addons_wrap .addons-column-block-item-icon {
					border: 1px solid #e6e6e6;
					height: 100px;
					margin: 0 10px 10px 0;
					width: 100px;
				}
				.wc_addons_wrap .addons-column-block img {
					max-height: 90px;
				}

				.wc_addons_wrap .addons-column-block-item {
					border-top: 2px solid #e6e6e6;
					-webkit-box-orient: horizontal;
					-webkit-box-direction: normal;
					flex-direction: row;
					flex-wrap: wrap;
					-webkit-box-pack: justify;
					justify-content: space-between;
					margin: 0 -20px;
					padding: 20px;
				}
				.wc_addons_wrap .addons-column-block-item-content {
					display: -webkit-box;
					display: flex;
					-webkit-box-flex: 1;
					flex: 1;
					flex-wrap: wrap;
					height: 20%;
					-webkit-box-pack: justify;
					justify-content: space-between;
					min-width: 200px;
				}
				.wc_addons_wrap .addons-column-block-item-content a {
					float: right;
				}
				.wc_addons_wrap .addons-button-solid {
					background-color: #674399;
					color: #fff;
				}
				.wc_addons_wrap .addons-button {
					border-radius: 3px;
					cursor: pointer;
					display: block;
					height: 37px;
					line-height: 37px;
					text-align: center;
					text-decoration: none;
					width: 124px;
				}
				.wc_addons_wrap .addons-column-block-item-content p {
					float: left;
					width: 100%;
				}
			</style>
			<div class="addons-featured">
				<div class="addons-banner-block">
					<h1>Obtain Superpowers to get the best out of TeraWallet </h1>
					<p>These power boosting extensions can unlock the ultimate potential for your site.</p>
					<div class="addons-banner-block-items">
						<div class="addons-banner-block-item">
							<div class="addons-banner-block-item-icon">
								<img class="addons-img" src="<?php echo esc_url( woo_wallet()->plugin_url() . '/assets/images/wallet-withdrawal.png' ); ?>">
							</div>
							<div class="addons-banner-block-item-content">
								<h3>Wallet Withdrawal</h3>
								<p>Let your users withdraw their Wallet balance to bank and other digital accounts like PayPal with this awesome addon.</p>
								<a href="https://standalonetech.com/product/wallet-withdrawal/" class="button addons-button addons-button-solid">
									$49 / Year		</a>
							</div>
						</div>
						<div class="addons-banner-block-item">
							<div class="addons-banner-block-item-icon">
								<img class="addons-img" src="<?php echo esc_url( woo_wallet()->plugin_url() . '/assets/images/wallet-importer.png' ); ?>">
							</div>
							<div class="addons-banner-block-item-content">
								<h3>Wallet Importer</h3>
								<p>Wallet importer addon enables you to modify the Wallet balances of multiple or all users with just one CSV import, hassle free.</p>
								<a href="https://standalonetech.com/product/wallet-importer/" class="button addons-button addons-button-solid">
									$15	/ Year	</a>
							</div>
						</div>
						<div class="addons-banner-block-item">
							<div class="addons-banner-block-item-icon">
								<img class="addons-img" src="<?php echo esc_url( woo_wallet()->plugin_url() . '/assets/images/wallet-coupons.png' ); ?>">
							</div>
							<div class="addons-banner-block-item-content">
								<h3>Wallet Coupons</h3>
								<p>Wallet Coupons add-on is the coupon system of Wallet. Coupons are a great way to offer rewards to your customers, coupons to be automatically redeemed to the customer's wallet if its restrictions are met.</p>
								<a href="https://standalonetech.com/product/wallet-coupons/" class="button addons-button addons-button-solid">
									$39	/ Year	</a>
							</div>
						</div>
					</div>
				</div>
				<div class="addons-column-section">
					<div class="addons-column">
						<div class="addons-column-block">
							<h1>Integrate with third party add-ons.</h1>
							<div class="addons-column-block-item">
								<div class="addons-column-block-item-icon">
									<img class="addons-img" src="<?php echo esc_url( woo_wallet()->plugin_url() . '/assets/images/wallet-affiliatewp.png' ); ?>">
								</div>
								<div class="addons-column-block-item-content">
									<h3>Wallet AffiliateWP</h3>
									<a href="https://standalonetech.com/product/wallet-affiliatewp/" class="button addons-button addons-button-solid">
										$15	/ Year	</a>
									<p>Pay AffiliateWP referrals as Wallet credit.</p>
								</div>
							</div>
						</div>
					</div>
					<div class="addons-column"></div>
				</div>
			</div>
			<?php

		}

	}

	endif;

new Woo_Wallet_Extensions_Settings( woo_wallet()->settings_api );
