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
};
