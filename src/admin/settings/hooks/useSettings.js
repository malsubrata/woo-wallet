import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

let toastCounter = 0;

const TOAST_DURATION = 3500;

export default function useSettings() {
	const [ schema, setSchema ]     = useState( null );
	const [ values, setValues ]     = useState( {} );
	const [ loading, setLoading ]   = useState( true );
	const [ error, setError ]       = useState( null );
	const [ saving, setSaving ]     = useState( {} );
	const [ saved, setSaved ]       = useState( {} );
	const [ toasts, setToasts ]     = useState( [] );
	const timers                    = useRef( {} );

	useEffect( () => {
		apiFetch( { path: '/wc/v3/wallet/settings' } )
			.then( ( data ) => {
				setSchema( data );
				setValues( data.values || {} );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message || __( 'Failed to load settings.', 'woo-wallet' ) );
				setLoading( false );
			} );
	}, [] );

	const pushToast = useCallback( ( type, message ) => {
		const id = ++toastCounter;
		setToasts( ( prev ) => [ ...prev, { id, type, message, duration: TOAST_DURATION } ] );
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
			[ sectionId ]: { ...( prev[ sectionId ] || {} ), [ fieldName ]: value },
		} ) );
	}, [] );

	const saveSection = useCallback(
		async ( sectionId ) => {
			setSaving( ( s ) => ( { ...s, [ sectionId ]: true } ) );

			const schemaFields  = ( schema?.fields?.[ sectionId ] ) || [];
			const sectionValues = values[ sectionId ] || {};
			const payload       = {};
			schemaFields.forEach( ( f ) => {
				payload[ f.name ] = sectionValues[ f.name ] !== undefined
					? sectionValues[ f.name ]
					: ( f.default ?? '' );
			} );

			const sectionTitle = schema?.sections?.find( ( s ) => s.id === sectionId )?.title || sectionId;

			try {
				const result = await apiFetch( {
					path: '/wc/v3/wallet/settings/section',
					method: 'POST',
					data: { section_id: sectionId, values: payload },
				} );
				setValues( ( v ) => ( { ...v, [ sectionId ]: result.values || {} } ) );
				setSaved( ( s ) => ( { ...s, [ sectionId ]: true } ) );
				setTimeout( () => setSaved( ( s ) => ( { ...s, [ sectionId ]: false } ) ), 2500 );
				pushToast( 'success', sectionTitle
					/* translators: %s: settings section title */
					? __( sectionTitle, 'woo-wallet' ) + ' ' + __( 'settings saved successfully.', 'woo-wallet' )
					: __( 'Settings saved successfully.', 'woo-wallet' )
				);
			} catch ( err ) {
				pushToast( 'error', err?.message || __( 'Failed to save settings.', 'woo-wallet' ) );
				// eslint-disable-next-line no-console
				console.error( 'TeraWallet: save section error', err );
			} finally {
				setSaving( ( s ) => ( { ...s, [ sectionId ]: false } ) );
			}
		},
		[ schema, values, pushToast ]
	);

	const saveAction = useCallback(
		async ( actionId, actionValues ) => {
			setSaving( ( s ) => ( { ...s, [ actionId ]: true } ) );
			try {
				const result = await apiFetch( {
					path: '/wc/v3/wallet/settings/action',
					method: 'POST',
					data: { action_id: actionId, values: actionValues },
				} );
				setSchema( ( prev ) => ( {
					...prev,
					actions: prev.actions.map( ( a ) =>
						a.id === actionId ? { ...a, values: result.values } : a
					),
				} ) );
				setSaved( ( s ) => ( { ...s, [ actionId ]: true } ) );
				setTimeout( () => setSaved( ( s ) => ( { ...s, [ actionId ]: false } ) ), 2500 );

				const actionTitle = schema?.actions?.find( ( a ) => a.id === actionId )?.title || actionId;
				pushToast( 'success', actionTitle
					? actionTitle + ' ' + __( 'action saved successfully.', 'woo-wallet' )
					: __( 'Action saved successfully.', 'woo-wallet' )
				);
			} catch ( err ) {
				pushToast( 'error', err?.message || __( 'Failed to save action.', 'woo-wallet' ) );
				// eslint-disable-next-line no-console
				console.error( 'TeraWallet: save action error', err );
			} finally {
				setSaving( ( s ) => ( { ...s, [ actionId ]: false } ) );
			}
		},
		[ schema, pushToast ]
	);

	return { schema, values, loading, error, saving, saved, toasts, dismissToast, setFieldValue, saveSection, saveAction };
}
