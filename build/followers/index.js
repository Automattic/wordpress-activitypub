/******/ (() => { // webpackBootstrap
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/library/people.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/people.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__);


/**
 * WordPress dependencies
 */

const people = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.SVG, {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 24 24"
}, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_1__.Path, {
  d: "M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",
  fillRule: "evenodd"
}));
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (people);
//# sourceMappingURL=people.js.map

/***/ }),

/***/ "./src/followers/edit.js":
/*!*******************************!*\
  !*** ./src/followers/edit.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/extends */ "./node_modules/@babel/runtime/helpers/esm/extends.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _followers__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./followers */ "./src/followers/followers.js");
/* harmony import */ var _shared_use_user_options__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../shared/use-user-options */ "./src/shared/use-user-options.js");








function Edit(_ref) {
  let {
    attributes,
    setAttributes
  } = _ref;
  const {
    order,
    per_page,
    selectedUser,
    title
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.useBlockProps)();
  const [page, setPage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useState)(1);
  const orderOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('New to old', 'activitypub'),
    value: 'desc'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Old to new', 'activitypub'),
    value: 'asc'
  }];
  const usersOptions = (0,_shared_use_user_options__WEBPACK_IMPORTED_MODULE_6__.useUserOptions)();
  const setAttributestAndResetPage = key => {
    return value => {
      setPage(1);
      setAttributes({
        [key]: value
      });
    };
  };
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("div", blockProps, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_3__.InspectorControls, {
    key: "setting"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
    title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Followers Options', 'activitypub')
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.TextControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Title', 'activitypub'),
    help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Title to display above the list of followers. Blank for none.', 'activitypub'),
    value: title,
    onChange: value => setAttributes({
      title: value
    })
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Select User', 'activitypub'),
    value: selectedUser,
    options: usersOptions,
    onChange: setAttributestAndResetPage('selectedUser')
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Sort', 'activitypub'),
    value: order,
    options: orderOptions,
    onChange: setAttributestAndResetPage('order')
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Number of Followers', 'activitypub'),
    value: per_page,
    onChange: setAttributestAndResetPage('per_page'),
    min: 1,
    max: 10
  }))), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_followers__WEBPACK_IMPORTED_MODULE_5__.Followers, (0,_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__["default"])({}, attributes, {
    page: page,
    setPage: setPage,
    followLinks: false
  })));
}

/***/ }),

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
/* harmony import */ var _babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @babel/runtime/helpers/extends */ "./node_modules/@babel/runtime/helpers/esm/extends.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _pagination__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./pagination */ "./src/followers/pagination.js");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_7__);









