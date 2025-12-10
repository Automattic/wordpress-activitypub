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
	} else // removed by dead control flow
{}
}());


/***/ }),

/***/ "./src/follow-me/block.json":
/*!**********************************!*\
  !*** ./src/follow-me/block.json ***!
  \**********************************/
/***/ ((module) => {

"use strict";
module.exports = /*#__PURE__*/JSON.parse('{"$schema":"https://schemas.wp.org/trunk/block.json","name":"activitypub/follow-me","apiVersion":3,"version":"2.2.0","title":"Follow me on the Fediverse","category":"widgets","description":"Display your Fediverse profile so that visitors can follow you.","textdomain":"activitypub","icon":"groups","example":{"attributes":{"className":"is-style-default"}},"supports":{"html":false,"interactivity":true,"color":{"gradients":true,"link":true,"__experimentalDefaultControls":{"background":true,"text":true,"link":true}},"__experimentalBorder":{"radius":true,"width":true,"color":true,"style":true},"shadow":true,"typography":{"fontSize":true,"__experimentalDefaultControls":{"fontSize":true}},"innerBlocks":{"allowedBlocks":["core/button"]}},"styles":[{"name":"default","label":"Default","isDefault":true},{"name":"button-only","label":"Button"},{"name":"profile","label":"Profile"}],"attributes":{"selectedUser":{"type":"string","default":"blog"}},"usesContext":["postType","postId"],"editorScript":"file:./index.js","viewScriptModule":"file:./view.js","viewScript":"wp-api-fetch","style":"file:./style-index.css","render":"file:./render.php"}');

/***/ }),

/***/ "./src/follow-me/deprecation.js":
/*!**************************************!*\
  !*** ./src/follow-me/deprecation.js ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! classnames */ "./node_modules/classnames/index.js");
/* harmony import */ var classnames__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(classnames__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__);





/**
 * The block supports for the Follow Me block in version 1.
 *
 * @type {{html: boolean, color: {gradients: boolean, link: boolean, __experimentalDefaultControls: {background: boolean, text: boolean, link: boolean}}, __experimentalBorder: {radius: boolean, width: boolean, color: boolean, style: boolean}, typography: {fontSize: boolean, __experimentalDefaultControls: {fontSize: boolean}}}}
 */

const v1BlockSupports = {
  html: false,
  color: {
    gradients: true,
    link: true,
    __experimentalDefaultControls: {
      background: true,
      text: true,
      link: true
    }
  },
  __experimentalBorder: {
    radius: true,
    width: true,
    color: true,
    style: true
  },
  typography: {
    fontSize: true,
    __experimentalDefaultControls: {
      fontSize: true
    }
  }
};
const v2BlockSupports = v1BlockSupports;

/**
 * Migrates the buttonOnly attribute to a block style for the Follow Me block.
 *
 * @param {Object} attributes The block attributes.
 * @return {Object} The migrated block attributes.
 */
function migrateButtonOnly({
  buttonOnly = false,
  className = '',
  ...newAttributes
}) {
  newAttributes.className = classnames__WEBPACK_IMPORTED_MODULE_0___default()(className, buttonOnly ? 'is-style-button-only' : 'is-style-default');
  return newAttributes;
}

/**
 * Deprecation for the Follow Me block to use a core button block instead of the custom button.
 * This handles the migration of the buttonText and buttonSize attributes to the innerBlock.
 */
const v1 = {
  attributes: {
    buttonOnly: {
      type: 'boolean',
      default: false
    },
    buttonText: {
      type: 'string',
      default: 'Follow'
    },
    selectedUser: {
      type: 'string',
      default: 'blog'
    }
  },
  supports: v1BlockSupports,
  /**
   * Checks if the block is eligible for migration.
   *
   * @param {Object} attributes The block attributes.
   *
   * @return {boolean} Whether the block is eligible for migration.
   */
  isEligible({
    buttonText,
    buttonOnly
  }) {
    // Run migration if buttonText or buttonOnly is set.
    return !!buttonText || !!buttonOnly;
  },
  /**
   * Migrates the Follow Me block to use a core button block instead of the custom button.
   *
   * @param {Object} attributes The block attributes.
   *
   * @return {[Object, Array]} An array with the new block attributes and inner blocks.
   */
  migrate({
    buttonText,
    ...newAttributes
  }) {
    const buttonBlock = (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__.createBlock)('core/button', {
      text: buttonText
    });
    return [migrateButtonOnly(newAttributes), [buttonBlock]];
  }
};

