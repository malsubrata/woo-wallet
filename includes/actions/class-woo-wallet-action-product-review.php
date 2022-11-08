<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Action_Product_Review extends WooWalletAction {
	/**
	 * Product review amount
	 *
	 * @var decimal
	 */
	public $amount = 0;
	/**
	 * Class constructor.
	 */
	public function __construct() {
		$this->id           = 'product_review';
		$this->action_title = __( 'WooCommerce product review', 'woo-wallet' );
		$this->description  = __( 'Set credit for WooCommerce product review', 'woo-wallet' );
		$this->init_form_fields();
		$this->init_settings();
		// Actions.
		add_action( 'comment_post', array( $this, 'new_product_review' ), 10, 3 );
		add_action( 'transition_comment_status', array( $this, 'woo_wallet_product_review_credit' ), 10, 3 );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled'     => array(
				'title'   => __( 'Enable/Disable', 'woo-wallet' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable credit for WooCommerce product review', 'woo-wallet' ),
				'default' => 'no',
			),
			'amount'      => array(
				'title'       => __( 'Amount', 'woo-wallet' ),
				'type'        => 'price',
				'description' => __( 'Enter amount which will be credited to the user wallet for reviewing a WooCommerce product.', 'woo-wallet' ),
				'default'     => '10',
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woo-wallet' ),
				'type'        => 'textarea',
				'description' => __( 'Wallet transaction description that will display as transaction note.', 'woo-wallet' ),
				'default'     => __( 'Balance credited for reviewing a WooCommerce product.', 'woo-wallet' ),
				'desc_tip'    => true,
			),
		);
	}
	/**
	 * Process new product review action.
	 *
	 * @param int   $comment_id comment_id.
	 * @param bool  $comment_approved comment_approved.
	 * @param array $commentdata commentdata.
	 */
	public function new_product_review( $comment_id, $comment_approved, $commentdata ) {
		if ( 'product' === get_post_type( absint( $commentdata['comment_post_ID'] ) ) ) {
			if ( ! $this->is_enabled() || 1 !== $commentdata['comment_approved'] || get_comment_meta( $comment_id, 'wallet_transaction_id', true ) || get_post_meta( $commentdata['comment_post_ID'], "_woo_wallet_comment_commission_received_{$commentdata['user_id']}", true ) ) {
				return;
			}
			$this->amount = apply_filters( 'woo_wallet_product_review_action_amount', $this->settings['amount'], $comment_id, $commentdata['user_id'] );
			$product      = wc_get_product( $commentdata['comment_post_ID'] );
			if ( $this->amount && $product && apply_filters( 'woo_wallet_product_review_credit', true, $commentdata ) ) {
				$transaction_id = woo_wallet()->wallet->credit( $commentdata['user_id'], $this->amount, sanitize_textarea_field( $this->settings['description'] ) );
				update_comment_meta( $comment_id, 'wallet_transaction_id', $transaction_id );
				update_post_meta( $commentdata['comment_post_ID'], "_woo_wallet_comment_commission_received_{$commentdata['user_id']}", true );
				do_action( 'woo_wallet_after_product_review', $transaction_id, $comment_id );
			}
		}
	}
	/**
	 * Credit new product review.
	 *
	 * @param string $new_status new_status.
	 * @param string $old_status old_status.
	 * @param object $comment comment.
	 */
	public function woo_wallet_product_review_credit( $new_status, $old_status, $comment ) {
		$product = wc_get_product( $comment->comment_post_ID );
		if ( ! $this->is_enabled() || 'approved' !== $new_status || get_comment_meta( $comment->comment_ID, 'wallet_transaction_id', true ) || get_post_meta( $product->get_id(), "_woo_wallet_comment_commission_received_{$comment->user_id}", true ) ) {
			return;
		}
		$this->amount = apply_filters( 'woo_wallet_product_review_action_amount', $this->settings['amount'], $comment->comment_ID, $comment->user_id );
		if ( $this->amount && $product && apply_filters( 'woo_wallet_product_review_credit', true, $comment ) ) {
			$transaction_id = woo_wallet()->wallet->credit( $comment->user_id, $this->amount, sanitize_textarea_field( $this->settings['description'] ) );
			update_comment_meta( $comment->comment_ID, 'wallet_transaction_id', $transaction_id );
			update_post_meta( $product->get_id(), "_woo_wallet_comment_commission_received_{$comment->user_id}", true );
			do_action( 'woo_wallet_after_product_review', $transaction_id, $comment->comment_ID );
		}
	}

}
