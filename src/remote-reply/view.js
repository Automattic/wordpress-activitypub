import { store, getContext } from '@wordpress/interactivity';
import { createModalStore } from '../shared/modal';
import './style.scss';

/** @var {object} wp WordPress global. */
const { apiFetch } = window.wp;

createModalStore( 'activitypub/remote-reply' );

/**
 * @typedef {Object} state
 * @property {String} state.namespace ActivityPub REST Namespace.
 * @property {Object} state.i18n Internationalization strings.
 * @property {String} state.i18n.copy "Copy" button text.
 * @property {String} state.i18n.copied "Copied" button text.
 * @property {String} state.i18n.emptyProfileError Error message for empty remote profile.
 * @property {String} state.i18n.invalidProfileError Error message for invalid remote profile.
 * @property {String} state.i18n.genericError Generic error message.
 */

/**
 * @typedef {Object} Context
 * @property {String} context.blockId The block ID.
 * @property {String} context.commentId The comment ID.
 * @property {String} context.commentURL The comment URL.
 * @property {String} context.copyButtonText The copy button text.
 * @property {String} context.errorMessage The error message.
 * @property {boolean} context.hasRemoteUser Whether a remote user is set.
 * @property {boolean} context.isError Whether there is an error.
 * @property {boolean} context.isLoading Whether the remote profile is being submitted.
 * @property {Object} context.modal The modal state.
 * @property {boolean} context.modal.isOpen Whether the modal is open.
 * @property {String} context.profileURL The remote profile URL.
 * @property {String} context.remoteProfile The remote profile.
 * @property {boolean} context.shouldSaveProfile Whether to save the profile.
 * @property {String} context.template The template for the remote reply URL.
 */

const { state, actions, callbacks } = store( 'activitypub/remote-reply', {
	actions: {
		/**
		 * Copy the comment URL to the clipboard.
		 */
		copyToClipboard() {
			const context = getContext();

			// Use the Clipboard API to copy text.
			navigator.clipboard.writeText( context.commentURL ).then(
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
		 * @param {string} event.key Key pressed.
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
		*submitRemoteProfile() {
			const context = getContext();
			const { namespace } = state;
			const input = context.remoteProfile.trim();

			// Validate input.
			if ( ! input ) {
				context.isError = true;
				context.errorMessage = state.i18n.emptyProfileError;
				return;
			}

			if ( ! callbacks.isHandle( input ) && ! callbacks.isUrl( input ) ) {
				context.isError = true;
				context.errorMessage = state.i18n.invalidProfileError;
				return;
			}

			// Set loading state.
			context.isLoading = true;
			context.isError = false;
			context.errorMessage = '';

			// Construct the API path.
			const path = `/${ namespace }/comments/${ context.commentId }/remote-reply?resource=${ encodeURIComponent(
				input
			) }`;

			try {
				// Make the API request.
				const response = yield apiFetch( { path } );

				// Save the remote user if the remember option is checked.
				if ( context.shouldSaveProfile ) {
					callbacks.setStore( { profileURL: input, template: response.template } );
				}

				// Set opening state.
				context.isLoading = false;

				// Open the remote reply URL in a new tab.
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

		/**
		 * Open the remote user's instance to reply.
		 */
		openRemoteInstance() {
			const context = getContext();
			const url = context.template.replace( '{uri}', context.commentURL );
			window.open( url, '_blank' );
		},
	},
	callbacks: {
		/**
		 * Initialize the component.
		 */
		init() {
			const context = getContext();
			const storedUser = callbacks.getStore();

			document.getElementById( context.blockId ).removeAttribute( 'hidden' );

			// Set the remote user data from localStorage if available.
			if ( storedUser.profileURL && storedUser.template ) {
				context.hasRemoteUser = true;
				context.profileURL = storedUser.profileURL;
				context.template = storedUser.template;
			}
		},

		storageKey: 'fediverse-remote-user',

		/**
		 * Retrieve the remote user data from localStorage.
		 *
		 * @returns {Object} Remote user data or empty object, if not set.
		 */
		getStore() {
			const data = localStorage.getItem( callbacks.storageKey );
			if ( ! data ) {
				return {};
			}
			return JSON.parse( data );
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
