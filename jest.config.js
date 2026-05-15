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
	// Allow `uuid` to be transformed by Babel at any depth in node_modules.
	// `node_modules/(?!(uuid)/)` only excluded the top-level copy; the nested
	// `node_modules/@wordpress/components/node_modules/uuid/` still matched
	// the outer `node_modules/` and stayed in the ignore set. The negative
	// lookahead with an optional inner path skips the ignore for uuid at any
	// depth so its ESM `export` syntax reaches Babel.
	transformIgnorePatterns: [ '/node_modules/(?!(?:.*/)?uuid/)' ],
};
