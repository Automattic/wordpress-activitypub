/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/app/components/actor-switcher/index.tsx":
/*!*****************************************************!*\
  !*** ./src/app/components/actor-switcher/index.tsx ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ActorSwitcher)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../store */ "./src/app/store/index.ts");
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../router */ "./src/app/router/index.tsx");
/* harmony import */ var _site_icon__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../site-icon */ "./src/app/components/site-icon/index.tsx");
/* harmony import */ var _avatar__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../avatar */ "./src/app/components/avatar/index.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/actor-switcher/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__);
/**
 * Actor Switcher Component
 *
 * Displays current actor (user or site) and allows switching between user and blog actors
 * based on user capabilities and actor mode settings.
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






// Actor mode constants matching PHP definitions.
// Hopefully temporary—there's just no good way to query these currently.

const ACTOR_MODE = 'actor';
const BLOG_MODE = 'blog';
const ACTOR_AND_BLOG_MODE = 'actor_blog';
function ActorSwitcher() {
  const navigate = (0,_router__WEBPACK_IMPORTED_MODULE_7__.useNavigate)();
  const {
    setActiveActor
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_store__WEBPACK_IMPORTED_MODULE_6__.STORE_NAME);
  const {
    currentUser,
    activeActorId,
    actorMode,
    hasUserCap,
    hasBlogCap
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    var _activitypub_actor_mo;
    return {
      currentUser: select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).getCurrentUser(),
      activeActorId: select(_store__WEBPACK_IMPORTED_MODULE_6__.STORE_NAME).getActiveActorId(),
      actorMode: (_activitypub_actor_mo = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).getEntityRecord('root', 'site')?.activitypub_actor_mode) !== null && _activitypub_actor_mo !== void 0 ? _activitypub_actor_mo : ACTOR_AND_BLOG_MODE,
      // Check if user has the activitypub capability (can create user extra fields).
      hasUserCap: select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).canUser('create', {
        kind: 'postType',
        name: 'ap_extrafield'
      }),
      // Check if user can manage options (can create blog extra fields).
      hasBlogCap: select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).canUser('create', {
        kind: 'postType',
        name: 'ap_extrafield_blog'
      })
    };
  }, []);

  // User can use their actor if user mode is enabled AND they have the capability.
  const userModeEnabled = actorMode === ACTOR_MODE || actorMode === ACTOR_AND_BLOG_MODE;
  const canUseUserActor = userModeEnabled && hasUserCap;

  // User can use the blog actor if blog mode is enabled AND they have the capability.
  const blogModeEnabled = actorMode === BLOG_MODE || actorMode === ACTOR_AND_BLOG_MODE;
  const canUseBlogActor = blogModeEnabled && hasBlogCap;
  const currentUserId = currentUser?.id;
  const canSwitchActors = canUseUserActor && canUseBlogActor;

  // Correct the active actor if it's not valid for the current mode.
  const isSiteActor = activeActorId === 0;
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_3__.useEffect)(() => {
    if (isSiteActor && !canUseBlogActor && canUseUserActor && currentUserId) {
      // Blog actor is selected but not available, switch to user actor.
      setActiveActor(currentUserId);
    } else if (!isSiteActor && !canUseUserActor && canUseBlogActor) {
      // User actor is selected but not available, switch to blog actor.
      setActiveActor(0);
    }
  }, [isSiteActor, canUseUserActor, canUseBlogActor, currentUserId, setActiveActor]);
  const userAvatarUrl = currentUser?.avatar_urls?.[48] || _avatar__WEBPACK_IMPORTED_MODULE_9__.DEFAULT_AVATAR;
  const displayName = isSiteActor ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Site', 'activitypub') : currentUser?.name || '';

  // Toggle between user and site actor.
  const onClick = () => {
    if (canSwitchActors && currentUserId) {
      setActiveActor(activeActorId === 0 ? currentUserId : 0);

      // Close inspector.
      void navigate({
        search: prev => {
          const {
            postId: _,
            ...rest
          } = prev;
          return rest;
        }
      });
    }
  };

  // Determine the appropriate settings link based on available actor.
  const href = canUseBlogActor && !canUseUserActor ? (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_5__.addQueryArgs)('options-general.php', {
    page: 'activitypub',
    tab: 'blog-profile'
  }) : 'profile.php#activitypub';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
    ...(canSwitchActors ? {
      onClick
    } : {
      href
    }),
    className: "actor-switcher",
    label: canSwitchActors ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Switch Actor', 'activitypub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Profile', 'activitypub'),
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHStack, {
      spacing: 2,
      alignment: "center",
      children: [isSiteActor ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)(_site_icon__WEBPACK_IMPORTED_MODULE_8__["default"], {
        className: "actor-switcher__avatar"
      }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("img", {
        src: userAvatarUrl,
        alt: displayName,
        className: "actor-switcher__avatar",
        onError: e => {
          e.currentTarget.src = _avatar__WEBPACK_IMPORTED_MODULE_9__.DEFAULT_AVATAR;
        }
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_11__.jsx)("span", {
        className: "actor-switcher__name",
        children: displayName
      })]
    })
  });
}

/***/ }),

/***/ "./src/app/components/actor-switcher/style.scss":
/*!******************************************************!*\
  !*** ./src/app/components/actor-switcher/style.scss ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/avatar/index.tsx":
/*!*********************************************!*\
  !*** ./src/app/components/avatar/index.tsx ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   DEFAULT_AVATAR: () => (/* binding */ DEFAULT_AVATAR),
/* harmony export */   "default": () => (/* binding */ Avatar)
/* harmony export */ });
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/avatar/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * Avatar component that displays an actor's avatar with fallback support.
 */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */



const DEFAULT_AVATAR = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200'%3E%3Crect width='200' height='200' fill='%23f0f0f0'/%3E%3Cpath fill='%23c6c6c6' d='M32,201 C22,201 12,201 1,201 C1,134 1,68 1,1 C68,1 134,1 201,1 C201,68 201,134 201,201 C194,201 186,201 178,201 C174,184 165,172 149,166 C145,164 139,163 134,162 C131,161 128,160 126,158 C123,156 122,154 126,151 C147,137 154,112 145,89 C139,70 122,58 104,59 C90,59 79,66 71,77 C54,101 60,135 84,151 C88,154 88,155 84,158 C81,160 78,161 75,162 C53,167 38,179 32,201z'/%3E%3C/svg%3E";
function Avatar({
  item
}) {
  const avatarUrl = item.actor_info?.icon || DEFAULT_AVATAR;
  const altText = item.actor_info?.name || item.actor_info?.username || '';
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("img", {
    alt: altText,
    src: avatarUrl,
    className: "activitypub-avatar",
    onError: e => {
      e.target.src = DEFAULT_AVATAR;
    },
    ...(!altText && {
      role: 'presentation'
    })
  });
}

/***/ }),

/***/ "./src/app/components/avatar/style.scss":
/*!**********************************************!*\
  !*** ./src/app/components/avatar/style.scss ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/layout/index.tsx":
/*!*********************************************!*\
  !*** ./src/app/components/layout/index.tsx ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Layout: () => (/* binding */ Layout)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_notices__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/notices */ "@wordpress/notices");
