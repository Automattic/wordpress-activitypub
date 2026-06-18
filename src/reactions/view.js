import { getContext, getElement, store, withScope, getConfig } from '@wordpress/interactivity';
import './view-style.scss';
import { createModalStore } from '../shared/modal';

createModalStore( 'activitypub/reactions' );

/**
 * @typedef {Object} state
 * @property {Object} reactions Reactions data, keyed by post ID.
 */

/**
 * @typedef {Object} context
 * @property {string}  blockId         The block ID.
 * @property {Object}  modal           The modal state.
 * @property {boolean} modal.isCompact Whether the modal is compact.
 * @property {boolean} modal.isOpen    Whether the modal is open.
 * @property {Object}  modal.items     The items to display in the modal.
 * @property {string}  modal.title     The modal title (used in full-size mode).
 * @property {string}  modal.intent    The intent type (like, announce).
 * @property {string}  postId          The post ID.
 * @property {Object}  reactions       Reactions data, keyed by reaction type.
 */

/**
 * Intent label map keys for modal titles — resolved from i18n config.
 */
const INTENT_LABEL_KEYS = {
	like: 'intentLabelLike',
	announce: 'intentLabelAnnounce',
};

/**
 * The storage key for the remote user data (shared with remote-reply block).
 */
const STORAGE_KEY = 'fediverse-remote-user';

