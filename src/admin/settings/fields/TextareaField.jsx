export default function TextareaField( { value, onChange, field } ) {
	return (
		<textarea
			value={ value ?? '' }
			onChange={ ( e ) => onChange( e.target.value ) }
			placeholder={ field.placeholder || '' }
			rows={ field.rows || 4 }
			style={ {
				width: '100%', padding: '10px 14px', fontSize: 14,
				fontFamily: 'inherit', lineHeight: 1.5,
				background: 'var(--ww-input-bg)', color: 'var(--ww-text)',
				border: '1.5px solid var(--ww-border-input)', borderRadius: 10,
				outline: 'none', resize: 'vertical',
				transition: 'border-color 0.15s',
			} }
			onFocus={ ( e ) => e.target.style.borderColor = 'var(--ww-accent)' }
			onBlur={ ( e ) => e.target.style.borderColor = 'var(--ww-border-input)' }
		/>
	);
}
