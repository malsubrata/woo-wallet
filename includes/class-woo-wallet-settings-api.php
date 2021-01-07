<?php
/**
 * WooCommerce wallet settings API helper class
 *
 * @version 1.0.0
 *
 * @author Subrata Mal
 */
if ( ! class_exists( 'Woo_Wallet_Settings_API' ) ):

    class Woo_Wallet_Settings_API {

        /**
         * settings sections array
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
            add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 15);
        }

        /**
         * Enqueue scripts and styles
         */
        function admin_enqueue_scripts() {
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
         * @param array   $sections setting sections array
         */
        function set_sections( $sections) {
            $this->settings_sections = $sections;

            return $this;
        }

        /**
         * Add a single section
         *
         * @param array   $section
         */
        function add_section( $section) {
            $this->settings_sections[] = $section;

            return $this;
        }

        /**
         * Set settings fields
         *
         * @param array   $fields settings fields array
         */
        function set_fields( $fields) {
            $this->settings_fields = $fields;

            return $this;
        }

        function add_field( $section, $field) {
            $defaults = array(
                'name' => '',
                'label' => '',
                'desc' => '',
                'type' => 'text'
            );

            $arg = wp_parse_args( $field, $defaults);
            $this->settings_fields[$section][] = $arg;

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
        function admin_init() {
            //register settings sections
            foreach ( $this->settings_sections as $section) {
                if (false == get_option( $section['id'] ) ) {
                    add_option( $section['id'] );
                }

                if ( isset( $section['desc'] ) && ! empty( $section['desc'] ) ) {
                    $section['desc'] = '<div class="inside">' . $section['desc'] . '</div>';
                    $callback = create_function( '', 'echo "' . str_replace( '"', '\"', $section['desc'] ) . '";' );
                } else if ( isset( $section['callback'] ) ) {
                    $callback = $section['callback'];
                } else {
                    $callback = null;
                }

                add_settings_section( $section['id'], $section['title'], $callback, $section['id'] );
            }

            //register settings fields
            foreach ( $this->settings_fields as $section => $field) {
                foreach ( $field as $option) {

                    $name = $option['name'];
                    $type = isset( $option['type'] ) ? $option['type'] : 'text';
                    $label = isset( $option['label'] ) ? $option['label'] : '';
                    $callback = isset( $option['callback'] ) ? $option['callback'] : array( $this, 'callback_' . $type);

                    $args = array(
                        'id' => $name,
                        'class' => isset( $option['class'] ) ? $option['class'] : $name,
                        'label_for' => "{$section}[{$name}]",
                        'desc' => isset( $option['desc'] ) ? $option['desc'] : '',
                        'name' => $label,
                        'section' => $section,
                        'size' => isset( $option['size'] ) ? $option['size'] : null,
                        'options' => isset( $option['options'] ) ? $option['options'] : '',
                        'std' => isset( $option['default'] ) ? $option['default'] : '',
                        'sanitize_callback' => isset( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : '',
                        'type' => $type,
                        'placeholder' => isset( $option['placeholder'] ) ? $option['placeholder'] : '',
                        'min' => isset( $option['min'] ) ? $option['min'] : '',
                        'max' => isset( $option['max'] ) ? $option['max'] : '',
                        'step' => isset( $option['step'] ) ? $option['step'] : '',
                        'multiple' => isset( $option['multiple'] ) ? $option['multiple'] : ''
                    );

                    add_settings_field( "{$section}[{$name}]", $label, $callback, $section, $section, $args );
                }
            }

            // creates our settings in the options table
            foreach ( $this->settings_sections as $section) {
                register_setting( $section['id'], $section['id'], array( $this, 'sanitize_options' ) );
            }
        }

        /**
         * Get field description for display
         *
         * @param array   $args settings field args
         */
        public function get_field_description( $args ) {
            if ( ! empty( $args['desc'] ) ) {
                $desc = sprintf( '<p class="description">%s</p>', $args['desc'] );
            } else {
                $desc = '';
            }

            return $desc;
        }

        /**
         * Displays a text field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_text( $args ) {
            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
            $type = isset( $args['type'] ) ? $args['type'] : 'text';
            $placeholder = empty( $args['placeholder'] ) ? '' : ' placeholder="' . $args['placeholder'] . '"';

            $html = sprintf( '<input type="%1$s" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s"%6$s/>', $type, $size, $args['section'], $args['id'], $value, $placeholder);
            $html .= $this->get_field_description( $args );

            echo $html;
        }
        
        /**
         * Render a hidden random field
         *
         * @param array   $args settings field args
         */
        function callback_rand($args) {
            $value = rand();
            $type = 'hidden';

            $html = sprintf('<input type="%1$s" id="%2$s-%3$s" name="%2$s[%3$s]" value="%4$s"/>', $type, $args['section'], $args['id'], $value);

            echo $html;
        }

        /**
         * Displays a url field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_url( $args ) {
            $this->callback_text( $args );
        }

        /**
         * Displays a number field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_number( $args ) {
            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
            $type = isset( $args['type'] ) ? $args['type'] : 'number';
            $placeholder = empty( $args['placeholder'] ) ? '' : ' placeholder="' . $args['placeholder'] . '"';
            $min = empty( $args['min'] ) ? ' min="0"' : ' min="' . $args['min'] . '"';
            $max = empty( $args['max'] ) ? '' : ' max="' . $args['max'] . '"';
            $step = empty( $args['step'] ) ? ' step="0.01"' : ' step="' . $args['step'] . '"';

            $html = sprintf( '<input type="%1$s" class="%2$s-text" id="%3$s[%4$s]" name="%3$s[%4$s]" value="%5$s"%6$s%7$s%8$s%9$s/>', $type, $size, $args['section'], $args['id'], $value, $placeholder, $min, $max, $step);
            $html .= $this->get_field_description( $args );

            echo $html;
        }

        /**
         * Displays a checkbox for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_checkbox( $args ) {

            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );

            $html = '<fieldset>';
            $html .= sprintf( '<label for="wcwp-%1$s[%2$s]">', $args['section'], $args['id'] );
            $html .= sprintf( '<input type="hidden" name="%1$s[%2$s]" value="off" />', $args['section'], $args['id'] );
            $html .= sprintf( '<input type="checkbox" class="checkbox" id="wcwp-%1$s-%2$s" name="%1$s[%2$s]" value="on" %3$s />', $args['section'], $args['id'], checked( $value, 'on', false ) );
            $html .= sprintf( '%1$s</label>', $args['desc'] );
            $html .= '</fieldset>';

            echo $html;
        }

        /**
         * Displays a multicheckbox a settings field
         *
         * @param array   $args settings field args
         */
        function callback_multicheck( $args ) {

            $value = $this->get_option( $args['id'], $args['section'], $args['std'] );
            $html = '<fieldset>';
            $html .= sprintf( '<input type="hidden" name="%1$s[%2$s]" value="" />', $args['section'], $args['id'] );
            foreach ( $args['options'] as $key => $label) {
                $checked = isset( $value[$key] ) ? $value[$key] : '0';
                $html .= sprintf( '<label for="%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key);
                $html .= sprintf( '<input type="checkbox" class="checkbox" id="wcwp-%1$s[%2$s][%3$s]" name="%1$s[%2$s][%3$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked( $checked, $key, false ) );
                $html .= sprintf( '%1$s</label><br>', $label);
            }

            $html .= $this->get_field_description( $args );
            $html .= '</fieldset>';

            echo $html;
        }

        /**
         * Displays a multicheckbox a settings field
         *
         * @param array   $args settings field args
         */
        function callback_radio( $args ) {

            $value = $this->get_option( $args['id'], $args['section'], $args['std'] );
            $html = '<fieldset>';

            foreach ( $args['options'] as $key => $label) {
                $html .= sprintf( '<label for="wcwp-%1$s[%2$s][%3$s]">', $args['section'], $args['id'], $key);
                $html .= sprintf( '<input type="radio" class="radio" id="wcwp-%1$s[%2$s][%3$s]" name="%1$s[%2$s]" value="%3$s" %4$s />', $args['section'], $args['id'], $key, checked( $value, $key, false ) );
                $html .= sprintf( '%1$s</label><br>', $label);
            }

            $html .= $this->get_field_description( $args );
            $html .= '</fieldset>';

            echo $html;
        }

        /**
         * Displays a selectbox for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_select( $args ) {
            
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular-text';
            $multiple = ! empty( $args['multiple'] ) ? ' multiple="true"' : '';
            if ( $multiple){
                $value = $this->get_option( $args['id'], $args['section'], $args['std'] );
                $html = sprintf( '<select class="%1$s" name="%2$s[%3$s][]" id="%2$s-%3$s" %4$s>', $size, $args['section'], $args['id'], $multiple);
            } else{
                $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
                $html = sprintf( '<select class="%1$s" name="%2$s[%3$s]" id="%2$s-%3$s">', $size, $args['section'], $args['id'] );
            }
            
            foreach ( $args['options'] as $key => $label) {
                if ( $multiple){
                    $html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected(in_array( $key, $value ), true, false ), $label);
                } else{
                    $html .= sprintf( '<option value="%s"%s>%s</option>', $key, selected( $value, $key, false ), $label);
                }
            }

            $html .= sprintf( '</select>' );
            $html .= $this->get_field_description( $args );

            echo $html;
        }

        /**
         * Displays a textarea for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_textarea( $args ) {

            $value = esc_textarea( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
            $placeholder = empty( $args['placeholder'] ) ? '' : ' placeholder="' . $args['placeholder'] . '"';

            $html = sprintf( '<textarea rows="5" cols="55" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]"%4$s>%5$s</textarea>', $size, $args['section'], $args['id'], $placeholder, $value );
            $html .= $this->get_field_description( $args );

            echo $html;
        }

        /**
         * Displays a textarea for a settings field
         *
         * @param array   $args settings field args
         * @return string
         */
        function callback_html( $args ) {
            echo $this->get_field_description( $args );
        }

        /**
         * Displays a rich text textarea for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_wysiwyg( $args ) {

            $value = $this->get_option( $args['id'], $args['section'], $args['std'] );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : '500px';

            echo '<div style="max-width: ' . $size . ';">';

            $editor_settings = array(
                'teeny' => true,
                'textarea_name' => $args['section'] . '[' . $args['id'] . ']',
                'textarea_rows' => 10
            );

            if ( isset( $args['options'] ) && is_array( $args['options'] ) ) {
                $editor_settings = array_merge( $editor_settings, $args['options'] );
            }

            wp_editor( $value, $args['section'] . '-' . $args['id'], $editor_settings);

            echo '</div>';

            echo $this->get_field_description( $args );
        }

        /**
         * Displays a file upload field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_file( $args ) {

            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
            $id = $args['section'] . '[' . $args['id'] . ']';
            $label = isset( $args['options']['button_label'] ) ? $args['options']['button_label'] : __( 'Choose File', 'woo-wallet' );

            $html = sprintf( '<input type="text" class="%1$s-text wpsa-url" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );
            $html .= '<input type="button" class="button wpsa-browse" value="' . $label . '" />';
            $html .= $this->get_field_description( $args );

            echo $html;
        }
        
        /**
         * Displays a file upload field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_attachment( $args ) {

            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';
            $id = $args['section'] . '[' . $args['id'] . ']';
            $label = isset( $args['options']['button_label'] ) ? $args['options']['button_label'] : __( 'Choose File', 'woo-wallet' );
            $uploader_title = isset( $args['options']['uploader_title'] ) ? $args['options']['uploader_title'] : __( 'Select', 'woo-wallet' );
            $uploader_button_text = isset( $args['options']['uploader_button_text'] ) ? $args['options']['uploader_button_text'] : __( 'Select', 'woo-wallet' );
            $attachment_src = WC()->plugin_url() . '/assets/images/placeholder.png';
            if ( $value ){
                $attachment_src = wp_get_attachment_url( $value );
            }
            $html = '';
            $html .= '<img class="wpsa-attachment-image" src="'.$attachment_src.'" width="75" />';
            $html .= sprintf( '<input type="hidden" class="%1$s-text wpsa-attachment-id" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );
            $html .= '<input type="button" class="button wpsa-attachment" data-uploader_title="'.$uploader_title.'" data-uploader_button_text="'.$uploader_button_text.'" value="' . $label . '" />';
            $html .= $this->get_field_description( $args );

            echo $html;
        }

        /**
         * Displays a password field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_password( $args ) {

            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

            $html = sprintf( '<input type="password" class="%1$s-text" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s"/>', $size, $args['section'], $args['id'], $value );
            $html .= $this->get_field_description( $args );

            echo $html;
        }

        /**
         * Displays a color picker field for a settings field
         *
         * @param array   $args settings field args
         */
        function callback_color( $args ) {

            $value = esc_attr( $this->get_option( $args['id'], $args['section'], $args['std'] ) );
            $size = isset( $args['size'] ) && ! is_null( $args['size'] ) ? $args['size'] : 'regular';

            $html = sprintf( '<input type="text" class="%1$s-text wp-color-picker-field" id="%2$s[%3$s]" name="%2$s[%3$s]" value="%4$s" data-default-color="%5$s" />', $size, $args['section'], $args['id'], $value, $args['std'] );
            $html .= $this->get_field_description( $args );

            echo $html;
        }

        /**
         * Sanitize callback for Settings API
         *
         * @return mixed
         */
        function sanitize_options( $options) {

            if ( !$options) {
                return $options;
            }

            foreach ( $options as $option_slug => $option_value) {
                $sanitize_callback = $this->get_sanitize_callback( $option_slug);

                // If callback is set, call it
                if ( $sanitize_callback) {
                    $options[$option_slug] = call_user_func( $sanitize_callback, $option_value);
                    continue;
                }
            }

            return $options;
        }

        /**
         * Get sanitization callback for given option slug
         *
         * @param string $slug option slug
         *
         * @return mixed string or bool false
         */
        function get_sanitize_callback( $slug = '' ) {
            if (empty( $slug) ) {
                return false;
            }

            // Iterate over registered fields and see if we can find proper callback
            foreach ( $this->settings_fields as $section => $options) {
                foreach ( $options as $option) {
                    if ( $option['name'] != $slug) {
                        continue;
                    }

                    // Return the callback name
                    return isset( $option['sanitize_callback'] ) && is_callable( $option['sanitize_callback'] ) ? $option['sanitize_callback'] : false;
                }
            }

            return false;
        }

        /**
         * Get the value of a settings field
         *
         * @param string  $option  settings field name
         * @param string  $section the section name this field belongs to
         * @param string  $default default text if it's not found
         * @return string
         */
        function get_option( $option, $section, $default = '' ) {

            $options = get_option( $section);
            
            $option_value = isset( $options[$option] ) && ! empty( $options[$option] ) ? $options[$option] : $default;

            return apply_filters("woo_wallet_get_option_{$section}_{$option}", $option_value);
        }

        /**
         * Show navigations as tab
         *
         * Shows all the settings section labels as tab
         */
        function show_navigation() {
            $html = '<h2 class="nav-tab-wrapper">';

            $count = count( $this->settings_sections);

            // don't show the navigation if only one section exists
            if ( $count === 1 ) {
                return;
            }

            foreach ( $this->settings_sections as $tab) {
                if ( ! isset( $tab['icon'] ) || empty( $tab['icon'] ) ) {
                    $tab['icon'] = 'dashicons-admin-generic';
                }
                $html .= sprintf( '<a href="#%1$s" class="nav-tab" id="%1$s-tab"><span class="dashicons %2$s"></span> %3$s</a>', $tab['id'], $tab['icon'], $tab['title'] );
            }

            $html .= '</h2>';

            echo $html;
        }

        /**
         * Show the section settings forms
         *
         * This function displays every sections in a different form
         */
        function show_forms() {
            ?>
            <div class="metabox-holder">
                <?php foreach ( $this->settings_sections as $form) { ?>
                    <div id="<?php echo $form['id']; ?>" class="group" style="display: none;">
                        <form method="post" action="options.php">
                            <?php
                            do_action( 'woo_wallet_form_top_' . $form['id'], $form);
                            settings_fields( $form['id'] );
                            do_settings_sections( $form['id'] );
                            do_action( 'woo_wallet_form_bottom_' . $form['id'], $form);
                            if ( isset( $this->settings_fields[$form['id']] ) ):
                                ?>
                                <div style="padding-left: 10px">
                                    <?php submit_button(); ?>
                                </div>
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
        function script() {
            $this->_style_fix();
        }

        function _style_fix() {
            global $wp_version;

            if (version_compare( $wp_version, '3.8', '<=' ) ):
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