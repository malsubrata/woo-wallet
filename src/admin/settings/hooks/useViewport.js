import { useState, useEffect } from '@wordpress/element';

/**
 * Viewport-size hook.
 *
 * The settings app is styled with inline style objects, which cannot carry
 * CSS media queries — so responsive layout decisions are made in JS instead.
 * `isCompact` matches WordPress admin's own 782px breakpoint, where the admin
 * menu collapses; below it the wallet sidebar becomes an overlay drawer.
 *
 * @return {{ width: number, isCompact: boolean, isPhone: boolean }} Viewport flags.
 */
export default function useViewport() {
	const read = () =>
		typeof window !== 'undefined' ? window.innerWidth : 1280;

	const [ width, setWidth ] = useState( read );

	useEffect( () => {
		let frame = null;
		const onResize = () => {
			if ( frame ) {
				return;
			}
			frame = window.requestAnimationFrame( () => {
				frame = null;
				setWidth( read() );
			} );
		};
		window.addEventListener( 'resize', onResize );
		return () => {
			window.removeEventListener( 'resize', onResize );
			if ( frame ) {
				window.cancelAnimationFrame( frame );
			}
		};
	}, [] );

	return {
		width,
		isCompact: width <= 782,
		isPhone: width <= 480,
	};
}
