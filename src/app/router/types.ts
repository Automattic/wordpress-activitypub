/**
 * Router type definitions
 *
 * Based on @wordpress/boot package patterns for forward compatibility
 * with Gutenberg's routing infrastructure.
 */

/**
 * External dependencies
 */
import type { ComponentType } from 'react';

/**
 * Route loader context containing params and search.
 */
export interface RouteLoaderContext {
	params: Record< string, string >;
	search: Record< string, unknown >;
}

/**
 * Route lifecycle configuration exported from route_module.
 * The module should export a named `route` object with these optional functions.
 */
export interface RouteConfig {
	/**
	 * Pre-navigation hook for authentication, validation, or redirects.
	 * Called before the route is loaded.
	 */
	beforeLoad?: ( context: RouteLoaderContext ) => void | Promise< void >;

	/**
	 * Data preloading function.
	 * Called when the route is being loaded.
	 */
	loader?: ( context: RouteLoaderContext ) => Promise< unknown >;

	/**
	 * Function that returns the document title for the route.
	 */
	title?: ( context: RouteLoaderContext ) => string | Promise< string >;

	/**
	 * Function that determines whether to show the inspector panel.
	 * When not defined, defaults to true (always show inspector if component exists).
	 * When it returns false, the inspector is hidden even if an inspector component is exported.
	 */
	inspector?: ( context: RouteLoaderContext ) => boolean | Promise< boolean >;
}

/**
 * Route surfaces exported from content_module.
 * Stage is the main content, inspector is the sidebar panel.
 */
export interface RouteSurfaces {
	stage?: ComponentType;
	inspector?: ComponentType;
}

/**
 * Module containing route lifecycle configuration.
 */
export interface RouteModule {
	route?: RouteConfig;
}

/**
 * Route configuration interface.
 * Routes specify script module IDs for content and route modules.
 */
export interface Route {
	/**
	 * Route path (e.g., "/" or "/settings")
	 */
	path: string;

	/**
	 * Script module ID for route surfaces.
	 */
	content_module?: string;

	/**
	 * Script module ID for route lifecycle functions.
	 */
	route_module?: string;
}
