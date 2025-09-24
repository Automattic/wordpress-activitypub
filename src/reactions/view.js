import { getContext, getElement, store, withScope, getConfig } from '@wordpress/interactivity';
import { createModalStore } from '../shared/modal';

/** @var {Object} window.wp WordPress global object */
const { apiFetch } = window.wp;

createModalStore( 'activitypub/reactions' );

/**
 * @typedef {Object} state
 * @property {Object} reactions Reactions data, keyed by post ID.
 */

/**
 * @typedef {Object} context
 * @property {String} blockId The block ID.
 * @property {Object} modal The modal state.
 * @property {boolean} modal.isCompact Whether the modal is compact.
 * @property {boolean} modal.isOpen Whether the modal is open.
 * @property {Object} modal.items The items to display in the modal.
 * @property {String} postId The post ID.
 * @property {Object} reactions Reactions data, keyed by reaction type.
 */

const { callbacks, state } = store( 'activitypub/reactions', {
	actions: {
		/**
		 * Fetches reactions for a post.
		 */
		async fetchReactions() {
			const context = getContext();
			const { namespace } = getConfig();

			if ( ! context.postId ) return;

			try {
				// Update the state with the new Reactions data.
				context.reactions = await apiFetch( {
					path: `/${ namespace }/posts/${ context.postId }/reactions`,
				} );
			} catch ( error ) {
				console.error( 'Error fetching reactions:', error );
			}
		},
	},
	callbacks: {
		/**
		 * Initializes the Reactions component.
		 */
		initReactions() {
			// Set up resize observer to recalculate on window resize.
			const resizeObserver = new ResizeObserver( withScope( callbacks.calculateVisibleAvatars ) );
			getElement()
				.ref.querySelectorAll( '.reaction-group' )
				.forEach( ( group ) => {
					resizeObserver.observe( group );
				} );

			// Return a cleanup function to disconnect the observer when the block is unmounted.
			return () => {
				resizeObserver.disconnect();
			};
		},

		/**
		 * Calculates and sets the number of visible avatars based on container width.
		 */
		calculateVisibleAvatars() {
			const { postId } = getContext();

			// Constants for calculations
			const AVATAR_WIDTH = 32; // Width of each avatar
			const AVATAR_OVERLAP = 10; // How much each avatar overlaps
			const EFFECTIVE_AVATAR_WIDTH = AVATAR_WIDTH - AVATAR_OVERLAP; // Width each additional avatar takes
			const BUTTON_GAP = 12; // Gap between avatars and button (0.75em)

			// Get all reaction types from the state.
			const reactionTypes =
				state.reactions && state.reactions[ postId ] ? Object.keys( state.reactions[ postId ] ) : [];

			// Process each reaction group.
			reactionTypes.forEach( ( reactionType ) => {
				if ( ! state.reactions?.[ postId ][ reactionType ]?.items?.length ) {
					return;
				}

				getElement()
					.ref.querySelectorAll( `.reaction-group[data-reaction-type="${ reactionType }"]` )
					.forEach( ( container ) => {
						const label = container.querySelector( '.reaction-label' );
						const labelWidth = label.offsetWidth || 0;
						const availableWidth = container.offsetWidth - labelWidth - BUTTON_GAP;

						// Calculate how many avatars can fit.
						// The first avatar takes full width, the rest take effective width.
						let maxAvatars = 1; // Start with 1 for the first avatar.

						// If we have space for more than one avatar.
						if ( availableWidth > AVATAR_WIDTH ) {
							// Calculate how many additional avatars can fit in the remaining space.
							maxAvatars += Math.floor( ( availableWidth - AVATAR_WIDTH ) / EFFECTIVE_AVATAR_WIDTH );
						}

						// Ensure we don't show more than we have.
						const items = state.reactions[ postId ][ reactionType ].items;
						const visibleCount = Math.min( maxAvatars, items.length );

						// Update the DOM to show only the calculated number of avatars.
						const avatarsList = container.querySelector( '.reaction-avatars' );
						if ( avatarsList ) {
							const avatarItems = avatarsList.querySelectorAll( 'li' );
							avatarItems.forEach( ( item, index ) => {
								if ( index < visibleCount ) {
									item.removeAttribute( 'hidden' );
								} else {
									item.setAttribute( 'hidden', 'hidden' );
								}
							} );
						}
					} );
			} );
		},

		/**
		 * Sets the default avatar when the avatar image fails to load.
		 *
		 * @param {Object} event The error event.
		 */
		setDefaultAvatar( event ) {
			event.target.src = getConfig().defaultAvatarUrl;
		},

		/**
		 * Opens the modal with the specified reaction type.
		 */
		onModalOpen() {
			const context = getContext();
			const reactionType = getElement().ref.dataset.reactionType;

			// Set modal properties.
			context.modal.items = state.reactions[ context.postId ][ reactionType ].items;
		},
	},
} );
