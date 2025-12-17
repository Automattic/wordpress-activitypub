"use strict";
(globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || []).push([["app/feed-content"],{

/***/ "./src/app/components/empty-state/index.tsx":
/*!**************************************************!*\
  !*** ./src/app/components/empty-state/index.tsx ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ EmptyState)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/views */ "@wordpress/views");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_views__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../store */ "./src/app/store/index.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Empty State component.
 *
 * Displays contextual messages when the feed has no posts.
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */


function EmptyState() {
  const activeActorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => select(_store__WEBPACK_IMPORTED_MODULE_5__.STORE_NAME).getActiveActorId(), []);
  const {
    view
  } = (0,_wordpress_views__WEBPACK_IMPORTED_MODULE_4__.useView)({
    kind: 'postType',
    name: 'ap_post',
    slug: 'feed'
  });

  // If search or filters are active, show simple "no results" message.
  if (view.search || view.filters && view.filters.length > 0) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No posts found.', 'activitypub')
    });
  }

  // Show prompt to follow more people with link to following page.
  const followingUrl = activeActorId === 0 ? (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_2__.addQueryArgs)('options-general.php', {
    page: 'activitypub',
    tab: 'following'
  }) : (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_2__.addQueryArgs)('users.php', {
    page: 'activitypub-following-list'
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("p", {
    children: (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.createInterpolateElement)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Your feed is waiting to come alive. <a>Follow more people on the Fediverse</a> to see their posts here.', 'activitypub'), {
      a: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("a", {
        href: followingUrl
      })
    })
  });
}

/***/ }),

/***/ "./src/app/components/fields/avatar/index.tsx":
/*!****************************************************!*\
  !*** ./src/app/components/fields/avatar/index.tsx ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   avatarField: () => (/* binding */ avatarField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _avatar__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../avatar */ "./src/app/components/avatar/index.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/fields/avatar/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Avatar field for DataViews.
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */

/**
 * Internal dependencies
 */



const avatarField = {
  id: 'avatar',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Avatar', 'activitypub'),
  type: 'media',
  enableHiding: false,
  enableSorting: false,
  getValue: ({
    item
  }) => item.actor_info?.icon || '',
  render: ({
    item
  }) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_avatar__WEBPACK_IMPORTED_MODULE_1__["default"], {
    item: item
  })
};

/***/ }),

/***/ "./src/app/components/fields/avatar/style.scss":
/*!*****************************************************!*\
  !*** ./src/app/components/fields/avatar/style.scss ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/fields/content/index.tsx":
/*!*****************************************************!*\
  !*** ./src/app/components/fields/content/index.tsx ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   contentField: () => (/* binding */ contentField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_dom__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/dom */ "@wordpress/dom");
/* harmony import */ var _wordpress_dom__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_dom__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _contexts_object_type_context__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../contexts/object-type-context */ "./src/app/contexts/object-type-context.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/fields/content/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */




/**
 * Internal dependencies
 */




/**
 * Smart content field that automatically chooses between excerpt and content
 * based on the post's ActivityPub object type.
 *
 * - Notes: Show full content (HTML)
 * - All other types (Articles, etc.): Show excerpt (plain text)
 */

const contentField = {
  id: 'content',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Content', 'activitypub'),
  enableHiding: false,
  enableSorting: false,
  getValue: ({
    item
  }) => {
    const text = item.excerpt?.rendered || item.content?.rendered || '';
    return (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)((0,_wordpress_dom__WEBPACK_IMPORTED_MODULE_2__.__unstableStripHTML)(text));
  },
  render: ({
    item
  }) => {
    const {
      getObjectTypeName,
      isLoading
    } = (0,_contexts_object_type_context__WEBPACK_IMPORTED_MODULE_3__.useObjectType)();

    // Get the object type name from the cached map
    const objectTypeId = item.ap_object_type?.[0];
    const objectTypeName = getObjectTypeName(objectTypeId);

    // While loading, show a placeholder to prevent flicker
    if (isLoading && !objectTypeName) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "activitypub-feed-excerpt",
        children: '\u00A0'
      });
    }

    // Check if this is a Note type
    const isNote = objectTypeName === 'Note';
    if (isNote) {
      // Show full content for Notes (HTML)
      const content = (0,_wordpress_dom__WEBPACK_IMPORTED_MODULE_2__.safeHTML)((0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(item.content?.rendered || ''));
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
        className: "activitypub-feed-post",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
          className: "activitypub-feed-content",
          dangerouslySetInnerHTML: {
            __html: content || '<p>\u00A0</p>'
          }
        })
      });
    }

    // Show excerpt for Articles and other types (plain text)
    const plainText = contentField.getValue({
      item
    }).trim();
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
      className: "activitypub-feed-excerpt",
      children: plainText || '\u00A0'
    });
  }
};

/***/ }),

