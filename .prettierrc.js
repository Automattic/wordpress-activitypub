const wpPrettierConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpPrettierConfig,

	printWidth: 120,
	overrides: [
		{
			files: '*.yml',
			options: {
				useTabs: false,
				tabWidth: 2,
			},
		},
	],
};
