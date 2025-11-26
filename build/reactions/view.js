import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "./src/shared/modal/index.js":
/*!***********************************!*\
  !*** ./src/shared/modal/index.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createModalStore: () => (/* binding */ createModalStore)
/* harmony export */ });
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");


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
function createModalStore(namespace) {
  const {
    actions,
    callbacks
  } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)(namespace, {
    actions: {
      /**
       * Open the modal.
       *
       * @param {Event} event Click event.
       */
      openModal(event) {
        const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

        // Set modal properties
        context.modal.isOpen = true;
        if (context.modal.isCompact) {
          // Position the compact modal relative to the button.
          setTimeout(callbacks.positionModal, 0);
        } else {
          // Set up the focus trap after modal is open.
          setTimeout(() => {
            // Use the blockId to find the specific modal frame for this block
            const blockWrapper = document.getElementById(context.blockId);
            if (blockWrapper) {
              const modalFrame = blockWrapper.querySelector('.activitypub-modal__frame');
              if (modalFrame) {
                callbacks.trapFocus(modalFrame);
              }
            }
          }, 50);
        }

        // Call the onOpen callback if provided.
        if (typeof callbacks.onModalOpen === 'function') {
          callbacks.onModalOpen(event);
        }
      },
      /**
       * Close the modal.
       *
       * @param {Event} event Click event.
       */
      closeModal(event) {
        const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

        // Reset modal state
        context.modal.isOpen = false;

        // Return focus to the button that opened the modal.
        const button = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)();
        if (button.ref.dataset['wpOn-Click'] === 'actions.toggleModal') {
          button.ref.focus();
        } else {
          const blockWrapper = document.getElementById(context.blockId);
          if (blockWrapper) {
            const openButton = blockWrapper.querySelector('[data-wp-on--click="actions.toggleModal"], [data-wp-on-async--click="actions.toggleModal"]');
            if (openButton) {
              openButton.focus();
            }
          }
        }

        // Call the onClose callback if provided.
        if (typeof callbacks.onModalClose === 'function') {
          callbacks.onModalClose(event);
        }
      },
      /**
       * Toggle the modal.
       *
       * @param {Event} event Click event.
       */
      toggleModal(event) {
        const {
          modal
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
        modal.isOpen ? actions.closeModal(event) : actions.openModal(event);
      }
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
        const {
          modal
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

        // Update body class.
        if (modal.isOpen && !modal.isCompact) {
          document.body.classList.add('modal-open');
        } else {
          document.body.classList.remove('modal-open');
        }

        // Remove all existing listeners.
        if (callbacks._abortController) {
          callbacks._abortController.abort();
          callbacks._abortController = null;
        }

        // Add new listeners if modal is open.
        if (modal.isOpen) {
          callbacks._abortController = new AbortController();
          const {
            signal
          } = callbacks._abortController;
          document.addEventListener('keydown', callbacks.documentKeydown, {
            signal
          });
          document.addEventListener('click', callbacks.documentClick, {
            signal
          });
        }
        return undefined;
      },
      /**
       * Handles keydown events on the document.
       *
       * @param {Event} event Keydown event.
       * @param {String} event.key The key that was pressed.
       */
      documentKeydown(event) {
        const {
          modal
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
        if (modal.isOpen && event.key === 'Escape') {
          actions.closeModal();
        }
      },
      /**
       * Handles click events on the document.
       *
       * @param {Event} event Click event.
       */
      documentClick(event) {
        const {
          blockId,
          modal
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
        if (!modal.isOpen) {
          return;
        }

        // Get the block wrapper element.
        const blockWrapper = document.getElementById(blockId);
        if (!blockWrapper) {
          return;
        }

        // If the click was on the button or its children, we should not close the modal.
        const toggleButton = blockWrapper.querySelector('.wp-element-button[data-wp-on--click="actions.toggleModal"]');
        if (toggleButton && (toggleButton === event.target || toggleButton.contains(event.target))) {
          return;
        }

        // Check if the click was inside the modal frame.
        const modalFrame = blockWrapper.querySelector('.activitypub-modal__frame');
        if (!modalFrame || modalFrame.contains(event.target)) {
          return;
        }
        actions.closeModal();
      },
      /**
       * Positions the modal relative to the button that opened it.
       */
      positionModal() {
        const {
          blockId
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
        const blockWrapper = document.getElementById(blockId);
        if (!blockWrapper) {
          return;
        }
        const modalOverlay = blockWrapper.querySelector('.activitypub-modal__overlay');
        if (!modalOverlay) {
          return;
        }

        // Reset any previously set positioning.
        modalOverlay.style.top = '';
        modalOverlay.style.left = '';
        modalOverlay.style.right = '';
        modalOverlay.style.bottom = '';

        // Get button position relative to viewport.
        const buttonRect = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)().ref.getBoundingClientRect();

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
          top: `${relativeTop + 8}px`,
          left: `${relativeLeft - 2}px` // -2 px to account for the button border.
        };

        // If not enough space to the right, align with the right edge.
        if (spaceRight < 250) {
          position.left = 'auto';
          position.right = `${blockRect.right - buttonRect.right}px`;
        }

        // Apply the position.
        Object.assign(modalOverlay.style, position);
      },
      /**
       * Traps focus within the specified element.
       *
       * @param {Element} element The element to trap focus within.
       */
      trapFocus(element) {
        const focusableElements = element.querySelectorAll('a[href]:not([disabled]), button:not([disabled]), textarea:not([disabled]), input[type="text"]:not([disabled]):not([readonly]), input[type="radio"]:not([disabled]), input[type="checkbox"]:not([disabled]), select:not([disabled])');
        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];

        // If the first focusable element is the close button, set initial focus to the next element instead.
        if (firstFocusableElement && firstFocusableElement.classList.contains('activitypub-modal__close') && focusableElements.length > 1) {
          // Set initial focus to the second element, but keep firstFocusableElement as is for tab trapping.
          focusableElements[1].focus();
        } else {
          // Otherwise focus the first element as usual.
          firstFocusableElement.focus();
        }
        element.addEventListener('keydown', function (event) {
          if (event.key !== 'Tab' && event.keyCode !== 9 /* KEYCODE_TAB */) {
            return;
          }
          if (event.shiftKey) {
            /* shift + tab */
            if (document.activeElement === firstFocusableElement) {
              lastFocusableElement.focus();
              event.preventDefault();
            }
          } /* tab */else {
            if (document.activeElement === lastFocusableElement) {
              firstFocusableElement.focus();
              event.preventDefault();
            }
          }
        });
      }
    }
  });
}

