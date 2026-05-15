<?php
/**
 * Wallet Balance Block – Server-Side Render
 *
 * Dynamically renders the wallet balance block on the frontend.
 * This file is referenced by block.json's "render" field and receives
 * $attributes and $content automatically.
 *
 * @package StandaleneTech
 * @since   1.7.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content (empty for dynamic blocks).
 * @var WP_Block $block      Block instance.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Don't render if user is not logged in.
if ( ! is_user_logged_in() ) {
	return '';
}

// Don't render if WooWallet is not available.
if ( ! function_exists( 'woo_wallet' ) || ! woo_wallet()->wallet ) {
	return '';
}

// ── Extract attributes with defaults ──────────────────────────────────────
$wallet_icon   = isset( $attributes['walletIcon'] ) ? sanitize_text_field( $attributes['walletIcon'] ) : 'classic-wallet';
$icon_size     = isset( $attributes['iconSize'] ) ? absint( $attributes['iconSize'] ) : 24;
$icon_color    = isset( $attributes['iconColor'] ) ? sanitize_hex_color( $attributes['iconColor'] ) : '';
$balance_color = isset( $attributes['balanceColor'] ) ? sanitize_hex_color( $attributes['balanceColor'] ) : '';
$link_url      = isset( $attributes['linkUrl'] ) && ! empty( $attributes['linkUrl'] ) ? esc_url( $attributes['linkUrl'] ) : '';
$show_balance  = isset( $attributes['showBalance'] ) ? (bool) $attributes['showBalance'] : true;

// ── Resolve the link URL ──────────────────────────────────────────────────
if ( empty( $link_url ) ) {
	$link_url = esc_url( wc_get_account_endpoint_url( get_option( 'woocommerce_woo_wallet_endpoint', 'my-wallet' ) ) );
}

// ── Get formatted wallet balance ──────────────────────────────────────────
$balance = woo_wallet()->wallet->get_wallet_balance( get_current_user_id() );

// ── SVG Icons Map ─────────────────────────────────────────────────────────
$svg_open = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="' . $icon_size . '" height="' . $icon_size . '" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">';

$icons = array(
	'classic-wallet' => $svg_open . '<path d="M6 6V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1"/><rect x="2" y="6" width="20" height="14" rx="2" ry="2"/><path d="M2 10h20"/><circle cx="18" cy="14" r="1.1" fill="currentColor" stroke="none"/></svg>',
	'cash-wallet'    => $svg_open . '<path d="M8 6V3.5a1 1 0 0 1 1.3-.95l7 2.1a1 1 0 0 1 .7.96V6"/><rect x="2" y="6" width="20" height="14" rx="2" ry="2"/><path d="M16 13h4"/><circle cx="17.5" cy="13" r="0.5" fill="currentColor" stroke="none"/><path d="M6 13h6" stroke-width="1" opacity="0.55"/></svg>',
	'card-wallet'    => $svg_open . '<rect x="2" y="5" width="20" height="15" rx="2.5" ry="2.5"/><path d="M2 10h20"/><rect x="5" y="13.5" width="9" height="3.5" rx="0.75" ry="0.75" stroke-width="1.25"/><path d="M16.5 15.25h2.5" stroke-width="1.25" opacity="0.6"/></svg>',
);

// Fall back to classic-wallet if the selected icon doesn't exist.
$icon_svg = isset( $icons[ $wallet_icon ] ) ? $icons[ $wallet_icon ] : $icons['classic-wallet'];

// ── Build inline styles ───────────────────────────────────────────────────
$icon_style    = $icon_color ? 'color:' . $icon_color . ';' : '';
$balance_style = $balance_color ? 'color:' . $balance_color . ';' : '';

// ── Get block wrapper attributes ──────────────────────────────────────────
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'wc-block-mini-wallet',
	)
);

// ── Render ────────────────────────────────────────────────────────────────
?>
<div <?php echo $wrapper_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<a
		class="wc-block-mini-wallet__link"
		href="<?php echo esc_url( $link_url ); ?>"
		title="<?php esc_attr_e( 'View your wallet', 'woo-wallet' ); ?>"
		aria-label="<?php esc_attr_e( 'View your wallet', 'woo-wallet' ); ?>"
	>
		<span
			class="wc-block-mini-wallet__icon"
			<?php if ( $icon_style ) : ?>
				style="<?php echo esc_attr( $icon_style ); ?>"
			<?php endif; ?>
		>
			<?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Pre-defined SVG markup. ?>
		</span>
		<?php if ( $show_balance ) : ?>
			<span
				class="wc-block-mini-wallet__amount"
				<?php if ( $balance_style ) : ?>
					style="<?php echo esc_attr( $balance_style ); ?>"
				<?php endif; ?>
			>
				<?php echo $balance; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output from wc_price(). ?>
			</span>
		<?php endif; ?>
	</a>
</div>
