import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getFieldTypes } from '../registry/fieldTypes';
import CheckboxField from '../fields/CheckboxField';
import Icon from './Icon';

function SaveButton( { onClick, saving, saved } ) {
	return (
		<button
			type="button"
			onClick={ onClick }
			disabled={ saving }
			style={ {
				display: 'inline-flex',
				alignItems: 'center',
				gap: 8,
				padding: '11px 24px',
				borderRadius: 10,
				border: 'none',
				cursor: 'pointer',
				background: saved ? 'oklch(0.6 0.16 155)' : 'var(--ww-accent)',
				color: 'white',
				fontSize: 14,
				fontWeight: 600,
				fontFamily: 'inherit',
				transition: 'all 0.2s',
				boxShadow: '0 4px 14px oklch(0.55 0.16 185 / 0.3)',
				opacity: saving ? 0.7 : 1,
			} }
		>
			<Icon name={ saved ? 'check' : 'save' } size={ 16 } color="white" />
			{ saving
				? __( 'Saving…', 'woo-wallet' )
				: saved
				? __( 'Saved!', 'woo-wallet' )
				: __( 'Save Changes', 'woo-wallet' ) }
		</button>
	);
}

function evaluateCondition( cond, values ) {
	const { field: depField, equals } = cond;
	const val = values[ depField ];
	if ( Array.isArray( equals ) ) {
		return equals.includes( val );
	}
	return String( val ) === String( equals );
}

function isVisible( field, sectionValues ) {
	if ( ! field.show_if ) {
		return true;
	}
	const conds = Array.isArray( field.show_if )
		? field.show_if
		: [ field.show_if ];
	return conds.every( ( c ) => evaluateCondition( c, sectionValues ) );
}

function StackedField( { field, value, onChange } ) {
	const fieldTypes = getFieldTypes();
	const FieldComponent = fieldTypes[ field.type ];
	if ( ! FieldComponent ) {
		return null;
	}

	if ( field.type === 'checkbox' ) {
		return (
			<FieldComponent
				value={ value }
				onChange={ onChange }
				field={ field }
			/>
		);
	}

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: 10 } }>
			<div>
				<label
					style={ {
						fontSize: 13,
						fontWeight: 600,
						color: 'var(--ww-text-label)',
						display: 'block',
					} }
					dangerouslySetInnerHTML={ { __html: field.label } }
				/>
				{ field.hint && (
					<p
						style={ {
							margin: '3px 0 0',
							fontSize: 12,
							color: 'var(--ww-text-muted)',
							lineHeight: 1.5,
						} }
						dangerouslySetInnerHTML={ { __html: field.hint } }
					/>
				) }
			</div>
			<FieldComponent
				value={ value }
				onChange={ onChange }
				field={ field }
			/>
		</div>
	);
}

function renderFieldRows( fields, sectionValues, onChange ) {
	const rows = [];
	let pendingHalf = null;

	const pushHalf = ( field ) => {
		if ( pendingHalf ) {
			rows.push( [ pendingHalf, field ] );
			pendingHalf = null;
		} else {
			pendingHalf = field;
		}
	};
	const flushHalf = () => {
		if ( pendingHalf ) {
			rows.push( [ pendingHalf ] );
			pendingHalf = null;
		}
	};

	fields.forEach( ( field ) => {
		if ( ! isVisible( field, sectionValues ) ) {
			return;
		}
		if ( field.type === 'section_heading' ) {
			flushHalf();
			rows.push( [ field ] );
			return;
		}
		if ( field.half ) {
			pushHalf( field );
		} else {
			flushHalf();
			rows.push( [ field ] );
		}
	} );
	flushHalf();

	return rows.map( ( row, idx ) => {
		if ( row.length === 2 ) {
			return (
				<div key={ idx } className="ww-field-grid">
					{ row.map( ( field ) => (
						<StackedField
							key={ field.name }
							field={ field }
							value={
								sectionValues[ field.name ] ??
								field.default ??
								''
							}
							onChange={ ( v ) => onChange( field.name, v ) }
						/>
					) ) }
				</div>
			);
		}
		const field = row[ 0 ];
		if ( field.type === 'section_heading' ) {
			const HeadingComponent = getFieldTypes().section_heading;
			return HeadingComponent ? (
				<HeadingComponent key={ field.name } field={ field } />
			) : null;
		}
		return (
			<StackedField
				key={ field.name }
				field={ field }
				value={ sectionValues[ field.name ] ?? field.default ?? '' }
				onChange={ ( v ) => onChange( field.name, v ) }
			/>
		);
	} );
}