/* harmony import */ var _wordpress_notices__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_notices__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/compose */ "@wordpress/compose");
/* harmony import */ var _wordpress_compose__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_compose__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../../router */ "./src/app/router/index.tsx");
/* harmony import */ var _sidebar__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../sidebar */ "./src/app/components/sidebar/index.tsx");
/* harmony import */ var _site_hub__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../site-hub */ "./src/app/components/site-hub/index.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/layout/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__);
/**
 * Layout Component
 *
 * Three-panel layout system:
 * - Sidebar (300px fixed) - Navigation
 * - Stage (flexible) - Main content
 * - Inspector (380px fixed, optional) - Detail panel
 *
 * On mobile (<782px), shows:
 * - SiteHubMobile header with back button and menu toggle
 * - Animated sidebar drawer (slides in from left)
 * - Full-screen content area (stage or mobile component)
 *
 * Follows @wordpress/boot architecture patterns for future compatibility.
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





function Layout() {
  const isMobileViewport = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_4__.useViewportMatch)('medium', '<');
  const location = (0,_router__WEBPACK_IMPORTED_MODULE_6__.useLocation)();
  const disableMotion = (0,_wordpress_compose__WEBPACK_IMPORTED_MODULE_4__.useReducedMotion)();
  const [isMobileSidebarOpen, setIsMobileSidebarOpen] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useState)(false);

  // Get the current page title from menu items based on route
  const currentTitle = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useMemo)(() => {
    const menuItem = _sidebar__WEBPACK_IMPORTED_MODULE_7__.menuItems.find(item => item.path === location.pathname);
    return menuItem?.label || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Social Web', 'activitypub');
  }, [location.pathname]);

  // Auto-close sidebar on navigation or viewport change
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_2__.useEffect)(() => {
    setIsMobileSidebarOpen(false);
  }, [location.pathname, isMobileViewport]);

  // Get notices for the snackbar
  const notices = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    const {
      getNotices
    } = select(_wordpress_notices__WEBPACK_IMPORTED_MODULE_3__.store);
    return getNotices().filter(notice => notice.type === 'snackbar');
  }, []);
  const {
    removeNotice
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useDispatch)(_wordpress_notices__WEBPACK_IMPORTED_MODULE_3__.store);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
    className: "app-layout",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__unstableAnimatePresence, {
      children: isMobileViewport && isMobileSidebarOpen && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__unstableMotion.div, {
        className: "sidebar-backdrop",
        initial: {
          opacity: 0
        },
        animate: {
          opacity: 1
        },
        exit: {
          opacity: 0
        },
        transition: {
          type: 'tween',
          duration: disableMotion ? 0 : 0.2,
          ease: 'easeOut'
        },
        onClick: () => setIsMobileSidebarOpen(false),
        onKeyDown: event => {
          if (event.key === 'Escape') {
            setIsMobileSidebarOpen(false);
          }
        },
        role: "button",
        tabIndex: -1,
        "aria-label": (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Close menu', 'activitypub')
      })
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__unstableAnimatePresence, {
      children: isMobileViewport && isMobileSidebarOpen && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__unstableMotion.div, {
        className: "sidebar-region is-mobile",
        initial: {
          x: '-100%'
        },
        animate: {
          x: 0
        },
        exit: {
          x: '-100%'
        },
        transition: {
          type: 'tween',
          duration: disableMotion ? 0 : 0.2,
          ease: 'easeOut'
        },
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_sidebar__WEBPACK_IMPORTED_MODULE_7__["default"], {})
      })
    }), !isMobileViewport && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
      className: "app-content",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
        className: "sidebar-region",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_sidebar__WEBPACK_IMPORTED_MODULE_7__["default"], {})
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_router__WEBPACK_IMPORTED_MODULE_6__.Outlet, {})]
    }), isMobileViewport && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
      className: "app-content is-mobile",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_site_hub__WEBPACK_IMPORTED_MODULE_8__.SiteHubMobile, {
        title: currentTitle,
        onMenuClick: () => setIsMobileSidebarOpen(true)
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_router__WEBPACK_IMPORTED_MODULE_6__.Outlet, {})]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.SnackbarList, {
      notices: notices,
      onRemove: removeNotice
    })]
  });
}

/***/ }),

/***/ "./src/app/components/layout/style.scss":
/*!**********************************************!*\
  !*** ./src/app/components/layout/style.scss ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/object-types/index.tsx":
/*!***************************************************!*\
  !*** ./src/app/components/object-types/index.tsx ***!
  \***************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ObjectTypes: () => (/* binding */ ObjectTypes),
/* harmony export */   objectTypeConfig: () => (/* binding */ objectTypeConfig)
/* harmony export */ });
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/audio.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/calendar.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/comment.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/file.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/image.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/page.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/pin.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/post-content.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/video.js");
/* harmony import */ var _hooks_use_object_type_filter__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../../hooks/use-object-type-filter */ "./src/app/hooks/use-object-type-filter.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13__);
/**
 * Object Types Component
 *
 * Displays ap_object_type taxonomy terms as a clickable list of object types
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


// Object type configuration with translations and icons - matches object-type field definitions
const objectTypeConfig = {
  // @see Base_Object::TYPES
  Article: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Articles', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_10__["default"]
  },
  Note: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Notes & Updates', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"]
  },
  Image: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Photos & Images', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__["default"]
  },
  Event: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Events & Meetups', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"]
  },
  Video: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Videos', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_11__["default"]
  },
  Audio: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Music & Podcasts', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"]
  },
  Document: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Documents & Files', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__["default"]
  },
  Page: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Pages', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_8__["default"]
  },
  Place: {
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Places & Locations', 'activitypub'),
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_9__["default"]
  }
};
function ObjectTypes() {
  const {
    records: objectTypes,
    isResolving
  } = (0,_wordpress_core_data__WEBPACK_IMPORTED_MODULE_0__.useEntityRecords)('taxonomy', 'ap_object_type', {
    per_page: -1
  });
  const {
    selectedObjectTypeId,
    updateObjectTypeFilter
  } = (0,_hooks_use_object_type_filter__WEBPACK_IMPORTED_MODULE_12__.useObjectTypeFilter)();

  // Toggle: if clicking the same object type, clear the filter
  const updateFilter = objectTypeId => updateObjectTypeFilter(selectedObjectTypeId === objectTypeId ? null : objectTypeId);
  if (isResolving || !objectTypes || objectTypes.length === 0) {
    return null;
  }

  // Filter to only show known object types (those with config)
  const knownObjectTypes = objectTypes.filter(objectType => !!objectTypeConfig[objectType.name]);

  // Don't show the filter if there are no object types or only one type
  if (knownObjectTypes.length <= 1) {
    return null;
  }

  // Sort by the order in objectTypeConfig object
  const configOrder = Object.keys(objectTypeConfig);
  const sortedObjectTypes = [...knownObjectTypes].sort((a, b) => {
    const indexA = configOrder.indexOf(a.name);
    const indexB = configOrder.indexOf(b.name);
    return indexA - indexB;
  });
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuGroup, {
    className: "object-types-menu",
    children: sortedObjectTypes.map(objectType => {
      const config = objectTypeConfig[objectType.name];
      return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuItem, {
        onClick: () => updateFilter(objectType.id),
        className: "menu-item",
        "aria-pressed": selectedObjectTypeId === objectType.id,
        "aria-label": /* translators: %s: object type name */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.sprintf)((0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Filter by type: %s', 'activitypub'), config.label),
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Icon, {
          icon: config.icon,
          size: 24
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_13__.jsx)("span", {
          children: config.label
        })]
      }, objectType.id);
    })
  });
}

/***/ }),

/***/ "./src/app/components/panel/index.tsx":
/*!********************************************!*\
  !*** ./src/app/components/panel/index.tsx ***!
  \********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Panel)
/* harmony export */ });
/* harmony import */ var clsx__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! clsx */ "./node_modules/clsx/dist/clsx.mjs");
/* harmony import */ var _themed_surface__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ../themed-surface */ "./src/app/components/themed-surface/index.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/panel/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Panel Component
 *
 * A reusable surface wrapper for themed content areas.
 * Uses ThemedSurface component with margin spacing.
 */

/**
 * External dependencies
 */



/**
 * Internal dependencies
 */



function Panel({
  className,
  children
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)("div", {
    className: (0,clsx__WEBPACK_IMPORTED_MODULE_0__["default"])('panel', className),
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(_themed_surface__WEBPACK_IMPORTED_MODULE_1__["default"], {
      children: children
    })
  });
}

/***/ }),

/***/ "./src/app/components/panel/style.scss":
/*!*********************************************!*\
  !*** ./src/app/components/panel/style.scss ***!
  \*********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/popular-tags/index.tsx":
/*!***************************************************!*\
  !*** ./src/app/components/popular-tags/index.tsx ***!
  \***************************************************/
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
/* harmony import */ var _hooks_use_tag_filter__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ../../hooks/use-tag-filter */ "./src/app/hooks/use-tag-filter.ts");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/popular-tags/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * Popular Tags Component
 *
 * Displays ap_tag taxonomy terms as a clickable list of popular tags
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
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("div", {
    className: "popular-tags",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("h3", {
      className: "popular-tags__title",
      children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Popular Tags', 'activitypub')
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.MenuGroup, {
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
    })]
  });
}

/***/ }),

/***/ "./src/app/components/popular-tags/style.scss":
/*!****************************************************!*\
  !*** ./src/app/components/popular-tags/style.scss ***!
  \****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/sidebar/feed-description.tsx":
/*!*********************************************************!*\
  !*** ./src/app/components/sidebar/feed-description.tsx ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ FeedDescription)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../../store */ "./src/app/store/index.ts");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * Feed Description Component
 *
 * Shows context-aware description based on active actor.
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


function FeedDescription() {
  const activeActorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.useSelect)(select => select(_store__WEBPACK_IMPORTED_MODULE_2__.STORE_NAME).getActiveActorId(), []);
  const text = activeActorId === 0 ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Posts from accounts this site follows.', 'activitypub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('Posts from accounts you follow.', 'activitypub');
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.Fragment, {
    children: text
  });
}

/***/ }),

