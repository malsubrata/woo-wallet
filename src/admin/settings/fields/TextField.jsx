export default function TextField( { value, onChange, field } ) {
	return (
		<div>
			<div style={ {
				display: 'flex', alignItems: 'center',
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
						padding: '0 12px', color: 'var(--ww-text-muted)', fontSize: 14,
						borderRight: '1.5px solid var(--ww-border-input)', height: '100%',
						display: 'flex', alignItems: 'center',
						background: 'var(--ww-input-prefix-bg)', whiteSpace: 'nowrap',
					} }>{ field.prefix }</span>
				) }
				<input
					type={ field.type === 'url' ? 'url' : 'text' }
					value={ value ?? '' }
					onChange={ ( e ) => onChange( e.target.value ) }
					placeholder={ field.placeholder || '' }
					style={ {
						flex: 1, border: 'none', outline: 'none',
						padding: '10px 14px', fontSize: 14,
						fontFamily: 'inherit',
						background: 'transparent', color: 'var(--ww-text)',
					} }
				/>
			</div>
		</div>
	);
}
