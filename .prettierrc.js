const wpPrettierConfig = require( '@wordpress/prettier-config' );

module.exports = {
	...wpPrettierConfig,

	printWidth: 120,
	plugins: [require.resolve('./bin/prettier/sort-php-imports.js')],
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