/***/ }),

/***/ "@wordpress/interactivity":
/*!*******************************************!*\
  !*** external "@wordpress/interactivity" ***!
  \*******************************************/
/***/ ((module) => {

module.exports = __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__;

/***/ })

/******/ });
/************************************************************************/
/******/ // The module cache
/******/ var __webpack_module_cache__ = {};
/******/ 
/******/ // The require function
/******/ function __webpack_require__(moduleId) {
/******/ 	// Check if module is in cache
/******/ 	var cachedModule = __webpack_module_cache__[moduleId];
/******/ 	if (cachedModule !== undefined) {
/******/ 		return cachedModule.exports;
/******/ 	}
/******/ 	// Create a new module (and put it into the cache)
/******/ 	var module = __webpack_module_cache__[moduleId] = {
/******/ 		// no module.id needed
/******/ 		// no module.loaded needed
/******/ 		exports: {}
/******/ 	};
/******/ 
/******/ 	// Execute the module function
/******/ 	__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 
/******/ 	// Return the exports of the module
/******/ 	return module.exports;
/******/ }
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/define property getters */
/******/ (() => {
/******/ 	// define getter functions for harmony exports
/******/ 	__webpack_require__.d = (exports, definition) => {
/******/ 		for(var key in definition) {
/******/ 			if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 				Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 			}
/******/ 		}
/******/ 	};
/******/ })();
/******/ 
/******/ /* webpack/runtime/hasOwnProperty shorthand */
/******/ (() => {
/******/ 	__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ })();
/******/ 
/******/ /* webpack/runtime/make namespace object */
/******/ (() => {
/******/ 	// define __esModule on exports
/******/ 	__webpack_require__.r = (exports) => {
/******/ 		if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 			Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 		}
/******/ 		Object.defineProperty(exports, '__esModule', { value: true });
/******/ 	};
/******/ })();
/******/ 
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!*******************************!*\
  !*** ./src/reactions/view.js ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _shared_modal__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/modal */ "./src/shared/modal/index.js");



