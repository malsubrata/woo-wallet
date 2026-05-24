/**
 * Renders a section heading row inside a settings panel — an uppercase title
 * with an optional descriptive paragraph. Used for `type: section_heading`
 * fields emitted by the PHP action-settings transform.
 *
 * `label` and `hint` flow through third-party PHP filters
 * (`woo_wallet_action_*_form_fields`) and can also be supplied by JS-registered
 * tabs (`window.wooWallet.settings.registerField`) which bypass the PHP REST
 * sanitisation layer entirely. Both are therefore rendered as plain text nodes
 * so no caller — trusted or otherwise — can inject markup or scripts.
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
			>
				{ field.label || '' }
			</h4>
			{ field.hint && (
				<p
					style={ {
						margin: '4px 0 0',
						fontSize: 12,
						color: 'var(--ww-text-muted)',
						lineHeight: 1.5,
					} }
				>
					{ field.hint }
				</p>
			) }
		</div>
	);
}
