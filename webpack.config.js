/**
 * ActivityPub webpack configuration
 *
 * This configuration extends the default @wordpress/scripts webpack config
 * to include assets from the /assets folder without overwriting existing entries.
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const RemoveEmptyScriptsPlugin = require( 'webpack-remove-empty-scripts' );
const path = require( 'path' );
const { sync: glob } = require( 'fast-glob' );

/**
 * Gets dynamic entry points from the assets directory.
 *
 * @return {Object} Entry points object.
 */
function getAssetEntryPoints() {
	const entries = {};

	// Get all JS, CSS, and SCSS files from src directory except blocks.
	const files = glob( './src/**/*.{js,css,scss}', {
		absolute: true,
		cwd: process.cwd(),
		ignore: [ './src/blocks/**/*' ],
	} );

	// Create entry points for all files.
	files.forEach( ( file ) => {
		const relativePath = path.relative( path.join( process.cwd(), 'src' ), file );
		const entryKey = relativePath.replace( path.extname( file ), '' );

		entries[ entryKey ] = file;
	} );

	return entries;
}
const [ standardConfig, ...otherConfigs ] = defaultConfig;

// Create a new configuration that extends the default one.
const modifiedConfig = {
	...standardConfig,
	entry: () => ( {
		...standardConfig.entry(),
		...getAssetEntryPoints(),
	} ),
	plugins: [ new RemoveEmptyScriptsPlugin(), ...standardConfig.plugins ],
};

module.exports = [ modifiedConfig, ...otherConfigs ];
