/**
 * Custom webpack configuration extending WordPress Scripts
 *
 * This configuration:
 * - Places JS chunks in their source directory structure (src/foo/ → build/foo/)
 * - Handles social-web vendor chunks separately
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const processConfig = ( config ) => {
	return {
		...config,
		output: {
			...config.output,
			// Place JS chunks in their source directory
			chunkFilename: ( pathData ) => {
				const chunk = pathData.chunk;

				if ( chunk && chunk.getModules ) {
					for ( const module of chunk.getModules() ) {
						const modulePath = module.resource || module.context || '';
						const srcMatch = modulePath.match( /\/src\/([^/]+)\// );
						if ( srcMatch ) {
							return `${ srcMatch[ 1 ] }/[name].js`;
						}
					}
				}

				return '[name].js';
			},
		},
		optimization: {
			...config.optimization,
			splitChunks: {
				...( config.optimization?.splitChunks || {} ),
				cacheGroups: {
					...( config.optimization?.splitChunks?.cacheGroups || {} ),
					// Social-web vendor chunk
					socialWebVendors: {
						test: /[\\/]node_modules[\\/]/,
						name: 'social-web/vendors',
						chunks: ( chunk ) => chunk.name && chunk.name.startsWith( 'social-web/' ),
						priority: 20,
						reuseExistingChunk: true,
						enforce: true,
					},
					// Disable default vendors to avoid unused files
					default: false,
					defaultVendors: false,
				},
			},
		},
	};
};

module.exports = Array.isArray( defaultConfig ) ? defaultConfig.map( processConfig ) : processConfig( defaultConfig );
