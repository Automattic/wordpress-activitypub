/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./src/followers/followers.js":
/*!************************************!*\
  !*** ./src/followers/followers.js ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Followers: () => (/* binding */ Followers)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _pagination__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./pagination */ "./src/followers/pagination.js");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _shared_use_options__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../shared/use-options */ "./src/shared/use-options.js");









function getPath(userId, per_page, order, page) {
  const {
    namespace
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_7__.useOptions)();
  const path = `/${namespace}/actors/${userId}/followers`;
  const args = {
    per_page,
    order,
    page,
    context: 'full'
  };
  return (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_2__.addQueryArgs)(path, args);
}
function usePage() {
  const [page, setPage] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(1);
  return [page, setPage];
}
function Followers({
  selectedUser,
  per_page,
  order,
  title,
  page: passedPage,
  setPage: passedSetPage,
  className = '',
  followLinks = true,
  followerData = false
}) {
  const userId = selectedUser === 'site' ? 0 : selectedUser;
  const [followers, setFollowers] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [pages, setPages] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  const [total, setTotal] = (0,react__WEBPACK_IMPORTED_MODULE_0__.useState)(0);
  const [localPage, setLocalPage] = usePage();
  const page = passedPage || localPage;
  const setPage = passedSetPage || setLocalPage;
  const prevLabel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.createInterpolateElement)(/* translators: arrow for previous followers link */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('<span>←</span> Less', 'activitypub'), {
    span: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "wp-block-query-pagination-previous-arrow is-arrow-arrow",
      "aria-hidden": "true"
    })
  });
  const nextLabel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.createInterpolateElement)(/* translators: arrow for next followers link */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('More <span>→</span>', 'activitypub'), {
    span: (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
      className: "wp-block-query-pagination-next-arrow is-arrow-arrow",
      "aria-hidden": "true"
    })
  });
  const setData = (followers, total) => {
    setFollowers(followers);
    setTotal(total);
    setPages(Math.ceil(total / per_page));
  };
  (0,react__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (followerData && page === 1) {
      return setData(followerData.followers, followerData.total);
    }
    const path = getPath(userId, per_page, order, page);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_1___default()({
      path
    }).then(data => setData(data.orderedItems, data.totalItems)).catch(() => {});
  }, [userId, per_page, order, page, followerData]);
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "activitypub-follower-block " + className
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("h3", null, title), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("ul", null, followers && followers.map(follower => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("li", {
    key: follower.url
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(Follower, {
    ...follower,
    followLinks: followLinks
  })))), pages > 1 && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination__WEBPACK_IMPORTED_MODULE_5__.Pagination, {
    page: page,
    perPage: per_page,
    total: total,
    pageClick: setPage,
    nextLabel: nextLabel,
    prevLabel: prevLabel,
    compact: className === 'is-style-compact'
  }));
}
function Follower({
  name,
  icon,
  url,
  preferredUsername,
  followLinks = true
}) {
  const handle = `@${preferredUsername}`;
  const extraProps = {};
  if (!followLinks) {
    extraProps.onClick = event => event.preventDefault();
  }
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_6__.ExternalLink, {
    className: "activitypub-link",
    href: url,
    title: handle,
    ...extraProps
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("img", {
    width: "40",
    height: "40",
    src: icon.url,
    className: "avatar activitypub-avatar",
    alt: name
  }), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "activitypub-actor"
  }, (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("strong", {
    className: "activitypub-name"
  }, name), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "sep"
  }, "/"), (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("span", {
    className: "activitypub-handle"
  }, handle)));
}

/***/ }),

/***/ "./src/followers/pagination-page.js":
/*!******************************************!*\
  !*** ./src/followers/pagination-page.js ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PaginationPage: () => (/* binding */ PaginationPage)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! classnames */ "./node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_1__);


function PaginationPage({
  active,
  children,
  page,
  pageClick,
  className
}) {
  const handleClick = event => {
    event.preventDefault();
    !active && pageClick(page);
  };
  const classes = classnames__WEBPACK_IMPORTED_MODULE_1___default()('wp-block activitypub-pager', className, {
    'current': active
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
    className: classes,
    onClick: handleClick
  }, children);
}

/***/ }),

/***/ "./src/followers/pagination.js":
/*!*************************************!*\
  !*** ./src/followers/pagination.js ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Pagination: () => (/* binding */ Pagination)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! classnames */ "./node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _pagination_page__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./pagination-page */ "./src/followers/pagination-page.js");

// Adapted from: https://github.com/Automattic/wp-calypso/tree/trunk/client/components/pagination
// Markup adapted to imitate the core query-pagination component so we can inherit those styles.


