import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from './Icon';
import { getFieldTypes } from '../registry/fieldTypes';

function SaveButton( { onClick, saving, saved } ) {
	return (
		<button
			type="button"
			onClick={ onClick }
			disabled={ saving }
			style={ {
				display: 'inline-flex', alignItems: 'center', gap: 6,
				padding: '9px 18px', borderRadius: 8, border: 'none', cursor: 'pointer',
				background: saved ? 'oklch(0.6 0.16 155)' : 'var(--ww-accent)',
				color: 'white', fontSize: 13, fontWeight: 600, fontFamily: 'inherit',
				transition: 'all 0.2s', opacity: saving ? 0.7 : 1,
			} }
		>
			<Icon name={ saved ? 'check' : 'save' } size={ 14 } color="white"/>
			{ saving ? __( 'Saving…', 'woo-wallet' ) : saved ? __( 'Saved!', 'woo-wallet' ) : __( 'Save', 'woo-wallet' ) }
		</button>
	);
}

function ActionCard( { action, onSave, saving, saved } ) {
	const [ open, setOpen ] = useState( false );
	const [ localValues, setLocalValues ] = useState( () => ( { ...( action.values || {} ) } ) );
	const fieldTypes = getFieldTypes();

	const enabledVal = localValues.enabled ?? ( action.enabled ? 'yes' : 'no' );
	const isEnabled  = enabledVal === 'yes' || enabledVal === true || enabledVal === 1;

	const setVal = ( key, val ) => setLocalValues( ( prev ) => ( { ...prev, [ key ]: val } ) );

	const headBg = open ? 'var(--ww-accent-bg)' : 'transparent';

	return (
		<div style={ {
			border: `1.5px solid ${ isEnabled ? 'oklch(0.55 0.16 185 / 0.35)' : 'var(--ww-border)' }`,
			borderRadius: 12, overflow: 'hidden', transition: 'border-color 0.2s',
		} }>
			{ /* Header row */ }
			<div
				onClick={ () => setOpen( ( o ) => ! o ) }
				style={ {
					display: 'flex', alignItems: 'center', gap: 12, padding: '14px 18px',
					cursor: 'pointer', background: headBg, transition: 'background 0.15s',
					userSelect: 'none',
				} }
			>
				<div style={ { flex: 1 } }>
					<p
						style={ { fontSize: 13, fontWeight: 600, color: 'var(--ww-text-heading)', margin: 0 } }
						dangerouslySetInnerHTML={ { __html: action.title } }
					/>
					{ action.description && (
						<p
							style={ {
								fontSize: 11, margin: '2px 0 0',
								color: isEnabled ? 'var(--ww-accent)' : 'var(--ww-text-muted)',
							} }
							dangerouslySetInnerHTML={ { __html: isEnabled ? __( '● Enabled', 'woo-wallet' ) : action.description } }
						/>
					) }
				</div>

				{ /* Enable toggle — click doesn't propagate to accordion */ }
				<div onClick={ ( e ) => e.stopPropagation() }>
					<button
						type="button"
						role="switch"
						aria-checked={ isEnabled }
						onClick={ () => setVal( 'enabled', isEnabled ? 'no' : 'yes' ) }
						style={ {
							width: 48, height: 26, borderRadius: 13,
							background: isEnabled ? 'var(--ww-accent)' : 'var(--ww-toggle-off-bg)',
							border: 'none', cursor: 'pointer', position: 'relative',
							transition: 'background 0.25s', flexShrink: 0,
						} }
					>
						<span style={ {
							position: 'absolute', top: 3,
							left: isEnabled ? 25 : 3,
							width: 20, height: 20, borderRadius: '50%',
							background: 'white',
							boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.25)',
							transition: 'left 0.22s cubic-bezier(.4,0,.2,1)',
							display: 'block',
						} }/>
					</button>
				</div>

				<div style={ {
					color: 'var(--ww-text-muted)',
					transform: open ? 'rotate(180deg)' : 'none',
					transition: 'transform 0.2s',
				} }>
					<Icon name="chevron" size={ 16 }/>
				</div>
			</div>

			{ /* Expanded fields */ }
			{ open && (
				<div style={ {
					padding: '18px 20px', borderTop: '1px solid var(--ww-border)',
					background: 'var(--ww-section-body-bg)',
					display: 'flex', flexDirection: 'column', gap: 18,
				} }>
					{ action.fields.filter( ( f ) => f.name !== 'enabled' ).map( ( field ) => {
						const FieldComponent = fieldTypes[ field.type ];
						if ( ! FieldComponent ) return null;

						if ( field.type === 'checkbox' ) {
							return (
								<FieldComponent
									key={ field.name }
									value={ localValues[ field.name ] ?? field.default ?? '' }
									onChange={ ( v ) => setVal( field.name, v ) }
									field={ field }
								/>
							);
						}

						return (
							<div key={ field.name } style={ {
								display: 'grid',
								gridTemplateColumns: '200px 1fr',
								gap: '0 20px', alignItems: 'start',
							} }>
								<div style={ { paddingTop: 10 } }>
									<label
										style={ { fontSize: 13, fontWeight: 500, color: 'var(--ww-text-label)', display: 'block' } }
										dangerouslySetInnerHTML={ { __html: field.label } }
									/>
								</div>
								<div>
									<FieldComponent
										value={ localValues[ field.name ] ?? field.default ?? '' }
										onChange={ ( v ) => setVal( field.name, v ) }
										field={ field }
									/>
									{ field.hint && (
										<p
											style={ { marginTop: 5, fontSize: 12, color: 'var(--ww-text-hint)', lineHeight: 1.5 } }
											dangerouslySetInnerHTML={ { __html: field.hint } }
										/>
									) }
								</div>
							</div>
						);
					} ) }

					<div style={ { display: 'flex', justifyContent: 'flex-end', marginTop: 4 } }>
						<SaveButton
							onClick={ () => onSave( action.id, localValues ) }
							saving={ saving }
							saved={ saved }
						/>
					</div>
				</div>
			) }
		</div>
	);
}

