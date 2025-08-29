import { store, getContext, getConfig } from '@wordpress/interactivity';

/**
 * @var {Object} window.wp WordPress global object
 * @var {Function} url.addQueryArgs Function to add query arguments to a URL.
 */
const { apiFetch, url } = window.wp;

/**
 * @typedef {Object} config
 * @property {String} defaultAvatarUrl Default avatar URL.
 * @property {String} namespace ActivityPub REST Namespace.
 */

/**
 * @typedef {Object} context
 * @property {Array} followers The list of followers.
 * @property {boolean} isLoading Whether the followers are currently being fetched.
 * @property {String} order The order in which to fetch followers (e.g., 'asc', 'desc').
 * @property {Number} page The current page of followers.
 * @property {Number} pages The total number of pages of followers.
 * @property {Number} per_page The number of followers per page.
 * @property {Number} total The total number of followers.
 * @property {String} userId The user ID for which to fetch followers.
 */

const { actions } = store( 'activitypub/followers', {
	/**
	 * @typedef {Object} state
	 * @property {Function} paginationText Get the pagination text.
	 * @property {Function} disablePreviousLink Whether the previous link should be disabled.
	 * @property {Function} disableNextLink Whether the next link should be disabled.
	 */
	state: {
		/**
		 * Get the pagination text.
		 *
		 * @returns {string}
		 */
		get paginationText() {
			const { page, pages } = getContext();
			return `${ page } / ${ pages }`;
		},

		/**
		 * Check if the previous link should be disabled.
		 *
		 * @returns {boolean}
		 */
		get disablePreviousLink() {
			const { page } = getContext();
			return page <= 1;
		},

		/**
		 * Check if the next link should be disabled.
		 *
		 * @returns {boolean}
		 */
		get disableNextLink() {
			const { page, pages } = getContext();
			return page >= pages;
		},
	},
	actions: {
		/**
		 * Fetch followers for the current page.
		 *
		 * @return {Promise<void>} Promise that resolves when followers are fetched.
		 */
		async fetchFollowers() {
			const context = getContext();
			const { userId, page, per_page, order } = context;

			// Set loading state.
			context.isLoading = true;

			try {
				// Build the API path and parameters
				const { namespace } = getConfig();
				const path = url.addQueryArgs( `/${ namespace }/actors/${ userId }/followers`, {
					context: 'full',
					per_page,
					order,
					page,
				} );

				// Use apiFetch to get the Followers data.
				const { orderedItems, totalItems } = await apiFetch( { path } );

				// Update the context with the new followers.
				context.followers = orderedItems.map( ( follower ) => ( {
					handle: '@' + follower.preferredUsername,
					icon: follower.icon,
					name: follower.name || follower.preferredUsername,
					url: follower.url || follower.id,
				} ) );

				context.total = totalItems;
				context.pages = Math.ceil( totalItems / per_page );
			} catch ( error ) {
				console.error( 'Error fetching followers:', error );
			} finally {
				// Clear loading state.
				context.isLoading = false;
			}
		},

		/**
		 * Navigate to the previous page.
		 *
		 * @param {Event} event - The click event.
		 */
		previousPage( event ) {
			event.preventDefault();
			const context = getContext();

			if ( context.page > 1 ) {
				context.page--;
				actions.fetchFollowers().catch( ( error ) => {
					console.error( 'Error fetching followers:', error );
				} );
			}
		},

		/**
		 * Navigate to the next page.
		 *
		 * @param {Event} event - The click event.
		 */
		nextPage( event ) {
			event.preventDefault();
			const context = getContext();

			if ( context.page < context.pages ) {
				context.page++;
				actions.fetchFollowers().catch( ( error ) => {
					console.error( 'Error fetching followers:', error );
				} );
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
