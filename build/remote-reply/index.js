import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "./src/remote-reply/index.js":
/*!***********************************!*\
  !*** ./src/remote-reply/index.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _view_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./view.js */ "./src/remote-reply/view.js");
/**
 * WordPress dependencies.
 */


// This file is kept minimal as the Interactivity API handles the component initialization.
// The view.js file contains all the interactivity logic and store definitions.

/***/ }),

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
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/remote-reply/style.scss");



/** @var {object} wp WordPress global. */
const {
  apiFetch
} = window.wp;

/**
 * Traps focus within the specified element.
 *
 * @param {Element} element The element to trap focus within.
 */
function trapFocus(element) {
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
const storageKey = 'fediverse-remote-user';

/**
 * Retrieve the remote user data from localStorage.
 *
 * @returns {Object} Remote user data or empty object, if not set.
 */
function getStore() {
  const data = localStorage.getItem(storageKey);
  if (!data) {
    return {};
  }
  return JSON.parse(data);
}

/**
 * Store remote user data in localStorage.
 *
 * @param {Object} data - Remote user data to store.
 */
function setStore(data) {
  localStorage.setItem(storageKey, JSON.stringify(data));
}

/**
 * Remove remote user data from localStorage.
 */
function deleteStore() {
  localStorage.removeItem(storageKey);
}
const {
  state,
  actions,
  utils
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('activitypub/remote-reply', {
  actions: {
    /**
     * Open the modal.
     */
    openModal() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      context.isModalOpen = true;
      document.body.classList.add('modal-open');

      // Set up the focus trap after modal is open.
      setTimeout(() => {
        // Use the blockId to find the specific modal frame for this block.
        const blockWrapper = document.getElementById(context.blockId);
        if (blockWrapper) {
          const modalFrame = blockWrapper.querySelector('.activitypub-modal__frame');
          if (modalFrame) {
            trapFocus(modalFrame);
          }
        }
      }, 50);
    },
    /**
     * Close the modal.
     */
    closeModal() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      context.isModalOpen = false;
      context.isError = false;
      document.body.classList.remove('modal-open');

      // Return focus to the button that opened the modal.
      const blockWrapper = document.getElementById(context.blockId);
      if (blockWrapper) {
        const openButton = blockWrapper.querySelector('.activitypub-remote-reply__button');
        if (openButton) {
          openButton.focus();
        }
      }
    },
    /**
     * Toggle the modal state.
     */
    toggleModal() {
      const {
        isModalOpen
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      isModalOpen ? actions.closeModal() : actions.openModal();
    },
    /**
     * Copy the comment URL to clipboard.
     */
    copyToClipboard() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Use the Clipboard API to copy text.
      navigator.clipboard.writeText(context.commentURL).then(() => {
        // Update button text to show success.
        context.copyButtonText = state.i18n.copied;

        // Reset button text after 1 second.
        setTimeout(() => {
          context.copyButtonText = state.i18n.copy;
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
     */
    handleKeyDown(event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        actions.submitRemoteProfile();
      }
    },
    /**
     * Submit the remote profile.
     */
    submitRemoteProfile: function* () {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        namespace
      } = state;
      const input = context.remoteProfile.trim();

      // Validate input.
      if (!input) {
        context.isError = true;
        context.errorMessage = state.i18n.emptyProfileError;
        return;
      }
      if (!utils.isHandle(input) && !utils.isUrl(input)) {
        context.isError = true;
        context.errorMessage = state.i18n.invalidProfileError;
        return;
      }

      // Set loading state.
      context.isLoading = true;
      context.isError = false;

      // Construct the API path.
      const path = `/${namespace}/comments/${context.commentId}/remote-reply?resource=${encodeURIComponent(input)}`;
      try {
        // Make the API request.
        const response = yield apiFetch({
          path
        });

        // Save the remote user if the remember option is checked.
        if (context.shouldSaveProfile) {
          setStore({
            profileURL: input,
            template: response.template
          });
        }

        // Set opening state.
        context.isLoading = false;

        // Open the remote reply URL in a new tab.
        window.open(response.url, '_blank');

        // Close the modal after opening the URL.
        actions.closeModal();
      } catch (error) {
        // Handle error.
        console.error('Error submitting profile:', error);
        context.isLoading = false;
        context.isError = true;
        context.errorMessage = error.message || state.i18n.genericError;
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
      deleteStore();
      // Refresh the page to update the UI.
      window.location.reload();
    },
    /**
     * Open the remote user's instance to reply.
     */
    openRemoteInstance() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const url = context.template.replace('{uri}', context.commentURL);
      window.open(url, '_blank');
    }
  },
  callbacks: {
    /**
     * Close modal when pressing ESC key.
     *
     * @param {Event} event Keyboard event.
     */
    documentKeydown(event) {
      const {
        isModalOpen
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (isModalOpen && event.key === 'Escape') {
        actions.closeModal();
      }
    },
    /**
     * Close modal when clicking outside.
     *
     * @param {Event} event Click event.
     */
    documentClick(event) {
      const {
        blockId,
        isModalOpen
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (!isModalOpen) {
        return;
      }

      // Get the block wrapper element.
      const blockWrapper = document.getElementById(blockId);
      if (!blockWrapper) {
        return;
      }

      // If the click was on the button or its children, we should not close the modal.
      const toggleButton = blockWrapper.querySelector('button[data-wp-on--click="actions.toggleModal"]');
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
     * Initialize the component.
     */
    init() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const storedUser = getStore();

      // Set the remote user data from localStorage if available.
      if (storedUser.profileURL && storedUser.template) {
        context.hasRemoteUser = true;
        context.profileURL = storedUser.profileURL;
        context.template = storedUser.template;
      }
    }
  },
  utils: {
    /**
     * Best guess whether a string is a valid ActivityPub handle.
     *
     * @param {string} string - String to check.
     * @returns {boolean} True if string is a valid handle, false otherwise.
     */
    isHandle(string) {
      // Check if the string starts with '@' and contains a valid URL.
      const parts = string.replace(/^@/, '').split('@');
      return parts.length === 2 && utils.isUrl(`https://${parts[1]}`);
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
/******/ /* webpack/runtime/import chunk loading */
/******/ (() => {
/******/ 	// no baseURI
/******/ 	
/******/ 	// object to store loaded and loading chunks
/******/ 	// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 	// [resolve, Promise] = chunk loading, 0 = chunk loaded
/******/ 	var installedChunks = {
/******/ 		"remote-reply/index": 0,
/******/ 		"remote-reply/style-index": 0
/******/ 	};
/******/ 	
/******/ 	// no install chunk
/******/ 	
/******/ 	// no chunk on demand loading
/******/ 	
/******/ 	// no prefetching
/******/ 	
/******/ 	// no preloaded
/******/ 	
/******/ 	// no external install chunk
/******/ 	
/******/ 	__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ })();
/******/ 
/************************************************************************/
/******/ 
/******/ // startup
/******/ // Load entry module and return exports
/******/ // This entry module depends on other loaded chunks and execution need to be delayed
/******/ var __webpack_exports__ = __webpack_require__.O(undefined, ["remote-reply/style-index"], () => (__webpack_require__("./src/remote-reply/index.js")))
/******/ __webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 

//# sourceMappingURL=index.js.map