<?php
/**
 * F15: wallet transfer rows name the other party (never a username or full
 * email) and both sides see the note.
 *
 * @package WooWallet\Tests
 */

require_once WOO_WALLET_ABSPATH . 'includes/services/class-woo-wallet-transfer-service.php';

/**
 * @covers WooWallet_Transfer_Service::execute
 * @covers WOO_Wallet_Helper::get_transfer_party_name
 */
class Test_Transfer_Notes extends WP_UnitTestCase {

	/**
	 * Funded sender and a recipient.
	 *
	 * @param array $sender_args    Extra sender user fields.
	 * @param array $recipient_args Extra recipient user fields.
	 * @return int[] [ sender, recipient ]
	 */
	private function users( array $sender_args, array $recipient_args ) {
		$sender    = self::factory()->user->create( array_merge( array( 'role' => 'customer' ), $sender_args ) );
		$recipient = self::factory()->user->create( array_merge( array( 'role' => 'customer' ), $recipient_args ) );
		woo_wallet()->wallet->credit( $sender, 100, 'Fund' );
		return array( $sender, $recipient );
	}

	/**
	 * Latest transaction note for a user.
	 *
	 * @param int $user_id User.
	 * @return string
	 */
	private function latest_note( $user_id ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( "SELECT details FROM {$wpdb->base_prefix}woo_wallet_transactions WHERE user_id = %d ORDER BY transaction_id DESC LIMIT 1", $user_id ) );
	}

	/**
	 * With a note: "From <sender>: note" / "To <recipient>: note".
	 */
	public function test_note_shown_to_both_sides_with_names() {
		list( $sender, $recipient ) = $this->users(
			array( 'first_name' => 'Asha', 'last_name' => 'Rao' ),
			array( 'first_name' => 'Ben', 'last_name' => 'Cole' )
		);
		$result = WooWallet_Transfer_Service::execute( $sender, $recipient, 10, 'Dinner' );

		$this->assertTrue( $result['is_valid'], wp_json_encode( $result ) );
		$this->assertSame( 'From Asha Rao: Dinner', $this->latest_note( $recipient ) );
		$this->assertSame( 'To Ben Cole: Dinner', $this->latest_note( $sender ) );
	}

	/**
	 * No name set: a masked email, never the username or the full email.
	 */
	public function test_no_username_or_full_email() {
		list( $sender, $recipient ) = $this->users(
			array( 'user_login' => 'sender_login', 'user_email' => 'sender@example.com', 'display_name' => 'sender_login' ),
			array( 'user_login' => 'recip_login', 'user_email' => 'recip@example.com', 'display_name' => 'recip_login' )
		);
		WooWallet_Transfer_Service::execute( $sender, $recipient, 10 );

		$credit = $this->latest_note( $recipient );
		$debit  = $this->latest_note( $sender );
		$this->assertSame( 'Wallet funds received from s***@example.com', $credit );
		$this->assertSame( 'Wallet funds transfer to r***@example.com', $debit );
		foreach ( array( $credit, $debit ) as $note ) {
			$this->assertStringNotContainsString( '_login', $note );
			$this->assertStringNotContainsString( 'sender@', $note );
			$this->assertStringNotContainsString( 'recip@', $note );
		}
	}

	/**
	 * Markup stored straight in user meta is stripped from the name.
	 */
	public function test_name_markup_is_stripped() {
		list( $sender, $recipient ) = $this->users( array(), array() );
		update_user_meta( $recipient, 'first_name', '<b>Bob</b><script>x</script>' );
		update_user_meta( $recipient, 'last_name', ' ' );
		WooWallet_Transfer_Service::execute( $sender, $recipient, 10, 'Lunch' );

		$this->assertSame( 'To Bob: Lunch', $this->latest_note( $sender ) );
	}

	/**
	 * A display name made only of markup falls back to the masked email.
	 */
	public function test_display_name_markup_only_falls_back() {
		$user               = new WP_User( self::factory()->user->create( array( 'user_email' => 'dn@example.com' ) ) );
		$user->display_name = '<i>Dee</i>';
		$this->assertSame( 'Dee', WOO_Wallet_Helper::get_transfer_party_name( $user ) );
		$user->display_name = '<script>x</script>';
		$this->assertSame( 'd***@example.com', WOO_Wallet_Helper::get_transfer_party_name( $user ) );
	}
}