/***/ "./src/app/components/sidebar/index.tsx":
/*!**********************************************!*\
  !*** ./src/app/components/sidebar/index.tsx ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Sidebar),
/* harmony export */   menuItems: () => (/* binding */ menuItems)
/* harmony export */ });
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/chevron-left.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/chevron-right.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/cog.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/post-list.js");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _hooks_use_feed_filters__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../../hooks/use-feed-filters */ "./src/app/hooks/use-feed-filters.ts");
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../../router */ "./src/app/router/index.tsx");
/* harmony import */ var _site_hub__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../site-hub */ "./src/app/components/site-hub/index.tsx");
/* harmony import */ var _actor_switcher__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ../actor-switcher */ "./src/app/components/actor-switcher/index.tsx");
/* harmony import */ var _object_types__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ../object-types */ "./src/app/components/object-types/index.tsx");
/* harmony import */ var _popular_tags__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ../popular-tags */ "./src/app/components/popular-tags/index.tsx");
/* harmony import */ var _feed_description__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./feed-description */ "./src/app/components/sidebar/feed-description.tsx");
/* harmony import */ var _menu_description__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ./menu-description */ "./src/app/components/sidebar/menu-description.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/sidebar/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__);
/**
 * Sidebar Component
 *
 * Navigation sidebar with menu items for different sections
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










/**
 * Menu item configuration for sidebar navigation.
 * Each item maps to a route path.
 */

const menuItems = [{
  id: 'feed',
  path: '/',
  label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Feed', 'activitypub'),
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_4__["default"],
  description: _feed_description__WEBPACK_IMPORTED_MODULE_13__["default"]
}];
function Sidebar() {
  const location = (0,_router__WEBPACK_IMPORTED_MODULE_8__.useLocation)();
  const navigate = (0,_router__WEBPACK_IMPORTED_MODULE_8__.useNavigate)();
  const {
    hasActiveFilters,
    clearAllFilters
  } = (0,_hooks_use_feed_filters__WEBPACK_IMPORTED_MODULE_7__.useFeedFilters)();

  // Check if a route is currently active
  const isRouteActive = path => location.pathname === path;

  // For feed route, also consider filters for "selected" state
  const isFeedFullySelected = isRouteActive('/') && !hasActiveFilters;

  // Handle menu item click - navigate and clear filters if going to feed
  const handleMenuItemClick = path => {
    if (path === '/') {
      clearAllFilters();
    }
    void navigate({
      to: path
    });
  };
  const activeItem = menuItems.find(item => isRouteActive(item.path));
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsxs)("div", {
    className: "sidebar",
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_site_hub__WEBPACK_IMPORTED_MODULE_9__["default"], {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsxs)("nav", {
      className: "nav",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHStack, {
        spacing: 3,
        alignment: "flex-start",
        className: "sidebar-navigation__icon-title",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
          className: "sidebar-navigation__button",
          size: "compact",
          icon: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.isRTL)() ? _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"] : _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__["default"],
          href: "/wp-admin/",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Go to the Dashboard', 'activitypub')
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHeading, {
          className: "sidebar-navigation__title",
          level: 1,
          size: 20,
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Social Web', 'activitypub')
        })]
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.NavigableMenu, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_menu_description__WEBPACK_IMPORTED_MODULE_14__["default"], {
          menuItem: activeItem
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.MenuGroup, {
          children: menuItems.map(item => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.MenuItem, {
            isSelected: item.path === '/' ? isFeedFullySelected : isRouteActive(item.path),
            onClick: () => handleMenuItemClick(item.path),
            className: "menu-item",
            children: [item.icon && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Icon, {
              icon: item.icon,
              size: 24
            }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)("span", {
              children: item.label
            })]
          }, item.id))
        })]
      }), isRouteActive('/') && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_object_types__WEBPACK_IMPORTED_MODULE_11__.ObjectTypes, {})]
    }), isRouteActive('/') && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_popular_tags__WEBPACK_IMPORTED_MODULE_12__.PopularTags, {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)("div", {
      className: "footer",
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.__experimentalHStack, {
        justify: "space-between",
        alignment: "center",
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_actor_switcher__WEBPACK_IMPORTED_MODULE_10__["default"], {}), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_16__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_0__.Button, {
          icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_3__["default"],
          iconSize: 20,
          size: "compact",
          href: (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_6__.addQueryArgs)('admin.php', {
            page: 'activitypub'
          }),
          target: "_blank",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_5__.__)('Settings', 'activitypub'),
          className: "footer-settings-button"
        })]
      })
    })]
  });
}

/***/ }),

/***/ "./src/app/components/sidebar/menu-description.tsx":
/*!*********************************************************!*\
  !*** ./src/app/components/sidebar/menu-description.tsx ***!
  \*********************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ MenuDescription)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__);

/**
 * Menu Description Component
 *
 * Renders the description for a menu item.
 * Supports string, function, or component descriptions.
 */

/**
 * External dependencies
 */

/**
 * Internal dependencies
 */

function MenuDescription({
  menuItem: {
    description
  }
}) {
  if (!description) {
    return null;
  }
  if (typeof description === 'string') {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("p", {
      className: "sidebar-description",
      children: description
    });
  }
  const Description = description;
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("p", {
    className: "sidebar-description",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(Description, {})
  });
}

/***/ }),

/***/ "./src/app/components/sidebar/style.scss":
/*!***********************************************!*\
  !*** ./src/app/components/sidebar/style.scss ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/site-hub/index.tsx":
/*!***********************************************!*\
  !*** ./src/app/components/site-hub/index.tsx ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SiteHubMobile: () => (/* binding */ SiteHubMobile),
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/html-entities */ "@wordpress/html-entities");
/* harmony import */ var _wordpress_html_entities__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/chevron-left.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/chevron-right.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/menu.js");
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/search.js");
/* harmony import */ var _wordpress_commands__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @wordpress/commands */ "@wordpress/commands");
/* harmony import */ var _wordpress_commands__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(_wordpress_commands__WEBPACK_IMPORTED_MODULE_9__);
/* harmony import */ var _wordpress_keycodes__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! @wordpress/keycodes */ "@wordpress/keycodes");
/* harmony import */ var _wordpress_keycodes__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(_wordpress_keycodes__WEBPACK_IMPORTED_MODULE_10__);
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! @wordpress/url */ "@wordpress/url");
/* harmony import */ var _wordpress_url__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(_wordpress_url__WEBPACK_IMPORTED_MODULE_11__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_12___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_12__);
/* harmony import */ var _site_icon__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ../site-icon */ "./src/app/components/site-icon/index.tsx");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/site-hub/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__);
/**
 * Site Hub Component
 *
 * Displays site icon, title, and command palette toggle.
 * SiteHubMobile provides a mobile-specific header with back navigation and menu button.
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



function SiteHub() {
  const {
    homeUrl,
    siteTitle
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.useSelect)(select => {
    const {
      getEntityRecord
    } = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__.store);
    const _base = getEntityRecord('root', '__unstableBase');
    return {
      homeUrl: _base?.home,
      siteTitle: !_base?.name && !!_base?.url ? (0,_wordpress_url__WEBPACK_IMPORTED_MODULE_11__.filterURLForDisplay)(_base?.url) : _base?.name
    };
  }, []);
  const {
    open: openCommandCenter
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.useDispatch)(_wordpress_commands__WEBPACK_IMPORTED_MODULE_9__.store);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
    className: "site-hub",
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.__experimentalHStack, {
      justify: "flex-start",
      spacing: "0",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
        className: "site-hub__icon-container",
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
          __next40pxDefaultSize: true,
          href: "/wp-admin/",
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Go to the Dashboard', 'activitypub'),
          className: "site-hub__icon-button",
          style: {
            transform: 'scale(0.5333) translateX(-4px)',
            // Offset to position the icon 12px from viewport edge
            borderRadius: 4
          },
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_site_icon__WEBPACK_IMPORTED_MODULE_13__["default"], {
            className: "site-hub__icon"
          })
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.__experimentalHStack, {
        children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
          className: "site-hub__title",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            variant: "link",
            href: homeUrl,
            target: "_blank",
            children: [(0,_wordpress_html_entities__WEBPACK_IMPORTED_MODULE_4__.decodeEntities)(siteTitle), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.VisuallyHidden, {
              as: "span",
              children: /* translators: accessibility text */
              (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('(opens in a new tab)', 'activitypub')
            })]
          })
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.__experimentalHStack, {
          spacing: 0,
          expanded: false,
          className: "site-hub__actions",
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
            size: "compact",
            className: "site-hub__command-button",
            icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_8__["default"],
            onClick: openCommandCenter,
            label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Open command palette', 'activitypub'),
            shortcut: _wordpress_keycodes__WEBPACK_IMPORTED_MODULE_10__.displayShortcut.primary('k')
          })
        })]
      })]
    })
  });
}

/**
 * Mobile Site Hub props.
 */

