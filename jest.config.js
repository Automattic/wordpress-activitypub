const defaultConfig = require( '@wordpress/scripts/config/jest-unit.config.js' );

module.exports = {
	...defaultConfig,
	testMatch: [ '**/tests/js/**/*.[jt]s?(x)', '**/?(*.)+(spec|test).[jt]s?(x)' ],
	testPathIgnorePatterns: [ '/node_modules/', '/tests/e2e/', '/tests/phpunit/', '/vendor/', '/build/' ],
	setupFilesAfterEnv: [ '<rootDir>/jest.setup.js' ],
	moduleNameMapper: {
		...defaultConfig.moduleNameMapper,
		'^@wordpress/interactivity$': '<rootDir>/__mocks__/@wordpress/interactivity.js',
	},
};