/***/ "./src/app/components/fields/content/style.scss":
/*!******************************************************!*\
  !*** ./src/app/components/fields/content/style.scss ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/fields/date/index.tsx":
/*!**************************************************!*\
  !*** ./src/app/components/fields/date/index.tsx ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   dateField: () => (/* binding */ dateField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */

const dateField = {
  id: 'date',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Date', 'activitypub'),
  enableHiding: false,
  enableSorting: true,
  getValue: ({
    item
  }) => item.date || '',
  render: ({
    item
  }) => {
    if (!item.date) {
      return '';
    }
    return new Date(item.date).toLocaleDateString();
  }
};

/***/ }),

/***/ "./src/app/components/fields/follow-status/index.tsx":
/*!***********************************************************!*\
  !*** ./src/app/components/fields/follow-status/index.tsx ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   followStatusField: () => (/* binding */ followStatusField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/fields/follow-status/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Follow Status field for DataViews.
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */



const followStatusField = {
  id: 'follow_status',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Following', 'activitypub'),
  enableHiding: true,
  getValue: ({
    item
  }) => item.follow_status?.follows_back,
  render: ({
    item
  }) => {
    if (item.follow_status?.follows_back) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
        className: "activitypub-mutual",
        children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__._x)('Mutual', 'Follow status', 'activitypub')
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
      children: "\u2014"
    });
  }
};

/***/ }),

/***/ "./src/app/components/fields/follow-status/style.scss":
/*!************************************************************!*\
  !*** ./src/app/components/fields/follow-status/style.scss ***!
  \************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/fields/index.ts":
/*!********************************************!*\
  !*** ./src/app/components/fields/index.ts ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   avatarField: () => (/* reexport safe */ _avatar__WEBPACK_IMPORTED_MODULE_0__.avatarField),
/* harmony export */   contentField: () => (/* reexport safe */ _content__WEBPACK_IMPORTED_MODULE_1__.contentField),
/* harmony export */   dateField: () => (/* reexport safe */ _date__WEBPACK_IMPORTED_MODULE_2__.dateField),
/* harmony export */   followStatusField: () => (/* reexport safe */ _follow_status__WEBPACK_IMPORTED_MODULE_3__.followStatusField),
/* harmony export */   metadataField: () => (/* reexport safe */ _metadata__WEBPACK_IMPORTED_MODULE_4__.metadataField),
/* harmony export */   modifiedField: () => (/* reexport safe */ _modified__WEBPACK_IMPORTED_MODULE_5__.modifiedField),
/* harmony export */   nameField: () => (/* reexport safe */ _name__WEBPACK_IMPORTED_MODULE_6__.nameField),
/* harmony export */   objectTypeField: () => (/* reexport safe */ _object_type__WEBPACK_IMPORTED_MODULE_7__.objectTypeField),
/* harmony export */   statusField: () => (/* reexport safe */ _status__WEBPACK_IMPORTED_MODULE_8__.statusField),
/* harmony export */   tagField: () => (/* reexport safe */ _tag__WEBPACK_IMPORTED_MODULE_9__.tagField),
/* harmony export */   titleField: () => (/* reexport safe */ _title__WEBPACK_IMPORTED_MODULE_10__.titleField),
/* harmony export */   webfingerField: () => (/* reexport safe */ _webfinger__WEBPACK_IMPORTED_MODULE_11__.webfingerField)
/* harmony export */ });
/* harmony import */ var _avatar__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./avatar */ "./src/app/components/fields/avatar/index.tsx");
/* harmony import */ var _content__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./content */ "./src/app/components/fields/content/index.tsx");
/* harmony import */ var _date__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./date */ "./src/app/components/fields/date/index.tsx");
/* harmony import */ var _follow_status__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./follow-status */ "./src/app/components/fields/follow-status/index.tsx");
/* harmony import */ var _metadata__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./metadata */ "./src/app/components/fields/metadata/index.tsx");
/* harmony import */ var _modified__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./modified */ "./src/app/components/fields/modified/index.tsx");
/* harmony import */ var _name__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./name */ "./src/app/components/fields/name/index.tsx");
/* harmony import */ var _object_type__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./object-type */ "./src/app/components/fields/object-type/index.tsx");
/* harmony import */ var _status__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./status */ "./src/app/components/fields/status/index.tsx");
/* harmony import */ var _tag__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./tag */ "./src/app/components/fields/tag/index.tsx");
/* harmony import */ var _title__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./title */ "./src/app/components/fields/title/index.tsx");
/* harmony import */ var _webfinger__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./webfinger */ "./src/app/components/fields/webfinger/index.tsx");













/***/ }),

/***/ "./src/app/components/fields/metadata/index.tsx":
/*!******************************************************!*\
  !*** ./src/app/components/fields/metadata/index.tsx ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   metadataField: () => (/* binding */ metadataField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../utils */ "./src/app/utils.ts");
