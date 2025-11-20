/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/library/comment-reply-link.js":
/*!**********************************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/comment-reply-link.js ***!
  \**********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ comment_reply_link_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
// packages/icons/src/library/comment-reply-link.tsx


var comment_reply_link_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { viewBox: "0 0 24 24", xmlns: "http://www.w3.org/2000/svg", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path, { d: "M6.68822 10.625L6.24878 11.0649L5.5 11.8145L5.5 5.5L12.5 5.5V8L14 6.5V5C14 4.44772 13.5523 4 13 4H5C4.44772 4 4 4.44771 4 5V13.5247C4 13.8173 4.16123 14.086 4.41935 14.2237C4.72711 14.3878 5.10601 14.3313 5.35252 14.0845L7.31 12.125H8.375L9.875 10.625H7.31H6.68822ZM14.5605 10.4983L11.6701 13.75H16.9975C17.9963 13.75 18.7796 14.1104 19.3553 14.7048C19.9095 15.2771 20.2299 16.0224 20.4224 16.7443C20.7645 18.0276 20.7543 19.4618 20.7487 20.2544C20.7481 20.345 20.7475 20.4272 20.7475 20.4999L19.2475 20.5001C19.2475 20.4191 19.248 20.3319 19.2484 20.2394V20.2394C19.2526 19.4274 19.259 18.2035 18.973 17.1307C18.8156 16.5401 18.586 16.0666 18.2778 15.7483C17.9909 15.4521 17.5991 15.25 16.9975 15.25H11.8106L14.5303 17.9697L13.4696 19.0303L8.96956 14.5303L13.4394 9.50171L14.5605 10.4983Z" }) });

//# sourceMappingURL=comment-reply-link.js.map


/***/ }),

/***/ "./src/reply/edit.js":
/*!***************************!*\
  !*** ./src/reply/edit.js ***!
  \***************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_8__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);










/**
 * Help text messages for different reply states.
 */

const HELP_TEXT = {
  default: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Enter the URL of a post from the Fediverse (Mastodon, Pixelfed, etc.) that you want to reply to.', 'activitypub'),
  checking: () => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Spinner, {}), ' ' + (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Checking URL...', 'activitypub')]
  }),
  valid: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('The author will be notified of your response.', 'activitypub'),
  error: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('This site doesn\u2019t have ActivityPub enabled and won\u2019t receive your reply.', 'activitypub')
};

/**
 * Edit component for the ActivityPub Reply block.
 *
 * @param {Object} props - Component props.
 * @param {Object} props.attributes - Block attributes.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @param {string} props.clientId - Block client ID.
 * @param {boolean} props.isSelected - Whether the block is selected.
 */
