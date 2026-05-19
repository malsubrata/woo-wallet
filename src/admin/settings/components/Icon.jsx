import { applyFilters } from '@wordpress/hooks';
import { createElement, isValidElement } from '@wordpress/element';

const S = { strokeLinecap: 'round', strokeLinejoin: 'round', fill: 'none' };

const ICONS = {
	settings: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<circle cx="12" cy="12" r="3" />
			<path d="M19.07 4.93A10 10 0 0 0 4.93 19.07M4.93 4.93a10 10 0 0 0 14.14 14.14" />
			<path d="M12 2v2M12 20v2M2 12h2M20 12h2" />
		</svg>
	),
	credit: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<rect x="2" y="5" width="20" height="14" rx="3" />
			<line x1="2" y1="10" x2="22" y2="10" />
			<line x1="6" y1="15" x2="9" y2="15" />
			<line x1="13" y1="15" x2="18" y2="15" />
		</svg>
	),
	withdraw: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M12 2v14M6 10l6 6 6-6" />
			<path d="M3 20h18" />
		</svg>
	),
	actions: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9l-7-7z" />
			<path d="M13 2v7h7M9 13h6M9 17h4" />
		</svg>
	),
	wallet: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M20 7H4a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2z" />
			<path d="M16 3H8L4 7h16l-4-4z" />
			<circle cx="17" cy="14" r="1" fill={ c } />
		</svg>
	),
	menu: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<line x1="3" y1="6" x2="21" y2="6" />
			<line x1="3" y1="12" x2="21" y2="12" />
			<line x1="3" y1="18" x2="21" y2="18" />
		</svg>
	),
	chevron: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<polyline points="6 9 12 15 18 9" />
		</svg>
	),
	check: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<polyline points="20 6 9 17 4 12" />
		</svg>
	),
	x: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<line x1="18" y1="6" x2="6" y2="18" />
			<line x1="6" y1="6" x2="18" y2="18" />
		</svg>
	),
	save: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z" />
			<polyline points="17 21 17 13 7 13 7 21" />
			<polyline points="7 3 7 8 15 8" />
		</svg>
	),
	upload: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
			<polyline points="17 8 12 3 7 8" />
			<line x1="12" y1="3" x2="12" y2="15" />
		</svg>
	),
	eye: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" />
			<circle cx="12" cy="12" r="3" />
		</svg>
	),
	eyeOff: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24" />
			<line x1="1" y1="1" x2="23" y2="23" />
		</svg>
	),
	link: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" />
			<path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" />
		</svg>
	),
	key: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4" />
		</svg>
	),
	info: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<circle cx="12" cy="12" r="10" />
			<line x1="12" y1="16" x2="12" y2="12" />
			<line x1="12" y1="8" x2="12.01" y2="8" />
		</svg>
	),
	sun: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<circle cx="12" cy="12" r="5" />
			<line x1="12" y1="1" x2="12" y2="3" />
			<line x1="12" y1="21" x2="12" y2="23" />
			<line x1="4.22" y1="4.22" x2="5.64" y2="5.64" />
			<line x1="18.36" y1="18.36" x2="19.78" y2="19.78" />
			<line x1="1" y1="12" x2="3" y2="12" />
			<line x1="21" y1="12" x2="23" y2="12" />
			<line x1="4.22" y1="19.78" x2="5.64" y2="18.36" />
			<line x1="18.36" y1="5.64" x2="19.78" y2="4.22" />
		</svg>
	),
	moon: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z" />
		</svg>
	),
	monitor: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<rect x="2" y="3" width="20" height="14" rx="2" />
			<line x1="8" y1="21" x2="16" y2="21" />
			<line x1="12" y1="17" x2="12" y2="21" />
		</svg>
	),
	refresh: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<path d="M23 4v6h-6" />
			<path d="M1 20v-6h6" />
			<path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
		</svg>
	),
	color: ( { c, w } ) => (
		<svg viewBox="0 0 24 24" stroke={ c } strokeWidth={ w } { ...S }>
			<circle cx="13.5" cy="6.5" r="1.5" />
			<circle cx="17.5" cy="10.5" r="1.5" />
			<circle cx="8.5" cy="7.5" r="1.5" />
			<circle cx="6.5" cy="12.5" r="1.5" />
			<path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8z" />
		</svg>
	),
};

/**
 * Whitelist of SVG child tags accepted via `{ svg: '<path/>' }`. Anything else
 * is stripped — keeps the schema-driven icon path safe even though only
 * admins can register tabs.
 */
const ALLOWED_SVG_TAGS =
	/^(?:path|circle|rect|line|polyline|polygon|g|ellipse)$/i;