/* harmony import */ var _avatar__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../avatar */ "./src/app/components/avatar/index.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */




const metadataField = {
  id: 'metadata',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Metadata', 'activitypub'),
  enableHiding: true,
  enableSorting: false,
  getValue: ({
    item
  }) => {
    const author = item.actor_info?.name || '';
    const relativeTime = item.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_2__.getRelativeTime)(item.date) : '';
    return `${author} · ${relativeTime}`;
  },
  render: ({
    item
  }) => {
    const name = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(item.actor_info?.name || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Unknown author', 'activitypub'));
    const relativeTime = item.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_2__.getRelativeTime)(item.date) : '';
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "activitypub-feed-post-meta",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)(_avatar__WEBPACK_IMPORTED_MODULE_3__["default"], {
        item: item
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
        className: "author",
        children: name
      }), relativeTime && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.Fragment, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
          className: "separator",
          children: "\xB7"
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("span", {
          className: "date",
          children: relativeTime
        })]
      })]
    });
  }
};

/***/ }),

/***/ "./src/app/components/fields/modified/index.tsx":
/*!******************************************************!*\
  !*** ./src/app/components/fields/modified/index.tsx ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   modifiedField: () => (/* binding */ modifiedField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Modified/Last Updated field for DataViews.
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */

const modifiedField = {
  id: 'modified',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Last Updated', 'activitypub'),
  enableHiding: true,
  enableSorting: true,
  getValue: ({
    item
  }) => item.modified_gmt || item.modified,
  render: ({
    item
  }) => {
    const date = item.modified_gmt || item.modified;
    if (!date) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("span", {
        children: "\u2014"
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("time", {
      dateTime: date,
      children: (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.dateI18n)('M j, Y', date)
    });
  },
  filterBy: {
    operators: ['after', 'before']
  }
};

/***/ }),

/***/ "./src/app/components/fields/name/index.tsx":
/*!**************************************************!*\
  !*** ./src/app/components/fields/name/index.tsx ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   nameField: () => (/* binding */ nameField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Name field for DataViews.
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */

const nameField = {
  id: 'name',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Name', 'activitypub'),
  enableHiding: false,
  enableSorting: true,
  getValue: ({
    item
  }) => item.actor_info?.name || '',
  render: ({
    item
  }) => {
    const name = item.actor_info?.name || '';
    const url = item.actor_info?.url || '#';
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("a", {
      href: url,
      target: "_blank",
      rel: "noopener noreferrer",
      className: "activitypub-name-field__link",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("strong", {
        children: name
      })
    });
  }
};

/***/ }),

/***/ "./src/app/components/fields/object-type/index.tsx":
/*!*********************************************************!*\
  !*** ./src/app/components/fields/object-type/index.tsx ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   objectTypeField: () => (/* binding */ objectTypeField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _object_types__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../object-types */ "./src/app/components/object-types/index.tsx");
/**
 * WordPress dependencies
 */




/**
 * Internal dependencies
 */


const objectTypeField = {
  id: 'ap_object_type',
  type: 'integer',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Type', 'activitypub'),
  enableHiding: false,
  enableSorting: false,
  getValue: ({
    item
  }) => item.ap_object_type?.[0],
  getElements: async () => {
    const records = await (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.resolveSelect)(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).getEntityRecords('taxonomy', 'ap_object_type', {
      per_page: -1,
      orderby: 'count',
      order: 'desc',
      hide_empty: true
    });
    if (!records) {
      return [];
    }

    // Map terms with translations from objectTypeConfig
    return records.map(term => ({
      value: term.id,
      label: _object_types__WEBPACK_IMPORTED_MODULE_3__.objectTypeConfig[term.name]?.label || term.name
    }));
  },
  render: () => null,
  filterBy: {
    operators: ['is']
  }
};

/***/ }),

/***/ "./src/app/components/fields/status/index.tsx":
/*!****************************************************!*\
  !*** ./src/app/components/fields/status/index.tsx ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   statusField: () => (/* binding */ statusField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */

const statusField = {
  id: 'status',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Status', 'activitypub'),
  enableHiding: true,
  enableSorting: true,
  getValue: ({
    item
  }) => item.status || ''
};

/***/ }),

/***/ "./src/app/components/fields/tag/index.tsx":
/*!*************************************************!*\
  !*** ./src/app/components/fields/tag/index.tsx ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   tagField: () => (/* binding */ tagField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__);
/**
 * WordPress dependencies
 */




/**
 * Internal dependencies
 */

