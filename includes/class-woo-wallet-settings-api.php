<?php
/**
 * WooCommerce wallet settings API helper class
 *
 * @version 1.0.0
 *
 * @author Subrata Mal
 * @package WooWallet
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
		 * Enqueue scripts and styles
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
		 * Initialize and registers the settings sections and fileds to WordPress
		 *
		 * Usually this should be called at `admin_init` hook.
		 *
		 * This function gets the initiated settings sections and fields. Then
		 * registers them to WordPress and ready for use.
		 */
		public function admin_init() {
			// Register settings sections.
			foreach ( $this->settings_sections as $section ) {
				if ( false === get_option( $section['id'] ) ) {
					add_option( $section['id'] );
				}

				if ( isset( $section['callback'] ) ) {
					$callback = $section['callback'];
				} else {
					$callback = null;
				}

				add_settings_section( $section['id'], $section['title'], $callback, $section['id'] );
			}

			// Register settings fields.
			foreach ( $this->settings_fields as $section => $field ) {
				foreach ( $field as $option ) {

					$name     = $option['name'];
					$type     = isset( $option['type'] ) ? $option['type'] : 'text';
					$label    = isset( $option['label'] ) ? $option['label'] : '';
					$callback = isset( $option['callback'] ) ? $option['callback'] : array( $this, 'callback_' . $type );

					$args = array(
						'id'                => $name,
						'class'             => isset( $option['class'] ) ? $option['class'] : $name,
						'label_for'         => "{$section}[{$name}]",
						'desc'              => isset( $option['desc'] ) ? $option['desc'] : '',
						'name'              => $label,
						'section'           => $section,
						'size'              => isset( $option['size'] ) ? $option['size'] : null,
						'options'           => isset( $option['options'] ) ? $option['options'] : '',
						'std'               => isset( $option['default'] ) ? $option['default'] : '',
						'sanitize_callback' => isset( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : '',
						'type'              => $type,
						'placeholder'       => isset( $option['placeholder'] ) ? $option['placeholder'] : '',
						'min'               => isset( $option['min'] ) ? $option['min'] : '',
						'max'               => isset( $option['max'] ) ? $option['max'] : '',
						'step'              => isset( $option['step'] ) ? $option['step'] : '',
						'multiple'          => isset( $option['multiple'] ) ? $option['multiple'] : '',
					);

					add_settings_field( "{$section}[{$name}]", $label, $callback, $section, $section, $args );
				}
			}

			// Creates our settings in the options table.
			foreach ( $this->settings_sections as $section ) {
				register_setting( $section['id'], $section['id'], array( $this, 'sanitize_options' ) );
			}
		}

		/**
		 * Get field description for display
		 *
		 * @param array $args settings field args.
		 */
		public function get_field_description( $args ) {
			if ( ! empty( $args['desc'] ) ) {
				?>
				<p class="description"><?php echo esc_html( $args['desc'] ); ?></p>
				<?php
			}
		}

		/**
		 * Displays a text field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_text( $args ) {
			$value       = $this->get_option( $args['id'], $args['section'], $args['std'] );
			$size        = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
			$type        = isset( $args['type'] ) ? $args['type'] : 'text';
			$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];

			?>
			<input type="<?php echo esc_attr( $type ); ?>" class="<?php echo esc_attr( $size ); ?>-text" id="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" />
			<?php
			echo esc_html( $this->get_field_description( $args ) );
		}

		/**
		 * Render a hidden random field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_rand( $args ) {
			$value = wp_rand();
			?>
			<input type="hidden" id="<?php echo esc_attr( $args['section'] ); ?>-<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['section'] ); ?>-<?php echo esc_attr( $args['id'] ); ?>" value="<?php echo esc_html( $value ); ?>" />
			<?php
		}

		/**
		 * Displays a url field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_url( $args ) {
			$this->callback_text( $args );
		}

		/**
		 * Displays a number field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_number( $args ) {
			$value       = $this->get_option( $args['id'], $args['section'], $args['std'] );
			$size        = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
			$type        = isset( $args['type'] ) ? $args['type'] : 'number';
			$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
			$min         = empty( $args['min'] ) ? 0 : $args['min'];
			$max         = empty( $args['max'] ) ? '' : $args['max'];
			$step        = empty( $args['step'] ) ? '0.01' : $args['step'];
			?>
			<input type="<?php echo esc_attr( $type ); ?>" class="<?php echo esc_attr( $size ); ?>-text" id="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>"/>
			<?php
			$this->get_field_description( $args );
		}

		/**
		 * Displays a checkbox for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_checkbox( $args ) {
			$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
			?>
			<fieldset>
				<label for="wcwp-<?php echo esc_attr( $args['section'] ); ?>-<?php echo esc_attr( $args['id'] ); ?>">
				<input type="hidden" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="off" />
				<input type="checkbox" class="checkbox" id="wcwp-<?php echo esc_attr( $args['section'] ); ?>-<?php echo esc_attr( $args['id'] ); ?>" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="on" <?php checked( $value, 'on', true ); ?> />
				<?php echo esc_html( $args['desc'] ); ?>
				</label>
			</fieldset>
			<?php
		}

		/**
		 * Displays a multicheckbox a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_multicheck( $args ) {
			$value = $this->get_option( $args['id'], $args['section'], $args['std'] );
			?>
			<fieldset>
				<input type="hidden" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="" />
				<?php
				foreach ( $args['options'] as $key => $label ) {
					$checked = isset( $value[ $key ] ) ? $value[ $key ] : '0';
					?>
						<label for="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>][<?php echo esc_attr( $key ); ?>]">
							<input type="checkbox" class="checkbox" id="wcwp-<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>][<?php echo esc_attr( $key ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $checked, $key, true ); ?> />
							<?php echo esc_html( $label ); ?>
						</label><br>
					<?php
				}
				?>
			</fieldset>
			<?php
		}

		/**
		 * Displays a multicheckbox a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_radio( $args ) {

			$value = $this->get_option( $args['id'], $args['section'], $args['std'] );
			?>
			<fieldset>
				<?php
				foreach ( $args['options'] as $key => $label ) {
					?>
					<label for="wcwp-<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>][<?php echo esc_attr( $key ); ?>]">
						<input type="radio" class="radio" id="wcwp-<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>][<?php echo esc_attr( $key ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php esc_attr( $value ); ?>" <?php checked( $value, $key, true ); ?> />
						<?php echo esc_html( $label ); ?>
					</label><br>
					<?php
				}
				$this->get_field_description( $args );
				?>
			</fieldset>
			<?php
		}

		/**
		 * Displays a selectbox for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_select( $args ) {

			$size     = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular-text';
			$multiple = ! empty( $args['multiple'] ) ? true : false;
			$value    = $this->get_option( $args['id'], $args['section'], $args['std'] );

			if ( $multiple ) {
				?>
				<select class="<?php echo esc_attr( $size ); ?>" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>][]" id="<?php echo esc_attr( $args['section'] ); ?>-<?php echo esc_attr( $args['id'] ); ?>" multiple="true">
				<?php
			} else {
				?>
				<select class="<?php echo esc_attr( $size ); ?>" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" id="<?php echo esc_attr( $args['section'] ); ?>-<?php echo esc_attr( $args['id'] ); ?>">
				<?php
			}
			foreach ( $args['options'] as $key => $label ) {
				if ( $multiple ) {
					?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( in_array( $key, (array) $value, true ), true ); ?>><?php echo esc_html( $label ); ?></option>
					<?php
				} else {
					?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>><?php echo esc_html( $label ); ?></option>
					<?php
				}
			}
			?>
				</select>
			<?php
			$this->get_field_description( $args );
		}

		/**
		 * Displays a textarea for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_textarea( $args ) {

			$value       = $this->get_option( $args['id'], $args['section'], $args['std'] );
			$size        = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
			$placeholder = empty( $args['placeholder'] ) ? '' : $args['placeholder'];
			?>
			<textarea rows="5" cols="55" class="<?php echo esc_attr( $size ); ?>-text" id="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php
			$this->get_field_description( $args );
		}

		/**
		 * Displays a textarea for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_html( $args ) {
			$this->get_field_description( $args );
		}

		/**
		 * Displays a rich text textarea for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_wysiwyg( $args ) {

			$value = $this->get_option( $args['id'], $args['section'], $args['std'] );
			$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : '500px';

			echo '<div style="max-width: ' . esc_attr( $size ) . ';">';

			$editor_settings = array(
				'teeny'         => true,
				'textarea_name' => $args['section'] . '[' . $args['id'] . ']',
				'textarea_rows' => 10,
			);

			if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
				$editor_settings = array_merge( $editor_settings, $args['options'] );
			}

			wp_editor( $value, $args['section'] . '-' . $args['id'], $editor_settings );

			echo '</div>';

			$this->get_field_description( $args );
		}

		/**
		 * Displays a file upload field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_file( $args ) {

			$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
			$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
			$label = isset( $args['options']['button_label'] ) ? $args['options']['button_label'] : __( 'Choose File', 'woo-wallet' );
			?>
			<input type="text" class="<?php echo esc_attr( $size ); ?>-text wpsa-url" id="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>"/>
			<input type="button" class="button wpsa-browse" value="<?php esc_html( $label ); ?>" />
			<?php
			$this->get_field_description( $args );
		}

		/**
		 * Displays a file upload field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_attachment( $args ) {

			$value                = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
			$size                 = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
			$id                   = $args['section'] . '[' . $args['id'] . ']';
			$label                = isset( $args['options']['button_label'] ) ? $args['options']['button_label'] : __( 'Choose File', 'woo-wallet' );
			$uploader_title       = isset( $args['options']['uploader_title'] ) ? $args['options']['uploader_title'] : __( 'Select', 'woo-wallet' );
			$uploader_button_text = isset( $args['options']['uploader_button_text'] ) ? $args['options']['uploader_button_text'] : __( 'Select', 'woo-wallet' );
			$attachment_src       = WC()->plugin_url() . '/assets/images/placeholder.png';
			if ( $value ) {
				$attachment_src = wp_get_attachment_url( $value );
			}
			?>
			<img class="wpsa-attachment-image" src="<?php echo esc_url( $attachment_src ); ?>" width="75" />
			<input type="hidden" class="<?php echo esc_attr( $size ); ?>-text wpsa-attachment-id" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $id ); ?>" value="<?php echo esc_attr( $value ); ?>"/>
			<input type="button" class="button wpsa-attachment" data-uploader_title="<?php echo esc_attr( $uploader_title ); ?>" data-uploader_button_text="<?php echo esc_attr( $uploader_button_text ); ?>" value="<?php echo esc_html( $label ); ?>" />
			<?php
			$this->get_field_description( $args );
		}

		/**
		 * Displays a password field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_password( $args ) {
			$args['type'] = 'password';
			$this->callback_text( $args );
		}

		/**
		 * Displays a color picker field for a settings field
		 *
		 * @param array $args settings field args.
		 */
		public function callback_color( $args ) {

			$value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
			$size  = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
			?>
			<input type="text" class="<?php echo esc_attr( $size ); ?>-text wp-color-picker-field" id="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" name="<?php echo esc_attr( $args['section'] ); ?>[<?php echo esc_attr( $args['id'] ); ?>]" value="<?php echo esc_attr( $value ); ?>" data-default-color="<?php echo esc_attr( $args['std'] ); ?>" />
			<?php
			$this->get_field_description( $args );
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

				// If callback is set, call it.
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

			// Iterate over registered fields and see if we can find proper callback.
			foreach ( $this->settings_fields as $section => $options ) {
				foreach ( $options as $option ) {
					if ( $option['name'] !== $slug ) {
						continue;
					}

					// Return the callback name.
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
		 * @return string
		 */
		public function get_option( $option, $section, $default = '' ) {

			$options = get_option( $section );

			$option_value = isset( $options[ $option ] ) && ! empty( $options[ $option ] ) ? $options[ $option ] : $default;

			return apply_filters( "woo_wallet_get_option_{$section}_{$option}", $option_value );
		}

		/**
		 * Show navigations as tab
		 *
		 * Shows all the settings section labels as tab
		 */
		public function show_navigation() {
			$count = count( $this->settings_sections );
			// Don't show the navigation if only one section exists.
			if ( 1 === $count ) {
				return;
			}
			?>
			<h2 class="nav-tab-wrapper">
				<?php
				foreach ( $this->settings_sections as $tab ) {
					if ( ! isset( $tab['icon'] ) || empty( $tab['icon'] ) ) {
						$tab['icon'] = 'dashicons-admin-generic';
					}
					?>
					<a href="#<?php echo esc_attr( $tab['id'] ); ?>" class="nav-tab" id="<?php echo esc_attr( $tab['id'] ); ?>-tab"><span class="dashicons <?php echo esc_attr( $tab['icon'] ); ?>"></span> <?php echo esc_html( $tab['title'] ); ?></a>
					<?php
				}
				?>
			</h2>
			<?php
		}

		/**
		 * Show the section settings forms
		 *
		 * This function displays every sections in a different form
		 */
		public function show_forms() {
			?>
			<div class="metabox-holder">
				<?php foreach ( $this->settings_sections as $form ) { ?>
					<div id="<?php echo esc_attr( $form['id'] ); ?>" class="group" style="display: none;">
						<form method="post" action="options.php">
							<?php
							do_action( 'woo_wallet_form_top_' . $form['id'], $form );
							settings_fields( $form['id'] );
							do_settings_sections( $form['id'] );
							do_action( 'woo_wallet_form_bottom_' . $form['id'], $form );
							if ( isset( $this->settings_fields[ $form['id'] ] ) ) :
								?>
								<?php submit_button(); ?>
							<?php endif; ?>
						</form>
					</div>
				<?php } ?>
			</div>
			<?php
			$this->script();
		}

		/**
		 * Tabable JavaScript codes & Initiate Color Picker
		 *
		 * This code uses localstorage for displaying active tabs
		 */
		public function script() {
			$this->style_fix();
		}
		/**
		 * Fix style.
		 */
		public function style_fix() {
			global $wp_version;

			if ( version_compare( $wp_version, '3.8', '<=' ) ) :
				?>
				<style type="text/css">
					/** WordPress 3.8 Fix **/
					.form-table th { padding: 20px 10px; }
					#wpbody-content .metabox-holder { padding-top: 5px; }
				</style>
				<?php
			endif;
		}

	}



endif;
