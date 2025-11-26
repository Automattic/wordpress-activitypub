"use strict";
(globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || []).push([["social-web/feed-inspector"],{

/***/ "./src/social-web/components/inspector-sidebar/index.tsx":
/*!***************************************************************!*\
  !*** ./src/social-web/components/inspector-sidebar/index.tsx ***!
  \***************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ InspectorSidebar)
/* harmony export */ });
/* harmony import */ var _widgets__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./widgets */ "./src/social-web/components/inspector-sidebar/widgets/index.ts");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/components/inspector-sidebar/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Inspector Sidebar Component
 *
 * Persistent right sidebar shown when no post is selected
 * Displays contextual widgets like trending tags
 */




/**
 * Available widgets that can be displayed in the inspector sidebar
 * Add new widgets here to make them available
 */

const WIDGETS = [_widgets__WEBPACK_IMPORTED_MODULE_0__.NavigationWidget, _widgets__WEBPACK_IMPORTED_MODULE_0__.TrendingWidget
// Add more widgets here as they're created
// Example: WhoToFollowWidget, SuggestedPostsWidget, etc.
];
function InspectorSidebar() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
    className: "inspector-sidebar",
    children: WIDGETS.map((Widget, index) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(Widget, {}, index))
  });
}

/***/ }),

/***/ "./src/social-web/components/inspector-sidebar/style.scss":
/*!****************************************************************!*\
  !*** ./src/social-web/components/inspector-sidebar/style.scss ***!
  \****************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/components/inspector-sidebar/widgets/index.ts":
/*!**********************************************************************!*\
  !*** ./src/social-web/components/inspector-sidebar/widgets/index.ts ***!
  \**********************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   NavigationWidget: () => (/* reexport safe */ _navigation_widget__WEBPACK_IMPORTED_MODULE_1__["default"]),
/* harmony export */   TrendingWidget: () => (/* reexport safe */ _trending_widget__WEBPACK_IMPORTED_MODULE_2__["default"])
/* harmony export */ });
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/components/inspector-sidebar/widgets/style.scss");
/* harmony import */ var _navigation_widget__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./navigation-widget */ "./src/social-web/components/inspector-sidebar/widgets/navigation-widget.tsx");
/* harmony import */ var _trending_widget__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./trending-widget */ "./src/social-web/components/inspector-sidebar/widgets/trending-widget.tsx");
/**
 * Inspector Sidebar Widgets
 *
 * Export all available widgets for the inspector sidebar
 */





/***/ }),

/***/ "./src/social-web/components/inspector-sidebar/widgets/navigation-widget.tsx":
/*!***********************************************************************************!*\
  !*** ./src/social-web/components/inspector-sidebar/widgets/navigation-widget.tsx ***!
  \***********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ NavigationWidget)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/cog.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/people.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/plus.js");
/* harmony import */ var _contexts_settings_context__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ../../../contexts/settings-context */ "./src/social-web/contexts/settings-context.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__);
/**
 * Navigation Widget Component
 *
 * Quick access links to common actions and settings
 */






function NavigationWidget() {
  const {
    adminUrl
  } = (0,_contexts_settings_context__WEBPACK_IMPORTED_MODULE_5__.useSettings)();
  const navigationItems = [{
    id: 'new-post',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('New Post', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
    href: `${adminUrl}post-new.php`
  }, {
    id: 'followers',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Followers', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
    href: `${adminUrl}users.php?page=activitypub-followers-list`
  }, {
    id: 'following',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Following', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
    href: `${adminUrl}users.php?page=activitypub-following-list`
  }, {
    id: 'settings',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Settings', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"],
    href: `${adminUrl}admin.php?page=activitypub`
  }];
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)("div", {
    className: "inspector-widget navigation-widget",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("h2", {
      className: "inspector-widget__title",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Quick Actions', 'activitypub')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("div", {
      className: "inspector-widget__content",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuGroup, {
        children: navigationItems.map(item => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuItem, {
          href: item.href,
          className: "menu-item",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Icon, {
            icon: item.icon,
            size: 20
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_6__.jsx)("span", {
            children: item.label
          })]
        }, item.id))
      })
    })]
  });
}

/***/ }),

/***/ "./src/social-web/components/inspector-sidebar/widgets/style.scss":
/*!************************************************************************!*\
  !*** ./src/social-web/components/inspector-sidebar/widgets/style.scss ***!
  \************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/components/inspector-sidebar/widgets/trending-widget.tsx":
/*!*********************************************************************************!*\
  !*** ./src/social-web/components/inspector-sidebar/widgets/trending-widget.tsx ***!
  \*********************************************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ TrendingWidget)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _popular_tags__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../../popular-tags */ "./src/social-web/components/popular-tags/index.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * Trending Widget Component
 *
 * Displays trending/popular tags for the feed
 */




function TrendingWidget() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsxs)("div", {
    className: "inspector-widget trending-widget",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("h2", {
      className: "inspector-widget__title",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Trending', 'activitypub')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
      className: "inspector-widget__content",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)(_popular_tags__WEBPACK_IMPORTED_MODULE_1__.PopularTags, {})
    })]
  });
}

/***/ }),

/***/ "./src/social-web/components/popular-tags/index.tsx":
/*!**********************************************************!*\
  !*** ./src/social-web/components/popular-tags/index.tsx ***!
  \**********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PopularTags: () => (/* binding */ PopularTags)
