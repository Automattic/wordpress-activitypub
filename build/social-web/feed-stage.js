"use strict";
(globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || []).push([["social-web/feed-stage"],{

/***/ "./src/social-web/components/fields/avatar/avatar.tsx":
/*!************************************************************!*\
  !*** ./src/social-web/components/fields/avatar/avatar.tsx ***!
  \************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Avatar)
/* harmony export */ });
/* harmony import */ var _contexts_settings_context__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ../../../contexts/settings-context */ "./src/social-web/contexts/settings-context.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Avatar component that displays an actor's avatar with fallback support.
 */



function Avatar({
  item
}) {
  const {
    defaultAvatar
  } = (0,_contexts_settings_context__WEBPACK_IMPORTED_MODULE_0__.useSettings)();
  const avatarUrl = item.actor_info?.icon || defaultAvatar;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("img", {
    alt: item.actor_info?.username || '',
    src: avatarUrl,
    className: "activitypub-avatar-field__image",
    onError: e => {
      e.target.src = defaultAvatar;
    }
  });
}

/***/ }),

/***/ "./src/social-web/components/fields/avatar/index.tsx":
/*!***********************************************************!*\
  !*** ./src/social-web/components/fields/avatar/index.tsx ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   avatarField: () => (/* binding */ avatarField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _avatar__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./avatar */ "./src/social-web/components/fields/avatar/avatar.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/components/fields/avatar/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Avatar field for DataViews.
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

/***/ "./src/social-web/components/fields/avatar/style.scss":
/*!************************************************************!*\
  !*** ./src/social-web/components/fields/avatar/style.scss ***!
  \************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/components/fields/content/index.tsx":
/*!************************************************************!*\
  !*** ./src/social-web/components/fields/content/index.tsx ***!
  \************************************************************/
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
/* harmony import */ var _contexts_object_type_context__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../contexts/object-type-context */ "./src/social-web/contexts/object-type-context.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/components/fields/content/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);






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

/***/ "./src/social-web/components/fields/content/style.scss":
/*!*************************************************************!*\
  !*** ./src/social-web/components/fields/content/style.scss ***!
  \*************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/components/fields/date/index.tsx":
/*!*********************************************************!*\
  !*** ./src/social-web/components/fields/date/index.tsx ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   dateField: () => (/* binding */ dateField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);

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

/***/ "./src/social-web/components/fields/follow-status/index.tsx":
/*!******************************************************************!*\
  !*** ./src/social-web/components/fields/follow-status/index.tsx ***!
  \******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   followStatusField: () => (/* binding */ followStatusField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/components/fields/follow-status/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Follow Status field for DataViews.
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

/***/ "./src/social-web/components/fields/follow-status/style.scss":
/*!*******************************************************************!*\
  !*** ./src/social-web/components/fields/follow-status/style.scss ***!
  \*******************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/components/fields/index.ts":
/*!***************************************************!*\
  !*** ./src/social-web/components/fields/index.ts ***!
  \***************************************************/
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
/* harmony import */ var _avatar__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./avatar */ "./src/social-web/components/fields/avatar/index.tsx");
/* harmony import */ var _content__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./content */ "./src/social-web/components/fields/content/index.tsx");
/* harmony import */ var _date__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./date */ "./src/social-web/components/fields/date/index.tsx");
/* harmony import */ var _follow_status__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./follow-status */ "./src/social-web/components/fields/follow-status/index.tsx");
/* harmony import */ var _metadata__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./metadata */ "./src/social-web/components/fields/metadata/index.tsx");
/* harmony import */ var _modified__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./modified */ "./src/social-web/components/fields/modified/index.tsx");
/* harmony import */ var _name__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./name */ "./src/social-web/components/fields/name/index.tsx");
/* harmony import */ var _object_type__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./object-type */ "./src/social-web/components/fields/object-type/index.tsx");
/* harmony import */ var _status__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./status */ "./src/social-web/components/fields/status/index.tsx");
/* harmony import */ var _tag__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./tag */ "./src/social-web/components/fields/tag/index.tsx");
/* harmony import */ var _title__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./title */ "./src/social-web/components/fields/title/index.tsx");
/* harmony import */ var _webfinger__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./webfinger */ "./src/social-web/components/fields/webfinger/index.tsx");













/***/ }),

