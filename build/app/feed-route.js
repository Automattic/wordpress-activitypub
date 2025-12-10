"use strict";
(globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || []).push([["app/feed-route"],{

/***/ "./src/app/routes/feed/route.ts":
/*!**************************************!*\
  !*** ./src/app/routes/feed/route.ts ***!
  \**************************************/
/***/ ((__unused_webpack_module, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   route: () => (/* binding */ route)
/* harmony export */ });
/**
 * Feed Route Module
 *
 * Route lifecycle configuration for the feed route.
 * Controls when the inspector panel should be shown.
 */

const route = {
  /**
   * Show inspector only when a post is selected (postId in search params)
   * @param root0
   * @param root0.search
   */
  inspector: ({
    search
  }) => !!search.postId
};

/***/ })

}]);
//# sourceMappingURL=feed-route.js.map