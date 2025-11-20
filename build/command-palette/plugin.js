/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

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

/***/ "react":
/*!************************!*\
  !*** external "React" ***!
  \************************/
/***/ ((module) => {

module.exports = window["React"];

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
/*!****************************************!*\
  !*** ./src/command-palette/plugin.tsx ***!
  \****************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(react__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__);
/**
 * ActivityPub Command Palette Integration
 *
 * Registers commands for the WordPress Command Palette (Cmd/Ctrl + K)
 * to provide quick navigation to ActivityPub admin pages.
 */







// Icon for ActivityPub commands - using the official ActivityPub plugin icon.
const activityPubIcon = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsxs)("svg", {
  xmlns: "http://www.w3.org/2000/svg",
  viewBox: "0 0 80 80",
  width: "24",
  height: "24",
  children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("rect", {
    width: "80",
    height: "80",
    fill: "#f1027e"
  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("path", {
    d: "M42.9 19.8L72 36.6v6.7L42.9 60.2v-6.7L66.2 40 42.9 26.6v-6.8z",
    fillRule: "evenodd",
    clipRule: "evenodd",
    fill: "white"
  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("path", {
    d: "M42.9 33.3L54.5 40l-11.6 6.7V33.3z",
    fillRule: "evenodd",
    clipRule: "evenodd",
    fill: "white"
  }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_5__.jsx)("path", {
    d: "M37.1 19.8L8 36.6v6.7l23.3-13.4v26.9l5.8 3.4V19.8zM25.5 40L13.8 46.7l11.6 6.7V40z",
    fillRule: "evenodd",
    clipRule: "evenodd",
    fill: "white"
  })]
});

// Get configuration from PHP.
const {
  actorMode,
  canManageOptions,
  followingEnabled
} = window.activitypubCommandPalette || {
  followingEnabled: false,
  actorMode: 'actor',
  canManageOptions: false
};

// Helper function to register a command.
const registerCommand = command => {
  try {
    (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/commands').registerCommand(command);
  } catch (error) {
    console.error('Failed to register ActivityPub command:', command.name, error);
  }
};

// Helper function to register a command loader for dynamic commands.
const registerCommandLoader = loaderConfig => {
  try {
    (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.dispatch)('core/commands').registerCommandLoader(loaderConfig);
  } catch (error) {
    console.error('Failed to register ActivityPub command loader:', loaderConfig.name, error);
  }
};

/**
 * Hook to load user extra fields as dynamic commands.
 */
const useExtraFieldsCommandLoader = ({
  search
}) => {
  // Retrieving the extra fields for the "search" term.
  const {
    records,
    isLoading
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => {
    const store = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__.store);
    const currentUser = store.getCurrentUser();
    const query = {
      search: !!search ? search : undefined,
      per_page: 10,
      orderby: search ? 'relevance' : 'date',
      status: 'any',
      author: currentUser?.id
    };
    return {
      records: store.getEntityRecords('postType', 'ap_extrafield', query),
      isLoading: !store.hasFinishedResolution('getEntityRecords', ['postType', 'ap_extrafield', query])
    };
  }, [search]);

  // Creating the commands.
  const commands = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useMemo)(() => {
    return (records !== null && records !== void 0 ? records : []).slice(0, 10).map(record => {
      const title = record.title?.rendered || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(no title)', 'activitypub');
      // Remove all quotes and special characters that could break CSS selectors.
      const sanitizedTitle = title.replace(/["'`]/g, '');
      return {
        // Use ID in the name to ensure uniqueness even with duplicate titles.
        name: `activitypub/edit-extra-field/${record.id}`,
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(/* translators: %s: Extra field title */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: Edit - %s', 'activitypub'), sanitizedTitle),
        icon: activityPubIcon,
        callback: ({
          close
        }) => {
          document.location = `post.php?post=${record.id}&action=edit`;
          close();
        }
      };
    });
  }, [records]);
  return {
    commands,
    isLoading
  };
};

/**
 * Hook to load blog extra fields as dynamic commands.
 */
const useBlogExtraFieldsCommandLoader = ({
  search
}) => {
  // Retrieving the blog extra fields for the "search" term.
  const {
    records,
    isLoading
  } = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_2__.useSelect)(select => {
    const store = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_3__.store);
    const query = {
      search: !!search ? search : undefined,
      per_page: 10,
      orderby: search ? 'relevance' : 'date',
      status: 'any'
    };
    return {
      records: store.getEntityRecords('postType', 'ap_extrafield_blog', query),
      isLoading: !store.hasFinishedResolution('getEntityRecords', ['postType', 'ap_extrafield_blog', query])
    };
  }, [search]);

  // Creating the commands.
  const commands = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useMemo)(() => {
    return (records !== null && records !== void 0 ? records : []).slice(0, 10).map(record => {
      const title = record.title?.rendered || (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('(no title)', 'activitypub');
      // Remove all quotes and special characters that could break CSS selectors.
      const sanitizedTitle = title.replace(/["'`]/g, '');
      return {
        // Use ID in the name to ensure uniqueness even with duplicate titles.
        name: `activitypub/edit-blog-extra-field/${record.id}`,
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.sprintf)(/* translators: %s: Blog extra field title */
        (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: Edit Blog - %s', 'activitypub'), sanitizedTitle),
        icon: activityPubIcon,
        callback: ({
          close
        }) => {
          document.location = `post.php?post=${record.id}&action=edit`;
          close();
        }
      };
    });
  }, [records]);
  return {
    commands,
    isLoading
  };
};

// User-specific commands (for actor and actor_blog modes).
if (actorMode === 'actor' || actorMode === 'actor_blog') {
  // User Followers command.
  registerCommand({
    name: 'activitypub/navigate-user-followers',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Your Followers', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'users.php?page=activitypub-followers-list';
      close();
    }
  });

  // User Following command (only if enabled).
  if (followingEnabled) {
    registerCommand({
      name: 'activitypub/navigate-user-following',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Who You Follow', 'activitypub'),
      icon: activityPubIcon,
      callback: ({
        close
      }) => {
        document.location.href = 'users.php?page=activitypub-following-list';
        close();
      }
    });
  }

  // User Extra Fields commands.
  registerCommand({
    name: 'activitypub/navigate-extra-fields',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Extra Fields', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'edit.php?post_type=ap_extrafield';
      close();
    }
  });
  registerCommand({
    name: 'activitypub/add-extra-field',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: Add New Extra Field', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'post-new.php?post_type=ap_extrafield';
      close();
    }
  });

  // Dynamic command loader: Edit existing extra fields.
  registerCommandLoader({
    name: 'activitypub/extra-fields-search',
    hook: useExtraFieldsCommandLoader
  });

  // Blocked Actors command (user-specific).
  registerCommand({
    name: 'activitypub/navigate-blocked-actors',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Blocked Actors', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'users.php?page=activitypub-blocked-actors-list';
      close();
    }
  });
}

// Blog-related commands (for blog and actor_blog modes with manage_options capability).
if (canManageOptions && (actorMode === 'blog' || actorMode === 'actor_blog')) {
  // Blog Followers command.
  registerCommand({
    name: 'activitypub/navigate-blog-followers',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Blog Followers', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'options-general.php?page=activitypub&tab=followers';
      close();
    }
  });

  // Blog Following command (only if enabled).
  if (followingEnabled) {
    registerCommand({
      name: 'activitypub/navigate-blog-following',
      label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Blog Following', 'activitypub'),
      icon: activityPubIcon,
      callback: ({
        close
      }) => {
        document.location.href = 'options-general.php?page=activitypub&tab=following';
        close();
      }
    });
  }

  // Settings command (blog-related, requires manage_options).
  registerCommand({
    name: 'activitypub/navigate-settings',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Settings', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'options-general.php?page=activitypub&tab=settings';
      close();
    }
  });

  // Blog Extra Fields commands.
  registerCommand({
    name: 'activitypub/navigate-blog-extra-fields',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: View Blog Extra Fields', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'edit.php?post_type=ap_extrafield_blog';
      close();
    }
  });
  registerCommand({
    name: 'activitypub/add-blog-extra-field',
    label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_1__.__)('ActivityPub: Add New Blog Extra Field', 'activitypub'),
    icon: activityPubIcon,
    callback: ({
      close
    }) => {
      document.location.href = 'post-new.php?post_type=ap_extrafield_blog';
      close();
    }
  });

  // Dynamic command loader: Edit existing blog extra fields.
  registerCommandLoader({
    name: 'activitypub/blog-extra-fields-search',
    hook: useBlogExtraFieldsCommandLoader
  });
}
})();

/******/ })()
;
//# sourceMappingURL=plugin.js.map