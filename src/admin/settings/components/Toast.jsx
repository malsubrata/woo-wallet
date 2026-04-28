import { useEffect, useState } from '@wordpress/element';
import Icon from './Icon';

function ToastItem( { toast, onDismiss } ) {
	const [ visible, setVisible ] = useState( false );

	useEffect( () => {
		const enterTimer = requestAnimationFrame( () => setVisible( true ) );
		const exitTimer  = setTimeout( () => setVisible( false ), toast.duration - 350 );
		return () => {
			cancelAnimationFrame( enterTimer );
			clearTimeout( exitTimer );
		};
	}, [] );

	const isSuccess = toast.type === 'success';
	const bgColor   = isSuccess ? 'oklch(0.35 0.12 155)' : 'oklch(0.42 0.18 25)';
	const iconName  = isSuccess ? 'check' : 'x';

	return (
		<div
			style={ {
				display: 'flex', alignItems: 'center', gap: 12,
				padding: '13px 20px',
				background: bgColor,
				color: 'white',
				borderRadius: 12,
				boxShadow: '0 8px 32px oklch(0.1 0.05 260 / 0.35)',
				fontSize: 13, fontWeight: 500,
				minWidth: 280, maxWidth: 420,
				cursor: 'pointer',
				opacity: visible ? 1 : 0,
				transform: visible ? 'translateY(0)' : 'translateY(24px)',
				transition: 'opacity 0.32s ease, transform 0.32s cubic-bezier(0.34,1.56,0.64,1)',
				userSelect: 'none',
				whiteSpace: 'nowrap',
			} }
			onClick={ onDismiss }
		>
			<div style={ {
				width: 28, height: 28, borderRadius: '50%',
				background: 'oklch(1 0 0 / 0.18)',
				display: 'flex', alignItems: 'center', justifyContent: 'center',
				flexShrink: 0,
			} }>
				<Icon name={ iconName } size={ 14 } color="white" strokeWidth={ 2.5 }/>
			</div>
			<span style={ { flex: 1, lineHeight: 1.4 } }>{ toast.message }</span>
		</div>
	);
}

export default function ToastStack( { toasts, onDismiss } ) {
	if ( ! toasts.length ) return null;

	return (
		<div style={ {
			position: 'fixed',
			bottom: 32,
			left: '50%',
			transform: 'translateX(-50%)',
			zIndex: 99999,
			display: 'flex', flexDirection: 'column', gap: 10,
			alignItems: 'center',
			pointerEvents: 'none',
		} }>
			{ toasts.map( ( toast ) => (
				<div key={ toast.id } style={ { pointerEvents: 'auto' } }>
					<ToastItem toast={ toast } onDismiss={ () => onDismiss( toast.id ) }/>
				</div>
			) ) }
		</div>
	);
}
