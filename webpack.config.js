/**
 * Custom webpack configuration extending WordPress Scripts
 *
 * This configuration:
 * - Places JS chunks in their source directory structure (src/foo/ → build/foo/)
 * - Handles app vendor chunks separately
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const processConfig = ( config ) => {
	// Remove the default DependencyExtractionWebpackPlugin to add our custom one
	const filteredPlugins = config.plugins.filter(
		( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
	);

	// @wordpress/views is in BUNDLED_PACKAGES but WordPress core ships it as wp-views
	// We need to externalize it to avoid bundling
	filteredPlugins.push(
		new DependencyExtractionWebpackPlugin( {
			requestToExternal( request ) {
				if ( request === '@wordpress/views' ) {
					return [ 'wp', 'views' ];
				}
			},
			requestToHandle( request ) {
				if ( request === '@wordpress/views' ) {
					return 'wp-views';
				}
			},
		} )
	);

	return {
		...config,
		plugins: filteredPlugins,
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