/**
 * Deprecation for the Follow Me block.
 * Handles the transition from using the buttonOnly attribute to using block styles.
 */
const v2 = {
  attributes: {
    selectedUser: {
      type: 'string',
      default: 'blog'
    },
    buttonOnly: {
      type: 'boolean',
      default: false
    }
  },
  supports: v2BlockSupports,
  /**
   * Checks if the block is eligible for migration.
   *
   * @param {Object} attributes The block attributes.
   *
   * @return {boolean} Whether the block is eligible for migration.
   */
  isEligible({
    buttonOnly
  }) {
    return !!buttonOnly;
  },
  /**
   * Migrates the Follow Me block to use a block style instead of the buttonOnly attribute.
   *
   * @param {Object} attributes The block attributes.
   *
   * @return {[Object, Array]} An array with the new block attributes and inner blocks.
   */
  migrate: migrateButtonOnly,
  /**
   * Save function for the Follow Me block.
   *
   * @return {JSX.Element} React element to save.
   */
  save() {
    const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps.save();
    const innerBlocksProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useInnerBlocksProps.save(blockProps);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      ...innerBlocksProps
    });
  }
};

/**
 * Deprecation for the Follow Me block.
 * Handles the case where the button HTML is stripped due to unfiltered_html capability restrictions.
 */
const v3 = {
  attributes: {
    selectedUser: {
      type: 'string',
      default: 'blog'
    }
  },
  supports: v2BlockSupports,
  /**
   * Checks if the block is eligible for migration.
   *
   * @param {Object} attributes The block attributes.
   * @param {array} innerBlocks The inner blocks.
   *
   * @return {boolean} Whether the block is eligible for migration.
   */
  isEligible(attributes, innerBlocks) {
    return innerBlocks.length === 1 && 'button' === innerBlocks[0].attributes.tagName;
  },
  /**
   * Migrates the Follow Me block to fix the broken button.
   *
   * @param {Object} attributes The block attributes.
   * @param {array} innerBlocks The inner blocks.
   *
   * @return {[Object, Array]} An array with the new block attributes and inner blocks.
   */
  migrate(attributes, innerBlocks) {
    var _innerBlocks$0$origin;
    const {
      tagName,
      ...buttonAttributes
    } = innerBlocks[0].attributes;
    const text = (_innerBlocks$0$origin = innerBlocks[0].originalContent.replace(/<[^>]*>/g, '')) !== null && _innerBlocks$0$origin !== void 0 ? _innerBlocks$0$origin : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_3__.__)('Follow', 'activitypub');

    // Create a proper button block with the correct structure and the extracted text
    const buttonBlock = (0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_2__.createBlock)('core/button', {
      ...buttonAttributes,
      text
    });
    return [attributes, [buttonBlock]];
  },
  /**
   * Save function for the Follow Me block.
   *
   * @return {JSX.Element} React element to save.
   */
  save() {
    const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps.save();
    const innerBlocksProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useInnerBlocksProps.save(blockProps);
    return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_4__.jsx)("div", {
      ...innerBlocksProps
    });
  }
};
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = ([v3, v2, v1]);

/***/ }),

/***/ "./src/follow-me/edit.js":
/*!*******************************!*\
  !*** ./src/follow-me/edit.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (/* binding */ Edit)
