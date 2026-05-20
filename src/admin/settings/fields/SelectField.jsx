import Icon from '../components/Icon';

export default function SelectField( { value, onChange, field } ) {
	const options = Array.isArray( field.options )
		? field.options
		: Object.entries( field.options || {} ).map( ( [ k, v ] ) => ( { value: k, label: v } ) );

	return (
		<div style={ { position: 'relative', display: 'block' } }>
			<select
				value={ value ?? '' }
				onChange={ ( e ) => onChange( e.target.value ) }
				style={ {
					display: 'block', width: '100%', maxWidth: '100%', boxSizing: 'border-box',
					border: '1.5px solid var(--ww-border-input)', borderRadius: 10,
					fontSize: 14, fontFamily: 'inherit',
					background: 'var(--ww-input-bg)', color: 'var(--ww-text)',
					outline: 'none', cursor: 'pointer',
					appearance: 'none', WebkitAppearance: 'none',
					transition: 'border-color 0.15s',
				} }
				onFocus={ ( e ) => e.target.style.borderColor = 'var(--ww-accent)' }
				onBlur={ ( e ) => e.target.style.borderColor = 'var(--ww-border-input)' }
			>
				{ options.map( ( o ) => (
					<option key={ o.value } value={ o.value }>{ o.label }</option>
				) ) }
			</select>
			<div style={ {
				position: 'absolute', right: 12, top: '50%',
				transform: 'translateY(-50%)', pointerEvents: 'none',
				color: 'var(--ww-text-muted)',
			} }>
				<Icon name="chevron" size={ 16 }/>
			</div>
		</div>
	);
}
