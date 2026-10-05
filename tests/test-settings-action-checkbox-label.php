<?php
/**
 * Actions tab: a WC_Settings_API checkbox's own `label` (its explanation) is
 * shown as the helper text in the React settings app, instead of being
 * dropped — e.g. Daily visits → First purchase.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Settings::get_actions_settings_fields
 */
class Test_Settings_Action_Checkbox_Label extends WP_UnitTestCase {

	/**
	 * Action checkbox fields keyed by flattened name.
	 *
	 * @return array
	 */
	private function checkboxes() {
		require_once WOO_WALLET_ABSPATH . 'includes/class-woo-wallet-settings.php';
		$settings = new Woo_Wallet_Settings( woo_wallet()->settings_api );
		$out      = array();
		foreach ( $settings->get_actions_settings_fields() as $field ) {
			if ( 'checkbox' === ( $field['type'] ?? '' ) ) {
				$out[ $field['name'] ] = $field;
			}
		}
		return $out;
	}

	/**
	 * The checkbox label becomes the helper text; the title stays the label.
	 */
	public function test_checkbox_label_becomes_helper_text() {
		$fields = $this->checkboxes();

		$this->assertSame( 'First purchase', $fields['daily_visits__require_paid_order']['label'] );
		$this->assertSame( 'Only reward customers with at least one paid order', $fields['daily_visits__require_paid_order']['desc'] );
		$this->assertSame( 'Enable credit for daily visits.', $fields['daily_visits__enabled']['desc'] );
	}

	/**
	 * A label identical to the title is not repeated underneath it.
	 */
	public function test_label_equal_to_title_is_not_repeated() {
		$fields = $this->checkboxes();
		$this->assertSame( '', $fields['referrals__enabled']['desc'] );
	}
}
