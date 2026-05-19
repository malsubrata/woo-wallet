/**
 * TeraWallet settings registry.
 *
 * Public, JS-first extension API. Third-party plugins enqueue a script after
 * the main `woo-wallet-admin-settings` bundle and call:
 *
 *   window.wooWallet.settings.registerTab( { ... } );
 *
 * The registry is the canonical entry point. Under the hood it hooks the
 * existing `wp.hooks` filters (`woo_wallet.settings.schema`,
 * `woo_wallet.settings.fieldTypes`, `woo_wallet.settings.icons`) so the React
 * app picks up the additions transparently.
 *
 * Legacy PHP filters (`woo_wallet_settings_sections`,
 * `woo_wallet_settings_fields`) continue to work for back-compat. New
 * integrations should prefer this JS API.
 *
 * @see docs/EXTENDING_SETTINGS.md
 */
import { addFilter } from '@wordpress/hooks';

const HOOK_NAMESPACE = 'woo-wallet/settings-registry';

/**
 * Whitelist of sanitization hints the server will honor. Anything outside this
 * list is coerced to `text` on the server, so it's safe to mirror the same
 * list client-side as the validation surface.
 */
const SANITIZE_HINTS = [
	'text',
	'textarea',
	'kses_post',
	'number',
	'absint',
	'float',
	'bool',
	'email',
	'url',
	'key',
	'array_of_text',
	'array_of_int',
	'attachment_id',
	'color_hex',
];

/**
 * Map a field `type` to the default sanitize hint used when the registrant
 * doesn't supply one explicitly. Keeps the JS-only flow ergonomic for the
 * common case.
 */
const TYPE_TO_DEFAULT_SANITIZE = {
	checkbox: 'bool',
	number: 'float',
	email: 'email',
	url: 'url',
	attachment: 'attachment_id',
	file: 'attachment_id',
	color: 'color_hex',
	multiselect: 'array_of_text',
	multicheck: 'array_of_text',
	textarea: 'textarea',
};

function logError( method, message, payload ) {
	// eslint-disable-next-line no-console
	console.error( `wooWallet.settings.${ method }: ${ message }`, payload );
}

function normalizeField( field, methodLabel ) {
	if ( ! field || typeof field !== 'object' ) {
		logError( methodLabel, 'field must be an object', field );
		return null;
	}
	if ( ! field.name || typeof field.name !== 'string' ) {
		logError(
			methodLabel,
			'field requires a non-empty string `name`',
			field
		);
		return null;
	}
	if ( ! field.type || typeof field.type !== 'string' ) {
		logError( methodLabel, 'field requires a string `type`', field );
		return null;
	}

	const out = { ...field };
	// Coerce sanitize hint to whitelist or derive from type.
	if ( out.sanitize && ! SANITIZE_HINTS.includes( out.sanitize ) ) {
		logError(
			methodLabel,
			`unknown sanitize hint "${ out.sanitize }"; falling back to type-derived default`,
			field
		);
		delete out.sanitize;
	}
	if ( ! out.sanitize ) {
		out.sanitize = TYPE_TO_DEFAULT_SANITIZE[ out.type ] || 'text';
	}
	return out;
}

/**
 * Build the registry singleton. Kept as a closure so the internal stores stay
 * private — host plugins can only interact through the exposed methods.
 */