/**
 * Mobile Site Hub Component
 *
 * Provides a mobile-specific header with:
 * - Back button (chevron) that navigates to dashboard
 * - Title showing the current navigation context
 * - Menu button (hamburger) to open sidebar drawer
 */
const SiteHubMobile = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_12__.forwardRef)(function SiteHubMobile({
  onMenuClick,
  title
}, ref) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)("div", {
    className: "site-hub-mobile",
    ref: ref,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.__experimentalHStack, {
      spacing: 2,
      justify: "flex-start",
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
        icon: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.isRTL)() ? _wordpress_icons__WEBPACK_IMPORTED_MODULE_6__["default"] : _wordpress_icons__WEBPACK_IMPORTED_MODULE_5__["default"],
        href: "/wp-admin/",
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Go to the Dashboard', 'activitypub'),
        className: "site-hub-mobile__button",
        size: "compact"
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("span", {
        className: "site-hub-mobile__title",
        children: title || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Social Web', 'activitypub')
      })]
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Button, {
      icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_7__["default"],
      onClick: onMenuClick,
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Open menu', 'activitypub'),
      className: "site-hub-mobile__button",
      size: "compact"
    })]
  });
});
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SiteHub);

/***/ }),

/***/ "./src/app/components/site-hub/style.scss":
/*!************************************************!*\
  !*** ./src/app/components/site-hub/style.scss ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/site-icon/index.tsx":
/*!************************************************!*\
  !*** ./src/app/components/site-icon/index.tsx ***!
  \************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var clsx__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! clsx */ "./node_modules/clsx/dist/clsx.mjs");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/wordpress.js");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/site-icon/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__);
/**
 * External dependencies
 */



/**
 * WordPress dependencies
 */





/**
 * Internal dependencies
 */


function SiteIcon({
  className
}) {
  const {
    isRequestingSite,
    siteIconUrl
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const {
      getEntityRecord
    } = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_5__.store);
    const siteData = getEntityRecord('root', '__unstableBase', undefined);
    return {
      isRequestingSite: !siteData,
      siteIconUrl: siteData?.site_icon_url
    };
  }, []);
  let icon = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.Icon, {
    className: "site-icon__icon",
    icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_2__["default"],
    size: 32
  });
  if (isRequestingSite) {
    icon = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
      className: "site-icon__image"
    });
  } else if (siteIconUrl) {
    icon = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("img", {
      className: "site-icon__image",
      alt: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_4__.__)('Site Icon', 'activitypub'),
      src: siteIconUrl
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_7__.jsx)("div", {
    className: (0,clsx__WEBPACK_IMPORTED_MODULE_0__["default"])(className, 'site-icon'),
    children: icon
  });
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (SiteIcon);

/***/ }),

/***/ "./src/app/components/site-icon/style.scss":
/*!*************************************************!*\
  !*** ./src/app/components/site-icon/style.scss ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/components/themed-surface/index.tsx":
/*!*****************************************************!*\
  !*** ./src/app/components/themed-surface/index.tsx ***!
  \*****************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ ThemedSurface)
/* harmony export */ });
/* harmony import */ var clsx__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! clsx */ "./node_modules/clsx/dist/clsx.mjs");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./style.scss */ "./src/app/components/themed-surface/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__);
/**
 * ThemedSurface Component
 *
 * This component wraps content with appropriate theme context for consistent styling.
 */

/**
 * External dependencies
 */



/**
 * Internal dependencies
 */


/**
 * ThemedSurface component
 *
 * Wraps content in a themed surface with light background.
 * Uses wpds design tokens that are provided by ThemeProvider context.
 */
function ThemedSurface({
  className,
  children
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_2__.jsx)("div", {
    className: (0,clsx__WEBPACK_IMPORTED_MODULE_0__["default"])('themed-surface', className),
    children: children
  });
}

/***/ }),

/***/ "./src/app/components/themed-surface/style.scss":
/*!******************************************************!*\
  !*** ./src/app/components/themed-surface/style.scss ***!
  \******************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/app/contexts/object-type-context.tsx":
/*!**************************************************!*\
  !*** ./src/app/contexts/object-type-context.tsx ***!
  \**************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ObjectTypeProvider: () => (/* binding */ ObjectTypeProvider),
/* harmony export */   useObjectType: () => (/* binding */ useObjectType)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__);
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */




const ObjectTypeContext = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createContext)({
  getObjectTypeName: () => null,
  isLoading: true
});

// Type for core-data store with isResolving selector (not in official types)

function ObjectTypeProvider({
  children
}) {
  const {
    terms,
    isResolving
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_1__.useSelect)(select => {
    const store = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store);
    return {
      terms: store.getEntityRecords('taxonomy', 'ap_object_type', {
        per_page: -1
      }),
      isResolving: store.isResolving('getEntityRecords', ['taxonomy', 'ap_object_type', {
        per_page: -1
      }])
    };
  }, []);

  // Create a lookup map for fast access
  const termMap = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    if (!terms) {
      return new Map();
    }
    return new Map(terms.map(term => [term.id, term.name]));
  }, [terms]);
  const getObjectTypeName = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(id => {
    if (!id) {
      return null;
    }
    return termMap.get(id) || null;
  }, [termMap]);
  const value = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => ({
    getObjectTypeName,
    isLoading: isResolving
  }), [getObjectTypeName, isResolving]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_3__.jsx)(ObjectTypeContext.Provider, {
    value: value,
    children: children
  });
}
function useObjectType() {
  return (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useContext)(ObjectTypeContext);
}

/***/ }),

/***/ "./src/app/contexts/settings-context.tsx":
/*!***********************************************!*\
  !*** ./src/app/contexts/settings-context.tsx ***!
  \***********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SettingsProvider: () => (/* binding */ SettingsProvider),
/* harmony export */   useSettings: () => (/* binding */ useSettings)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */

const SettingsContext = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createContext)(undefined);
function SettingsProvider({
  children,
  settings
}) {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)(SettingsContext.Provider, {
    value: settings,
    children: children
  });
}
function useSettings() {
  const settings = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useContext)(SettingsContext);
  if (!settings) {
    throw new Error('useSettings must be used within a SettingsProvider');
  }
  return settings;
}

/***/ }),

/***/ "./src/app/hooks/use-feed-filters.ts":
/*!*******************************************!*\
  !*** ./src/app/hooks/use-feed-filters.ts ***!
  \*******************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useFeedFilters: () => (/* binding */ useFeedFilters)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/views */ "@wordpress/views");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_views__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */


/**
 * Hook to manage feed filters
 *
 * Provides utilities to detect if any filters are active and clear them all.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseFeedFiltersReturn} Filter status and clear function
 */
function useFeedFilters() {
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

  // Check if any filters are active
  const hasActiveFilters = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    var _view$filters$length;
    return ((_view$filters$length = view.filters?.length) !== null && _view$filters$length !== void 0 ? _view$filters$length : 0) > 0;
  }, [view.filters]);

  // Clear all filters
  const clearAllFilters = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)(() => {
    updateView({
      ...view,
      filters: [],
      page: 1 // Reset to first page
    });
  }, [view, updateView]);
  return {
    hasActiveFilters,
    clearAllFilters
  };
}

/***/ }),

/***/ "./src/app/hooks/use-object-type-filter.ts":
/*!*************************************************!*\
  !*** ./src/app/hooks/use-object-type-filter.ts ***!
  \*************************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useObjectTypeFilter: () => (/* binding */ useObjectTypeFilter)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/views */ "@wordpress/views");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_views__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../router */ "./src/app/router/index.tsx");
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
 * Hook to manage object type filtering in the feed view
 *
 * Provides a consistent way to read and update object type filters across components.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseObjectTypeFilterReturn} Selected object type ID and update function
 */
