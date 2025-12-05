<?php
/**
 * The Template for displaying wallet dashboard.
 *
 * This template can be overridden by copying it to yourtheme/woo-wallet/wc-endpoint-wallet.php.
 *
 * HOWEVER, on occasion we will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @author  Subrata Mal
 * @version     1.1.8
 * @package StandaleneTech
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

global $wp;
do_action( 'woo_wallet_before_my_wallet_content' );
$is_rendred_from_myaccount = wc_post_content_has_shortcode( 'woo-wallet' ) ? false : is_account_page();
$menu_items                = apply_filters(
	'woo_wallet_nav_menu_items',
	array(
		'add'          => array(
			'title' => apply_filters( 'woo_wallet_account_topup_menu_title', __( 'Wallet topup', 'woo-wallet' ) ),
			'url'   => $is_rendred_from_myaccount ? esc_url( wc_get_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ), 'add', wc_get_page_permalink( 'myaccount' ) ) ) : add_query_arg( 'wallet_action', 'add' ),
			'icon'  => 'dashicons dashicons-plus-alt',
		),
		'transfer'     => array(
			'title' => apply_filters( 'woo_wallet_account_transfer_amount_menu_title', __( 'Wallet transfer', 'woo-wallet' ) ),
			'url'   => $is_rendred_from_myaccount ? esc_url( wc_get_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ), 'transfer', wc_get_page_permalink( 'myaccount' ) ) ) : add_query_arg( 'wallet_action', 'transfer' ),
			'icon'  => 'dashicons dashicons-randomize',
		),
		'transactions' => array(
			'title' => apply_filters( 'woo_wallet_account_transaction_menu_title', __( 'Transactions', 'woo-wallet' ) ),
			'url'   => $is_rendred_from_myaccount ? esc_url( wc_get_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ), 'transactions', wc_get_page_permalink( 'myaccount' ) ) ) : add_query_arg( 'wallet_action', 'transactions' ),
			'icon'  => 'dashicons dashicons-list-view',
		),
	),
	$is_rendred_from_myaccount
);
$current_action            = isset( $_GET['wallet_action'] ) ? $_GET['wallet_action'] : ( isset( $wp->query_vars['woo-wallet'] ) ? $wp->query_vars['woo-wallet'] : '' );
// Default to transactions if no action or just 'woo-wallet' endpoint.
if ( empty( $current_action ) && ! isset( $_GET['wallet_action'] ) ) {
	$current_action = 'transactions';
}
if ( ! function_exists( 'is_wallet_tab_active' ) ) {
	/**
	 * Helper to check active state.
	 *
	 * @param string $tab_key tab key.
	 * @param string $current_action current action.
	 * @param array  $menu_item menu item.
	 * @return bool
	 */
	function is_wallet_tab_active( $tab_key, $current_action, $menu_item = null ) {
		if ( $tab_key === $current_action ) {
			return true;
		}
		if ( 'transactions' === $tab_key && empty( $current_action ) ) {
			return true;
		}

		// Check submenu.
		if ( $menu_item && isset( $menu_item['submenu'] ) && is_array( $menu_item['submenu'] ) ) {
			if ( array_key_exists( $current_action, $menu_item['submenu'] ) ) {
				return true;
			}
		}
		return false;
	}
}
?>