const tagField = {
  id: 'ap_tag',
  type: 'integer',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Tag', 'activitypub'),
  enableHiding: false,
  enableSorting: false,
  getValue: ({
    item
  }) => {
    var _item$ap_tag;
    return (_item$ap_tag = item.ap_tag) !== null && _item$ap_tag !== void 0 ? _item$ap_tag : [];
  },
  getElements: async () => {
    const records = await (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.resolveSelect)(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).getEntityRecords('taxonomy', 'ap_tag', {
      per_page: 10,
      orderby: 'count',
      order: 'desc',
      hide_empty: true
    });
    if (!records) {
      return [];
    }

    // Map popular tags with # prefix
    return records.map(term => ({
      value: term.id,
      label: `#${term.name}`
    }));
  },
  render: () => null,
  filterBy: {
    operators: ['isAny']
  }
};

/***/ }),

/***/ "./src/app/components/fields/title/index.tsx":
/*!***************************************************!*\
  !*** ./src/app/components/fields/title/index.tsx ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   titleField: () => (/* binding */ titleField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */

const titleField = {
  id: 'title.rendered',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Title', 'activitypub'),
  enableHiding: true,
  enableSorting: false,
  enableGlobalSearch: true,
  getValue: ({
    item
  }) => (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(item.title?.rendered || ''),
  render: ({
    item
  }) => {
    if (!item.title?.rendered) {
      return null;
    }

    // Remove backslash escapes and decode entities
    const unescaped = item.title.rendered.replace(/\\(.)/g, '$1');
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "activitypub-feed-post-title",
      children: (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(unescaped)
    });
  }
};

/***/ }),

/***/ "./src/app/components/fields/webfinger/index.tsx":
/*!*******************************************************!*\
  !*** ./src/app/components/fields/webfinger/index.tsx ***!
  \*******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   webfingerField: () => (/* binding */ webfingerField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Webfinger/Profile field for DataViews.
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */

const webfingerField = {
  id: 'webfinger',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Profile', 'activitypub'),
  enableHiding: true,
  getValue: ({
    item
  }) => item.actor_info?.webfinger || '',
  render: ({
    item
  }) => {
    const webfinger = item.actor_info?.webfinger || '';
    const url = item.actor_info?.url || '#';
    if (!webfinger) {
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("span", {
        children: "\u2014"
      });
    }
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsxs)("a", {
      href: url,
      target: "_blank",
      rel: "noopener noreferrer",
      title: webfinger,
      children: ["@", webfinger]
    });
  }
};

/***/ }),

/***/ "./src/app/hooks/use-feed.ts":
/*!***********************************!*\
  !*** ./src/app/hooks/use-feed.ts ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useFeed: () => (/* binding */ useFeed)
/* harmony export */ });
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */

// Stable default values to prevent unnecessary re-renders
const DEFAULT_FIELDS = ['id', 'date', 'modified', 'title', 'excerpt', 'content', 'actor_info', 'status', 'link', 'ap_object_type', 'ap_tag'];
const DEFAULT_FILTERS = [];
const EMPTY_FEED = [];
function useFeed({
  perPage = 20,
  page = 1,
  orderBy = 'date',
  order = 'desc',
  search = '',
  userId,
  fields = DEFAULT_FIELDS,
  filters = DEFAULT_FILTERS
} = {}) {
  // Don't fetch if userId is not set
  const enabled = userId !== null && userId !== undefined;
  const queryArgs = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_1__.useMemo)(() => {
    const args = {
      per_page: perPage,
      page,
      orderby: orderBy,
      order,
      search,
      _fields: fields
    };

    // Only add user_id if we have a valid userId
    if (enabled) {
      args.user_id = userId;
    }

    // Extract ap_object_type filter from filters array
    const apObjectTypeFilter = filters.find(f => f.field === 'ap_object_type');
    if (apObjectTypeFilter?.value !== undefined) {
      // Wrap single value in array for REST API
      args.ap_object_type = Array.isArray(apObjectTypeFilter.value) ? apObjectTypeFilter.value : [apObjectTypeFilter.value];
    }

    // Extract ap_tag filter from filters array
    const apTagFilter = filters.find(f => f.field === 'ap_tag');
    if (apTagFilter?.value !== undefined) {
      args.ap_tag = apTagFilter.value;
    }
    return args;
  }, [perPage, page, orderBy, order, search, userId, fields, enabled, filters]);
  const {
    records,
    hasResolved,
    isResolving,
    totalItems,
    totalPages
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__.useEntityRecords)('postType', 'ap_post', queryArgs, {
    enabled
  });
  return {
    feed: enabled ? records || EMPTY_FEED : EMPTY_FEED,
    hasResolved,
    isResolving,
    totalItems: enabled ? totalItems : null,
    totalPages: enabled ? totalPages : null
  };
}

/***/ }),

/***/ "./src/app/routes/feed/content.ts":
/*!****************************************!*\
  !*** ./src/app/routes/feed/content.ts ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   inspector: () => (/* reexport safe */ _inspector__WEBPACK_IMPORTED_MODULE_1__["default"]),
