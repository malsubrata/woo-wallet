/**
 * Wallet Balance Block – Registration
 *
 * Entry point that registers the block using metadata from block.json.
 *
 * @package woo-wallet
 */

import { registerBlockType } from '@wordpress/blocks';
import { createElement } from '@wordpress/element';
import metadata from './block.json';
import Edit from './edit';
import save from './save';

/**
 * Shared styles (frontend + editor): extracted as style-index.css
 * Editor-only styles: extracted as index.css (imported via edit.js)
 */
import './style.scss';

/**
 * Custom SVG block icon for the inserter (wallet outline).
 */
const blockIcon = (
	<svg
		xmlns="http://www.w3.org/2000/svg"
		viewBox="0 0 24 24"
		width="24"
		height="24"
		fill="none"
		stroke="currentColor"
		strokeWidth="1.5"
		strokeLinecap="round"
		strokeLinejoin="round"
	>
		<rect x="2" y="6" width="20" height="14" rx="2" ry="2" />
		<path d="M2 10h20" />
		<circle cx="18" cy="14" r="1" fill="currentColor" stroke="none" />
		<path d="M6 6V4a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v2" />
	</svg>
);

registerBlockType( metadata.name, {
	...metadata,
	icon: blockIcon,
	edit: Edit,
	save,
} );