function partitionFields( fields ) {
	const groupMap = new Map();
	const loose = [];

	fields.forEach( ( field ) => {
		if ( ! field.group ) {
			loose.push( field );
			return;
		}
		if ( ! groupMap.has( field.group ) ) {
			groupMap.set( field.group, {
				id: field.group,
				type: field.group_type || 'regular',
				logo: field.group_logo || null,
				title: '',
				description: '',
				master: null,
				children: [],
			} );
		}
		const group = groupMap.get( field.group );
		if ( field.group_title && ! group.title ) {
			group.title = field.group_title;
		}
		if ( field.group_description && ! group.description ) {
			group.description = field.group_description;
		}
		if ( field.group_type && group.type === 'regular' ) {
			group.type = field.group_type;
		}
		if ( field.group_logo && ! group.logo ) {
			group.logo = field.group_logo;
		}

		if (
			! group.master &&
			field.type === 'checkbox' &&
			field.group_title
		) {
			group.master = field;
		} else {
			group.children.push( field );
		}
	} );

	const allGroups = Array.from( groupMap.values() );
	const gatewayGroups = allGroups.filter( ( g ) => g.type === 'gateway' );
	const actionGroups = allGroups.filter( ( g ) => g.type === 'action' );
	const regularGroups = allGroups.filter(
		( g ) => g.type !== 'gateway' && g.type !== 'action'
	);

	return { groups: regularGroups, gatewayGroups, actionGroups, loose };
}

function GroupCard( { group, values, onChange } ) {
	const master = group.master;
	const masterValue = master
		? values[ master.name ] ?? master.default ?? ''
		: 'on';
	const masterChecked =
		masterValue === 'on' ||
		masterValue === true ||
		masterValue === 1 ||
		masterValue === '1' ||
		masterValue === 'yes';
	const hasVisibleChildren =
		masterChecked && group.children.some( ( f ) => isVisible( f, values ) );

	return (
		<div
			style={ {
				background: 'var(--ww-surface)',
				border: `1.5px solid ${
					masterChecked
						? 'oklch(0.55 0.16 185 / 0.35)'
						: 'var(--ww-border)'
				}`,
				borderRadius: 14,
				boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.04)',
				transition: 'border-color 0.2s',
			} }
		>
			{ ( group.title || group.description ) && (
				<div
					style={ {
						padding: '18px 24px 16px',
						borderBottom: '1px solid var(--ww-border)',
					} }
				>
					<h3
						style={ {
							fontSize: 15,
							fontWeight: 600,
							color: 'var(--ww-text-heading)',
							margin: 0,
						} }
						dangerouslySetInnerHTML={ { __html: group.title } }
					/>
					{ group.description && (
						<p
							style={ {
								fontSize: 12,
								color: 'var(--ww-text-muted)',
								margin: '4px 0 0',
							} }
							dangerouslySetInnerHTML={ {
								__html: group.description,
							} }
						/>
					) }
				</div>
			) }

			<div
				style={ {
					padding: '20px 24px',
					display: 'flex',
					flexDirection: 'column',
					gap: 22,
					background: hasVisibleChildren
						? 'var(--ww-section-body-bg)'
						: 'transparent',
					borderBottomLeftRadius: 14,
					borderBottomRightRadius: 14,
				} }
			>
				{ master && (
					<StackedField
						field={ master }
						value={ masterValue }
						onChange={ ( v ) => onChange( master.name, v ) }
					/>
				) }
				{ hasVisibleChildren &&
					renderFieldRows( group.children, values, onChange ) }
			</div>
		</div>
	);
}

