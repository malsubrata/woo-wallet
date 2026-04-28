import { __ } from '@wordpress/i18n';
import Icon from './Icon';

const SECTION_ICONS = {
	_wallet_settings_general:    'settings',
	_wallet_settings_credit:     'credit',
	_wallet_settings_withdrawal: 'withdraw',
	actions:                     'actions',
};

const SECTION_DESC = {
	_wallet_settings_general:    __( 'Topup, gateways & transfers', 'woo-wallet' ),
	_wallet_settings_credit:     __( 'Cashback & reward rules', 'woo-wallet' ),
	_wallet_settings_withdrawal: __( 'Payout methods & limits', 'woo-wallet' ),
	actions:                     __( 'Automated wallet events', 'woo-wallet' ),
};

export default function Sidebar( { sections, activeTab, onTabChange, open } ) {
	const navItems = [
		...sections.map( ( s ) => ( {
			id: s.id,
			label: s.title || s.id,
			desc:  SECTION_DESC[ s.id ] || '',
			icon:  SECTION_ICONS[ s.id ] || 'settings',
		} ) ),
		{ id: 'actions', label: __( 'Actions', 'woo-wallet' ), desc: __( 'Automated wallet events', 'woo-wallet' ), icon: 'actions' },
	];

	return (
		<nav style={ {
			width: open ? 240 : 0,
			flexShrink: 0,
			background: 'var(--ww-sidebar-bg)',
			borderRight: open ? '1px solid var(--ww-border)' : 'none',
			padding: open ? '16px 12px' : 0,
			display: 'flex', flexDirection: 'column', gap: 4,
			transition: 'width 0.28s cubic-bezier(.4,0,.2,1), padding 0.28s, opacity 0.2s',
			overflowY: 'auto', overflowX: 'hidden',
			opacity: open ? 1 : 0,
		} }>
			<p style={ {
				fontSize: 10, fontWeight: 600, letterSpacing: '0.08em',
				textTransform: 'uppercase', color: 'var(--ww-text-muted)',
				padding: '4px 12px 8px', marginBottom: 4, whiteSpace: 'nowrap',
			} }>
				{ __( 'Configuration', 'woo-wallet' ) }
			</p>
			{ navItems.map( ( item ) => {
				const active = activeTab === item.id;
				return (
					<button
						key={ item.id }
						type="button"
						onClick={ () => onTabChange( item.id ) }
						style={ {
							display: 'flex', alignItems: 'center', gap: 11,
							padding: '11px 12px', borderRadius: 10, border: 'none', cursor: 'pointer',
							background: active ? 'var(--ww-nav-active-bg)' : 'transparent',
							textAlign: 'left', width: '100%', fontFamily: 'inherit',
							transition: 'background 0.15s',
						} }
						onMouseEnter={ ( e ) => {
							if ( ! active ) e.currentTarget.style.background = 'var(--ww-hover-bg)';
						} }
						onMouseLeave={ ( e ) => {
							if ( ! active ) e.currentTarget.style.background = 'transparent';
						} }
					>
						<span style={ {
							color: active ? 'var(--ww-accent)' : 'var(--ww-text-muted)',
							flexShrink: 0, display: 'flex', alignItems: 'center',
						} }>
							<Icon name={ item.icon } size={ 17 }/>
						</span>
						<div style={ { overflow: 'hidden', minWidth: 0 } }>
							<p style={ {
								fontSize: 13,
								fontWeight: active ? 600 : 400,
								color: active ? 'var(--ww-accent)' : 'var(--ww-text-label)',
								whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
								margin: 0,
							} }>
								{ item.label }
							</p>
							<p style={ {
								fontSize: 11, color: 'var(--ww-text-muted)', marginTop: 1,
								whiteSpace: 'nowrap', overflow: 'hidden', textOverflow: 'ellipsis',
								margin: 0,
							} }>
								{ item.desc }
							</p>
						</div>
					</button>
				);
			} ) }
		</nav>
	);
}
