<?php
/**
 * Transaction category registry.
 *
 * Every wallet transaction has a semantic kind ("category") in addition to
 * the credit/debit `type` column. Since 1.6.3 the category lives as a
 * first-class column on `woo_wallet_transactions`; this file defines the
 * canonical core set, lets third-party plugins register their own slugs via
 * the `woo_wallet_transaction_types` filter, and provides the small helpers
 * the read paths and admin UI use to display them.
 *
 * @package StandaleneTech
 * @since 1.6.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get the registered transaction categories.
 *
 * The returned array is keyed by slug. Each value is an array with:
 *   - label             (string) human-readable label, translated.
 *   - description       (string) short admin help text, translated.
 *   - default_template  (string) example template string shown as a hint in
 *                       the admin "Transaction descriptions" tab. May contain
 *                       `{order_id}`, `{amount}`, `{user_name}`, `{currency}`,
 *                       `{original_details}` token placeholders.
 *
 * Filter: `woo_wallet_transaction_types` — merge in additional categories.
 * Filtered slugs must be 32 characters or fewer (column width) and should be
 * snake_case ASCII. Unknown slugs that appear at read time fall back to the
 * `other` label for display.
 *
 * @return array
 */
function woo_wallet_get_transaction_types() {
	$types = array(
		'topup'               => array(
			'label'            => __( 'Top up', 'woo-wallet' ),
			'description'      => __( 'Funds added to the wallet via a WooCommerce gateway.', 'woo-wallet' ),
			'default_template' => '',
		),
		'cashback'            => array(
			'label'            => __( 'Cashback', 'woo-wallet' ),
			'description'      => __( 'Earned through cashback rules on completed orders.', 'woo-wallet' ),
			'default_template' => '',
		),
		'cashback_adjustment' => array(
			'label'            => __( 'Cashback adjustment', 'woo-wallet' ),
			'description'      => __( 'Manual or automated correction to a previously credited cashback.', 'woo-wallet' ),
			'default_template' => '',
		),
		'cashback_refund'     => array(
			'label'            => __( 'Cashback refund', 'woo-wallet' ),
			'description'      => __( 'Cashback unwound when its originating order is refunded.', 'woo-wallet' ),
			'default_template' => '',
		),
		'partial_payment'     => array(
			'label'            => __( 'Partial payment', 'woo-wallet' ),
			'description'      => __( 'Wallet debit applied to an order at checkout.', 'woo-wallet' ),
			'default_template' => '',
		),
		'transfer'            => array(
			'label'            => __( 'Transfer', 'woo-wallet' ),
			'description'      => __( 'Peer-to-peer transfer between two customer wallets.', 'woo-wallet' ),
			'default_template' => '',
		),
		'refund'              => array(
			'label'            => __( 'Refund', 'woo-wallet' ),
			'description'      => __( 'Order refund credited back to the wallet.', 'woo-wallet' ),
			'default_template' => '',
		),
		'adjustment'          => array(
			'label'            => __( 'Adjustment', 'woo-wallet' ),
			'description'      => __( 'Manual admin credit or debit.', 'woo-wallet' ),
			'default_template' => '',
		),
		'vendor_commission'   => array(
			'label'            => __( 'Vendor commission', 'woo-wallet' ),
			'description'      => __( 'Marketplace commission paid into a vendor wallet.', 'woo-wallet' ),
			'default_template' => '',
		),
		'other'               => array(
			'label'            => __( 'Other', 'woo-wallet' ),
			'description'      => __( 'Anything not matching a known category.', 'woo-wallet' ),
			'default_template' => '',
		),
	);

	$filtered = apply_filters( 'woo_wallet_transaction_types', $types );
	if ( ! is_array( $filtered ) ) {
		return $types;
	}

	// Normalise: each entry must be an array with at least a `label`. Drop the
	// rest — third-party slugs with garbage shapes shouldn't crash the admin UI.
	$normalised = array();
	foreach ( $filtered as $slug => $cfg ) {
		if ( ! is_string( $slug ) || '' === $slug ) {
			continue;
		}
		$slug = substr( $slug, 0, 32 );
		if ( ! is_array( $cfg ) || empty( $cfg['label'] ) ) {
			continue;
		}
		$normalised[ $slug ] = array(
			'label'            => (string) $cfg['label'],
			'description'      => isset( $cfg['description'] ) ? (string) $cfg['description'] : '',
			'default_template' => isset( $cfg['default_template'] ) ? (string) $cfg['default_template'] : '',
		);
	}

	// Ensure `other` is always present.
	if ( ! isset( $normalised['other'] ) ) {
		$normalised['other'] = $types['other'];
	}

	return $normalised;
}

/**
 * Display label for a category slug. Falls back to the `other` label when the
 * slug isn't registered.
 *
 * @param string $slug Category slug.
 * @return string
 */
