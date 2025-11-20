import * as __WEBPACK_EXTERNAL_MODULE__wordpress_interactivity_8e89b257__ from "@wordpress/interactivity";
/******/ var __webpack_modules__ = ({

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
  !*** ./src/followers/view.js ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/interactivity */ "@wordpress/interactivity");


/**
 * @var {Object} window.wp WordPress global object
 * @var {Function} url.addQueryArgs Function to add query arguments to a URL.
 */
const {
  apiFetch,
  url
} = window.wp;

/**
 * @typedef {Object} config
 * @property {String} defaultAvatarUrl Default avatar URL.
 * @property {String} namespace ActivityPub REST Namespace.
 */

/**
 * @typedef {Object} context
 * @property {Array} followers The list of followers.
 * @property {boolean} isLoading Whether the followers are currently being fetched.
 * @property {String} order The order in which to fetch followers (e.g., 'asc', 'desc').
 * @property {Number} page The current page of followers.
 * @property {Number} pages The total number of pages of followers.
 * @property {Number} per_page The number of followers per page.
 * @property {Number} total The total number of followers.
 * @property {String} userId The user ID for which to fetch followers.
 */

const {
  actions
} = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.store)('activitypub/followers', {
  /**
   * @typedef {Object} state
   * @property {Function} paginationText Get the pagination text.
   * @property {Function} disablePreviousLink Whether the previous link should be disabled.
   * @property {Function} disableNextLink Whether the next link should be disabled.
   */
  state: {
    /**
     * Get the pagination text.
     *
     * @returns {string}
     */
    get paginationText() {
      const {
        page,
        pages
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      return `${page} / ${pages}`;
    },
    /**
     * Check if the previous link should be disabled.
     *
     * @returns {boolean}
     */
    get disablePreviousLink() {
      const {
        page
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      return page <= 1;
    },
    /**
     * Check if the next link should be disabled.
     *
     * @returns {boolean}
     */
    get disableNextLink() {
      const {
        page,
        pages
      } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      return page >= pages;
    }
  },
  actions: {
    /**
     * Fetch followers for the current page.
     *
     * @return {Promise<void>} Promise that resolves when followers are fetched.
     */
    async fetchFollowers() {
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      const {
        userId,
        page,
        per_page,
        order
      } = context;

      // Set loading state.
      context.isLoading = true;
      try {
        // Build the API path and parameters
        const {
          namespace
        } = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)();
        const path = url.addQueryArgs(`/${namespace}/actors/${userId}/followers`, {
          context: 'full',
          per_page,
          order,
          page
        });

        // Use apiFetch to get the Followers data.
        const {
          orderedItems,
          totalItems
        } = await apiFetch({
          path
        });

        // Update the context with the new followers.
        context.followers = orderedItems.map(follower => ({
          handle: '@' + follower.preferredUsername,
          icon: follower.icon,
          name: follower.name || follower.preferredUsername,
          url: follower.url || follower.id
        }));
        context.total = totalItems;
        context.pages = Math.ceil(totalItems / per_page);
      } catch (error) {
        console.error('Error fetching followers:', error);
      } finally {
        // Clear loading state.
        context.isLoading = false;
      }
    },
    /**
     * Navigate to the previous page.
     *
     * @param {Event} event - The click event.
     */
    previousPage(event) {
      event.preventDefault();
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (context.page > 1) {
        context.page--;
        actions.fetchFollowers().catch(error => {
          console.error('Error fetching followers:', error);
        });
      }
    },
    /**
     * Navigate to the next page.
     *
     * @param {Event} event - The click event.
     */
    nextPage(event) {
      event.preventDefault();
      const context = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getContext)();
      if (context.page < context.pages) {
        context.page++;
        actions.fetchFollowers().catch(error => {
          console.error('Error fetching followers:', error);
        });
      }
    }
  },
  callbacks: {
    /**
     * Sets the default avatar when the avatar image fails to load.
     *
     * @param {Object} event The error event.
     */
    setDefaultAvatar(event) {
      event.target.src = (0,_wordpress_interactivity__WEBPACK_IMPORTED_MODULE_0__.getConfig)().defaultAvatarUrl;
    }
  }
});
})();


//# sourceMappingURL=view.js.map