function useObjectTypeFilter() {
  const navigate = (0,_router__WEBPACK_IMPORTED_MODULE_2__.useNavigate)();
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

  // Derive selected object type from view.filters
  const selectedObjectTypeId = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useMemo)(() => {
    var _objectTypeFilter$val;
    const objectTypeFilter = view.filters?.find(f => f.field === 'ap_object_type');
    // With 'is' operator, value is a single number, not an array
    return (_objectTypeFilter$val = objectTypeFilter?.value) !== null && _objectTypeFilter$val !== void 0 ? _objectTypeFilter$val : null;
  }, [view.filters]);

  // Update object type filter with toggle support
  const updateObjectTypeFilter = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.useCallback)((objectTypeId, options = {}) => {
    const currentFilters = view.filters || [];
    const objectTypeFilterIndex = currentFilters.findIndex(f => f.field === 'ap_object_type');
    let newFilters;
    if (objectTypeId === null) {
      // Clear object type filter
      newFilters = currentFilters.filter(f => f.field !== 'ap_object_type');
    } else if (objectTypeFilterIndex !== -1) {
      // Object type filter exists - toggle it
      const currentValue = currentFilters[objectTypeFilterIndex].value;
      if (currentValue === objectTypeId) {
        // Remove the object type filter if it's the same object type
        newFilters = currentFilters.filter(f => f.field !== 'ap_object_type');
      } else {
        // Replace with new object type
        newFilters = [...currentFilters.slice(0, objectTypeFilterIndex), {
          field: 'ap_object_type',
          operator: 'is',
          value: objectTypeId
        }, ...currentFilters.slice(objectTypeFilterIndex + 1)];
      }
    } else {
      // No object type filter exists - add one
      newFilters = [...currentFilters, {
        field: 'ap_object_type',
        operator: 'is',
        value: objectTypeId
      }];
    }

    // Update the view with new filters
    updateView({
      ...view,
      filters: newFilters,
      page: 1 // Reset to first page
    });

    // Close inspector by removing postId from URL
    void navigate({
      search: prev => {
        const {
          postId: _,
          ...rest
        } = prev;
        return rest;
      }
    });

    // Call completion callback if provided
    if (options.onComplete) {
      options.onComplete();
    }
  }, [view, updateView, navigate]);
  return {
    selectedObjectTypeId,
    updateObjectTypeFilter
  };
}

/***/ }),

/***/ "./src/app/hooks/use-tag-filter.ts":
/*!*****************************************!*\
  !*** ./src/app/hooks/use-tag-filter.ts ***!
  \*****************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useTagFilter: () => (/* binding */ useTagFilter)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/views */ "@wordpress/views");
/* harmony import */ var _wordpress_views__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_views__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ../router */ "./src/app/router/index.tsx");
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
 * Hook to manage tag filtering in the feed view
 *
 * Provides a consistent way to read and update tag filters across components.
 * Uses `view.filters` as the single source of truth.
 *
 * @return {UseTagFilterReturn} Selected tag ID and update function
 */
function useTagFilter() {
  const navigate = (0,_router__WEBPACK_IMPORTED_MODULE_2__.useNavigate)();
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

    // Close inspector by removing postId from URL
    void navigate({
      search: prev => {
        const {
          postId: _,
          ...rest
        } = prev;
        return rest;
      }
    });

    // Call completion callback if provided
    if (options.onComplete) {
      options.onComplete();
    }
  }, [view, updateView, navigate]);
  return {
    selectedTagId,
    updateTagFilter
  };
}

/***/ }),

/***/ "./src/app/index.tsx":
/*!***************************!*\
  !*** ./src/app/index.tsx ***!
  \***************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   initialize: () => (/* binding */ initialize)
/* harmony export */ });
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_keyboard_shortcuts__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/keyboard-shortcuts */ "@wordpress/keyboard-shortcuts");
/* harmony import */ var _wordpress_keyboard_shortcuts__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_keyboard_shortcuts__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _router__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./router */ "./src/app/router/index.tsx");
/* harmony import */ var _components_layout__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./components/layout */ "./src/app/components/layout/index.tsx");
/* harmony import */ var _contexts_settings_context__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./contexts/settings-context */ "./src/app/contexts/settings-context.tsx");
/* harmony import */ var _contexts_object_type_context__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./contexts/object-type-context */ "./src/app/contexts/object-type-context.tsx");
/* harmony import */ var _store__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./store */ "./src/app/store/index.ts");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./style.scss */ "./src/app/style.scss");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__);
/**
 * External dependencies
 */

/**
 * WordPress dependencies
 */




/**
 * Internal dependencies
 */




 // Import to register the store
 // Import all styles

/**
 * Route definitions for the App application.
 */

const routes = [{
  path: '/',
  contentLoader: () => Promise.all(/*! import() | app/feed-content */[__webpack_require__.e("app/vendors"), __webpack_require__.e("app/style-feed-content"), __webpack_require__.e("app/feed-content")]).then(__webpack_require__.bind(__webpack_require__, /*! ./routes/feed/content */ "./src/app/routes/feed/content.ts")),
  routeLoader: () => __webpack_require__.e(/*! import() | app/feed-route */ "app/feed-route").then(__webpack_require__.bind(__webpack_require__, /*! ./routes/feed/route */ "./src/app/routes/feed/route.ts"))
}];

/**
 * Initialize the App application.
 *
 * @param id       The ID of the root element.
 * @param settings The editor settings.
 */
function initialize(id, settings) {
  const target = document.getElementById(id);
  if (!target) {
    return;
  }
  const root = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_0__.createRoot)(target);
  root.render(/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_contexts_settings_context__WEBPACK_IMPORTED_MODULE_5__.SettingsProvider, {
    settings: settings,
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_contexts_object_type_context__WEBPACK_IMPORTED_MODULE_6__.ObjectTypeProvider, {
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_keyboard_shortcuts__WEBPACK_IMPORTED_MODULE_2__.ShortcutProvider, {
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_1__.SlotFillProvider, {
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_9__.jsx)(_router__WEBPACK_IMPORTED_MODULE_3__["default"], {
            routes: routes,
            rootComponent: _components_layout__WEBPACK_IMPORTED_MODULE_4__.Layout
          })
        })
      })
    })
  }));
}

// Extend Window interface for type safety.

// Export to window for inline script access.
window.wp = window.wp || {};
window.wp.activitypubApp = {
  initialize
};

/***/ }),

/***/ "./src/app/router/index.tsx":
/*!**********************************!*\
  !*** ./src/app/router/index.tsx ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Link: () => (/* binding */ Link),
/* harmony export */   Outlet: () => (/* reexport safe */ _tanstack_react_router__WEBPACK_IMPORTED_MODULE_3__.Outlet),
/* harmony export */   "default": () => (/* binding */ Router),
/* harmony export */   useLoaderData: () => (/* reexport safe */ _tanstack_react_router__WEBPACK_IMPORTED_MODULE_4__.useLoaderData),
/* harmony export */   useLocation: () => (/* reexport safe */ _tanstack_react_router__WEBPACK_IMPORTED_MODULE_10__.useLocation),
/* harmony export */   useNavigate: () => (/* reexport safe */ _tanstack_react_router__WEBPACK_IMPORTED_MODULE_8__.useNavigate),
/* harmony export */   useSearch: () => (/* reexport safe */ _tanstack_react_router__WEBPACK_IMPORTED_MODULE_9__.useSearch)
/* harmony export */ });
/* harmony import */ var _tanstack_history__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/history/dist/esm/index.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/fileRoute.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/link.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/Match.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/useLoaderData.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/route.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/router.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/RouterProvider.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/useNavigate.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/useSearch.js");
/* harmony import */ var _tanstack_react_router__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! @tanstack/react-router */ "./node_modules/@tanstack/react-router/dist/esm/useLocation.js");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_11___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_11__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_12___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_12__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_13___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_13__);
/* harmony import */ var _components_panel__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ../components/panel */ "./src/app/components/panel/index.tsx");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__);
/**
 * Router Component
 *
 * TanStack Router setup with custom history for ?p= query parameter format.
 * Based on @wordpress/boot package patterns for forward compatibility.
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



// Re-export hooks for use in route components



// Re-export Outlet for layout component


// Create Link component for navigation
const Link = (0,_tanstack_react_router__WEBPACK_IMPORTED_MODULE_2__.createLink)({
  defaultPreload: 'intent'
});

/**
 * Not found component displayed when no route matches.
 */
function NotFoundComponent() {
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
    style: {
      padding: '20px',
      textAlign: 'center'
    },
    children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_13__.__)('Page not found', 'activitypub')
  });
}

/**
 * Creates a TanStack route from a Route definition.
 *
 * Note: TanStack Router requires strictNullChecks which is not enabled globally.
 * We use 'any' types for the internal TanStack callbacks to work around this.
 *
 * @param route       Route configuration
 * @param parentRoute Parent route.
 * @return TanStack Route.
 */
