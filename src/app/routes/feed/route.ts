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
import { resolveSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { loadView } from '@wordpress/views';

/**
 * Internal dependencies
 */
import type { RouteConfig, RouteLoaderContext } from '../../router/types';
import { DEFAULT_VIEW, defaultLayouts, viewToQuery } from './utils';
import { STORE_NAME } from '../../store';
import type { AppSelectors } from '../../store';

export const route: RouteConfig = {
	/**
	 * Document title for the feed route.
	 *
	 * @return Route title.
	 */
	title: (): string => __( 'Social Web', 'activitypub' ),

	/**
	 * Warm the feed before the screen paints, so the first render is a list and not a spinner.
	 *
	 * Resolves the same view `useView` will resolve in the stage and asks core-data for the same
	 * records the stage will ask for; the stage then reads them from the store.
	 *
	 * @param context        Route loader context.
	 * @param context.search URL search parameters (`page`, `search`).
	 */
	loader: async ( { search }: RouteLoaderContext ): Promise< void > => {
		const { page, search: term } = search as { page?: number; search?: string };
		const userId: number | null = await ( resolveSelect( STORE_NAME ) as AppSelectors ).getActiveActorId();
		const view = await loadView( {
			kind: 'postType',
			name: 'ap_post',
			slug: 'feed',
			defaultView: DEFAULT_VIEW,
			defaultLayouts,
			queryParams: { page, search: term },
		} );

		await resolveSelect( coreStore ).getEntityRecords( 'postType', 'ap_post', viewToQuery( view, userId ) );
	},

	/**
	 * Show inspector only when a post is selected (`postIds` in search params)
	 * @param context        Route loader context.
	 * @param context.search URL search parameters.
	 */
	inspector: ( { search }: RouteLoaderContext ): boolean =>
		Array.isArray( search.postIds ) && search.postIds.length > 0,
};