const { actions, callbacks, state } = store( 'activitypub/reactions', {
	actions: {
		/**
		 * Fetches reactions for a post.
		 */
		async fetchReactions() {
			const context = getContext();

			if ( ! context.postId ) {
				return;
			}

			const { namespace } = getConfig();
			const { apiFetch } = window.wp;

			try {
				// Update the state with the new Reactions data.
				context.reactions = await apiFetch( {
					path: `/${ namespace }/posts/${ context.postId }/reactions`,
				} );
			} catch ( error ) {
				// eslint-disable-next-line no-console -- Log error for debugging.
				console.error( 'Error fetching reactions:', error );
			}
		},

		/**
		 * Open the intent modal for a given action (like, announce).
		 * Sets the modal to full-size mode and pre-fills the profile input.
		 */
		openIntentModal() {
			const context = getContext();
			const intent = getElement().ref.dataset.intent;

			// Switch to full-size mode and set intent data.
			context.modal.isCompact = false;
			context.modal.intent = intent;
			const { i18n } = getConfig();
			context.modal.title = i18n[ INTENT_LABEL_KEYS[ intent ] ] || intent;

			// Pre-fill saved profile if available.
			const { profileURL } = callbacks.getStore();
			if ( profileURL ) {
				context.remoteProfile = profileURL;
			}

			// Open via shared modal (will trap focus since isCompact is false).
			actions.openModal();
		},

		/**
		 * Update the remote profile input value.
		 *
		 * @param {Event} event Input event.
		 */
		updateIntentProfile( event ) {
			const context = getContext();
			context.remoteProfile = event.target.value;
			context.isError = false;
			context.errorMessage = '';
		},

		/**
		 * Handle keydown on the intent profile input.
		 *
		 * @param {Event} event Keydown event.
		 */
		onIntentKeydown( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				actions.submitIntent();
			}
		},

		/**
		 * Submit the intent to the remote server.
		 */
		*submitIntent() {
			const context = getContext();
			const { namespace, i18n } = getConfig();
			const { apiFetch } = window.wp;
			const profileURL = context.remoteProfile.trim();

			// Validate input.
			if ( ! profileURL ) {
				context.isError = true;
				context.errorMessage = i18n.emptyProfileError;
				return;
			}

			if ( ! callbacks.isHandle( profileURL ) && ! callbacks.isUrl( profileURL ) ) {
				context.isError = true;
				context.errorMessage = i18n.invalidProfileError;
				return;
			}

			// Set loading state.
			context.isLoading = true;
			context.isError = false;
			context.errorMessage = '';

			// Construct the API path.
			const path = `/${ namespace }/posts/${ context.postId }/remote-intent?resource=${ encodeURIComponent(
				profileURL
			) }&intent=${ encodeURIComponent( context.modal.intent ) }`;

			try {
				const response = yield apiFetch( { path } );

				context.isLoading = false;

				// Open the remote intent URL in a new tab.
				window.open( response.url, '_blank', 'noopener,noreferrer' );

				// Close via shared modal.
				actions.closeModal();

				// Save the remote user if the remember option is checked.
				if ( context.shouldSaveProfile ) {
					callbacks.setStore( { profileURL } );
				}
			} catch ( error ) {
				// eslint-disable-next-line no-console -- Log error for debugging.
				console.error( 'Error submitting intent:', error );
				context.isLoading = false;
				context.isError = true;
				context.errorMessage = error.message || i18n.genericError;
			}
		},

		/**
		 * Copy the post URL to the clipboard.
		 */
		copyPostUrl() {
			const context = getContext();
			const { i18n } = getConfig();

			navigator.clipboard.writeText( context.postUrl ).then(
				() => {
					context.copyButtonText = i18n.copied;
					setTimeout( () => {
						context.copyButtonText = i18n.copy;
					}, 1000 );
				},
				( error ) => {
					// eslint-disable-next-line no-console -- Log error for debugging.
					console.error( 'Could not copy text: ', error );
				}
			);
		},

		/**
		 * Toggle the remember profile checkbox.
		 */
		toggleRememberProfile() {
			const context = getContext();
			context.shouldSaveProfile = ! context.shouldSaveProfile;
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
						const actionButton = container.querySelector( '.reaction-action-button' );
						const actionButtonWidth = actionButton ? actionButton.offsetWidth + BUTTON_GAP : 0;
						const availableWidth = container.offsetWidth - labelWidth - actionButtonWidth - BUTTON_GAP;

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
		 * Called when the shared modal opens. Sets up content based on trigger.
		 */
		onModalOpen() {
			const context = getContext();

			if ( context.modal.isCompact ) {
				// Compact mode: show reactors list.
				const reactionType = getElement().ref.dataset.reactionType;
				context.modal.items = state.reactions[ context.postId ][ reactionType ].items;
			}
		},

		/**
		 * Called when the shared modal closes. Resets to compact mode.
		 */
		onModalClose() {
			const context = getContext();

			// Reset to compact mode for next use.
			context.modal.isCompact = true;
			context.modal.intent = '';
			context.modal.title = '';
			context.isError = false;
			context.errorMessage = '';
		},

		/**
		 * Retrieve the remote user data from localStorage.
		 *
		 * @return {Object} Remote user data or empty object.
		 */
		getStore() {
			const data = localStorage.getItem( STORAGE_KEY );

			if ( ! data ) {
				return {};
			}

			try {
				return JSON.parse( data );
			} catch ( _ ) {
				localStorage.removeItem( STORAGE_KEY );
				return {};
			}
		},

		/**
		 * Store remote user data in localStorage.
		 *
		 * @param {Object} data Remote user data to store.
		 */
		setStore( data ) {
			localStorage.setItem( STORAGE_KEY, JSON.stringify( data ) );
		},

		/**
		 * Best guess whether a string is a valid ActivityPub handle.
		 *
		 * @param {string} string String to check.
		 * @return {boolean} True if string is a valid handle.
		 */
		isHandle( string ) {
			const parts = string.replace( /^@/, '' ).split( '@' );

			return parts.length === 2 && callbacks.isUrl( `https://${ parts[ 1 ] }` );
		},

		/**
		 * Checks if a string is a valid URL.
		 *
		 * @param {string} string String to check.
		 * @return {boolean} True if string is a valid URL.
		 */
		isUrl( string ) {
			try {
				new URL( string );
				return true;
			} catch ( _ ) {
				return false;
			}
		},
	},
} );
