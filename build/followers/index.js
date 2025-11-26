/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./node_modules/@wordpress/icons/build-module/library/people.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@wordpress/icons/build-module/library/people.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ people_default)
/* harmony export */ });
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/primitives */ "@wordpress/primitives");
/* harmony import */ var _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
// packages/icons/src/library/people.tsx


var people_default = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(_wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.SVG, { xmlns: "http://www.w3.org/2000/svg", viewBox: "0 0 24 24", children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(
  _wordpress_primitives__WEBPACK_IMPORTED_MODULE_0__.Path,
  {
    d: "M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z",
    fillRule: "evenodd"
  }
) });

//# sourceMappingURL=people.js.map


/***/ }),

/***/ "./src/followers/block.json":
/*!**********************************!*\
  !*** ./src/followers/block.json ***!
  \**********************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","name":"activitypub/followers","apiVersion":3,"version":"2.0.1","title":"Fediverse Followers","category":"widgets","description":"Display your followers from the Fediverse on your website.","textdomain":"activitypub","icon":"groups","supports":{"html":false,"interactivity":true},"attributes":{"selectedUser":{"type":"string","default":"blog"},"per_page":{"type":"number","default":10},"order":{"type":"string","default":"desc","enum":["asc","desc"]}},"usesContext":["postType","postId"],"styles":[{"name":"default","label":"Default","isDefault":true},{"name":"card","label":"Card"},{"name":"compact","label":"Compact"}],"editorScript":"file:./index.js","editorStyle":"file:./index.css","viewScriptModule":"file:./view.js","viewScript":"wp-api-fetch","style":["file:./style-index.css"],"render":"file:./render.php"}');

/***/ }),

/***/ "./src/followers/deprecations.js":
/*!***************************************!*\
  !*** ./src/followers/deprecations.js ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);


/**
 * Deprecation for the followers block to handle the migration from custom title to InnerBlocks.
 */
const v1 = {
  attributes: {
    title: {
      type: 'string',
      default: 'Fediverse Followers'
    },
    selectedUser: {
      type: 'string',
      default: 'blog'
    },
    per_page: {
      type: 'number',
      default: 10
    },
    order: {
      type: 'string',
      default: 'desc',
      enum: ['asc', 'desc']
    }
  },
  supports: {
    html: false
  },
  /**
   * Checks if the block is eligible for migration.
   *
   * @param {Object} attributes The block attributes.
   *
   * @return {boolean} Whether the block is eligible for migration.
   */
  isEligible({
    title
  }) {
    return !!title;
  },
  /**
   * Migrates the block to use a core heading block instead of the custom heading attribute.
   *
   * @param {Object} attributes The attributes for the block.
   *
   * @return {[Object, Array]} An array with the new block attributes and inner blocks.
   */
  migrate: ({
    title,
    ...newAttributes
  }) => {
    const headingBlock = (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.createBlock)('core/heading', {
      content: title,
      level: 3
    });
    return [newAttributes, [headingBlock]];
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ([v1]);

/***/ }),

/***/ "./src/followers/edit.js":
/*!*******************************!*\
  !*** ./src/followers/edit.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__);
/* harmony import */ var _shared_use_options__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../shared/use-options */ "./src/shared/use-options.js");
/* harmony import */ var _shared_use_user_options__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../shared/use-user-options */ "./src/shared/use-user-options.js");
/* harmony import */ var _shared_inherit_block_fallback__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../shared/inherit-block-fallback */ "./src/shared/inherit-block-fallback.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__);












/**
 * Edit component.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @param {Object} props.context Block context.
 * @param {string} props.context.postType Post type.
 * @param {number} props.context.postId Post ID.
 *
 * @return {JSX.Element} Edit component.
 */

