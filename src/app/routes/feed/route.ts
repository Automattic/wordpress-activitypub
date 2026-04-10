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
	 * Always show the inspector panel. When a post is selected it shows
	 * post details; otherwise it renders the persistent sidebar widgets.
	 */
	inspector: (): boolean => true,
};
