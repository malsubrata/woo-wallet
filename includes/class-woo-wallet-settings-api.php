<?php
/**
 * WooCommerce wallet settings API helper class.
 *
 * Acts as a data registry for the React settings app and a thin shim around
 * the WordPress Settings API: legacy server-rendered form callbacks were
 * removed in 1.7.0 when the React app became the sole renderer.
 *
 * @author Subrata Mal
 * @package StandaleneTech
 */

if ( ! class_exists( 'Woo_Wallet_Settings_API' ) ) :
	/**
	 * Wallet setting API class.
	 */
	class Woo_Wallet_Settings_API {

		/**
		 * Settings sections array
		 *
		 * @var array
		 */
		protected $settings_sections = array();

		/**
		 * Settings fields array
		 *
		 * @var array
		 */
		protected $settings_fields = array();

		/**
		 * Class constructor
		 */
		public function __construct() {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 15 );
		}

		/**
		 * Enqueue scripts and styles used by the (now React-driven) settings page.
		 */
		public function admin_enqueue_scripts() {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'woocommerce_admin_styles' );

			wp_enqueue_media();
			wp_enqueue_script( 'wp-color-picker' );
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}

		/**
		 * Set settings sections
		 *
		 * @param array $sections setting sections array.
		 */
		public function set_sections( $sections ) {
			$this->settings_sections = $sections;

			return $this;
		}

		/**
		 * Add a single section
		 *
		 * @param array $section section.
		 */
		public function add_section( $section ) {
			$this->settings_sections[] = $section;

			return $this;
		}

		/**
		 * Set settings fields
		 *
		 * @param array $fields settings fields array.
		 */
		public function set_fields( $fields ) {
			$this->settings_fields = $fields;

			return $this;
		}

		/**
		 * Add Field to settings page.
		 *
		 * @param array $section section.
		 * @param array $field field.
		 * @return object
		 */
		public function add_field( $section, $field ) {
			$defaults = array(
				'name'  => '',
				'label' => '',
				'desc'  => '',
				'type'  => 'text',
			);

			$arg                                 = wp_parse_args( $field, $defaults );
			$this->settings_fields[ $section ][] = $arg;

			return $this;
		}

		/**
		 * Register sections and fields with the WordPress Settings API.
		 *
		 * This still runs on `admin_init` so that `register_setting()` installs
		 * the `sanitize_option_{section}` filter — that filter fires on every
		 * `update_option()`, including REST-driven saves, and is the hook point
		 * for per-field `sanitize_callback`s declared in the section schema.
		 */
		public function admin_init() {
			foreach ( $this->settings_sections as $section ) {
				if ( false === get_option( $section['id'] ) ) {
					add_option( $section['id'] );
				}

				$callback = isset( $section['callback'] ) ? $section['callback'] : null;

				add_settings_section( $section['id'], '', $callback, $section['id'] );
			}

			foreach ( $this->settings_sections as $section ) {
				register_setting( $section['id'], $section['id'], array( $this, 'sanitize_options' ) );
			}
		}

		/**
		 * Sanitize callback for Settings API
		 *
		 * @param array $options options.
		 * @return mixed
		 */
		public function sanitize_options( $options ) {

			if ( ! $options ) {
				return $options;
			}

			foreach ( $options as $option_slug => $option_value ) {
				$sanitize_callback = $this->get_sanitize_callback( $option_slug );

				if ( $sanitize_callback ) {
					$options[ $option_slug ] = call_user_func( $sanitize_callback, $option_value );
					continue;
				}
			}

			return $options;
		}

		/**
		 * Get sanitization callback for given option slug
		 *
		 * @param string $slug option slug.
		 *
		 * @return mixed string or bool false
		 */
		public function get_sanitize_callback( $slug = '' ) {
			if ( empty( $slug ) ) {
				return false;
			}

			foreach ( $this->settings_fields as $section => $options ) {
				foreach ( $options as $option ) {
					if ( $option['name'] !== $slug ) {
						continue;
					}

					return isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : false;
				}
			}

			return false;
		}

		/**
		 * Get the value of a settings field
		 *
		 * @param string $option  settings field name.
		 * @param string $section the section name this field belongs to.
		 * @param string $default default text if it's not found.
		 * @return mixed
		 */
		public function get_option( $option, $section, $default = '' ) {

			$options = get_option( $section );

			$option_value = isset( $options[ $option ] ) && ! empty( $options[ $option ] ) ? $options[ $option ] : $default;

			return apply_filters( "woo_wallet_get_option_{$section}_{$option}", $option_value );
		}
	}

endif;
