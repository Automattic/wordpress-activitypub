/**
 * Router Component
 *
 * TanStack Router setup with custom history for ?p= query parameter format.
 * Based on @wordpress/boot package patterns for forward compatibility.
 */

/**
 * External dependencies
 */
import type { ComponentType } from 'react';
import { parseHref } from '@tanstack/history';
import type { RouterHistory, HistoryLocation } from '@tanstack/history';
import {
	createBrowserHistory,
	createLazyRoute,
	createLink,
	createRootRoute,
	createRoute,
	createRouter,
	Outlet,
	RouterProvider,
	useLoaderData,
	useLocation,
	useNavigate,
	useSearch,
} from '@tanstack/react-router';
import type { AnyRoute, AnyRouter, RouteComponent } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Route, RouteConfig, RouteLoaderContext, RouteModule, RouteSurfaces } from './types';
import Panel from '../components/panel';

// Re-export hooks for use in route components
export { useNavigate, useSearch, useLoaderData, useLocation };

// Re-export Outlet for layout component
export { Outlet };

// Create Link component for navigation
export const Link = createLink( { defaultPreload: 'intent' } );

/**
 * Not found component displayed when no route matches.
 */
function NotFoundComponent() {
	return <div style={ { padding: '20px', textAlign: 'center' } }>{ __( 'Page not found', 'activitypub' ) }</div>;
}

/**
 * Creates a TanStack route from a Route definition.
 *
 * Note: TanStack Router requires strictNullChecks which is not enabled globally.
 * We use 'any' types for the internal TanStack callbacks to work around this.
 *
 * @param route       Route configuration
 * @param parentRoute Parent route.
 * @return Tanstack Route.
 */
async function createRouteFromDefinition( route: Route, parentRoute: AnyRoute ): Promise< AnyRoute > {
	let routeConfig: RouteConfig = {};

	if ( route.routeLoader ) {
		const module: RouteModule = await route.routeLoader();
		routeConfig = module.route || {};
	}

	// Create base route configuration
	// Using 'any' for TanStack callbacks due to strictNullChecks requirement
	const baseRoute = createRoute( {
		getParentRoute: (): AnyRoute => parentRoute,
		path: route.path,
		beforeLoad: routeConfig.beforeLoad
			? ( ctx: any ) =>
					routeConfig.beforeLoad!( {
						params: ctx.params || {},
						search: ctx.search || {},
					} )
			: undefined,
		loader: async ( ctx: any ): Promise< { inspector: boolean } > => {
			const context: RouteLoaderContext = {
				params: ctx.params || {},
				search: ctx.deps || {},
			};

			const [ , inspectorVisible ] = await Promise.all( [
				routeConfig.loader ? routeConfig.loader( context ) : Promise.resolve( undefined ),
				routeConfig.inspector ? routeConfig.inspector( context ) : Promise.resolve( true ),
			] );

			return {
				inspector: inspectorVisible as boolean,
			};
		},
		loaderDeps: ( opts: any ) => opts.search,
	} );

	// Chain .lazy() to preload content module on intent
	const lazyRoute = baseRoute.lazy( async () => {
		const module: RouteSurfaces = route.contentLoader ? await route.contentLoader() : {};

		const Stage: ComponentType = module.stage;
		const Inspector: ComponentType = module.inspector;

		return createLazyRoute( route.path )( {
			component: function RouteComponent() {
				const loaderData = useLoaderData( { from: route.path } ) as { inspector?: boolean } | undefined;
				const showInspector: boolean = loaderData?.inspector ?? false;

				return (
					<>
						{ Stage && (
							<div className="stage-region">
								<Panel>
									<Stage />
								</Panel>
							</div>
						) }
						{ Inspector && showInspector && (
							<div className="inspector-region">
								<Panel>
									<Inspector />
								</Panel>
							</div>
						) }
					</>
				);
			},
		} );
	} );

	return lazyRoute as AnyRoute;
}

/**
 * Creates a route tree from route definitions.
 *
 * @param routes        Routes definition.
 * @param rootComponent Root component to use for the router.
 * @return Router tree.
 */
async function createRouteTree( routes: Route[], rootComponent: RouteComponent ): Promise< AnyRoute > {
	const rootRoute = createRootRoute( {
		component: rootComponent,
		context: (): Record< string, unknown > => ( {} ),
	} );

	// Create routes from definitions
	const dynamicRoutes: AnyRoute[] = await Promise.all(
		routes.map( ( route: Route ) => createRouteFromDefinition( route, rootRoute ) )
	);

	return rootRoute.addChildren( dynamicRoutes );
}

/**
 * Create custom history that parses ?p= query parameter
 *
 * @return Custom browser history instance.
 */
function createPathHistory(): RouterHistory {
	return createBrowserHistory( {
		parseLocation: (): HistoryLocation => {
			const url = new URL( window.location.href );
			const path: string = url.searchParams.get( 'p' ) || '/';
			const pathHref = `${ path }${ url.hash }`;
			return parseHref( pathHref, window.history.state );
		},
		createHref: ( href: string ): string => {
			const searchParams = new URLSearchParams( window.location.search );
			searchParams.set( 'p', href );
			return `${ window.location.pathname }?${ searchParams }`;
		},
	} );
}

interface RouterProps {
	routes: Route[];
	rootComponent: RouteComponent;
}

export default function Router( { routes, rootComponent }: RouterProps ) {
	const [ router, setRouter ] = useState< AnyRouter | null >( null );

	useEffect( () => {
		let cancelled: boolean = false;

		async function initializeRouter(): Promise< void > {
			const history: RouterHistory = createPathHistory();
			const routeTree: AnyRoute = await createRouteTree( routes, rootComponent );

			if ( ! cancelled ) {
				// TanStack Router requires strictNullChecks at the type level.
				// Cast to `never` to bypass the check since we can't enable it globally.
				const newRouter = createRouter( {
					history,
					routeTree,
					defaultPreload: 'intent',
					defaultNotFoundComponent: NotFoundComponent,
				} as never );
				setRouter( newRouter );
			}
		}

		void initializeRouter();

		return (): void => {
			cancelled = true;
		};
	}, [ routes, rootComponent ] );

	if ( ! router ) {
		return (
			<div style={ { padding: '20px', textAlign: 'center' } }>
				<Spinner />
			</div>
		);
	}

	return <RouterProvider router={ router } />;
}
