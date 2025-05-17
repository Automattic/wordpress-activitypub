import { store, getContext } from '@wordpress/interactivity';

const { actions, state } = store( 'activitypub/reactions', {
	actions: {
		/**
		 * Fetches reactions for a post.
		 *
		 * @param {Object} context The context object.
		 */
		fetchReactions: async ( context ) => {
			const { postId } = getContext();

			if ( ! postId ) return;

			try {
				const response = await fetch( `/wp-json/activitypub/v1/posts/${ postId }/reactions` );
				if ( ! response.ok ) throw new Error( 'Failed to fetch reactions.' );

				const data = await response.json();
				// Update the state with the new reactions data.
				context.reactions = data;
			} catch ( error ) {
				console.error( 'Error fetching reactions:', error );
			}
		},

		/**
		 * Clears all timeouts for a specific post.
		 *
		 * @param {Object} context The context object.
		 */
		clearTimeouts: ( { postId } ) => {
			const context = getContext();

			if ( ! context.timeoutRefs ) {
				context.timeoutRefs = {};
			}

			if ( context.timeoutRefs[ postId ] ) {
				context.timeoutRefs[ postId ].forEach( ( timeout ) => clearTimeout( timeout ) );
				context.timeoutRefs[ postId ] = [];
			}
		},

		/**
		 * Starts a wave animation on avatar hover.
		 *
		 * @param {Object} context The context object.
		 */
		startWave: ( { postId, startIndex, isEntering, reactionType } ) => {
			const context = getContext();

			if ( ! context.reactions || ! context.reactions[ reactionType ] ) return;

			actions.clearTimeouts( { postId } );

			const delay = 100; // 100ms between each avatar.
			const totalAvatars = context.reactions[ reactionType ].items.length;

			// Initialize context objects if they don't exist.
			if ( ! context.activeIndices ) context.activeIndices = {};
			if ( ! context.rotationStates ) context.rotationStates = {};
			if ( ! context.timeoutRefs ) context.timeoutRefs = {};

			if ( ! context.activeIndices[ postId ] ) context.activeIndices[ postId ] = {};
			if ( ! context.rotationStates[ postId ] ) context.rotationStates[ postId ] = {};
			if ( ! context.timeoutRefs[ postId ] ) context.timeoutRefs[ postId ] = [];

			if ( ! context.activeIndices[ postId ][ reactionType ] ) {
				context.activeIndices[ postId ][ reactionType ] = new Set();
			}

			if ( ! context.rotationStates[ postId ][ reactionType ] ) {
				context.rotationStates[ postId ][ reactionType ] = new Map();
			}

			if ( isEntering ) {
				const updatedRotation = new Map( context.rotationStates[ postId ][ reactionType ] );
				updatedRotation.set( startIndex, 'clockwise' );
				context.rotationStates[ postId ][ reactionType ] = updatedRotation;
			}

			// Helper function to create wave in either direction.
			const createWave = ( direction ) => {
				const isRightward = direction === 'right';
				const start = isRightward ? startIndex : startIndex - 1;
				const end = isRightward ? totalAvatars - 1 : 0;
				const step = isRightward ? 1 : -1;

				for ( let i = start; isRightward ? i <= end : i >= end; i += step ) {
					const delayMultiplier = Math.abs( i - startIndex );
					const timeout = setTimeout( () => {
						const updatedActiveIndices = new Set( context.activeIndices[ postId ][ reactionType ] );

						if ( isEntering ) {
							updatedActiveIndices.add( i );
						} else {
							updatedActiveIndices.delete( i );
						}

						context.activeIndices[ postId ][ reactionType ] = updatedActiveIndices;

						if ( isEntering && i !== startIndex ) {
							const updatedRotation = new Map( context.rotationStates[ postId ][ reactionType ] );
							const neighborIndex = i - step;
							const neighborRotation = updatedRotation.get( neighborIndex );

							updatedRotation.set( i, neighborRotation === 'clockwise' ? 'counter' : 'clockwise' );

							context.rotationStates[ postId ][ reactionType ] = updatedRotation;
						}
					}, delayMultiplier * delay );

					context.timeoutRefs[ postId ].push( timeout );
				}
			};

			// Create waves in both directions.
			createWave( 'right' );
			createWave( 'left' );

			// Clear rotations when wave finishes retracting.
			if ( ! isEntering ) {
				const maxDelay = Math.max( ( totalAvatars - startIndex ) * delay, startIndex * delay );

				const timeout = setTimeout( () => {
					context.rotationStates[ postId ][ reactionType ] = new Map();
				}, maxDelay + delay );

				context.timeoutRefs[ postId ].push( timeout );
			}
		},

		/**
		 * Refreshes reactions data from the server.
		 *
		 * @param {Object} context The context object.
		 */
		refreshReactions: ( { postId } ) => {
			actions.fetchReactions( { postId } ).then( ( r ) => {} );
		},
	},
	selectors: {
		/**
		 * Gets the avatar class for a reaction avatar.
		 *
		 * @param {Object} context The context object.
		 * @return {string} The avatar class.
		 */
		getAvatarClass: ( { postId, index, reactionType } ) => {
			const context = getContext();

			if (
				! context.activeIndices ||
				! context.activeIndices[ postId ] ||
				! context.activeIndices[ postId ][ reactionType ] ||
				! context.rotationStates ||
				! context.rotationStates[ postId ] ||
				! context.rotationStates[ postId ][ reactionType ]
			) {
				return 'reaction-avatar';
			}

			const rotationClass = context.rotationStates[ postId ][ reactionType ].get( index );
			const classes = [
				'reaction-avatar',
				context.activeIndices[ postId ][ reactionType ].has( index ) ? 'wave-active' : '',
				rotationClass ? `rotate-${ rotationClass }` : '',
			]
				.filter( Boolean )
				.join( ' ' );

			return classes;
		},

		/**
		 * Gets the reactions for a post.
		 *
		 * @param {Object} context The context object.
		 * @return {Object} The reactions object.
		 */
		getReactions: ( { postId } ) => {
			const context = getContext();
			return context.reactions || {};
		},

		/**
		 * Checks if reactions exist for a specific type.
		 *
		 * @param {Object} context The context object.
		 * @param {string} reactionType The reaction type to check.
		 * @return {boolean} Whether reactions exist for the given type.
		 */
		hasReactions: ( { reactionType } ) => {
			const context = getContext();
			return !! (
				context.reactions &&
				context.reactions[ reactionType ] &&
				context.reactions[ reactionType ].items &&
				context.reactions[ reactionType ].items.length > 0
			);
		},
	},
	callbacks: {
		/**
		 * Calculates and sets the number of visible avatars based on container width.
		 */
		calculateVisibleAvatars: () => {
			const context = getContext();

			// Constants for calculations
			const AVATAR_WIDTH = 32; // Width of each avatar
			const AVATAR_OVERLAP = 10; // How much each avatar overlaps
			const EFFECTIVE_AVATAR_WIDTH = AVATAR_WIDTH - AVATAR_OVERLAP; // Width each additional avatar takes
			const BUTTON_GAP = 12; // Gap between avatars and button (0.75em)

			// Process each reaction group
			[ 'likes', 'reposts' ].forEach( ( reactionType ) => {
				if ( ! context.reactions?.[ reactionType ]?.items?.length ) {
					return;
				}

				const groupSelector = `.reaction-group[data-wp-context*="${ reactionType }"]`;
				const container = document.querySelector( groupSelector );
				if ( ! container ) {
					return;
				}

				const label = container.querySelector( '.reaction-label' );
				if ( ! label ) {
					return;
				}

				const containerWidth = container.offsetWidth;
				const labelWidth = label.offsetWidth || 0;
				const availableWidth = containerWidth - labelWidth - BUTTON_GAP;

				// Calculate how many avatars can fit
				// First avatar takes full width, rest take effective width
				const maxAvatars = Math.max(
					1,
					Math.floor( ( availableWidth - AVATAR_WIDTH ) / EFFECTIVE_AVATAR_WIDTH )
				);

				// Ensure we don't show more than we have
				const items = context.reactions[ reactionType ].items;
				const visibleCount = Math.min( maxAvatars, items.length );

				// Update the DOM to show only the calculated number of avatars
				const avatarsList = container.querySelector( '.reaction-avatars' );
				if ( avatarsList ) {
					const avatarItems = avatarsList.querySelectorAll( 'li' );
					avatarItems.forEach( ( item, index ) => {
						if ( index < visibleCount ) {
							item.style.display = '';
						} else {
							item.style.display = 'none';
						}
					} );
				}
			} );
		},

		/**
		 * Initializes the reactions component.
		 */
		initReactions: () => {
			// Log the context to help with debugging

			const { postId } = getContext();

			// Cleanup function to clear timeouts when component unmounts.
			return () => {
				actions.clearTimeouts( { postId } );
			};
		},

		/**
		 * Sets the default avatar URL when an image fails to load.
		 *
		 * @param {Object} event - The error event object.
		 */
		setDefaultAvatar: ( event ) => {
			const { target } = event;
			target.src = state.defaultAvatarUrl;
		},

		logReactions: () => {
			const context = getContext();
			console.log( 'Reactions context:', context );
		},
	},
} );