function ActionGroupCard( { group, values, onChange } ) {
	const [ open, setOpen ] = useState( false );

	const master = group.master;
	const masterValue = master
		? values[ master.name ] ?? master.default ?? ''
		: 'on';
	const isEnabled =
		masterValue === 'on' ||
		masterValue === true ||
		masterValue === 1 ||
		masterValue === '1' ||
		masterValue === 'yes';

	const headBg = open ? 'var(--ww-accent-bg)' : 'transparent';

	return (
		<div
			style={ {
				border: `1.5px solid ${
					isEnabled
						? 'oklch(0.55 0.16 185 / 0.35)'
						: 'var(--ww-border)'
				}`,
				borderRadius: 12,
				overflow: 'hidden',
				transition: 'border-color 0.2s',
			} }
		>
			<div
				onClick={ () => setOpen( ( o ) => ! o ) }
				style={ {
					display: 'flex',
					alignItems: 'center',
					gap: 12,
					padding: '14px 18px',
					cursor: 'pointer',
					background: headBg,
					transition: 'background 0.15s',
					userSelect: 'none',
				} }
			>
				<div style={ { flex: 1 } }>
					<p
						style={ {
							fontSize: 13,
							fontWeight: 600,
							color: 'var(--ww-text-heading)',
							margin: 0,
						} }
						dangerouslySetInnerHTML={ { __html: group.title } }
					/>
					{ group.description && (
						<p
							style={ {
								fontSize: 11,
								margin: '2px 0 0',
								color: isEnabled
									? 'var(--ww-accent)'
									: 'var(--ww-text-muted)',
							} }
							dangerouslySetInnerHTML={ {
								__html: isEnabled
									? __( '● Enabled', 'woo-wallet' )
									: group.description,
							} }
						/>
					) }
				</div>

				{ master && (
					<div
						onClick={ ( e ) => e.stopPropagation() }
						style={ { marginRight: 10 } }
					>
						<CheckboxField
							value={ masterValue }
							onChange={ ( v ) => onChange( master.name, v ) }
							field={ { ...master, label: '', hint: '' } }
						/>
					</div>
				) }

				<div
					style={ {
						color: 'var(--ww-text-muted)',
						transform: open ? 'rotate(180deg)' : 'none',
						transition: 'transform 0.2s',
						flexShrink: 0,
					} }
				>
					<Icon name="chevron" size={ 16 } />
				</div>
			</div>

			{ open && group.children.length > 0 && (
				<div
					style={ {
						padding: '18px 20px',
						borderTop: '1px solid var(--ww-border)',
						background: 'var(--ww-section-body-bg)',
						display: 'flex',
						flexDirection: 'column',
						gap: 18,
					} }
				>
					{ renderFieldRows( group.children, values, onChange ) }
				</div>
			) }
		</div>
	);
}

