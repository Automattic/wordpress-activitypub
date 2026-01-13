module.exports = {
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	env: {
		browser: true,
	},
	rules: {
		// Allow usage of experimental/unstable WordPress APIs.
		// These are used intentionally throughout the codebase.
		'@wordpress/no-unsafe-wp-apis': 'off',
		// Define React/JSX types for JSDoc.
		'jsdoc/no-undefined-types': [ 'error', { definedTypes: [ 'React', 'JSX' ] } ],
	},
	overrides: [
		{
			// CLI scripts use console for output.
			files: [ 'bin/**/*.js' ],
			rules: {
				'no-console': 'off',
			},
			env: {
				node: true,
				jest: true,
			},
		},
		{
			// Test files use Jest globals and @jest-environment directive.
			files: [ '**/__tests__/**/*.{js,ts,tsx}', '**/*.test.{js,ts,tsx}', 'tests/e2e/**/*.js' ],
			env: {
				jest: true,
			},
			rules: {
				// @jest-environment is a valid Jest directive in JSDoc comments.
				'jsdoc/check-tag-names': [ 'error', { definedTags: [ 'jest-environment' ] } ],
				// Test files commonly mock/suppress console for setup.
				'no-console': 'off',
			},
		},
	],
};