<div class="woo-wallet-my-wallet-container">
	
	<!-- Header -->
	<div class="woo-wallet-header">
		<h2><?php echo esc_html( apply_filters( 'woo_wallet_account_menu_title', __( 'My Wallet', 'woo-wallet' ) ) ); ?></h2>
		<p><?php esc_html_e( 'Manage your wallet and transactions seamlessly.', 'woo-wallet' ); ?></p>
	</div>

	<!-- Top Section Wrapper -->
	<div class="woo-wallet-top-section">
		<!-- Balance Card -->
		<div class="woo-wallet-balance-card">
			<h3><?php esc_html_e( 'Total Balance', 'woo-wallet' ); ?></h3>
			<p class="woo-wallet-price"><?php echo woo_wallet()->wallet->get_wallet_balance( get_current_user_id() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></p>
		</div>

		<!-- Navigation Tabs -->
		<div class="woo-wallet-nav-tabs">
			<?php foreach ( $menu_items as $item => $menu_item ) : ?>
				<?php if ( apply_filters( 'woo_wallet_is_enable_' . $item, true ) ) : ?>
					<div class="woo-wallet-nav-item-wrapper <?php echo isset( $menu_item['submenu'] ) ? 'has-submenu' : ''; ?>">
						<a href="<?php echo esc_url( $menu_item['url'] ); ?>" class="woo-wallet-nav-tab <?php echo is_wallet_tab_active( $item, $current_action, $menu_item ) ? 'active' : ''; ?>">
							<span class="<?php echo esc_attr( $menu_item['icon'] ); ?>"></span>
							<?php echo esc_html( $menu_item['title'] ); ?>
							<?php if ( isset( $menu_item['submenu'] ) ) : ?>
								<span class="woo-wallet-submenu-toggle dashicons dashicons-arrow-down-alt2"></span>
							<?php endif; ?>
						</a>
						<?php if ( isset( $menu_item['submenu'] ) && is_array( $menu_item['submenu'] ) ) : ?>
							<div class="woo-wallet-submenu">
								<?php foreach ( $menu_item['submenu'] as $sub_key => $sub_item ) : ?>
									<a href="<?php echo esc_url( $sub_item['url'] ); ?>" class="woo-wallet-submenu-item">
										<?php echo esc_html( $sub_item['title'] ); ?>
									</a>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
			<?php do_action( 'woo_wallet_menu_items' ); ?>
		</div>
	</div>
	<!-- Print notices -->
	<?php wc_print_notices(); ?>
	<!-- Content Area -->
	<div class="woo-wallet-content-area">
		<?php if ( ( isset( $wp->query_vars['woo-wallet'] ) && ! empty( $wp->query_vars['woo-wallet'] ) ) || isset( $_GET['wallet_action'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<?php
			if ( apply_filters( "woo_wallet_is_enable_{$current_action}", true ) ) {
				do_action( "woo_wallet_{$current_action}_content" );
			}
			do_action( 'woo_wallet_menu_content' ); // will be removed in future.
		} elseif ( apply_filters( 'woo_wallet_is_enable_transactions', true ) ) {
			?>
			<!-- Recent Transactions -->
			<div class="woo-wallet-transactions-list">
				<h3 class="woo-wallet-section-title"><?php esc_html_e( 'Balance History', 'woo-wallet' ); ?></h3>
				<?php $transactions = get_wallet_transactions( array( 'limit' => apply_filters( 'woo_wallet_transactions_count', 10 ) ) ); ?>
				<?php if ( ! empty( $transactions ) ) { ?>
					<table class="woo-wallet-transactions-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'woo-wallet' ); ?></th>
								<th><?php esc_html_e( 'Description', 'woo-wallet' ); ?></th>
								<th style="text-align: right;"><?php esc_html_e( 'Amount', 'woo-wallet' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $transactions as $transaction ) : ?> 
								<tr>
									<td><?php echo esc_html( wc_string_to_datetime( $transaction->date )->date_i18n( wc_date_format() ) ); ?></td>
									<td><?php echo wp_kses_post( $transaction->details ); ?></td>
									<td class="amount <?php echo esc_attr( $transaction->type ); ?>">
										<?php
										echo 'credit' === $transaction->type ? '+' : '-';
										echo wp_kses_post( wc_price( apply_filters( 'woo_wallet_amount', $transaction->amount, $transaction->currency, $transaction->user_id ), woo_wallet_wc_price_args( $transaction->user_id ) ) );
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<?php
				} else {
					echo '<p style="padding: 20px; color: #666; text-align: center;">' . esc_html__( 'No transactions found', 'woo-wallet' ) . '</p>';
				}
				?>
			</div>
		<?php } ?>
	</div>
</div>
<?php
do_action( 'woo_wallet_after_my_wallet_content' );
