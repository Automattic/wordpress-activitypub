/**
 * Router Component
 *
 * TanStack Router setup with custom history for ?p= query parameter format.
 * Based on @wordpress/boot package patterns for forward compatibility.
 */

/**
 * External dependencies
 */
import { parseHref } from '@tanstack/history';
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
import type { AnyRoute } from '@tanstack/react-router';

/**
 * WordPress dependencies
 */
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { Route, RouteConfig, RouteLoaderContext } from './types';
import Panel from '../components/panel';

// Re-export hooks for use in route components
export { useNavigate, useSearch, useLoaderData, useLocation };

// Re-export Outlet for layout component
export { Outlet };

// Create Link component for navigation
export const Link = createLink( { defaultPreload: 'intent' } );

/**
 * Creates a TanStack route from a Route definition.
 *
 * @param route       Route configuration
 * @param parentRoute Parent route.
 * @return Tanstack Route.
 */
async function createRouteFromDefinition( route: Route, parentRoute: AnyRoute ) {
	let routeConfig: RouteConfig = {};

	if ( route.routeLoader ) {
		const module = await route.routeLoader();
		routeConfig = module.route || {};
	}

	// Create route without component initially
	let tanstackRoute = createRoute( {
		getParentRoute: () => parentRoute,
		path: route.path,
		beforeLoad: routeConfig.beforeLoad
			? ( opts: any ) =>
					routeConfig.beforeLoad!( {
						params: opts.params || {},
						search: opts.search || {},
					} )
			: undefined,
		loader: async ( opts: any ) => {
			const context: RouteLoaderContext = {
				params: opts.params || {},
				search: opts.deps || {},
			};

			const [ loaderData, inspectorVisible ] = await Promise.all( [
				routeConfig.loader ? routeConfig.loader( context ) : Promise.resolve( undefined ),
				routeConfig.inspector ? routeConfig.inspector( context ) : Promise.resolve( true ),
			] );

			return {
				...( loaderData as any ),
				inspector: inspectorVisible,
			};
		},
		loaderDeps: ( opts: any ) => opts.search,
	} );

	// Chain .lazy() to preload content module on intent
	tanstackRoute = tanstackRoute.lazy( async () => {
		const module = route.contentLoader ? await route.contentLoader() : {};

		const Stage = module.stage;
		const Inspector = module.inspector;

		return createLazyRoute( route.path )( {
			component: function RouteComponent() {
				const { inspector: showInspector } = useLoaderData( { from: route.path } ) ?? {};

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

	return tanstackRoute;
}

/**
 * Creates a route tree from route definitions.
 *
 * @param routes        Routes definition.
 * @param rootComponent Root component to use for the router.
 * @return Router tree.
 */
async function createRouteTree( routes: Route[], rootComponent: React.ComponentType ) {
	const rootRoute = createRootRoute( {
		component: rootComponent as any,
		context: () => ( {} ),
	} );

	// Create routes from definitions
	const dynamicRoutes = await Promise.all( routes.map( ( route ) => createRouteFromDefinition( route, rootRoute ) ) );

	return rootRoute.addChildren( dynamicRoutes );
}

/**
 * Create custom history that parses ?p= query parameter
 */
function createPathHistory() {
	return createBrowserHistory( {
		parseLocation: () => {
			const url = new URL( window.location.href );
			const path = url.searchParams.get( 'p' ) || '/';
			const pathHref = `${ path }${ url.hash }`;
			return parseHref( pathHref, window.history.state );
		},
		createHref: ( href: string ) => {
			const searchParams = new URLSearchParams( window.location.search );
			searchParams.set( 'p', href );
			return `${ window.location.pathname }?${ searchParams }`;
		},
	} );
}

interface RouterProps {
	routes: Route[];
	rootComponent: React.ComponentType;
}

export default function Router( { routes, rootComponent }: RouterProps ) {
	const [ router, setRouter ] = useState< any >( null );

	useEffect( () => {
		let cancelled = false;

		async function initializeRouter() {
			const history = createPathHistory();
			const routeTree = await createRouteTree( routes, rootComponent );

			if ( ! cancelled ) {
				const newRouter = createRouter( {
					history,
					routeTree,
					defaultPreload: 'intent',
					defaultNotFoundComponent: () => (
						<div style={ { padding: '20px', textAlign: 'center' } }>
							{ __( 'Page not found', 'activitypub' ) }
						</div>
					),
				} );
				setRouter( newRouter );
			}
		}

		initializeRouter();

		return () => {
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
