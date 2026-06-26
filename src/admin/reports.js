/**
 * TeraWallet — Wallet Reports dashboard interactivity.
 *
 * Progressive enhancement over the server-rendered PHP markup:
 *   - count-up animation on the headline + stat figures
 *   - hover-linked composition bar <-> legend
 *   - a live "Refresh" button that re-pulls the REST summary
 *
 * All values are present in the DOM on load, so the page is fully usable with
 * JS disabled. No framework, no chart library.
 */

import '../scss/reports.scss';

( function () {
	'use strict';

	var params = window.wooWalletReports || {};
	var reduceMotion =
		window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	/**
	 * Format a number as a price string using the localized WooCommerce
	 * format params, so count-up matches the server-rendered figure exactly.
	 */
	function formatCurrency( value ) {
		var p = params.price || {};
		var decimals = typeof p.decimals === 'number' ? p.decimals : 2;
		var fixed = Math.abs( value ).toFixed( decimals );
		var parts = fixed.split( '.' );
		parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, p.thousand || ',' );
		var num = parts.join( p.decimal || '.' );
		var formatted = ( p.format || '%1$s%2$s' )
			.replace( '%1$s', p.symbol || '' )
			.replace( '%2$s', num );
		return ( value < 0 ? '-' : '' ) + formatted;
	}

	function formatInt( value ) {
		var p = params.price || {};
		return Math.round( value )
			.toString()
			.replace( /\B(?=(\d{3})+(?!\d))/g, p.thousand || ',' );
	}

	function formatValue( el, value ) {
		return el.getAttribute( 'data-format' ) === 'int'
			? formatInt( value )
			: formatCurrency( value );
	}

	/** Animate one element from 0 (or its current shown value) to data-raw. */
	function countUp( el, to ) {
		if ( reduceMotion ) {
			el.textContent = formatValue( el, to );
			return;
		}
		var from = 0;
		var start = null;
		var dur = 700;
		function tick( ts ) {
			if ( start === null ) {
				start = ts;
			}
			var t = Math.min( 1, ( ts - start ) / dur );
			var eased = 1 - Math.pow( 1 - t, 3 );
			el.textContent = formatValue( el, from + ( to - from ) * eased );
			if ( t < 1 ) {
				window.requestAnimationFrame( tick );
			}
		}
		window.requestAnimationFrame( tick );
	}

	function animateAllFigures( root ) {
		var figs = root.querySelectorAll( '[data-raw]' );
		Array.prototype.forEach.call( figs, function ( el ) {
			countUp( el, parseFloat( el.getAttribute( 'data-raw' ) ) || 0 );
		} );
	}

	function reveal( root ) {
		var items = root.querySelectorAll( '.twr-reveal' );
		Array.prototype.forEach.call( items, function ( el, i ) {
			window.setTimeout(
				function () {
					el.classList.add( 'is-in' );
				},
				reduceMotion ? 0 : 60 * i
			);
		} );
	}

	/** Link composition bar segments and legend rows by data-slug. */
	function wireComposition( root ) {
		var bar = root.querySelector( '.twr-bar' );
		if ( ! bar ) {
			return;
		}
		var segs = bar.querySelectorAll( '.twr-bar__seg' );
		var legend = root.querySelectorAll( '.twr-legend__item' );

		function setActive( slug, on ) {
			[].forEach.call( segs, function ( s ) {
				s.classList.toggle( 'is-active', on && s.getAttribute( 'data-slug' ) === slug );
			} );
			[].forEach.call( legend, function ( l ) {
				l.classList.toggle( 'is-active', on && l.getAttribute( 'data-slug' ) === slug );
			} );
			bar.classList.toggle( 'has-dim', on );
		}

		function bind( nodes ) {
			[].forEach.call( nodes, function ( n ) {
				var slug = n.getAttribute( 'data-slug' );
				n.addEventListener( 'mouseenter', function () {
					setActive( slug, true );
				} );
				n.addEventListener( 'mouseleave', function () {
					setActive( slug, false );
				} );
			} );
		}
		bind( segs );
		bind( legend );
	}

	/** Re-pull the summary from REST and update figures + bar live. */
	function wireRefresh( root ) {
		var btn = root.querySelector( '.twr-refresh' );
		if ( ! btn || ! params.restUrl ) {
			return;
		}
		var updated = root.querySelector( '[data-updated] time' );

		btn.addEventListener( 'click', function () {
			btn.classList.add( 'is-busy' );
			btn.disabled = true;

			// Cache-bust: server already skips the transient, but a repeated GET to
			// the same URL is served from the browser/proxy HTTP cache otherwise,
			// so the refresh returns stale numbers. ponytail: timestamp + no-store
			// covers both browser disk cache and upstream proxies.
			var url = params.restUrl + ( params.restUrl.indexOf( '?' ) === -1 ? '?' : '&' ) + 'nocache=1&_=' + Date.now();

			window
				.fetch( url, {
					headers: { 'X-WP-Nonce': params.nonce || '' },
					credentials: 'same-origin',
					cache: 'no-store',
				} )
				.then( function ( r ) {
					return r.ok ? r.json() : Promise.reject( r );
				} )
				.then( function ( data ) {
					applySummary( root, data );
					if ( updated ) {
						updated.textContent = params.i18n ? params.i18n.justNow : 'just now';
					}
				} )
				.catch( function () {
					/* leave the existing figures in place on failure */
				} )
				.then( function () {
					btn.classList.remove( 'is-busy' );
					btn.disabled = false;
				} );
		} );
	}

	/** Push a fresh REST payload into the DOM. */
	function applySummary( root, data ) {
		[ 'total_liability', 'positive_wallets', 'lifetime_credited', 'lifetime_debited' ].forEach(
			function ( field ) {
				if ( typeof data[ field ] === 'undefined' ) {
					return;
				}
				var el = root.querySelector( '[data-field="' + field + '"]' );
				if ( el ) {
					el.setAttribute( 'data-raw', data[ field ] );
					countUp( el, parseFloat( data[ field ] ) || 0 );
				}
			}
		);

		// Update composition bar widths + legend amounts for known slugs.
		if ( Array.isArray( data.composition ) ) {
			var positive = data.composition.filter( function ( c ) {
				return parseFloat( c.amount ) > 0;
			} );
			var total = positive.reduce( function ( s, c ) {
				return s + parseFloat( c.amount );
			}, 0 );
			data.composition.forEach( function ( c ) {
				var seg = root.querySelector( '.twr-bar__seg[data-slug="' + c.slug + '"]' );
				if ( seg && total > 0 && parseFloat( c.amount ) > 0 ) {
					seg.style.setProperty( '--w', ( ( parseFloat( c.amount ) / total ) * 100 ).toFixed( 2 ) + '%' );
				}
				var amt = root.querySelector( '.twr-legend__item[data-slug="' + c.slug + '"] .twr-legend__amt' );
				if ( amt ) {
					amt.textContent = formatCurrency( parseFloat( c.amount ) );
				}
			} );
		}
	}

	function init() {
		var root = document.querySelector( '.woo-wallet-reports' );
		if ( ! root ) {
			return;
		}
		animateAllFigures( root );
		reveal( root );
		wireComposition( root );
		wireRefresh( root );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
