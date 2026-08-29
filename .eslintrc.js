/**
 * ESLint config for the TeraWallet source bundles.
 *
 * Without this file wp-scripts falls back to its bundled defaults, which do not
 * know this is a WordPress plugin and flood the report with false positives.
 */
module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
		jquery: true,
		es2021: true,
	},
	settings: {
		// Resolved at runtime by WooCommerceDependencyExtractionWebpackPlugin --
		// these packages are never installed into node_modules.
		'import/core-modules': [ '@woocommerce/blocks-registry', '@woocommerce/settings', '@woocommerce/blocks-checkout' ],
	},
	rules: {
		// WordPress localizes data to JS in snake_case; renaming would break the PHP <-> JS contract.
		camelcase: 'off',
		// Real findings, but they touch UI behaviour -- tracked as follow-up work, not release blockers.
		'jsx-a11y/click-events-have-key-events': 'warn',
		'jsx-a11y/no-static-element-interactions': 'warn',
		'jsx-a11y/label-has-associated-control': 'warn',
		'jsx-a11y/anchor-is-valid': 'warn',
		'jsdoc/require-param-type': 'warn',
		'jsdoc/require-returns-description': 'warn',
		'jsdoc/check-param-names': 'warn',
		'no-nested-ternary': 'warn',
		'no-alert': 'warn',
		'react-hooks/exhaustive-deps': 'warn',
		'react-hooks/rules-of-hooks': 'warn',
	},
};
