import { store, getContext, getConfig } from '@wordpress/interactivity';
import { createModalStore } from '../shared/modal';
import './style.scss';

/** @var {object} wp WordPress global. */
const { apiFetch } = window.wp;

createModalStore( 'activitypub/remote-reply' );

/**
 * @typedef {Object} config
 * @property {String} namespace ActivityPub REST Namespace.
 * @property {Object} i18n Internationalization strings.
 * @property {String} i18n.copy "Copy" button text.
 * @property {String} i18n.copied "Copied" button text.
 * @property {String} i18n.emptyProfileError Error message for empty remote profile.
 * @property {String} i18n.invalidProfileError Error message for invalid remote profile.
 * @property {String} i18n.genericError Generic error message.
 */

/**
 * @typedef {Object} context
 * @property {String} blockId The block ID.
 * @property {String} commentId The comment ID.
 * @property {String} commentURL The comment URL.
 * @property {String} copyButtonText The copy button text.
 * @property {String} errorMessage The error message.
 * @property {boolean} hasRemoteUser Whether a remote user is set.
 * @property {boolean} isError Whether there is an error.
 * @property {boolean} isLoading Whether the remote profile is being submitted.
 * @property {Object} modal The modal state.
 * @property {boolean} modal.isOpen Whether the modal is open.
 * @property {String} profileURL The remote profile URL.
 * @property {String} remoteProfile The remote profile.
 * @property {boolean} shouldSaveProfile Whether to save the profile.
 * @property {String} template The template for the remote reply URL.
 */

const { actions, callbacks } = store( 'activitypub/remote-reply', {
	state: {
		/**
		 * Get the remote profile URL.
		 *
		 * @returns {String} The remote profile URL.
		 */
		get remoteProfileUrl() {
			const { commentURL, template } = getContext();

			return template.replace( '{uri}', encodeURIComponent( commentURL ) );
		},
	},
	actions: {
		/**
		 * Handle the opening of the modal.
		 *
		 * @param {Event} event The event that triggered the modal opening/closing.
		 * @param {String} event.key The key pressed, if any.
		 */
		onReplyLinkKeydown( event ) {
			// Handle Enter key to open the modal.
			if ( event.key === 'Enter' || event.key === ' ' ) {
				event.preventDefault();
				actions.toggleModal( event );
			}
		},

		/**
		 * Copy the comment URL to the clipboard.
		 */
		copyToClipboard() {
			const context = getContext();
			const { i18n } = getConfig();

			// Use the Clipboard API to copy text.
			navigator.clipboard.writeText( context.commentURL ).then(
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
		 * @param {String} event.target.value The remote profile value.
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
		 * @param {String} event.key Key pressed.
		 */
		onInputKeydown( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();

				return actions.submitRemoteProfile();
			}
		},

		/**
		 * Submit the remote profile.
		 */
		*submitRemoteProfile() {
			const context = getContext();
			const { namespace, i18n } = getConfig();
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
			const path = `/${ namespace }/comments/${ context.commentId }/remote-reply?resource=${ encodeURIComponent(
				profileURL
			) }`;

			try {
				// Make the API request.
				const { template, url } = yield apiFetch( { path } );

				// Set opening state.
				context.isLoading = false;

				// Open the remote reply URL in a new tab.
				window.open( url, '_blank' );

				// Close the modal after opening the URL.
				actions.closeModal();

				// Save the remote user if the remember option is checked.
				if ( context.shouldSaveProfile ) {
					callbacks.setStore( { profileURL, template } );
					Object.assign( context, { hasRemoteUser: true, profileURL, template } );
				}
			} catch ( error ) {
				// Handle error.
				console.error( 'Error submitting profile:', error );
				context.isLoading = false;
				context.isError = true;
				context.errorMessage = error.message || i18n.genericError;
			}
		},

		/**
		 * Toggle the remember profile checkbox.
		 */
		toggleRememberProfile() {
			const context = getContext();
			context.shouldSaveProfile = ! context.shouldSaveProfile;
		},

		/**
		 * Delete the saved remote user profile.
		 */
		deleteRemoteUser() {
			const context = getContext();

			callbacks.deleteStore();
			context.hasRemoteUser = false;
			context.profileURL = '';
			context.template = '';
		},
	},
	callbacks: {
		/**
		 * The storage key for the remote user data.
		 */
		storageKey: 'fediverse-remote-user',

		/**
		 * Initialize the component.
		 */
		init() {
			const context = getContext();
			const { profileURL, template } = callbacks.getStore();

			// Set the remote user data from localStorage if available.
			if ( profileURL && template ) {
				Object.assign( context, { hasRemoteUser: true, profileURL, template } );
			}
		},

		/**
		 * Retrieve the remote user data from localStorage.
		 *
		 * @returns {Object} Remote user data or empty object, if not set.
		 */
		getStore() {
			const data = localStorage.getItem( callbacks.storageKey );

			return data ? JSON.parse( data ) : {};
		},

		/**
		 * Store remote user data in localStorage.
		 *
		 * @param {Object} data - Remote user data to store.
		 */
		setStore( data ) {
			localStorage.setItem( callbacks.storageKey, JSON.stringify( data ) );
		},

		/**
		 * Remove remote user data from localStorage.
		 */
		deleteStore() {
			localStorage.removeItem( callbacks.storageKey );
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
	},
} );