function createRegistry() {
	const tabs = new Map(); // id -> tab descriptor
	const tabOrder = []; // insertion-ordered ids (sort by priority on render)
	const fieldAdditions = new Map(); // existing-section-id -> [ fields ]
	const customFieldTypes = {}; // type-name -> React component
	const customIcons = {}; // icon-name -> React render fn
	const onSaveHandlers = new Map(); // tab id -> onSave fn
	let hooksAttached = false;

	function attachHooksOnce() {
		if ( hooksAttached ) {
			return;
		}
		hooksAttached = true;

		addFilter( 'woo_wallet.settings.schema', HOOK_NAMESPACE, ( schema ) => {
			if ( ! schema ) {
				return schema;
			}

			const next = {
				...schema,
				sections: [ ...( schema.sections || [] ) ],
				fields: { ...( schema.fields || {} ) },
				values: { ...( schema.values || {} ) },
			};

			// 1. Append registered tabs and stitch their fields/values in.
			const sorted = tabOrder
				.map( ( id ) => tabs.get( id ) )
				.filter( Boolean )
				.sort(
					( a, b ) => ( a.priority ?? 50 ) - ( b.priority ?? 50 )
				);

			sorted.forEach( ( tab ) => {
				next.sections.push( {
					id: tab.id,
					title: tab.title,
					description: tab.description || '',
					icon: tab.icon || 'settings',
					_jsRegistered: true,
				} );
				next.fields[ tab.id ] = ( tab.fields || [] ).map( ( f ) => ( {
					...f,
				} ) );

				// Seed missing values with defaults — keeps the form usable
				// before the user opens the tab for the first time.
				if ( ! next.values[ tab.id ] ) {
					const seeded = {};
					( tab.fields || [] ).forEach( ( f ) => {
						if ( f.default !== undefined ) {
							seeded[ f.name ] = f.default;
						}
					} );
					next.values[ tab.id ] = seeded;
				}
			} );

			// 2. Inject fields into pre-existing (built-in or PHP-filter) tabs.
			fieldAdditions.forEach( ( fields, sectionId ) => {
				if ( ! next.fields[ sectionId ] ) {
					next.fields[ sectionId ] = [];
				}
				next.fields[ sectionId ] = [
					...next.fields[ sectionId ],
					...fields.map( ( f ) => ( { ...f } ) ),
				];
			} );

			return next;
		} );

		addFilter(
			'woo_wallet.settings.fieldTypes',
			HOOK_NAMESPACE,
			( types ) => ( { ...types, ...customFieldTypes } )
		);

		addFilter( 'woo_wallet.settings.icons', HOOK_NAMESPACE, ( icons ) => ( {
			...icons,
			...customIcons,
		} ) );
	}

	function registerTab( descriptor ) {
		attachHooksOnce();

		if ( ! descriptor || typeof descriptor !== 'object' ) {
			logError(
				'registerTab',
				'descriptor must be an object',
				descriptor
			);
			return false;
		}

		const { id, title, fields } = descriptor;

		if ( ! id || typeof id !== 'string' || ! /^[a-z0-9_]+$/.test( id ) ) {
			logError(
				'registerTab',
				'id must be a non-empty lowercase string of [a-z0-9_] characters',
				descriptor
			);
			return false;
		}
		if ( ! title || typeof title !== 'string' ) {
			logError(
				'registerTab',
				'title must be a non-empty string',
				descriptor
			);
			return false;
		}
		if ( tabs.has( id ) ) {
			logError(
				'registerTab',
				`tab "${ id }" already registered — second call ignored`,
				descriptor
			);
			return false;
		}

		const normalizedFields = ( Array.isArray( fields ) ? fields : [] )
			.map( ( f ) => normalizeField( f, 'registerTab' ) )
			.filter( Boolean );

		tabs.set( id, {
			id,
			title,
			description: descriptor.description || '',
			icon: descriptor.icon || 'settings',
			priority: Number.isFinite( descriptor.priority )
				? descriptor.priority
				: 50,
			fields: normalizedFields,
		} );
		tabOrder.push( id );

		if ( typeof descriptor.onSave === 'function' ) {
			onSaveHandlers.set( id, descriptor.onSave );
		}

		return true;
	}

	function registerField( sectionId, field ) {
		attachHooksOnce();

		if ( ! sectionId || typeof sectionId !== 'string' ) {
			logError( 'registerField', 'sectionId must be a non-empty string', {
				sectionId,
				field,
			} );
			return false;
		}
		const normalized = normalizeField( field, 'registerField' );
		if ( ! normalized ) {
			return false;
		}

		if ( tabs.has( sectionId ) ) {
			// Field on a tab we registered ourselves — append directly so it
			// participates in our save-side fields_schema.
			const tab = tabs.get( sectionId );
			tab.fields.push( normalized );
		} else {
			// Field on a built-in or PHP-filter section — go through the
			// schema filter; save still goes via the legacy endpoint, so we
			// don't include this field's sanitize hint over the wire. The
			// existing PHP-side sanitization handles those.
			if ( ! fieldAdditions.has( sectionId ) ) {
				fieldAdditions.set( sectionId, [] );
			}
			fieldAdditions.get( sectionId ).push( normalized );
		}
		return true;
	}

	function registerFieldType( typeName, Component ) {
		attachHooksOnce();
		if ( ! typeName || typeof typeName !== 'string' ) {
			logError(
				'registerFieldType',
				'typeName must be a non-empty string',
				{ typeName }
			);
			return false;
		}
		if ( typeof Component !== 'function' ) {
			logError(
				'registerFieldType',
				'Component must be a React component (function)',
				{ typeName }
			);
			return false;
		}
		customFieldTypes[ typeName ] = Component;
		return true;
	}

	function registerIcon( iconName, renderFn ) {
		attachHooksOnce();
		if ( ! iconName || typeof iconName !== 'string' ) {
			logError( 'registerIcon', 'iconName must be a non-empty string', {
				iconName,
			} );
			return false;
		}
		if ( typeof renderFn !== 'function' ) {
			logError(
				'registerIcon',
				'renderFn must be a function that returns SVG',
				{ iconName }
			);
			return false;
		}
		customIcons[ iconName ] = renderFn;
		return true;
	}

	function getTab( id ) {
		const t = tabs.get( id );
		if ( ! t ) {
			return null;
		}
		// Return a defensive copy so callers can't mutate internal state.
		return { ...t, fields: t.fields.map( ( f ) => ( { ...f } ) ) };
	}

	function unregisterTab( id ) {
		if ( ! tabs.has( id ) ) {
			return false;
		}
		tabs.delete( id );
		const idx = tabOrder.indexOf( id );
		if ( idx !== -1 ) {
			tabOrder.splice( idx, 1 );
		}
		onSaveHandlers.delete( id );
		return true;
	}

	function isJsRegistered( id ) {
		return tabs.has( id );
	}

	/**
	 * Build the `fields_schema` payload sent to the server on save. Includes
	 * only `name` + `sanitize` per field — the minimum the server needs to
	 * pick the right sanitizer. Other field metadata (label, options, etc.)
	 * is purely client-side.
	 *
	 * @param {string} id Tab id whose schema to serialize.
	 * @return {Array<{name: string, sanitize: string}>} Wire-format schema.
	 */
	function getFieldsSchemaForSave( id ) {
		const tab = tabs.get( id );
		if ( ! tab ) {
			return [];
		}
		return tab.fields.map( ( f ) => ( {
			name: f.name,
			sanitize: f.sanitize,
		} ) );
	}

	function runOnSave( id, newValues, oldValues ) {
		const handler = onSaveHandlers.get( id );
		if ( typeof handler !== 'function' ) {
			return;
		}
		try {
			handler( newValues, oldValues );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error(
				`wooWallet.settings: onSave for "${ id }" threw`,
				err
			);
		}
	}

	return {
		registerTab,
		registerField,
		registerFieldType,
		registerIcon,
		getTab,
		unregisterTab,
		isJsRegistered,
		getFieldsSchemaForSave,
		runOnSave,
		// Surface the whitelist so authors can introspect it if needed.
		SANITIZE_HINTS: [ ...SANITIZE_HINTS ],
	};
}

let singleton = null;

/**
 * Get the registry singleton. Constructs it on first call and attaches it to
 * `window.wooWallet.settings` for global access from host plugin scripts.
 */
export function getRegistry() {
	if ( singleton ) {
		return singleton;
	}

	singleton = createRegistry();

	if ( typeof window !== 'undefined' ) {
		window.wooWallet = window.wooWallet || {};
		window.wooWallet.settings = singleton;
		try {
			document.dispatchEvent(
				new CustomEvent( 'wooWallet.settings.ready', {
					detail: { registry: singleton },
				} )
			);
		} catch ( e ) {
			/* IE/old envs — ignore */
		}
	}

	return singleton;
}

// Named re-exports for ES-module consumers.
export const registerTab = ( ...args ) => getRegistry().registerTab( ...args );
export const registerField = ( ...args ) =>
	getRegistry().registerField( ...args );
export const registerFieldType = ( ...args ) =>
	getRegistry().registerFieldType( ...args );
export const registerIcon = ( ...args ) =>
	getRegistry().registerIcon( ...args );
