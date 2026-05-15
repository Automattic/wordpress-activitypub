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
	 * Allow `uuid` to be transformed by Babel. uuid@13+ ships as ESM, and a
	 * nested copy gets pulled in transitively by @wordpress/components. Jest's
	 * default `transformIgnorePatterns` skips everything under node_modules,
	 * which would otherwise leave the bare `export` syntax in place and fail
	 * any test suite that touches @wordpress/components.
	 */
	transformIgnorePatterns: [ 'node_modules/(?!(uuid)/)' ],
};
