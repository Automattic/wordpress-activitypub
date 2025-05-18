import { store, getContext, withScope } from '@wordpress/interactivity';

/** @var {Object} window.wp WordPress global object */
const { apiFetch } = window.wp;

/**
 * @var {Object} state
 * @var {Object} state.reactions Reactions data.
 * @var {String} state.defaultAvatarUrl Default avatar URL.
 */
const { actions, callbacks, state } = store( 'activitypub/reactions', {
	actions: {
		/**
		 * Fetches reactions for a post.
		 */
		async fetchReactions() {
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

		/**
		 * Opens the modal with the specified reaction type.
		 *
		 * @param {Object} event The click event.
		 */
		openModal( event ) {
			const { modal, postId, blockId } = getContext();
			const button = event.target.closest( '[data-reaction-type]' );
			const reactionType = button.getAttribute( 'data-reaction-type' );

			// Set modal properties.
			modal.isOpen = true;
			modal.reactionType = reactionType;
			modal.items = state.reactions[ postId ][ reactionType ].items;

			// Position the compact modal relative to the button.
			setTimeout( () => {
				const blockWrapper = document.getElementById( blockId );
				if ( ! blockWrapper ) {
					return;
				}

				const modalOverlay = blockWrapper.querySelector( '.activitypub-modal__overlay' );
				if ( ! modalOverlay ) {
					return;
				}

				// Reset any previously set positioning.
				modalOverlay.style.top = '';
				modalOverlay.style.left = '';
				modalOverlay.style.right = '';
				modalOverlay.style.bottom = '';

				// Get button position relative to viewport.
				const buttonRect = button.getBoundingClientRect();

				// Get viewport dimensions.
				const viewportWidth = window.innerWidth;

				// Get the block's position to calculate relative positioning.
				const blockRect = blockWrapper.getBoundingClientRect();

				// Calculate position relative to the block (our positioning context).
				const relativeTop = buttonRect.bottom - blockRect.top;
				const relativeLeft = buttonRect.left - blockRect.left;

				// Calculate available space.
				const spaceRight = viewportWidth - buttonRect.right;

				// Default position (below button, relative to the block).
				let position = {
					top: `${ relativeTop + 8 }px`,
					left: `${ relativeLeft - 2 }px`, // -2px to account for the button border.
				};

				// If not enough space to the right, align with the right edge.
				if ( spaceRight < 250 ) {
					position.left = 'auto';
					position.right = `${ blockRect.right - buttonRect.right }px`;
				}

				// Apply the position.
				Object.assign( modalOverlay.style, position );
			}, 0 );
		},

		/**
		 * Closes the reactions modal.
		 */
		closeModal() {
			const { blockId, modal } = getContext();

			modal.isOpen = false;

			// Return focus to the button that opened the modal.
			const blockWrapper = document.getElementById( blockId );
			if ( blockWrapper ) {
				const openButton = blockWrapper.querySelector( '.reaction-label' );
				if ( openButton ) {
					openButton.focus();
				}
			}
		},
	},
	callbacks: {
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

			// Process each reaction group
			[ 'likes', 'reposts' ].forEach( ( reactionType ) => {
				if ( ! state.reactions?.[ postId ][ reactionType ]?.items?.length ) {
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
					const items = state.reactions[ postId ][ reactionType ].items;
					const visibleCount = Math.min( maxAvatars, items.length );

					// Update the DOM to show only the calculated number of avatars
					const avatarsList = container.querySelector( '.reaction-avatars' );
					if ( avatarsList ) {
						const avatarItems = avatarsList.querySelectorAll( 'li' );
						avatarItems.forEach( ( item, index ) => {
							if ( index < visibleCount ) {
								item.setAttribute( 'hidden', 'hidden' );
							} else {
								item.removeAttribute( 'hidden' );
							}
						} );
					}
				} );
			} );
		},

		/**
		 * Initializes the Reactions component.
		 */
		initReactions() {
			// Calculate visible avatars after the component is initialized.
			setTimeout(
				withScope( () => {
					const { blockId } = getContext();

					// Set up resize observer to recalculate on window resize.
					const resizeObserver = new ResizeObserver( withScope( callbacks.calculateVisibleAvatars ) );

					// Observe both reaction groups.
					const blockWrapper = document.getElementById( blockId );
					if ( blockWrapper ) {
						blockWrapper.querySelectorAll( '.reaction-group' ).forEach( ( group ) => {
							resizeObserver.observe( group );
						} );
					}
				} ),
				100
			);
		},

		/**
		 * Sets the default avatar when the avatar image fails to load.
		 *
		 * @param {Object} event The error event.
		 */
		setDefaultAvatar( event ) {
			event.target.src = state.defaultAvatarUrl;
		},

		/**
		 * Close modal when pressing the ESC key.
		 *
		 * @param {String} key Keyboard event key.
		 */
		documentKeydown( { key } ) {
			const { modal } = getContext();

			if ( modal.isOpen && key === 'Escape' ) {
				actions.closeModal();
			}
		},

		/**
		 * Close modal when clicking outside.
		 *
		 * @param {Event} event Click event.
		 */
		documentClick( event ) {
			const { blockId, modal } = getContext();
			if ( ! modal.isOpen ) {
				return;
			}

			// Get the block wrapper element.
			const blockWrapper = document.getElementById( blockId );
			if ( ! blockWrapper ) {
				return;
			}

			// If the click was on the button or its children, we should not close the modal.
			const toggleButton = blockWrapper.querySelector( '.reaction-label[data-wp-on--click="actions.openModal"]' );
			if ( toggleButton && ( toggleButton === event.target || toggleButton.contains( event.target ) ) ) {
				return;
			}

			// Check if the click was inside the modal frame.
			const modalFrame = blockWrapper.querySelector( '.activitypub-modal__frame' );
			if ( ! modalFrame || modalFrame.contains( event.target ) ) {
				return;
			}

			actions.closeModal();
		},
	},
} );
