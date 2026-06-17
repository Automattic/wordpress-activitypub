/**
 * Custom webpack configuration extending WordPress Scripts
 *
 * This configuration:
 * - Places JS chunks in their source directory structure (src/foo/ → build/foo/)
 * - Handles app vendor chunks separately
 */

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const CLASSIC_SCRIPT_EXTERNALS = {
	lodash: 'lodash',
	'lodash-es': 'lodash',
	moment: 'moment',
	react: 'React',
	'react-dom': 'ReactDOM',
	'react-dom/client': 'ReactDOM',
	'react/jsx-runtime': 'ReactJSXRuntime',
	'react/jsx-dev-runtime': 'ReactJSXRuntime',
	'@wordpress/api-fetch': [ 'wp', 'apiFetch' ],
	'@wordpress/commands': [ 'wp', 'commands' ],
	'@wordpress/components': [ 'wp', 'components' ],
	'@wordpress/compose': [ 'wp', 'compose' ],
	'@wordpress/core-data': [ 'wp', 'coreData' ],
	'@wordpress/data': [ 'wp', 'data' ],
	'@wordpress/data-controls': [ 'wp', 'dataControls' ],
	'@wordpress/date': [ 'wp', 'date' ],
	'@wordpress/dom': [ 'wp', 'dom' ],
	'@wordpress/element': [ 'wp', 'element' ],
	'@wordpress/html-entities': [ 'wp', 'htmlEntities' ],
	'@wordpress/i18n': [ 'wp', 'i18n' ],
	'@wordpress/keyboard-shortcuts': [ 'wp', 'keyboardShortcuts' ],
	'@wordpress/keycodes': [ 'wp', 'keycodes' ],
	'@wordpress/notices': [ 'wp', 'notices' ],
	'@wordpress/preferences': [ 'wp', 'preferences' ],
	'@wordpress/primitives': [ 'wp', 'primitives' ],
	'@wordpress/private-apis': [ 'wp', 'privateApis' ],
	'@wordpress/url': [ 'wp', 'url' ],
	'@wordpress/viewport': [ 'wp', 'viewport' ],
};

const SCRIPT_MODULE_EXTERNALS = {
	'@wordpress/a11y': 'import @wordpress/a11y',
	'@wordpress/boot': '@wordpress/boot',
	'@wordpress/interactivity': 'module @wordpress/interactivity',
	'@wordpress/interactivity-router': 'import @wordpress/interactivity-router',
	'@wordpress/route': '@wordpress/route',
};

const requestToExternalModule = ( request ) => SCRIPT_MODULE_EXTERNALS[ request ] || false;

const replaceModuleDependencyExtraction = ( plugins = [] ) => [
	...plugins.filter( ( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin' ),
	new DependencyExtractionWebpackPlugin( {
		requestToExternalModule,
		useDefaults: false,
	} ),
];

const classicScriptExternal = ( { request }, callback ) => {
	if ( CLASSIC_SCRIPT_EXTERNALS[ request ] ) {
		callback( null, CLASSIC_SCRIPT_EXTERNALS[ request ] );
		return;
	}

	callback();
};

const getExternalEntries = ( externals ) => {
	if ( Array.isArray( externals ) ) {
		return externals;
	}

	return externals ? [ externals ] : [];
};

const withClassicScriptExternals = ( externals ) => [ classicScriptExternal, ...getExternalEntries( externals ) ];

const processConfig = ( config ) => {
	const isModuleBuild = Boolean( config.output?.module );
	const splitChunks = isModuleBuild
		? config.optimization?.splitChunks
		: {
				...( config.optimization?.splitChunks || {} ),
				cacheGroups: {
					...( config.optimization?.splitChunks?.cacheGroups || {} ),
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
		  };

	return {
		...config,
		module: {
			...config.module,
			rules: [
				...( config.module?.rules || [] ),
				{
					test: /\.m?js$/,
					resolve: {
						fullySpecified: false,
					},
				},
				// Mark `@wordpress/views` side-effect-free so webpack can tree-shake
				// its barrel export. Views' `useViewConfig` pulls in `lock-unlock.mjs`,
				// which opts into `@wordpress/private-apis` under the name
				// `@wordpress/views` — a name WordPress core's allowlist rejects,
				// crashing the app at module-eval time. We only use `useView`, so
				// letting webpack drop the unused chain avoids the crash.
				{
					test: /node_modules[\\/]@wordpress[\\/]views[\\/]/,
					sideEffects: false,
				},
			],
		},
		externalsType: isModuleBuild ? 'window' : config.externalsType,
		externals: isModuleBuild ? withClassicScriptExternals( config.externals ) : config.externals,
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
			splitChunks,
		},
		plugins: isModuleBuild ? replaceModuleDependencyExtraction( config.plugins ) : config.plugins,
	};
};

module.exports = Array.isArray( defaultConfig ) ? defaultConfig.map( processConfig ) : processConfig( defaultConfig );
