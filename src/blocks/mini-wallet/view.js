/**
 * Wallet Balance Block – Frontend View Script
 *
 * Minimal JS loaded on the frontend for accessibility and interactivity
 * enhancements. This script is automatically enqueued by WordPress via
 * the "viewScript" field in block.json.
 *
 * @package woo-wallet
 */

( function () {
	'use strict';

	/**
	 * Initialize all wallet balance block instances on the page.
	 */
	function init() {
		const blocks = document.querySelectorAll(
			'.wc-block-mini-wallet'
		);

		blocks.forEach( function ( block ) {
			const link = block.querySelector(
				'.wc-block-mini-wallet__link'
			);
			if ( ! link ) {
				return;
			}

			// Ensure ARIA label is present.
			if ( ! link.getAttribute( 'aria-label' ) ) {
				link.setAttribute( 'aria-label', 'View your wallet' );
			}

			// Add keyboard focus styles.
			link.addEventListener( 'focus', function () {
				block.classList.add( 'is-focused' );
			} );

			link.addEventListener( 'blur', function () {
				block.classList.remove( 'is-focused' );
			} );
		} );
	}

	// Run on DOM ready.
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