const {
  namespace
} = window._activityPubOptions;
function getPath(userId, per_page, order, page) {
  const path = `/${namespace}/users/${userId}/followers`;
  const args = {
    per_page,
    order,
    page,
    context: 'full'
  };
  return (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_4__.addQueryArgs)(path, args);
}
function usePage() {
  const [page, setPage] = (0,react__WEBPACK_IMPORTED_MODULE_2__.useState)(1);
  return [page, setPage];
}
function Followers(_ref) {
  let {
    selectedUser,
    per_page,
    order,
    title,
    page: passedPage,
    setPage: passedSetPage,
    className = '',
    followLinks = true
  } = _ref;
  const userId = selectedUser === 'site' ? 0 : selectedUser;
  const [followers, setFollowers] = (0,react__WEBPACK_IMPORTED_MODULE_2__.useState)([]);
  const [pages, setPages] = (0,react__WEBPACK_IMPORTED_MODULE_2__.useState)(0);
  const [total, setTotal] = (0,react__WEBPACK_IMPORTED_MODULE_2__.useState)(0);
  const [localPage, setLocalPage] = usePage();
  const page = passedPage || localPage;
  const setPage = passedSetPage || setLocalPage;
  const prevLabel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createInterpolateElement)( /* translators: arrow for previous followers link */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('<span>←</span> Less', 'activitypub'), {
    span: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("span", {
      class: "wp-block-query-pagination-previous-arrow is-arrow-arrow",
      "aria-hidden": "true"
    })
  });
  const nextLabel = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createInterpolateElement)( /* translators: arrow for next followers link */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('More <span>→</span>', 'activitypub'), {
    span: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("span", {
      class: "wp-block-query-pagination-next-arrow is-arrow-arrow",
      "aria-hidden": "true"
    })
  });
  (0,react__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    const path = getPath(userId, per_page, order, page);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path
    }).then(data => {
      setPages(Math.ceil(data.totalItems / per_page));
      setTotal(data.totalItems);
      setFollowers(data.orderedItems);
    }).catch(error => console.error(error));
  }, [userId, per_page, order, page]);
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("div", {
    className: "activitypub-follower-block " + className
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("h3", null, title), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("ul", null, followers && followers.map(follower => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("li", {
    key: follower.url
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(Follower, (0,_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__["default"])({}, follower, {
    followLinks: followLinks
  }))))), pages > 1 && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_pagination__WEBPACK_IMPORTED_MODULE_6__.Pagination, {
    page: page,
    perPage: per_page,
    total: total,
    pageClick: setPage,
    nextLabel: nextLabel,
    prevLabel: prevLabel,
    compact: className === 'is-style-compact'
  }));
}
function Follower(_ref2) {
  let {
    name,
    icon,
    url,
    preferredUsername,
    followLinks = true
  } = _ref2;
  const handle = `@${preferredUsername}`;
  const extraProps = {};
  if (!followLinks) {
    extraProps.onClick = event => event.preventDefault();
  }
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)(_wordpress_components__WEBPACK_IMPORTED_MODULE_7__.ExternalLink, (0,_babel_runtime_helpers_extends__WEBPACK_IMPORTED_MODULE_0__["default"])({
    className: "activitypub-link",
    href: url,
    title: handle
  }, extraProps), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("img", {
    width: "40",
    height: "40",
    src: icon.url,
    class: "avatar activitypub-avatar"
  }), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("span", {
    class: "activitypub-actor"
  }, (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("strong", {
    className: "activitypub-name"
  }, name), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("span", {
    class: "sep"
  }, "/"), (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createElement)("span", {
    class: "activitypub-handle"
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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! classnames */ "./node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_1__);


function PaginationPage(_ref) {
  let {
    active,
    children,
    page,
    pageClick,
    className
  } = _ref;
  const handleClick = event => {
    event.preventDefault();
    !active && pageClick(page);
  };
  const classes = classnames__WEBPACK_IMPORTED_MODULE_1___default()('wp-block activitypub-pager', className, {
    'current': active
  });
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("a", {
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
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! classnames */ "./node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _pagination_page__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./pagination-page */ "./src/followers/pagination-page.js");

// Adapted from: https://github.com/Automattic/wp-calypso/tree/trunk/client/components/pagination
// Markup adapted to imitate the core query-pagination component so we can inherit those styles.


const PaginationVariant = {
  outlined: 'outlined',
  minimal: 'minimal'
};
function Pagination(_ref) {
  let {
    compact,
    nextLabel,
    page,
    pageClick,
    perPage,
    prevLabel,
    total,
    variant = PaginationVariant.outlined
  } = _ref;
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
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("nav", {
    className: className
  }, prevLabel && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination_page__WEBPACK_IMPORTED_MODULE_2__.PaginationPage, {
    key: "prev",
    page: page - 1,
    pageClick: pageClick,
    active: page === 1,
    "aria-label": prevLabel,
    className: "wp-block-query-pagination-previous block-editor-block-list__block"
  }, prevLabel), !compact && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)("div", {
    className: "block-editor-block-list__block wp-block wp-block-query-pagination-numbers"
  }, pageList.map(pageNumber => (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination_page__WEBPACK_IMPORTED_MODULE_2__.PaginationPage, {
    key: pageNumber,
    page: pageNumber,
    pageClick: pageClick,
    active: pageNumber === page,
    className: "page-numbers"
  }, pageNumber))), nextLabel && (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createElement)(_pagination_page__WEBPACK_IMPORTED_MODULE_2__.PaginationPage, {
    key: "next",
    page: page + 1,
    pageClick: pageClick,
    active: page === Math.ceil(total / perPage),
    "aria-label": nextLabel,
    className: "wp-block-query-pagination-next block-editor-block-list__block"
  }, nextLabel));
}

/***/ }),

/***/ "./src/shared/use-user-options.js":
/*!****************************************!*\
  !*** ./src/shared/use-user-options.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useUserOptions: () => (/* binding */ useUserOptions)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);



const enabled = window._activityPubOptions?.enabled;
function useUserOptions() {
  const users = enabled?.users ? (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => select('core').getUsers({
    who: 'authors'
  })) : [];
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => {
    if (!users) {
      return [];
    }
    const withBlogUser = enabled?.site ? [{
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Whole Site', 'activitypub'),
      value: 'site'
    }] : [];
    return users.reduce((acc, user) => {
      acc.push({
        label: user.name,
        value: user.id
      });
      return acc;
    }, withBlogUser);
  }, [users]);
}

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
	var nativeCodeString = '[native code]';

	function classNames() {
		var classes = [];

		for (var i = 0; i < arguments.length; i++) {
			var arg = arguments[i];
			if (!arg) continue;

			var argType = typeof arg;

			if (argType === 'string' || argType === 'number') {
				classes.push(arg);
			} else if (Array.isArray(arg)) {
				if (arg.length) {
					var inner = classNames.apply(null, arg);
					if (inner) {
						classes.push(inner);
					}
				}
			} else if (argType === 'object') {
				if (arg.toString !== Object.prototype.toString && !arg.toString.toString().includes('[native code]')) {
					classes.push(arg.toString());
					continue;
				}

				for (var key in arg) {
					if (hasOwn.call(arg, key) && arg[key]) {
						classes.push(key);
					}
				}
			}
		}

		return classes.join(' ');
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

/***/ "@wordpress/block-editor":
/*!*************************************!*\
  !*** external ["wp","blockEditor"] ***!
  \*************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["blockEditor"];

/***/ }),