function Edit({
  attributes,
  setAttributes,
  context: {
    postType,
    postId
  }
}) {
  const {
    className = '',
    order,
    per_page,
    selectedUser
  } = attributes;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.useBlockProps)();
  const [page, setPage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useState)(1);
  const orderOptions = [{
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('New to old', 'activitypub'),
    value: 'desc'
  }, {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Old to new', 'activitypub'),
    value: 'asc'
  }];
  const usersOptions = (0,_shared_use_user_options__WEBPACK_IMPORTED_MODULE_9__.useUserOptions)({
    withInherit: true
  });
  const setAttributeWithPageReset = key => value => {
    setPage(1);
    setAttributes({
      [key]: value
    });
  };
  const authorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => {
    const {
      getEditedEntityRecord
    } = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__.store);
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return _authorId !== null && _authorId !== void 0 ? _authorId : null;
  }, [postType, postId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useEffect)(() => {
    // if there are no users yet, do nothing
    if (!usersOptions.length) {
      return;
    }
    // ensure that the selected user is in the list of options, if not, select the first available user
    if (!usersOptions.find(({
      value
    }) => value === selectedUser)) {
      setAttributes({
        selectedUser: usersOptions[0].value
      });
    }
  }, [selectedUser, usersOptions]);

  // Template for InnerBlocks - allows only a heading block.
  const TEMPLATE = [['core/heading', {
    level: 3,
    placeholder: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Fediverse Followers', 'activitypub'),
    content: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Fediverse Followers', 'activitypub')
  }]];
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InspectorControls, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Followers Options', 'activitypub'),
        children: [usersOptions.length > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Select User', 'activitypub'),
          value: selectedUser,
          options: usersOptions,
          onChange: setAttributeWithPageReset('selectedUser'),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Sort', 'activitypub'),
          value: order,
          options: orderOptions,
          onChange: setAttributeWithPageReset('order'),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.RangeControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Number of Followers', 'activitypub'),
          value: per_page,
          onChange: setAttributeWithPageReset('per_page'),
          min: 1,
          max: 10,
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        })]
      })
    }, "setting"), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
      className: 'wp-block-activitypub-followers ' + className,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_2__.InnerBlocks, {
        template: TEMPLATE,
        allowedBlocks: ['core/heading'],
        templateLock: 'all',
        renderAppender: false
      }), selectedUser === 'inherit' ? authorId ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(Followers, {
        ...attributes,
        page: page,
        setPage: setPage,
        selectedUser: authorId
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_shared_inherit_block_fallback__WEBPACK_IMPORTED_MODULE_10__.InheritModeBlockFallback, {
        name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Followers', 'activitypub')
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(Followers, {
        ...attributes,
        page: page,
        setPage: setPage
      })]
    })]
  });
}

/**
 * Builds the API path for fetching followers.
 *
 * @param {number} userId - The ID of the user whose followers are being fetched.
 * @param {number} per_page - The number of followers to fetch per page.
 * @param {string} order - The order in which to fetch followers ('asc' or 'desc').
 * @param {number} page - The page number to fetch.
 * @return {string} The API path with query arguments for fetching followers.
 */
function getPath(userId, per_page, order, page) {
  const {
    namespace
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_8__.useOptions)();
  const path = `/${namespace}/actors/${userId}/followers`;
  const args = {
    per_page,
    order,
    page,
    context: 'full'
  };
  return (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_6__.addQueryArgs)(path, args);
}

/**
 * Component to display followers of a user.
 *
 * @param {Object} props - The component props.
 * @param {String} props.selectedUser - The ID of the user whose followers are being fetched.
 * @param {number} props.per_page - The number of followers to fetch per page.
 * @param {string} props.order - The order in which to fetch followers ('asc' or 'desc').
 * @param {number} props.page - The page number to fetch.
 * @param {function} props.setPage - The function to set the page number.
 * @param {Object} props.followerData - Optional pre-fetched follower data.
 */
