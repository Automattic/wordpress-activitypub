import { store, getContext, withScope } from '@wordpress/interactivity';

/** @var {Object} window.wp WordPress global object */
const { apiFetch } = window.wp;

const { callbacks, state } = store( 'activitypub/reactions', {
	actions: {
		/**
		 * Fetches reactions for a post.
		 */
		fetchReactions: async () => {
			const context = getContext();
			const { namespace } = state;

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

				document.querySelectorAll( `.reaction-group` ).forEach( ( container ) => {
					const label = container.querySelector( '.reaction-label' );
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
			} );
		},

		/**
		 * Initializes the reactions component.
		 */
		initReactions: () => {
			// Calculate visible avatars after the component is initialized.
			setTimeout(
				withScope( () => {
					// Set up resize observer to recalculate on window resize.
					const resizeObserver = new ResizeObserver( withScope( callbacks.calculateVisibleAvatars ) );

					// Observe both reaction groups.
					document.querySelectorAll( '.reaction-group' ).forEach( ( group ) => {
						resizeObserver.observe( group );
					} );
				} ),
				100
			);
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
	},
} );
