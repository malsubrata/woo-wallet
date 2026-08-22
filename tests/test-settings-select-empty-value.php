<?php
/**
 * Settings save: empty scalar select values.
 *
 * The React settings app POSTs every field in a section, using the field's
 * declared `default` for any field the admin never touched
 * (`src/admin/settings/hooks/useSettings.js`). A `select` field with no
 * `default` in its schema is therefore sent as an empty string, while the
 * browser shows the first option as if it were selected — the stored value and
 * the visible label disagree. `cashback_type` is the field this bit.
 *
 * The save controller must not persist an empty value for a select whose
 * options are a fixed list, regardless of what the client sends.
 *
 * The read side must keep masking already-persisted empty values: existing
 * stores have `cashback_type => ''` on disk and rely on it resolving to
 * `percent`. Flipping that to `fixed` would silently change every cashback
 * amount on those stores.
 *
 * @package WooWallet\Tests
 */

/**
 * @covers TeraWallet_REST_Settings_Section_Controller::save_section
 */
class Test_Settings_Select_Empty_Value extends WP_UnitTestCase {

	/**
	 * Load the REST settings controller under test.
	 */
	public function set_up() {
		parent::set_up();
		require_once WOO_WALLET_ABSPATH . 'includes/api/abstracts/class-terawallet-rest-controller-base.php';
		require_once WOO_WALLET_ABSPATH . 'includes/api/abstracts/class-terawallet-rest-admin-controller-base.php';
		require_once WOO_WALLET_ABSPATH . 'includes/api/abstracts/class-terawallet-rest-settings-controller-base.php';
		require_once WOO_WALLET_ABSPATH . 'includes/api/v1/settings/class-terawallet-rest-settings-section-controller.php';
	}

	/**
	 * Save a set of credit-section values through the real save controller.
	 *
	 * @param array $values Field values as the React app would POST them.
	 * @return array Persisted section option.
	 */
	private function save_credit_section( array $values ) {
		$request = new WP_REST_Request( 'POST', '/terawallet/v1/settings/section' );
		$request->set_param( 'section_id', '_wallet_settings_credit' );
		$request->set_param( 'values', $values );

		$controller = new TeraWallet_REST_Settings_Section_Controller();
		$response   = $controller->save_section( $request );

		$this->assertNotWPError( $response, 'Section save must succeed.' );

		return (array) get_option( '_wallet_settings_credit', array() );
	}

	/**
	 * The exact payload the settings UI sends for an untouched cashback type.
	 */
	public function test_empty_cashback_type_is_not_persisted() {
		$stored = $this->save_credit_section(
			array(
				'is_enable_cashback_reward_program' => 'on',
				'cashback_type'                     => '',
				'cashback_amount'                   => '10',
			)
		);

		$this->assertSame(
			'percent',
			$stored['cashback_type'],
			'An empty select value must fall back to the field default instead of being stored.'
		);
	}

	/**
	 * `cashback_rule` has the same shape and the same missing default.
	 */
	public function test_empty_cashback_rule_is_not_persisted() {
		$stored = $this->save_credit_section(
			array(
				'is_enable_cashback_reward_program' => 'on',
				'cashback_rule'                     => '',
			)
		);

		$this->assertSame(
			'cart',
			$stored['cashback_rule'],
			'An empty select value must fall back to the field default instead of being stored.'
		);
	}

	/**
	 * A real selection must survive untouched.
	 */
	public function test_valid_select_value_is_persisted_verbatim() {
		$stored = $this->save_credit_section(
			array(
				'cashback_type' => 'fixed',
				'cashback_rule' => 'product',
			)
		);

		$this->assertSame( 'fixed', $stored['cashback_type'], 'A chosen option must be stored as chosen.' );
		$this->assertSame( 'product', $stored['cashback_rule'], 'A chosen option must be stored as chosen.' );
	}

	/**
	 * A value outside the option list is not a value this field can hold.
	 */
	public function test_unknown_select_value_falls_back_to_default() {
		$stored = $this->save_credit_section( array( 'cashback_type' => 'bogus_unit' ) );

		$this->assertSame(
			'percent',
			$stored['cashback_type'],
			'A value that is not one of the field options must not be persisted.'
		);
	}

	/**
	 * The fallback must be the declared default, not "whichever option is first".
	 */
	public function test_fallback_uses_the_declared_default_not_the_first_option() {
		add_filter(
			'woo_wallet_settings_fields',
			function ( $fields ) {
				foreach ( $fields['_wallet_settings_credit'] as $i => $field ) {
					if ( 'cashback_type' === $field['name'] ) {
						$fields['_wallet_settings_credit'][ $i ]['options'] = array(
							'fixed'   => 'Fixed',
							'percent' => 'Percentage',
						);
					}
				}
				return $fields;
			}
		);

		$stored = $this->save_credit_section( array( 'cashback_type' => '' ) );

		$this->assertSame(
			'percent',
			$stored['cashback_type'],
			'The declared default wins even when it is not the first option.'
		);

		remove_all_filters( 'woo_wallet_settings_fields' );
	}

	/**
	 * A multiselect legitimately holds an empty selection — leave it alone.
	 */
	public function test_empty_multiselect_is_left_empty() {
		$stored = $this->save_credit_section( array( 'exclude_role' => array() ) );

		$this->assertSame(
			array(),
			$stored['exclude_role'],
			'Selecting no roles is a real choice and must not be overwritten by a default.'
		);
	}

	/**
	 * A free-text field may legitimately be empty.
	 */
	public function test_empty_non_select_field_is_left_empty() {
		$stored = $this->save_credit_section( array( 'max_cashback_amount' => '' ) );

		$this->assertSame(
			'',
			$stored['max_cashback_amount'],
			'Only select fields with a fixed option list are constrained.'
		);
	}

	/**
	 * Read-side masking must not change: stores already hold `cashback_type = ''`
	 * and every one of them is computing percentage cashback today.
	 */
	public function test_persisted_empty_cashback_type_still_reads_as_percent() {
		update_option(
			'_wallet_settings_credit',
			array(
				'is_enable_cashback_reward_program' => 'on',
				'cashback_type'                     => '',
				'cashback_amount'                   => '5',
			)
		);

		$this->assertSame(
			'percent',
			woo_wallet()->settings_api->get_option( 'cashback_type', '_wallet_settings_credit', 'percent' ),
			'A legacy empty value must keep resolving to percent, never to fixed.'
		);
	}
}
