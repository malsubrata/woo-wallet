import { __ } from '@wordpress/i18n';
import Icon from './Icon';

const getOptions = () => [
	{ value: 'light',  icon: 'sun',     label: __( 'Light', 'woo-wallet' ) },
	{ value: 'dark',   icon: 'moon',    label: __( 'Dark', 'woo-wallet' ) },
	{ value: 'system', icon: 'monitor', label: __( 'System', 'woo-wallet' ) },
];

export default function ThemeSwitcher( { theme, onChange } ) {
	return (
		<div style={ {
			display: 'flex', alignItems: 'center',
			background: 'var(--ww-surface-2)',
			border: '1.5px solid var(--ww-border-input)',
			borderRadius: 10, padding: 3, gap: 2,
		} }>
			{ getOptions().map( ( o ) => {
				const active = theme === o.value;
				return (
					<button
						key={ o.value }
						type="button"
						onClick={ () => onChange( o.value ) }
						title={ o.label }
						style={ {
							display: 'flex', alignItems: 'center', gap: 5,
							padding: '5px 10px', borderRadius: 7, border: 'none', cursor: 'pointer',
							background: active ? 'var(--ww-surface)' : 'transparent',
							color: active ? 'var(--ww-accent)' : 'var(--ww-text-muted)',
							fontFamily: 'inherit', fontSize: 12,
							fontWeight: active ? 600 : 400,
							boxShadow: active ? '0 1px 3px oklch(0.2 0.05 260 / 0.1)' : 'none',
							transition: 'all 0.15s', whiteSpace: 'nowrap',
						} }
					>
						<Icon name={ o.icon } size={ 13 } color={ active ? 'var(--ww-accent)' : 'var(--ww-text-muted)' }/>
						<span>{ o.label }</span>
					</button>
				);
			} ) }
		</div>
	);
}
