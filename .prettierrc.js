const wpPrettierConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpPrettierConfig,

	printWidth: 120,
	plugins: [ '@prettier/plugin-php' ],
	overrides: [
		{
			files: '*.yml',
			options: {
				useTabs: false,
				tabWidth: 2,
			},
		},
		{
			files: '*.php',
			options: {
				phpVersion: '7.4',
				braceStyle: '1tbs',
				tabWidth: 4,
				useTabs: true,
			},
		},
	],
};
