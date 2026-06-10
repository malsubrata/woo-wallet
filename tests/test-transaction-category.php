<?php
/**
 * Transaction category column tests.
 *
 * Covers the 1.6.3 promotion of semantic transaction kinds from `_type`
 * meta to a first-class `category` column on `woo_wallet_transactions`:
 *
 *  - credit/debit/transfer all persist the column on write
 *  - the legacy `for` arg aliases correctly (`credit_purchase` → `topup`,
 *    `purchase` → `partial_payment`)
 *  - the renderer substitutes tokens and falls back to the caller's
 *    `details` when no template is configured
 *  - the 1.6.3 backfill migration populates the column from legacy `_type`
 *    meta values, including untagged transfers
 *
 * @package WooWallet\Tests
 */

class Test_Transaction_Category extends WP_UnitTestCase {

	private $user_id;
	private $other_user_id;

	public function set_up() {
		parent::set_up();
		$this->user_id       = self::factory()->user->create( array( 'role' => 'customer' ) );
		$this->other_user_id = self::factory()->user->create( array( 'role' => 'customer' ) );
		// Clear any leftover templates from previous tests.
		delete_option( '_wallet_settings_transaction_descriptions' );
	}

	private function read_category( $transaction_id ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT category FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = %d",
				$transaction_id
			)
		);
	}

	private function read_details( $transaction_id ) {
		global $wpdb;
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT details FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE transaction_id = %d",
				$transaction_id
			)
		);
	}

	/**
	 * A credit with an explicit `category` arg persists that slug.
	 */
	public function test_credit_persists_explicit_category() {
		$transaction_id = woo_wallet()->wallet->credit(
			$this->user_id,
			25.00,
			'Manual top-up',
			array( 'category' => 'topup' )
		);

		$this->assertIsInt( $transaction_id );
		$this->assertSame( 'topup', $this->read_category( $transaction_id ) );
	}

	/**
	 * Legacy `for` arg aliases `credit_purchase` to canonical `topup`.
	 */
	public function test_credit_legacy_for_credit_purchase_aliases_to_topup() {
		$transaction_id = woo_wallet()->wallet->credit(
			$this->user_id,
			10.00,
			'Legacy credit',
			array( 'for' => 'credit_purchase' )
		);

		$this->assertSame( 'topup', $this->read_category( $transaction_id ) );
	}

	/**
	 * Since 1.6.4 the legacy `for => purchase` arg maps to its own first-class
	 * `purchase` category (it is no longer aliased to `partial_payment`). The
	 * "total spent" aggregations count both categories, so the split is
	 * transparent there.
	 */
	public function test_debit_legacy_for_purchase_maps_to_purchase_category() {
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'Seed' );
		$transaction_id = woo_wallet()->wallet->debit(
			$this->user_id,
			30.00,
			'Legacy debit',
			array( 'for' => 'purchase' )
		);

		$this->assertSame( 'purchase', $this->read_category( $transaction_id ) );
	}

	/**
	 * A credit with neither `category` nor `for` falls back to `other`.
	 */
	public function test_credit_without_category_falls_back_to_other() {
		$transaction_id = woo_wallet()->wallet->credit( $this->user_id, 5.00, 'Plain' );
		$this->assertSame( 'other', $this->read_category( $transaction_id ) );
	}

	/**
	 * A transfer tags both legs with `transfer`.
	 */
	public function test_transfer_tags_both_legs_with_transfer_category() {
		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'Seed sender' );

		$result = woo_wallet()->wallet->transfer(
			$this->user_id,
			$this->other_user_id,
			20.00,
			'Sent',
			'Received'
		);

		$this->assertIsArray( $result );
		$this->assertSame( 'transfer', $this->read_category( $result['debit'] ) );
		$this->assertSame( 'transfer', $this->read_category( $result['credit'] ) );
	}

	/**
	 * The token renderer substitutes known tokens and leaves unknowns intact.
	 */
	public function test_renderer_substitutes_tokens_and_leaves_unknowns() {
		$out = woo_wallet_render_transaction_template(
			'Hello {user_name}, refund of {amount} {currency} for order #{order_id}. {unknown_token} stays.',
			array(
				'user_name' => 'Alice',
				'amount'    => '12.50',
				'currency'  => 'USD',
				'order_id'  => '42',
			)
		);

		$this->assertStringContainsString( 'Hello Alice', $out );
		$this->assertStringContainsString( '12.50 USD', $out );
		$this->assertStringContainsString( 'order #42', $out );
		$this->assertStringContainsString( '{unknown_token} stays.', $out );
	}

	/**
	 * When a category template is configured, the rendered string replaces the
	 * caller's `details` string on the persisted row.
	 */
	public function test_configured_template_overrides_caller_details() {
		update_option(
			'_wallet_settings_transaction_descriptions',
			array(
				'topup' => 'Funded {amount} {currency} ({original_details})',
			)
		);

		$transaction_id = woo_wallet()->wallet->credit(
			$this->user_id,
			25.00,
			'Caller-supplied details',
			array( 'category' => 'topup', 'currency' => 'USD' )
		);

		$details = $this->read_details( $transaction_id );
		$this->assertStringStartsWith( 'Funded ', $details );
		$this->assertStringContainsString( 'USD', $details );
		$this->assertStringContainsString( 'Caller-supplied details', $details );
	}

	/**
	 * With no template configured the caller-supplied details are preserved
	 * verbatim (no token substitution applied).
	 */
	public function test_no_template_preserves_caller_details() {
		$transaction_id = woo_wallet()->wallet->credit(
			$this->user_id,
			5.00,
			'Plain details {amount}',
			array( 'category' => 'cashback' )
		);

		$this->assertSame( 'Plain details {amount}', $this->read_details( $transaction_id ) );
	}

	/**
	 * The 1.6.3 backfill migration translates legacy `_type` meta to the new
	 * `category` column. We force a row's column to the schema default `'other'`
	 * and verify the migration callback fills it in based on the meta value.
	 */
	public function test_163_migration_backfills_category_from_legacy_meta() {
		global $wpdb;

		// Insert a row via the API, then strip the column back to 'other' and
		// add the legacy meta the backfill scans for.
		$transaction_id = woo_wallet()->wallet->credit(
			$this->user_id,
			15.00,
			'Pretending to be pre-1.6.3'
		);
		$wpdb->update(
			"{$wpdb->base_prefix}woo_wallet_transactions",
			array( 'category' => 'other' ),
			array( 'transaction_id' => $transaction_id )
		);
		update_wallet_transaction_meta( $transaction_id, '_type', 'credit_purchase', $this->user_id );

		require_once dirname( __DIR__ ) . '/includes/helper/woo-wallet-update-functions.php';
		woo_wallet_update_163_db_schema();

		$this->assertSame( 'topup', $this->read_category( $transaction_id ) );
	}

	/**
	 * The migration also recognises untagged transfer rows via the
	 * `_wallet_transfer_charge` / `_to_wallet_user_id` /
	 * `_from_wallet_user_id` meta keys the transfer service has historically
	 * written.
	 */
	public function test_163_migration_detects_legacy_untagged_transfer_rows() {
		global $wpdb;

		woo_wallet()->wallet->credit( $this->user_id, 100.00, 'Seed' );
		$result = woo_wallet()->wallet->transfer(
			$this->user_id,
			$this->other_user_id,
			10.00,
			'Sent',
			'Received'
		);

		// Force the rows back to 'other' (simulating pre-1.6.3 state where
		// transfer never tagged itself).
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->base_prefix}woo_wallet_transactions SET category='other' WHERE transaction_id IN (%d,%d)",
				$result['debit'],
				$result['credit']
			)
		);
		// Ensure the discoverable transfer-meta exists on at least the debit leg.
		update_wallet_transaction_meta( $result['debit'], '_wallet_transfer_charge', 0, $this->user_id );

		require_once dirname( __DIR__ ) . '/includes/helper/woo-wallet-update-functions.php';
		woo_wallet_update_163_db_schema();

		$this->assertSame( 'transfer', $this->read_category( $result['debit'] ) );
	}
}
