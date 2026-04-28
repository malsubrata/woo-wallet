import { useState, useEffect } from '@wordpress/element';
import Icon from '../components/Icon';

export default function AttachmentField( { value, onChange } ) {
	const [ preview, setPreview ] = useState( null );
	const attachmentId = parseInt( value, 10 ) || 0;

	useEffect( () => {
		if ( attachmentId > 0 && window.wp && wp.media ) {
			const attachment = wp.media.attachment( attachmentId );
			attachment.fetch().then( () => {
				setPreview( attachment.get( 'url' ) );
			} );
		} else {
			setPreview( null );
		}
	}, [ attachmentId ] );

	const openMediaLibrary = () => {
		if ( ! window.wp || ! wp.media ) return;
		const frame = wp.media( {
			title: 'Select Image',
			button: { text: 'Use this image' },
			multiple: false,
			library: { type: 'image' },
		} );
		frame.on( 'select', () => {
			const attachment = frame.state().get( 'selection' ).first().toJSON();
			onChange( attachment.id );
			setPreview( attachment.url );
		} );
		frame.open();
	};

	const bg = preview
		? `url(${ preview }) center/cover`
		: 'var(--ww-surface-2)';

	return (
		<div style={ { display: 'flex', flexDirection: 'column', gap: 8 } }>
			<div
				onClick={ openMediaLibrary }
				style={ {
					border: `2px dashed var(--ww-border-input)`, borderRadius: 12,
					padding: preview ? 0 : '28px 20px', textAlign: 'center', cursor: 'pointer',
					background: bg,
					height: preview ? 120 : 'auto',
					transition: 'border-color 0.15s',
					position: 'relative', overflow: 'hidden',
				} }
				onMouseEnter={ ( e ) => e.currentTarget.style.borderColor = 'var(--ww-accent)' }
				onMouseLeave={ ( e ) => e.currentTarget.style.borderColor = 'var(--ww-border-input)' }
			>
				{ ! preview && (
					<>
						<div style={ { display: 'flex', justifyContent: 'center', marginBottom: 10, color: 'var(--ww-accent-mid)' } }>
							<Icon name="upload" size={ 24 }/>
						</div>
						<p style={ { fontSize: 13, fontWeight: 500, color: 'var(--ww-text-label)' } }>
							Click to upload image
						</p>
						<p style={ { fontSize: 11, color: 'var(--ww-text-hint)', marginTop: 4 } }>
							PNG, JPG or WebP
						</p>
					</>
				) }
			</div>
			{ preview && (
				<button
					type="button"
					onClick={ () => { onChange( 0 ); setPreview( null ); } }
					style={ {
						fontSize: 12, color: 'var(--ww-text-muted)', background: 'none',
						border: '1px solid var(--ww-border-input)', borderRadius: 6,
						padding: '4px 10px', cursor: 'pointer', alignSelf: 'flex-start',
					} }
				>
					Remove
				</button>
			) }
		</div>
	);
}