function ActionsSection( { groups, values, onChange } ) {
	return (
		<div
			style={ {
				background: 'var(--ww-surface)',
				border: '1px solid var(--ww-border)',
				borderRadius: 14,
				overflow: 'hidden',
				boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.04)',
			} }
		>
			<div
				style={ {
					padding: '18px 24px 16px',
					borderBottom: '1px solid var(--ww-border)',
				} }
			>
				<div
					style={ {
						display: 'flex',
						alignItems: 'center',
						gap: 8,
					} }
				>
					<Icon name="actions" size={ 15 } color="var(--ww-accent)" />
					<h3
						style={ {
							fontSize: 14,
							fontWeight: 600,
							color: 'var(--ww-text-heading)',
							margin: 0,
						} }
					>
						{ __( 'Wallet Actions', 'woo-wallet' ) }
					</h3>
				</div>
				<p
					style={ {
						fontSize: 12,
						color: 'var(--ww-text-muted)',
						marginTop: 4,
					} }
				>
					{ __(
						'Configure automated wallet credits for user events. Enable each action and set the credit amount.',
						'woo-wallet'
					) }
				</p>
			</div>
			<div
				style={ {
					padding: '16px 20px',
					display: 'flex',
					flexDirection: 'column',
					gap: 10,
				} }
			>
				{ groups.map( ( group ) => (
					<ActionGroupCard
						key={ group.id }
						group={ group }
						values={ values }
						onChange={ onChange }
					/>
				) ) }
			</div>
		</div>
	);
}

// ── Gateway logo SVGs ────────────────────────────────────────────────────────

const GATEWAY_LOGOS = {
	paypal: () => (
		<svg viewBox="0 0 48 48" width="22" height="22">
			<rect width="48" height="48" rx="8" fill="#003087" />
			<text
				x="50%"
				y="68%"
				textAnchor="middle"
				fill="white"
				fontSize="20"
				fontWeight="700"
				fontFamily="sans-serif"
			>
				P
			</text>
		</svg>
	),
	stripe: () => (
		<svg viewBox="0 0 48 48" width="22" height="22">
			<rect width="48" height="48" rx="8" fill="#635BFF" />
			<text
				x="50%"
				y="68%"
				textAnchor="middle"
				fill="white"
				fontSize="20"
				fontWeight="700"
				fontFamily="sans-serif"
			>
				S
			</text>
		</svg>
	),
	razorpay: () => (
		<svg viewBox="0 0 48 48" width="22" height="22">
			<rect width="48" height="48" rx="8" fill="#072654" />
			<text
				x="50%"
				y="68%"
				textAnchor="middle"
				fill="white"
				fontSize="20"
				fontWeight="700"
				fontFamily="sans-serif"
			>
				R
			</text>
		</svg>
	),
	bacs: () => (
		<svg viewBox="0 0 48 48" width="22" height="22">
			<rect width="48" height="48" rx="8" fill="#1e40af" />
			<text
				x="50%"
				y="68%"
				textAnchor="middle"
				fill="white"
				fontSize="20"
				fontWeight="700"
				fontFamily="sans-serif"
			>
				B
			</text>
		</svg>
	),
	cashfree: () => (
		<svg viewBox="0 0 48 48" width="22" height="22">
			<rect width="48" height="48" rx="8" fill="#00b04f" />
			<text
				x="50%"
				y="68%"
				textAnchor="middle"
				fill="white"
				fontSize="20"
				fontWeight="700"
				fontFamily="sans-serif"
			>
				C
			</text>
		</svg>
	),
	paystack: () => (
		<svg viewBox="0 0 48 48" width="22" height="22">
			<rect width="48" height="48" rx="8" fill="#00c3f7" />
			<text
				x="50%"
				y="68%"
				textAnchor="middle"
				fill="white"
				fontSize="20"
				fontWeight="700"
				fontFamily="sans-serif"
			>
				P
			</text>
		</svg>
	),
};

function GatewayLogo( { id } ) {
	const Logo = GATEWAY_LOGOS[ id ] || GATEWAY_LOGOS.bacs;
	return (
		<div
			style={ {
				width: 36,
				height: 36,
				borderRadius: 8,
				overflow: 'hidden',
				display: 'flex',
				alignItems: 'center',
				justifyContent: 'center',
				flexShrink: 0,
			} }
		>
			<Logo />
		</div>
	);
}