async function createRouteFromDefinition(route, parentRoute) {
  let routeConfig = {};
  if (route.routeLoader) {
    const module = await route.routeLoader();
    routeConfig = module.route || {};
  }

  // Create base route configuration
  // Using 'any' for TanStack callbacks due to strictNullChecks requirement
  const baseRoute = (0,_tanstack_react_router__WEBPACK_IMPORTED_MODULE_5__.createRoute)({
    getParentRoute: () => parentRoute,
    path: route.path,
    beforeLoad: routeConfig.beforeLoad ? ctx => routeConfig.beforeLoad({
      params: ctx.params || {},
      search: ctx.search || {}
    }) : undefined,
    loader: async ctx => {
      const context = {
        params: ctx.params || {},
        search: ctx.deps || {}
      };
      const [, inspectorVisible] = await Promise.all([routeConfig.loader ? routeConfig.loader(context) : Promise.resolve(undefined), routeConfig.inspector ? routeConfig.inspector(context) : Promise.resolve(true)]);
      return {
        inspector: inspectorVisible
      };
    },
    loaderDeps: opts => opts.search
  });

  // Chain .lazy() to preload content module on intent
  const lazyRoute = baseRoute.lazy(async () => {
    const module = route.contentLoader ? await route.contentLoader() : {};
    const Stage = module.stage;
    const Inspector = module.inspector;
    return (0,_tanstack_react_router__WEBPACK_IMPORTED_MODULE_1__.createLazyRoute)(route.path)({
      component: function RouteComponent() {
        var _loaderData$inspector;
        const loaderData = (0,_tanstack_react_router__WEBPACK_IMPORTED_MODULE_4__.useLoaderData)({
          from: route.path
        });
        const showInspector = (_loaderData$inspector = loaderData?.inspector) !== null && _loaderData$inspector !== void 0 ? _loaderData$inspector : false;
        return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.Fragment, {
          children: [Stage && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
            className: "stage-region",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_panel__WEBPACK_IMPORTED_MODULE_14__["default"], {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(Stage, {})
            })
          }), Inspector && showInspector && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
            className: "inspector-region",
            children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_components_panel__WEBPACK_IMPORTED_MODULE_14__["default"], {
              children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(Inspector, {})
            })
          })]
        });
      }
    });
  });
  return lazyRoute;
}

/**
 * Creates a route tree from route definitions.
 *
 * @param routes        Routes definition.
 * @param rootComponent Root component to use for the router.
 * @return Router tree.
 */
async function createRouteTree(routes, rootComponent) {
  const rootRoute = (0,_tanstack_react_router__WEBPACK_IMPORTED_MODULE_5__.createRootRoute)({
    component: rootComponent,
    context: () => ({})
  });

  // Create routes from definitions
  const dynamicRoutes = await Promise.all(routes.map(route => createRouteFromDefinition(route, rootRoute)));
  return rootRoute.addChildren(dynamicRoutes);
}

/**
 * Create custom history that parses ?p= query parameter
 *
 * @return Custom browser history instance.
 */
function createPathHistory() {
  return (0,_tanstack_history__WEBPACK_IMPORTED_MODULE_0__.createBrowserHistory)({
    parseLocation: () => {
      const url = new URL(window.location.href);
      const path = url.searchParams.get('p') || '/';
      const pathHref = `${path}${url.hash}`;
      return (0,_tanstack_history__WEBPACK_IMPORTED_MODULE_0__.parseHref)(pathHref, window.history.state);
    },
    createHref: href => {
      const searchParams = new URLSearchParams(window.location.search);
      searchParams.set('p', href);
      return `${window.location.pathname}?${searchParams}`;
    }
  });
}
function Router({
  routes,
  rootComponent
}) {
  const [router, setRouter] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_11__.useState)(null);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_11__.useEffect)(() => {
    let cancelled = false;
    async function initializeRouter() {
      const history = createPathHistory();
      const routeTree = await createRouteTree(routes, rootComponent);
      if (!cancelled) {
        // TanStack Router requires strictNullChecks at the type level.
        // Cast to `never` to bypass the check since we can't enable it globally.
        const newRouter = (0,_tanstack_react_router__WEBPACK_IMPORTED_MODULE_6__.createRouter)({
          history,
          routeTree,
          defaultPreload: 'intent',
          defaultNotFoundComponent: NotFoundComponent
        });
        setRouter(newRouter);
      }
    }
    void initializeRouter();
    return () => {
      cancelled = true;
    };
  }, [routes, rootComponent]);
  if (!router) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)("div", {
      style: {
        padding: '20px',
        textAlign: 'center'
      },
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_12__.Spinner, {})
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_15__.jsx)(_tanstack_react_router__WEBPACK_IMPORTED_MODULE_7__.RouterProvider, {
    router: router
  });
}

/***/ }),

/***/ "./src/app/store/actions.ts":
/*!**********************************!*\
  !*** ./src/app/store/actions.ts ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   actions: () => (/* binding */ actions)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/preferences */ "@wordpress/preferences");
/* harmony import */ var _wordpress_preferences__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _types__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./types */ "./src/app/store/types.ts");
/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */



/**
 * Store actions
 */
const actions = {
  setActiveActor(actorId) {
    // Save to preferences
    (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)(_wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__.store).set('activitypub/app', 'activeActorId', actorId);
    return {
      type: _types__WEBPACK_IMPORTED_MODULE_2__.SET_ACTIVE_ACTOR,
      actorId
    };
  }
};

/***/ }),

/***/ "./src/app/store/index.ts":
/*!********************************!*\
  !*** ./src/app/store/index.ts ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   STORE_NAME: () => (/* binding */ STORE_NAME),
/* harmony export */   store: () => (/* binding */ store)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_data_controls__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/data-controls */ "@wordpress/data-controls");
/* harmony import */ var _wordpress_data_controls__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data_controls__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _actions__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./actions */ "./src/app/store/actions.ts");
/* harmony import */ var _selectors__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./selectors */ "./src/app/store/selectors.ts");
/* harmony import */ var _reducer__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./reducer */ "./src/app/store/reducer.ts");
/* harmony import */ var _resolvers__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./resolvers */ "./src/app/store/resolvers.ts");
/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */





/**
 * Store name
 */
const STORE_NAME = 'activitypub/app';

/**
 * Store configuration
 */

const storeConfig = {
  reducer: _reducer__WEBPACK_IMPORTED_MODULE_4__.reducer,
  actions: _actions__WEBPACK_IMPORTED_MODULE_2__.actions,
  selectors: _selectors__WEBPACK_IMPORTED_MODULE_3__.selectors,
  resolvers: _resolvers__WEBPACK_IMPORTED_MODULE_5__.resolvers,
  controls: _wordpress_data_controls__WEBPACK_IMPORTED_MODULE_1__.controls
};

/**
 * Create and register the store
 */
const store = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createReduxStore)(STORE_NAME, storeConfig);
(0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.register)(store);

/**
 * Re-export types for convenience
 */

/**
 * Store types for TypeScript
 */

/**
 * Type helpers for using the store
 */

/***/ }),

/***/ "./src/app/store/reducer.ts":
/*!**********************************!*\
  !*** ./src/app/store/reducer.ts ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   reducer: () => (/* binding */ reducer)
/* harmony export */ });
/* harmony import */ var _types__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./types */ "./src/app/store/types.ts");
/**
 * Internal dependencies
 */



/**
 * Store reducer
 */
function reducer(state = _types__WEBPACK_IMPORTED_MODULE_0__.DEFAULT_STATE, action) {
  switch (action.type) {
    case _types__WEBPACK_IMPORTED_MODULE_0__.SET_ACTIVE_ACTOR:
      return {
        ...state,
        activeActorId: action.actorId
      };
    default:
      return state;
  }
}

/***/ }),

/***/ "./src/app/store/resolvers.ts":
/*!************************************!*\
  !*** ./src/app/store/resolvers.ts ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getActiveActorId: () => (/* binding */ getActiveActorId),
/* harmony export */   resolvers: () => (/* binding */ resolvers)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/preferences */ "@wordpress/preferences");
/* harmony import */ var _wordpress_preferences__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _types__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./types */ "./src/app/store/types.ts");
/**
 * WordPress dependencies
 */




/**
 * Internal dependencies
 */


/**
 * Resolver to initialize the active actor from preferences
 */
function* getActiveActorId() {
  // Use sync select for preferences (already loaded)
  let actorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.select)(_wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__.store).get('activitypub/app', 'activeActorId');

  // No saved preference, initialize with current user ID
  if (actorId === undefined || actorId === null) {
    const currentUser = yield (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.resolveSelect)(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_2__.store).getCurrentUser();
    if (currentUser?.id) {
      actorId = currentUser.id;
      // Save to preferences for future loads
      (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.dispatch)(_wordpress_preferences__WEBPACK_IMPORTED_MODULE_1__.store).set('activitypub/app', 'activeActorId', actorId);
    }
  }

  // Return action to set the state
  if (actorId !== undefined) {
    return {
      type: _types__WEBPACK_IMPORTED_MODULE_3__.SET_ACTIVE_ACTOR,
      actorId
    };
  }
}
const resolvers = {
  getActiveActorId
};

/***/ }),

/***/ "./src/app/store/selectors.ts":
/*!************************************!*\
  !*** ./src/app/store/selectors.ts ***!
  \************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   selectors: () => (/* binding */ selectors)
/* harmony export */ });
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__);
/**
 * WordPress dependencies
 */



/**
 * Internal dependencies
 */

/**
 * Store selectors
 */