function Followers({
  selectedUser,
  per_page,
  order,
  page: passedPage,
  setPage: passedSetPage,
  followerData = false
}) {
  const userId = selectedUser === 'blog' ? 0 : selectedUser;
  const [followers, setFollowers] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useState)([]);
  const [pages, setPages] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useState)(0);
  const [total, setTotal] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useState)(0);
  const [localPage, setLocalPage] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useState)(1);
  const page = passedPage || localPage;
  const setPage = passedSetPage || setLocalPage;
  const setData = (followers, total) => {
    setFollowers(followers);
    setTotal(total);
    setPages(Math.ceil(total / per_page));
  };
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_5__.useEffect)(() => {
    if (followerData && page === 1) {
      return setData(followerData.followers, followerData.total);
    }
    const path = getPath(userId, per_page, order, page);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
      path
    }).then(({
      orderedItems,
      totalItems
    }) => setData(orderedItems, totalItems)).catch(() => setData([], 0));
  }, [userId, per_page, order, page, followerData]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
    className: "followers-container",
    children: [followers.length ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("ul", {
      className: "followers-list",
      children: followers.map(follower => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("li", {
        className: "follower-item",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(Follower, {
          ...follower
        })
      }, follower.url))
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("p", {
      className: "followers-placeholder",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('No followers found.', 'activitypub')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(Pagination, {
      page: page,
      pages: pages,
      setPage: setPage
    })]
  });
}

/**
 * Component to display pagination navigation.
 *
 * @param {Object} props - The component props.
 * @param {number} props.page - The current page number.
 * @param {number} props.pages - The total number of pages.
 * @param {function} props.setPage - The function to set the page number.
 */
function Pagination({
  page,
  pages,
  setPage
}) {
  if (pages <= 1) {
    return null;
  }
  const disablePreviousLink = page <= 1;
  const disableNextLink = page >= pages;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("nav", {
    className: "followers-pagination",
    role: "navigation",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("h1", {
      className: "screen-reader-text",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Follower navigation', 'activitypub')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("a", {
      className: "pagination-previous",
      "aria-disabled": disablePreviousLink,
      "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Previous page', 'activitypub'),
      onClick: event => {
        event.preventDefault();
        setPage(page - 1);
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Previous', 'activitypub')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("div", {
      className: "pagination-info",
      children: `${page} / ${pages}`
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("a", {
      className: "pagination-next",
      "aria-disabled": disableNextLink,
      "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Next page', 'activitypub'),
      onClick: event => {
        event.preventDefault();
        setPage(page + 1);
      },
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_7__.__)('Next', 'activitypub')
    })]
  });
}

/**
 * Component to display a single follower.
 *
 * @param {Object} props - The component props.
 * @param {string} props.name - The name of the follower.
 * @param {Object} props.icon - The icon of the follower.
 * @param {string} props.url - The URL of the follower.
 * @param {string} props.preferredUsername - The preferred username of the follower.
 */
function Follower({
  name,
  icon,
  url,
  preferredUsername
}) {
  const handle = `@${preferredUsername}`;
  const {
    defaultAvatarUrl
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_8__.useOptions)();
  const avatar = icon?.url || defaultAvatarUrl;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("a", {
    className: "follower-link",
    href: url,
    title: handle,
    onClick: event => event.preventDefault(),
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("img", {
      width: "48",
      height: "48",
      src: avatar,
      className: "follower-avatar",
      alt: name,
      onError: event => {
        event.target.src = defaultAvatarUrl;
      }
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)("div", {
      className: "follower-info",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
        className: "follower-name",
        children: name
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
        className: "follower-username",
        children: handle
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("svg", {
      xmlns: "http://www.w3.org/2000/svg",
      viewBox: "0 0 24 24",
      width: "24",
      height: "24",
      className: "external-link-icon",
      "aria-hidden": "true",
      focusable: "false",
      fill: "currentColor",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("path", {
        d: "M18.2 17c0 .7-.6 1.2-1.2 1.2H7c-.7 0-1.2-.6-1.2-1.2V7c0-.7.6-1.2 1.2-1.2h3.2V4.2H7C5.5 4.2 4.2 5.5 4.2 7v10c0 1.5 1.2 2.8 2.8 2.8h10c1.5 0 2.8-1.2 2.8-2.8v-3.6h-1.5V17zM14.9 3v1.5h3.7l-6.4 6.4 1.1 1.1 6.4-6.4v3.7h1.5V3h-6.3z"
      })
    })]
  });
}

/***/ }),

/***/ "./src/followers/index.js":
/*!********************************!*\
  !*** ./src/followers/index.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/people.js");
/* harmony import */ var _deprecations__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./deprecations */ "./src/followers/deprecations.js");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./src/followers/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./block.json */ "./src/followers/block.json");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./save */ "./src/followers/save.js");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "./src/followers/style.scss");







(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_4__, {
  deprecated: _deprecations__WEBPACK_IMPORTED_MODULE_2__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  save: _save__WEBPACK_IMPORTED_MODULE_5__["default"],
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__["default"]
});

/***/ }),

/***/ "./src/followers/save.js":
/*!*******************************!*\
  !*** ./src/followers/save.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ save)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


/**
 * Save function for the Followers block.
 *
 * @return {JSX.Element} Element to render.
 */

function save() {
  const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps.save();
  const innerBlocksProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useInnerBlocksProps.save(blockProps);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
    ...innerBlocksProps
  });
}

/***/ }),

/***/ "./src/followers/style.scss":
/*!**********************************!*\
  !*** ./src/followers/style.scss ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/shared/inherit-block-fallback.js":
/*!**********************************************!*\
  !*** ./src/shared/inherit-block-fallback.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   InheritModeBlockFallback: () => (/* binding */ InheritModeBlockFallback)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _use_options__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./use-options */ "./src/shared/use-options.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





/**
 * Block fallback component for inheriting user context in ActivityPub blocks.
 *
 * @param {Object} props
 * @param {string} props.name - Name of the block.
 * @returns {JSX.Element} Rendered fallback block.
 */