function Edit({
  attributes,
  setAttributes,
  clientId,
  isSelected
}) {
  const {
    url = '',
    embedPost = false
  } = attributes;
  const [helpText, setHelpText] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(HELP_TEXT.default);
  const [isValidEmbed, setIsValidEmbed] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const [isCheckingEmbed, setIsCheckingEmbed] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useState)(false);
  const urlInputRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useRef)();
  const {
    insertAfterBlock,
    removeBlock,
    replaceInnerBlocks
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.useDispatch)('core/block-editor');

  // Show embed in both selected and non-selected states when embedPost is true.
  const showEmbed = embedPost && !isCheckingEmbed && isValidEmbed;

  // Setup inner blocks.
  const innerBlocksProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useInnerBlocksProps)({
    className: 'activitypub-embed-container'
  }, {
    allowedBlocks: ['core/embed'],
    template: url && showEmbed ? [['core/embed', {
      url
    }]] : [],
    templateLock: 'all'
  });

  // Update inner blocks when URL, embedPost, or isValidEmbed changes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (url && showEmbed) {
      replaceInnerBlocks(clientId, [(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_8__.createBlock)('core/embed', {
        url
      })]);
    } else {
      // Remove all inner blocks if embedding is disabled or URL is not embeddable.
      replaceInnerBlocks(clientId, []);
    }
  }, [url, showEmbed, clientId, replaceInnerBlocks]);

  // Update help text based on state changes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (!url) {
      setHelpText(HELP_TEXT.default);
    } else if (isCheckingEmbed) {
      setHelpText(HELP_TEXT.checking());
    } else if (isValidEmbed) {
      setHelpText(HELP_TEXT.valid);
    } else {
      setHelpText(HELP_TEXT.error);
    }
  }, [url, isCheckingEmbed, isValidEmbed]);
  const focusInput = () => {
    setTimeout(() => urlInputRef.current?.focus(), 50);
  };

  // Check URL when it changes.
  const checkUrl = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useCallback)(async urlToCheck => {
    if (!urlToCheck) {
      setIsValidEmbed(false);
      return;
    }
    try {
      setIsCheckingEmbed(true);

      // Simple URL validation.
      new URL(urlToCheck); // Will throw if invalid.

      try {
        /**
         * Fetch the embed information using the WordPress oEmbed API.
         *
         * @typedef {Object} OEmbedResponse
         * @property {string} [provider_name] The name of the oEmbed provider.
         * @property {string} [html] The HTML content to embed.
         * @property {string} [title] The title of the embedded content.
         * @property {string} [author_name] The author of the embedded content.
         * @property {string} [author_url] The URL of the author.
         * @property {number} [width] The width of the embedded content.
         * @property {number} [height] The height of the embedded content.
         * @property {string} [type] The type of the embedded content (rich, video, photo).
         */
        const response = await _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_6___default()({
          path: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_7__.addQueryArgs)('/oembed/1.0/proxy', {
            url: urlToCheck,
            activitypub: true
          })
        });
        if (response && response.provider_name) {
          setAttributes({
            embedPost: true,
            isValidActivityPub: true
          }); // Auto-enable embedding when we get valid embed info.
          setIsValidEmbed(true);
        } else {
          setAttributes({
            isValidActivityPub: false
          });
          setIsValidEmbed(false);
        }
      } catch (error) {
        console.log('Could not fetch embed:', error);
        setAttributes({
          isValidActivityPub: false
        });
        setIsValidEmbed(false);
      }
    } catch (error) {
      setAttributes({
        isValidActivityPub: false
      });
      setIsValidEmbed(false);
    } finally {
      setIsCheckingEmbed(false);
    }
  }, [embedPost, setAttributes]);

  // Debounce the URL check to avoid too many requests.
  const debouncedCheckUrl = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_4__.useDebounce)(checkUrl, 250);

  // Check URL when it changes.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (url) {
      debouncedCheckUrl(url);
    }
  }, [url]);
  const onKeyDown = event => {
    if (event.key === 'Enter') {
      insertAfterBlock(clientId);
    }
    if (!url && ['Backspace', 'Delete'].includes(event.key)) {
      removeBlock(clientId);
    }
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Settings', 'activitypub'),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.ToggleControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Embed Post', 'activitypub'),
          checked: !!embedPost,
          onChange: value => setAttributes({
            embedPost: value
          }),
          disabled: !isValidEmbed,
          help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Show embedded content from the URL.', 'activitypub'),
          __nextHasNoMarginBottom: true
        })
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
      onClick: focusInput,
      ...(0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps)(),
      children: [isSelected && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.TextControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Your post is a reply to the following URL', 'activitypub'),
        value: url,
        onChange: value => setAttributes({
          url: value
        }),
        help: helpText,
        onKeyDown: onKeyDown,
        ref: urlInputRef,
        __next40pxDefaultSize: true,
        __nextHasNoMarginBottom: true
      }), showEmbed && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
        ...innerBlocksProps
      }), url && !showEmbed && !isSelected && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
        className: "activitypub-reply-block-editor__preview",
        contentEditable: false,
        onClick: focusInput,
        style: {
          cursor: 'pointer'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("a", {
          href: url,
          className: "u-in-reply-to",
          target: "_blank",
          rel: "noreferrer",
          children: '↬' + url.replace(/^https?:\/\//, '')
        })
      })]
    })]
  });
}

/***/ }),

/***/ "./src/reply/editor.scss":
/*!*******************************!*\
  !*** ./src/reply/editor.scss ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/compose":
/*!*********************************!*\
  !*** external ["wp","compose"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["compose"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["data"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/primitives":
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["primitives"];

/***/ }),

/***/ "@wordpress/url":
/*!*****************************!*\
  !*** external ["wp","url"] ***!
  \*****************************/
/***/ ((module) => {

module.exports = window["wp"]["url"];

/***/ }),

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["ReactJSXRuntime"];

/***/ })

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	var __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		var cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		var module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/compat get default export */
/******/ 	(() => {
/******/ 		// getDefaultExport function for compatibility with non-harmony modules
/******/ 		__webpack_require__.n = (module) => {
/******/ 			var getter = module && module.__esModule ?
/******/ 				() => (module['default']) :
/******/ 				() => (module);
/******/ 			__webpack_require__.d(getter, { a: getter });
/******/ 			return getter;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			for(var key in definition) {
/******/ 				if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 					Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(typeof Symbol !== 'undefined' && Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/************************************************************************/
var __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!****************************!*\
  !*** ./src/reply/index.js ***!
  \****************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/comment-reply-link.js");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./edit */ "./src/reply/edit.js");
/* harmony import */ var _editor_scss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./editor.scss */ "./src/reply/editor.scss");




const save = () => null;
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)('activitypub/reply', {
  edit: _edit__WEBPACK_IMPORTED_MODULE_2__["default"],
  save,
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__["default"],
  transforms: {
    from: [{
      type: 'block',
      blocks: ['core/embed'],
      transform: attributes => {
        return (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.createBlock)('activitypub/reply', {
          url: attributes.url || '',
          embedPost: true
        });
      }
    }],
    to: [{
      type: 'block',
      blocks: ['core/embed'],
      transform: attributes => {
        return (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.createBlock)('core/embed', {
          url: attributes.url || ''
        });
      }
    }]
  }
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map