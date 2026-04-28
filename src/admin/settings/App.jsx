import { useState, useEffect } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import Header from './components/Header';
import Sidebar from './components/Sidebar';
import Panel from './components/Panel';
import ActionsPanel from './components/ActionsPanel';
import ToastStack from './components/Toast';
import useSettings from './hooks/useSettings';

const SECTION_TITLES = {
	_wallet_settings_general:    __( 'General Options', 'woo-wallet' ),
	_wallet_settings_credit:     __( 'Credit Options', 'woo-wallet' ),
	_wallet_settings_withdrawal: __( 'Withdrawal Options', 'woo-wallet' ),
};

const SECTION_DESCS = {
	_wallet_settings_general:    __( 'Topup, gateways & transfers', 'woo-wallet' ),
	_wallet_settings_credit:     __( 'Cashback & reward rules', 'woo-wallet' ),
	_wallet_settings_withdrawal: __( 'Payout methods & limits', 'woo-wallet' ),
};

export default function App() {
	const { schema, values, loading, error, saving, saved, toasts, dismissToast, setFieldValue, saveSection, saveAction } = useSettings();

	const [ theme, setTheme ] = useState( () => {
		try { return localStorage.getItem( 'ww_admin_theme' ) || 'light'; } catch ( e ) { return 'light'; }
	} );
	const [ systemDark, setSystemDark ] = useState(
		() => window.matchMedia( '(prefers-color-scheme: dark)' ).matches
	);
	const [ activeTab, setActiveTab ] = useState( () => {
		try { return localStorage.getItem( 'ww_admin_tab' ) || ''; } catch ( e ) { return ''; }
	} );
	const [ sidebarOpen, setSidebarOpen ] = useState( true );

	// OS theme listener
	useEffect( () => {
		const mq = window.matchMedia( '(prefers-color-scheme: dark)' );
		const handler = ( e ) => setSystemDark( e.matches );
		mq.addEventListener( 'change', handler );
		return () => mq.removeEventListener( 'change', handler );
	}, [] );

	// Persist theme
	useEffect( () => {
		try { localStorage.setItem( 'ww_admin_theme', theme ); } catch ( e ) { /**/ }
	}, [ theme ] );

	// Set default active tab when schema loads
	useEffect( () => {
		if ( schema && ! activeTab && schema.sections && schema.sections.length > 0 ) {
			setActiveTab( schema.sections[ 0 ].id );
		}
	}, [ schema ] );

	// Persist tab
	useEffect( () => {
		if ( activeTab ) {
			try { localStorage.setItem( 'ww_admin_tab', activeTab ); } catch ( e ) { /**/ }
		}
	}, [ activeTab ] );

	const resolvedTheme = theme === 'system' ? ( systemDark ? 'dark' : 'light' ) : theme;
	const version = window.wooWalletSettingsData?.version || '';

	// Allow JS extensions to modify the schema
	const resolvedSchema = schema ? applyFilters( 'woo_wallet.settings.schema', schema ) : null;

	if ( loading ) {
		return (
			<div data-theme={ resolvedTheme } className="ww-app--fill">
				<p style={ { color: 'var(--ww-text-muted)', fontSize: 14 } }>
					{ __( 'Loading settings…', 'woo-wallet' ) }
				</p>
			</div>
		);
	}

	if ( error ) {
		return (
			<div data-theme={ resolvedTheme } className="ww-app--fill">
				<p style={ { color: 'oklch(0.55 0.2 30)', fontSize: 14 } }>{ error }</p>
			</div>
		);
	}

	const sections = resolvedSchema?.sections || [];
	const activeSection = sections.find( ( s ) => s.id === activeTab );
	const isActionsTab = activeTab === 'actions';

	const pageTitle = isActionsTab
		? __( 'Actions', 'woo-wallet' )
		: ( activeSection?.title || SECTION_TITLES[ activeTab ] || activeTab );
	const pageDesc  = isActionsTab
		? __( 'Automated wallet events', 'woo-wallet' )
		: ( SECTION_DESCS[ activeTab ] || '' );

	return (
		<div
			data-theme={ resolvedTheme }
			className="ww-app"
		>
			<Header
				sidebarOpen={ sidebarOpen }
				onToggleSidebar={ () => setSidebarOpen( ( o ) => ! o ) }
				theme={ theme }
				onThemeChange={ setTheme }
				version={ version }
			/>

			<div style={ { display: 'flex', flex: 1, overflow: 'hidden' } }>
				<Sidebar
					sections={ sections }
					activeTab={ activeTab }
					onTabChange={ setActiveTab }
					open={ sidebarOpen }
				/>

				<main style={ { flex: 1, overflowY: 'auto', padding: '28px 32px', minWidth: 0 } }>
					<div style={ { marginBottom: 24 } }>
						<h2 style={ {
							fontSize: 20, fontWeight: 700, color: 'var(--ww-text-heading)',
							letterSpacing: '-0.02em', margin: 0,
						} }>
							{ pageTitle }
						</h2>
						{ pageDesc && (
							<p style={ { fontSize: 13, color: 'var(--ww-text-muted)', marginTop: 4 } }>
								{ pageDesc }
							</p>
						) }
					</div>

					<div key={ activeTab } className="ww-fade-up">
						{ isActionsTab ? (
							<ActionsPanel
								actions={ resolvedSchema?.actions || [] }
								onSave={ saveAction }
								saving={ saving }
								saved={ saved }
							/>
						) : activeTab ? (
							<Panel
								sectionId={ activeTab }
								schema={ resolvedSchema }
								values={ values }
								onChange={ setFieldValue }
								onSave={ saveSection }
								saving={ saving[ activeTab ] }
								saved={ saved[ activeTab ] }
							/>
						) : null }
					</div>
				</main>
			</div>

			<ToastStack toasts={ toasts } onDismiss={ dismissToast }/>
		</div>
	);
}
