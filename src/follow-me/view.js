import { store, getContext } from '@wordpress/interactivity';
import { getBlockStyles, getPopupStyles } from './button-style';
import { createModalStore } from '../shared/modal';

/** @var {object} wp WordPress global. */
const { apiFetch } = window.wp;

createModalStore( 'activitypub/follow-me' );

const { actions, callbacks, state } = store( 'activitypub/follow-me', {
	actions: {
		/**
		 * Copy the webfinger to clipboard.
		 */
		copyToClipboard() {
			const context = getContext();

			// Use the Clipboard API to copy text.
			navigator.clipboard.writeText( context.webfinger ).then(
				() => {
					// Update button text to show success.
					context.copyButtonText = state.i18n.copied;

					// Reset button text after 1 second.
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

			if ( ! callbacks.isHandle( input ) ) {
				context.isError = true;
				context.errorMessage = state.i18n.invalidProfileError;
				return;
			}

			// Set loading state.
			context.isLoading = true;
			context.isError = false;

			// Construct the API path.
			const path = `/${ namespace }/actors/${ context.userId }/remote-follow?resource=${ encodeURIComponent(
				input
			) }`;

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
		 * Best guess whether a string is a valid ActivityPub handle.
		 *
		 * @param {string} string - String to check.
		 * @returns {boolean} True if string is a valid handle, false otherwise.
		 */
		isHandle( string ) {
			// Check if the string starts with '@' and contains a valid URL.
			const parts = string.replace( /^@/, '' ).split( '@' );

			return parts.length === 2 && callbacks.isUrl( `https://${ parts[ 1 ] }` );
		},

		/**
		 * Checks if a string is a valid URL.
		 *
		 * @param {string} string - String to check.
		 * @returns {boolean} True if string is a valid URL, false otherwise.
		 */
		isUrl( string ) {
			try {
				new URL( string );
				return true;
			} catch ( _ ) {
				return false;
			}
		},

		/**
		 * Callback when modal is closed.
		 */
		onModalClose() {
			const context = getContext();

			context.isError = false;
		},
	},
} );
