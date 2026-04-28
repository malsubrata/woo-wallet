export default function ColorField( { value, onChange } ) {
	return (
		<div style={ { display: 'flex', alignItems: 'center', gap: 10 } }>
			<input
				type="color"
				value={ value || '#000000' }
				onChange={ ( e ) => onChange( e.target.value ) }
				style={ {
					width: 44, height: 44, padding: 2,
					border: '1.5px solid var(--ww-border-input)',
					borderRadius: 8, cursor: 'pointer', background: 'none',
				} }
			/>
			<input
				type="text"
				value={ value || '' }
				onChange={ ( e ) => onChange( e.target.value ) }
				placeholder="#000000"
				style={ {
					width: 110, padding: '8px 12px', fontSize: 13,
					fontFamily: 'inherit', letterSpacing: '0.05em',
					background: 'var(--ww-input-bg)', color: 'var(--ww-text)',
					border: '1.5px solid var(--ww-border-input)', borderRadius: 8,
					outline: 'none',
				} }
			/>
		</div>
	);
}