const selectors = {
  /**
   * Get the active actor ID, falling back to current user if not set.
   */
  getActiveActorId: (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_0__.createRegistrySelector)(select => state => {
    var _currentUser$id;
    if (state.activeActorId !== null) {
      return state.activeActorId;
    }

    // Fall back to current user ID if no actor is set
    const currentUser = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_1__.store).getCurrentUser();
    return (_currentUser$id = currentUser?.id) !== null && _currentUser$id !== void 0 ? _currentUser$id : null;
  })
};

/***/ }),

/***/ "./src/app/store/types.ts":
/*!********************************!*\
  !*** ./src/app/store/types.ts ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   DEFAULT_STATE: () => (/* binding */ DEFAULT_STATE),
/* harmony export */   SET_ACTIVE_ACTOR: () => (/* binding */ SET_ACTIVE_ACTOR)
/* harmony export */ });
/**
 * Store state interface
 */

/**
 * Action Types
 */
const SET_ACTIVE_ACTOR = 'SET_ACTIVE_ACTOR';
/**
 * Initial state
 */
const DEFAULT_STATE = {
  activeActorId: null
};

/***/ }),

/***/ "./src/app/style.scss":
/*!****************************!*\
  !*** ./src/app/style.scss ***!
  \****************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "@wordpress/commands":
/*!**********************************!*\
  !*** external ["wp","commands"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["commands"];

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

/***/ "@wordpress/data-controls":
/*!**************************************!*\
  !*** external ["wp","dataControls"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["dataControls"];

/***/ }),

/***/ "@wordpress/date":
/*!******************************!*\
  !*** external ["wp","date"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["date"];

/***/ }),

/***/ "@wordpress/dom":
/*!*****************************!*\
  !*** external ["wp","dom"] ***!
  \*****************************/
/***/ ((module) => {

module.exports = window["wp"]["dom"];

/***/ }),

/***/ "@wordpress/element":
/*!*********************************!*\
  !*** external ["wp","element"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["element"];

/***/ }),

/***/ "@wordpress/hooks":
/*!*******************************!*\
  !*** external ["wp","hooks"] ***!
  \*******************************/
/***/ ((module) => {

module.exports = window["wp"]["hooks"];

/***/ }),

/***/ "@wordpress/html-entities":
/*!**************************************!*\
  !*** external ["wp","htmlEntities"] ***!
  \**************************************/
/***/ ((module) => {

module.exports = window["wp"]["htmlEntities"];

/***/ }),

/***/ "@wordpress/i18n":
/*!******************************!*\
  !*** external ["wp","i18n"] ***!
  \******************************/
/***/ ((module) => {

module.exports = window["wp"]["i18n"];

/***/ }),

/***/ "@wordpress/keyboard-shortcuts":
/*!*******************************************!*\
  !*** external ["wp","keyboardShortcuts"] ***!
  \*******************************************/
/***/ ((module) => {

module.exports = window["wp"]["keyboardShortcuts"];

/***/ }),

/***/ "@wordpress/keycodes":
/*!**********************************!*\
  !*** external ["wp","keycodes"] ***!
  \**********************************/
/***/ ((module) => {

module.exports = window["wp"]["keycodes"];

/***/ }),

/***/ "@wordpress/notices":
/*!*********************************!*\
  !*** external ["wp","notices"] ***!
  \*********************************/
/***/ ((module) => {

module.exports = window["wp"]["notices"];

/***/ }),

/***/ "@wordpress/preferences":
/*!*************************************!*\
  !*** external ["wp","preferences"] ***!
  \*************************************/
/***/ ((module) => {

module.exports = window["wp"]["preferences"];

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

/***/ "@wordpress/views":
/*!*******************************!*\
  !*** external ["wp","views"] ***!
  \*******************************/
/***/ ((module) => {

module.exports = window["wp"]["views"];

/***/ }),

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

/***/ }),

/***/ "react-dom":
/*!***************************!*\
  !*** external "ReactDOM" ***!
  \***************************/