/***/ "@wordpress/blocks":
/*!********************************!*\
  !*** external ["wp","blocks"] ***!
  \********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["blocks"];

/***/ }),

/***/ "@wordpress/components":
/*!************************************!*\
  !*** external ["wp","components"] ***!
  \************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["components"];

/***/ }),

/***/ "@wordpress/data":
/*!******************************!*\
  !*** external ["wp","data"] ***!
  \******************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["data"];

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

/***/ "@wordpress/primitives":
/*!************************************!*\
  !*** external ["wp","primitives"] ***!
  \************************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["primitives"];

/***/ }),

/***/ "@wordpress/url":
/*!*****************************!*\
  !*** external ["wp","url"] ***!
  \*****************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["url"];

/***/ }),

/***/ "./node_modules/@babel/runtime/helpers/esm/extends.js":
/*!************************************************************!*\
  !*** ./node_modules/@babel/runtime/helpers/esm/extends.js ***!
  \************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ _extends)
/* harmony export */ });
function _extends() {
  _extends = Object.assign ? Object.assign.bind() : function (target) {
    for (var i = 1; i < arguments.length; i++) {
      var source = arguments[i];
      for (var key in source) {
        if (Object.prototype.hasOwnProperty.call(source, key)) {
          target[key] = source[key];
        }
      }
    }
    return target;
  };
  return _extends.apply(this, arguments);
}

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
// This entry need to be wrapped in an IIFE because it need to be in strict mode.
(() => {
"use strict";
/*!********************************!*\
  !*** ./src/followers/index.js ***!
  \********************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/people.js");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./edit */ "./src/followers/edit.js");



const save = () => null;
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)('activitypub/followers', {
  edit: _edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save,
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"]
});
})();

/******/ })()
;
//# sourceMappingURL=index.js.map