/* harmony export */ });
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _hooks_use_tag_filter__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../hooks/use-tag-filter */ "./src/social-web/hooks/use-tag-filter.ts");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./src/social-web/components/popular-tags/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Popular Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of popular tags
 */







function PopularTags() {
  const {
    records: tags,
    isResolving
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__.useEntityRecords)('taxonomy', 'ap_tag', {
    per_page: 5,
    orderby: 'count',
    order: 'desc',
    hide_empty: true
  });
  const {
    selectedTagId,
    updateTagFilter
  } = (0,_hooks_use_tag_filter__WEBPACK_IMPORTED_MODULE_3__.useTagFilter)();

  // Toggle: if clicking the same tag, clear the filter
  const updateFilter = tagId => updateTagFilter(selectedTagId === tagId ? null : tagId);
  if (isResolving || !tags || tags.length === 0) {
    return null;
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("div", {
    className: "popular-tags",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuGroup, {
      children: tags.map(tag => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuItem, {
        onClick: () => updateFilter(tag.id),
        className: "menu-item",
        "aria-pressed": selectedTagId === tag.id,
        "aria-label": /* translators: %s: tag name */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Filter by tag: %s', 'activitypub'), tag.name),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("span", {
          children: ["#", tag.name]
        })
      }, tag.id))
    })
  });
}

/***/ }),

/***/ "./src/social-web/components/popular-tags/style.scss":
/*!***********************************************************!*\
  !*** ./src/social-web/components/popular-tags/style.scss ***!
  \***********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/social-web/hooks/use-tag-filter.ts":
/*!************************************************!*\
  !*** ./src/social-web/hooks/use-tag-filter.ts ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useTagFilter: () => (/* binding */ useTagFilter)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/views */ "./node_modules/@wordpress/views/build-module/index.js");
/**
 * WordPress dependencies
 */



/**
 * Hook to manage tag filtering in the feed view
 *
 * Provides a consistent way to read and update tag filters across components.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseTagFilterReturn} Selected tag ID and update function
 */
function useTagFilter() {
  const {
    view,
    updateView
  } = (0,_wordpress_views__WEBPACK_IMPORTED_MODULE_1__.useView)({
    kind: 'postType',
    name: 'ap_post',
    slug: 'feed',
    defaultView: {
      type: 'list',
      filters: []
    }
  });

  // Derive selected tag from view.filters
  const selectedTagId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    var _tagFilter$value;
    const tagFilter = view.filters?.find(f => f.field === 'ap_tag');
    const value = (_tagFilter$value = tagFilter?.value) !== null && _tagFilter$value !== void 0 ? _tagFilter$value : [];

    // Only highlight when exactly one tag is selected
    return value.length === 1 ? value[0] : null;
  }, [view.filters]);

  // Update tag filter with toggle support
  const updateTagFilter = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((tagId, options = {}) => {
    const currentFilters = view.filters || [];
    const tagFilterIndex = currentFilters.findIndex(f => f.field === 'ap_tag');
    let newFilters;
    if (tagId === null) {
      // Clear tag filter
      newFilters = currentFilters.filter(f => f.field !== 'ap_tag');
    } else if (tagFilterIndex !== -1) {
      // Tag filter exists - toggle it
      const currentValue = currentFilters[tagFilterIndex].value;
      if (Array.isArray(currentValue) && currentValue.includes(tagId)) {
        // Remove the tag filter if it's the same tag
        newFilters = currentFilters.filter(f => f.field !== 'ap_tag');
      } else {
        // Replace with new tag
        newFilters = [...currentFilters.slice(0, tagFilterIndex), {
          field: 'ap_tag',
          operator: 'isAny',
          value: [tagId]
        }, ...currentFilters.slice(tagFilterIndex + 1)];
      }
    } else {
      // No tag filter exists - add one
      newFilters = [...currentFilters, {
        field: 'ap_tag',
        operator: 'isAny',
        value: [tagId]
      }];
    }

    // Update the view with new filters
    updateView({
      ...view,
      filters: newFilters,
      page: 1 // Reset to first page
    });

    // Call completion callback if provided
    if (options.onComplete) {
      options.onComplete();
    }
  }, [view, updateView]);
  return {
    selectedTagId,
    updateTagFilter
  };
}

/***/ }),

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
/* harmony import */ var _hooks_use_tag_filter__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../hooks/use-tag-filter */ "./src/social-web/hooks/use-tag-filter.ts");
/* harmony import */ var _components_inspector_sidebar__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../components/inspector-sidebar */ "./src/social-web/components/inspector-sidebar/index.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);
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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("div", {
    dangerouslySetInnerHTML: {
      __html: decoded
    }
  });
};
function FeedInspector({
  id,
  onClose
}) {
  // Show sidebar when no post is selected
  if (!id) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_components_inspector_sidebar__WEBPACK_IMPORTED_MODULE_8__["default"], {});
  }
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
    order: 'asc',
    orderby: 'date'
  });

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
  const avatarUrl = actor?.icon || '';
  const postLink = post.link || '';
  const relativeTime = post.date ? (0,_utils__WEBPACK_IMPORTED_MODULE_6__.getRelativeTime)(post.date) : '';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
    className: "activitypub-inspector",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Card, {
      className: "activitypub-inspector-card",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.CardHeader, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsxs)("div", {
          className: "activitypub-inspector-header",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)("img", {
            src: avatarUrl,
            alt: author,
            className: "activitypub-inspector-avatar",
            onError: e => {
              e.target.src = defaultAvatar;
            }
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