import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

// Visual contract:
//   • Always renders an inspection card showing detected provider, base/active
//     currencies, and effective mode — even when the per_currency feature flag
//     is off (so admins can verify which adapter the manager picked).
//   • Renders the mode-toggle UI only when the per_currency flag is on. Until
//     PR4 GA the flag is filtered to false; the card stays read-only and shows
//     a "feature not enabled" hint instead of a toggle.
//   • Renders a yellow warning banner when mode === 'per_currency' AND the
//     active provider is the generic fallback, since conversion is a noop in
//     that combination and the admin needs to know.

const ENDPOINT = '/wc/v3/wallet/multicurrency';

function StateRow( { label, value, mono } ) {
	return (
		<div style={ {
			display: 'flex',
			alignItems: 'center',
			justifyContent: 'space-between',
			gap: 16,
			padding: '10px 0',
			borderBottom: '1px solid var(--ww-border)',
		} }>
			<span style={ { fontSize: 13, color: 'var(--ww-text-muted)' } }>{ label }</span>
			<span style={ {
				fontSize: 13,
				fontWeight: 600,
				color: 'var(--ww-text)',
				fontFamily: mono ? 'ui-monospace, SFMono-Regular, Menlo, monospace' : 'inherit',
			} }>
				{ value }
			</span>
		</div>
	);
}

function Banner( { tone, children } ) {
	const palette = tone === 'warning'
		? { bg: 'oklch(0.95 0.06 85)', border: 'oklch(0.7 0.15 85)', text: 'oklch(0.4 0.12 60)' }
		: { bg: 'oklch(0.96 0.03 230)', border: 'oklch(0.7 0.1 230)', text: 'oklch(0.4 0.12 240)' };
	return (
		<div style={ {
			background: palette.bg,
			border: `1px solid ${ palette.border }`,
			color: palette.text,
			borderRadius: 10,
			padding: '12px 14px',
			fontSize: 13,
			lineHeight: 1.5,
		} }>
			{ children }
		</div>
	);
}

function ConfirmDialog( { open, title, body, confirmLabel, onConfirm, onCancel } ) {
	if ( ! open ) return null;
	return (
		<div style={ {
			position: 'fixed', inset: 0, zIndex: 99999,
			background: 'oklch(0.2 0.03 260 / 0.4)',
			display: 'flex', alignItems: 'center', justifyContent: 'center',
		} } onClick={ onCancel }>
			<div
				onClick={ ( e ) => e.stopPropagation() }
				style={ {
					background: 'var(--ww-surface)',
					border: '1px solid var(--ww-border)',
					borderRadius: 14,
					padding: 24,
					maxWidth: 460,
					boxShadow: '0 12px 40px oklch(0.2 0.03 260 / 0.18)',
				} }
			>
				<h3 style={ {
					fontSize: 16, fontWeight: 700, color: 'var(--ww-text-heading)',
					margin: '0 0 12px',
				} }>{ title }</h3>
				<div style={ {
					fontSize: 13, color: 'var(--ww-text)', lineHeight: 1.6,
					marginBottom: 18,
				} }>{ body }</div>
				<div style={ { display: 'flex', justifyContent: 'flex-end', gap: 10 } }>
					<button
						type="button"
						onClick={ onCancel }
						style={ {
							padding: '8px 16px', fontSize: 13, fontWeight: 600,
							background: 'transparent',
							color: 'var(--ww-text)',
							border: '1px solid var(--ww-border)',
							borderRadius: 8, cursor: 'pointer',
						} }
					>
						{ __( 'Cancel', 'woo-wallet' ) }
					</button>
					<button
						type="button"
						onClick={ onConfirm }
						style={ {
							padding: '8px 16px', fontSize: 13, fontWeight: 600,
							background: 'oklch(0.55 0.16 30)',
							color: 'white', border: 'none',
							borderRadius: 8, cursor: 'pointer',
						} }
					>
						{ confirmLabel }
					</button>
				</div>
			</div>
		</div>
	);
}