/* harmony export */   stage: () => (/* reexport safe */ _stage__WEBPACK_IMPORTED_MODULE_0__["default"])
/* harmony export */ });
/* harmony import */ var _stage__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./stage */ "./src/app/routes/feed/stage.tsx");
/* harmony import */ var _inspector__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./inspector */ "./src/app/routes/feed/inspector.tsx");
/**
 * Feed Route Content Module
 *
 * Exports stage and inspector components for the feed route.
 * This module is loaded lazily by the router for code splitting.
 */

/**
 * Internal dependencies
 */



/***/ }),

/***/ "./src/app/routes/feed/inspector.tsx":
/*!*******************************************!*\
  !*** ./src/app/routes/feed/inspector.tsx ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ FeedInspector)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/close.js");
/* harmony import */ var _components_avatar__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../components/avatar */ "./src/app/components/avatar/index.tsx");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../utils */ "./src/app/utils.ts");
/* harmony import */ var _hooks_use_tag_filter__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../hooks/use-tag-filter */ "./src/app/hooks/use-tag-filter.ts");
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../router */ "./src/app/router/index.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);
/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */





// Helper to render HTML content with proper entity decoding and unescape
const RenderHTML = ({
  html
}) => {
  // Remove backslash escapes (e.g., \! becomes !)
  const unescaped = html.replace(/\\(.)/g, '$1');
  const decoded = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(unescaped);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
    dangerouslySetInnerHTML: {
      __html: decoded
    }
  });
};
function FeedInspector() {
  const search = (0,_router__WEBPACK_IMPORTED_MODULE_8__.useSearch)({
    strict: false
  });
  const navigate = (0,_router__WEBPACK_IMPORTED_MODULE_8__.useNavigate)();
  const id = search.postId;

  // Close inspector by removing postId from search params
  const onClose = () => {
    void navigate({
      search: prev => {
        const {
          postId: _,
          ...rest
        } = prev;
        return rest;
      }
    });
  };
  const {
    record: post,
    isResolving: isLoading
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__.useEntityRecord)('postType', 'ap_post', id !== null && id !== void 0 ? id : 0);
  const {
    records: comments,
    isResolving: isLoadingComments
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__.useEntityRecords)('root', 'comment', {
    post: id !== null && id !== void 0 ? id : 0,
    order: 'asc',
    orderby: 'date'
  });

  // Early return if no id (shouldn't happen due to route config, but handle gracefully)
  if (!id) {
    return null;
  }

  // Fetch tag terms if the post has tags
  const tagIds = post?.ap_tag || [];
  const {
    records: terms
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__.useEntityRecords)('taxonomy', 'ap_tag', {
    include: tagIds
  });

  // Use the shared tag filter hook
  const {
    selectedTagId,
    updateTagFilter
  } = (0,_hooks_use_tag_filter__WEBPACK_IMPORTED_MODULE_7__.useTagFilter)();
  const handleTagClick = tagId => {
    // Apply filter and close inspector
    updateTagFilter(tagId, {
      onComplete: onClose
    });
  };
  if (isLoading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "activitypub-inspector-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Spinner, {})
    });
  }
  if (!post) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
      className: "activitypub-inspector-loading",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Post not found', 'activitypub')
    });
  }
  const actor = post.actor_info;
  const author = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(actor?.name || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Unknown author', 'activitypub'));
  const webfinger = actor?.webfinger || '';
  const profileUrl = actor?.url || '';
  const postLink = post.link || '';
  const relativeTime = post.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_6__.getRelativeTime)(post.date) : '';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    className: "activitypub-inspector",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      className: "activitypub-inspector-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "activitypub-inspector-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_components_avatar__WEBPACK_IMPORTED_MODULE_5__["default"], {
            item: post
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
            className: "activitypub-inspector-author",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("a", {
              href: profileUrl,
              target: "_blank",
              rel: "noopener noreferrer",
              className: "activitypub-inspector-author-name",
              children: author
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
              className: "activitypub-inspector-meta",
              children: [webfinger && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                className: "activitypub-inspector-webfinger",
                children: webfinger
              }), relativeTime && postLink && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.Fragment, {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                  className: "activitypub-inspector-separator",
                  children: "\xB7"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("a", {
                  href: postLink,
                  target: "_blank",
                  rel: "noopener noreferrer",
                  className: "activitypub-inspector-timestamp",
                  children: relativeTime
                })]
              })]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
            icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'activitypub'),
            onClick: onClose,
            className: "activitypub-inspector-close"
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: [post.title?.rendered && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("h2", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(RenderHTML, {
            html: post.title.rendered
          })
        }), (post.content?.rendered || post.excerpt?.rendered) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(RenderHTML, {
          html: post.content?.rendered || post.excerpt?.rendered || ''
        }), terms && terms.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
          className: "activitypub-inspector-tags",
          children: terms.map(term => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
            size: "small",
            variant: "secondary",
            onClick: () => handleTagClick(term.id),
            "aria-pressed": selectedTagId === term.id,
            "aria-label": /* translators: %s: tag name */
            (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Filter by tag: %s', 'activitypub'), term.name),
            children: ["#", term.name]
          }, term.id))
        })]
      })]
    }), (isLoadingComments || comments && comments.length > 0) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      className: "activitypub-inspector-card activitypub-inspector-comments-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Comments', 'activitypub'), comments && comments.length > 0 && ` (${comments.length})`]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: [isLoadingComments && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Spinner, {}), !isLoadingComments && comments && comments.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
          children: comments.map(comment => {
            // Use date_gmt for reliable UTC parsing
            const commentDate = comment.date_gmt ? (0,_utils__WEBPACK_IMPORTED_MODULE_6__.getRelativeTime)(comment.date_gmt) : '';
            return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
              className: "activitypub-inspector-comment",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
                className: "activitypub-inspector-comment-meta",
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("strong", {
                  children: (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(comment.author_name)
                }), commentDate && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("span", {
                  className: "activitypub-inspector-comment-date",
                  children: commentDate
                })]
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(RenderHTML, {
                html: comment.content.rendered
              })]
            }, comment.id);
          })
        }), !isLoadingComments && (!comments || comments.length === 0) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No comments yet.', 'activitypub')
        })]
      })]
    })]
  });
}

