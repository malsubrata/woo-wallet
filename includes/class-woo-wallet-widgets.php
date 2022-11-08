<?php

/**
 * WooWallet top-up widget
 *
 * @since 1.3.0
 */
class Woo_Wallet_Topup extends WP_Widget {

	/**
	 * Sets up a new top-up widget instance.
	 *
	 * @since 1.3.0
	 */
	public function __construct() {
		$widget_ops = array(
			'classname'                   => 'widget_wallet_topup',
			'description'                 => __( 'TeraWallet top-up form for your site.', 'woo-wallet' ),
			'customize_selective_refresh' => true,
		);
		parent::__construct( 'woo-wallet-topup', _x( 'Wallet Top-up', 'Wallet Top-up widget', 'woo-wallet' ), $widget_ops );
	}

	/**
	 * Outputs the content for the wallet top-up widget instance.
	 *
	 * @since 1.3.0
	 *
	 * @param array $args     Display arguments including 'before_title', 'after_title',
	 *                        'before_widget', and 'after_widget'.
	 * @param array $instance Settings for the current Search widget instance.
	 */
	public function widget( $args, $instance ) {
		if ( ! is_user_logged_in() ) {
			return;
		}
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';

		/** This filter is documented in wp-includes/widgets/class-wp-widget-pages.php */
		$title = apply_filters( 'widget_title', $title, $instance, $this->id_base );

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		?>
		<form method="post" action="">
			<div class="woo-wallet-add-amount">
				<?php
				$min_amount = woo_wallet()->settings_api->get_option( 'min_topup_amount', '_wallet_settings_general', 0 );
				$max_amount = woo_wallet()->settings_api->get_option( 'max_topup_amount', '_wallet_settings_general', '' );
				?>
				<p>
					<input type="number" style="max-width: 70%;" step="0.01" min="<?php echo esc_attr( $min_amount ); ?>" max="<?php echo esc_attr( $max_amount ); ?>" name="woo_wallet_balance_to_add" id="woo_wallet_balance_to_add" class="woo-wallet-balance-to-add input-text" placeholder="<?php esc_attr_e( 'Enter amount', 'woo-wallet' ); ?>" required="" />
					<input type="submit" class="button" value="<?php esc_attr_e( 'Add', 'woo-wallet' ); ?>" />
				</p>
				<?php wp_nonce_field( 'woo_wallet_topup', 'woo_wallet_topup' ); ?>
			</div>
		</form>
		<?php
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Outputs the settings form for the wallet top-up widget.
	 *
	 * @since 1.3.0
	 *
	 * @param array $instance Current settings.
	 */
	public function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '' ) );
		$title    = $instance['title'];
		?>
		<p><label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'woo-wallet' ); ?> <input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" /></label></p>
		<?php
	}

	/**
	 * Handles updating settings for the current wallet top-up widget instance.
	 *
	 * @since 1.3.0
	 *
	 * @param array $new_instance New settings for this instance as input by the user via
	 *                            WP_Widget::form().
	 * @param array $old_instance Old settings for this instance.
	 * @return array Updated settings.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance          = $old_instance;
		$new_instance      = wp_parse_args( (array) $new_instance, array( 'title' => '' ) );
		$instance['title'] = sanitize_text_field( $new_instance['title'] );
		return $instance;
	}

}