function InheritModeBlockFallback({
  name
}) {
  const {
    enabled
  } = (0,_use_options__WEBPACK_IMPORTED_MODULE_3__.useOptions)();
  const nonAuthorExtra = enabled?.blog ? '' : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('It will be empty in other non-author contexts.', 'activitypub');
  const text = (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(/* translators: %1$s: block name, %2$s: extra information for non-author context */
  (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('This <strong>%1$s</strong> block will adapt to the page it is on, displaying the user profile associated with a post author (in a loop) or a user archive. %2$s', 'activitypub'), name, nonAuthorExtra).trim();
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
      children: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.createInterpolateElement)(text, {
        strong: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("strong", {})
      })
    })
  });
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
 * React hook to return the ActivityPub options object from the global window.
 *
 * @returns {Object} The options object.
 */
function useOptions() {
  return window._activityPubOptions || {};
}

/***/ }),

/***/ "./src/shared/use-user-options.js":
/*!****************************************!*\
  !*** ./src/shared/use-user-options.js ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

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
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _use_options__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./use-options */ "./src/shared/use-options.js");






/**
 * React hook providing user options for ActivityPub blocks.
 *
 * @param {Object} params
 * @param {boolean} params.withInherit - Whether to include the inherit option.
 * @returns {Array} List of user option objects.
 */
function useUserOptions({
  withInherit = false
}) {
  /**
   * ActivityPub options.
   *
   * @type {Object}
   * @property {boolean} enabled.users - Whether users are enabled.
   * @property {boolean} enabled.blog - Whether the blog user is enabled.
   */
  const {
    enabled,
    namespace
  } = (0,_use_options__WEBPACK_IMPORTED_MODULE_4__.useOptions)();
  const [currentUserCanActivityPub, setCurrentUserCanActivityPub] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);
  const {
    fetchedUsers,
    isLoadingUsers
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    const {
      getUsers,
      getIsResolving
    } = select('core');
    return {
      fetchedUsers: enabled?.users ? getUsers({
        capabilities: 'activitypub'
      }) : null,
      isLoadingUsers: enabled?.users ? getIsResolving('getUsers', [{
        capabilities: 'activitypub'
      }]) : false
    };
  }, []);

  // Only fetch current user if fetchedUsers is empty and we're not still loading.
  const currentUser = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => fetchedUsers || isLoadingUsers ? null : select('core').getCurrentUser(), [fetchedUsers, isLoadingUsers]);

  // Test if current user has activitypub capability by trying to access their actor endpoint.
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    if (fetchedUsers || isLoadingUsers || !currentUser) {
      return;
    }
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_3___default()({
      path: `/${namespace}/actors/${currentUser.id}`,
      method: 'HEAD',
      headers: {
        Accept: 'application/activity+json'
      },
      parse: false
    }).then(() => setCurrentUserCanActivityPub(true)).catch(() => setCurrentUserCanActivityPub(false));
  }, [fetchedUsers, isLoadingUsers, currentUser]);
  const users = fetchedUsers || (currentUser && currentUserCanActivityPub ? [{
    id: currentUser.id,
    name: currentUser.name
  }] : []);

  /**
   * Memoized computation of user options for block settings.
   */
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => {
    if (!users.length) {
      return [];
    }
    const userKeywords = [];
    if (enabled?.blog && fetchedUsers) {
      userKeywords.push({
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Blog', 'activitypub'),
        value: 'blog'
      });
    }

    // Only show the inherit option when explicitly asked for and users are enabled.
    if (withInherit && enabled?.users && fetchedUsers) {
      userKeywords.push({
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Dynamic User', 'activitypub'),
        value: 'inherit'
      });
    }

    /**
     * Reduce users into keyword/value pairs for options.
     */
    return users.reduce((acc, user) => {
      acc.push({
        label: user.name,
        value: `${user.id}` // Casting to string because that's how Gutenberg stores the attribute.
      });
      return acc;
    }, userKeywords);
  }, [users]);
}

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

/***/ "@wordpress/core-data":
/*!**********************************!*\
  !*** external ["wp","coreData"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["coreData"];

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
/******/ 				var [chunkIds, fn, priority] = deferred[i];
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
/******/ 			"followers/index": 0,
/******/ 			"followers/style-index": 0
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
/******/ 			var [chunkIds, moreModules, runtime] = data;
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
/******/ 		var chunkLoadingGlobal = globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || [];
/******/ 		chunkLoadingGlobal.forEach(webpackJsonpCallback.bind(null, 0));
/******/ 		chunkLoadingGlobal.push = webpackJsonpCallback.bind(null, chunkLoadingGlobal.push.bind(chunkLoadingGlobal));
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["followers/style-index"], () => (__webpack_require__("./src/followers/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map