/***/ }),

/***/ "./src/app/routes/feed/stage.tsx":
/*!***************************************!*\
  !*** ./src/app/routes/feed/stage.tsx ***!
  \***************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ FeedStage)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_dataviews_wp__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/dataviews/wp */ "./node_modules/@wordpress/dataviews/build-wp/index.js");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/views */ "@wordpress/views");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_views__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _hooks_use_feed__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../hooks/use-feed */ "./src/app/hooks/use-feed.ts");
/* harmony import */ var _components_fields__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../components/fields */ "./src/app/components/fields/index.ts");
/* harmony import */ var _components_empty_state__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../components/empty-state */ "./src/app/components/empty-state/index.tsx");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./utils */ "./src/app/routes/feed/utils.ts");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../../store */ "./src/app/store/index.ts");
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../../router */ "./src/app/router/index.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./style.scss */ "./src/app/routes/feed/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_12___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_12__);
/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */

/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */






/**
 * Internal dependencies
 */








// Using ReturnType to get the View type from useView to avoid version conflicts between @wordpress/views and @wordpress/dataviews

const DEFAULT_VIEW = {
  type: 'list',
  perPage: 20,
  page: 1,
  sort: {
    field: 'date',
    direction: 'desc'
  },
  search: '',
  filters: [],
  fields: ['metadata', 'title.rendered', 'content'],
  infiniteScrollEnabled: true
};
const defaultLayouts = {
  list: {
    primaryField: 'metadata',
    fields: ['metadata', 'title.rendered', 'content'],
    mediaField: undefined
  }
};
function FeedStage() {
  const navigate = (0,_router__WEBPACK_IMPORTED_MODULE_10__.useNavigate)();

  // Navigate to inspector by updating search params
  const selectItem = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(id => {
    void navigate({
      search: prev => ({
        ...prev,
        postId: id
      })
    });
  }, [navigate]);
  // Get active actor ID from store
  const activeActorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_4__.useSelect)(select => select(_store__WEBPACK_IMPORTED_MODULE_9__.STORE_NAME).getActiveActorId(), []);

  // Track URL query parameters as state for reactivity
  const [urlQueryParams, setUrlQueryParams] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(() => {
    const args = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.getQueryArgs)(window.location.href);
    return {
      page: args.paged ? Number(args.paged) : undefined,
      search: args.search || undefined
    };
  });

  // Listen for URL changes (browser back/forward).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const updateQueryParams = () => {
      const args = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.getQueryArgs)(window.location.href);
      setUrlQueryParams({
        page: args.paged ? Number(args.paged) : undefined,
        search: args.search || undefined
      });
    };
    window.addEventListener('popstate', updateQueryParams);
    window.addEventListener('hashchange', updateQueryParams);
    return () => {
      window.removeEventListener('popstate', updateQueryParams);
      window.removeEventListener('hashchange', updateQueryParams);
    };
  }, []);

  // Memoize onChangeQueryParams to prevent updateView from changing on every render.
  const handleChangeQueryParams = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(params => {
    const currentUrl = window.location.href;
    const currentArgs = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.getQueryArgs)(currentUrl);
    const newUrl = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_3__.addQueryArgs)(currentUrl, {
      ...currentArgs,
      paged: params.page || undefined,
      search: params.search || undefined
    });
    window.history.pushState(null, '', newUrl);
    setUrlQueryParams({
      page: params.page,
      search: params.search
    });
  }, []);

  // Use the views hook to persist user preferences
  const {
    view,
    updateView
  } = (0,_wordpress_views__WEBPACK_IMPORTED_MODULE_2__.useView)({
    kind: 'postType',
    name: 'ap_post',
    slug: 'feed',
    defaultView: DEFAULT_VIEW,
    queryParams: urlQueryParams,
    onChangeQueryParams: handleChangeQueryParams
  });

  // Wrap updateView to reset page when filters change
  const updateFeedView = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(updatedView => {
    var _updatedView$page;
    // Reset to page 1 when filters change
    const filtersChanged = JSON.stringify(view.filters) !== JSON.stringify(updatedView.filters);
    const page = filtersChanged ? 1 : (_updatedView$page = updatedView.page) !== null && _updatedView$page !== void 0 ? _updatedView$page : 1;
    updateView({
      ...updatedView,
      page
    });
  }, [view.filters, updateView]);

  // Reset view to default state when actor switches
  const prevActiveActorId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(activeActorId);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (prevActiveActorId.current !== activeActorId) {
      // Actor changed - reset to default view, preserving only field visibility
      updateView({
        ...DEFAULT_VIEW,
        fields: view.fields
      });
      prevActiveActorId.current = activeActorId;
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps -- updateView changes reference frequently; condition guards against repeated calls
  }, [activeActorId]);
  const {
    feed,
    isResolving,
    totalItems,
    totalPages
  } = (0,_hooks_use_feed__WEBPACK_IMPORTED_MODULE_5__.useFeed)({
    perPage: view.perPage || 20,
    page: view.page || 1,
    orderBy: view.sort?.field || 'date',
    order: view.sort?.direction || 'desc',
    search: view.search || '',
    userId: activeActorId,
    filters: view.filters || DEFAULT_VIEW.filters
  });
  const fields = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => [_components_fields__WEBPACK_IMPORTED_MODULE_6__.metadataField, _components_fields__WEBPACK_IMPORTED_MODULE_6__.titleField, _components_fields__WEBPACK_IMPORTED_MODULE_6__.contentField, _components_fields__WEBPACK_IMPORTED_MODULE_6__.dateField, _components_fields__WEBPACK_IMPORTED_MODULE_6__.objectTypeField, _components_fields__WEBPACK_IMPORTED_MODULE_6__.tagField], []);

  // Normalize view.fields to maintain the canonical order defined in fields array
  const normalizedView = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => (0,_utils__WEBPACK_IMPORTED_MODULE_8__.normalizeFieldOrder)(view, fields), [view, fields]);
  const [selection, setSelection] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);

  // State for infinite scroll
  const [allLoadedRecords, setAllLoadedRecords] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)([]);
  const [isLoadingMore, setIsLoadingMore] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(false);
  const lastProcessedPage = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useRef)(0);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    if (selection.length === 0) {
      return;
    }
    const selectedId = selection[0];
    const exists = feed.some(item => item.id.toString() === selectedId);
    if (!exists) {
      setSelection([]);
    }
  }, [feed, selection]);
  const changeSelection = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(nextSelection => {
    setSelection(nextSelection);
    if (nextSelection.length === 0) {
      return;
    }
    const selectedId = nextSelection[0];
    const selectedItem = feed.find(item => item.id.toString() === selectedId);
    if (selectedItem) {
      selectItem(selectedItem.id);
    }
  }, [feed, selectItem]);

  // Infinite scroll handler
  const infiniteScrollHandler = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    const currentPage = view.page || 1;

    // Prevent concurrent requests or loading beyond available pages
    if (isLoadingMore || currentPage >= (totalPages || 1)) {
      return;
    }
    setIsLoadingMore(true);
    updateFeedView({
      ...view,
      page: currentPage + 1
    });
  }, [isLoadingMore, view, totalPages, updateFeedView]);

  // Accumulate data across pages for infinite scroll
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const currentPage = normalizedView.page || 1;
    const infiniteScrollEnabled = normalizedView.infiniteScrollEnabled;

    // Clear records when on first page with no results (handles filter/search changes)
    if (feed.length === 0 && currentPage === 1) {
      setAllLoadedRecords([]);
      lastProcessedPage.current = currentPage;
      setIsLoadingMore(false);
      return;
    }

    // Don't process until feed data is available
    if (feed.length === 0) {
      return;
    }

    // Skip if we've already processed this page (but always process page 1 for search/initial load)
    if (currentPage > 1 && lastProcessedPage.current === currentPage) {
      return;
    }

    // Reset to new data on first page or when infinite scroll is disabled
    if (currentPage === 1 || !infiniteScrollEnabled) {
      setAllLoadedRecords(feed);
      lastProcessedPage.current = currentPage;
      setIsLoadingMore(false);
    } else {
      // Append new records while avoiding duplicates
      setAllLoadedRecords(prev => {
        const existingIds = new Set(prev.map(item => item.id));
        const newRecords = feed.filter(record => !existingIds.has(record.id));
        return newRecords.length > 0 ? [...prev, ...newRecords] : prev;
      });
      lastProcessedPage.current = currentPage;
      setIsLoadingMore(false);
    }
  }, [feed, normalizedView.page, normalizedView.search, normalizedView.infiniteScrollEnabled, normalizedView.filters]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_12__.jsx)(_wordpress_dataviews_wp__WEBPACK_IMPORTED_MODULE_1__.DataViews, {
    data: allLoadedRecords,
    fields: fields,
    view: normalizedView,
    onChangeView: updateFeedView,
    isLoading: isResolving || isLoadingMore,
    onClickItem: item => selectItem(item.id),
    isItemClickable: () => true,
    getItemId: item => item.id.toString(),
    selection: selection,
    onChangeSelection: changeSelection,
    empty: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_12__.jsx)(_components_empty_state__WEBPACK_IMPORTED_MODULE_7__["default"], {}),
    paginationInfo: {
      totalItems,
      totalPages,
      infiniteScrollHandler
    },
    defaultLayouts: defaultLayouts
  });
}