/***/ "./src/social-web/components/fields/metadata/index.tsx":
/*!*************************************************************!*\
  !*** ./src/social-web/components/fields/metadata/index.tsx ***!
  \*************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   metadataField: () => (/* binding */ metadataField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _contexts_settings_context__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../../contexts/settings-context */ "./src/social-web/contexts/settings-context.tsx");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../../utils */ "./src/social-web/utils.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





const metadataField = {
  id: 'metadata',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Metadata', 'activitypub'),
  enableHiding: true,
  enableSorting: false,
  getValue: ({
    item
  }) => {
    const author = item.actor_info?.name || '';
    const relativeTime = item.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_3__.getRelativeTime)(item.date) : '';
    return `${author} · ${relativeTime}`;
  },
  render: ({
    item
  }) => {
    const {
      defaultAvatar
    } = (0,_contexts_settings_context__WEBPACK_IMPORTED_MODULE_2__.useSettings)();
    const name = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_1__.decodeEntities)(item.actor_info?.name || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Unknown author', 'activitypub'));
    const avatarUrl = item.actor_info?.icon || '';
    const relativeTime = item.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_3__.getRelativeTime)(item.date) : '';
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsxs)("div", {
      className: "activitypub-feed-post-meta",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("img", {
        src: avatarUrl,
        alt: name,
        className: "activitypub-feed-avatar",
        onError: e => {
          e.target.src = defaultAvatar;
        }
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

/***/ "./src/social-web/components/fields/modified/index.tsx":
/*!*************************************************************!*\
  !*** ./src/social-web/components/fields/modified/index.tsx ***!
  \*************************************************************/
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

/***/ "./src/social-web/components/fields/name/index.tsx":
/*!*********************************************************!*\
  !*** ./src/social-web/components/fields/name/index.tsx ***!
  \*********************************************************/
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

/***/ "./src/social-web/components/fields/object-type/index.tsx":
/*!****************************************************************!*\
  !*** ./src/social-web/components/fields/object-type/index.tsx ***!
  \****************************************************************/
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
/* harmony import */ var _object_types__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../object-types */ "./src/social-web/components/object-types/index.tsx");




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

/***/ "./src/social-web/components/fields/status/index.tsx":
/*!***********************************************************!*\
  !*** ./src/social-web/components/fields/status/index.tsx ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   statusField: () => (/* binding */ statusField)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);

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

/***/ "./src/social-web/components/fields/tag/index.tsx":
/*!********************************************************!*\
  !*** ./src/social-web/components/fields/tag/index.tsx ***!
  \********************************************************/
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

/***/ "./src/social-web/components/fields/title/index.tsx":
/*!**********************************************************!*\
  !*** ./src/social-web/components/fields/title/index.tsx ***!
  \**********************************************************/
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

/***/ "./src/social-web/components/fields/webfinger/index.tsx":
/*!**************************************************************!*\
  !*** ./src/social-web/components/fields/webfinger/index.tsx ***!
  \**************************************************************/
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

/***/ "./src/social-web/hooks/use-feed.ts":
/*!******************************************!*\
  !*** ./src/social-web/hooks/use-feed.ts ***!
  \******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useFeed: () => (/* binding */ useFeed)
/* harmony export */ });
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_1__);


function useFeed({
  perPage = 20,
  page = 1,
  orderBy = 'date',
  order = 'desc',
  search = '',
  userId,
  fields = ['id', 'date', 'modified', 'title', 'excerpt', 'content', 'actor_info', 'status', 'link', 'ap_object_type', 'ap_tag'],
  filters = []
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
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__.useEntityRecords)('postType', 'ap_post', enabled ? queryArgs : undefined);
  return {
    feed: enabled ? records || [] : [],
    hasResolved,
    isResolving,
    totalItems: enabled ? totalItems : null,
    totalPages: enabled ? totalPages : null
  };
}

/***/ }),

/***/ "./src/social-web/routes/feed/stage.tsx":
/*!**********************************************!*\
  !*** ./src/social-web/routes/feed/stage.tsx ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ FeedStage)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_dataviews__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/dataviews */ "./node_modules/@wordpress/dataviews/build-module/components/dataviews/index.js");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/views */ "./node_modules/@wordpress/views/build-module/index.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _hooks_use_feed__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../hooks/use-feed */ "./src/social-web/hooks/use-feed.ts");
