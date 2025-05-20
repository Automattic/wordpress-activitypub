import { store, getContext } from '@wordpress/interactivity';
import { getBlockStyles, getPopupStyles } from './button-style';
import './style.scss';

/** @var {object} wp WordPress global. */
const { apiFetch } = window.wp;

const { state, actions, utils } = store( 'activitypub/follow-me', {
	actions: {
		/**
		 * Open the modal.
		 */
		openModal() {
			const context = getContext();
			context.isModalOpen = true;
			document.body.classList.add( 'modal-open' );

			// Set up the focus trap after modal is open.
			setTimeout( () => {
				// Use the blockId to find the specific modal frame for this block.
				const blockWrapper = document.getElementById( context.blockId );
				if ( blockWrapper ) {
					const modalFrame = blockWrapper.querySelector( '.activitypub-modal__frame' );
					if ( modalFrame ) {
						utils.trapFocus( modalFrame );
					}
				}
			}, 50 );
		},

		/**
		 * Close the modal.
		 */
		closeModal() {
			const context = getContext();
			context.isModalOpen = false;
			context.isError = false;
			document.body.classList.remove( 'modal-open' );

			// Return focus to the button that opened the modal.
			const blockWrapper = document.getElementById( context.blockId );
			if ( blockWrapper ) {
				const openButton = blockWrapper.querySelector( '.wp-block-button__link' );
				if ( openButton ) {
					openButton.focus();
				}
			}
		},

		toggleModal() {
			const { isModalOpen } = getContext();

			isModalOpen ? actions.closeModal() : actions.openModal();
		},

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

			if ( ! utils.isHandle( input ) ) {
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
		 * Close modal when pressing ESC key.
		 *
		 * @param {Event} event Keyboard event.
		 */
		documentKeydown( event ) {
			const { isModalOpen } = getContext();

			if ( isModalOpen && event.key === 'Escape' ) {
				actions.closeModal();
			}
		},

		/**
		 * Close modal when clicking outside.
		 *
		 * @param {Event} event Click event.
		 */
		documentClick( event ) {
			const { blockId, isModalOpen } = getContext();
			if ( ! isModalOpen ) {
				return;
			}

			// Get the block wrapper element.
			const blockWrapper = document.getElementById( blockId );
			if ( ! blockWrapper ) {
				return;
			}

			// If the click was on the button or its children, we should not close the modal.
			const toggleButton = blockWrapper.querySelector(
				'.wp-element-button[data-wp-on--click="actions.toggleModal"]'
			);
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
	utils: {
		/**
		 * Best guess whether a string is a valid ActivityPub handle.
		 *
		 * @param {string} string - String to check.
		 * @returns {boolean} True if string is a valid handle, false otherwise.
		 */
		isHandle( string ) {
			// Check if the string starts with '@' and contains a valid URL.
			const parts = string.replace( /^@/, '' ).split( '@' );

			return parts.length === 2 && utils.isUrl( `https://${ parts[ 1 ] }` );
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

	/**
	 * Traps focus within the specified element.
	 *
	 * @param {Element} element The element to trap focus within.
	 */
	trapFocus( element ) {
		const focusableElements = element.querySelectorAll(
			'a[href]:not([disabled]), button:not([disabled]), textarea:not([disabled]), input[type="text"]:not([disabled]):not([readonly]), input[type="radio"]:not([disabled]), input[type="checkbox"]:not([disabled]), select:not([disabled])'
		);
		const firstFocusableElement = focusableElements[ 0 ];
		const lastFocusableElement = focusableElements[ focusableElements.length - 1 ];

		// If the first focusable element is the close button, set initial focus to the next element instead.
		if (
			firstFocusableElement &&
			firstFocusableElement.classList.contains( 'activitypub-modal__close' ) &&
			focusableElements.length > 1
		) {
			// Set initial focus to the second element, but keep firstFocusableElement as is for tab trapping.
			focusableElements[ 1 ].focus();
		} else {
			// Otherwise focus the first element as usual.
			firstFocusableElement.focus();
		}

		element.addEventListener( 'keydown', function ( event ) {
			if ( event.key !== 'Tab' && event.keyCode !== 9 /* KEYCODE_TAB */ ) {
				return;
			}

			if ( event.shiftKey ) {
				/* shift + tab */
				if ( document.activeElement === firstFocusableElement ) {
					lastFocusableElement.focus();
					event.preventDefault();
				}
			} /* tab */ else {
				if ( document.activeElement === lastFocusableElement ) {
					firstFocusableElement.focus();
					event.preventDefault();
				}
			}
		} );
	},
} );
