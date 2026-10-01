const presetConfig = require( '@wordpress/jest-preset-default' );

const babelTransform = [
	require.resolve( 'babel-jest' ),
	{ presets: [ require.resolve( '@wordpress/babel-preset-default' ) ] },
];

module.exports = {
	...presetConfig,
	testMatch: [ '**/tests/js/**/*.[jt]s?(x)', '**/?(*.)+(spec|test).[jt]s?(x)' ],
	testPathIgnorePatterns: [
		'/build/',
		'/node_modules/',
		'/tests/e2e/',
		'/tests/js/__mocks__/',
		'/tests/phpunit/',
		'/vendor/',
	],
	setupFilesAfterEnv: [ ...presetConfig.setupFilesAfterEnv, '<rootDir>/jest.setup.js' ],
	moduleNameMapper: {
		...presetConfig.moduleNameMapper,
		'^@wordpress/interactivity$': '<rootDir>/tests/js/__mocks__/@wordpress/interactivity.js',
	},
	/*
	 * Babel is configured here rather than in a root config file, so the transform stays scoped to
	 * the tests: a root Babel config would also apply to the webpack build.
	 *
	 * `@wordpress/theme` (a transitive dependency of `@wordpress/components` 37) is ESM-only and
	 * ships as .mjs, so it needs the same transform as the .js/.ts sources.
	 */
	transform: {
		'\\.[jt]sx?$': babelTransform,
		'\\.mjs$': babelTransform,
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