/* harmony export */ });
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/api-fetch */ "@wordpress/api-fetch");
/* harmony import */ var _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__);
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @wordpress/i18n */ "@wordpress/i18n");
/* harmony import */ var _wordpress_i18n__WEBPACK_IMPORTED_MODULE_2___default = /*#__PURE__*/__webpack_require__.n(_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__);
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @wordpress/data */ "@wordpress/data");
/* harmony import */ var _wordpress_data__WEBPACK_IMPORTED_MODULE_3___default = /*#__PURE__*/__webpack_require__.n(_wordpress_data__WEBPACK_IMPORTED_MODULE_3__);
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @wordpress/core-data */ "@wordpress/core-data");
/* harmony import */ var _wordpress_core_data__WEBPACK_IMPORTED_MODULE_4___default = /*#__PURE__*/__webpack_require__.n(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_4__);
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @wordpress/components */ "@wordpress/components");
/* harmony import */ var _wordpress_components__WEBPACK_IMPORTED_MODULE_5___default = /*#__PURE__*/__webpack_require__.n(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__);
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @wordpress/element */ "@wordpress/element");
/* harmony import */ var _wordpress_element__WEBPACK_IMPORTED_MODULE_6___default = /*#__PURE__*/__webpack_require__.n(_wordpress_element__WEBPACK_IMPORTED_MODULE_6__);
/* harmony import */ var _shared_use_user_options__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ../shared/use-user-options */ "./src/shared/use-user-options.js");
/* harmony import */ var _shared_inherit_block_fallback__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ../shared/inherit-block-fallback */ "./src/shared/inherit-block-fallback.js");
/* harmony import */ var _shared_use_options__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ../shared/use-options */ "./src/shared/use-options.js");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__);











/**
 * Default profile data.
 *
 * @type {Object}
 */

const DEFAULT_PROFILE_DATA = {
  avatar: 'https://secure.gravatar.com/avatar/default?s=120',
  webfinger: '@well@hello.dolly',
  name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Hello Dolly Fan Account', 'activitypub'),
  url: '#',
  image: {
    url: ''
  },
  summary: ''
};

/**
 * Get normalized profile data.
 *
 * @param {Object} profile Profile data.
 * @return {Object} Normalized profile data.
 */
function getNormalizedProfile(profile) {
  if (!profile) {
    return DEFAULT_PROFILE_DATA;
  }
  const data = {
    ...DEFAULT_PROFILE_DATA,
    ...profile
  };
  data.avatar = data?.icon?.url;

  // Ensure webfinger always has the @ prefix.
  if (data.webfinger && !data.webfinger.startsWith('@')) {
    data.webfinger = '@' + data.webfinger;
  }
  return data;
}

/**
 * Fetch profile data.
 *
 * @param {number} userId User ID.
 * @return {Promise} Promise resolving with profile data.
 */
function fetchProfile(userId) {
  const {
    namespace
  } = (0,_shared_use_options__WEBPACK_IMPORTED_MODULE_9__.useOptions)();
  const fetchOptions = {
    headers: {
      Accept: 'application/activity+json'
    },
    path: `/${namespace}/actors/${userId}`
  };
  return _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()(fetchOptions);
}

/**
 * Profile component for the editor.
 *
 * @param {Object} props Component props.
 * @return {JSX.Element} Profile component.
 */
function EditorProfile({
  profile,
  className,
  innerBlocksProps
}) {
  const {
    webfinger,
    avatar,
    name,
    image,
    summary,
    followersCount,
    postsCount
  } = profile;

  // Ensure we're checking for the right className format
  const isButtonOnly = className && className.includes('is-style-button-only');

  // Stats for the editor preview - use real followers count if available
  const stats = {
    posts: postsCount || 0,
    followers: followersCount || 0
  };
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
    className: "activitypub-profile",
    children: [!isButtonOnly && image?.url && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
      className: "activitypub-profile__header",
      style: {
        backgroundImage: `url(${image.url})`
      }
    }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
      className: "activitypub-profile__body",
      children: [!isButtonOnly && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("img", {
        className: "activitypub-profile__avatar",
        src: avatar,
        alt: name
      }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
        className: "activitypub-profile__content",
        children: [!isButtonOnly && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
          className: "activitypub-profile__info",
          children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
            className: "activitypub-profile__name",
            children: name
          }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
            className: "activitypub-profile__handle",
            children: webfinger
          })]
        }), /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
          ...innerBlocksProps
        }), !isButtonOnly && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
          className: "activitypub-profile__bio",
          dangerouslySetInnerHTML: {
            __html: summary
          }
        }), !isButtonOnly && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("div", {
          className: "activitypub-profile__stats",
          children: Object.entries(stats).map(([key, count]) => /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
            children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)("strong", {
              children: count
            }), ' ', key === 'posts' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._n)('post', 'posts', count, 'activitypub') : key === 'followers' ? (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._n)('follower', 'followers', count, 'activitypub') : (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__._n)('following', 'following', count, 'activitypub')]
          }, key))
        })]
      })]
    })]
  });
}