/** @var {Object} window.wp WordPress global object */
const {
  apiFetch
} = window.wp;
(0,_shared_modal__WEBPACK_IMPORTED_MODULE_1__.createModalStore)('activitypub/reactions');

/**
 * @typedef {Object} state
 * @property {Object} reactions Reactions data, keyed by post ID.
 */

/**
 * @typedef {Object} context
 * @property {String} blockId The block ID.
 * @property {Object} modal The modal state.
 * @property {boolean} modal.isCompact Whether the modal is compact.
 * @property {boolean} modal.isOpen Whether the modal is open.
 * @property {Object} modal.items The items to display in the modal.
 * @property {String} postId The post ID.
 * @property {Object} reactions Reactions data, keyed by reaction type.
 */

const {
  callbacks,
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('activitypub/reactions', {
  actions: {
    /**
     * Fetches reactions for a post.
     */
    async fetchReactions() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        namespace
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)();
      if (!context.postId) return;
      try {
        // Update the state with the new Reactions data.
        context.reactions = await apiFetch({
          path: `/${namespace}/posts/${context.postId}/reactions`
        });
      } catch (error) {
        console.error('Error fetching reactions:', error);
      }
    }
  },
  callbacks: {
    /**
     * Initializes the Reactions component.
     */
    initReactions() {
      // Set up resize observer to recalculate on window resize.
      const resizeObserver = new ResizeObserver((0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.withScope)(callbacks.calculateVisibleAvatars));
      (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)().ref.querySelectorAll('.reaction-group').forEach(group => {
        resizeObserver.observe(group);
      });

      // Return a cleanup function to disconnect the observer when the block is unmounted.
      return () => {
        resizeObserver.disconnect();
      };
    },
    /**
     * Calculates and sets the number of visible avatars based on container width.
     */
    calculateVisibleAvatars() {
      const {
        postId
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Constants for calculations
      const AVATAR_WIDTH = 32; // Width of each avatar
      const AVATAR_OVERLAP = 10; // How much each avatar overlaps
      const EFFECTIVE_AVATAR_WIDTH = AVATAR_WIDTH - AVATAR_OVERLAP; // Width each additional avatar takes
      const BUTTON_GAP = 12; // Gap between avatars and button (0.75em)

      // Get all reaction types from the state.
      const reactionTypes = state.reactions && state.reactions[postId] ? Object.keys(state.reactions[postId]) : [];

      // Process each reaction group.
      reactionTypes.forEach(reactionType => {
        if (!state.reactions?.[postId][reactionType]?.items?.length) {
          return;
        }
        (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)().ref.querySelectorAll(`.reaction-group[data-reaction-type="${reactionType}"]`).forEach(container => {
          const label = container.querySelector('.reaction-label');
          const labelWidth = label.offsetWidth || 0;
          const availableWidth = container.offsetWidth - labelWidth - BUTTON_GAP;

          // Calculate how many avatars can fit.
          // The first avatar takes full width, the rest take effective width.
          let maxAvatars = 1; // Start with 1 for the first avatar.

          // If we have space for more than one avatar.
          if (availableWidth > AVATAR_WIDTH) {
            // Calculate how many additional avatars can fit in the remaining space.
            maxAvatars += Math.floor((availableWidth - AVATAR_WIDTH) / EFFECTIVE_AVATAR_WIDTH);
          }

          // Ensure we don't show more than we have.
          const items = state.reactions[postId][reactionType].items;
          const visibleCount = Math.min(maxAvatars, items.length);

          // Update the DOM to show only the calculated number of avatars.
          const avatarsList = container.querySelector('.reaction-avatars');
          if (avatarsList) {
            const avatarItems = avatarsList.querySelectorAll('li');
            avatarItems.forEach((item, index) => {
              if (index < visibleCount) {
                item.removeAttribute('hidden');
              } else {
                item.setAttribute('hidden', 'hidden');
              }
            });
          }
        });
      });
    },
    /**
     * Sets the default avatar when the avatar image fails to load.
     *
     * @param {Object} event The error event.
     */
    setDefaultAvatar(event) {
      event.target.src = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)().defaultAvatarUrl;
    },
    /**
     * Opens the modal with the specified reaction type.
     */
    onModalOpen() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const reactionType = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)().ref.dataset.reactionType;

      // Set modal properties.
      context.modal.items = state.reactions[context.postId][reactionType].items;
    }
  }
});
})();


//# sourceMappingURL=view.js.map