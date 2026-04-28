export default function RadioField( { value, onChange, field } ) {
	const options = Array.isArray( field.options )
		? field.options
		: Object.entries( field.options || {} ).map( ( [ k, v ] ) => ( { value: k, label: v } ) );

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: 10 } }>
			{ options.map( ( o ) => {
				const checked = value === o.value;
				return (
					<label key={ o.value } style={ {
						display: 'flex', alignItems: 'center', gap: 10,
						cursor: 'pointer', fontSize: 14, color: 'var(--ww-text)',
					} }>
						<div
							onClick={ () => onChange( o.value ) }
							style={ {
								width: 18, height: 18, borderRadius: '50%', flexShrink: 0,
								border: `2px solid ${ checked ? 'var(--ww-accent)' : 'var(--ww-border-input)' }`,
								background: 'transparent',
								display: 'flex', alignItems: 'center', justifyContent: 'center',
								cursor: 'pointer', transition: 'border-color 0.15s',
							} }
						>
							{ checked && (
								<div style={ {
									width: 8, height: 8, borderRadius: '50%',
									background: 'var(--ww-accent)',
								} }/>
							) }
						</div>
						<span onClick={ () => onChange( o.value ) }>{ o.label }</span>
					</label>
				);
			} ) }
		</div>
	);
}
