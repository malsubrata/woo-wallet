import './styles.css';

import { createElement, render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';
import { getRegistry } from './registry';

// Construct the public registry first thing so any third-party inline script
// enqueued after this bundle finds `window.wooWallet.settings` already wired.
// See docs/EXTENDING_SETTINGS.md for the public API.
getRegistry();

// Wire up REST nonce from localized data.
const { restNonce } = window.wooWalletSettingsData || {};
if ( restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( restNonce ) );
}

const root = document.getElementById( 'woo-wallet-settings-root' );
if ( root ) {
	render( createElement( App ), root );
}
