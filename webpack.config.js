/**
 * Custom webpack configuration extending WordPress Scripts
 *
 * This configuration:
 * - Places dynamic JS chunks in their source directory structure (src/foo/ → build/foo/).
 * - Handles classic app vendor chunks separately.
 * - Classifies script-module imports as import-map modules, wp-admin globals, or bundled packages.
 */

const path = require( 'path' );

const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const DependencyExtractionWebpackPlugin = require( '@wordpress/dependency-extraction-webpack-plugin' );

const PROJECT_SOURCE_DIR = path.resolve( __dirname, 'src' );

// The admin app is loaded as script modules, but many WordPress packages it uses
// are still provided to wp-admin as classic `wp-*` scripts. Keep those imports
// externalized to the existing `window.wp.*` globals so module chunks do not
// bundle duplicate stores or private API instances.
const WORDPRESS_SCRIPT_GLOBALS = {
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
	'@wordpress/hooks': [ 'wp', 'hooks' ],
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
	'@wordpress/boot': 'module @wordpress/boot',
	'@wordpress/interactivity': 'module @wordpress/interactivity',
	'@wordpress/interactivity-router': 'import @wordpress/interactivity-router',
	'@wordpress/route': 'module @wordpress/route',
};

const BUNDLED_WORDPRESS_SCRIPT_MODULES = new Set( [
	'@wordpress/dataviews',
	'@wordpress/dataviews/wp',
	'@wordpress/icons',
	'@wordpress/views',
] );

const hasOwn = ( object, key ) => Object.prototype.hasOwnProperty.call( object, key );

const requestToExternalModule = ( request ) => {
	if ( hasOwn( SCRIPT_MODULE_EXTERNALS, request ) ) {
		return SCRIPT_MODULE_EXTERNALS[ request ];
	}

	if ( hasOwn( WORDPRESS_SCRIPT_GLOBALS, request ) || BUNDLED_WORDPRESS_SCRIPT_MODULES.has( request ) ) {
		return false;
	}

	if ( request.startsWith( '@wordpress/' ) ) {
		throw new Error(
			`Unclassified WordPress module import "${ request }" in script-module build. Add it to SCRIPT_MODULE_EXTERNALS, WORDPRESS_SCRIPT_GLOBALS, or BUNDLED_WORDPRESS_SCRIPT_MODULES.`
		);
	}

	return false;
};

const replaceModuleDependencyExtraction = ( plugins = [] ) => [
	...plugins.filter( ( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin' ),
	new DependencyExtractionWebpackPlugin( {
		requestToExternalModule,
		useDefaults: false,
	} ),
];

const wordpressScriptGlobalExternal = ( { request }, callback ) => {
	if ( WORDPRESS_SCRIPT_GLOBALS[ request ] ) {
		callback( null, WORDPRESS_SCRIPT_GLOBALS[ request ] );
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

const withWordPressScriptGlobalExternals = ( externals ) => [
	wordpressScriptGlobalExternal,
	...getExternalEntries( externals ),
];

const getProjectSourceDirectory = ( modulePath = '' ) => {
	if ( ! modulePath ) {
		return null;
	}

	const relativePath = path.relative( PROJECT_SOURCE_DIR, modulePath );

	if ( ! relativePath || relativePath.startsWith( '..' ) || path.isAbsolute( relativePath ) ) {
		return null;
	}

	return relativePath.split( path.sep )[ 0 ];
};

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
	const moduleRules = [
		...( config.module?.rules || [] ),
		{
			test: /\.m?js$/,
			resolve: {
				fullySpecified: false,
			},
		},
		...( isModuleBuild
			? [
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
			  ]
			: [] ),
	];

	return {
		...config,
		module: {
			...config.module,
			rules: moduleRules,
		},
		externalsType: isModuleBuild ? 'window' : config.externalsType,
		externals: isModuleBuild ? withWordPressScriptGlobalExternals( config.externals ) : config.externals,
		output: {
			...config.output,
			// Place JS chunks in their source directory with content hash for cache busting
			chunkFilename: ( pathData ) => {
				const chunk = pathData.chunk;
				const chunkGraph = pathData.webpack?.chunkGraph;

				if ( chunk && chunkGraph ) {
					for ( const module of chunkGraph.getChunkModules( chunk ) ) {
						const modulePath = module.resource || module.context || '';
						const sourceDirectory = getProjectSourceDirectory( modulePath );

						if ( sourceDirectory ) {
							return `${ sourceDirectory }/[name].[contenthash:8].js`;
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
