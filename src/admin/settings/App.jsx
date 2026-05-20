import { useState, useEffect } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import Header from './components/Header';
import Sidebar from './components/Sidebar';
import Panel from './components/Panel';
import CurrencyModePanel from './components/CurrencyModePanel';
import ToastStack from './components/Toast';
import useSettings from './hooks/useSettings';
import useViewport from './hooks/useViewport';

const SECTION_TITLES = {
	_wallet_settings_general: __( 'General Options', 'woo-wallet' ),
	_wallet_settings_credit: __( 'Credit Options', 'woo-wallet' ),
	_wallet_settings_actions: __( 'Actions', 'woo-wallet' ),
};

const SECTION_DESCS = {
	_wallet_settings_general: __( 'Topup, gateways & transfers', 'woo-wallet' ),
	_wallet_settings_credit: __( 'Cashback & reward rules', 'woo-wallet' ),
	_wallet_settings_actions: __( 'Automated wallet events', 'woo-wallet' ),
};

export default function App() {
	const {
		schema,
		values,
		loading,
		error,
		saving,
		saved,
		toasts,
		dismissToast,
		setFieldValue,
		saveSection,
	} = useSettings();

	const [ theme, setTheme ] = useState( () => {
		try {
			return localStorage.getItem( 'ww_admin_theme' ) || 'light';
		} catch ( e ) {
			return 'light';
		}
	} );
	const [ systemDark, setSystemDark ] = useState(
		() => window.matchMedia( '(prefers-color-scheme: dark)' ).matches
	);
	const [ activeTab, setActiveTab ] = useState( () => {
		// Honour `#_wallet_settings_*` deep-links (e.g. from admin notices) first,
		// then fall back to the last persisted tab.
		try {
			const hash = ( window.location.hash || '' ).replace( /^#/, '' );
			if ( hash && /^[\w-]+$/.test( hash ) ) {
				return hash;
			}
			return localStorage.getItem( 'ww_admin_tab' ) || '';
		} catch ( e ) {
			return '';
		}
	} );
	const { isCompact, isPhone } = useViewport();
	// Below WP admin's 782px breakpoint the sidebar becomes an overlay drawer
	// and starts collapsed; on wider screens it is docked open.
	const [ sidebarOpen, setSidebarOpen ] = useState(
		() => typeof window === 'undefined' || window.innerWidth > 782
	);

	// Reconcile the sidebar with the viewport whenever it crosses the
	// compact breakpoint: collapse it on shrink, re-dock it on grow.
	useEffect( () => {
		setSidebarOpen( ! isCompact );
	}, [ isCompact ] );

	// OS theme listener
	useEffect( () => {
		const mq = window.matchMedia( '(prefers-color-scheme: dark)' );
		const handler = ( e ) => setSystemDark( e.matches );
		mq.addEventListener( 'change', handler );
		return () => mq.removeEventListener( 'change', handler );
	}, [] );

	// Persist theme
	useEffect( () => {
		try {
			localStorage.setItem( 'ww_admin_theme', theme );
		} catch ( e ) {
			/**/
		}
	}, [ theme ] );

	// Set default active tab when schema loads. Also reconcile against the
	// schema: if the value seeded from URL hash / localStorage points to a
	// section that no longer exists, fall back to the first section so the
	// page doesn't render empty. Validate against the *filtered* schema so
	// JS-registered third-party tabs persisted in localStorage survive the
	// reload — the raw schema from REST doesn't know about them.
	useEffect( () => {
		if ( ! schema || ! schema.sections || schema.sections.length === 0 ) {
			return;
		}
		const filtered = applyFilters( 'woo_wallet.settings.schema', schema );
		const allSections = filtered?.sections || schema.sections;
		const knownIds = allSections.map( ( s ) => s.id );
		if ( ! activeTab || ! knownIds.includes( activeTab ) ) {
			setActiveTab( allSections[ 0 ].id );
		}
	}, [ schema ] );

	// Persist tab
	useEffect( () => {
		if ( activeTab ) {
			try {
				localStorage.setItem( 'ww_admin_tab', activeTab );
			} catch ( e ) {
				/**/
			}
		}
	}, [ activeTab ] );

	const resolvedTheme =
		theme === 'system' ? ( systemDark ? 'dark' : 'light' ) : theme;
	const version = window.wooWalletSettingsData?.version || '';

	// Allow JS extensions to modify the schema
	const resolvedSchema = schema
		? applyFilters( 'woo_wallet.settings.schema', schema )
		: null;

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
				<p style={ { color: 'oklch(0.55 0.2 30)', fontSize: 14 } }>
					{ error }
				</p>
			</div>
		);
	}

	const sections = resolvedSchema?.sections || [];
	const activeSection = sections.find( ( s ) => s.id === activeTab );

	const pageTitle =
		activeSection?.title || SECTION_TITLES[ activeTab ] || activeTab;
	// Built-in sections keep their hardcoded subtitle; third-party tabs use
	// whatever they registered as `description`.
	const pageDesc =
		SECTION_DESCS[ activeTab ] || activeSection?.description || '';

	return (
		<div data-theme={ resolvedTheme } className="ww-app">
			<Header
				sidebarOpen={ sidebarOpen }
				onToggleSidebar={ () => setSidebarOpen( ( o ) => ! o ) }
				theme={ theme }
				onThemeChange={ setTheme }
				version={ version }
				isCompact={ isCompact }
				isPhone={ isPhone }
			/>

			<div
				style={ {
					display: 'flex',
					flex: 1,
					overflow: 'hidden',
					position: 'relative',
				} }
			>
				<Sidebar
					sections={ sections }
					activeTab={ activeTab }
					onTabChange={ ( id ) => {
						setActiveTab( id );
						// On the overlay drawer, picking a tab dismisses it.
						if ( isCompact ) {
							setSidebarOpen( false );
						}
					} }
					open={ sidebarOpen }
					isCompact={ isCompact }
				/>

				{ isCompact && sidebarOpen && (
					<div
						onClick={ () => setSidebarOpen( false ) }
						aria-hidden="true"
						style={ {
							position: 'absolute',
							inset: 0,
							zIndex: 40,
							background: 'oklch(0.2 0.03 260 / 0.4)',
						} }
					/>
				) }

				<main
					style={ {
						flex: 1,
						overflowY: 'auto',
						padding: isCompact
							? isPhone
								? '16px 14px'
								: '20px 18px'
							: '28px 32px',
						minWidth: 0,
					} }
				>
					<div
						style={ { marginBottom: isCompact ? 16 : 24 } }
					>
						<h2
							style={ {
								fontSize: isCompact ? 17 : 20,
								fontWeight: 700,
								color: 'var(--ww-text-heading)',
								letterSpacing: '-0.02em',
								margin: 0,
							} }
						>
							{ pageTitle }
						</h2>
						{ pageDesc && (
							<p
								style={ {
									fontSize: 13,
									color: 'var(--ww-text-muted)',
									marginTop: 4,
								} }
							>
								{ pageDesc }
							</p>
						) }
					</div>

					<div key={ activeTab } className="ww-fade-up">
						{ activeTab ? (
							<Panel
								sectionId={ activeTab }
								schema={ resolvedSchema }
								values={ values }
								onChange={ setFieldValue }
								onSave={ saveSection }
								saving={ saving[ activeTab ] }
								saved={ saved[ activeTab ] }
								appendChildren={
									activeTab === '_wallet_settings_general' ? (
										<CurrencyModePanel
											value={
												values?._wallet_settings_general
													?.wallet_currency_mode
											}
											onChange={ ( next ) =>
												setFieldValue(
													'_wallet_settings_general',
													'wallet_currency_mode',
													next
												)
											}
										/>
									) : null
								}
							/>
						) : null }
					</div>
				</main>
			</div>

			<ToastStack toasts={ toasts } onDismiss={ dismissToast } />
		</div>
	);
}
