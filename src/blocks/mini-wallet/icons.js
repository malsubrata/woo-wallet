/**
 * Wallet Balance Block – SVG Icon Components
 *
 * Each icon is a React component rendering an inline SVG at a 24×24 viewBox.
 * They use `currentColor` for fill/stroke so they inherit the parent's color
 * and respond to the admin's iconColor attribute.
 *
 * @package woo-wallet
 */

/**
 * Shared <svg> wrapper for all wallet icons.
 *
 * @param {Object} props          Props for the SVG element.
 * @param {number} [props.size]   Pixel size for width/height.
 * @return {JSX.Element}
 */
const Icon = ( { children, size = 24, ...props } ) => (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width={ size }
		height={ size }
		fill="none"
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
		aria-hidden="true"
		focusable="false"
		{ ...props }
	>
		{ children }
	</svg>
);

/**
 * Classic Wallet – bifold wallet with clasp.
 */
export const ClassicWallet = ( { size, ...props } ) => (
	<Icon size={ size } { ...props }>
		<path d="M6 6V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v1" />
		<rect x="2" y="6" width="20" height="14" rx="2" ry="2" />
		<path d="M2 10h20" />
		<circle cx="18" cy="14" r="1.1" fill="currentColor" stroke="none" />
	</Icon>
);

/**
 * Cash Wallet – wallet with cash / bill peeking out the top.
 */
export const CashWallet = ( { size, ...props } ) => (
	<Icon size={ size } { ...props }>
		<path d="M8 6V3.5a1 1 0 0 1 1.3-.95l7 2.1a1 1 0 0 1 .7.96V6" />
		<rect x="2" y="6" width="20" height="14" rx="2" ry="2" />
		<path d="M16 13h4" />
		<circle cx="17.5" cy="13" r="0.5" fill="currentColor" stroke="none" />
		<path d="M6 13h6" strokeWidth="1" opacity="0.55" />
	</Icon>
);

/**
 * Card Wallet – modern slim wallet with stacked cards.
 */
export const CardWallet = ( { size, ...props } ) => (
	<Icon size={ size } { ...props }>
		<rect x="2" y="5" width="20" height="15" rx="2.5" ry="2.5" />
		<path d="M2 10h20" />
		<rect x="5" y="13.5" width="9" height="3.5" rx="0.75" ry="0.75" strokeWidth="1.25" />
		<path d="M16.5 15.25h2.5" strokeWidth="1.25" opacity="0.6" />
	</Icon>
);

/**
 * Icon map – keyed by the `walletIcon` attribute value.
 */
const WALLET_ICONS = {
	'classic-wallet': ClassicWallet,
	'cash-wallet': CashWallet,
	'card-wallet': CardWallet,
};

/**
 * Icon labels for the picker UI.
 */
export const WALLET_ICON_LABELS = {
	'classic-wallet': 'Classic Wallet',
	'cash-wallet': 'Cash Wallet',
	'card-wallet': 'Card Wallet',
};

export default WALLET_ICONS;
