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
						aria-label={ o.label }
						aria-pressed={ active }
						style={ {
							display: 'flex', alignItems: 'center', justifyContent: 'center',
							padding: 7, borderRadius: 7, border: 'none', cursor: 'pointer',
							background: active ? 'var(--ww-surface)' : 'transparent',
							color: active ? 'var(--ww-accent)' : 'var(--ww-text-muted)',
							boxShadow: active ? '0 1px 3px oklch(0.2 0.05 260 / 0.1)' : 'none',
							transition: 'all 0.15s',
						} }
					>
						<Icon name={ o.icon } size={ 15 } color={ active ? 'var(--ww-accent)' : 'var(--ww-text-muted)' }/>
					</button>
				);
			} ) }
		</div>
	);
}
