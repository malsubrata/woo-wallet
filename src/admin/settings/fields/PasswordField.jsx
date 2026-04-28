import { useState } from '@wordpress/element';
import Icon from '../components/Icon';

export default function PasswordField( { value, onChange, field } ) {
	const [ show, setShow ] = useState( false );
	return (
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
			<input
				type={ show ? 'text' : 'password' }
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
			<button
				type="button"
				onClick={ () => setShow( ( s ) => ! s ) }
				style={ {
					border: 'none', background: 'transparent', cursor: 'pointer',
					padding: '0 12px', color: 'var(--ww-text-muted)',
					display: 'flex', alignItems: 'center',
				} }
				title={ show ? 'Hide' : 'Show' }
			>
				<Icon name={ show ? 'eyeOff' : 'eye' } size={ 15 }/>
			</button>
		</div>
	);
}
