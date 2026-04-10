/**
 * Feed Route Module
 *
 * Route lifecycle configuration for the feed route.
 * Controls when the inspector panel should be shown.
 */

/**
 * Internal dependencies
 */
import type { RouteConfig } from '../../router/types';

export const route: RouteConfig = {
	/**
	 * Always show the inspector panel.
	 * When a post is selected (postId in search params), the post detail view is shown.
	 * When no post is selected, the inspector sidebar with widgets is displayed.
	 */
	inspector: (): boolean => true,
};
