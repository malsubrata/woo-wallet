<?php
/**
 * TeraWallet sell content action file.
 *
 * @package Standalonetech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}
/**
 * TeraWallet sell content action class
 */
class Woo_Wallet_Action_Sell_Content extends WooWalletAction {
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id           = 'sell_content';
		$this->action_title = __( 'Sell Content', 'woo-wallet' );
		$this->description  = __( 'Sell your content using wallet balance. Use shortcode <code>[tw-sell-content]</code> to sell part of a content.', 'woo-wallet' );
		$this->init_form_fields();
		$this->init_settings();
		// Actions.
		add_filter( 'woocommerce_generate_wysiwyg_html', array( $this, 'generate_wysiwyg_html' ), 10, 2 );
		if ( $this->is_enabled() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
			add_action( 'save_post', array( $this, 'save' ) );
			add_filter( 'the_content', array( $this, 'validate_content_for_sale' ) );
			add_action( 'template_redirect', array( $this, 'handle_purchase_content' ) );
			add_shortcode( 'tw-sell-content', array( $this, 'tw_sell_content_shortcode_callback' ) );
		}
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'                     => array(
				'title'   => __( 'Enable/Disable', 'woo-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable sell Content action', 'woo-wallet' ),
				'default' => 'no',
			),
			'post_types'                  => array(
				'title'       => __( 'Which post type(s) content do you want to sell?', 'woo-wallet' ),
				'type'        => 'multiselect',
				'class'       => 'wc-enhanced-select',
				'css'         => 'min-width: 350px;',
				'desc_tip'    => true,
				'description' => __( 'This option lets you to choose post types which you want to sell.', 'woo-wallet' ),
				'options'     => self::get_post_types_options(),
				'default'     => array(),
			),
			'default_amount'              => array(
				'title'       => __( 'Default Amount', 'woo-wallet' ),
				'type'        => 'price',
				'description' => __( 'Enter amount which will be from user wallet when purchase paid content.', 'woo-wallet' ),
				'default'     => '10',
				'desc_tip'    => true,
			),
			'profit_share'                => array(
				'title'             => __( 'Profit Share', 'woo-wallet' ),
				'description'       => __( 'Option to pay a percentage of each sale with the content author.', 'woo-wallet' ),
				'type'              => 'number',
				'desc_tip'          => true,
				'default'           => 0,
				'custom_attributes' => array(
					'min' => 0,
					'max' => 100,
				),
			),
			'expiration'                  => array(
				'title'             => __( 'Expiration', 'woo-wallet' ),
				'description'       => __( 'Option to automatically expire purchases after certain number of Hour(s). Use zero to disable..', 'woo-wallet' ),
				'type'              => 'number',
				'desc_tip'          => true,
				'default'           => 0,
				'custom_attributes' => array(
					'min' => 0,
				),
			),
			'purchase_description'        => array(
				'title'       => __( 'Purchase Description', 'woo-wallet' ),
				'type'        => 'text',
				'description' => __( 'Purchase transaction description that will display as transaction note. Available Tags<code>#title#</code> <code>#link_with_title#</code>', 'woo-wallet' ),
				'default'     => __( 'Purchase of #title#', 'woo-wallet' ),
				'desc_tip'    => false,
			),
			'sell_description'            => array(
				'title'       => __( 'Selling Description', 'woo-wallet' ),
				'type'        => 'text',
				'description' => __( 'Selling transaction description that will display as transaction note. Available Tags<code>#title#</code> <code>#link_with_title#</code>', 'woo-wallet' ),
				'default'     => __( 'Sale of #title#', 'woo-wallet' ),
				'desc_tip'    => false,
			),
			'button_lable'                => array(
				'title'       => __( 'Button Label', 'woo-wallet' ),
				'type'        => 'text',
				'description' => __( 'Enter pay button lable. Available Tags<code>#price#</code>', 'woo-wallet' ),
				'default'     => __( 'Pay #price#', 'woo-wallet' ),
				'desc_tip'    => true,
			),
			'button_css_class'            => array(
				'title'       => __( 'Button CSS Classes', 'woo-wallet' ),
				'type'        => 'text',
				'description' => __( 'Enter custom button CSS Classes', 'woo-wallet' ),
				'default'     => __( 'button button-primary', 'woo-wallet' ),
				'desc_tip'    => true,
			),
			'purchase_template_title'     => array(
				'title'       => __( 'Purchase Template', 'woo-wallet' ),
				'type'        => 'title',
				'description' => __( 'The content will be replaced with this template when viewed by a user that has not paid for the content but can afford to pay.', 'woo-wallet' ),
			),
			'purchase_template'           => array(
				'title'       => '',
				'type'        => 'wysiwyg',
				'default'     => '<h3>Premium Content</h3>
                Buy access to this content.
                #buy_button#',
				'description' => __( 'Available Tags: <code>#buy_button#</code> <code>#price#</code> <code>#balance#</code>', 'woo-wallet' ),
			),
			'insufficient_funds_title'    => array(
				'title'       => __( 'Insufficient Funds Template', 'woo-wallet' ),
				'type'        => 'title',
				'description' => __( 'The content will be replaced with this template when viewed by a user that has not paid for the content and can not afford to pay.', 'woo-wallet' ),
			),
			'insufficient_funds_template' => array(
				'title'       => '',
				'type'        => 'wysiwyg',
				'default'     => '<h3>Premium Content</h3>
                Buy access to this content.
                <strong>Insufficient Funds</strong>',
				'description' => __( 'Available Tags: <code>#price#</code> <code>#balance#</code>', 'woo-wallet' ),
			),
			'visitors_template_title'     => array(
				'title'       => __( 'Visitors Template', 'woo-wallet' ),
				'type'        => 'title',
				'description' => __( 'The content will be replaced with this template when viewed by someone who is not logged in on your website.', 'woo-wallet' ),
			),
			'visitors_template'           => array(
				'title'       => '',
				'type'        => 'wysiwyg',
				'default'     => '<h3>Premium Content</h3>
                Login to buy access to this content.',
				'description' => __( 'Available Tags: <code>#price#</code>', 'woo-wallet' ),
			),
		);
	}
	/**
	 * Get all post type object.
	 *
	 * @return array
	 */
	public static function get_post_types_options() : array {
		$post_types_objects = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);
		$options            = array();
		$exclude_list       = array( 'attachment', 'elementor_library' );
		foreach ( $post_types_objects as $cpt_slug => $post_type ) {
			if ( in_array( $cpt_slug, $exclude_list, true ) ) {
				continue;
			}
			$options[ $cpt_slug ] = $post_type->labels->name;
		}
		return $options;
	}
	/**
	 * Generate wysiwyg Input HTML.
	 *
	 * @param string $key Field key.
	 * @param array  $data Field data.
	 * @since  1.0.0
	 * @return string
	 */
	public function generate_wysiwyg_html( $key, $data ) : string {
		$field_key = $this->get_field_key( $key );
		$defaults  = array(
			'title'             => '',
			'disabled'          => false,
			'class'             => '',
			'css'               => '',
			'placeholder'       => '',
			'type'              => 'text',
			'desc_tip'          => false,
			'description'       => '',
			'custom_attributes' => array(
				'teeny'         => true,
				'textarea_rows' => 10,
			),
		);

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?> <?php echo $this->get_tooltip_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></label>
			</th>
			<td class="forminp">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>
					<?php
					wp_editor(
						$this->get_option( $key ),
						$field_key,
						$data['custom_attributes']
					);
					?>
					<?php echo $this->get_description_html( $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				</fieldset>
			</td>
		</tr>
		<?php
		return ob_get_clean();
	}

	/**
	 * Adds the meta box container.
	 *
	 * @param string $post_type $post_type.
	 */
	public function add_meta_box( $post_type ) {
		// Limit meta box to certain post types.
		$post_types = (array) $this->settings['post_types'];

		if ( in_array( $post_type, $post_types, true ) ) {
			add_meta_box(
				'tw_sell_content',
				__( 'Sell this content', 'woo-wallet' ),
				array( $this, 'render_tw_sell_meta_box_content' ),
				$post_type,
				'advanced',
				'high'
			);
		}
	}

	/**
	 * Save the meta when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function save( $post_id ) {
		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */

		// Check if our nonce is set.
		if ( ! isset( $_POST['tw_sell_content_meta_box_nonce'] ) ) {
			return $post_id;
		}

		$nonce = $_POST['tw_sell_content_meta_box_nonce']; // phpcs:ignore WordPress.Security

		// Verify that the nonce is valid.
		if ( ! wp_verify_nonce( $nonce, 'tw_sell_content_meta_box' ) ) {
			return $post_id;
		}

		/*
		 * If this is an autosave, our form has not been submitted,
		 * so we don't want to do anything.
		 */
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $post_id;
		}

		/* OK, it's safe for us to save the data now. */
		if ( isset( $_POST['tw_sell_content'] ) ) {
			update_post_meta( $post_id, '_tw_sell_content', '1' );
		} else {
			delete_post_meta( $post_id, '_tw_sell_content' );
		}
		if ( isset( $_POST['tw_sell_content_amount'] ) ) {
			update_post_meta( $post_id, '_tw_sell_content_amount', sanitize_text_field( $_POST['tw_sell_content_amount'] ) ); // phpcs:ignore WordPress.Security
		}
	}
	/**
	 * Cheeck is user is administrator.
	 *
	 * @return boolean
	 */
	public static function is_user_administrator() {
		$user = wp_get_current_user();
		if ( in_array( 'administrator', (array) $user->roles, true ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function render_tw_sell_meta_box_content( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'tw_sell_content_meta_box', 'tw_sell_content_meta_box_nonce' );

		// Use get_post_meta to retrieve an existing value from the database.
		$tw_sell_content = get_post_meta( $post->ID, '_tw_sell_content', true );
		// Use get_post_meta to retrieve an existing value from the database.
		$tw_sell_content_amount = get_post_meta( $post->ID, '_tw_sell_content_amount', true );

		// Display the form, using the current value.
		?>
		<div class="hcf_box">
			<style scoped>
				.hcf_box{
					display: grid;
					grid-template-columns: max-content 1fr;
					grid-row-gap: 10px;
					grid-column-gap: 20px;
				}
				.hcf_field{
					display: contents;
				}
				.hcf_field input[type=number]{
					width: 25%;
				}
			</style>
			<p class="meta-options hcf_field">
				<label for="tw_sell_content"><?php echo esc_html_e( 'Enable/Disable', 'woo-wallet' ); ?></label>
				<input type="checkbox" name="tw_sell_content" id="tw_sell_content" value="1" <?php checked( $tw_sell_content, '1' ); ?> />
			</p>
			<p class="meta-options hcf_field">
				<label for="tw_sell_content_amount"><?php echo esc_html_e( 'Price', 'woo-wallet' ); ?></label>
				<input type="number" min="0" step="any" name="tw_sell_content_amount" id="tw_sell_content_amount" value="<?php echo esc_attr( $tw_sell_content_amount ); ?>" />
			</p>
		</div>
		<?php
	}
	/**
	 * Render post content.
	 *
	 * @param string $content content.
	 * @return string
	 */
	public function validate_content_for_sale( $content ) : string {
		global $post;
		$user_id         = get_current_user_id();
		$post_types      = (array) $this->settings['post_types'];
		$tw_sell_content = get_post_meta( $post->ID, '_tw_sell_content', true );
		$is_owner        = ( (int) $post->post_author === $user_id ) ? true : false;
		if ( ! $tw_sell_content || ! in_array( $post->post_type, $post_types, true ) || $is_owner || self::is_user_administrator() ) {
			return $content;
		}
		$tw_sell_content_amount = get_post_meta( $post->ID, '_tw_sell_content_amount', true ) ? floatval( get_post_meta( $post->ID, '_tw_sell_content_amount', true ) ) : floatval( $this->settings['default_amount'] );
		if ( ! $tw_sell_content_amount ) {
			return $content;
		}

		if ( ! is_user_logged_in() ) {
			return $this->render_settings_template( 'visitors_template' );
		}

		if ( ! $this->has_paid( $tw_sell_content_amount ) ) {
			if ( woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) >= $tw_sell_content_amount ) {
				return $this->render_settings_template( 'purchase_template' );
			} else {
				return $this->render_settings_template( 'insufficient_funds_template' );
			}
		}

		return $content;
	}
	/**
	 * Render settings template
	 *
	 * @param string $template_option_name template_option_name.
	 * @param float  $amount amount.
	 * @param string $id id.
	 * @return string
	 */
	private function render_settings_template( $template_option_name, $amount = 0, $id = 'tw-sell-content' ) : string {
		global $post;
		$content = $this->settings[ $template_option_name ];
		if ( ! $amount ) {
			$tw_sell_content_amount = get_post_meta( $post->ID, '_tw_sell_content_amount', true ) ? get_post_meta( $post->ID, '_tw_sell_content_amount', true ) : $this->settings['default_amount'];
		} else {
			$tw_sell_content_amount = $amount;
		}

		$content = str_replace( '#buy_button#', $this->render_buy_form( $tw_sell_content_amount, $id ), $content );
		$content = str_replace( '#price#', wc_price( $tw_sell_content_amount ), $content );
		$content = str_replace( '#balance#', woo_wallet()->wallet->get_wallet_balance(), $content );
		return do_shortcode( $content );
	}
	/**
	 * Render buy button
	 *
	 * @param float  $amount amount.
	 * @param string $id id.
	 * @return string
	 */
	private function render_buy_form( $amount, $id ) : string {
		global $post;
		$user_id = get_current_user_id();
		ob_start();
		?>
		<form method="post">
			<?php wp_nonce_field( 'tw_buy_content_nonce_' . $amount, 'tw_buy_content_nonce' ); ?>
			<input type="hidden" name="amount" value="<?php echo esc_attr( $amount ); ?>">
			<input type="hidden" name="transient" value="<?php echo esc_attr( md5( 'tw-sell-content' . $post->ID . $user_id . $amount ) ); ?>">
			<button type="submit" class="<?php echo esc_attr( $this->settings['button_css_class'] ); ?>"><?php echo esc_html( $this->settings['button_lable'] ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}
	/**
	 * Check if current user paid for the content
	 *
	 * @param float  $amount amount.
	 * @param string $id id.
	 * @return boolean
	 */
	private function has_paid( $amount, $id = 'tw-sell-content' ) : bool {
		global $post;
		$user_id   = get_current_user_id();
		$transient = md5( $id . $post->ID . $user_id . $amount );
		return get_transient( $transient ) ? true : false;
	}
	/**
	 * Handle purchase of content
	 *
	 * @return void
	 */
	public function handle_purchase_content() : void {
		global $post;
		if ( isset( $_POST['tw_buy_content_nonce'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$amount = isset( $_POST['amount'] ) ? sanitize_text_field( wp_unslash( $_POST['amount'] ) ) : 0;
			if ( wp_verify_nonce( wp_unslash( $_POST['tw_buy_content_nonce'] ), 'tw_buy_content_nonce_' . $amount ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				$user_id                = get_current_user_id();
				$transient              = isset( $_POST['transient'] ) ? sanitize_text_field( wp_unslash( $_POST['transient'] ) ) : md5( 'tw-sell-content' . $post->ID . $user_id . $amount );
				$tw_sell_content_amount = floatval( $amount );
				$title                  = get_the_title();
				$post_link              = get_permalink();
				$post_author            = (int) $post->post_author;
				$profit_share           = $this->settings['profit_share'];
				$purchase_description   = str_replace( array( '#title#', '#link_with_title#' ), array( $title, '<a href="' . $post_link . '">' . $title . '</a>' ), $this->settings['purchase_description'] );
				$sell_description       = str_replace( array( '#title#', '#link_with_title#' ), array( $title, '<a href="' . $post_link . '">' . $title . '</a>' ), $this->settings['sell_description'] );
				$transaction_id         = woo_wallet()->wallet->debit( $user_id, $tw_sell_content_amount, $purchase_description );
				$expiration             = intval( $this->settings['expiration'] );
				if ( $transaction_id ) {
					if ( $profit_share ) {
						$profit = $tw_sell_content_amount * $profit_share / 100;
						woo_wallet()->wallet->credit( $post_author, $profit, $sell_description );
					}
					if ( $expiration ) {
						set_transient( $transient, true, $expiration * DAY_IN_SECONDS );
					} else {
						set_transient( $transient, true );
					}
				}
			} else {
				wc_add_notice( __( 'Cheatin&#8217; huh?', 'woo-wallet' ), 'error' );
			}
		}
	}
	/**
	 * Render sell content shortcode.
	 *
	 * @param array  $atts atts.
	 * @param string $content content.
	 * @return string
	 */
	public function tw_sell_content_shortcode_callback( $atts, $content = null ) {
		global $post;
		$user_id = get_current_user_id();
		$atts    = shortcode_atts(
			array(
				'amount' => floatval( $this->settings['default_amount'] ),
				'id'     => 'tw-sell-content',
			),
			$atts,
			'tw-sell-content'
		);
		$amount  = floatval( $atts['amount'] );
		if ( ! $amount ) {
			return do_shortcode( $content );
		}

		$is_owner = ( (int) $post->post_author === $user_id ) ? true : false;
		if ( $is_owner || self::is_user_administrator() ) {
			return do_shortcode( $content );
		}

		if ( ! is_user_logged_in() ) {
			return $this->render_settings_template( 'visitors_template', $amount, $atts['id'] );
		}

		if ( ! $this->has_paid( $amount, $atts['id'] ) ) {
			if ( woo_wallet()->wallet->get_wallet_balance( get_current_user_id(), 'edit' ) >= $amount ) {
				return $this->render_settings_template( 'purchase_template', $amount, $atts['id'] );
			} else {
				return $this->render_settings_template( 'insufficient_funds_template', $amount, $atts['id'] );
			}
		}
		return do_shortcode( $content );
	}

}

