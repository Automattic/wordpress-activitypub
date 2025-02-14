/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/reactions/reactions.js":
/*!************************************!*\
  !*** ./src/reactions/reactions.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Reactions: () => (/* binding */ Reactions)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _shared_use_options__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../shared/use-options */ "./src/shared/use-options.js");

/**
 * WordPress dependencies
 */






/**
 * A component that renders a row of user avatars for a given set of reactions.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.reactions Array of reaction objects.
 * @return {JSX.Element}           The rendered component.
 */
const FacepileRow = ({
  reactions
}) => {
  const {
    defaultAvatarUrl
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_5__.useOptions)();
  const [activeIndices, setActiveIndices] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(new Set());
  const [rotationStates, setRotationStates] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(new Map());
  const timeoutRefs = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)([]);
  const clearTimeouts = () => {
    timeoutRefs.current.forEach(timeout => clearTimeout(timeout));
    timeoutRefs.current = [];
  };
  const startWave = (startIndex, isEntering) => {
    clearTimeouts();
    const delay = 100; // 100ms between each avatar
    const totalAvatars = reactions.length;
    if (isEntering) {
      setRotationStates(current => {
        const updated = new Map(current);
        updated.set(startIndex, 'clockwise');
        return updated;
      });
    }

    // Helper function to create wave in either direction
    const createWave = direction => {
      const isRightward = direction === 'right';
      const start = isRightward ? startIndex : startIndex - 1;
      const end = isRightward ? totalAvatars - 1 : 0;
      const step = isRightward ? 1 : -1;
      for (let i = start; isRightward ? i <= end : i >= end; i += step) {
        const delayMultiplier = Math.abs(i - startIndex);
        const timeout = setTimeout(() => {
          setActiveIndices(current => {
            const updated = new Set(current);
            if (isEntering) {
              updated.add(i);
            } else {
              updated.delete(i);
            }
            return updated;
          });
          if (isEntering && i !== startIndex) {
            setRotationStates(current => {
              const updated = new Map(current);
              const neighborIndex = i - step;
              const neighborRotation = updated.get(neighborIndex);
              updated.set(i, neighborRotation === 'clockwise' ? 'counter' : 'clockwise');
              return updated;
            });
          }
        }, delayMultiplier * delay);
        timeoutRefs.current.push(timeout);
      }
    };

    // Create waves in both directions
    createWave('right');
    createWave('left');

    // Clear rotations when wave finishes retracting
    if (!isEntering) {
      const maxDelay = Math.max((totalAvatars - startIndex) * delay, startIndex * delay);
      const timeout = setTimeout(() => {
        setRotationStates(new Map());
      }, maxDelay + delay);
      timeoutRefs.current.push(timeout);
    }
  };

  // Cleanup timeouts on unmount
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    return () => clearTimeouts();
  }, []);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
    className: "reaction-avatars"
  }, reactions.map((reaction, index) => {
    const rotationClass = rotationStates.get(index);
    const classes = ['reaction-avatar', activeIndices.has(index) ? 'wave-active' : '', rotationClass ? `rotate-${rotationClass}` : ''].filter(Boolean).join(' ');
    const avatar = reaction.avatar || defaultAvatarUrl;
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
      key: index
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
      href: reaction.url,
      target: "_blank",
      rel: "noopener noreferrer",
      onMouseEnter: () => startWave(index, true),
      onMouseLeave: () => startWave(index, false)
    }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
      src: avatar,
      alt: reaction.name,
      className: classes,
      width: "32",
      height: "32"
    })));
  }));
};

/**
 * A component that renders a dropdown list of reactions.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.reactions Array of reaction objects.
 * @param {Object}   props.anchor    Reference to anchor element.
 * @param {Function} props.onClose   Callback when dropdown closes.
 * @return {JSX.Element}            The rendered component.
 */
const ReactionDropdown = ({
  reactions,
  anchor,
  onClose
}) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Popover, {
  anchor: anchor,
  placement: "bottom-end",
  onClose: onClose,
  className: "reaction-dropdown",
  noArrow: false,
  offset: 10
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
  className: "activitypub-reaction-list"
}, reactions.map((reaction, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
  key: index
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
  href: reaction.url,
  className: "reaction-item",
  target: "_blank",
  rel: "noopener noreferrer"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
  src: reaction.avatar,
  alt: reaction.name,
  width: "32",
  height: "32"
}), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, reaction.name))))));

/**
 * A component that renders a dropdown list of reactions.
 *
 * @param {Object}   props           Component props.
 * @param {Array}    props.reactions Array of reaction objects.
 * @param {string}   props.type      Type of reaction (likes/reposts).
 * @return {JSX.Element}            The rendered component.
 */
const ReactionList = ({
  reactions,
  type
}) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", {
  className: "activitypub-reaction-list"
}, reactions.map((reaction, index) => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
  key: index
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
  href: reaction.url,
  className: "reaction-item",
  target: "_blank",
  rel: "noopener noreferrer"
}, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
  src: reaction.avatar,
  alt: reaction.name,
  width: "32",
  height: "32"
}), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", null, reaction.name)))));