/***/ ((module) => {

module.exports = window["ReactDOM"];

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
/******/ 	/* webpack/runtime/create fake namespace object */
/******/ 	(() => {
/******/ 		var getProto = Object.getPrototypeOf ? (obj) => (Object.getPrototypeOf(obj)) : (obj) => (obj.__proto__);
/******/ 		var leafPrototypes;
/******/ 		// create a fake namespace object
/******/ 		// mode & 1: value is a module id, require it
/******/ 		// mode & 2: merge all properties of value into the ns
/******/ 		// mode & 4: return value when already ns object
/******/ 		// mode & 16: return value when it's Promise-like
/******/ 		// mode & 8|1: behave like require
/******/ 		__webpack_require__.t = function(value, mode) {
/******/ 			if(mode & 1) value = this(value);
/******/ 			if(mode & 8) return value;
/******/ 			if(typeof value === 'object' && value) {
/******/ 				if((mode & 4) && value.__esModule) return value;
/******/ 				if((mode & 16) && typeof value.then === 'function') return value;
/******/ 			}
/******/ 			var ns = Object.create(null);
/******/ 			__webpack_require__.r(ns);
/******/ 			var def = {};
/******/ 			leafPrototypes = leafPrototypes || [null, getProto({}), getProto([]), getProto(getProto)];
/******/ 			for(var current = mode & 2 && value; (typeof current == 'object' || typeof current == 'function') && !~leafPrototypes.indexOf(current); current = getProto(current)) {
/******/ 				Object.getOwnPropertyNames(current).forEach((key) => (def[key] = () => (value[key])));
/******/ 			}
/******/ 			def['default'] = () => (value);
/******/ 			__webpack_require__.d(ns, def);
/******/ 			return ns;
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
/******/ 	/* webpack/runtime/ensure chunk */
/******/ 	(() => {
/******/ 		__webpack_require__.f = {};
/******/ 		// This file contains only the entry chunk.
/******/ 		// The chunk loading function for additional chunks
/******/ 		__webpack_require__.e = (chunkId) => {
/******/ 			return Promise.all(Object.keys(__webpack_require__.f).reduce((promises, key) => {
/******/ 				__webpack_require__.f[key](chunkId, promises);
/******/ 				return promises;
/******/ 			}, []));
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/get javascript chunk filename */
/******/ 	(() => {
/******/ 		// This function allow to reference async chunks
/******/ 		__webpack_require__.u = (chunkId) => {
/******/ 			// return url for filenames not based on template
/******/ 			if (chunkId === "app/feed-content") return "" + chunkId + ".d1b1d388.js";
/******/ 			if (chunkId === "app/feed-route") return "" + chunkId + ".a1658013.js";
/******/ 			// return url for filenames based on template
/******/ 			return undefined;
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/get mini-css chunk filename */
/******/ 	(() => {
/******/ 		// This function allow to reference async chunks
/******/ 		__webpack_require__.miniCssF = (chunkId) => {
/******/ 			// return url for filenames based on template
/******/ 			return "" + chunkId + ".css";
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/load script */
/******/ 	(() => {
/******/ 		var inProgress = {};
/******/ 		var dataWebpackPrefix = "wordpress-activitypub:";
/******/ 		// loadScript function to load a script via script tag
/******/ 		__webpack_require__.l = (url, done, key, chunkId) => {
/******/ 			if(inProgress[url]) { inProgress[url].push(done); return; }
/******/ 			var script, needAttach;
/******/ 			if(key !== undefined) {
/******/ 				var scripts = document.getElementsByTagName("script");
/******/ 				for(var i = 0; i < scripts.length; i++) {
/******/ 					var s = scripts[i];
/******/ 					if(s.getAttribute("src") == url || s.getAttribute("data-webpack") == dataWebpackPrefix + key) { script = s; break; }
/******/ 				}
/******/ 			}
/******/ 			if(!script) {
/******/ 				needAttach = true;
/******/ 				script = document.createElement('script');
/******/ 		
/******/ 				script.charset = 'utf-8';
/******/ 				if (__webpack_require__.nc) {
/******/ 					script.setAttribute("nonce", __webpack_require__.nc);
/******/ 				}
/******/ 				script.setAttribute("data-webpack", dataWebpackPrefix + key);
/******/ 		
/******/ 				script.src = url;
/******/ 			}
/******/ 			inProgress[url] = [done];
/******/ 			var onScriptComplete = (prev, event) => {
/******/ 				// avoid mem leaks in IE.
/******/ 				script.onerror = script.onload = null;
/******/ 				clearTimeout(timeout);
/******/ 				var doneFns = inProgress[url];
/******/ 				delete inProgress[url];
/******/ 				script.parentNode && script.parentNode.removeChild(script);
/******/ 				doneFns && doneFns.forEach((fn) => (fn(event)));
/******/ 				if(prev) return prev(event);
/******/ 			}
/******/ 			var timeout = setTimeout(onScriptComplete.bind(null, undefined, { type: 'timeout', target: script }), 120000);
/******/ 			script.onerror = onScriptComplete.bind(null, script.onerror);
/******/ 			script.onload = onScriptComplete.bind(null, script.onload);
/******/ 			needAttach && document.head.appendChild(script);
/******/ 		};
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
/******/ 	/* webpack/runtime/publicPath */
/******/ 	(() => {
/******/ 		var scriptUrl;
/******/ 		if (globalThis.importScripts) scriptUrl = globalThis.location + "";
/******/ 		var document = globalThis.document;
/******/ 		if (!scriptUrl && document) {
/******/ 			if (document.currentScript && document.currentScript.tagName.toUpperCase() === 'SCRIPT')
/******/ 				scriptUrl = document.currentScript.src;
/******/ 			if (!scriptUrl) {
/******/ 				var scripts = document.getElementsByTagName("script");
/******/ 				if(scripts.length) {
/******/ 					var i = scripts.length - 1;
/******/ 					while (i > -1 && (!scriptUrl || !/^http(s?):/.test(scriptUrl))) scriptUrl = scripts[i--].src;
/******/ 				}
/******/ 			}
/******/ 		}
/******/ 		// When supporting browsers where an automatic publicPath is not supported you must specify an output.publicPath manually via configuration
/******/ 		// or pass an empty string ("") and set the __webpack_public_path__ variable from your code to use your own logic.
/******/ 		if (!scriptUrl) throw new Error("Automatic publicPath is not supported in this browser");
/******/ 		scriptUrl = scriptUrl.replace(/^blob:/, "").replace(/#.*$/, "").replace(/\?.*$/, "").replace(/\/[^\/]+$/, "/");
/******/ 		__webpack_require__.p = scriptUrl + "../";
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/css loading */
/******/ 	(() => {
/******/ 		if (typeof document === "undefined") return;
/******/ 		var createStylesheet = (chunkId, fullhref, oldTag, resolve, reject) => {
/******/ 			var linkTag = document.createElement("link");
/******/ 		
/******/ 			linkTag.rel = "stylesheet";
/******/ 			linkTag.type = "text/css";
/******/ 			if (__webpack_require__.nc) {
/******/ 				linkTag.nonce = __webpack_require__.nc;
/******/ 			}
/******/ 			var onLinkComplete = (event) => {
/******/ 				// avoid mem leaks.
/******/ 				linkTag.onerror = linkTag.onload = null;
/******/ 				if (event.type === 'load') {
/******/ 					resolve();
/******/ 				} else {
/******/ 					var errorType = event && event.type;
/******/ 					var realHref = event && event.target && event.target.href || fullhref;
/******/ 					var err = new Error("Loading CSS chunk " + chunkId + " failed.\n(" + errorType + ": " + realHref + ")");
/******/ 					err.name = "ChunkLoadError";
/******/ 					err.code = "CSS_CHUNK_LOAD_FAILED";
/******/ 					err.type = errorType;
/******/ 					err.request = realHref;
/******/ 					if (linkTag.parentNode) linkTag.parentNode.removeChild(linkTag)
/******/ 					reject(err);
/******/ 				}
/******/ 			}
/******/ 			linkTag.onerror = linkTag.onload = onLinkComplete;
/******/ 			linkTag.href = fullhref;
/******/ 		
/******/ 		
/******/ 			if (oldTag) {
/******/ 				oldTag.parentNode.insertBefore(linkTag, oldTag.nextSibling);
/******/ 			} else {
/******/ 				document.head.appendChild(linkTag);
/******/ 			}
/******/ 			return linkTag;
/******/ 		};
/******/ 		var findStylesheet = (href, fullhref) => {
/******/ 			var existingLinkTags = document.getElementsByTagName("link");
/******/ 			for(var i = 0; i < existingLinkTags.length; i++) {
/******/ 				var tag = existingLinkTags[i];
/******/ 				var dataHref = tag.getAttribute("data-href") || tag.getAttribute("href");
/******/ 				if(tag.rel === "stylesheet" && (dataHref === href || dataHref === fullhref)) return tag;
/******/ 			}
/******/ 			var existingStyleTags = document.getElementsByTagName("style");
/******/ 			for(var i = 0; i < existingStyleTags.length; i++) {
/******/ 				var tag = existingStyleTags[i];
/******/ 				var dataHref = tag.getAttribute("data-href");
/******/ 				if(dataHref === href || dataHref === fullhref) return tag;
/******/ 			}
/******/ 		};
/******/ 		var loadStylesheet = (chunkId) => {
/******/ 			return new Promise((resolve, reject) => {
/******/ 				var href = __webpack_require__.miniCssF(chunkId);
/******/ 				var fullhref = __webpack_require__.p + href;
/******/ 				if(findStylesheet(href, fullhref)) return resolve();
/******/ 				createStylesheet(chunkId, fullhref, null, resolve, reject);
/******/ 			});
/******/ 		}
/******/ 		// object to store loaded CSS chunks
/******/ 		var installedCssChunks = {
/******/ 			"app/index": 0
/******/ 		};
/******/ 		
/******/ 		__webpack_require__.f.miniCss = (chunkId, promises) => {
/******/ 			var cssChunks = {"app/style-feed-content":1};
/******/ 			if(installedCssChunks[chunkId]) promises.push(installedCssChunks[chunkId]);
/******/ 			else if(installedCssChunks[chunkId] !== 0 && cssChunks[chunkId]) {
/******/ 				promises.push(installedCssChunks[chunkId] = loadStylesheet(chunkId).then(() => {
/******/ 					installedCssChunks[chunkId] = 0;
/******/ 				}, (e) => {
/******/ 					delete installedCssChunks[chunkId];
/******/ 					throw e;
/******/ 				}));
/******/ 			}
/******/ 		};
/******/ 		
/******/ 		// no hmr
/******/ 		
/******/ 		// no prefetching
/******/ 		
/******/ 		// no preloaded
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
/******/ 			"app/index": 0,
/******/ 			"app/style-index": 0
/******/ 		};
/******/ 		
/******/ 		__webpack_require__.f.j = (chunkId, promises) => {
/******/ 				// JSONP chunk loading for javascript
/******/ 				var installedChunkData = __webpack_require__.o(installedChunks, chunkId) ? installedChunks[chunkId] : undefined;
/******/ 				if(installedChunkData !== 0) { // 0 means "already installed".
/******/ 		
/******/ 					// a Promise means "currently loading".
/******/ 					if(installedChunkData) {
/******/ 						promises.push(installedChunkData[2]);
/******/ 					} else {
/******/ 						if(!/^app\/style\-(feed\-content|index)$/.test(chunkId)) {
/******/ 							// setup Promise in chunk cache
/******/ 							var promise = new Promise((resolve, reject) => (installedChunkData = installedChunks[chunkId] = [resolve, reject]));
/******/ 							promises.push(installedChunkData[2] = promise);
/******/ 		
/******/ 							// start chunk loading
/******/ 							var url = __webpack_require__.p + __webpack_require__.u(chunkId);
/******/ 							// create error before stack unwound to get useful stacktrace later
/******/ 							var error = new Error();
/******/ 							var loadingEnded = (event) => {
/******/ 								if(__webpack_require__.o(installedChunks, chunkId)) {
/******/ 									installedChunkData = installedChunks[chunkId];
/******/ 									if(installedChunkData !== 0) installedChunks[chunkId] = undefined;
/******/ 									if(installedChunkData) {
/******/ 										var errorType = event && (event.type === 'load' ? 'missing' : event.type);
/******/ 										var realSrc = event && event.target && event.target.src;
/******/ 										error.message = 'Loading chunk ' + chunkId + ' failed.\n(' + errorType + ': ' + realSrc + ')';
/******/ 										error.name = 'ChunkLoadError';
/******/ 										error.type = errorType;
/******/ 										error.request = realSrc;
/******/ 										installedChunkData[1](error);
/******/ 									}
/******/ 								}
/******/ 							};
/******/ 							__webpack_require__.l(url, loadingEnded, "chunk-" + chunkId, chunkId);
/******/ 						} else installedChunks[chunkId] = 0;
/******/ 					}
/******/ 				}
/******/ 		};
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
/******/ 	/* webpack/runtime/nonce */
/******/ 	(() => {
/******/ 		__webpack_require__.nc = undefined;
/******/ 	})();
/******/ 	
/************************************************************************/
/******/ 	
/******/ 	// startup
/******/ 	// Load entry module and return exports
/******/ 	// This entry module depends on other loaded chunks and execution need to be delayed
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["app/tanstack-router","app/vendors","app/style-index"], () => (__webpack_require__("./src/app/index.tsx")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map