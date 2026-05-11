/**
 * ESLint flat config.
 *
 * wp-scripts 32 switched to ESLint v10, which only reads flat config
 * (`eslint.config.*`). Legacy `.eslintrc.*` files are silently ignored, so this
 * file ports the previous `.eslintrc.js` overrides onto the flat-config shape.
 */

const globals = require( 'globals' );
const wpPlugin = require( '@wordpress/eslint-plugin' );
const { hasBabelConfig } = require( '@wordpress/scripts/utils' );

const testFiles = [
	'**/__tests__/**/*.{js,ts,tsx}',
	'**/*.test.{js,ts,tsx}',
	'tests/e2e/**/*.js',
	'__mocks__/**/*.js',
	'jest.setup.js',
];

const config = [
	// Global ignores. Pre-built `assets/js/*.js` files ship ES5 and are not
	// authored source; `build/` is generated output; vendor/node_modules speak
	// for themselves.
	{
		ignores: [ 'assets/**', 'build/**', 'node_modules/**', 'vendor/**' ],
	},

	// Base recommended config from @wordpress/eslint-plugin.
	...wpPlugin.configs.recommended,

	// Unit-test overrides from the plugin, scoped to our test file patterns.
	...wpPlugin.configs[ 'test-unit' ].map( ( c ) => ( {
		...c,
		files: testFiles,
	} ) ),

	// Project-wide tweaks.
	{
		languageOptions: {
			globals: {
				...globals.browser,
			},
		},
		rules: {
			// Experimental/unstable Gutenberg APIs are used intentionally.
			'@wordpress/no-unsafe-wp-apis': 'off',
			// React/JSX types referenced from JSDoc comments.
			'jsdoc/no-undefined-types': [ 'error', { definedTypes: [ 'React', 'JSX', 'jqXHR' ] } ],
			// Allow unused callback args (common in map/filter/React hooks),
			// rest-destructuring discards, and `_`-prefixed caught errors.
			'no-unused-vars': [
				'error',
				{
					args: 'none',
					caughtErrorsIgnorePattern: '^(_|error$)',
					ignoreRestSiblings: true,
					varsIgnorePattern: '^_',
				},
			],
		},
	},

	// TypeScript-specific rule tweaks. The plugin is already registered by
	// @wordpress/eslint-plugin's recommended config, so we just override rules.
	{
		files: [ '**/*.{ts,tsx}' ],
		rules: {
			// We rely on shorthand method signatures in type declarations.
			'@typescript-eslint/method-signature-style': 'off',
			'@typescript-eslint/no-unused-vars': [
				'error',
				{
					args: 'none',
					caughtErrorsIgnorePattern: '^(_|error$)',
					ignoreRestSiblings: true,
					varsIgnorePattern: '^_',
				},
			],
		},
	},

	// The ESLint config itself pulls plugin modules that are transitive deps
	// of `@wordpress/scripts`; that's fine — don't ask us to list them twice.
	{
		files: [ 'eslint.config.cjs' ],
		rules: {
			'import/no-extraneous-dependencies': 'off',
		},
	},

	// CLI scripts use console for output and run in Node.
	{
		files: [ 'bin/**/*.js' ],
		languageOptions: {
			globals: { ...globals.node, ...globals.jest },
		},
		rules: {
			'no-console': 'off',
		},
	},

	// Test files: jest globals, allow `@jest-environment` tag, allow console.
	{
		files: testFiles,
		languageOptions: {
			globals: { ...globals.jest },
		},
		rules: {
			'jsdoc/check-tag-names': [ 'error', { definedTags: [ 'jest-environment' ] } ],
			'no-console': 'off',
		},
	},
];

// Babel defaults when the project has no Babel config of its own.
if ( ! hasBabelConfig() ) {
	config.push( {
		languageOptions: {
			parserOptions: {
				requireConfigFile: false,
				babelOptions: {
					presets: [ require.resolve( '@wordpress/babel-preset-default' ) ],
				},
			},
		},
	} );
}

module.exports = config;