/***/ }),

/***/ "./src/app/routes/feed/style.scss":
/*!****************************************!*\
  !*** ./src/app/routes/feed/style.scss ***!
  \****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/routes/feed/utils.ts":
/*!**************************************!*\
  !*** ./src/app/routes/feed/utils.ts ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   normalizeFieldOrder: () => (/* binding */ normalizeFieldOrder)
/* harmony export */ });
/**
 * Utility functions for feed view management
 */

/**
 * WordPress dependencies
 */

// Using ReturnType to get the View type from useView to avoid version conflicts
// between @wordpress/views and @wordpress/dataviews

/**
 * Normalizes view fields to maintain canonical order.
 * Sorts the visible fields according to the order defined in the fields array.
 *
 * @param view   - The current view configuration
 * @param fields - Array of field objects with their canonical order
 * @return The view with fields sorted in canonical order
 */
function normalizeFieldOrder(view, fields) {
  if (!view.fields) {
    return view;
  }

  // Create a map of field IDs to their canonical order
  const fieldOrder = new Map(fields.map((field, index) => [field.id, index]));

  // Sort view.fields according to the canonical order
  const sortedFields = [...view.fields].sort((a, b) => {
    var _fieldOrder$get, _fieldOrder$get2;
    const orderA = (_fieldOrder$get = fieldOrder.get(a)) !== null && _fieldOrder$get !== void 0 ? _fieldOrder$get : Infinity;
    const orderB = (_fieldOrder$get2 = fieldOrder.get(b)) !== null && _fieldOrder$get2 !== void 0 ? _fieldOrder$get2 : Infinity;
    return orderA - orderB;
  });
  return {
    ...view,
    fields: sortedFields
  };
}

