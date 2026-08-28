const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	testMatch: [ '**/tests/js/**/*.[jt]s?(x)', '**/?(*.)+(spec|test).[jt]s?(x)' ],
	testPathIgnorePatterns: [
		'/build/',
		'/node_modules/',
		'/tests/e2e/',
		'/tests/js/__mocks__/',
		'/tests/phpunit/',
		'/vendor/',
	],
	setupFilesAfterEnv: [ '<rootDir>/jest.setup.js' ],
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'^@wordpress/interactivity$': '<rootDir>/tests/js/__mocks__/@wordpress/interactivity.js',
	},
	/*
	 * The preset only transforms .js/.jsx/.ts/.tsx. `@wordpress/theme` (a new transitive dependency
	 * of `@wordpress/components` 37) is ESM-only and ships as .mjs, so add a rule to run it through
	 * the same Babel transform; without this the un-ignore below is not enough.
	 */
	transform: {
		...defaultConfig.transform,
		'\\.mjs$': require.resolve( '@wordpress/scripts/config/babel-transform' ),
	},
	/*
	 * Allow ESM/TypeScript-only packages to be transformed by Babel at any depth in node_modules.
	 * `node_modules/(?!(uuid)/)` only excluded the top-level copy; the nested
	 * `node_modules/@wordpress/components/node_modules/uuid/` still matched the outer
	 * `node_modules/` and stayed in the ignore set. The negative lookahead with an optional inner
	 * path skips the ignore at any depth so the untranspiled `import`/`export` syntax reaches Babel.
	 * `@wordpress/components` 37 pulls in `@wordpress/ui` (raw TS source) and `@wordpress/theme`
	 * (an `.mjs` ESM module), both of which must be transformed too.
	 */
	transformIgnorePatterns: [ '/node_modules/(?!(?:.*/)?(?:uuid|@wordpress/(?:theme|ui))/)' ],
};
