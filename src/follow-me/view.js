import { store, getContext } from '@wordpress/interactivity';
import { getBlockStyles, getPopupStyles } from './button-style';
import './style.scss';

// Get dependencies from the window.wp object.
const { apiFetch } = window.wp;

/**
 * Normalizes profile data.
 *
 * @param {Object} profile Profile data.
 *
 * @return {Object} Normalized profile data.
 */
function normalizeProfile( profile ) {
	if ( ! profile ) {
		return getContext().profile.data;
	}

	const data = { ...getContext().profile.data, ...profile };
	data.avatar = data?.icon?.url;

	// Ensure webfinger always has the @ prefix.
	if ( data.webfinger && ! data.webfinger.startsWith( '@' ) ) {
		data.webfinger = '@' + data.webfinger;
	}

	return data;
}

const { state, actions, callbacks } = store( 'activitypub/follow-me', {
	actions: {
		/**
		 * Open the modal.
		 */
		openModal() {
			const context = getContext();
			context.isModalOpen = true;
			document.body.classList.add( 'modal-open' );
		},

		/**
		 * Close the modal.
		 */
		closeModal() {
			const context = getContext();
			context.isModalOpen = false;
			document.body.classList.remove( 'modal-open' );
		},

		toggleModal() {
			const context = getContext();
			context.isModalOpen ? actions.closeModal() : actions.openModal();
		},

		/**
		 * Copy the webfinger to clipboard.
		 */
		copyToClipboard() {
			const context = getContext();
			const webfinger = context.profile.data.webfinger;

			// Use the Clipboard API to copy text.
			navigator.clipboard.writeText( webfinger ).then(
				() => {
					// Update button text to show success.
					context.copyButtonText = state.i18n.copied;

					// Reset button text after 2 seconds.
					setTimeout( () => {
						context.copyButtonText = state.i18n.copy;
					}, 1000 );
				},
				( error ) => {
					// Log error if copying fails.
					console.error( 'Could not copy text: ', error );
				}
			);
		},

		/**
		 * Update the remote profile value.
		 *
		 * @param {Event} event Input event.
		 */
		updateRemoteProfile( event ) {
			const context = getContext();
			context.remoteProfile = event.target.value;
			// Reset error state when input changes.
			context.isError = false;
			context.errorMessage = '';
		},

		/**
		 * Handle keydown event for remote profile input.
		 *
		 * @param {Event} event Keydown event.
		 */
		handleKeyDown( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				actions.submitRemoteProfile();
			}
		},

		/**
		 * Submit the remote profile.
		 */
		submitRemoteProfile: function* () {
			const context = getContext();
			const { namespace } = state;
			const input = context.remoteProfile.trim();

			// Validate input.
			if ( ! input ) {
				context.isError = true;
				context.errorMessage = state.i18n.emptyProfileError;
				return;
			}

			if ( ! /^(https?:\/\/|@)/.test( input ) ) {
				context.isError = true;
				context.errorMessage = state.i18n.invalidProfileError;
				return;
			}

			// Set loading state.
			context.isLoading = true;
			context.isError = false;

			// Construct the API path.
			const path = `/${ namespace }/actors/${ context.userId }/remote-follow?resource=${ encodeURIComponent( input ) }`;

			try {
				// Make the API request.
				const response = yield apiFetch( { path } );

				// Set opening state.
				context.isLoading = false;

				// Open the remote follow URL in a new tab.
				window.open( response.url, '_blank' );

				// Close the modal after opening the URL.
				actions.closeModal();
			} catch ( error ) {
				// Handle error.
				console.error( 'Error submitting profile:', error );
				context.isLoading = false;
				context.isError = true;
				context.errorMessage = error.message || state.i18n.genericError;
			}
		},
	},
	callbacks: {
		/**
		 * Initialize the block.
		 *
		 * This function combines multiple initialization tasks.
		 */
		init: function* () {
			// First initialize button styles.
			callbacks.initButtonStyles();

			// Then fetch the profile data.
			yield callbacks.fetchProfile();
		},

		/**
		 * Fetch profile data.
		 *
		 * @return {Promise} Promise resolving with profile data.
		 */
		fetchProfile: function* () {
			const context = getContext();
			const { namespace } = state;

			try {
				const fetchOptions = {
					headers: { Accept: 'application/activity+json' },
					path: `/${ namespace }/actors/${ context.userId }`,
				};

				const profileData = yield apiFetch( fetchOptions );
				context.profile.data = normalizeProfile( profileData );
				context.profile.loading = false;
			} catch ( error ) {
				console.error( 'Error fetching profile:', error );
				context.profile.loading = false;
			}
		},

		/**
		 * Initialize button styles.
		 */
		initButtonStyles: () => {
			const { buttonStyle, backgroundColor, blockId } = getContext();

			// Add dynamic button styles to the document.
			if ( blockId && buttonStyle ) {
				const styleElement = document.createElement( 'style' );
				const selector = `#${ blockId }`;

				// Use getBlockStyles from button-style.js to get the CSS string.
				styleElement.textContent = getBlockStyles( selector, buttonStyle, backgroundColor );

				document.head.appendChild( styleElement );

				// Add popup styles.
				const popupStyleElement = document.createElement( 'style' );
				popupStyleElement.textContent = getPopupStyles( buttonStyle );

				document.head.appendChild( popupStyleElement );
			}
		},

		/**
		 * Close modal when pressing ESC key.
		 *
		 * @param {Event} event Keyboard event.
		 */
		documentKeydown: ( event ) => {
			const context = getContext();
			if ( context.isModalOpen && event.key === 'Escape' ) {
				actions.closeModal();
			}
		},

		/**
		 * Close modal when clicking outside.
		 *
		 * @param {Event} event Click event.
		 */
		documentClick: ( event ) => {
			const context = getContext();
			// Update selector to match the new modal structure.
			if (
				context.isModalOpen &&
				! event.target.closest( '.activitypub-modal__frame' ) &&
				! event.target.closest( '.activitypub-profile__follow' )
			) {
				actions.closeModal();
			}
		},
	},
} );
