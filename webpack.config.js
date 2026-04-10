/**
 * Custom webpack configuration extending WordPress Scripts
 *
 * This configuration:
 * - Places JS chunks in their source directory structure (src/foo/ → build/foo/)
 * - Handles app vendor chunks separately
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

const processConfig = ( config ) => {
	return {
		...config,
		output: {
			...config.output,
			// Place JS chunks in their source directory with content hash for cache busting
			chunkFilename: ( pathData ) => {
				const chunk = pathData.chunk;
				const chunkGraph = pathData.webpack?.chunkGraph;

				if ( chunk && chunkGraph ) {
					for ( const module of chunkGraph.getChunkModules( chunk ) ) {
						const modulePath = module.resource || module.context || '';
						const srcMatch = modulePath.match( /\/src\/([^/]+)\// );
						if ( srcMatch ) {
							return `${ srcMatch[ 1 ] }/[name].[contenthash:8].js`;
						}
					}
				}

				return '[name].[contenthash:8].js';
			},
		},
		optimization: {
			...config.optimization,
			splitChunks: {
				...( config.optimization?.splitChunks || {} ),
				cacheGroups: {
					...( config.optimization?.splitChunks?.cacheGroups || {} ),
					// TanStack Router - loaded async since it's large (~250KB)
					tanstackRouter: {
						test: /[\\/]node_modules[\\/]@tanstack[\\/]/,
						name: 'app/tanstack-router',
						chunks: ( chunk ) => chunk.name && chunk.name.startsWith( 'app/' ),
						priority: 30,
						reuseExistingChunk: true,
						enforce: true,
					},
					// App vendor chunk (other dependencies)
					appVendors: {
						test: /[\\/]node_modules[\\/]/,
						name: 'app/vendors',
						chunks: ( chunk ) => chunk.name && chunk.name.startsWith( 'app/' ),
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
