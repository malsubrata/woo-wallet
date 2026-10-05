<?php
/**
 * Locked Pro teaser cards on the Dashboard show neutral placeholders, never
 * figures that read as real store data (12.4%, 94 days) or a hard-coded ₹ on
 * a store whose base currency is something else.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers Woo_Wallet_Reports::pro_slot_copy
 */
class Test_Reports_Pro_Teaser_Placeholders extends WP_UnitTestCase {

	/**
	 * Teaser values on a GBP store.
	 *
	 * @return string[] slot id => sample_v
	 */
	private function sample_values() {
		update_option( 'woocommerce_currency', 'GBP' );
		require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-reports-data.php';
		require_once WOO_WALLET_ABSPATH . 'includes/admin/class-woo-wallet-reports.php';
		$reports = new class() extends Woo_Wallet_Reports {
			public function copy() {
				return $this->pro_slot_copy();
			}
		};
		return wp_list_pluck( $reports->copy(), 'sample_v' );
	}

	/**
	 * No fake figures, no foreign currency symbol; the trend card uses the base symbol.
	 */
	public function test_placeholders_are_neutral() {
		$values = $this->sample_values();
		$all    = implode( ' | ', $values );

		$this->assertDoesNotMatchRegularExpression( '/\d/', $all, 'Teaser values must not contain digits that read as real data.' );
		$this->assertStringNotContainsString( '₹', $all );
		$this->assertSame( '£ — /mo', $values['trend'] );
	}
}
