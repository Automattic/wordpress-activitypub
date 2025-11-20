import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

/***/ "./src/follow-me/button-style.js":
/*!***************************************!*\
  !*** ./src/follow-me/button-style.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getBlockStyles: () => (/* binding */ getBlockStyles),
/* harmony export */   getPopupStyles: () => (/* binding */ getPopupStyles)
/* harmony export */ });
/**
 * Cache for computed styles and CSS variable checks.
 */
const cssCache = {
  computedStyles: null,
  variables: {}
};

/**
 * Checks if a CSS variable is defined.
 *
 * Uses a caching mechanism to avoid frequent getComputedStyle calls,
 * which can cause layout thrashing when called repeatedly.
 *
 * @param {string} variableName The CSS variable name to check.
 * @return {boolean} Whether the variable is defined.
 */
function isCssVariableDefined(variableName) {
  // Return false if we're in a server-side context.
  if (typeof window === 'undefined' || !window.getComputedStyle) {
    return false;
  }

  // Check if we've already cached this variable.
  if (cssCache.variables.hasOwnProperty(variableName)) {
    return cssCache.variables[variableName];
  }

  // Get the computed style of the root element (cached).
  if (!cssCache.computedStyles) {
    cssCache.computedStyles = window.getComputedStyle(document.documentElement);
  }

  // Get the value of the CSS variable.
  const value = cssCache.computedStyles.getPropertyValue(variableName).trim();

  // Cache the result.
  cssCache.variables[variableName] = value !== '';

  // If the value is empty, the variable is not defined or is set to an empty value.
  return cssCache.variables[variableName];
}

/**
 * Gets the background color from a style object.
 *
 * @param {Object|string} color Color object or string.
 * @return {string|null} Background color.
 */
function getBackgroundColor(color) {
  // If color is a string, it's a var like this.
  if (typeof color === 'string') {
    const varName = `--wp--preset--color--${color}`;
    if (!isCssVariableDefined(varName)) {
      return null;
    }
    return `var(${varName})`;
  }
  return color?.color?.background || null;
}

/**
 * Gets the link color from a style object.
 *
 * @param {string} text Text color.
 * @return {string|null} Link color.
 */
function getLinkColor(text) {
  if (typeof text !== 'string') {
    return null;
  }
  // If it starts with a hash, leave it be.
  if (text.match(/^#/)) {
    // We don't handle the alpha channel if present.
    return text.substring(0, 7);
  }
  // var:preset|color|luminous-vivid-amber
  // var(--wp--preset--color--luminous-vivid-amber)
  // We will receive the top format, we need to output the bottom format.
  const [,, color] = text.split('|');
  const varName = `--wp--preset--color--${color}`;

  // Check if the CSS variable is defined before using it.
  if (!isCssVariableDefined(varName)) {
    return null;
  }
  return `var(${varName})`;
}

/**
 * Generates a CSS selector.
 *
 * @param {string} selector CSS selector.
 * @param {string} prop CSS property.
 * @param {string|null} value CSS value.
 * @param {string} pseudo Pseudo-selector.
 * @return {string} CSS selector.
 */
function generateSelector(selector, prop, value = null, pseudo = '') {
  if (!value) {
    return '';
  }
  return `${selector}${pseudo} { ${prop}: ${value}; }\n`;
}

/**
 * Gets styles for a button.
 *
 * @param {string} selector CSS selector.
 * @param {string} button Button color.
 * @param {string} text Text color.
 * @param {string} hover Hover color.
 * @return {string} CSS styles.
 */
function getStyles(selector, button, text, hover) {
  return generateSelector(selector, 'background-color', button) + generateSelector(selector, 'color', text) + generateSelector(selector, 'background-color', hover, ':hover') + generateSelector(selector, 'background-color', hover, ':focus');
}

/**
 * Gets block styles.
 *
 * @param {string} base Base selector.
 * @param {Object} style Style object.
 * @param {Object|string} backgroundColor Background color.
 * @return {string} CSS styles.
 */
function getBlockStyles(base, style, backgroundColor) {
  const selector = `${base} .wp-block-button__link`;

  // We grab the background color if set as a good color for our button text.
  const buttonTextColor = getBackgroundColor(backgroundColor) ||
  // Background might be in this form.
  style?.color?.background;

  // We misuse the link color for the button background.
  const buttonColor = getLinkColor(style?.elements?.link?.color?.text);
  const buttonHoverColor = getLinkColor(style?.elements?.link?.[':hover']?.color?.text);
  return getStyles(selector, buttonColor, buttonTextColor, buttonHoverColor);
}

/**
 * Gets popup styles.
 *
 * @param {Object} style Style object.
 * @return {string} CSS styles.
 */
function getPopupStyles(style) {
  // We don't accept backgroundColor because the popup is always white (right?).
  const buttonColor = getLinkColor(style?.elements?.link?.color?.text) || '#111';
  const buttonTextColor = '#fff';
  const buttonHoverColor = getLinkColor(style?.elements?.link?.[':hover']?.color?.text) || '#333';
  const selector = '.activitypub-dialog__button-group .wp-block-button';
  return getStyles(selector, buttonColor, buttonTextColor, buttonHoverColor);
}

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
  !*** ./src/follow-me/view.js ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");
/* harmony import */ var _button_style__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./button-style */ "./src/follow-me/button-style.js");
/* harmony import */ var _shared_modal__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../shared/modal */ "./src/shared/modal/index.js");




