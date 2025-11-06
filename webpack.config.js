/**
 * Custom webpack configuration extending WordPress Scripts
 *
 * This configuration:
 * - Properly names and places vendor chunks in the social-web directory
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// WordPress Scripts might return different configs for different commands
// We need to handle both single config and array of configs
const processConfig = ( config ) => {
	return {
		...config,
		optimization: {
			...config.optimization,
			splitChunks: {
				...( config.optimization?.splitChunks || {} ),
				cacheGroups: {
					...( config.optimization?.splitChunks?.cacheGroups || {} ),
					// Vendor chunk for social-web modules
					socialWebVendors: {
						test: /[\\/]node_modules[\\/]/,
						name: 'social-web/vendors',
						chunks( chunk ) {
							// Only include in this vendor bundle if the chunk is from social-web
							return chunk.name && chunk.name.startsWith( 'social-web/' );
						},
						priority: 20, // Higher priority to override default
						reuseExistingChunk: true,
						enforce: true, // Force this configuration
					},
				},
			},
		},
	};
};

// Handle both single config object and array of configs
module.exports = Array.isArray( defaultConfig ) ? defaultConfig.map( processConfig ) : processConfig( defaultConfig );