/**
 * Edit component.
 *
 * @param {Object} props Component props.
 * @param {Object} props.attributes Block attributes.
 * @param {Function} props.setAttributes Set block attributes.
 * @param {Object} props.context Block context.
 * @param {string} props.context.postType Post type.
 * @param {number} props.context.postId Post ID.
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
  const blockProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useBlockProps)({
    className: 'activitypub-follow-me-block-wrapper'
  });
  const usersOptions = (0,_shared_use_user_options__WEBPACK_IMPORTED_MODULE_7__.useUserOptions)({
    withInherit: true
  });
  const {
    selectedUser,
    className = 'is-style-default'
  } = attributes;
  const isInheritMode = selectedUser === 'inherit';
  const [profile, setProfile] = (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_6__.useState)(getNormalizedProfile(DEFAULT_PROFILE_DATA));
  const userId = selectedUser === 'blog' ? 0 : selectedUser;
  const TEMPLATE = [['core/button', {
    text: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Follow', 'activitypub')
  }]];
  const innerBlocksProps = (0,_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.useInnerBlocksProps)({}, {
    allowedBlocks: ['core/button'],
    template: TEMPLATE,
    templateLock: false,
    renderAppender: false
  });
  const authorId = (0,_wordpress_data__WEBPACK_IMPORTED_MODULE_3__.useSelect)(select => {
    const {
      getEditedEntityRecord
    } = select(_wordpress_core_data__WEBPACK_IMPORTED_MODULE_4__.store);
    const _authorId = getEditedEntityRecord('postType', postType, postId)?.author;
    return _authorId !== null && _authorId !== void 0 ? _authorId : null;
  }, [postType, postId]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_6__.useEffect)(() => {
    // Fetch profile data when userId changes.
    if (isInheritMode && !authorId) {
      return;
    }
    const effectiveUserId = isInheritMode ? authorId : userId;
    fetchProfile(effectiveUserId).then(data => {
      setProfile(getNormalizedProfile(data));

      // Convert the full URL to a path if it's a local URL.
      if (data.followers) {
        try {
          // Extract just the path portion from the URL
          const {
            pathname: path
          } = new URL(data.followers);
          _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
            path: path.replace('wp-json/', '')
          }).then(({
            totalItems = 0
          }) => {
            setProfile(prevProfile => ({
              ...prevProfile,
              followersCount: totalItems
            }));
          }).catch(() => {});
        } catch (e) {
          // If URL parsing fails, just continue without fetching followers.
        }
      }
      if (effectiveUserId) {
        _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: `/wp/v2/users/${effectiveUserId}/?context=activitypub`
        }).then(({
          post_count
        }) => {
          setProfile(prevProfile => ({
            ...prevProfile,
            postsCount: post_count
          }));
        }).catch(() => {});
      } else {
        _wordpress_api_fetch__WEBPACK_IMPORTED_MODULE_0___default()({
          path: '/wp/v2/posts',
          method: 'HEAD',
          parse: false // Preserve headers.
        }).then(response => {
          const postsCount = response.headers.get('X-WP-Total');
          setProfile(prevProfile => ({
            ...prevProfile,
            postsCount
          }));
        }).catch(() => {});
      }
    }).catch(() => {});
  }, [userId, authorId, isInheritMode]);
  (0,_wordpress_element__WEBPACK_IMPORTED_MODULE_6__.useEffect)(() => {
    // If there are no users yet, do nothing.
    if (!usersOptions.length) {
      return;
    }
    // Ensure that the selected user is in the list of options, if not, select the first available user.
    if (!usersOptions.find(({
      value
    }) => value === selectedUser)) {
      setAttributes({
        selectedUser: usersOptions[0].value
      });
    }
  }, [selectedUser, usersOptions]);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsxs)("div", {
    ...blockProps,
    children: [/*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_1__.InspectorControls, {
      children: usersOptions.length > 1 && /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.PanelBody, {
        title: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Follow Me Options', 'activitypub'),
        children: /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_wordpress_components__WEBPACK_IMPORTED_MODULE_5__.SelectControl, {
          label: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Select User', 'activitypub'),
          value: attributes.selectedUser,
          options: usersOptions,
          onChange: value => setAttributes({
            selectedUser: value
          }),
          __next40pxDefaultSize: true,
          __nextHasNoMarginBottom: true
        })
      })
    }, "activitypub-follow-me"), isInheritMode && !authorId ? /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(_shared_inherit_block_fallback__WEBPACK_IMPORTED_MODULE_8__.InheritModeBlockFallback, {
      name: (0,_wordpress_i18n__WEBPACK_IMPORTED_MODULE_2__.__)('Follow Me', 'activitypub')
    }) : /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_10__.jsx)(EditorProfile, {
      profile: profile,
      className: className,
      innerBlocksProps: innerBlocksProps
    })]
  });
}

/***/ }),