function GatewayCard( { group, values, onChange } ) {
	const [ open, setOpen ] = useState( false );

	const master = group.master;
	const masterValue = master
		? values[ master.name ] ?? master.default ?? ''
		: 'on';
	const masterChecked =
		masterValue === 'on' ||
		masterValue === true ||
		masterValue === 1 ||
		masterValue === '1';

	const credFields = group.children.filter( ( f ) => ! f.fee_field );
	const feeFields = group.children.filter( ( f ) => !! f.fee_field );

	// Determine live/sandbox status from mode field
	const modeField = group.children.find( ( f ) =>
		/_test_mode$/.test( f.name )
	);
	const isTestMode = modeField ? values[ modeField.name ] === 'on' : false;

	const statusLabel = masterChecked
		? isTestMode
			? __( '● Sandbox mode', 'woo-wallet' )
			: __( '● Live mode', 'woo-wallet' )
		: __( 'Not configured', 'woo-wallet' );
	const statusColor = masterChecked
		? 'var(--ww-accent)'
		: 'var(--ww-text-muted)';

	return (
		<div
			style={ {
				border: `1.5px solid ${
					masterChecked
						? 'oklch(0.55 0.16 185 / 0.35)'
						: 'var(--ww-border)'
				}`,
				borderRadius: 12,
				overflow: 'hidden',
				transition: 'border-color 0.2s',
			} }
		>
			{ /* Header */ }
			<div
				onClick={ () => setOpen( ( o ) => ! o ) }
				style={ {
					display: 'flex',
					alignItems: 'center',
					gap: 12,
					padding: '14px 18px',
					cursor: 'pointer',
					background: open ? 'var(--ww-accent-light)' : 'transparent',
					transition: 'background 0.15s',
					userSelect: 'none',
				} }
			>
				<GatewayLogo id={ group.logo } />
				<div style={ { flex: 1 } }>
					<p
						style={ {
							fontSize: 13,
							fontWeight: 600,
							color: 'var(--ww-text)',
							margin: 0,
						} }
					>
						{ group.title }
					</p>
					<p
						style={ {
							fontSize: 11,
							color: statusColor,
							margin: '2px 0 0',
						} }
					>
						{ statusLabel }
					</p>
				</div>
				{ /* Enable toggle — click stops accordion toggle */ }
				{ master && (
					<div
						onClick={ ( e ) => e.stopPropagation() }
						style={ { marginRight: 10 } }
					>
						<CheckboxField
							value={ masterValue }
							onChange={ ( v ) => onChange( master.name, v ) }
							field={ { ...master, label: '', hint: '' } }
						/>
					</div>
				) }
				<div
					style={ {
						color: 'var(--ww-text-muted)',
						transform: open ? 'rotate(180deg)' : 'none',
						transition: 'transform 0.2s',
						flexShrink: 0,
					} }
				>
					<Icon name="chevron" size={ 16 } />
				</div>
			</div>

			{ /* Expanded body */ }
			{ open && (
				<div
					style={ {
						padding: '18px 20px',
						borderTop: '1px solid var(--ww-border)',
						display: 'flex',
						flexDirection: 'column',
						gap: 16,
						background: 'var(--ww-section-body-bg)',
					} }
				>
					{ credFields.length > 0 &&
						renderFieldRows( credFields, values, onChange ) }

					{ feeFields.length > 0 && (
						<div
							style={ {
								borderTop:
									credFields.length > 0
										? '1px solid var(--ww-border)'
										: 'none',
								paddingTop: credFields.length > 0 ? 16 : 0,
								marginTop: 4,
							} }
						>
							<p
								style={ {
									fontSize: 11,
									fontWeight: 600,
									color: 'var(--ww-text-muted)',
									margin: '0 0 14px',
									textTransform: 'uppercase',
									letterSpacing: '0.06em',
								} }
							>
								{ __( 'Processing Fee', 'woo-wallet' ) }
							</p>
							{ renderFieldRows( feeFields, values, onChange ) }
						</div>
					) }
				</div>
			) }
		</div>
	);
}

