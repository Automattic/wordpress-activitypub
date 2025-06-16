import { getContext, store, getElement } from '@wordpress/interactivity';

/**
 * @typedef {Object} context
 * @property {String} blockId - The ID of the block.
 * @property {Object} modal - The modal state.
 * @property {boolean} modal.isOpen - Whether the modal is open.
 * @property {boolean} modal.isCompact - Whether the modal is compact.
 */

/**
 * Set up a modal store with actions and callbacks.
 *
 * The Interactivity API merges all stores that share the same namespace,
 * so these actions and callbacks are added directly to the importing block’s existing store.
 *
 * @param {string} namespace - The interactivity namespace for the block.
 */
export function createModalStore( namespace ) {
	const { actions, callbacks } = store( namespace, {
		actions: {
			/**
			 * Open the modal.
			 *
			 * @param {Event} event Click event.
			 */
			openModal( event ) {
				const context = getContext();

				// Set modal properties
				context.modal.isOpen = true;

				if ( context.modal.isCompact ) {
					// Position the compact modal relative to the button.
					setTimeout( callbacks.positionModal, 0 );
				} else {
					// Set up the focus trap after modal is open.
					setTimeout( () => {
						// Use the blockId to find the specific modal frame for this block
						const blockWrapper = document.getElementById( context.blockId );
						if ( blockWrapper ) {
							const modalFrame = blockWrapper.querySelector( '.activitypub-modal__frame' );
							if ( modalFrame ) {
								callbacks.trapFocus( modalFrame );
							}
						}
					}, 50 );
				}

				// Call the onOpen callback if provided.
				if ( typeof callbacks.onModalOpen === 'function' ) {
					callbacks.onModalOpen( event );
				}
			},

			/**
			 * Close the modal.
			 *
			 * @param {Event} event Click event.
			 */
			closeModal( event ) {
				const context = getContext();

				// Reset modal state
				context.modal.isOpen = false;

				// Return focus to the button that opened the modal.
				const button = getElement();

				if ( button.ref.dataset[ 'wpOn-Click' ] === 'actions.toggleModal' ) {
					button.ref.focus();
				} else {
					const blockWrapper = document.getElementById( context.blockId );
					if ( blockWrapper ) {
						const openButton = blockWrapper.querySelector(
							'[data-wp-on--click="actions.toggleModal"], [data-wp-on-async--click="actions.toggleModal"]'
						);
						if ( openButton ) {
							openButton.focus();
						}
					}
				}

				// Call the onClose callback if provided.
				if ( typeof callbacks.onModalClose === 'function' ) {
					callbacks.onModalClose( event );
				}
			},

			/**
			 * Toggle the modal.
			 *
			 * @param {Event} event Click event.
			 */
			toggleModal( event ) {
				const { modal } = getContext();

				modal.isOpen ? actions.closeModal( event ) : actions.openModal( event );
			},
		},

		callbacks: {
			/**
			 * Abort controller for keydown and click event listeners.
			 *
			 * @type {AbortController | null} Abort controller.
			 */
			_abortController: null,

			/**
			 * Handles modal effects like body class and event listeners.
			 * This is called via data-wp-watch in the modal HTML.
			 */
			handleModalEffects() {
				const { modal } = getContext();

				// Update body class.
				if ( modal.isOpen && ! modal.isCompact ) {
					document.body.classList.add( 'modal-open' );
				} else {
					document.body.classList.remove( 'modal-open' );
				}

				// Remove all existing listeners.
				if ( callbacks._abortController ) {
					callbacks._abortController.abort();
					callbacks._abortController = null;
				}

				// Add new listeners if modal is open.
				if ( modal.isOpen ) {
					callbacks._abortController = new AbortController();
					const { signal } = callbacks._abortController;

					document.addEventListener( 'keydown', callbacks.documentKeydown, { signal } );
					document.addEventListener( 'click', callbacks.documentClick, { signal } );
				}

				return undefined;
			},

			/**
			 * Handles keydown events on the document.
			 *
			 * @param {Event} event Keydown event.
			 * @param {String} event.key The key that was pressed.
			 */
			documentKeydown( event ) {
				const { modal } = getContext();

				if ( modal.isOpen && event.key === 'Escape' ) {
					actions.closeModal();
				}
			},

			/**
			 * Handles click events on the document.
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

			/**
			 * Positions the modal relative to the button that opened it.
			 */
			positionModal() {
				const { blockId } = getContext();

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
				const buttonRect = getElement().ref.getBoundingClientRect();

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
					left: `${ relativeLeft - 2 }px`, // -2 px to account for the button border.
				};

				// If not enough space to the right, align with the right edge.
				if ( spaceRight < 250 ) {
					position.left = 'auto';
					position.right = `${ blockRect.right - buttonRect.right }px`;
				}

				// Apply the position.
				Object.assign( modalOverlay.style, position );
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
		},
	} );
}
