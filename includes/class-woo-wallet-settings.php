<?php

/**
 * Woo Wallet settings
 *
 * @author Subrata Mal
 */
if ( ! class_exists( 'Woo_Wallet_Settings' ) ) :

	class Woo_Wallet_Settings {
		/* setting api object */

		private $settings_api;

		/**
		 * Class constructor
		 *
		 * @param object $settings_api
		 */
		public function __construct( $settings_api ) {
			$this->settings_api = $settings_api;
			add_action( 'admin_init', array( $this, 'plugin_settings_page_init' ) );
			add_action( 'admin_menu', array( $this, 'admin_menu' ), 60 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		}

		/**
		 * WC wallet menu
		 */
		public function admin_menu() {
			add_submenu_page( 'woo-wallet', __( 'Settings', 'woo-wallet' ), __( 'Settings', 'woo-wallet' ), get_wallet_user_capability(), 'woo-wallet-settings', array( $this, 'plugin_page' ) );
		}

		/**
		 * Admin init
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
			$woo_wallet_settings_screen_id = sanitize_title( __( 'TeraWallet', 'woo-wallet' ) );
			if ( in_array( $screen_id, array( "{$woo_wallet_settings_screen_id}_page_woo-wallet-settings" ) ) ) {
				wp_enqueue_style( 'dashicons' );
				wp_enqueue_style( 'wp-color-picker' );
				wp_enqueue_style( 'woo_wallet_admin_styles' );
				wp_enqueue_media();
				wp_enqueue_script( 'wp-color-picker' );
				wp_enqueue_script( 'jquery' );
				wp_enqueue_script( 'woo-wallet-admin-settings' );
				$localize_param = array(
					'screen_id'          => $screen_id,
					'gateways'           => $this->get_wc_payment_gateways( 'id' ),
					'settings_screen_id' => "{$woo_wallet_settings_screen_id}_page_woo-wallet-settings",
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
					'id'    => '_wallet_settings_general',
					'title' => __( 'General', 'woo-wallet' ),
					'icon'  => 'dashicons-admin-generic',
				),
				array(
					'id'    => '_wallet_settings_credit',
					'title' => __( 'Credit Options', 'woo-wallet' ),
					'icon'  => 'dashicons-money',
				),
			);
			return apply_filters( 'woo_wallet_settings_sections', $sections );
		}

		/**
		 * Returns all the settings fields
		 *
		 * @return array settings fields
		 */
		public function get_settings_fields() {
			$settings_fields = array(
				'_wallet_settings_general' => array_merge(
					array(
						array(
							'name'    => 'product_title',
							'label'   => __( 'Rechargeable Product Title', 'woo-wallet' ),
							'desc'    => __( 'Enter wallet rechargeable product title', 'woo-wallet' ),
							'type'    => 'text',
							'default' => $this->get_rechargeable_product_title(),
						),
						array(
							'name'    => 'product_image',
							'label'   => __( 'Rechargeable Product Image', 'woo-wallet' ),
							'desc'    => __( 'Choose wallet rechargeable product image', 'woo-wallet' ),
							'type'    => 'attachment',
							'options' => array(
								'button_label'         => __( 'Set product image', 'woo-wallet' ),
								'uploader_title'       => __( 'Product image', 'woo-wallet' ),
								'uploader_button_text' => __( 'Set product image', 'woo-wallet' ),
							),
						),
					),
					$this->get_wc_tax_options(),
					array(
						array(
							'name'  => 'min_topup_amount',
							'label' => __( 'Minimum Topup Amount', 'woo-wallet' ),
							'desc'  => __( 'The minimum amount needed for wallet top up', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
						array(
							'name'  => 'max_topup_amount',
							'label' => __( 'Maximum Topup Amount', 'woo-wallet' ),
							'desc'  => __( 'The maximum amount needed for wallet top up', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
					),
					$this->wp_menu_locations(),
					array(
						array(
							'name'  => 'is_auto_deduct_for_partial_payment',
							'label' => __( 'Auto deduct wallet balance for partial payment', 'woo-wallet' ),
							'desc'  => __( 'If a purchase requires more balance than you have in your wallet, then if checked the wallet balance will be deduct first and the rest of the amount will need to be paid.', 'woo-wallet' ),
							'type'  => 'checkbox',
						),
						array(
							'name'    => 'is_enable_wallet_transfer',
							'label'   => __( 'Allow Wallet Transfer', 'woo-wallet' ),
							'desc'    => __( 'If checked user will be able to transfer fund to another user.', 'woo-wallet' ),
							'type'    => 'checkbox',
							'default' => 'on',
						),
						array(
							'name'  => 'min_transfer_amount',
							'label' => __( 'Minimum Transfer Amount', 'woo-wallet' ),
							'desc'  => __( 'Enter minimum transfer amount', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
						array(
							'name'    => 'transfer_charge_type',
							'label'   => __( 'Transfer charge type', 'woo-wallet' ),
							'desc'    => __( 'Select transfer charge type percentage or fixed', 'woo-wallet' ),
							'type'    => 'select',
							'options' => array(
								'percent' => __( 'Percentage', 'woo-wallet' ),
								'fixed'   => __( 'Fixed', 'woo-wallet' ),
							),
							'size'    => 'regular-text wc-enhanced-select',
						),
						array(
							'name'  => 'transfer_charge_amount',
							'label' => __( 'Transfer charge Amount', 'woo-wallet' ),
							'desc'  => __( 'Enter transfer charge amount', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
					),
					$this->get_wc_payment_allowed_gateways()
				),
				'_wallet_settings_credit'  => array_merge(
					array(
						array(
							'name'  => 'is_enable_cashback_reward_program',
							'label' => __( 'Cashback Reward Program', 'woo-wallet' ),
							'desc'  => __( 'Run cashback reward program on your store', 'woo-wallet' ),
							'type'  => 'checkbox',
						),
						array(
							'name'     => 'process_cashback_status',
							'label'    => __( 'Process cashback', 'woo-wallet' ),
							'desc'     => __( 'Select order status to process cashback', 'woo-wallet' ),
							'type'     => 'select',
							'options'  => apply_filters(
								'woo_wallet_process_cashback_status',
								array(
									'pending'    => __( 'Pending payment', 'woo-wallet' ),
									'on-hold'    => __( 'On hold', 'woo-wallet' ),
									'processing' => __( 'Processing', 'woo-wallet' ),
									'completed'  => __(
										'Completed',
										'woo-wallet'
									),
								)
							),
							'default'  => array( 'processing', 'completed' ),
							'size'     => 'regular-text wc-enhanced-select',
							'multiple' => true,
						),
						array(
							'name'    => 'cashback_rule',
							'label'   => __( 'Cashback Rule', 'woo-wallet' ),
							'desc'    => __( 'Select Cashback Rule cart or product wise', 'woo-wallet' ),
							'type'    => 'select',
							'options' => apply_filters(
								'woo_wallet_cashback_rules',
								array(
									'cart'        => __( 'Cart wise', 'woo-wallet' ),
									'product'     => __( 'Product wise', 'woo-wallet' ),
									'product_cat' => __(
										'Product category wise',
										'woo-wallet'
									),
								)
							),
							'size'    => 'regular-text wc-enhanced-select',
						),
						array(
							'name'    => 'cashback_type',
							'label'   => __( 'Cashback type', 'woo-wallet' ),
							'desc'    => __( 'Select cashback type percentage or fixed', 'woo-wallet' ),
							'type'    => 'select',
							'options' => array(
								'percent' => __( 'Percentage', 'woo-wallet' ),
								'fixed'   => __( 'Fixed', 'woo-wallet' ),
							),
							'size'    => 'regular-text wc-enhanced-select',
						),
						array(
							'name'  => 'cashback_amount',
							'label' => __( 'Cashback Amount', 'woo-wallet' ),
							'desc'  => __( 'Enter cashback amount', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
						array(
							'name'  => 'min_cart_amount',
							'label' => __( 'Minimum Cart Amount', 'woo-wallet' ),
							'desc'  => __( 'Enter applicable minimum cart amount for cashback', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
						array(
							'name'  => 'max_cashback_amount',
							'label' => __( 'Maximum Cashback Amount', 'woo-wallet' ),
							'desc'  => __( 'Enter maximum cashback amount', 'woo-wallet' ),
							'type'  => 'number',
							'step'  => '0.01',
						),
						array(
							'name'    => 'allow_min_cashback',
							'label'   => __( 'Allow Minimum cashback', 'woo-wallet' ),
							'desc'    => __( 'If checked minimum cashback amount will be applied on product category cashback calculation.', 'woo-wallet' ),
							'type'    => 'checkbox',
							'default' => 'on',
						),
						array(
							'name'  => 'is_enable_gateway_charge',
							'label' => __( 'Payment gateway charge', 'woo-wallet' ),
							'desc'  => __( 'Charge customer when they add balance to their wallet?', 'woo-wallet' ),
							'type'  => 'checkbox',
						),
						array(
							'name'    => 'gateway_charge_type',
							'label'   => __( 'Charge type', 'woo-wallet' ),
							'desc'    => __( 'Select gateway charge type percentage or fixed', 'woo-wallet' ),
							'type'    => 'select',
							'options' => array(
								'percent' => __( 'Percentage', 'woo-wallet' ),
								'fixed'   => __( 'Fixed', 'woo-wallet' ),
							),
							'size'    => 'regular-text wc-enhanced-select',
						),
					),
					$this->get_wc_payment_gateways(),
					array()
				),
			);
			return apply_filters( 'woo_wallet_settings_filds', $settings_fields );
		}

		/**
		 * Fetch rechargeable product title
		 *
		 * @return string title
		 */
		public function get_rechargeable_product_title() {
			$product_title  = '';
			$wallet_product = get_wallet_rechargeable_product();
			if ( $wallet_product ) {
				$product_title = $wallet_product->get_title();
			}
			return $product_title;
		}

		/**
		 * Display plugin settings page
		 */
		public function plugin_page() {
			echo '<div class="wrap">';
			echo '<h2 style="margin-bottom: 15px;">' . esc_html__( 'Settings', 'woo-wallet' ) . '</h2>';
			settings_errors();
			echo '<div class="wallet-settings-wrap">';
			$this->settings_api->show_navigation();
			$this->settings_api->show_forms();
			echo '</div>';
			echo '</div>';
		}

		/**
		 * Chargeable payment gateways
		 *
		 * @param string $context context.
		 * @return array
		 */
		public function get_wc_payment_gateways( $context = 'field' ) {
			$gateways = array();
			foreach ( WC()->payment_gateways()->payment_gateways as $gateway ) {
				if ( 'yes' === $gateway->enabled && 'wallet' !== $gateway->id ) {
					$method_title = $gateway->get_title() ? $gateway->get_title() : __( '(no title)', 'woo-wallet' );
					if ( 'field' === $context ) {
						$gateways[] = array(
							'name'  => $gateway->id,
							'label' => $method_title,
							'desc'  => __( 'Enter gateway charge amount for ', 'woo-wallet' ) . $method_title,
							'type'  => 'number',
							'step'  => '0.01',
						);
					} else {
						$gateways[] = $gateway->id;
					}
				}
			}
			return $gateways;
		}

		/**
		 * Allowed payment gateways
		 *
		 * @param string $context context.
		 * @return array
		 */
		public function get_wc_payment_allowed_gateways( $context = 'field' ) {
			$gateways = array();
			foreach ( WC()->payment_gateways()->payment_gateways as $gateway ) {
				if ( 'yes' === $gateway->enabled && 'wallet' !== $gateway->id ) {
					$method_title = $gateway->get_title() ? $gateway->get_title() : __( '(no title)', 'woo-wallet' );
					if ( 'field' === $context ) {
						$gateways[] = array(
							'name'    => $gateway->id,
							'label'   => $method_title,
							'desc'    => __( 'Allow this gateway for recharge wallet', 'woo-wallet' ),
							'type'    => 'checkbox',
							'default' => 'on',
						);
					}
				}
			}
			return $gateways;
		}

		/**
		 * Allowed payment gateways
		 *
		 * @param string $context context.
		 * @return array
		 */
		public function get_wc_tax_options( $context = 'field' ) {
			$tax_options = array();
			if ( wc_tax_enabled() ) {
				$tax_options[] = array(
					'name'    => '_tax_status',
					'label'   => __( 'Rechargeable Product Tax status', 'woo-wallet' ),
					'desc'    => __( 'Define whether or not the rechargeable Product is taxable.', 'woo-wallet' ),
					'type'    => 'select',
					'options' => array(
						'taxable' => __( 'Taxable', 'woo-wallet' ),
						'none'    => _x( 'None', 'Tax status', 'woo-wallet' ),
					),
					'size'    => 'regular-text wc-enhanced-select',
				);
				$tax_options[] = array(
					'name'    => '_tax_class',
					'label'   => __( 'Rechargeable Product Tax class', 'woo-wallet' ),
					'desc'    => __( 'Define whether or not the rechargeable Product is taxable.', 'woo-wallet' ),
					'type'    => 'select',
					'options' => wc_get_product_tax_class_options(),
					'desc'    => __( 'Choose a tax class for rechargeable product. Tax classes are used to apply different tax rates specific to certain types of product.', 'woo-wallet' ),
					'size'    => 'regular-text wc-enhanced-select',
				);
			}
			return $tax_options;
		}

		/**
		 * Get all registered nav menu locations settings
		 *
		 * @return array
		 */
		public function wp_menu_locations() {
			$menu_locations = array();
			if ( current_theme_supports( 'menus' ) ) {
				$locations = get_registered_nav_menus();
				if ( $locations ) {
					foreach ( $locations as $location => $title ) {
						$menu_locations[] = array(
							'name'  => $location,
							'label' => ( current( $locations ) === $title ) ? __( 'Mini wallet display location', 'woo-wallet' ) : '',
							'desc'  => $title,
							'type'  => 'checkbox',
						);
					}
				}
			}
			return $menu_locations;
		}

		/**
		 * Callback fuction of all option after save
		 *
		 * @param array  $old_value old_value.
		 * @param array  $value value.
		 * @param string $option option.
		 */
		public function update_option__wallet_settings_general_callback( $old_value, $value, $option ) {
			/**
			 * Save product title on option change
			 */
			if ( $old_value['product_title'] !== $value['product_title'] ) {
				$this->set_rechargeable_product_title( $value['product_title'] );
			}

			/**
			 * Save tax status
			 */
			if ( $old_value['_tax_status'] !== $value['_tax_status'] || $old_value['_tax_class'] !== $value['_tax_class'] ) {
				$this->set_rechargeable_tax_status( $value['_tax_status'], $value['_tax_class'] );
			}

			/**
			 * Save product image
			 */
			if ( $old_value['product_image'] !== $value['product_image'] ) {
				$this->set_rechargeable_product_image( $value['product_image'] );
			}
		}

		/**
		 * Set rechargeable product title
		 *
		 * @param string $title title.
		 * @return boolean | int
		 */
		public function set_rechargeable_product_title( $title ) {
			$wallet_product = get_wallet_rechargeable_product();
			if ( $wallet_product ) {
				$wallet_product->set_name( $title );
				return $wallet_product->save();
			}
			return false;
		}

		/**
		 * Set rechargeable tax status
		 *
		 * @param string $_tax_status Tax status.
		 * @param string $_tax_class Tax class.
		 * @return boolean | int
		 */
		public function set_rechargeable_tax_status( $_tax_status, $_tax_class ) {
			$wallet_product = get_wallet_rechargeable_product();
			if ( $wallet_product ) {
				$wallet_product->set_tax_status( $_tax_status );
				$wallet_product->set_tax_class( $_tax_class );
				return $wallet_product->save();
			}
			return false;
		}

		/**
		 * Set rechargeable product image
		 *
		 * @param int $attachment_id attachment_id.
		 * @return boolean | int
		 */
		public function set_rechargeable_product_image( $attachment_id ) {
			$wallet_product = get_wallet_rechargeable_product();
			if ( $wallet_product ) {
				$wallet_product->set_image_id( $attachment_id );
				return $wallet_product->save();
			}
			return false;
		}

	}

	endif;

new Woo_Wallet_Settings( woo_wallet()->settings_api );
