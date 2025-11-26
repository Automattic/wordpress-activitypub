import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "./src/remote-reply/style.scss":
/*!*************************************!*\
  !*** ./src/remote-reply/style.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/remote-reply/view.js":
/*!**********************************!*\
  !*** ./src/remote-reply/view.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _shared_modal__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../shared/modal */ "./src/shared/modal/index.js");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./src/remote-reply/style.scss");




/** @var {object} wp WordPress global. */
const {
  apiFetch
} = window.wp;
(0,_shared_modal__WEBPACK_IMPORTED_MODULE_1__.createModalStore)('activitypub/remote-reply');

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
 * @property {boolean} isError Whether there is an error.
 * @property {boolean} isLoading Whether the remote profile is being submitted.
 * @property {Object} modal The modal state.
 * @property {boolean} modal.isOpen Whether the modal is open.
 * @property {String} remoteProfile The remote profile.
 * @property {boolean} shouldSaveProfile Whether to save the profile.
 */

const {
  actions,
  callbacks,
  state
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('activitypub/remote-reply', {
  state: {
    /**
     * Get the remote profile URL.
     *
     * @returns {String} The remote profile URL.
     */
    get remoteProfileUrl() {
      const {
        commentURL
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      return state.template.replace('{uri}', encodeURIComponent(commentURL));
    }
  },
  actions: {
    /**
     * Handle the opening of the modal.
     *
     * @param {Event} event The event that triggered the modal opening/closing.
     * @param {String} event.key The key pressed, if any.
     */
    onReplyLinkKeydown(event) {
      // Handle Enter key to open the modal.
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        actions.toggleModal(event);
      }
    },
    /**
     * Copy the comment URL to the clipboard.
     */
    copyToClipboard() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        i18n
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)();

      // Use the Clipboard API to copy text.
      navigator.clipboard.writeText(context.commentURL).then(() => {
        // Update button text to show success.
        context.copyButtonText = i18n.copied;

        // Reset button text after 1 second.
        setTimeout(() => {
          context.copyButtonText = i18n.copy;
        }, 1000);
      }, error => {
        // Log error if copying fails.
        console.error('Could not copy text: ', error);
      });
    },
    /**
     * Update the remote profile value.
     *
     * @param {Event} event Input event.
     * @param {String} event.target.value The remote profile value.
     */
    updateRemoteProfile(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
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
    onInputKeydown(event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        return actions.submitRemoteProfile();
      }
    },
    /**
     * Submit the remote profile.
     */
    *submitRemoteProfile() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        namespace,
        i18n
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)();
      const profileURL = context.remoteProfile.trim();

      // Validate input.
      if (!profileURL) {
        context.isError = true;
        context.errorMessage = i18n.emptyProfileError;
        return;
      }
      if (!callbacks.isHandle(profileURL) && !callbacks.isUrl(profileURL)) {
        context.isError = true;
        context.errorMessage = i18n.invalidProfileError;
        return;
      }

      // Set loading state.
      context.isLoading = true;
      context.isError = false;
      context.errorMessage = '';

      // Construct the API path.
      const path = `/${namespace}/comments/${context.commentId}/remote-reply?resource=${encodeURIComponent(profileURL)}`;
      try {
        // Make the API request.
        const {
          template,
          url
        } = yield apiFetch({
          path
        });

        // Set opening state.
        context.isLoading = false;

        // Open the remote reply URL in a new tab.
        window.open(url, '_blank');

        // Close the modal after opening the URL.
        actions.closeModal();

        // Save the remote user if the remember option is checked.
        if (context.shouldSaveProfile) {
          callbacks.setStore({
            profileURL,
            template
          });
          Object.assign(state, {
            hasRemoteUser: true,
            profileURL,
            template
          });
        }
      } catch (error) {
        // Handle error.
        console.error('Error submitting profile:', error);
        context.isLoading = false;
        context.isError = true;
        context.errorMessage = error.message || i18n.genericError;
      }
    },
    /**
     * Toggle the remember profile checkbox.
     */
    toggleRememberProfile() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      context.shouldSaveProfile = !context.shouldSaveProfile;
    },
    /**
     * Delete the saved remote user profile.
     */
    deleteRemoteUser() {
      callbacks.deleteStore();
      state.hasRemoteUser = false;
      state.profileURL = '';
      state.template = '';
    }
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
      const {
        profileURL,
        template
      } = callbacks.getStore();

      // Set the remote user data from localStorage if available.
      if (profileURL && template) {
        Object.assign(state, {
          hasRemoteUser: true,
          profileURL,
          template
        });
      }
    },
    /**
     * Retrieve the remote user data from localStorage.
     *
     * @returns {Object} Remote user data or empty object, if not set.
     */
    getStore() {
      const data = localStorage.getItem(callbacks.storageKey);
      return data ? JSON.parse(data) : {};
    },
    /**
     * Store remote user data in localStorage.
     *
     * @param {Object} data - Remote user data to store.
     */
    setStore(data) {
      localStorage.setItem(callbacks.storageKey, JSON.stringify(data));
    },
    /**
     * Remove remote user data from localStorage.
     */
    deleteStore() {
      localStorage.removeItem(callbacks.storageKey);
    },
    /**
     * Best guess whether a string is a valid ActivityPub handle.
     *
     * @param {string} string - String to check.
     * @returns {boolean} True if string is a valid handle, false otherwise.
     */
    isHandle(string) {
      // Check if the string starts with '@' and contains a valid URL.
      const parts = string.replace(/^@/, '').split('@');
      return parts.length === 2 && callbacks.isUrl(`https://${parts[1]}`);
    },
    /**
     * Checks if a string is a valid URL.
     *
     * @param {string} string - String to check.
     * @returns {boolean} True if string is a valid URL, false otherwise.
     */
    isUrl(string) {
      try {
        new URL(string);
        return true;
      } catch (_) {
        return false;
      }
    }
  }
});

