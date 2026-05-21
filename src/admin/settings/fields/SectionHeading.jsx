/**
 * Renders a section heading row inside a settings panel — an uppercase title
 * with an optional descriptive paragraph. Used for `type: section_heading`
 * fields emitted by the PHP action-settings transform.
 */
export default function SectionHeading( { field } ) {
	return (
		<div
			style={ {
				borderTop: '1px solid var(--ww-border)',
				paddingTop: 16,
			} }
		>
			<h4
				style={ {
					margin: 0,
					fontSize: 13,
					fontWeight: 700,
					textTransform: 'uppercase',
					letterSpacing: '0.04em',
					color: 'var(--ww-text-heading)',
				} }
				dangerouslySetInnerHTML={ { __html: field.label || '' } }
			/>
			{ field.hint && (
				<p
					style={ {
						margin: '4px 0 0',
						fontSize: 12,
						color: 'var(--ww-text-muted)',
						lineHeight: 1.5,
					} }
					dangerouslySetInnerHTML={ { __html: field.hint } }
				/>
			) }
		</div>
	);
}