export default function ActionsPanel( { actions, onSave, saving, saved } ) {
	if ( ! actions || actions.length === 0 ) {
		return (
			<div style={ { padding: '40px 24px', textAlign: 'center' } }>
				<p style={ { color: 'var(--ww-text-muted)', fontSize: 14 } }>
					{ __( 'No actions registered.', 'woo-wallet' ) }
				</p>
			</div>
		);
	}

	return (
		<div style={ {
			background: 'var(--ww-surface)',
			border: '1px solid var(--ww-border)',
			borderRadius: 14, overflow: 'hidden',
			boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.04)',
		} }>
			<div style={ { padding: '18px 24px 16px', borderBottom: '1px solid var(--ww-border)' } }>
				<div style={ { display: 'flex', alignItems: 'center', gap: 8 } }>
					<Icon name="actions" size={ 15 } color="var(--ww-accent)"/>
					<h3 style={ {
						fontSize: 14, fontWeight: 600, color: 'var(--ww-text-heading)', margin: 0,
					} }>{ __( 'Wallet Actions', 'woo-wallet' ) }</h3>
				</div>
				<p style={ {
					fontSize: 12, color: 'var(--ww-text-muted)', marginTop: 4,
				} }>
					{ __( 'Configure automated wallet credits for user events. Enable each action and set the credit amount.', 'woo-wallet' ) }
				</p>
			</div>
			<div style={ { padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 10 } }>
				{ actions.map( ( action ) => (
					<ActionCard
						key={ action.id }
						action={ action }
						onSave={ onSave }
						saving={ saving[ action.id ] }
						saved={ saved[ action.id ] }
					/>
				) ) }
			</div>
		</div>
	);
}