/**
 * A component that renders a reaction group with facepile and dropdown.
 *
 * @param {Object} props           Component props.
 * @param {Array}  props.items     Array of reaction objects.
 * @param {string} props.label     Label for the reaction group.
 * @return {JSX.Element}          The rendered component.
 */
const ReactionGroup = ({
  items,
  label
}) => {
  const [isOpen, setIsOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(false);
  const [buttonRef, setButtonRef] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(null);
  const [visibleCount, setVisibleCount] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(items.length);
  const containerRef = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useRef)(null);

  // Constants for calculations
  const AVATAR_WIDTH = 32; // Width of each avatar
  const AVATAR_OVERLAP = 10; // How much each avatar overlaps
  const EFFECTIVE_AVATAR_WIDTH = AVATAR_WIDTH - AVATAR_OVERLAP; // Width each additional avatar takes
  const BUTTON_GAP = 12; // Gap between avatars and button (0.75em)

  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (!containerRef.current) {
      return;
    }
    const calculateVisibleAvatars = () => {
      const container = containerRef.current;
      if (!container) {
        return;
      }
      const containerWidth = container.offsetWidth;
      const labelWidth = buttonRef?.offsetWidth || 0;
      const availableWidth = containerWidth - labelWidth - BUTTON_GAP;

      // Calculate how many avatars can fit
      // First avatar takes full width, rest take effective width
      const maxAvatars = Math.max(1, Math.floor((availableWidth - AVATAR_WIDTH) / EFFECTIVE_AVATAR_WIDTH));

      // Ensure we don't show more than we have
      setVisibleCount(Math.min(maxAvatars, items.length));
    };

    // Initial calculation
    calculateVisibleAvatars();

    // Setup resize observer
    const resizeObserver = new ResizeObserver(calculateVisibleAvatars);
    resizeObserver.observe(containerRef.current);
    return () => {
      resizeObserver.disconnect();
    };
  }, [buttonRef, items.length]);
  const visibleItems = items.slice(0, visibleCount);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "reaction-group",
    ref: containerRef
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(FacepileRow, {
    reactions: visibleItems
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
    ref: setButtonRef,
    className: "reaction-label is-link",
    onClick: () => setIsOpen(!isOpen),
    "aria-expanded": isOpen
  }, label), isOpen && buttonRef && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Popover, {
    anchor: buttonRef,
    onClose: () => setIsOpen(false)
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ReactionList, {
    reactions: items
  })));
};

/**
 * The Reactions component.
 *
 * @param {Object}    props                  Component props.
 * @param {string}    props.title            The title text.
 * @param {?number}   props.postId           The post ID.
 * @param {?Object}   props.reactions        Optional reactions data.
 * @param {?JSX.Element} props.titleComponent Optional component for title editing.
 * @return {?JSX.Element}                    The rendered component.
 */
function Reactions({
  title = '',
  postId = null,
  reactions: providedReactions = null,
  titleComponent = null
}) {
  const {
    namespace
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_5__.useOptions)();
  const [reactions, setReactions] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(providedReactions);
  const [loading, setLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(!providedReactions);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useEffect)(() => {
    if (providedReactions) {
      setReactions(providedReactions);
      setLoading(false);
      return;
    }
    if (!postId) {
      setLoading(false);
      return;
    }
    setLoading(true);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: `/${namespace}/posts/${postId}/reactions`
    }).then(response => {
      setReactions(response);
      setLoading(false);
    }).catch(() => setLoading(false));
  }, [postId, providedReactions]);
  if (loading) {
    return null;
  }

  // Return null if there are no reactions
  if (!reactions || !Object.values(reactions).some(group => group.items?.length > 0)) {
    return null;
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "activitypub-reactions"
  }, titleComponent || title && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h6", null, title), Object.entries(reactions).map(([key, group]) => {
    if (!group.items?.length) {
      return null;
    }
    return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(ReactionGroup, {
      key: key,
      items: group.items,
      label: group.label
    });
  }));
}

/***/ }),

/***/ "./src/shared/use-options.js":
/*!***********************************!*\
  !*** ./src/shared/use-options.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useOptions: () => (/* binding */ useOptions)
/* harmony export */ });
/**
 * Returns the ActivityPub options object.
 *
 * @return {Object} The options object.
 */
function useOptions() {
  return window._activityPubOptions || {};
}

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/dom-ready":
/*!**********************************!*\
  !*** external ["wp","domReady"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["domReady"];

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
/*!*******************************!*\
  !*** ./src/reactions/view.js ***!
  \*******************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _reactions__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./reactions */ "./src/reactions/reactions.js");




_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_2___default()(() => {
  // iterate over a nodelist
  [].forEach.call(document.querySelectorAll('.activitypub-reactions-block'), element => {
    const attrs = JSON.parse(element.dataset.attrs);
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createRoot)(element).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_reactions__WEBPACK_IMPORTED_MODULE_3__.Reactions, {
      ...attrs
    }));
  });
});
})();

/******/ })()
;
//# sourceMappingURL=view.js.map