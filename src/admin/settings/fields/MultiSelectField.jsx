import { useState, useEffect, useRef } from '@wordpress/element';
import { createPortal } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import Icon from '../components/Icon';

export default function MultiSelectField( { value, onChange, field } ) {
	const [ open, setOpen ] = useState( false );
	const [ rect, setRect ] = useState( null );
	const triggerRef        = useRef( null );
	const dropdownRef       = useRef( null );

	const selected = Array.isArray( value ) ? value : ( value ? [ value ] : [] );

	const options = Array.isArray( field.options )
		? field.options
		: Object.entries( field.options || {} ).map( ( [ k, v ] ) => ( { value: k, label: v } ) );

	// Inherit data-theme from the nearest ancestor that carries it.
	const getTheme = () =>
		triggerRef.current?.closest( '[data-theme]' )?.getAttribute( 'data-theme' ) || 'light';

	const openDropdown = () => {
		if ( triggerRef.current ) {
			setRect( triggerRef.current.getBoundingClientRect() );
		}
		setOpen( true );
	};

	const closeDropdown = () => setOpen( false );

	const handleTriggerClick = () => ( open ? closeDropdown() : openDropdown() );

	// Close on outside click.
	useEffect( () => {
		if ( ! open ) return;
		const handler = ( e ) => {
			if (
				triggerRef.current && ! triggerRef.current.contains( e.target ) &&
				dropdownRef.current && ! dropdownRef.current.contains( e.target )
			) {
				closeDropdown();
			}
		};
		document.addEventListener( 'mousedown', handler );
		return () => document.removeEventListener( 'mousedown', handler );
	}, [ open ] );

	// Reposition on scroll or resize.
	useEffect( () => {
		if ( ! open ) return;
		const reposition = () => {
			if ( triggerRef.current ) {
				setRect( triggerRef.current.getBoundingClientRect() );
			}
		};
		window.addEventListener( 'scroll', reposition, true );
		window.addEventListener( 'resize', reposition );
		return () => {
			window.removeEventListener( 'scroll', reposition, true );
			window.removeEventListener( 'resize', reposition );
		};
	}, [ open ] );

	const toggle = ( val ) => {
		if ( selected.includes( val ) ) {
			onChange( selected.filter( ( v ) => v !== val ) );
		} else {
			onChange( [ ...selected, val ] );
		}
	};

	const dropdown = open && rect && createPortal(
		<div
			ref={ dropdownRef }
			// Mirror the app theme so CSS variables resolve correctly outside the app root.
			data-theme={ getTheme() }
			className="ww-multiselect-portal"
			style={ {
				position: 'fixed',
				top: rect.bottom + 6,
				left: rect.left,
				width: rect.width,
				zIndex: 999999,
				background: 'var(--ww-input-bg)',
				border: '1.5px solid var(--ww-border-input)',
				borderRadius: 10,
				boxShadow: '0 8px 24px rgba(0,0,0,0.18)',
				maxHeight: 260,
				overflowY: 'auto',
				fontFamily: "'DM Sans', sans-serif",
				fontSize: 14,
			} }
		>
			{ options.map( ( o ) => {
				const isSelected = selected.includes( o.value );
				return (
					<div
						key={ o.value }
						onMouseDown={ ( e ) => { e.preventDefault(); toggle( o.value ); } }
						style={ {
							display: 'flex', alignItems: 'center', gap: 10,
							padding: '10px 14px', cursor: 'pointer',
							background: isSelected ? 'var(--ww-accent-light)' : 'transparent',
							color: isSelected ? 'var(--ww-accent)' : 'var(--ww-text)',
							transition: 'background 0.1s',
						} }
						onMouseEnter={ ( e ) => {
							if ( ! isSelected ) e.currentTarget.style.background = 'var(--ww-hover-bg)';
						} }
						onMouseLeave={ ( e ) => {
							e.currentTarget.style.background = isSelected ? 'var(--ww-accent-light)' : 'transparent';
						} }
					>
						<div style={ {
							width: 18, height: 18, borderRadius: 5, flexShrink: 0,
							border: `2px solid ${ isSelected ? 'var(--ww-accent)' : 'var(--ww-border-input)' }`,
							background: isSelected ? 'var(--ww-accent)' : 'transparent',
							display: 'flex', alignItems: 'center', justifyContent: 'center',
							transition: 'all 0.15s',
						} }>
							{ isSelected && <Icon name="check" size={ 11 } color="white" strokeWidth={ 2.5 }/> }
						</div>
						{ o.label }
					</div>
				);
			} ) }
		</div>,
		document.body
	);

	return (
		<div ref={ triggerRef } style={ { position: 'relative', zIndex: open ? 50 : 'auto' } }>
			<div
				onClick={ handleTriggerClick }
				style={ {
					minHeight: 42,
					background: 'var(--ww-input-bg)',
					border: `1.5px solid ${ open ? 'var(--ww-accent)' : 'var(--ww-border-input)' }`,
					borderRadius: 10, padding: '6px 10px', cursor: 'pointer',
					display: 'flex', flexWrap: 'wrap', gap: 6, alignItems: 'center',
					transition: 'border-color 0.15s',
				} }
			>
				{ selected.length === 0 && (
					<span style={ { fontSize: 14, color: 'var(--ww-text-muted)' } }>
						{ field.placeholder || __( 'Select…', 'woo-wallet' ) }
					</span>
				) }
				{ selected.map( ( s ) => {
					const opt = options.find( ( o ) => o.value === s );
					return (
						<span key={ s } style={ {
							display: 'inline-flex', alignItems: 'center', gap: 5,
							background: 'var(--ww-accent-light)', color: 'var(--ww-accent)',
							borderRadius: 6, padding: '3px 8px', fontSize: 12, fontWeight: 500,
						} }>
							{ opt ? opt.label : s }
							<span
								onMouseDown={ ( e ) => { e.stopPropagation(); e.preventDefault(); toggle( s ); } }
								style={ { cursor: 'pointer', opacity: 0.7, display: 'flex', alignItems: 'center' } }
							>
								<Icon name="x" size={ 12 }/>
							</span>
						</span>
					);
				} ) }
				<div style={ { marginLeft: 'auto', color: 'var(--ww-text-muted)', flexShrink: 0 } }>
					<Icon name="chevron" size={ 16 }/>
				</div>
			</div>
			{ dropdown }
		</div>
	);
}
