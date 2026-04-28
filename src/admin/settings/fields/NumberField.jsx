export default function NumberField( { value, onChange, field } ) {
	return (
		<div style={ {
			display: 'flex', alignItems: 'stretch',
			background: 'var(--ww-input-bg)',
			border: '1.5px solid var(--ww-border-input)',
			borderRadius: 10, overflow: 'hidden',
			transition: 'border-color 0.15s',
		} }
		onFocus={ ( e ) => e.currentTarget.style.borderColor = 'var(--ww-accent)' }
		onBlur={ ( e ) => e.currentTarget.style.borderColor = 'var(--ww-border-input)' }
		>
			{ field.prefix && (
				<span style={ {
					padding: '0 14px',
					color: 'var(--ww-text-muted)', fontSize: 14, fontWeight: 500,
					borderRight: '1.5px solid var(--ww-border-input)',
					display: 'flex', alignItems: 'center',
					background: 'var(--ww-input-prefix-bg)', whiteSpace: 'nowrap',
					flexShrink: 0,
				} }>{ field.prefix }</span>
			) }
			<input
				type="number"
				value={ value ?? '' }
				onChange={ ( e ) => onChange( e.target.value ) }
				placeholder={ field.placeholder || '' }
				min={ field.min }
				max={ field.max }
				step={ field.step || 'any' }
				style={ {
					flex: 1, border: 'none', outline: 'none',
					padding: '10px 14px', fontSize: 14,
					fontFamily: 'inherit',
					background: 'transparent', color: 'var(--ww-text)',
				} }
			/>
		</div>
	);
}