const ALLOWED_SVG_ATTRS = new Set( [
	'd',
	'cx',
	'cy',
	'r',
	'x',
	'x1',
	'x2',
	'y',
	'y1',
	'y2',
	'rx',
	'ry',
	'width',
	'height',
	'points',
	'transform',
	'stroke-width',
	'stroke-linecap',
	'stroke-linejoin',
	'fill',
	'opacity',
	'fill-rule',
	'clip-rule',
] );

function sanitizeRawSvg( markup ) {
	if ( typeof markup !== 'string' || ! markup ) {
		return '';
	}
	if ( /<script|on\w+\s*=|xlink:href|javascript:/i.test( markup ) ) {
		// eslint-disable-next-line no-console
		console.warn(
			'wooWallet.settings: refusing to render unsafe SVG markup'
		);
		return '';
	}
	// Strip any tag that isn't in the whitelist. Tag names only — attributes
	// are coarsely allow-listed by the regex above.
	const cleaned = markup.replace(
		/<\/?([a-zA-Z][a-zA-Z0-9-]*)\b([^>]*)>/g,
		( match, tag, attrs ) => {
			if ( ! ALLOWED_SVG_TAGS.test( tag ) ) {
				return '';
			}
			// Drop disallowed attributes.
			const safeAttrs = ( attrs || '' ).replace(
				/([a-zA-Z-]+)\s*=\s*("[^"]*"|'[^']*')/g,
				( m, name ) =>
					ALLOWED_SVG_ATTRS.has( name.toLowerCase() ) ? m : ''
			);
			return `<${
				match.startsWith( '</' ) ? '/' : ''
			}${ tag }${ safeAttrs }>`;
		}
	);
	return cleaned;
}

function getResolvedIcons() {
	return applyFilters( 'woo_wallet.settings.icons', { ...ICONS } );
}

/**
 * Resolve the `name` prop into something renderable. Accepts:
 *  1. A React element (returned verbatim)
 *  2. A function/component (rendered with { c, w })
 *  3. An object `{ svg: '<path .../>' }` (raw markup, sanitized)
 *  4. An object `{ dashicon: 'dashicons-shield' }` (renders dashicon span)
 *  5. A string starting with `dashicons-` (treated as a dashicon class)
 *  6. A string looked up against the resolved icon registry
 *
 * @param {*}      name  Icon descriptor — see the list above.
 * @param {Object} props Render props (color, strokeWidth, size).
 * @return {*} React node or null when the descriptor can't be resolved.
 */
function resolveIcon( name, props ) {
	if ( ! name ) {
		return null;
	}

	if ( isValidElement( name ) ) {
		return name;
	}
	if ( typeof name === 'function' ) {
		return createElement( name, {
			c: props.color,
			w: props.strokeWidth,
			size: props.size,
		} );
	}
	if ( typeof name === 'object' ) {
		if ( name.svg ) {
			const safe = sanitizeRawSvg( name.svg );
			if ( ! safe ) {
				return null;
			}
			return (
				<svg
					viewBox="0 0 24 24"
					stroke={ props.color }
					strokeWidth={ props.strokeWidth }
					strokeLinecap="round"
					strokeLinejoin="round"
					fill="none"
					dangerouslySetInnerHTML={ { __html: safe } }
				/>
			);
		}
		if ( name.dashicon ) {
			return (
				<span
					className={ `dashicons ${ name.dashicon }` }
					style={ {
						color: props.color,
						fontSize: props.size,
						width: props.size,
						height: props.size,
						lineHeight: 1,
					} }
				/>
			);
		}
		return null;
	}
	if ( typeof name === 'string' && name.indexOf( 'dashicons-' ) === 0 ) {
		return (
			<span
				className={ `dashicons ${ name }` }
				style={ {
					color: props.color,
					fontSize: props.size,
					width: props.size,
					height: props.size,
					lineHeight: 1,
				} }
			/>
		);
	}

	const registry = getResolvedIcons();
	const Render = registry[ name ];
	if ( ! Render ) {
		return null;
	}
	return createElement( Render, {
		c: props.color,
		w: props.strokeWidth,
		size: props.size,
	} );
}

export default function Icon( {
	name,
	size = 18,
	color = 'currentColor',
	strokeWidth = 1.7,
} ) {
	const rendered = resolveIcon( name, { size, color, strokeWidth } );
	if ( ! rendered ) {
		return null;
	}
	return (
		<span
			style={ {
				display: 'inline-flex',
				alignItems: 'center',
				width: size,
				height: size,
				flexShrink: 0,
			} }
			aria-hidden="true"
		>
			{ rendered }
		</span>
	);
}
