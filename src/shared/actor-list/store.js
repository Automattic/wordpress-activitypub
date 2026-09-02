import { store, getContext, getConfig } from '@wordpress/interactivity';
import { withSyncEvent } from '../with-sync-event';
import { isSafeUrl } from '../safe-url';

/**
 * Creates and registers an Interactivity API store for actor lists.
 *
 * @param {string} storeName The name of the store (e.g., 'activitypub/followers').
 * @return {Object} The store actions object.
 */
export function createActorListStore( storeName ) {
	const { actions } = store( storeName, {
		/**
		 * @typedef {Object} state
		 * @property {Function} paginationText      Get the pagination text.
		 * @property {Function} disablePreviousLink Whether the previous link should be disabled.
		 * @property {Function} disableNextLink     Whether the next link should be disabled.
		 */
		state: {
			/**
			 * Get the pagination text.
			 *
			 * @return {string} The pagination text showing current page and total pages.
			 */
			get paginationText() {
				const { page, pages } = getContext();
				return `${ page } / ${ pages }`;
			},

			/**
			 * Check if the previous link should be disabled.
			 *
			 * @return {boolean} True if the previous link should be disabled.
			 */
			get disablePreviousLink() {
				const { page } = getContext();
				return page <= 1;
			},

			/**
			 * Check if the next link should be disabled.
			 *
			 * @return {boolean} True if the next link should be disabled.
			 */
			get disableNextLink() {
				const { page, pages } = getContext();
				return page >= pages;
			},
		},
		actions: {
			/**
			 * Fetch actors for the current page.
			 *
			 * @return {Promise<void>} Promise that resolves when actors are fetched.
			 */
			async fetchItems() {
				const context = getContext();
				const { userId, page, perPage, order, endpoint } = context;
				const { apiFetch, url } = window.wp;

				// Set loading state.
				context.isLoading = true;

				try {
					// Build the API path and parameters.
					const { namespace } = getConfig();
					const path = url.addQueryArgs( `/${ namespace }/actors/${ userId }/${ endpoint }`, {
						context: 'full',
						per_page: perPage,
						order,
						page,
					} );

					// Use apiFetch to get the data.
					const { orderedItems, totalItems } = await apiFetch( { path } );

					// Update the context with the new items.
					context.items = orderedItems.map( ( actor ) => {
						/*
						 * These come from a remote server and are bound straight into an href,
						 * so a `javascript:` value would run on click. The server-rendered first
						 * page passes the same values through `esc_url()` with an http/https
						 * allow list; this is the same gate for the paginated refresh.
						 */
						const actorUrl = actor.url || actor.id;

						return {
							handle: '@' + actor.webfinger,
							icon: actor.icon,
							name: actor.name || actor.preferredUsername,
							url: isSafeUrl( actorUrl ) ? actorUrl : '',
						};
					} );

					context.total = totalItems;
					context.pages = Math.ceil( totalItems / perPage );
				} catch ( error ) {
					// eslint-disable-next-line no-console -- Log error for debugging.
					console.error( `Error fetching ${ endpoint }:`, error );
				} finally {
					// Clear loading state.
					context.isLoading = false;
				}
			},

			/**
			 * Navigate to the previous page.
			 *
			 * @param {Event} event The click event.
			 */
			previousPage: withSyncEvent( ( event ) => {
				event.preventDefault();
				const context = getContext();

				if ( context.page > 1 ) {
					context.page--;
					actions.fetchItems();
				}
			} ),

			/**
			 * Navigate to the next page.
			 *
			 * @param {Event} event The click event.
			 */
			nextPage: withSyncEvent( ( event ) => {
				event.preventDefault();
				const context = getContext();

				if ( context.page < context.pages ) {
					context.page++;
					actions.fetchItems();
				}
			} ),
		},
		callbacks: {
			/**
			 * Sets the default avatar when the avatar image fails to load.
			 *
			 * @param {Object} event The error event.
			 */
			setDefaultAvatar( event ) {
				event.target.src = getConfig().defaultAvatarUrl;
			},
		},
	} );

	return { actions };
}
