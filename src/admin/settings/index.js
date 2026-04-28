import './styles.css';

import { createElement, render } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import App from './App';

// Wire up REST nonce from localized data.
const { restNonce } = window.wooWalletSettingsData || {};
if ( restNonce ) {
	apiFetch.use( apiFetch.createNonceMiddleware( restNonce ) );
}

const root = document.getElementById( 'woo-wallet-settings-root' );
if ( root ) {
	render( createElement( App ), root );
}
