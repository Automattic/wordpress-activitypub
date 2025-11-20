"use strict";
(globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || []).push([["social-web/feed-inspector"],{

/***/ "./src/social-web/routes/feed/inspector.tsx":
/*!**************************************************!*\
  !*** ./src/social-web/routes/feed/inspector.tsx ***!
  \**************************************************/
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
/* harmony import */ var _contexts_settings_context__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../contexts/settings-context */ "./src/social-web/contexts/settings-context.tsx");
/* harmony import */ var _utils__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../utils */ "./src/social-web/utils.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * Feed Post Inspector
 *
 * Detail view for a single feed post in the side panel
 */









// Helper to render HTML content with proper entity decoding and unescape
const RenderHTML = ({
  html
}) => {
  // Remove backslash escapes (e.g., \! becomes !)
  const unescaped = html.replace(/\\(.)/g, '$1');
  const decoded = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(unescaped);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
    dangerouslySetInnerHTML: {
      __html: decoded
    }
  });
};
function FeedInspector({
  id,
  onClose
}) {
  const {
    defaultAvatar
  } = (0,_contexts_settings_context__WEBPACK_IMPORTED_MODULE_5__.useSettings)();
  const {
    record: post,
    isResolving: isLoading
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__.useEntityRecord)('postType', 'ap_post', id);
  const {
    records: comments,
    isResolving: isLoadingComments
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__.useEntityRecords)('root', 'comment', {
    post: id,
    per_page: 100,
    order: 'asc',
    orderby: 'date'
  });
  if (isLoading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      className: "activitypub-inspector-loading",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Spinner, {})
    });
  }
  if (!post) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      className: "activitypub-inspector-loading",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Post not found', 'activitypub')
    });
  }
  const actor = post.actor_info;
  const author = (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(actor?.name || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Unknown author', 'activitypub'));
  const webfinger = actor?.webfinger || '';
  const profileUrl = actor?.url || '';
  const avatarUrl = actor?.icon || '';
  const postLink = post.link || '';
  const relativeTime = post.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_6__.getRelativeTime)(post.date) : '';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
    className: "activitypub-inspector",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      className: "activitypub-inspector-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
          className: "activitypub-inspector-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("img", {
            src: avatarUrl,
            alt: author,
            className: "activitypub-inspector-avatar",
            onError: e => {
              e.target.src = defaultAvatar;
            }
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "activitypub-inspector-author",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("a", {
              href: profileUrl,
              target: "_blank",
              rel: "noopener noreferrer",
              className: "activitypub-inspector-author-name",
              children: author
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "activitypub-inspector-meta",
              children: [webfinger && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                className: "activitypub-inspector-webfinger",
                children: webfinger
              }), relativeTime && postLink && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.Fragment, {
                children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                  className: "activitypub-inspector-separator",
                  children: "\xB7"
                }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("a", {
                  href: postLink,
                  target: "_blank",
                  rel: "noopener noreferrer",
                  className: "activitypub-inspector-timestamp",
                  children: relativeTime
                })]
              })]
            })]
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
            icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Close', 'activitypub'),
            onClick: onClose,
            className: "activitypub-inspector-close"
          })]
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: [post.title?.rendered && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("h2", {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(RenderHTML, {
            html: post.title.rendered
          })
        }), (post.content?.rendered || post.excerpt?.rendered) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(RenderHTML, {
          html: post.content?.rendered || post.excerpt?.rendered || ''
        })]
      })]
    }), (isLoadingComments || comments && comments.length > 0) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      className: "activitypub-inspector-card activitypub-inspector-comments-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Comments', 'activitypub'), comments && comments.length > 0 && ` (${comments.length})`]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardBody, {
        children: [isLoadingComments && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Spinner, {}), !isLoadingComments && comments && comments.length > 0 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
          children: comments.map(comment => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
            className: "activitypub-inspector-comment",
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsxs)("div", {
              className: "activitypub-inspector-comment-meta",
              children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("strong", {
                children: (0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_3__.decodeEntities)(comment.author_name)
              }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("span", {
                className: "activitypub-inspector-comment-date",
                children: new Date(comment.date).toLocaleString()
              })]
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(RenderHTML, {
              html: comment.content.rendered
            })]
          }, comment.id))
        }), !isLoadingComments && (!comments || comments.length === 0) && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('No comments yet.', 'activitypub')
        })]
      })]
    })]
  });
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
//# sourceMappingURL=feed-inspector.js.map