const PaginationVariant = {
  outlined: 'outlined',
  minimal: 'minimal'
};
function Pagination({
  compact,
  nextLabel,
  page,
  pageClick,
  perPage,
  prevLabel,
  total,
  variant = PaginationVariant.outlined
}) {
  const getPageList = (page, pageCount) => {
    let pageList = [1, page - 2, page - 1, page, page + 1, page + 2, pageCount];
    pageList.sort((a, b) => a - b);

    // Remove pages less than 1, or greater than total number of pages, and remove duplicates
    pageList = pageList.filter((pageNumber, index, originalPageList) => {
      return pageNumber >= 1 && pageNumber <= pageCount && originalPageList.lastIndexOf(pageNumber) === index;
    });
    for (let i = pageList.length - 2; i >= 0; i--) {
      if (pageList[i] === pageList[i + 1]) {
        pageList.splice(i + 1, 1);
      }
    }
    return pageList;
  };
  const pageList = getPageList(page, Math.ceil(total / perPage));
  const className = classnames__WEBPACK_IMPORTED_MODULE_1___default()('alignwide wp-block-query-pagination is-content-justification-space-between is-layout-flex wp-block-query-pagination-is-layout-flex', `is-${variant}`, {
    'is-compact': compact
  });
  return (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("nav", {
    className: className
  }, prevLabel && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination_page__WEBPACK_IMPORTED_MODULE_2__.PaginationPage, {
    key: "prev",
    page: page - 1,
    pageClick: pageClick,
    active: page === 1,
    "aria-label": prevLabel,
    className: "wp-block-query-pagination-previous block-editor-block-list__block"
  }, prevLabel), !compact && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "block-editor-block-list__block wp-block wp-block-query-pagination-numbers"
  }, pageList.map(pageNumber => (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination_page__WEBPACK_IMPORTED_MODULE_2__.PaginationPage, {
    key: pageNumber,
    page: pageNumber,
    pageClick: pageClick,
    active: pageNumber === page,
    className: "page-numbers"
  }, pageNumber))), nextLabel && (0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination_page__WEBPACK_IMPORTED_MODULE_2__.PaginationPage, {
    key: "next",
    page: page + 1,
    pageClick: pageClick,
    active: page === Math.ceil(total / perPage),
    "aria-label": nextLabel,
    className: "wp-block-query-pagination-next block-editor-block-list__block"
  }, nextLabel));
}

/***/ }),

/***/ "./src/followers/view.js":
/*!*******************************!*\
  !*** ./src/followers/view.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/followers/style.scss");
/* harmony import */ var _followers__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./followers */ "./src/followers/followers.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/dom-ready */ "@wordpress/dom-ready");
/* harmony import */ var _wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_4__);





_wordpress_dom_ready__WEBPACK_IMPORTED_MODULE_4___default()(() => {
  // iterate over a nodelist
  [].forEach.call(document.querySelectorAll('.activitypub-follower-block'), element => {
    const attrs = JSON.parse(element.dataset.attrs);
    (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.createRoot)(element).render((0,react__WEBPACK_IMPORTED_MODULE_0__.createElement)(_followers__WEBPACK_IMPORTED_MODULE_2__.Followers, {
      ...attrs
    }));
  });
});

/***/ }),

/***/ "./src/shared/use-options.js":
/*!***********************************!*\
  !*** ./src/shared/use-options.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
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

/***/ "./src/followers/style.scss":
/*!**********************************!*\
  !*** ./src/followers/style.scss ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

"use strict";
module.exports = window["React"];

/***/ }),

/***/ "@wordpress/api-fetch":
/*!**********************************!*\
  !*** external ["wp","apiFetch"] ***!
  \**********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["apiFetch"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/dom-ready":
/*!**********************************!*\
  !*** external ["wp","domReady"] ***!
  \**********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["domReady"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/url":
/*!*****************************!*\
  !*** external ["wp","url"] ***!
  \*****************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["url"];

/***/ }),

/***/ "./node_modules/classnames/index.js":
/*!******************************************!*\
  !*** ./node_modules/classnames/index.js ***!
  \******************************************/
