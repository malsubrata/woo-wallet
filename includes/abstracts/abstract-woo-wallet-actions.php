<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class WooWalletAction extends WC_Settings_API {

	/**
	 * Yes or no based on whether the method is enabled.
	 *
	 * @var string
	 */
	public $enabled = 'no';

	/**
	 * Gateway title.
	 *
	 * @var string
	 */
	public $action_title = '';

	/**
	 * Gateway ID
	 *
	 * @var string
	 */
	public $id = '';

	/**
	 * Action description
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * The plugin ID. Used for option names.
	 *
	 * @var string
	 */
	public $plugin_id = 'woo_wallet_';

	/**
	 * Return the title for admin screens.
	 *
	 * @return string
	 */
	public function get_action_title() {
		return apply_filters( 'woo_wallet_gateway_action_title', $this->action_title, $this );
	}

	/**
	 * Return the id for admin screens.
	 *
	 * @return string
	 */
	public function get_action_id() {
		return $this->id;
	}
	/**
	 * Get Action description.
	 */
	public function get_action_description() {
		return apply_filters( 'woo_wallet_action_description', $this->description, $this );
	}
	/**
	 * Check if is enabled.
	 */
	public function is_enabled() {
		return 'yes' === $this->enabled ? true : false;
	}
	/**
	 * Populate $this->settings from the unified `_wallet_settings_actions`
	 * option, falling back to the legacy per-action option for back-compat.
	 *
	 * In the merged option, every field is stored under `{$this->id}__{$key}`;
	 * this method strips the prefix so action subclasses can keep reading
	 * `$this->settings['amount']` unchanged. If no merged keys are present
	 * (fresh install or third-party action that still writes its own option),
	 * the parent WC_Settings_API loader is used.
	 */
	public function init_settings() {
		$merged    = get_option( '_wallet_settings_actions', array() );
		$prefix    = $this->id . '__';
		$prefix_ln = strlen( $prefix );
		$extracted = array();

		if ( is_array( $merged ) ) {
			foreach ( $merged as $key => $value ) {
				if ( 0 === strpos( $key, $prefix ) ) {
					$extracted[ substr( $key, $prefix_ln ) ] = $value;
				}
			}
		}

		if ( ! empty( $extracted ) ) {
			$this->init_form_fields();
			$this->settings = array();
			$form_fields    = $this->get_form_fields();
			if ( is_array( $form_fields ) ) {
				foreach ( $form_fields as $key => $field ) {
					$this->settings[ $key ] = array_key_exists( $key, $extracted )
						? $extracted[ $key ]
						: ( isset( $field['default'] ) ? $field['default'] : '' );
				}
			} else {
				$this->settings = $extracted;
			}
		} else {
			parent::init_settings();
		}

		$this->enabled = ! empty( $this->settings['enabled'] ) && 'yes' === $this->settings['enabled'] ? 'yes' : 'no';
	}

}