function woo_wallet_get_transaction_type_label( $slug ) {
	$types = woo_wallet_get_transaction_types();
	if ( isset( $types[ $slug ] ) ) {
		return $types[ $slug ]['label'];
	}
	return $types['other']['label'];
}

/**
 * Is this slug a known (core or third-party-registered) category?
 *
 * @param string $slug Category slug.
 * @return bool
 */
function woo_wallet_is_known_transaction_type( $slug ) {
	$types = woo_wallet_get_transaction_types();
	return isset( $types[ $slug ] );
}

/**
 * Render a per-category description template.
 *
 * Replaces a small fixed set of token placeholders with their concrete values:
 *
 *   {order_id}, {amount}, {user_name}, {currency}, {original_details}
 *
 * Unknown tokens are left intact. The renderer does no HTML interpretation —
 * the `details` column is plain text — and is safe to call with any string.
 *
 * @param string $template Template string (may be empty).
 * @param array  $tokens   Token => value map.
 * @return string Rendered string. Empty template returns ''.
 */
function woo_wallet_render_transaction_template( $template, array $tokens ) {
	if ( ! is_string( $template ) || '' === $template ) {
		return '';
	}

	$replacements = array();
	foreach ( $tokens as $key => $value ) {
		if ( ! is_scalar( $value ) && null !== $value ) {
			continue;
		}
		$replacements[ '{' . $key . '}' ] = (string) $value;
	}

	return strtr( $template, $replacements );
}

/**
 * Look up the admin-configured template for a category. Returns '' when no
 * template is set, in which case the caller-supplied `details` string is
 * preserved verbatim.
 *
 * Templates live in the `_wallet_settings_transaction_descriptions` settings
 * tab (registered via the legacy `woo_wallet_settings_sections` /
 * `woo_wallet_settings_fields` filters below). The option key matches the
 * section id; each registered category slug becomes one field name in the
 * stored array.
 *
 * @param string $slug Category slug.
 * @return string
 */
function woo_wallet_get_transaction_type_template( $slug ) {
	$templates = get_option( '_wallet_settings_transaction_descriptions', array() );
	if ( ! is_array( $templates ) ) {
		return '';
	}
	if ( isset( $templates[ $slug ] ) && is_string( $templates[ $slug ] ) ) {
		return $templates[ $slug ];
	}
	return '';
}

/**
 * Register the "Transaction descriptions" tab on the admin settings page via
 * the legacy `woo_wallet_settings_sections` / `woo_wallet_settings_fields`
 * filters. One textarea per registered category; placeholder text shows the
 * available tokens.
 *
 * Stored under option key `_wallet_settings_transaction_descriptions` (matches
 * the section id). Each field name is the category slug, so the stored value
 * is `[ slug => template-string ]`, which is exactly the shape
 * `woo_wallet_get_transaction_type_template()` expects.
 */
add_filter(
	'woo_wallet_settings_sections',
	function ( $sections ) {
		if ( ! is_array( $sections ) ) {
			return $sections;
		}
		$sections[] = array(
			'id'          => '_wallet_settings_transaction_descriptions',
			'title'       => __( 'Transaction descriptions', 'woo-wallet' ),
			'description' => __( 'Per-category description templates. When set, the template overrides the system-generated description on new transactions.', 'woo-wallet' ),
			'icon'        => 'dashicons-edit',
		);
		return $sections;
	}
);

add_filter(
	'woo_wallet_settings_fields',
	function ( $fields ) {
		if ( ! is_array( $fields ) ) {
			$fields = array();
		}
		$tokens_hint = sprintf(
			/* translators: list of supported template tokens */
			__( 'Available tokens: %1$s. %2$sLeave blank to keep the system-generated description.', 'woo-wallet' ),
			'<code>{order_id}</code>, <code>{amount}</code>, <code>{currency}</code>, <code>{original_details}</code>',
			'<br/>'
		);

		$category_fields = array();
		$first           = true;
		foreach ( woo_wallet_get_transaction_types() as $slug => $cfg ) {
			$field = array(
				'name'        => $slug,
				'label'       => $cfg['label'],
				'desc'        => $cfg['description'] . '<br/><small>' . $tokens_hint . '</small>',
				'type'        => 'textarea',
				'default'     => isset( $cfg['default_template'] ) ? $cfg['default_template'] : '',
				'placeholder' => sprintf(
					/* translators: %s: example category template */
					__( 'e.g. "%s"', 'woo-wallet' ),
					'{original_details} (#{order_id})'
				),
				'group'       => 'transaction_descriptions',
			);
			if ( $first ) {
				$field['group_title']       = __( 'Transaction description templates', 'woo-wallet' );
				$field['group_description'] = __( 'These templates replace the auto-generated description on each new transaction record.', 'woo-wallet' );
				$first                      = false;
			}
			$category_fields[] = $field;
		}

		$fields['_wallet_settings_transaction_descriptions'] = $category_fields;
		return $fields;
	}
);
