/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./src/extra-fields/block.json":
/*!*************************************!*\
  !*** ./src/extra-fields/block.json ***!
  \*************************************/
/***/ ((module) => {

module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","name":"activitypub/extra-fields","apiVersion":3,"version":"1.0.0","title":"Fediverse Extra Fields","category":"widgets","description":"Display extra fields from ActivityPub user profiles","textdomain":"activitypub","icon":"list-view","supports":{"html":false,"align":["wide","full"],"color":{"gradients":true,"link":true,"__experimentalDefaultControls":{"background":true,"text":true,"link":true}},"__experimentalBorder":{"radius":true,"width":true,"color":true,"style":true},"shadow":true,"typography":{"fontSize":true,"__experimentalDefaultControls":{"fontSize":true}}},"styles":[{"name":"compact","label":"Compact","isDefault":true},{"name":"stacked","label":"Stacked"},{"name":"cards","label":"Cards"}],"attributes":{"selectedUser":{"type":"string","default":"blog"},"maxFields":{"type":"number","default":0}},"usesContext":["postType","postId"],"editorScript":"file:./index.js","style":"file:./style-index.css","render":"file:./render.php"}');

/***/ }),

/***/ "./src/extra-fields/edit.js":
/*!**********************************!*\
  !*** ./src/extra-fields/edit.js ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _shared_use_options__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ../shared/use-options */ "./src/shared/use-options.js");
/* harmony import */ var _shared_use_user_options__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../shared/use-user-options */ "./src/shared/use-user-options.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__);
/**
 * WordPress dependencies
 */







/**
 * Internal dependencies
 */



/**
 * Editor component for Extra Fields block.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to set attributes.
 * @param {Object}   props.context       Block context.
 * @return {Element} Component element.
 */

