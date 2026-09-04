/**
 * ESLint flat config for the TeraWallet source bundles.
 *
 * @wordpress/scripts 34 ships ESLint 9, which only recognizes flat config
 * (`eslint.config.*`) -- it no longer reads `.eslintrc.js`, and silently
 * falls back to its own bundled default instead of erroring. This file
 * replaces the old `.eslintrc.js` with the flat-config equivalent so our
 * overrides (camelcase off, several rules softened to warn) actually apply
 * again. See https://eslint.org/docs/latest/use/configure/migration-guide
 */
const wpPlugin = require( '@wordpress/eslint-plugin' );
const globals = require( 'globals' );

module.exports = [
	{
		ignores: [ '**/build/**', '**/node_modules/**', '**/vendor/**' ],
	},

	...wpPlugin.configs.recommended,

	{
		languageOptions: {
			globals: {
				...globals.browser,
				...globals.es2021,
				jQuery: 'readonly',
				$: 'readonly',
			},
		},
		settings: {
			// Resolved at runtime by WooCommerceDependencyExtractionWebpackPlugin --
			// these packages are never installed into node_modules.
			'import/core-modules': [
				'@woocommerce/blocks-registry',
				'@woocommerce/settings',
				'@woocommerce/blocks-checkout',
				'@woocommerce/blocks-components',
				'@wordpress/api-fetch',
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/element',
				'@wordpress/hooks',
				'@wordpress/html-entities',
				'@wordpress/i18n',
				'@wordpress/plugins',
			],
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
			// New in @wordpress/eslint-plugin 25: flags JSDoc's conventional
			// `{JSX.Element}` return type as an unknown type. Not worth
			// stripping the annotation for.
			'jsdoc/no-undefined-types': 'warn',
			'no-nested-ternary': 'warn',
			'no-alert': 'warn',
			'react-hooks/exhaustive-deps': 'warn',
			'react-hooks/rules-of-hooks': 'warn',
		},
	},

	{
		// Jest globals for the unit tests run by `npm run test:js`.
		files: [ '**/test/**/*.js', '**/*.test.js' ],
		languageOptions: {
			globals: {
				...globals.jest,
			},
		},
	},
];