export default function CurrencyModePanel( { value, onChange } ) {
	const [ state, setState ]     = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ]     = useState( null );
	const [ pendingMode, setPendingMode ] = useState( null );

	const refresh = useCallback( () => {
		setLoading( true );
		apiFetch( { path: ENDPOINT } )
			.then( ( data ) => {
				setState( data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err?.message || __( 'Failed to load currency state.', 'woo-wallet' ) );
				setLoading( false );
			} );
	}, [] );

	useEffect( () => {
		refresh();
	}, [ refresh ] );

	if ( loading ) {
		return (
			<div style={ {
				background: 'var(--ww-surface)',
				border: '1px solid var(--ww-border)',
				borderRadius: 14, padding: 20,
				fontSize: 13, color: 'var(--ww-text-muted)',
			} }>{ __( 'Loading currency configuration…', 'woo-wallet' ) }</div>
		);
	}
	if ( error ) {
		return (
			<div style={ {
				background: 'var(--ww-surface)',
				border: '1px solid var(--ww-border)',
				borderRadius: 14, padding: 20,
				fontSize: 13, color: 'oklch(0.55 0.2 30)',
			} }>{ error }</div>
		);
	}
	if ( ! state ) return null;

	const {
		base_currency, active_currency,
		mode, mode_setting, per_currency_enabled,
		active_provider, all_providers, supported_currencies,
	} = state;

	const providerLabel = active_provider
		? `${ active_provider.label } (${ active_provider.id })`
		: __( 'None — falling back to base', 'woo-wallet' );

	const showWarning = mode === 'per_currency' && active_provider && active_provider.id === 'generic';

	// The setting value is the `wallet_currency_mode` field — its visibility in
	// the schema is gated by the same filter as `per_currency_enabled`. When
	// the flag is off we still want to show the inspection card, but the radio
	// belongs to the field-rendering pipeline, not here.
	const liveMode = value || mode_setting || 'single_base';

	const handleModeClick = ( next ) => {
		if ( next === liveMode ) return;
		setPendingMode( next );
	};

	const confirmMode = () => {
		if ( pendingMode && onChange ) onChange( pendingMode );
		setPendingMode( null );
	};

	return (
		<div style={ {
			background: 'var(--ww-surface)',
			border: '1px solid var(--ww-border)',
			borderRadius: 14,
			boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.04)',
			overflow: 'hidden',
		} }>
			<div style={ {
				padding: '18px 24px 14px',
				borderBottom: '1px solid var(--ww-border)',
			} }>
				<h3 style={ {
					fontSize: 15, fontWeight: 600,
					color: 'var(--ww-text-heading)', margin: 0,
				} }>{ __( 'Multi-Currency', 'woo-wallet' ) }</h3>
				<p style={ {
					fontSize: 12, color: 'var(--ww-text-muted)',
					margin: '4px 0 0',
				} }>{ __( 'Detected provider, currencies, and ledger storage mode.', 'woo-wallet' ) }</p>
			</div>

			<div style={ { padding: '8px 24px 4px' } }>
				<StateRow label={ __( 'Active provider', 'woo-wallet' ) } value={ providerLabel } />
				<StateRow label={ __( 'Base currency', 'woo-wallet' ) } value={ base_currency } mono />
				<StateRow label={ __( 'Active currency', 'woo-wallet' ) } value={ active_currency } mono />
				<StateRow
					label={ __( 'Effective mode', 'woo-wallet' ) }
					value={ mode === 'per_currency'
						? __( 'Per-currency sub-balances', 'woo-wallet' )
						: __( 'Single base currency', 'woo-wallet' )
					}
				/>
				{ Array.isArray( supported_currencies ) && supported_currencies.length > 0 && (
					<StateRow
						label={ __( 'Supported currencies', 'woo-wallet' ) }
						value={ supported_currencies.join( ', ' ) }
						mono
					/>
				) }
			</div>

			{ all_providers && all_providers.length > 1 && (
				<div style={ { padding: '12px 24px' } }>
					<details>
						<summary style={ {
							cursor: 'pointer', fontSize: 12,
							color: 'var(--ww-text-muted)', userSelect: 'none',
						} }>
							{ __( 'Registered providers', 'woo-wallet' ) }
						</summary>
						<ul style={ {
							margin: '8px 0 0', padding: '0 0 0 18px',
							fontSize: 12, color: 'var(--ww-text)',
						} }>
							{ all_providers.map( ( p ) => (
								<li key={ p.id } style={ { padding: '2px 0' } }>
									<code>{ p.id }</code>
									{ ' — ' }
									{ p.label }
									{ ' ' }
									<span style={ {
										color: p.available ? 'oklch(0.55 0.16 155)' : 'var(--ww-text-muted)',
										fontWeight: 600,
									} }>
										{ p.available ? __( '(available)', 'woo-wallet' ) : __( '(not loaded)', 'woo-wallet' ) }
									</span>
								</li>
							) ) }
						</ul>
					</details>
				</div>
			) }

			{ showWarning && (
				<div style={ { padding: '0 24px 16px' } }>
					<Banner tone="warning">
						{ __( 'Per-currency mode is active but no multi-currency plugin is detected. Conversions will fall open (1:1) until a supported plugin is installed.', 'woo-wallet' ) }
					</Banner>
				</div>
			) }

			{ per_currency_enabled && onChange && (
				<div style={ {
					padding: '16px 24px 20px',
					borderTop: '1px solid var(--ww-border)',
					background: 'var(--ww-section-body-bg)',
					display: 'flex', flexDirection: 'column', gap: 12,
				} }>
					<div>
						<label style={ {
							fontSize: 13, fontWeight: 600,
							color: 'var(--ww-text-label)', display: 'block',
						} }>{ __( 'Storage mode', 'woo-wallet' ) }</label>
						<p style={ {
							margin: '3px 0 0', fontSize: 12, color: 'var(--ww-text-muted)',
							lineHeight: 1.5,
						} }>
							{ __( 'Single base normalises every row to the shop currency. Per-currency keeps each row in its source currency and tracks sub-balances independently.', 'woo-wallet' ) }
						</p>
					</div>
					<div style={ { display: 'flex', gap: 10, flexWrap: 'wrap' } }>
						{ [
							{ id: 'single_base', label: __( 'Single base currency', 'woo-wallet' ) },
							{ id: 'per_currency', label: __( 'Per-currency sub-balances', 'woo-wallet' ) },
						].map( ( opt ) => {
							const checked = liveMode === opt.id;
							return (
								<button
									key={ opt.id }
									type="button"
									onClick={ () => handleModeClick( opt.id ) }
									style={ {
										flex: '1 1 220px',
										padding: '12px 14px',
										border: `1.5px solid ${ checked ? 'var(--ww-accent)' : 'var(--ww-border)' }`,
										background: checked ? 'var(--ww-accent-light)' : 'var(--ww-surface)',
										color: 'var(--ww-text)',
										borderRadius: 10, cursor: 'pointer',
										fontSize: 13, fontWeight: 600, textAlign: 'left',
										transition: 'border-color 0.15s, background 0.15s',
									} }
								>
									{ opt.label }
								</button>
							);
						} ) }
					</div>
				</div>
			) }

			{ ! per_currency_enabled && (
				<div style={ {
					padding: '12px 24px 18px',
					fontSize: 12, color: 'var(--ww-text-muted)',
				} }>
					{ __( 'Per-currency mode is not yet available on this site. Single base mode is in use.', 'woo-wallet' ) }
				</div>
			) }

			<ConfirmDialog
				open={ !! pendingMode }
				title={ pendingMode === 'per_currency'
					? __( 'Switch to per-currency mode?', 'woo-wallet' )
					: __( 'Switch to single base mode?', 'woo-wallet' )
				}
				body={ pendingMode === 'per_currency'
					? __( 'Existing transaction rows will not be rewritten. Mixed-currency rows from before this change will become real per-currency rows on read. Test on a staging environment before applying to production.', 'woo-wallet' )
					: sprintf(
						/* translators: %s: base currency code */
						__( 'New rows will normalise to %s. Existing per-currency rows are preserved but will be excluded from the active-currency balance until a reconcile pass converts them.', 'woo-wallet' ),
						base_currency
					)
				}
				confirmLabel={ __( 'Yes, switch mode', 'woo-wallet' ) }
				onConfirm={ confirmMode }
				onCancel={ () => setPendingMode( null ) }
			/>
		</div>
	);
}
