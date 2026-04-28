import Icon from '../components/Icon';

export default function MulticheckField( { value, onChange, field } ) {
	const selected = Array.isArray( value ) ? value : ( value ? [ value ] : [] );

	const options = Array.isArray( field.options )
		? field.options
		: Object.entries( field.options || {} ).map( ( [ k, v ] ) => ( { value: k, label: v } ) );

	const toggle = ( val ) => {
		if ( selected.includes( val ) ) {
			onChange( selected.filter( ( v ) => v !== val ) );
		} else {
			onChange( [ ...selected, val ] );
		}
	};

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: 10 } }>
			{ options.map( ( o ) => {
				const isSelected = selected.includes( o.value );
				return (
					<label key={ o.value } style={ {
						display: 'flex', alignItems: 'center', gap: 10,
						cursor: 'pointer', fontSize: 14, color: 'var(--ww-text)',
					} }>
						<div
							onClick={ () => toggle( o.value ) }
							style={ {
								width: 18, height: 18, borderRadius: 5, flexShrink: 0,
								border: `2px solid ${ isSelected ? 'var(--ww-accent)' : 'var(--ww-border-input)' }`,
								background: isSelected ? 'var(--ww-accent)' : 'transparent',
								display: 'flex', alignItems: 'center', justifyContent: 'center',
								cursor: 'pointer', transition: 'all 0.15s',
							} }
						>
							{ isSelected && <Icon name="check" size={ 11 } color="white" strokeWidth={ 2.5 }/> }
						</div>
						<span onClick={ () => toggle( o.value ) }>{ o.label }</span>
					</label>
				);
			} ) }
		</div>
	);
}