function GatewayCredentialsSection( { groups, values, onChange } ) {
	return (
		<div
			style={ {
				background: 'var(--ww-surface)',
				border: '1px solid var(--ww-border)',
				borderRadius: 14,
				boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.04)',
			} }
		>
			<div
				style={ {
					padding: '18px 24px 16px',
					borderBottom: '1px solid var(--ww-border)',
				} }
			>
				<div
					style={ { display: 'flex', alignItems: 'center', gap: 8 } }
				>
					<Icon name="key" size={ 15 } color="var(--ww-accent)" />
					<h3
						style={ {
							fontSize: 15,
							fontWeight: 600,
							color: 'var(--ww-text-heading)',
							margin: 0,
						} }
					>
						{ __( 'Payout Gateway Credentials', 'woo-wallet' ) }
					</h3>
				</div>
				<p
					style={ {
						fontSize: 12,
						color: 'var(--ww-text-muted)',
						marginTop: 4,
					} }
				>
					{ __(
						'Configure API keys for each payout method. Enable the ones you want to offer customers.',
						'woo-wallet'
					) }
				</p>
			</div>
			<div
				style={ {
					padding: '16px 20px',
					display: 'flex',
					flexDirection: 'column',
					gap: 10,
				} }
			>
				{ groups.map( ( group ) => (
					<GatewayCard
						key={ group.id }
						group={ group }
						values={ values }
						onChange={ onChange }
					/>
				) ) }
			</div>
		</div>
	);
}

export default function Panel( {
	sectionId,
	schema,
	values,
	onChange,
	onSave,
	saving,
	saved,
	appendChildren = null,
} ) {
	const fields = schema.fields?.[ sectionId ] || [];
	const sectionValues = values[ sectionId ] || {};
	const { groups, gatewayGroups, actionGroups, loose } =
		partitionFields( fields );
	const visibleLoose = loose.filter( ( f ) => isVisible( f, sectionValues ) );

	const handleFieldChange = ( fieldName, val ) =>
		onChange( sectionId, fieldName, val );

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: 20 } }>
			{ visibleLoose.length > 0 && (
				<div
					style={ {
						background: 'var(--ww-surface)',
						border: '1px solid var(--ww-border)',
						borderRadius: 14,
						boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.04)',
					} }
				>
					<div
						style={ {
							padding: '22px 24px',
							display: 'flex',
							flexDirection: 'column',
							gap: 22,
						} }
					>
						{ renderFieldRows(
							visibleLoose,
							sectionValues,
							handleFieldChange
						) }
					</div>
				</div>
			) }

			{ groups.map( ( group ) => (
				<GroupCard
					key={ group.id }
					group={ group }
					values={ sectionValues }
					onChange={ handleFieldChange }
				/>
			) ) }

			{ actionGroups.length > 0 && (
				<ActionsSection
					groups={ actionGroups }
					values={ sectionValues }
					onChange={ handleFieldChange }
				/>
			) }

			{ gatewayGroups.length > 0 && (
				<GatewayCredentialsSection
					groups={ gatewayGroups }
					values={ sectionValues }
					onChange={ handleFieldChange }
				/>
			) }

			{ fields.length === 0 && (
				<div
					style={ {
						background: 'var(--ww-surface)',
						border: '1px solid var(--ww-border)',
						borderRadius: 14,
						padding: 40,
						textAlign: 'center',
					} }
				>
					<p
						style={ {
							fontSize: 14,
							color: 'var(--ww-text-muted)',
							margin: 0,
						} }
					>
						{ __( 'No settings available.', 'woo-wallet' ) }
					</p>
				</div>
			) }

			{ appendChildren }

			<div style={ { display: 'flex', justifyContent: 'flex-end' } }>
				<SaveButton
					onClick={ () => onSave( sectionId ) }
					saving={ saving }
					saved={ saved }
				/>
			</div>
		</div>
	);
}
