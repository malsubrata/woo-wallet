export default function HtmlField( { field } ) {
	if ( ! field.html ) return null;
	return (
		<div
			style={ { fontSize: 13, color: 'var(--ww-text-hint)', lineHeight: 1.6 } }
			dangerouslySetInnerHTML={ { __html: field.html } }
		/>
	);
}
