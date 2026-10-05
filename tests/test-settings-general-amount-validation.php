<?php
/**
 * Settings save: General-section amounts that are impossible at runtime are
 * rejected with a WP_Error and nothing is stored (min top-up above max top-up
 * blocked every top-up; a 150% transfer fee "saved successfully").
 *
 * @package WooWallet\Tests
 */

/**
 * @covers TeraWallet_REST_Settings_Section_Controller::save_section
 */
class Test_Settings_General_Amount_Validation extends WP_UnitTestCase {

	/**
	 * Load the controller; start from a known stored section.
	 */
	public function set_up() {
		parent::set_up();
		require_once WOO_WALLET_ABSPATH . 'includes/api/abstracts/class-terawallet-rest-controller-base.php';
		require_once WOO_WALLET_ABSPATH . 'includes/api/abstracts/class-terawallet-rest-admin-controller-base.php';
		require_once WOO_WALLET_ABSPATH . 'includes/api/abstracts/class-terawallet-rest-settings-controller-base.php';
		require_once WOO_WALLET_ABSPATH . 'includes/api/v1/settings/class-terawallet-rest-settings-section-controller.php';
		update_option( '_wallet_settings_general', array( 'min_topup_amount' => '5' ) );
	}

	/**
	 * POST the General section.
	 *
	 * @param array $values Values (merged over a valid baseline).
	 * @return WP_REST_Response|WP_Error
	 */
	private function save( array $values ) {
		$request = new WP_REST_Request( 'POST', '/terawallet/v1/settings/section' );
		$request->set_param( 'section_id', '_wallet_settings_general' );
		$request->set_param(
			'values',
			array_merge(
				array(
					'is_enable_wallet_topup'    => 'on',
					'is_enable_gateway_charge'  => 'on',
					'gateway_charge_type'       => 'percent',
					'is_enable_wallet_transfer' => 'on',
					'transfer_charge_type'      => 'percent',
				),
				$values
			)
		);
		return ( new TeraWallet_REST_Settings_Section_Controller() )->save_section( $request );
	}

	/**
	 * Assert the save was rejected with $needle and nothing was stored.
	 *
	 * @param WP_REST_Response|WP_Error $response Response.
	 * @param string                    $needle   Expected message fragment.
	 */
	private function assert_rejected( $response, $needle ) {
		$this->assertWPError( $response );
		$this->assertSame( 400, $response->get_error_data()['status'] );
		$this->assertStringContainsString( $needle, $response->get_error_message() );
		$this->assertSame( array( 'min_topup_amount' => '5' ), get_option( '_wallet_settings_general' ), 'Nothing may be saved.' );
	}

	/**
	 * Data: invalid payloads and the message each must produce.
	 *
	 * @return array
	 */
	public function invalid_payloads() {
		return array(
			'min top-up above max'  => array( array( 'min_topup_amount' => '100', 'max_topup_amount' => '50' ), 'cannot be more than' ),
			'negative min top-up'   => array( array( 'min_topup_amount' => '-1' ), 'cannot be negative' ),
			'min transfer above max' => array( array( 'min_transfer_amount' => '20', 'max_transfer_amount' => '10' ), 'cannot be more than' ),
			'150% transfer fee'     => array( array( 'transfer_charge_amount' => '150' ), 'more than 100' ),
			'negative transfer fee' => array( array( 'transfer_charge_amount' => '-2' ), 'cannot be negative' ),
			'150% gateway charge'   => array( array( 'charge_amount_bacs' => '150' ), 'Gateway charge for' ),
			'negative gateway fee'  => array( array( 'charge_amount_bacs' => '-1', 'gateway_charge_type' => 'fixed' ), 'cannot be negative' ),
		);
	}

	/**
	 * @dataProvider invalid_payloads
	 *
	 * @param array  $values Payload.
	 * @param string $needle Message fragment.
	 */
	public function test_invalid_values_are_rejected( array $values, $needle ) {
		$this->assert_rejected( $this->save( $values ), $needle );
	}

	/**
	 * Valid values still save — including the boundaries and "no limit" max.
	 */
	public function test_valid_values_save() {
		$response = $this->save(
			array(
				'min_topup_amount'       => '10',
				'max_topup_amount'       => '10',
				'min_transfer_amount'    => '5',
				'max_transfer_amount'    => '', // Blank max = no limit.
				'transfer_charge_amount' => '100',
				'charge_amount_bacs'     => '2.5',
			)
		);
		$this->assertNotWPError( $response );
		$stored = get_option( '_wallet_settings_general' );
		$this->assertSame( '10', $stored['max_topup_amount'] );
		$this->assertSame( '100', $stored['transfer_charge_amount'] );
	}

	/**
	 * Fixed charges above 100 are fine; a zero max means "no limit".
	 */
	public function test_fixed_charge_and_zero_max_are_allowed() {
		$response = $this->save(
			array(
				'transfer_charge_type'   => 'fixed',
				'transfer_charge_amount' => '150',
				'min_topup_amount'       => '20',
				'max_topup_amount'       => '0',
			)
		);
		$this->assertNotWPError( $response );
	}

	/**
	 * A switched-off group is not validated: its fields are hidden, so a stale
	 * value there must not block saving the rest of the page.
	 */
	public function test_disabled_group_is_not_validated() {
		$response = $this->save(
			array(
				'is_enable_wallet_transfer' => 'off',
				'transfer_charge_amount'    => '150',
			)
		);
		$this->assertNotWPError( $response );
	}
}
