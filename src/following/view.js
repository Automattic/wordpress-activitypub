import { store, getContext, getConfig } from '@wordpress/interactivity';

/**
 * @member {Object} window.wp WordPress global object
 * @member {Function} url.addQueryArgs Function to add query arguments to a URL.
 */
const { apiFetch, url } = window.wp;

/**
 * Validates a URL to ensure it uses a safe scheme (http/https).
 *
 * @param {string} urlString The URL to validate.
 * @return {string} The validated URL or empty string if invalid.
 */
function validateUrl( urlString ) {
	try {
		const parsed = new URL( urlString );
		return [ 'http:', 'https:' ].includes( parsed.protocol ) ? urlString : '';
	} catch {
		return '';
	}
}

/**
 * @typedef {Object} config
 * @property {string} defaultAvatarUrl Default avatar URL.
 * @property {string} namespace        ActivityPub REST Namespace.
 */

/**
 * @typedef {Object} context
 * @property {Array}   items     The list of actors.
 * @property {boolean} isLoading Whether the actors are currently being fetched.
 * @property {string}  order     The order in which to fetch actors (e.g., 'asc', 'desc').
 * @property {number}  page      The current page of actors.
 * @property {number}  pages     The total number of pages of actors.
 * @property {number}  perPage   The number of actors per page.
 * @property {number}  total     The total number of actors.
 * @property {string}  userId    The user ID for which to fetch actors.
 */

const { actions } = store( 'activitypub/following', {
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
		 * Fetch following for the current page.
		 *
		 * @return {Promise<void>} Promise that resolves when following are fetched.
		 */
		async fetchItems() {
			const context = getContext();
			const { userId, page, perPage, order } = context;

			// Set loading state.
			context.isLoading = true;

			try {
				// Build the API path and parameters.
				const { namespace } = getConfig();
				const path = url.addQueryArgs( `/${ namespace }/actors/${ userId }/following`, {
					context: 'full',
					per_page: perPage,
					order,
					page,
				} );

				// Use apiFetch to get the data.
				const { orderedItems, totalItems } = await apiFetch( { path } );

				// Update the context with the new items.
				context.items = orderedItems.map( ( actor ) => ( {
					handle: '@' + actor.preferredUsername,
					icon: actor.icon,
					name: actor.name || actor.preferredUsername,
					url: validateUrl( actor.url || actor.id ),
				} ) );

				context.total = totalItems;
				context.pages = Math.ceil( totalItems / perPage );
			} catch ( error ) {
				// eslint-disable-next-line no-console -- Log error for debugging.
				console.error( 'Error fetching following:', error );
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
		previousPage( event ) {
			event.preventDefault();
			const context = getContext();

			if ( context.page > 1 ) {
				context.page--;
				actions.fetchItems();
			}
		},

		/**
		 * Navigate to the next page.
		 *
		 * @param {Event} event The click event.
		 */
		nextPage( event ) {
			event.preventDefault();
			const context = getContext();

			if ( context.page < context.pages ) {
				context.page++;
				actions.fetchItems();
			}
		},
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
