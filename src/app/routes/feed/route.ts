/**
 * Feed Route Module
 *
 * Route lifecycle configuration for the feed route.
 * Controls when the inspector panel should be shown.
 */

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import type { RouteConfig, RouteLoaderContext } from '../../router/types';

export const route: RouteConfig = {
	/**
	 * Document title for the feed route.
	 *
	 * @return Route title.
	 */
	title: (): string => __( 'Social Web', 'activitypub' ),

	/**
	 * Show inspector only when a post is selected (postId in search params)
	 * @param context        Route loader context.
	 * @param context.search URL search parameters.
	 */
	inspector: ( { search }: RouteLoaderContext ): boolean => !! search.postId,
};
