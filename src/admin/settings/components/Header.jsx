import { __ } from '@wordpress/i18n';
import Icon from './Icon';
import ThemeSwitcher from './ThemeSwitcher';

export default function Header( { sidebarOpen, onToggleSidebar, theme, onThemeChange, version, isCompact = false, isPhone = false } ) {
	return (
		<header style={ {
			background: 'var(--ww-header-bg)',
			borderBottom: '1px solid var(--ww-border)',
			padding: isCompact ? '0 12px' : '0 20px', height: 60,
			display: 'flex', alignItems: 'center',
			gap: isCompact ? 8 : 12, flexShrink: 0,
			transition: 'background 0.3s',
			boxShadow: '0 1px 0 var(--ww-border)',
		} }>
			<button
				type="button"
				onClick={ onToggleSidebar }
				title={ sidebarOpen ? __( 'Collapse sidebar', 'woo-wallet' ) : __( 'Expand sidebar', 'woo-wallet' ) }
				style={ {
					width: 36, height: 36, borderRadius: 9, border: 'none', cursor: 'pointer',
					background: sidebarOpen ? 'var(--ww-accent-light)' : 'var(--ww-hover-bg)',
					color: sidebarOpen ? 'var(--ww-accent)' : 'var(--ww-text-muted)',
					display: 'flex', alignItems: 'center', justifyContent: 'center',
					flexShrink: 0, transition: 'background 0.18s, color 0.18s',
				} }
			>
				<Icon name="menu" size={ 17 }/>
			</button>

			<div style={ {
				width: 34, height: 34, borderRadius: 9,
				background: 'var(--ww-accent)',
				display: 'flex', alignItems: 'center', justifyContent: 'center',
				flexShrink: 0,
			} }>
				<Icon name="wallet" size={ 18 } color="white"/>
			</div>

			<div style={ { minWidth: 0, overflow: 'hidden' } }>
				<h1 style={ {
					fontSize: 15, fontWeight: 700, color: 'var(--ww-text-heading)',
					letterSpacing: '-0.01em', margin: 0,
					whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
				} }>TeraWallet</h1>
				{ ! isPhone && (
					<p style={ { fontSize: 11, color: 'var(--ww-text-hint)', margin: 0 } }>
						{ __( 'Plugin Settings', 'woo-wallet' ) }
					</p>
				) }
			</div>

			<div style={ { marginLeft: 'auto', display: 'flex', alignItems: 'center', gap: 10 } }>
				<ThemeSwitcher theme={ theme } onChange={ onThemeChange }/>
				{ version && (
					<span style={ {
						fontSize: 11, fontWeight: 500, color: 'var(--ww-accent)',
						background: 'var(--ww-accent-light)', padding: '3px 9px', borderRadius: 20,
						whiteSpace: 'nowrap',
					} }>
						v{ version }
					</span>
				) }
			</div>
		</header>
	);
}
