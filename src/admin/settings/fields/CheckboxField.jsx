export default function CheckboxField( { value, onChange, field } ) {
	const checked = value === 'on' || value === true || value === 1 || value === '1' || value === 'yes';
	return (
		<div style={ { display: 'flex', alignItems: 'flex-start', justifyContent: 'space-between', gap: 12 } }>
			{ field.label && (
				<div style={ { flex: 1 } }>
					<p
						style={ { fontSize: 13, fontWeight: 500, color: 'var(--ww-text-label)' } }
						dangerouslySetInnerHTML={ { __html: field.label } }
					/>
					{ field.hint && (
						<p
							style={ { fontSize: 12, color: 'var(--ww-text-hint)', marginTop: 4, lineHeight: 1.5 } }
							dangerouslySetInnerHTML={ { __html: field.hint } }
						/>
					) }
				</div>
			) }
			<button
				type="button"
				role="switch"
				aria-checked={ checked }
				onClick={ () => onChange( checked ? 'off' : 'on' ) }
				style={ {
					width: 48, height: 26, borderRadius: 13,
					background: checked ? 'var(--ww-accent)' : 'var(--ww-toggle-off-bg)',
					border: 'none', cursor: 'pointer', position: 'relative',
					transition: 'background 0.25s', flexShrink: 0,
					outline: 'none',
				} }
			>
				<span style={ {
					position: 'absolute', top: 3,
					left: checked ? 25 : 3,
					width: 20, height: 20, borderRadius: '50%',
					background: 'white',
					boxShadow: '0 1px 4px oklch(0.2 0.05 260 / 0.25)',
					transition: 'left 0.22s cubic-bezier(.4,0,.2,1)',
					display: 'block',
				} }/>
			</button>
		</div>
	);
}
