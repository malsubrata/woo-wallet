import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { getRegistry } from '../registry';

let toastCounter = 0;

const TOAST_DURATION = 3500;

export default function useSettings() {
	const [ schema, setSchema ] = useState( null );
	const [ values, setValues ] = useState( {} );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ saving, setSaving ] = useState( {} );
	const [ saved, setSaved ] = useState( {} );
	const [ toasts, setToasts ] = useState( [] );
	const timers = useRef( {} );

	useEffect( () => {
		apiFetch( { path: '/terawallet/v1/settings' } )
			.then( ( data ) => {
				setSchema( data );
				setValues( data.values || {} );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError(
					err.message ||
						__( 'Failed to load settings.', 'woo-wallet' )
				);
				setLoading( false );
			} );
	}, [] );

	const pushToast = useCallback( ( type, message ) => {
		const id = ++toastCounter;
		setToasts( ( prev ) => [
			...prev,
			{ id, type, message, duration: TOAST_DURATION },
		] );
		timers.current[ id ] = setTimeout( () => {
			dismissToast( id );
			delete timers.current[ id ];
		}, TOAST_DURATION );
	}, [] );

	// Exposed so clicking a toast dismisses it immediately.
	const dismissToast = useCallback( ( id ) => {
		if ( timers.current[ id ] ) {
			clearTimeout( timers.current[ id ] );
			delete timers.current[ id ];
		}
		setToasts( ( prev ) => prev.filter( ( t ) => t.id !== id ) );
	}, [] );

	const setFieldValue = useCallback( ( sectionId, fieldName, value ) => {
		setValues( ( prev ) => ( {
			...prev,
			[ sectionId ]: {
				...( prev[ sectionId ] || {} ),
				[ fieldName ]: value,
			},
		} ) );
	}, [] );

	const saveSection = useCallback(
		async ( sectionId ) => {
			setSaving( ( s ) => ( { ...s, [ sectionId ]: true } ) );

			// JS-registered tabs (and JS field additions) only land in the
			// schema after the `woo_wallet.settings.schema` filter runs — the
			// raw REST payload doesn't know about them. Apply the filter here
			// so the save payload includes those fields.
			const resolvedSchema = schema
				? applyFilters( 'woo_wallet.settings.schema', schema )
				: schema;
			const schemaFields = resolvedSchema?.fields?.[ sectionId ] || [];
			const sectionValues = values[ sectionId ] || {};
			const payload = {};
			schemaFields.forEach( ( f ) => {
				payload[ f.name ] =
					sectionValues[ f.name ] !== undefined
						? sectionValues[ f.name ]
						: f.default ?? '';
			} );

			const sectionTitle =
				resolvedSchema?.sections?.find( ( s ) => s.id === sectionId )
					?.title || sectionId;
			const registry = getRegistry();
			const isJsTab = registry.isJsRegistered( sectionId );

			try {
				let result;
				if ( isJsTab ) {
					// JS-registered tab — send the fields_schema alongside so
					// the server can pick the right sanitizer per field.
					result = await apiFetch( {
						path: '/terawallet/v1/settings/js-section',
						method: 'POST',
						data: {
							section_id: sectionId,
							fields_schema:
								registry.getFieldsSchemaForSave( sectionId ),
							values: payload,
						},
					} );
					registry.runOnSave(
						sectionId,
						result.values || {},
						sectionValues
					);
				} else {
					result = await apiFetch( {
						path: '/terawallet/v1/settings/section',
						method: 'POST',
						data: { section_id: sectionId, values: payload },
					} );
				}
				setValues( ( v ) => ( {
					...v,
					[ sectionId ]: result.values || {},
				} ) );
				setSaved( ( s ) => ( { ...s, [ sectionId ]: true } ) );
				setTimeout(
					() =>
						setSaved( ( s ) => ( { ...s, [ sectionId ]: false } ) ),
					2500
				);
				pushToast(
					'success',
					sectionTitle
						? /* translators: %s: settings section title */
						  __( sectionTitle, 'woo-wallet' ) +
								' ' +
								__(
									'settings saved successfully.',
									'woo-wallet'
								)
						: __( 'Settings saved successfully.', 'woo-wallet' )
				);
			} catch ( err ) {
				pushToast(
					'error',
					err?.message ||
						__( 'Failed to save settings.', 'woo-wallet' )
				);
				// eslint-disable-next-line no-console
				console.error( 'TeraWallet: save section error', err );
			} finally {
				setSaving( ( s ) => ( { ...s, [ sectionId ]: false } ) );
			}
		},
		[ schema, values, pushToast ]
	);

	return {
		schema,
		values,
		loading,
		error,
		saving,
		saved,
		toasts,
		dismissToast,
		setFieldValue,
		saveSection,
	};
}