/* harmony import */ var _components_fields__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../components/fields */ "./src/social-web/components/fields/index.ts");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./utils */ "./src/social-web/routes/feed/utils.ts");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../../store */ "./src/social-web/store/index.ts");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/routes/feed/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__);
/**
 * Feed Stage
 *
 * Main feed list view with DataViews
 */













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
function FeedStage({
  onSelectItem
}) {
  // Get active actor ID from store
  const activeActorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_5__.useSelect)(select => select(_store__WEBPACK_IMPORTED_MODULE_9__.STORE_NAME).getActiveActorId(), []);

  // Track URL query parameters as state for reactivity
  const [urlQueryParams, setUrlQueryParams] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useState)(() => {
    const args = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_4__.getQueryArgs)(window.location.href);
    return {
      page: args.paged ? Number(args.paged) : undefined,
      search: args.search || undefined
    };
  });

  // Listen for URL changes (browser back/forward).
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useEffect)(() => {
    const updateQueryParams = () => {
      const args = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_4__.getQueryArgs)(window.location.href);
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
    onChangeQueryParams: params => {
      const currentUrl = window.location.href;
      const currentArgs = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_4__.getQueryArgs)(currentUrl);
      const newUrl = (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_4__.addQueryArgs)(currentUrl, {
        ...currentArgs,
        paged: params.page || undefined,
        search: params.search || undefined
      });
      window.history.pushState(null, '', newUrl);
      setUrlQueryParams({
        page: params.page,
        search: params.search
      });
    }
  });

  // Wrap updateView to reset page when filters change
  const updateFeedView = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(updatedView => {
    // Reset to page 1 when filters change
    const filtersChanged = JSON.stringify(view.filters) !== JSON.stringify(updatedView.filters);
    const page = filtersChanged ? 1 : updatedView.page;
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
  }, [activeActorId, updateView]);
  const {
    feed,
    isResolving,
    totalItems,
    totalPages
  } = (0,_hooks_use_feed__WEBPACK_IMPORTED_MODULE_6__.useFeed)({
    perPage: view.perPage || 20,
    page: view.page || 1,
    orderBy: view.sort?.field || 'date',
    order: view.sort?.direction || 'desc',
    search: view.search || '',
    userId: activeActorId,
    filters: view.filters || []
  });
  const fields = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => [_components_fields__WEBPACK_IMPORTED_MODULE_7__.metadataField, _components_fields__WEBPACK_IMPORTED_MODULE_7__.titleField, _components_fields__WEBPACK_IMPORTED_MODULE_7__.contentField, _components_fields__WEBPACK_IMPORTED_MODULE_7__.dateField, _components_fields__WEBPACK_IMPORTED_MODULE_7__.objectTypeField, _components_fields__WEBPACK_IMPORTED_MODULE_7__.tagField], []);

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
      onSelectItem(selectedItem.id);
    }
  }, [feed, onSelectItem]);

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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_dataviews__WEBPACK_IMPORTED_MODULE_1__["default"], {
    data: allLoadedRecords,
    fields: fields,
    view: normalizedView,
    onChangeView: updateFeedView,
    isLoading: isResolving || isLoadingMore,
    onClickItem: item => onSelectItem(item.id),
    isItemClickable: () => true,
    getItemId: item => item.id.toString(),
    selection: selection,
    onChangeSelection: changeSelection,
    empty: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("p", {
      children: normalizedView.search || normalizedView.filters && normalizedView.filters.length > 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('No posts found.', 'activitypub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('No posts found in your feed. Posts from ActivityPub actors you follow will appear here.', 'activitypub')
    }),
    paginationInfo: {
      totalItems,
      totalPages,
      infiniteScrollHandler
    },
    defaultLayouts: defaultLayouts
  });
}

/***/ }),

/***/ "./src/social-web/routes/feed/style.scss":
/*!***********************************************!*\
  !*** ./src/social-web/routes/feed/style.scss ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/routes/feed/utils.ts":
/*!*********************************************!*\
  !*** ./src/social-web/routes/feed/utils.ts ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   normalizeFieldOrder: () => (/* binding */ normalizeFieldOrder)
/* harmony export */ });
/**
 * Utility functions for feed view management
 */

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

/***/ "./src/social-web/utils.ts":
/*!*********************************!*\
  !*** ./src/social-web/utils.ts ***!
  \*********************************/
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
 * Utility functions for Social Web
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
//# sourceMappingURL=feed-stage.js.map