/***/ }),

/***/ "./src/app/utils.ts":
/*!**************************!*\
  !*** ./src/app/utils.ts ***!
  \**************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getRelativeTime: () => (/* binding */ getRelativeTime)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/date */ "@wordpress/date");
/* harmony import */ var _wordpress_date__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_date__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Utility functions for ActivityPub App.
 */

/**
 * WordPress dependencies
 */



/**
 * Format relative time in short format (5m, 2h, 6d)
 * For dates older than a week, returns the site's date format
 *
 * @param dateString - The date string to format
 * @return The formatted relative time string
 */
function getRelativeTime(dateString) {
  // Ensure the date string is parsed as UTC by adding 'Z' if not present
  const date = new Date(dateString.endsWith('Z') ? dateString : dateString + 'Z');
  const now = Date.now();
  const diffMs = now - date.getTime();
  const diffMinutes = Math.floor(diffMs / (1000 * 60));
  const diffHours = Math.floor(diffMs / (1000 * 60 * 60));
  const diffDays = Math.floor(diffMs / (1000 * 60 * 60 * 24));
  if (diffMinutes < 60) {
    return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %d: number of minutes */
    (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__._x)('%dm', 'short time format: minutes', 'activitypub'), diffMinutes);
  } else if (diffHours < 24) {
    return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %d: number of hours */
    (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__._x)('%dh', 'short time format: hours', 'activitypub'), diffHours);
  } else if (diffDays < 7) {
    return (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %d: number of days */
    (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__._x)('%dd', 'short time format: days', 'activitypub'), diffDays);
  }

  // Use site's date format for dates older than a week
  return (0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.dateI18n)((0,_wordpress_date__WEBPACK_IMPORTED_MODULE_1__.getSettings)().formats.date, dateString);
}

/***/ })

}]);
//# sourceMappingURL=feed-content.d1b1d388.js.map