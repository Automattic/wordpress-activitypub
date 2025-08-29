import { store, getContext, getElement, getConfig } from '@wordpress/interactivity';
import { getBlockStyles, getPopupStyles } from './button-style';
import { createModalStore } from '../shared/modal';

/** @var {object} wp WordPress global. */
const { apiFetch } = window.wp;

createModalStore( 'activitypub/follow-me' );

/**
 * @typedef {Object} config
 * @property {String} namespace ActivityPub REST Namespace.
 * @property {Object} i18n Internationalization strings.
 * @property {String} i18n.copy "Copy" button text.
 * @property {String} i18n.copied "Copied" button text.
 * @property {String} i18n.emptyProfileError Error message for empty remote profile.
 * @property {String} i18n.genericError Generic error message.
 * @property {String} i18n.invalidProfileError Error message for invalid remote profile.
 */

/**
 * @typedef {Object} context
 * @property {String} backgroundColor The background color for the button.
 * @property {String} blockId The block ID.
 * @property {String} buttonStyle The button style.
 * @property {String} copyButtonText The copy button text.
 * @property {String} errorMessage The error message.
 * @property {boolean} isError Whether the remote profile input has an error.
 * @property {boolean} isLoading Whether the remote profile is being submitted.
 * @property {Object} modal The modal state.
 * @property {boolean} modal.isOpen Whether the modal is open.
 * @property {String} remoteProfile The remote profile.
 * @property {String} template The template for the remote reply URL.
 * @property {String} userId The user ID.
 * @property {String} webfinger The webfinger of the user.
 */

const { actions, callbacks } = store( 'activitypub/follow-me', {
	actions: {
		/**
		 * Copy the webfinger to clipboard.
		 */
		copyToClipboard() {
			const context = getContext();
			const { i18n } = getConfig();

			// Use the Clipboard API to copy text.
			navigator.clipboard.writeText( context.webfinger ).then(
				() => {
					// Update button text to show success.
					context.copyButtonText = i18n.copied;

					// Reset button text after 1 second.
					setTimeout( () => {
						context.copyButtonText = i18n.copy;
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
		 * Handle the opening of the modal.
		 *
		 * @param {Event} event The event that triggered the modal opening/closing.
		 * @param {String} event.key The key pressed, if any.
		 */
		onKeydown( event ) {
			if ( getElement().ref.tagName === 'A' && ( event.key === 'Enter' || event.key === ' ' ) ) {
				event.preventDefault();
				actions.toggleModal( event );
			}
		},

		/**
		 * Handle keydown event for remote profile input.
		 *
		 * @param {Event} event Keydown event.
		 * @param {String} event.key The key pressed.
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
			const { namespace, i18n } = getConfig();
			const input = context.remoteProfile.trim();

			// Validate input.
			if ( ! input ) {
				context.isError = true;
				context.errorMessage = i18n.emptyProfileError;
				return;
			}

			if ( ! callbacks.isHandle( input ) ) {
				context.isError = true;
				context.errorMessage = i18n.invalidProfileError;
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
				actions.closeModal( new Event( 'click' ) );
			} catch ( error ) {
				// Handle error.
				console.error( 'Error submitting profile:', error );
				context.isLoading = false;
				context.isError = true;
				context.errorMessage = error.message || i18n.genericError;
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