function Edit({
  attributes,
  setAttributes,
  context
}) {
  const {
    selectedUser,
    maxFields
  } = attributes;
  const {
    postId: contextPostId,
    postType: contextPostType
  } = context !== null && context !== void 0 ? context : {};
  const [fields, setFields] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)([]);
  const [isLoading, setIsLoading] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)(false);
  const [error, setError] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useState)(null);

  // Get author ID from context or current post depending on editor.
  const authorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const editorStore = select('core/editor');
    const coreStore = select('core');
    if (contextPostId && contextPostType && coreStore) {
      var _coreStore$getEditedE, _coreStore$getEntityR;
      const editedRecord = (_coreStore$getEditedE = coreStore.getEditedEntityRecord?.('postType', contextPostType, contextPostId)) !== null && _coreStore$getEditedE !== void 0 ? _coreStore$getEditedE : null;
      if (editedRecord?.author) {
        return editedRecord.author;
      }
      const record = (_coreStore$getEntityR = coreStore.getEntityRecord?.('postType', contextPostType, contextPostId)) !== null && _coreStore$getEntityR !== void 0 ? _coreStore$getEntityR : null;
      if (record?.author) {
        return record.author;
      }
    }
    if (editorStore && editorStore.getCurrentPostAttribute) {
      return editorStore.getCurrentPostAttribute('author');
    }
    return null;
  }, [contextPostId, contextPostType]);

  // Determine which user ID to fetch
  const getUserId = () => {
    if (selectedUser === 'blog') {
      return 0;
    }
    if (selectedUser === 'inherit') {
      if (authorId) {
        return authorId;
      }
      return null;
    }
    return selectedUser;
  };
  const userId = getUserId();

  // Get ActivityPub options
  const {
    namespace = 'activitypub/1.0',
    profileUrls = {}
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_6__.useOptions)();

  // Select profile settings URL based on user type
  const profileUrl = selectedUser === 'blog' ? profileUrls.blog : profileUrls.user;
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'activitypub-extra-fields-block-wrapper'
  });

  // Get user options for dropdown
  const userOptions = (0,_shared_use_user_options__WEBPACK_IMPORTED_MODULE_7__.useUserOptions)({
    withInherit: true
  });

  // Fetch extra fields
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_4__.useEffect)(() => {
    if (userId === null) {
      setFields([]);
      return;
    }
    setIsLoading(true);
    setError(null);
    _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_5___default()({
      path: `/${namespace}/actors/${userId}`,
      headers: {
        Accept: 'application/activity+json'
      }
    }).then(actor => {
      // Extract fields from attachment array
      const attachments = actor.attachment || [];
      // Filter to only PropertyValue types (the main format)
      const propertyValues = attachments.filter(item => item.type === 'PropertyValue');
      setFields(propertyValues);
      setIsLoading(false);
    }).catch(err => {
      setError(err.message);
      setIsLoading(false);
    });
  }, [userId, namespace]);

  // Apply max fields limit for preview
  const displayFields = maxFields > 0 ? fields.slice(0, maxFields) : fields;

  // Extract background color for cards style
  const getCardStyle = () => {
    const isCardsStyle = attributes.className?.includes('is-style-cards');
    if (!isCardsStyle) {
      return {};
    }

    // Get background color from block attributes
    const style = attributes.style || {};
    const backgroundColor = attributes.backgroundColor;
    const customColor = style.color?.background;
    if (backgroundColor) {
      return {
        backgroundColor: `var(--wp--preset--color--${backgroundColor})`
      };
    } else if (customColor) {
      return {
        backgroundColor: customColor
      };
    }
    return {};
  };
  const cardStyle = getCardStyle();
  const settingsPanel = /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
    children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.PanelBody, {
      title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Settings', 'activitypub'),
      initialOpen: true,
      children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.SelectControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('User', 'activitypub'),
        value: selectedUser,
        options: userOptions,
        onChange: value => setAttributes({
          selectedUser: value
        })
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.RangeControl, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Maximum Fields', 'activitypub'),
        value: maxFields,
        onChange: value => setAttributes({
          maxFields: value
        }),
        min: 0,
        max: 20,
        help: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Limit the number of fields displayed. 0 = show all.', 'activitypub')
      })]
    })
  });

  // Render placeholder if inherit mode but no author. Keep controls mounted for recovery.
  if (selectedUser === 'inherit' && !authorId) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
      children: [settingsPanel, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
        ...blockProps,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Placeholder, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fediverse Extra Fields', 'activitypub'),
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
            children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('This block will display extra fields based on the post author when published.', 'activitypub')
          })
        })
      })]
    });
  }

  // Render loading state
  if (isLoading) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Placeholder, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fediverse Extra Fields', 'activitypub'),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Spinner, {})
      })
    });
  }

  // Render error state
  if (error) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Placeholder, {
        label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fediverse Extra Fields', 'activitypub'),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("p", {
          children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.sprintf)(/* translators: %s: Error message */
          (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Error loading extra fields: %s', 'activitypub'), error)
        })
      })
    });
  }

  // Render empty state
  if (displayFields.length === 0) {
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
      children: [settingsPanel, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
        ...blockProps,
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Placeholder, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Fediverse Extra Fields', 'activitypub'),
          children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("p", {
            children: [(0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('No extra fields found.', 'activitypub'), ' ', profileUrl && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_2__.Button, {
              variant: "link",
              onClick: () => {
                window.location.href = profileUrl;
              },
              children: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_0__.__)('Add fields in your profile settings', 'activitypub')
            })]
          })
        })
      })]
    });
  }
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.Fragment, {
    children: [settingsPanel, /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("div", {
      ...blockProps,
      children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("dl", {
        className: "activitypub-extra-fields",
        children: displayFields.map(field => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsxs)("div", {
          className: "activitypub-extra-field",
          style: cardStyle,
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("dt", {
            children: field.name
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_8__.jsx)("dd", {
            dangerouslySetInnerHTML: {
              __html: field.value
            }
          })]
        }, `${field.name}-${field.value}`))
      })
    })]
  });
}

/***/ }),

/***/ "./src/extra-fields/index.js":
/*!***********************************!*\
  !*** ./src/extra-fields/index.js ***!
  \***********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./edit */ "./src/extra-fields/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./block.json */ "./src/extra-fields/block.json");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./style.scss */ "./src/extra-fields/style.scss");
/**
 * WordPress dependencies
 */


/**
 * Internal dependencies
 */




/**
 * Register the Extra Fields block.
 *
 * This block uses server-side rendering, so the save function returns null.
 */
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_2__.name, {
  edit: _edit__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: () => null
});

/***/ }),

/***/ "./src/extra-fields/style.scss":
/*!*************************************!*\
  !*** ./src/extra-fields/style.scss ***!
  \*************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


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
/******/ 			"extra-fields/index": 0,
/******/ 			"extra-fields/style-index": 0
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
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["extra-fields/style-index"], () => (__webpack_require__("./src/extra-fields/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map