/**
 * Feed Route Module
 *
 * Route lifecycle configuration for the feed route.
 * Controls when the inspector panel should be shown.
 */

/**
 * Internal dependencies
 */
import type { RouteConfig, RouteLoaderContext } from '../../router/types';

export const route: RouteConfig = {
	/**
	 * Show inspector only when a post is selected (postId in search params)
	 * @param context        Route loader context.
	 * @param context.search URL search parameters.
	 */
	inspector: ( { search }: RouteLoaderContext ): boolean => !! search.postId,
};
