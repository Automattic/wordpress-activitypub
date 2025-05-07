const wpPrettierConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpPrettierConfig,

	printWidth: 120,
	overrides: [
		{
			files: '*.json',
			options: {
				useTabs: false,
			},
		},
		{
			files: '*.yml',
			options: {
				useTabs: false,
				tabWidth: 2,
			},
		},
		{
			files: '*.md',
			options: {
				trimTrailingWhitespace: false, // Not a valid Prettier option, handled by editorconfig only
			},
		},
	],
};