/** @var {object} wp WordPress global. */
const {
  apiFetch
} = window.wp;
(0,_shared_modal__WEBPACK_IMPORTED_MODULE_2__.createModalStore)('activitypub/follow-me');

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

const {
  actions,
  callbacks
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('activitypub/follow-me', {
  actions: {
    /**
     * Copy the webfinger to clipboard.
     */
    copyToClipboard() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        i18n
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)();

      // Use the Clipboard API to copy text.
      navigator.clipboard.writeText(context.webfinger).then(() => {
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
     */
    updateRemoteProfile(event) {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
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
    onKeydown(event) {
      if ((0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getElement)().ref.tagName === 'A' && (event.key === 'Enter' || event.key === ' ')) {
        event.preventDefault();
        actions.toggleModal(event);
      }
    },
    /**
     * Handle keydown event for remote profile input.
     *
     * @param {Event} event Keydown event.
     * @param {String} event.key The key pressed.
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
        namespace,
        i18n
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)();
      const input = context.remoteProfile.trim();

      // Validate input.
      if (!input) {
        context.isError = true;
        context.errorMessage = i18n.emptyProfileError;
        return;
      }
      if (!callbacks.isHandle(input)) {
        context.isError = true;
        context.errorMessage = i18n.invalidProfileError;
        return;
      }

      // Set loading state.
      context.isLoading = true;
      context.isError = false;

      // Construct the API path.
      const path = `/${namespace}/actors/${context.userId}/remote-follow?resource=${encodeURIComponent(input)}`;
      try {
        // Make the API request.
        const response = yield apiFetch({
          path
        });

        // Set opening state.
        context.isLoading = false;

        // Open the remote follow URL in a new tab.
        window.open(response.url, '_blank');

        // Close the modal after opening the URL.
        actions.closeModal(new Event('click'));
      } catch (error) {
        // Handle error.
        console.error('Error submitting profile:', error);
        context.isLoading = false;
        context.isError = true;
        context.errorMessage = error.message || i18n.genericError;
      }
    }
  },
  callbacks: {
    /**
     * Initialize button styles.
     */
    initButtonStyles: () => {
      const {
        buttonStyle,
        backgroundColor,
        blockId
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();

      // Add dynamic button styles to the document.
      if (blockId && buttonStyle) {
        const styleElement = document.createElement('style');
        const selector = `#${blockId}`;

        // Use getBlockStyles from button-style.js to get the CSS string.
        styleElement.textContent = (0,_button_style__WEBPACK_IMPORTED_MODULE_1__.getBlockStyles)(selector, buttonStyle, backgroundColor);
        document.head.appendChild(styleElement);

        // Add popup styles.
        const popupStyleElement = document.createElement('style');
        popupStyleElement.textContent = (0,_button_style__WEBPACK_IMPORTED_MODULE_1__.getPopupStyles)(buttonStyle);
        document.head.appendChild(popupStyleElement);
      }
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
    },
    /**
     * Callback when modal is closed.
     */
    onModalClose() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      context.isError = false;
    }
  }
});
})();


//# sourceMappingURL=view.js.map