/***/ }),

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
/******/ // expose the modules object (__webpack_modules__)
/******/ __webpack_require__.m = __webpack_modules__;
/******/ 
/************************************************************************/
/******/ /* webpack/runtime/chunk loaded */
/******/ (() => {
/******/ 	var deferred = [];
/******/ 	__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 		if(chunkIds) {
/******/ 			priority = priority || 0;
/******/ 			for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 			deferred[i] = [chunkIds, fn, priority];
/******/ 			return;
/******/ 		}
/******/ 		var notFulfilled = Infinity;
/******/ 		for (var i = 0; i < deferred.length; i++) {
/******/ 			var [chunkIds, fn, priority] = deferred[i];
/******/ 			var fulfilled = true;
/******/ 			for (var j = 0; j < chunkIds.length; j++) {
/******/ 				if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 					chunkIds.splice(j--, 1);
/******/ 				} else {
/******/ 					fulfilled = false;
/******/ 					if(priority < notFulfilled) notFulfilled = priority;
/******/ 				}
/******/ 			}
/******/ 			if(fulfilled) {
/******/ 				deferred.splice(i--, 1)
/******/ 				var r = fn();
/******/ 				if (r !== undefined) result = r;
/******/ 			}
/******/ 		}
/******/ 		return result;
/******/ 	};
/******/ })();
/******/ 
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
/******/ /* webpack/runtime/export webpack runtime */
/******/ var __webpack_require_temp__ = __webpack_require__;
/******/ export { __webpack_require_temp__ as __webpack_require__ };
/******/ 
/******/ /* webpack/runtime/import chunk loading */
/******/ (() => {
/******/ 	// no baseURI
/******/ 	
/******/ 	// object to store loaded and loading chunks
/******/ 	// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 	// [resolve, Promise] = chunk loading, 0 = chunk loaded
/******/ 	var installedChunks = {
/******/ 		"remote-reply/view": 0,
/******/ 		"remote-reply/style-view": 0
/******/ 	};
/******/ 	
/******/ 	var installChunk = (data) => {
/******/ 		var {__webpack_esm_ids__, __webpack_esm_modules__, __webpack_esm_runtime__} = data;
/******/ 		// add "modules" to the modules object,
/******/ 		// then flag all "ids" as loaded and fire callback
/******/ 		var moduleId, chunkId, i = 0;
/******/ 		for(moduleId in __webpack_esm_modules__) {
/******/ 			if(__webpack_require__.o(__webpack_esm_modules__, moduleId)) {
/******/ 				__webpack_require__.m[moduleId] = __webpack_esm_modules__[moduleId];
/******/ 			}
/******/ 		}
/******/ 		if(__webpack_esm_runtime__) __webpack_esm_runtime__(__webpack_require__);
/******/ 		for(;i < __webpack_esm_ids__.length; i++) {
/******/ 			chunkId = __webpack_esm_ids__[i];
/******/ 			if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 				installedChunks[chunkId][0]();
/******/ 			}
/******/ 			installedChunks[__webpack_esm_ids__[i]] = 0;
/******/ 		}
/******/ 		__webpack_require__.O();
/******/ 	}
/******/ 	
/******/ 	// no chunk on demand loading
/******/ 	
/******/ 	// no prefetching
/******/ 	
/******/ 	// no preloaded
/******/ 	
/******/ 	__webpack_require__.C = installChunk;
/******/ 	
/******/ 	__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 	// no HMR
/******/ 	
/******/ 	// no HMR manifest
/******/ })();
/******/ 
/************************************************************************/
/******/ 
/******/ 
/******/ // startup
/******/ // Load entry module and return exports
/******/ // This entry module depends on other loaded chunks and execution need to be delayed
/******/ var __webpack_exports__ = __webpack_require__.O(undefined, ["remote-reply/style-view"], () => (__webpack_require__("./src/remote-reply/view.js")))
/******/ __webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 

//# sourceMappingURL=view.js.map