/***/ ((module, exports) => {

var __WEBPACK_AMD_DEFINE_ARRAY__, __WEBPACK_AMD_DEFINE_RESULT__;/*!
	Copyright (c) 2018 Jed Watson.
	Licensed under the MIT License (MIT), see
	http://jedwatson.github.io/classnames
*/
/* global define */

(function () {
	'use strict';

	var hasOwn = {}.hasOwnProperty;

	function classNames () {
		var classes = '';

		for (var i = 0; i < arguments.length; i++) {
			var arg = arguments[i];
			if (arg) {
				classes = appendClass(classes, parseValue(arg));
			}
		}

		return classes;
	}

	function parseValue (arg) {
		if (typeof arg === 'string' || typeof arg === 'number') {
			return arg;
		}

		if (typeof arg !== 'object') {
			return '';
		}

		if (Array.isArray(arg)) {
			return classNames.apply(null, arg);
		}

		if (arg.toString !== Object.prototype.toString && !arg.toString.toString().includes('[native code]')) {
			return arg.toString();
		}

		var classes = '';

		for (var key in arg) {
			if (hasOwn.call(arg, key) && arg[key]) {
				classes = appendClass(classes, key);
			}
		}

		return classes;
	}

	function appendClass (value, newClass) {
		if (!newClass) {
			return value;
		}
	
		if (value) {
			return value + ' ' + newClass;
		}
	
		return value + newClass;
	}

	if ( true && module.exports) {
		classNames.default = classNames;
		module.exports = classNames;
	} else if (true) {
		// register as 'classnames', consistent with npm package name
		!(__WEBPACK_AMD_DEFINE_ARRAY__ = [], __WEBPACK_AMD_DEFINE_RESULT__ = (function () {
			return classNames;
		}).apply(exports, __WEBPACK_AMD_DEFINE_ARRAY__),
		__WEBPACK_AMD_DEFINE_RESULT__ !== undefined && (module.exports = __WEBPACK_AMD_DEFINE_RESULT__));
	} else {}
}());


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
/******/ 	// expose the modules object (__webpack_modules__)
/******/ 	__webpack_require__.m = __webpack_modules__;
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/chunk loaded */
/******/ 	(() => {
/******/ 		var deferred = [];
/******/ 		__webpack_require__.O = (result, chunkIds, fn, priority) => {
/******/ 			if(chunkIds) {
/******/ 				priority = priority || 0;
/******/ 				for(var i = deferred.length; i > 0 && deferred[i - 1][2] > priority; i--) deferred[i] = deferred[i - 1];
/******/ 				deferred[i] = [chunkIds, fn, priority];
/******/ 				return;
/******/ 			}
/******/ 			var notFulfilled = Infinity;
/******/ 			for (var i = 0; i < deferred.length; i++) {
/******/ 				var chunkIds = deferred[i][0];
/******/ 				var fn = deferred[i][1];
/******/ 				var priority = deferred[i][2];
/******/ 				var fulfilled = true;
/******/ 				for (var j = 0; j < chunkIds.length; j++) {
/******/ 					if ((priority & 1 === 0 || notFulfilled >= priority) && Object.keys(__webpack_require__.O).every((key) => (__webpack_require__.O[key](chunkIds[j])))) {
/******/ 						chunkIds.splice(j--, 1);
/******/ 					} else {
/******/ 						fulfilled = false;
/******/ 						if(priority < notFulfilled) notFulfilled = priority;
/******/ 					}
/******/ 				}
/******/ 				if(fulfilled) {
/******/ 					deferred.splice(i--, 1)
/******/ 					var r = fn();
/******/ 					if (r !== undefined) result = r;
/******/ 				}
/******/ 			}
/******/ 			return result;
/******/ 		};
/******/ 	})();
/******/ 	
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
/******/ 	/* webpack/runtime/jsonp chunk loading */
/******/ 	(() => {
/******/ 		// no baseURI
/******/ 		
/******/ 		// object to store loaded and loading chunks
/******/ 		// undefined = chunk not loaded, null = chunk preloaded/prefetched
/******/ 		// [resolve, reject, Promise] = chunk loading, 0 = chunk loaded
/******/ 		var installedChunks = {
/******/ 			"followers/view": 0,
/******/ 			"followers/style-view": 0
/******/ 		};
/******/ 		
/******/ 		// no chunk on demand loading
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
/******/ 		
/******/ 		// no HMR
/******/ 		
/******/ 		// no HMR manifest
/******/ 		
/******/ 		__webpack_require__.O.j = (chunkId) => (installedChunks[chunkId] === 0);
/******/ 		
/******/ 		// install a JSONP callback for chunk loading
/******/ 		var webpackJsonpCallback = (parentChunkLoadingFunction, data) => {
/******/ 			var chunkIds = data[0];
/******/ 			var moreModules = data[1];
/******/ 			var runtime = data[2];
/******/ 			// add "moreModules" to the modules object,
/******/ 			// then flag all "chunkIds" as loaded and fire callback
/******/ 			var moduleId, chunkId, i = 0;
/******/ 			if(chunkIds.some((id) => (installedChunks[id] !== 0))) {
/******/ 				for(moduleId in moreModules) {
/******/ 					if(__webpack_require__.o(moreModules, moduleId)) {
/******/ 						__webpack_require__.m[moduleId] = moreModules[moduleId];
/******/ 					}
/******/ 				}
/******/ 				if(runtime) var result = runtime(__webpack_require__);
/******/ 			}
/******/ 			if(parentChunkLoadingFunction) parentChunkLoadingFunction(data);
/******/ 			for(;i < chunkIds.length; i++) {
/******/ 				chunkId = chunkIds[i];
/******/ 				if(__webpack_require__.o(installedChunks, chunkId) && installedChunks[chunkId]) {
/******/ 					installedChunks[chunkId][0]();
/******/ 				}
/******/ 				installedChunks[chunkId] = 0;
/******/ 			}
/******/ 			return __webpack_require__.O(result);
/******/ 		}
/******/ 		
/******/ 		var chunkLoadingGlobal = self["webpackChunkwordpress_activitypub"] = self["webpackChunkwordpress_activitypub"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["followers/style-view"], () => (__webpack_require__("./src/followers/view.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=view.js.map