/***/ "./src/follow-me/index.js":
/*!********************************!*\
  !*** ./src/follow-me/index.js ***!
  \********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/blocks */ "@wordpress/blocks");
/* harmony import */ var _wordpress_blocks__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @wordpress/icons */ "./node_modules/@wordpress/icons/build-module/library/people.js");
/* harmony import */ var _deprecation__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./deprecation */ "./src/follow-me/deprecation.js");
/* harmony import */ var _edit__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./edit */ "./src/follow-me/edit.js");
/* harmony import */ var _block_json__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./block.json */ "./src/follow-me/block.json");
/* harmony import */ var _save__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./save */ "./src/follow-me/save.js");
/* harmony import */ var _style_scss__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./style.scss */ "./src/follow-me/style.scss");








// Register the block.
(0,_wordpress_blocks__WEBPACK_IMPORTED_MODULE_0__.registerBlockType)(_block_json__WEBPACK_IMPORTED_MODULE_4__, {
  deprecated: _deprecation__WEBPACK_IMPORTED_MODULE_2__["default"],
  edit: _edit__WEBPACK_IMPORTED_MODULE_3__["default"],
  icon: _wordpress_icons__WEBPACK_IMPORTED_MODULE_1__["default"],
  save: _save__WEBPACK_IMPORTED_MODULE_5__["default"]
});

/***/ }),

/***/ "./src/follow-me/save.js":
/*!*******************************!*\
  !*** ./src/follow-me/save.js ***!
  \*******************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   "default": () => (__WEBPACK_DEFAULT_EXPORT__)
/* harmony export */ });
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @wordpress/block-editor */ "@wordpress/block-editor");
/* harmony import */ var _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0___default = /*#__PURE__*/__webpack_require__.n(_wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__);
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1___default = /*#__PURE__*/__webpack_require__.n(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__);


/**
 * Save component for the Follow Me block.
 *
 * This component ensures that inner blocks (the button) are properly saved.
 *
 * @return {JSX.Element|null} Save component.
 */

function save() {
  const blockProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useBlockProps.save();
  const innerBlocksProps = _wordpress_block_editor__WEBPACK_IMPORTED_MODULE_0__.useInnerBlocksProps.save(blockProps);
  return /*#__PURE__*/(0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_1__.jsx)("div", {
    ...innerBlocksProps
  });
}
/* harmony default export */ const __WEBPACK_DEFAULT_EXPORT__ = (save);

/***/ }),

/***/ "./src/follow-me/style.scss":
/*!**********************************!*\
  !*** ./src/follow-me/style.scss ***!
  \**********************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ }),

/***/ "./src/shared/inherit-block-fallback.js":
/*!**********************************************!*\
  !*** ./src/shared/inherit-block-fallback.js ***!
  \**********************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

"use strict";
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

"use strict";
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

/***/ "@wordpress/core-data":
/*!**********************************!*\
  !*** external ["wp","coreData"] ***!
  \**********************************/
/***/ ((module) => {

"use strict";
module.exports = window["wp"]["coreData"];

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

/***/ "react/jsx-runtime":
/*!**********************************!*\
  !*** external "ReactJSXRuntime" ***!
  \**********************************/
/***/ ((module) => {

"use strict";
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
/******/ 			"follow-me/index": 0,
/******/ 			"follow-me/style-index": 0
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
/******/ 	var __webpack_exports__ = __webpack_require__.O(undefined, ["follow-me/style-index"], () => (__webpack_require__("./src/follow-me/index.js")))
/******/ 	__webpack_exports__ = __webpack_require__.O(__webpack_exports__);
/******/ 	
/******/ })()
;
//# sourceMappingURL=index.js.map