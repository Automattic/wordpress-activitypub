"use strict";
(globalThis["webpackChunkwordpress_activitypub"] = globalThis["webpackChunkwordpress_activitypub"] || []).push([["app/tanstack-router"],{

/***/ "./node_modules/@tanstack/history/dist/esm/index.js":
/*!**********************************************************!*\
  !*** ./node_modules/@tanstack/history/dist/esm/index.js ***!
  \**********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createBrowserHistory: () => (/* binding */ createBrowserHistory),
/* harmony export */   createHashHistory: () => (/* binding */ createHashHistory),
/* harmony export */   createHistory: () => (/* binding */ createHistory),
/* harmony export */   createMemoryHistory: () => (/* binding */ createMemoryHistory),
/* harmony export */   parseHref: () => (/* binding */ parseHref)
/* harmony export */ });
const stateIndexKey = "__TSR_index";
const popStateEvent = "popstate";
const beforeUnloadEvent = "beforeunload";
function createHistory(opts) {
  let location = opts.getLocation();
  const subscribers = /* @__PURE__ */ new Set();
  const notify = (action) => {
    location = opts.getLocation();
    subscribers.forEach((subscriber) => subscriber({ location, action }));
  };
  const handleIndexChange = (action) => {
    if (opts.notifyOnIndexChange ?? true) notify(action);
    else location = opts.getLocation();
  };
  const tryNavigation = async ({
    task,
    navigateOpts,
    ...actionInfo
  }) => {
    const ignoreBlocker = navigateOpts?.ignoreBlocker ?? false;
    if (ignoreBlocker) {
      task();
      return;
    }
    const blockers = opts.getBlockers?.() ?? [];
    const isPushOrReplace = actionInfo.type === "PUSH" || actionInfo.type === "REPLACE";
    if (typeof document !== "undefined" && blockers.length && isPushOrReplace) {
      for (const blocker of blockers) {
        const nextLocation = parseHref(actionInfo.path, actionInfo.state);
        const isBlocked = await blocker.blockerFn({
          currentLocation: location,
          nextLocation,
          action: actionInfo.type
        });
        if (isBlocked) {
          opts.onBlocked?.();
          return;
        }
      }
    }
    task();
  };
  return {
    get location() {
      return location;
    },
    get length() {
      return opts.getLength();
    },
    subscribers,
    subscribe: (cb) => {
      subscribers.add(cb);
      return () => {
        subscribers.delete(cb);
      };
    },
    push: (path, state, navigateOpts) => {
      const currentIndex = location.state[stateIndexKey];
      state = assignKeyAndIndex(currentIndex + 1, state);
      tryNavigation({
        task: () => {
          opts.pushState(path, state);
          notify({ type: "PUSH" });
        },
        navigateOpts,
        type: "PUSH",
        path,
        state
      });
    },
    replace: (path, state, navigateOpts) => {
      const currentIndex = location.state[stateIndexKey];
      state = assignKeyAndIndex(currentIndex, state);
      tryNavigation({
        task: () => {
          opts.replaceState(path, state);
          notify({ type: "REPLACE" });
        },
        navigateOpts,
        type: "REPLACE",
        path,
        state
      });
    },
    go: (index, navigateOpts) => {
      tryNavigation({
        task: () => {
          opts.go(index);
          handleIndexChange({ type: "GO", index });
        },
        navigateOpts,
        type: "GO"
      });
    },
    back: (navigateOpts) => {
      tryNavigation({
        task: () => {
          opts.back(navigateOpts?.ignoreBlocker ?? false);
          handleIndexChange({ type: "BACK" });
        },
        navigateOpts,
        type: "BACK"
      });
    },
    forward: (navigateOpts) => {
      tryNavigation({
        task: () => {
          opts.forward(navigateOpts?.ignoreBlocker ?? false);
          handleIndexChange({ type: "FORWARD" });
        },
        navigateOpts,
        type: "FORWARD"
      });
    },
    canGoBack: () => location.state[stateIndexKey] !== 0,
    createHref: (str) => opts.createHref(str),
    block: (blocker) => {
      if (!opts.setBlockers) return () => {
      };
      const blockers = opts.getBlockers?.() ?? [];
      opts.setBlockers([...blockers, blocker]);
      return () => {
        const blockers2 = opts.getBlockers?.() ?? [];
        opts.setBlockers?.(blockers2.filter((b) => b !== blocker));
      };
    },
    flush: () => opts.flush?.(),
    destroy: () => opts.destroy?.(),
    notify
  };
}
function assignKeyAndIndex(index, state) {
  if (!state) {
    state = {};
  }
  const key = createRandomKey();
  return {
    ...state,
    key,
    // TODO: Remove in v2 - use __TSR_key instead
    __TSR_key: key,
    [stateIndexKey]: index
  };
}
function createBrowserHistory(opts) {
  const win = opts?.window ?? (typeof document !== "undefined" ? window : void 0);
  const originalPushState = win.history.pushState;
  const originalReplaceState = win.history.replaceState;
  let blockers = [];
  const _getBlockers = () => blockers;
  const _setBlockers = (newBlockers) => blockers = newBlockers;
  const createHref = opts?.createHref ?? ((path) => path);
  const parseLocation = opts?.parseLocation ?? (() => parseHref(
    `${win.location.pathname}${win.location.search}${win.location.hash}`,
    win.history.state
  ));
  if (!win.history.state?.__TSR_key && !win.history.state?.key) {
    const addedKey = createRandomKey();
    win.history.replaceState(
      {
        [stateIndexKey]: 0,
        key: addedKey,
        // TODO: Remove in v2 - use __TSR_key instead
        __TSR_key: addedKey
      },
      ""
    );
  }
  let currentLocation = parseLocation();
  let rollbackLocation;
  let nextPopIsGo = false;
  let ignoreNextPop = false;
  let skipBlockerNextPop = false;
  let ignoreNextBeforeUnload = false;
  const getLocation = () => currentLocation;
  let next;
  let scheduled;
  const flush = () => {
    if (!next) {
      return;
    }
    history._ignoreSubscribers = true;
    (next.isPush ? win.history.pushState : win.history.replaceState)(
      next.state,
      "",
      next.href
    );
    history._ignoreSubscribers = false;
    next = void 0;
    scheduled = void 0;
    rollbackLocation = void 0;
  };
  const queueHistoryAction = (type, destHref, state) => {
    const href = createHref(destHref);
    if (!scheduled) {
      rollbackLocation = currentLocation;
    }
    currentLocation = parseHref(destHref, state);
    next = {
      href,
      state,
      isPush: next?.isPush || type === "push"
    };
    if (!scheduled) {
      scheduled = Promise.resolve().then(() => flush());
    }
  };
  const onPushPop = (type) => {
    currentLocation = parseLocation();
    history.notify({ type });
  };
  const onPushPopEvent = async () => {
    if (ignoreNextPop) {
      ignoreNextPop = false;
      return;
    }
    const nextLocation = parseLocation();
    const delta = nextLocation.state[stateIndexKey] - currentLocation.state[stateIndexKey];
    const isForward = delta === 1;
    const isBack = delta === -1;
    const isGo = !isForward && !isBack || nextPopIsGo;
    nextPopIsGo = false;
    const action = isGo ? "GO" : isBack ? "BACK" : "FORWARD";
    const notify = isGo ? {
      type: "GO",
      index: delta
    } : {
      type: isBack ? "BACK" : "FORWARD"
    };
    if (skipBlockerNextPop) {
      skipBlockerNextPop = false;
    } else {
      const blockers2 = _getBlockers();
      if (typeof document !== "undefined" && blockers2.length) {
        for (const blocker of blockers2) {
          const isBlocked = await blocker.blockerFn({
            currentLocation,
            nextLocation,
            action
          });
          if (isBlocked) {
            ignoreNextPop = true;
            win.history.go(1);
            history.notify(notify);
            return;
          }
        }
      }
    }
    currentLocation = parseLocation();
    history.notify(notify);
  };
  const onBeforeUnload = (e) => {
    if (ignoreNextBeforeUnload) {
      ignoreNextBeforeUnload = false;
      return;
    }
    let shouldBlock = false;
    const blockers2 = _getBlockers();
    if (typeof document !== "undefined" && blockers2.length) {
      for (const blocker of blockers2) {
        const shouldHaveBeforeUnload = blocker.enableBeforeUnload ?? true;
        if (shouldHaveBeforeUnload === true) {
          shouldBlock = true;
          break;
        }
        if (typeof shouldHaveBeforeUnload === "function" && shouldHaveBeforeUnload() === true) {
          shouldBlock = true;
          break;
        }
      }
    }
    if (shouldBlock) {
      e.preventDefault();
      return e.returnValue = "";
    }
    return;
  };
  const history = createHistory({
    getLocation,
    getLength: () => win.history.length,
    pushState: (href, state) => queueHistoryAction("push", href, state),
    replaceState: (href, state) => queueHistoryAction("replace", href, state),
    back: (ignoreBlocker) => {
      if (ignoreBlocker) skipBlockerNextPop = true;
      ignoreNextBeforeUnload = true;
      return win.history.back();
    },
    forward: (ignoreBlocker) => {
      if (ignoreBlocker) skipBlockerNextPop = true;
      ignoreNextBeforeUnload = true;
      win.history.forward();
    },
    go: (n) => {
      nextPopIsGo = true;
      win.history.go(n);
    },
    createHref: (href) => createHref(href),
    flush,
    destroy: () => {
      win.history.pushState = originalPushState;
      win.history.replaceState = originalReplaceState;
      win.removeEventListener(beforeUnloadEvent, onBeforeUnload, {
        capture: true
      });
      win.removeEventListener(popStateEvent, onPushPopEvent);
    },
    onBlocked: () => {
      if (rollbackLocation && currentLocation !== rollbackLocation) {
        currentLocation = rollbackLocation;
      }
    },
    getBlockers: _getBlockers,
    setBlockers: _setBlockers,
    notifyOnIndexChange: false
  });
  win.addEventListener(beforeUnloadEvent, onBeforeUnload, { capture: true });
  win.addEventListener(popStateEvent, onPushPopEvent);
  win.history.pushState = function(...args) {
    const res = originalPushState.apply(win.history, args);
    if (!history._ignoreSubscribers) onPushPop("PUSH");
    return res;
  };
  win.history.replaceState = function(...args) {
    const res = originalReplaceState.apply(win.history, args);
    if (!history._ignoreSubscribers) onPushPop("REPLACE");
    return res;
  };
  return history;
}
function createHashHistory(opts) {
  const win = opts?.window ?? (typeof document !== "undefined" ? window : void 0);
  return createBrowserHistory({
    window: win,
    parseLocation: () => {
      const hashSplit = win.location.hash.split("#").slice(1);
      const pathPart = hashSplit[0] ?? "/";
      const searchPart = win.location.search;
      const hashEntries = hashSplit.slice(1);
      const hashPart = hashEntries.length === 0 ? "" : `#${hashEntries.join("#")}`;
      const hashHref = `${pathPart}${searchPart}${hashPart}`;
      return parseHref(hashHref, win.history.state);
    },
    createHref: (href) => `${win.location.pathname}${win.location.search}#${href}`
  });
}
function createMemoryHistory(opts = {
  initialEntries: ["/"]
}) {
  const entries = opts.initialEntries;
  let index = opts.initialIndex ? Math.min(Math.max(opts.initialIndex, 0), entries.length - 1) : entries.length - 1;
  const states = entries.map(
    (_entry, index2) => assignKeyAndIndex(index2, void 0)
  );
  const getLocation = () => parseHref(entries[index], states[index]);
  return createHistory({
    getLocation,
    getLength: () => entries.length,
    pushState: (path, state) => {
      if (index < entries.length - 1) {
        entries.splice(index + 1);
        states.splice(index + 1);
      }
      states.push(state);
      entries.push(path);
      index = Math.max(entries.length - 1, 0);
    },
    replaceState: (path, state) => {
      states[index] = state;
      entries[index] = path;
    },
    back: () => {
      index = Math.max(index - 1, 0);
    },
    forward: () => {
      index = Math.min(index + 1, entries.length - 1);
    },
    go: (n) => {
      index = Math.min(Math.max(index + n, 0), entries.length - 1);
    },
    createHref: (path) => path
  });
}
function parseHref(href, state) {
  const hashIndex = href.indexOf("#");
  const searchIndex = href.indexOf("?");
  const addedKey = createRandomKey();
  return {
    href,
    pathname: href.substring(
      0,
      hashIndex > 0 ? searchIndex > 0 ? Math.min(hashIndex, searchIndex) : hashIndex : searchIndex > 0 ? searchIndex : href.length
    ),
    hash: hashIndex > -1 ? href.substring(hashIndex) : "",
    search: searchIndex > -1 ? href.slice(searchIndex, hashIndex === -1 ? void 0 : hashIndex) : "",
    state: state || { [stateIndexKey]: 0, key: addedKey, __TSR_key: addedKey }
  };
}
function createRandomKey() {
  return (Math.random() + 1).toString(36).substring(7);
}

//# sourceMappingURL=index.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/CatchBoundary.js":
/*!***********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/CatchBoundary.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CatchBoundary: () => (/* binding */ CatchBoundary),
/* harmony export */   ErrorComponent: () => (/* binding */ ErrorComponent)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");


function CatchBoundary(props) {
  const errorComponent = props.errorComponent ?? ErrorComponent;
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
    CatchBoundaryImpl,
    {
      getResetKey: props.getResetKey,
      onCatch: props.onCatch,
      children: ({ error, reset }) => {
        if (error) {
          return react__WEBPACK_IMPORTED_MODULE_1__.createElement(errorComponent, {
            error,
            reset
          });
        }
        return props.children;
      }
    }
  );
}
class CatchBoundaryImpl extends react__WEBPACK_IMPORTED_MODULE_1__.Component {
  constructor() {
    super(...arguments);
    this.state = { error: null };
  }
  static getDerivedStateFromProps(props) {
    return { resetKey: props.getResetKey() };
  }
  static getDerivedStateFromError(error) {
    return { error };
  }
  reset() {
    this.setState({ error: null });
  }
  componentDidUpdate(prevProps, prevState) {
    if (prevState.error && prevState.resetKey !== this.state.resetKey) {
      this.reset();
    }
  }
  componentDidCatch(error, errorInfo) {
    if (this.props.onCatch) {
      this.props.onCatch(error, errorInfo);
    }
  }
  render() {
    return this.props.children({
      error: this.state.resetKey !== this.props.getResetKey() ? null : this.state.error,
      reset: () => {
        this.reset();
      }
    });
  }
}
function ErrorComponent({ error }) {
  const [show, setShow] = react__WEBPACK_IMPORTED_MODULE_1__.useState("development" !== "production");
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("div", { style: { padding: ".5rem", maxWidth: "100%" }, children: [
    /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)("div", { style: { display: "flex", alignItems: "center", gap: ".5rem" }, children: [
      /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("strong", { style: { fontSize: "1rem" }, children: "Something went wrong!" }),
      /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
        "button",
        {
          style: {
            appearance: "none",
            fontSize: ".6em",
            border: "1px solid currentColor",
            padding: ".1rem .2rem",
            fontWeight: "bold",
            borderRadius: ".25rem"
          },
          onClick: () => setShow((d) => !d),
          children: show ? "Hide Error" : "Show Error"
        }
      )
    ] }),
    /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("div", { style: { height: ".25rem" } }),
    show ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("div", { children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
      "pre",
      {
        style: {
          fontSize: ".7em",
          border: "1px solid red",
          borderRadius: ".25rem",
          padding: ".3rem",
          color: "red",
          overflow: "auto"
        },
        children: error.message ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("code", { children: error.message }) : null
      }
    ) }) : null
  ] });
}

//# sourceMappingURL=CatchBoundary.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/ClientOnly.js":
/*!********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/ClientOnly.js ***!
  \********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ClientOnly: () => (/* binding */ ClientOnly),
/* harmony export */   useHydrated: () => (/* binding */ useHydrated)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");


function ClientOnly({ children, fallback = null }) {
  return useHydrated() ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(react__WEBPACK_IMPORTED_MODULE_1__.Fragment, { children }) : /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(react__WEBPACK_IMPORTED_MODULE_1__.Fragment, { children: fallback });
}
function useHydrated() {
  return react__WEBPACK_IMPORTED_MODULE_1__.useSyncExternalStore(
    subscribe,
    () => true,
    () => false
  );
}
function subscribe() {
  return () => {
  };
}

//# sourceMappingURL=ClientOnly.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/Match.js":
/*!***************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/Match.js ***!
  \***************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Match: () => (/* binding */ Match),
/* harmony export */   MatchInner: () => (/* binding */ MatchInner),
/* harmony export */   Outlet: () => (/* binding */ Outlet)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var tiny_warning__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! tiny-warning */ "./node_modules/tiny-warning/dist/tiny-warning.esm.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/root.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/router.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/redirect.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/not-found.js");
/* harmony import */ var _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./CatchBoundary.js */ "./node_modules/@tanstack/react-router/dist/esm/CatchBoundary.js");
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");
/* harmony import */ var _not_found_js__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./not-found.js */ "./node_modules/@tanstack/react-router/dist/esm/not-found.js");
/* harmony import */ var _matchContext_js__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./matchContext.js */ "./node_modules/@tanstack/react-router/dist/esm/matchContext.js");
/* harmony import */ var _SafeFragment_js__WEBPACK_IMPORTED_MODULE_14__ = __webpack_require__(/*! ./SafeFragment.js */ "./node_modules/@tanstack/react-router/dist/esm/SafeFragment.js");
/* harmony import */ var _renderRouteNotFound_js__WEBPACK_IMPORTED_MODULE_15__ = __webpack_require__(/*! ./renderRouteNotFound.js */ "./node_modules/@tanstack/react-router/dist/esm/renderRouteNotFound.js");
/* harmony import */ var _scroll_restoration_js__WEBPACK_IMPORTED_MODULE_16__ = __webpack_require__(/*! ./scroll-restoration.js */ "./node_modules/@tanstack/react-router/dist/esm/scroll-restoration.js");
/* harmony import */ var _ClientOnly_js__WEBPACK_IMPORTED_MODULE_17__ = __webpack_require__(/*! ./ClientOnly.js */ "./node_modules/@tanstack/react-router/dist/esm/ClientOnly.js");














const Match = react__WEBPACK_IMPORTED_MODULE_1__.memo(function MatchImpl({
  matchId
}) {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_11__.useRouter)();
  const matchState = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => {
      const match = s.matches.find((d) => d.id === matchId);
      (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_2__["default"])(
        match,
        `Could not find match for matchId "${matchId}". Please file an issue!`
      );
      return {
        routeId: match.routeId,
        ssr: match.ssr,
        _displayPending: match._displayPending
      };
    },
    structuralSharing: true
  });
  const route = router.routesById[matchState.routeId];
  const PendingComponent = route.options.pendingComponent ?? router.options.defaultPendingComponent;
  const pendingElement = PendingComponent ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(PendingComponent, {}) : null;
  const routeErrorComponent = route.options.errorComponent ?? router.options.defaultErrorComponent;
  const routeOnCatch = route.options.onCatch ?? router.options.defaultOnCatch;
  const routeNotFoundComponent = route.isRoot ? (
    // If it's the root route, use the globalNotFound option, with fallback to the notFoundRoute's component
    route.options.notFoundComponent ?? router.options.notFoundRoute?.options.component
  ) : route.options.notFoundComponent;
  const resolvedNoSsr = matchState.ssr === false || matchState.ssr === "data-only";
  const ResolvedSuspenseBoundary = (
    // If we're on the root route, allow forcefully wrapping in suspense
    (!route.isRoot || route.options.wrapInSuspense || resolvedNoSsr) && (route.options.wrapInSuspense ?? PendingComponent ?? (route.options.errorComponent?.preload || resolvedNoSsr)) ? react__WEBPACK_IMPORTED_MODULE_1__.Suspense : _SafeFragment_js__WEBPACK_IMPORTED_MODULE_14__.SafeFragment
  );
  const ResolvedCatchBoundary = routeErrorComponent ? _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_9__.CatchBoundary : _SafeFragment_js__WEBPACK_IMPORTED_MODULE_14__.SafeFragment;
  const ResolvedNotFoundBoundary = routeNotFoundComponent ? _not_found_js__WEBPACK_IMPORTED_MODULE_12__.CatchNotFound : _SafeFragment_js__WEBPACK_IMPORTED_MODULE_14__.SafeFragment;
  const resetKey = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => s.loadedAt
  });
  const parentRouteId = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => {
      const index = s.matches.findIndex((d) => d.id === matchId);
      return s.matches[index - 1]?.routeId;
    }
  });
  const ShellComponent = route.isRoot ? route.options.shellComponent ?? _SafeFragment_js__WEBPACK_IMPORTED_MODULE_14__.SafeFragment : _SafeFragment_js__WEBPACK_IMPORTED_MODULE_14__.SafeFragment;
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)(ShellComponent, { children: [
    /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_matchContext_js__WEBPACK_IMPORTED_MODULE_13__.matchContext.Provider, { value: matchId, children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(ResolvedSuspenseBoundary, { fallback: pendingElement, children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
      ResolvedCatchBoundary,
      {
        getResetKey: () => resetKey,
        errorComponent: routeErrorComponent || _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_9__.ErrorComponent,
        onCatch: (error, errorInfo) => {
          if ((0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_8__.isNotFound)(error)) throw error;
          (0,tiny_warning__WEBPACK_IMPORTED_MODULE_3__["default"])(false, `Error in route match: ${matchId}`);
          routeOnCatch?.(error, errorInfo);
        },
        children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
          ResolvedNotFoundBoundary,
          {
            fallback: (error) => {
              if (!routeNotFoundComponent || error.routeId && error.routeId !== matchState.routeId || !error.routeId && !route.isRoot)
                throw error;
              return react__WEBPACK_IMPORTED_MODULE_1__.createElement(routeNotFoundComponent, error);
            },
            children: resolvedNoSsr || matchState._displayPending ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_ClientOnly_js__WEBPACK_IMPORTED_MODULE_17__.ClientOnly, { fallback: pendingElement, children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(MatchInner, { matchId }) }) : /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(MatchInner, { matchId })
          }
        )
      }
    ) }) }),
    parentRouteId === _tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__.rootRouteId && router.options.scrollRestoration ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.Fragment, { children: [
      /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(OnRendered, {}),
      /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_scroll_restoration_js__WEBPACK_IMPORTED_MODULE_16__.ScrollRestoration, {})
    ] }) : null
  ] });
});
function OnRendered() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_11__.useRouter)();
  const prevLocationRef = react__WEBPACK_IMPORTED_MODULE_1__.useRef(
    void 0
  );
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
    "script",
    {
      suppressHydrationWarning: true,
      ref: (el) => {
        if (el && (prevLocationRef.current === void 0 || prevLocationRef.current.href !== router.latestLocation.href)) {
          router.emit({
            type: "onRendered",
            ...(0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_5__.getLocationChangeInfo)(router.state)
          });
          prevLocationRef.current = router.latestLocation;
        }
      }
    },
    router.latestLocation.state.__TSR_key
  );
}
const MatchInner = react__WEBPACK_IMPORTED_MODULE_1__.memo(function MatchInnerImpl({
  matchId
}) {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_11__.useRouter)();
  const { match, key, routeId } = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => {
      const match2 = s.matches.find((d) => d.id === matchId);
      const routeId2 = match2.routeId;
      const remountFn = router.routesById[routeId2].options.remountDeps ?? router.options.defaultRemountDeps;
      const remountDeps = remountFn?.({
        routeId: routeId2,
        loaderDeps: match2.loaderDeps,
        params: match2._strictParams,
        search: match2._strictSearch
      });
      const key2 = remountDeps ? JSON.stringify(remountDeps) : void 0;
      return {
        key: key2,
        routeId: routeId2,
        match: {
          id: match2.id,
          status: match2.status,
          error: match2.error,
          _forcePending: match2._forcePending,
          _displayPending: match2._displayPending
        }
      };
    },
    structuralSharing: true
  });
  const route = router.routesById[routeId];
  const out = react__WEBPACK_IMPORTED_MODULE_1__.useMemo(() => {
    const Comp = route.options.component ?? router.options.defaultComponent;
    if (Comp) {
      return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(Comp, {}, key);
    }
    return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(Outlet, {});
  }, [key, route.options.component, router.options.defaultComponent]);
  if (match._displayPending) {
    throw router.getMatch(match.id)?._nonReactive.displayPendingPromise;
  }
  if (match._forcePending) {
    throw router.getMatch(match.id)?._nonReactive.minPendingPromise;
  }
  if (match.status === "pending") {
    const pendingMinMs = route.options.pendingMinMs ?? router.options.defaultPendingMinMs;
    if (pendingMinMs) {
      const routerMatch = router.getMatch(match.id);
      if (routerMatch && !routerMatch._nonReactive.minPendingPromise) {
        if (!router.isServer) {
          const minPendingPromise = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_6__.createControlledPromise)();
          routerMatch._nonReactive.minPendingPromise = minPendingPromise;
          setTimeout(() => {
            minPendingPromise.resolve();
            routerMatch._nonReactive.minPendingPromise = void 0;
          }, pendingMinMs);
        }
      }
    }
    throw router.getMatch(match.id)?._nonReactive.loadPromise;
  }
  if (match.status === "notFound") {
    (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_2__["default"])((0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_8__.isNotFound)(match.error), "Expected a notFound error");
    return (0,_renderRouteNotFound_js__WEBPACK_IMPORTED_MODULE_15__.renderRouteNotFound)(router, route, match.error);
  }
  if (match.status === "redirected") {
    (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_2__["default"])((0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_7__.isRedirect)(match.error), "Expected a redirect error");
    throw router.getMatch(match.id)?._nonReactive.loadPromise;
  }
  if (match.status === "error") {
    if (router.isServer) {
      const RouteErrorComponent = (route.options.errorComponent ?? router.options.defaultErrorComponent) || _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_9__.ErrorComponent;
      return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
        RouteErrorComponent,
        {
          error: match.error,
          reset: void 0,
          info: {
            componentStack: ""
          }
        }
      );
    }
    throw match.error;
  }
  return out;
});
const Outlet = react__WEBPACK_IMPORTED_MODULE_1__.memo(function OutletImpl() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_11__.useRouter)();
  const matchId = react__WEBPACK_IMPORTED_MODULE_1__.useContext(_matchContext_js__WEBPACK_IMPORTED_MODULE_13__.matchContext);
  const routeId = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => s.matches.find((d) => d.id === matchId)?.routeId
  });
  const route = router.routesById[routeId];
  const parentGlobalNotFound = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => {
      const matches = s.matches;
      const parentMatch = matches.find((d) => d.id === matchId);
      (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_2__["default"])(
        parentMatch,
        `Could not find parent match for matchId "${matchId}"`
      );
      return parentMatch.globalNotFound;
    }
  });
  const childMatchId = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_10__.useRouterState)({
    select: (s) => {
      const matches = s.matches;
      const index = matches.findIndex((d) => d.id === matchId);
      return matches[index + 1]?.id;
    }
  });
  const pendingElement = router.options.defaultPendingComponent ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(router.options.defaultPendingComponent, {}) : null;
  if (parentGlobalNotFound) {
    return (0,_renderRouteNotFound_js__WEBPACK_IMPORTED_MODULE_15__.renderRouteNotFound)(router, route, void 0);
  }
  if (!childMatchId) {
    return null;
  }
  const nextMatch = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(Match, { matchId: childMatchId });
  if (routeId === _tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__.rootRouteId) {
    return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(react__WEBPACK_IMPORTED_MODULE_1__.Suspense, { fallback: pendingElement, children: nextMatch });
  }
  return nextMatch;
});

//# sourceMappingURL=Match.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/Matches.js":
/*!*****************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/Matches.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   MatchRoute: () => (/* binding */ MatchRoute),
/* harmony export */   Matches: () => (/* binding */ Matches),
/* harmony export */   useChildMatches: () => (/* binding */ useChildMatches),
/* harmony export */   useMatchRoute: () => (/* binding */ useMatchRoute),
/* harmony export */   useMatches: () => (/* binding */ useMatches),
/* harmony export */   useParentMatches: () => (/* binding */ useParentMatches)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var tiny_warning__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! tiny-warning */ "./node_modules/tiny-warning/dist/tiny-warning.esm.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/root.js");
/* harmony import */ var _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./CatchBoundary.js */ "./node_modules/@tanstack/react-router/dist/esm/CatchBoundary.js");
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");
/* harmony import */ var _Transitioner_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./Transitioner.js */ "./node_modules/@tanstack/react-router/dist/esm/Transitioner.js");
/* harmony import */ var _matchContext_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./matchContext.js */ "./node_modules/@tanstack/react-router/dist/esm/matchContext.js");
/* harmony import */ var _Match_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./Match.js */ "./node_modules/@tanstack/react-router/dist/esm/Match.js");
/* harmony import */ var _SafeFragment_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./SafeFragment.js */ "./node_modules/@tanstack/react-router/dist/esm/SafeFragment.js");











function Matches() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_6__.useRouter)();
  const rootRoute = router.routesById[_tanstack_router_core__WEBPACK_IMPORTED_MODULE_3__.rootRouteId];
  const PendingComponent = rootRoute.options.pendingComponent ?? router.options.defaultPendingComponent;
  const pendingElement = PendingComponent ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(PendingComponent, {}) : null;
  const ResolvedSuspense = router.isServer || typeof document !== "undefined" && router.ssr ? _SafeFragment_js__WEBPACK_IMPORTED_MODULE_10__.SafeFragment : react__WEBPACK_IMPORTED_MODULE_1__.Suspense;
  const inner = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsxs)(ResolvedSuspense, { fallback: pendingElement, children: [
    !router.isServer && /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_Transitioner_js__WEBPACK_IMPORTED_MODULE_7__.Transitioner, {}),
    /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(MatchesInner, {})
  ] });
  return router.options.InnerWrap ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(router.options.InnerWrap, { children: inner }) : inner;
}
function MatchesInner() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_6__.useRouter)();
  const matchId = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_5__.useRouterState)({
    select: (s) => {
      return s.matches[0]?.id;
    }
  });
  const resetKey = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_5__.useRouterState)({
    select: (s) => s.loadedAt
  });
  const matchComponent = matchId ? /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_Match_js__WEBPACK_IMPORTED_MODULE_9__.Match, { matchId }) : null;
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_matchContext_js__WEBPACK_IMPORTED_MODULE_8__.matchContext.Provider, { value: matchId, children: router.options.disableGlobalCatchBoundary ? matchComponent : /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
    _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_4__.CatchBoundary,
    {
      getResetKey: () => resetKey,
      errorComponent: _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_4__.ErrorComponent,
      onCatch: (error) => {
        (0,tiny_warning__WEBPACK_IMPORTED_MODULE_2__["default"])(
          false,
          `The following error wasn't caught by any route! At the very least, consider setting an 'errorComponent' in your RootRoute!`
        );
        (0,tiny_warning__WEBPACK_IMPORTED_MODULE_2__["default"])(false, error.message || error.toString());
      },
      children: matchComponent
    }
  ) });
}
function useMatchRoute() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_6__.useRouter)();
  (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_5__.useRouterState)({
    select: (s) => [s.location.href, s.resolvedLocation?.href, s.status],
    structuralSharing: true
  });
  return react__WEBPACK_IMPORTED_MODULE_1__.useCallback(
    (opts) => {
      const { pending, caseSensitive, fuzzy, includeSearch, ...rest } = opts;
      return router.matchRoute(rest, {
        pending,
        caseSensitive,
        fuzzy,
        includeSearch
      });
    },
    [router]
  );
}
function MatchRoute(props) {
  const matchRoute = useMatchRoute();
  const params = matchRoute(props);
  if (typeof props.children === "function") {
    return props.children(params);
  }
  return params ? props.children : null;
}
function useMatches(opts) {
  return (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_5__.useRouterState)({
    select: (state) => {
      const matches = state.matches;
      return opts?.select ? opts.select(matches) : matches;
    },
    structuralSharing: opts?.structuralSharing
  });
}
function useParentMatches(opts) {
  const contextMatchId = react__WEBPACK_IMPORTED_MODULE_1__.useContext(_matchContext_js__WEBPACK_IMPORTED_MODULE_8__.matchContext);
  return useMatches({
    select: (matches) => {
      matches = matches.slice(
        0,
        matches.findIndex((d) => d.id === contextMatchId)
      );
      return opts?.select ? opts.select(matches) : matches;
    },
    structuralSharing: opts?.structuralSharing
  });
}
function useChildMatches(opts) {
  const contextMatchId = react__WEBPACK_IMPORTED_MODULE_1__.useContext(_matchContext_js__WEBPACK_IMPORTED_MODULE_8__.matchContext);
  return useMatches({
    select: (matches) => {
      matches = matches.slice(
        matches.findIndex((d) => d.id === contextMatchId) + 1
      );
      return opts?.select ? opts.select(matches) : matches;
    },
    structuralSharing: opts?.structuralSharing
  });
}

//# sourceMappingURL=Matches.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/RouterProvider.js":
/*!************************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/RouterProvider.js ***!
  \************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   RouterContextProvider: () => (/* binding */ RouterContextProvider),
/* harmony export */   RouterProvider: () => (/* binding */ RouterProvider)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var _Matches_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./Matches.js */ "./node_modules/@tanstack/react-router/dist/esm/Matches.js");
/* harmony import */ var _routerContext_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./routerContext.js */ "./node_modules/@tanstack/react-router/dist/esm/routerContext.js");



function RouterContextProvider({
  router,
  children,
  ...rest
}) {
  if (Object.keys(rest).length > 0) {
    router.update({
      ...router.options,
      ...rest,
      context: {
        ...router.options.context,
        ...rest.context
      }
    });
  }
  const routerContext = (0,_routerContext_js__WEBPACK_IMPORTED_MODULE_2__.getRouterContext)();
  const provider = /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(routerContext.Provider, { value: router, children });
  if (router.options.Wrap) {
    return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(router.options.Wrap, { children: provider });
  }
  return provider;
}
function RouterProvider({ router, ...rest }) {
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(RouterContextProvider, { router, ...rest, children: /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_Matches_js__WEBPACK_IMPORTED_MODULE_1__.Matches, {}) });
}

//# sourceMappingURL=RouterProvider.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/SafeFragment.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/SafeFragment.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SafeFragment: () => (/* binding */ SafeFragment)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");

function SafeFragment(props) {
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.Fragment, { children: props.children });
}

//# sourceMappingURL=SafeFragment.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/ScriptOnce.js":
/*!********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/ScriptOnce.js ***!
  \********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ScriptOnce: () => (/* binding */ ScriptOnce)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");


function ScriptOnce({ children }) {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_1__.useRouter)();
  if (!router.isServer) {
    return null;
  }
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
    "script",
    {
      nonce: router.options.ssr?.nonce,
      className: "$tsr",
      dangerouslySetInnerHTML: {
        __html: children + ';typeof $_TSR !== "undefined" && $_TSR.c()'
      }
    }
  );
}

//# sourceMappingURL=ScriptOnce.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/Transitioner.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/Transitioner.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Transitioner: () => (/* binding */ Transitioner)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/path.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/router.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/scroll-restoration.js");
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/react-router/dist/esm/utils.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");





function Transitioner() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_5__.useRouter)();
  const mountLoadForRouter = react__WEBPACK_IMPORTED_MODULE_0__.useRef({ router, mounted: false });
  const [isTransitioning, setIsTransitioning] = react__WEBPACK_IMPORTED_MODULE_0__.useState(false);
  const { hasPendingMatches, isLoading } = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_6__.useRouterState)({
    select: (s) => ({
      isLoading: s.isLoading,
      hasPendingMatches: s.matches.some((d) => d.status === "pending")
    }),
    structuralSharing: true
  });
  const previousIsLoading = (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.usePrevious)(isLoading);
  const isAnyPending = isLoading || isTransitioning || hasPendingMatches;
  const previousIsAnyPending = (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.usePrevious)(isAnyPending);
  const isPagePending = isLoading || hasPendingMatches;
  const previousIsPagePending = (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.usePrevious)(isPagePending);
  router.startTransition = (fn) => {
    setIsTransitioning(true);
    react__WEBPACK_IMPORTED_MODULE_0__.startTransition(() => {
      fn();
      setIsTransitioning(false);
    });
  };
  react__WEBPACK_IMPORTED_MODULE_0__.useEffect(() => {
    const unsub = router.history.subscribe(router.load);
    const nextLocation = router.buildLocation({
      to: router.latestLocation.pathname,
      search: true,
      params: true,
      hash: true,
      state: true,
      _includeValidateSearch: true
    });
    if ((0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.trimPathRight)(router.latestLocation.href) !== (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.trimPathRight)(nextLocation.href)) {
      router.commitLocation({ ...nextLocation, replace: true });
    }
    return () => {
      unsub();
    };
  }, [router, router.history]);
  (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.useLayoutEffect)(() => {
    if (
      // if we are hydrating from SSR, loading is triggered in ssr-client
      typeof window !== "undefined" && router.ssr || mountLoadForRouter.current.router === router && mountLoadForRouter.current.mounted
    ) {
      return;
    }
    mountLoadForRouter.current = { router, mounted: true };
    const tryLoad = async () => {
      try {
        await router.load();
      } catch (err) {
        console.error(err);
      }
    };
    tryLoad();
  }, [router]);
  (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.useLayoutEffect)(() => {
    if (previousIsLoading && !isLoading) {
      router.emit({
        type: "onLoad",
        // When the new URL has committed, when the new matches have been loaded into state.matches
        ...(0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__.getLocationChangeInfo)(router.state)
      });
    }
  }, [previousIsLoading, router, isLoading]);
  (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.useLayoutEffect)(() => {
    if (previousIsPagePending && !isPagePending) {
      router.emit({
        type: "onBeforeRouteMount",
        ...(0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__.getLocationChangeInfo)(router.state)
      });
    }
  }, [isPagePending, previousIsPagePending, router]);
  (0,_utils_js__WEBPACK_IMPORTED_MODULE_4__.useLayoutEffect)(() => {
    if (previousIsAnyPending && !isAnyPending) {
      const changeInfo = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__.getLocationChangeInfo)(router.state);
      router.emit({
        type: "onResolved",
        ...changeInfo
      });
      router.__store.setState((s) => ({
        ...s,
        status: "idle",
        resolvedLocation: s.location
      }));
      if (changeInfo.hrefChanged) {
        (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_3__.handleHashScroll)(router);
      }
    }
  }, [isAnyPending, previousIsAnyPending, router]);
  return null;
}

//# sourceMappingURL=Transitioner.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/fileRoute.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/fileRoute.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   FileRoute: () => (/* binding */ FileRoute),
/* harmony export */   FileRouteLoader: () => (/* binding */ FileRouteLoader),
/* harmony export */   LazyRoute: () => (/* binding */ LazyRoute),
/* harmony export */   createFileRoute: () => (/* binding */ createFileRoute),
/* harmony export */   createLazyFileRoute: () => (/* binding */ createLazyFileRoute),
/* harmony export */   createLazyRoute: () => (/* binding */ createLazyRoute)
/* harmony export */ });
/* harmony import */ var tiny_warning__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! tiny-warning */ "./node_modules/tiny-warning/dist/tiny-warning.esm.js");
/* harmony import */ var _route_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./route.js */ "./node_modules/@tanstack/react-router/dist/esm/route.js");
/* harmony import */ var _useMatch_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./useMatch.js */ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js");
/* harmony import */ var _useLoaderDeps_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./useLoaderDeps.js */ "./node_modules/@tanstack/react-router/dist/esm/useLoaderDeps.js");
/* harmony import */ var _useLoaderData_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./useLoaderData.js */ "./node_modules/@tanstack/react-router/dist/esm/useLoaderData.js");
/* harmony import */ var _useSearch_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./useSearch.js */ "./node_modules/@tanstack/react-router/dist/esm/useSearch.js");
/* harmony import */ var _useParams_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./useParams.js */ "./node_modules/@tanstack/react-router/dist/esm/useParams.js");
/* harmony import */ var _useNavigate_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./useNavigate.js */ "./node_modules/@tanstack/react-router/dist/esm/useNavigate.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");









function createFileRoute(path) {
  if (typeof path === "object") {
    return new FileRoute(path, {
      silent: true
    }).createRoute(path);
  }
  return new FileRoute(path, {
    silent: true
  }).createRoute;
}
class FileRoute {
  constructor(path, _opts) {
    this.path = path;
    this.createRoute = (options) => {
      (0,tiny_warning__WEBPACK_IMPORTED_MODULE_0__["default"])(
        this.silent,
        "FileRoute is deprecated and will be removed in the next major version. Use the createFileRoute(path)(options) function instead."
      );
      const route = (0,_route_js__WEBPACK_IMPORTED_MODULE_1__.createRoute)(options);
      route.isRoot = false;
      return route;
    };
    this.silent = _opts?.silent;
  }
}
function FileRouteLoader(_path) {
  (0,tiny_warning__WEBPACK_IMPORTED_MODULE_0__["default"])(
    false,
    `FileRouteLoader is deprecated and will be removed in the next major version. Please place the loader function in the the main route file, inside the \`createFileRoute('/path/to/file')(options)\` options`
  );
  return (loaderFn) => loaderFn;
}
class LazyRoute {
  constructor(opts) {
    this.useMatch = (opts2) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_2__.useMatch)({
        select: opts2?.select,
        from: this.options.id,
        structuralSharing: opts2?.structuralSharing
      });
    };
    this.useRouteContext = (opts2) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_2__.useMatch)({
        from: this.options.id,
        select: (d) => opts2?.select ? opts2.select(d.context) : d.context
      });
    };
    this.useSearch = (opts2) => {
      return (0,_useSearch_js__WEBPACK_IMPORTED_MODULE_5__.useSearch)({
        select: opts2?.select,
        structuralSharing: opts2?.structuralSharing,
        from: this.options.id
      });
    };
    this.useParams = (opts2) => {
      return (0,_useParams_js__WEBPACK_IMPORTED_MODULE_6__.useParams)({
        select: opts2?.select,
        structuralSharing: opts2?.structuralSharing,
        from: this.options.id
      });
    };
    this.useLoaderDeps = (opts2) => {
      return (0,_useLoaderDeps_js__WEBPACK_IMPORTED_MODULE_3__.useLoaderDeps)({ ...opts2, from: this.options.id });
    };
    this.useLoaderData = (opts2) => {
      return (0,_useLoaderData_js__WEBPACK_IMPORTED_MODULE_4__.useLoaderData)({ ...opts2, from: this.options.id });
    };
    this.useNavigate = () => {
      const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_8__.useRouter)();
      return (0,_useNavigate_js__WEBPACK_IMPORTED_MODULE_7__.useNavigate)({ from: router.routesById[this.options.id].fullPath });
    };
    this.options = opts;
    this.$$typeof = Symbol.for("react.memo");
  }
}
function createLazyRoute(id) {
  return (opts) => {
    return new LazyRoute({
      id,
      ...opts
    });
  };
}
function createLazyFileRoute(id) {
  if (typeof id === "object") {
    return new LazyRoute(id);
  }
  return (opts) => new LazyRoute({ id, ...opts });
}

//# sourceMappingURL=fileRoute.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/link.js":
/*!**************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/link.js ***!
  \**************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Link: () => (/* binding */ Link),
/* harmony export */   createLink: () => (/* binding */ createLink),
/* harmony export */   linkOptions: () => (/* binding */ linkOptions),
/* harmony export */   useLinkProps: () => (/* binding */ useLinkProps)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var react_dom__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! react-dom */ "react-dom");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/link.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/path.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/react-router/dist/esm/utils.js");







function useLinkProps(options, forwardedRef) {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_7__.useRouter)();
  const [isTransitioning, setIsTransitioning] = react__WEBPACK_IMPORTED_MODULE_1__.useState(false);
  const hasRenderFetched = react__WEBPACK_IMPORTED_MODULE_1__.useRef(false);
  const innerRef = (0,_utils_js__WEBPACK_IMPORTED_MODULE_8__.useForwardedRef)(forwardedRef);
  const {
    // custom props
    activeProps,
    inactiveProps,
    activeOptions,
    to,
    preload: userPreload,
    preloadDelay: userPreloadDelay,
    hashScrollIntoView,
    replace,
    startTransition,
    resetScroll,
    viewTransition,
    // element props
    children,
    target,
    disabled,
    style,
    className,
    onClick,
    onFocus,
    onMouseEnter,
    onMouseLeave,
    onTouchStart,
    ignoreBlocker,
    // prevent these from being returned
    params: _params,
    search: _search,
    hash: _hash,
    state: _state,
    mask: _mask,
    reloadDocument: _reloadDocument,
    unsafeRelative: _unsafeRelative,
    from: _from,
    _fromLocation,
    ...propsSafeToSpread
  } = options;
  const currentSearch = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_6__.useRouterState)({
    select: (s) => s.location.search,
    structuralSharing: true
  });
  const from = options.from;
  const _options = react__WEBPACK_IMPORTED_MODULE_1__.useMemo(
    () => {
      return { ...options, from };
    },
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [
      router,
      currentSearch,
      from,
      options._fromLocation,
      options.hash,
      options.to,
      options.search,
      options.params,
      options.state,
      options.mask,
      options.unsafeRelative
    ]
  );
  const next = react__WEBPACK_IMPORTED_MODULE_1__.useMemo(
    () => router.buildLocation({ ..._options }),
    [router, _options]
  );
  const hrefOption = react__WEBPACK_IMPORTED_MODULE_1__.useMemo(() => {
    if (disabled) {
      return void 0;
    }
    let href = next.maskedLocation ? next.maskedLocation.url.href : next.url.href;
    let external = false;
    if (router.origin) {
      if (href.startsWith(router.origin)) {
        href = router.history.createHref(href.replace(router.origin, "")) || "/";
      } else {
        external = true;
      }
    }
    return { href, external };
  }, [disabled, next.maskedLocation, next.url, router.origin, router.history]);
  const externalLink = react__WEBPACK_IMPORTED_MODULE_1__.useMemo(() => {
    if (hrefOption?.external) {
      return hrefOption.href;
    }
    try {
      new URL(to);
      return to;
    } catch {
    }
    return void 0;
  }, [to, hrefOption]);
  const preload = options.reloadDocument || externalLink ? false : userPreload ?? router.options.defaultPreload;
  const preloadDelay = userPreloadDelay ?? router.options.defaultPreloadDelay ?? 0;
  const isActive = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_6__.useRouterState)({
    select: (s) => {
      if (externalLink) return false;
      if (activeOptions?.exact) {
        const testExact = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__.exactPathTest)(
          s.location.pathname,
          next.pathname,
          router.basepath
        );
        if (!testExact) {
          return false;
        }
      } else {
        const currentPathSplit = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__.removeTrailingSlash)(
          s.location.pathname,
          router.basepath
        );
        const nextPathSplit = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_4__.removeTrailingSlash)(
          next.pathname,
          router.basepath
        );
        const pathIsFuzzyEqual = currentPathSplit.startsWith(nextPathSplit) && (currentPathSplit.length === nextPathSplit.length || currentPathSplit[nextPathSplit.length] === "/");
        if (!pathIsFuzzyEqual) {
          return false;
        }
      }
      if (activeOptions?.includeSearch ?? true) {
        const searchTest = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_5__.deepEqual)(s.location.search, next.search, {
          partial: !activeOptions?.exact,
          ignoreUndefined: !activeOptions?.explicitUndefined
        });
        if (!searchTest) {
          return false;
        }
      }
      if (activeOptions?.includeHash) {
        return s.location.hash === next.hash;
      }
      return true;
    }
  });
  const doPreload = react__WEBPACK_IMPORTED_MODULE_1__.useCallback(() => {
    router.preloadRoute({ ..._options }).catch((err) => {
      console.warn(err);
      console.warn(_tanstack_router_core__WEBPACK_IMPORTED_MODULE_3__.preloadWarning);
    });
  }, [router, _options]);
  const preloadViewportIoCallback = react__WEBPACK_IMPORTED_MODULE_1__.useCallback(
    (entry) => {
      if (entry?.isIntersecting) {
        doPreload();
      }
    },
    [doPreload]
  );
  (0,_utils_js__WEBPACK_IMPORTED_MODULE_8__.useIntersectionObserver)(
    innerRef,
    preloadViewportIoCallback,
    intersectionObserverOptions,
    { disabled: !!disabled || !(preload === "viewport") }
  );
  react__WEBPACK_IMPORTED_MODULE_1__.useEffect(() => {
    if (hasRenderFetched.current) {
      return;
    }
    if (!disabled && preload === "render") {
      doPreload();
      hasRenderFetched.current = true;
    }
  }, [disabled, doPreload, preload]);
  const handleClick = (e) => {
    const elementTarget = e.currentTarget.getAttribute("target");
    const effectiveTarget = target !== void 0 ? target : elementTarget;
    if (!disabled && !isCtrlEvent(e) && !e.defaultPrevented && (!effectiveTarget || effectiveTarget === "_self") && e.button === 0) {
      e.preventDefault();
      (0,react_dom__WEBPACK_IMPORTED_MODULE_2__.flushSync)(() => {
        setIsTransitioning(true);
      });
      const unsub = router.subscribe("onResolved", () => {
        unsub();
        setIsTransitioning(false);
      });
      router.navigate({
        ..._options,
        replace,
        resetScroll,
        hashScrollIntoView,
        startTransition,
        viewTransition,
        ignoreBlocker
      });
    }
  };
  if (externalLink) {
    return {
      ...propsSafeToSpread,
      ref: innerRef,
      href: externalLink,
      ...children && { children },
      ...target && { target },
      ...disabled && { disabled },
      ...style && { style },
      ...className && { className },
      ...onClick && { onClick },
      ...onFocus && { onFocus },
      ...onMouseEnter && { onMouseEnter },
      ...onMouseLeave && { onMouseLeave },
      ...onTouchStart && { onTouchStart }
    };
  }
  const handleFocus = (_) => {
    if (disabled) return;
    if (preload) {
      doPreload();
    }
  };
  const handleTouchStart = handleFocus;
  const handleEnter = (e) => {
    if (disabled || !preload) return;
    if (!preloadDelay) {
      doPreload();
    } else {
      const eventTarget = e.target;
      if (timeoutMap.has(eventTarget)) {
        return;
      }
      const id = setTimeout(() => {
        timeoutMap.delete(eventTarget);
        doPreload();
      }, preloadDelay);
      timeoutMap.set(eventTarget, id);
    }
  };
  const handleLeave = (e) => {
    if (disabled || !preload || !preloadDelay) return;
    const eventTarget = e.target;
    const id = timeoutMap.get(eventTarget);
    if (id) {
      clearTimeout(id);
      timeoutMap.delete(eventTarget);
    }
  };
  const resolvedActiveProps = isActive ? (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_5__.functionalUpdate)(activeProps, {}) ?? STATIC_ACTIVE_OBJECT : STATIC_EMPTY_OBJECT;
  const resolvedInactiveProps = isActive ? STATIC_EMPTY_OBJECT : (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_5__.functionalUpdate)(inactiveProps, {}) ?? STATIC_EMPTY_OBJECT;
  const resolvedClassName = [
    className,
    resolvedActiveProps.className,
    resolvedInactiveProps.className
  ].filter(Boolean).join(" ");
  const resolvedStyle = (style || resolvedActiveProps.style || resolvedInactiveProps.style) && {
    ...style,
    ...resolvedActiveProps.style,
    ...resolvedInactiveProps.style
  };
  return {
    ...propsSafeToSpread,
    ...resolvedActiveProps,
    ...resolvedInactiveProps,
    href: hrefOption?.href,
    ref: innerRef,
    onClick: composeHandlers([onClick, handleClick]),
    onFocus: composeHandlers([onFocus, handleFocus]),
    onMouseEnter: composeHandlers([onMouseEnter, handleEnter]),
    onMouseLeave: composeHandlers([onMouseLeave, handleLeave]),
    onTouchStart: composeHandlers([onTouchStart, handleTouchStart]),
    disabled: !!disabled,
    target,
    ...resolvedStyle && { style: resolvedStyle },
    ...resolvedClassName && { className: resolvedClassName },
    ...disabled && STATIC_DISABLED_PROPS,
    ...isActive && STATIC_ACTIVE_PROPS,
    ...isTransitioning && STATIC_TRANSITIONING_PROPS
  };
}
const STATIC_EMPTY_OBJECT = {};
const STATIC_ACTIVE_OBJECT = { className: "active" };
const STATIC_DISABLED_PROPS = { role: "link", "aria-disabled": true };
const STATIC_ACTIVE_PROPS = { "data-status": "active", "aria-current": "page" };
const STATIC_TRANSITIONING_PROPS = { "data-transitioning": "transitioning" };
const timeoutMap = /* @__PURE__ */ new WeakMap();
const intersectionObserverOptions = {
  rootMargin: "100px"
};
const composeHandlers = (handlers) => (e) => {
  for (const handler of handlers) {
    if (!handler) continue;
    if (e.defaultPrevented) return;
    handler(e);
  }
};
function createLink(Comp) {
  return react__WEBPACK_IMPORTED_MODULE_1__.forwardRef(function CreatedLink(props, ref) {
    return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(Link, { ...props, _asChild: Comp, ref });
  });
}
const Link = react__WEBPACK_IMPORTED_MODULE_1__.forwardRef(
  (props, ref) => {
    const { _asChild, ...rest } = props;
    const {
      type: _type,
      ref: innerRef,
      ...linkProps
    } = useLinkProps(rest, ref);
    const children = typeof rest.children === "function" ? rest.children({
      isActive: linkProps["data-status"] === "active"
    }) : rest.children;
    if (_asChild === void 0) {
      delete linkProps.disabled;
    }
    return react__WEBPACK_IMPORTED_MODULE_1__.createElement(
      _asChild ? _asChild : "a",
      {
        ...linkProps,
        ref: innerRef
      },
      children
    );
  }
);
function isCtrlEvent(e) {
  return !!(e.metaKey || e.altKey || e.ctrlKey || e.shiftKey);
}
const linkOptions = (options) => {
  return options;
};

//# sourceMappingURL=link.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/matchContext.js":
/*!**********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/matchContext.js ***!
  \**********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   dummyMatchContext: () => (/* binding */ dummyMatchContext),
/* harmony export */   matchContext: () => (/* binding */ matchContext)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");

const matchContext = react__WEBPACK_IMPORTED_MODULE_0__.createContext(void 0);
const dummyMatchContext = react__WEBPACK_IMPORTED_MODULE_0__.createContext(
  void 0
);

//# sourceMappingURL=matchContext.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/not-found.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/not-found.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   CatchNotFound: () => (/* binding */ CatchNotFound),
/* harmony export */   DefaultGlobalNotFound: () => (/* binding */ DefaultGlobalNotFound)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/not-found.js");
/* harmony import */ var _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./CatchBoundary.js */ "./node_modules/@tanstack/react-router/dist/esm/CatchBoundary.js");
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");




function CatchNotFound(props) {
  const resetKey = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_3__.useRouterState)({
    select: (s) => `not-found-${s.location.pathname}-${s.status}`
  });
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
    _CatchBoundary_js__WEBPACK_IMPORTED_MODULE_2__.CatchBoundary,
    {
      getResetKey: () => resetKey,
      onCatch: (error, errorInfo) => {
        if ((0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.isNotFound)(error)) {
          props.onCatch?.(error, errorInfo);
        } else {
          throw error;
        }
      },
      errorComponent: ({ error }) => {
        if ((0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.isNotFound)(error)) {
          return props.fallback?.(error);
        } else {
          throw error;
        }
      },
      children: props.children
    }
  );
}
function DefaultGlobalNotFound() {
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)("p", { children: "Not Found" });
}

//# sourceMappingURL=not-found.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/renderRouteNotFound.js":
/*!*****************************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/renderRouteNotFound.js ***!
  \*****************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   renderRouteNotFound: () => (/* binding */ renderRouteNotFound)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var tiny_warning__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! tiny-warning */ "./node_modules/tiny-warning/dist/tiny-warning.esm.js");
/* harmony import */ var _not_found_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./not-found.js */ "./node_modules/@tanstack/react-router/dist/esm/not-found.js");



function renderRouteNotFound(router, route, data) {
  if (!route.options.notFoundComponent) {
    if (router.options.defaultNotFoundComponent) {
      return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(router.options.defaultNotFoundComponent, { ...data });
    }
    if (true) {
      (0,tiny_warning__WEBPACK_IMPORTED_MODULE_1__["default"])(
        route.options.notFoundComponent,
        `A notFoundError was encountered on the route with ID "${route.id}", but a notFoundComponent option was not configured, nor was a router level defaultNotFoundComponent configured. Consider configuring at least one of these to avoid TanStack Router's overly generic defaultNotFoundComponent (<p>Not Found</p>)`
      );
    }
    return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_not_found_js__WEBPACK_IMPORTED_MODULE_2__.DefaultGlobalNotFound, {});
  }
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(route.options.notFoundComponent, { ...data });
}

//# sourceMappingURL=renderRouteNotFound.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/route.js":
/*!***************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/route.js ***!
  \***************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   NotFoundRoute: () => (/* binding */ NotFoundRoute),
/* harmony export */   RootRoute: () => (/* binding */ RootRoute),
/* harmony export */   Route: () => (/* binding */ Route),
/* harmony export */   RouteApi: () => (/* binding */ RouteApi),
/* harmony export */   createRootRoute: () => (/* binding */ createRootRoute),
/* harmony export */   createRootRouteWithContext: () => (/* binding */ createRootRouteWithContext),
/* harmony export */   createRoute: () => (/* binding */ createRoute),
/* harmony export */   createRouteMask: () => (/* binding */ createRouteMask),
/* harmony export */   getRouteApi: () => (/* binding */ getRouteApi),
/* harmony export */   rootRouteWithContext: () => (/* binding */ rootRouteWithContext)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/route.js");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/not-found.js");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var _useLoaderData_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./useLoaderData.js */ "./node_modules/@tanstack/react-router/dist/esm/useLoaderData.js");
/* harmony import */ var _useLoaderDeps_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./useLoaderDeps.js */ "./node_modules/@tanstack/react-router/dist/esm/useLoaderDeps.js");
/* harmony import */ var _useParams_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./useParams.js */ "./node_modules/@tanstack/react-router/dist/esm/useParams.js");
/* harmony import */ var _useSearch_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./useSearch.js */ "./node_modules/@tanstack/react-router/dist/esm/useSearch.js");
/* harmony import */ var _useNavigate_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./useNavigate.js */ "./node_modules/@tanstack/react-router/dist/esm/useNavigate.js");
/* harmony import */ var _useMatch_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./useMatch.js */ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");
/* harmony import */ var _link_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./link.js */ "./node_modules/@tanstack/react-router/dist/esm/link.js");











function getRouteApi(id) {
  return new RouteApi({ id });
}
class RouteApi extends _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.BaseRouteApi {
  /**
   * @deprecated Use the `getRouteApi` function instead.
   */
  constructor({ id }) {
    super({ id });
    this.useMatch = (opts) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_9__.useMatch)({
        select: opts?.select,
        from: this.id,
        structuralSharing: opts?.structuralSharing
      });
    };
    this.useRouteContext = (opts) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_9__.useMatch)({
        from: this.id,
        select: (d) => opts?.select ? opts.select(d.context) : d.context
      });
    };
    this.useSearch = (opts) => {
      return (0,_useSearch_js__WEBPACK_IMPORTED_MODULE_7__.useSearch)({
        select: opts?.select,
        structuralSharing: opts?.structuralSharing,
        from: this.id
      });
    };
    this.useParams = (opts) => {
      return (0,_useParams_js__WEBPACK_IMPORTED_MODULE_6__.useParams)({
        select: opts?.select,
        structuralSharing: opts?.structuralSharing,
        from: this.id
      });
    };
    this.useLoaderDeps = (opts) => {
      return (0,_useLoaderDeps_js__WEBPACK_IMPORTED_MODULE_5__.useLoaderDeps)({ ...opts, from: this.id, strict: false });
    };
    this.useLoaderData = (opts) => {
      return (0,_useLoaderData_js__WEBPACK_IMPORTED_MODULE_4__.useLoaderData)({ ...opts, from: this.id, strict: false });
    };
    this.useNavigate = () => {
      const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_10__.useRouter)();
      return (0,_useNavigate_js__WEBPACK_IMPORTED_MODULE_8__.useNavigate)({ from: router.routesById[this.id].fullPath });
    };
    this.notFound = (opts) => {
      return (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__.notFound)({ routeId: this.id, ...opts });
    };
    this.Link = react__WEBPACK_IMPORTED_MODULE_3__.forwardRef((props, ref) => {
      const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_10__.useRouter)();
      const fullPath = router.routesById[this.id].fullPath;
      return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_link_js__WEBPACK_IMPORTED_MODULE_11__.Link, { ref, from: fullPath, ...props });
    });
  }
}
class Route extends _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.BaseRoute {
  /**
   * @deprecated Use the `createRoute` function instead.
   */
  constructor(options) {
    super(options);
    this.useMatch = (opts) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_9__.useMatch)({
        select: opts?.select,
        from: this.id,
        structuralSharing: opts?.structuralSharing
      });
    };
    this.useRouteContext = (opts) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_9__.useMatch)({
        ...opts,
        from: this.id,
        select: (d) => opts?.select ? opts.select(d.context) : d.context
      });
    };
    this.useSearch = (opts) => {
      return (0,_useSearch_js__WEBPACK_IMPORTED_MODULE_7__.useSearch)({
        select: opts?.select,
        structuralSharing: opts?.structuralSharing,
        from: this.id
      });
    };
    this.useParams = (opts) => {
      return (0,_useParams_js__WEBPACK_IMPORTED_MODULE_6__.useParams)({
        select: opts?.select,
        structuralSharing: opts?.structuralSharing,
        from: this.id
      });
    };
    this.useLoaderDeps = (opts) => {
      return (0,_useLoaderDeps_js__WEBPACK_IMPORTED_MODULE_5__.useLoaderDeps)({ ...opts, from: this.id });
    };
    this.useLoaderData = (opts) => {
      return (0,_useLoaderData_js__WEBPACK_IMPORTED_MODULE_4__.useLoaderData)({ ...opts, from: this.id });
    };
    this.useNavigate = () => {
      return (0,_useNavigate_js__WEBPACK_IMPORTED_MODULE_8__.useNavigate)({ from: this.fullPath });
    };
    this.Link = react__WEBPACK_IMPORTED_MODULE_3__.forwardRef(
      (props, ref) => {
        return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_link_js__WEBPACK_IMPORTED_MODULE_11__.Link, { ref, from: this.fullPath, ...props });
      }
    );
    this.$$typeof = Symbol.for("react.memo");
  }
}
function createRoute(options) {
  return new Route(
    // TODO: Help us TypeChris, you're our only hope!
    options
  );
}
function createRootRouteWithContext() {
  return (options) => {
    return createRootRoute(options);
  };
}
const rootRouteWithContext = createRootRouteWithContext;
class RootRoute extends _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.BaseRootRoute {
  /**
   * @deprecated `RootRoute` is now an internal implementation detail. Use `createRootRoute()` instead.
   */
  constructor(options) {
    super(options);
    this.useMatch = (opts) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_9__.useMatch)({
        select: opts?.select,
        from: this.id,
        structuralSharing: opts?.structuralSharing
      });
    };
    this.useRouteContext = (opts) => {
      return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_9__.useMatch)({
        ...opts,
        from: this.id,
        select: (d) => opts?.select ? opts.select(d.context) : d.context
      });
    };
    this.useSearch = (opts) => {
      return (0,_useSearch_js__WEBPACK_IMPORTED_MODULE_7__.useSearch)({
        select: opts?.select,
        structuralSharing: opts?.structuralSharing,
        from: this.id
      });
    };
    this.useParams = (opts) => {
      return (0,_useParams_js__WEBPACK_IMPORTED_MODULE_6__.useParams)({
        select: opts?.select,
        structuralSharing: opts?.structuralSharing,
        from: this.id
      });
    };
    this.useLoaderDeps = (opts) => {
      return (0,_useLoaderDeps_js__WEBPACK_IMPORTED_MODULE_5__.useLoaderDeps)({ ...opts, from: this.id });
    };
    this.useLoaderData = (opts) => {
      return (0,_useLoaderData_js__WEBPACK_IMPORTED_MODULE_4__.useLoaderData)({ ...opts, from: this.id });
    };
    this.useNavigate = () => {
      return (0,_useNavigate_js__WEBPACK_IMPORTED_MODULE_8__.useNavigate)({ from: this.fullPath });
    };
    this.Link = react__WEBPACK_IMPORTED_MODULE_3__.forwardRef(
      (props, ref) => {
        return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(_link_js__WEBPACK_IMPORTED_MODULE_11__.Link, { ref, from: this.fullPath, ...props });
      }
    );
    this.$$typeof = Symbol.for("react.memo");
  }
}
function createRootRoute(options) {
  return new RootRoute(options);
}
function createRouteMask(opts) {
  return opts;
}
class NotFoundRoute extends Route {
  constructor(options) {
    super({
      ...options,
      id: "404"
    });
  }
}

//# sourceMappingURL=route.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/router.js":
/*!****************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/router.js ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Router: () => (/* binding */ Router),
/* harmony export */   createRouter: () => (/* binding */ createRouter)
/* harmony export */ });
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/router.js");
/* harmony import */ var _fileRoute_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./fileRoute.js */ "./node_modules/@tanstack/react-router/dist/esm/fileRoute.js");


const createRouter = (options) => {
  return new Router(options);
};
class Router extends _tanstack_router_core__WEBPACK_IMPORTED_MODULE_0__.RouterCore {
  constructor(options) {
    super(options);
  }
}
if (typeof globalThis !== "undefined") {
  globalThis.createFileRoute = _fileRoute_js__WEBPACK_IMPORTED_MODULE_1__.createFileRoute;
  globalThis.createLazyFileRoute = _fileRoute_js__WEBPACK_IMPORTED_MODULE_1__.createLazyFileRoute;
} else if (typeof window !== "undefined") {
  window.createFileRoute = _fileRoute_js__WEBPACK_IMPORTED_MODULE_1__.createFileRoute;
  window.createLazyFileRoute = _fileRoute_js__WEBPACK_IMPORTED_MODULE_1__.createLazyFileRoute;
}

//# sourceMappingURL=router.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/routerContext.js":
/*!***********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/routerContext.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   getRouterContext: () => (/* binding */ getRouterContext)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");

const routerContext = react__WEBPACK_IMPORTED_MODULE_0__.createContext(null);
function getRouterContext() {
  if (typeof document === "undefined") {
    return routerContext;
  }
  if (window.__TSR_ROUTER_CONTEXT__) {
    return window.__TSR_ROUTER_CONTEXT__;
  }
  window.__TSR_ROUTER_CONTEXT__ = routerContext;
  return routerContext;
}

//# sourceMappingURL=routerContext.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/scroll-restoration.js":
/*!****************************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/scroll-restoration.js ***!
  \****************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   ScrollRestoration: () => (/* binding */ ScrollRestoration)
/* harmony export */ });
/* harmony import */ var react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react/jsx-runtime */ "react/jsx-runtime");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/scroll-restoration.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");
/* harmony import */ var _ScriptOnce_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./ScriptOnce.js */ "./node_modules/@tanstack/react-router/dist/esm/ScriptOnce.js");




function ScrollRestoration() {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_2__.useRouter)();
  if (!router.isScrollRestoring || !router.isServer) {
    return null;
  }
  if (typeof router.options.scrollRestoration === "function") {
    const shouldRestore = router.options.scrollRestoration({
      location: router.latestLocation
    });
    if (!shouldRestore) {
      return null;
    }
  }
  const getKey = router.options.getScrollRestorationKey || _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.defaultGetScrollRestorationKey;
  const userKey = getKey(router.latestLocation);
  const resolvedKey = userKey !== (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.defaultGetScrollRestorationKey)(router.latestLocation) ? userKey : void 0;
  const restoreScrollOptions = {
    storageKey: _tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.storageKey,
    shouldScrollRestoration: true
  };
  if (resolvedKey) {
    restoreScrollOptions.key = resolvedKey;
  }
  return /* @__PURE__ */ (0,react_jsx_runtime__WEBPACK_IMPORTED_MODULE_0__.jsx)(
    _ScriptOnce_js__WEBPACK_IMPORTED_MODULE_3__.ScriptOnce,
    {
      children: `(${_tanstack_router_core__WEBPACK_IMPORTED_MODULE_1__.restoreScroll.toString()})(${JSON.stringify(restoreScrollOptions)})`
    }
  );
}

//# sourceMappingURL=scroll-restoration.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useLoaderData.js":
/*!***********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useLoaderData.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useLoaderData: () => (/* binding */ useLoaderData)
/* harmony export */ });
/* harmony import */ var _useMatch_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./useMatch.js */ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js");

function useLoaderData(opts) {
  return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_0__.useMatch)({
    from: opts.from,
    strict: opts.strict,
    structuralSharing: opts.structuralSharing,
    select: (s) => {
      return opts.select ? opts.select(s.loaderData) : s.loaderData;
    }
  });
}

//# sourceMappingURL=useLoaderData.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useLoaderDeps.js":
/*!***********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useLoaderDeps.js ***!
  \***********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useLoaderDeps: () => (/* binding */ useLoaderDeps)
/* harmony export */ });
/* harmony import */ var _useMatch_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./useMatch.js */ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js");

function useLoaderDeps(opts) {
  const { select, ...rest } = opts;
  return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_0__.useMatch)({
    ...rest,
    select: (s) => {
      return select ? select(s.loaderDeps) : s.loaderDeps;
    }
  });
}

//# sourceMappingURL=useLoaderDeps.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useLocation.js":
/*!*********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useLocation.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useLocation: () => (/* binding */ useLocation)
/* harmony export */ });
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");

function useLocation(opts) {
  return (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_0__.useRouterState)({
    select: (state) => opts?.select ? opts.select(state.location) : state.location
  });
}

//# sourceMappingURL=useLocation.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js":
/*!******************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useMatch.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useMatch: () => (/* binding */ useMatch)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _useRouterState_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./useRouterState.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js");
/* harmony import */ var _matchContext_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./matchContext.js */ "./node_modules/@tanstack/react-router/dist/esm/matchContext.js");




function useMatch(opts) {
  const nearestMatchId = react__WEBPACK_IMPORTED_MODULE_0__.useContext(
    opts.from ? _matchContext_js__WEBPACK_IMPORTED_MODULE_3__.dummyMatchContext : _matchContext_js__WEBPACK_IMPORTED_MODULE_3__.matchContext
  );
  const matchSelection = (0,_useRouterState_js__WEBPACK_IMPORTED_MODULE_2__.useRouterState)({
    select: (state) => {
      const match = state.matches.find(
        (d) => opts.from ? opts.from === d.routeId : d.id === nearestMatchId
      );
      (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_1__["default"])(
        !((opts.shouldThrow ?? true) && !match),
        `Could not find ${opts.from ? `an active match from "${opts.from}"` : "a nearest match!"}`
      );
      if (match === void 0) {
        return void 0;
      }
      return opts.select ? opts.select(match) : match;
    },
    structuralSharing: opts.structuralSharing
  });
  return matchSelection;
}

//# sourceMappingURL=useMatch.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useNavigate.js":
/*!*********************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useNavigate.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Navigate: () => (/* binding */ Navigate),
/* harmony export */   useNavigate: () => (/* binding */ useNavigate)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/react-router/dist/esm/utils.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");



function useNavigate(_defaultOpts) {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_2__.useRouter)();
  return react__WEBPACK_IMPORTED_MODULE_0__.useCallback(
    (options) => {
      return router.navigate({
        ...options,
        from: options.from ?? _defaultOpts?.from
      });
    },
    [_defaultOpts?.from, router]
  );
}
function Navigate(props) {
  const router = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_2__.useRouter)();
  const navigate = useNavigate();
  const previousPropsRef = react__WEBPACK_IMPORTED_MODULE_0__.useRef(null);
  (0,_utils_js__WEBPACK_IMPORTED_MODULE_1__.useLayoutEffect)(() => {
    if (previousPropsRef.current !== props) {
      navigate(props);
      previousPropsRef.current = props;
    }
  }, [router, props, navigate]);
  return null;
}

//# sourceMappingURL=useNavigate.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useParams.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useParams.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useParams: () => (/* binding */ useParams)
/* harmony export */ });
/* harmony import */ var _useMatch_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./useMatch.js */ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js");

function useParams(opts) {
  return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_0__.useMatch)({
    from: opts.from,
    shouldThrow: opts.shouldThrow,
    structuralSharing: opts.structuralSharing,
    strict: opts.strict,
    select: (match) => {
      const params = opts.strict === false ? match.params : match._strictParams;
      return opts.select ? opts.select(params) : params;
    }
  });
}

//# sourceMappingURL=useParams.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useRouter.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useRouter: () => (/* binding */ useRouter)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var tiny_warning__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! tiny-warning */ "./node_modules/tiny-warning/dist/tiny-warning.esm.js");
/* harmony import */ var _routerContext_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./routerContext.js */ "./node_modules/@tanstack/react-router/dist/esm/routerContext.js");



function useRouter(opts) {
  const value = react__WEBPACK_IMPORTED_MODULE_0__.useContext((0,_routerContext_js__WEBPACK_IMPORTED_MODULE_2__.getRouterContext)());
  (0,tiny_warning__WEBPACK_IMPORTED_MODULE_1__["default"])(
    !((opts?.warn ?? true) && !value),
    "useRouter must be used inside a <RouterProvider> component!"
  );
  return value;
}

//# sourceMappingURL=useRouter.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useRouterState.js":
/*!************************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useRouterState.js ***!
  \************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useRouterState: () => (/* binding */ useRouterState)
/* harmony export */ });
/* harmony import */ var _tanstack_react_store__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @tanstack/react-store */ "./node_modules/@tanstack/react-store/dist/esm/index.js");
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! react */ "react");
/* harmony import */ var _tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @tanstack/router-core */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");
/* harmony import */ var _useRouter_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./useRouter.js */ "./node_modules/@tanstack/react-router/dist/esm/useRouter.js");




function useRouterState(opts) {
  const contextRouter = (0,_useRouter_js__WEBPACK_IMPORTED_MODULE_3__.useRouter)({
    warn: opts?.router === void 0
  });
  const router = opts?.router || contextRouter;
  const previousResult = (0,react__WEBPACK_IMPORTED_MODULE_1__.useRef)(void 0);
  return (0,_tanstack_react_store__WEBPACK_IMPORTED_MODULE_0__.useStore)(router.__store, (state) => {
    if (opts?.select) {
      if (opts.structuralSharing ?? router.options.defaultStructuralSharing) {
        const newSlice = (0,_tanstack_router_core__WEBPACK_IMPORTED_MODULE_2__.replaceEqualDeep)(
          previousResult.current,
          opts.select(state)
        );
        previousResult.current = newSlice;
        return newSlice;
      }
      return opts.select(state);
    }
    return state;
  });
}

//# sourceMappingURL=useRouterState.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/useSearch.js":
/*!*******************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/useSearch.js ***!
  \*******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useSearch: () => (/* binding */ useSearch)
/* harmony export */ });
/* harmony import */ var _useMatch_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./useMatch.js */ "./node_modules/@tanstack/react-router/dist/esm/useMatch.js");

function useSearch(opts) {
  return (0,_useMatch_js__WEBPACK_IMPORTED_MODULE_0__.useMatch)({
    from: opts.from,
    strict: opts.strict,
    shouldThrow: opts.shouldThrow,
    structuralSharing: opts.structuralSharing,
    select: (match) => {
      return opts.select ? opts.select(match.search) : match.search;
    }
  });
}

//# sourceMappingURL=useSearch.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-router/dist/esm/utils.js":
/*!***************************************************************!*\
  !*** ./node_modules/@tanstack/react-router/dist/esm/utils.js ***!
  \***************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   useForwardedRef: () => (/* binding */ useForwardedRef),
/* harmony export */   useIntersectionObserver: () => (/* binding */ useIntersectionObserver),
/* harmony export */   useLayoutEffect: () => (/* binding */ useLayoutEffect),
/* harmony export */   usePrevious: () => (/* binding */ usePrevious),
/* harmony export */   useStableCallback: () => (/* binding */ useStableCallback)
/* harmony export */ });
/* harmony import */ var react__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! react */ "react");

function useStableCallback(fn) {
  const fnRef = react__WEBPACK_IMPORTED_MODULE_0__.useRef(fn);
  fnRef.current = fn;
  const ref = react__WEBPACK_IMPORTED_MODULE_0__.useRef((...args) => fnRef.current(...args));
  return ref.current;
}
const useLayoutEffect = typeof window !== "undefined" ? react__WEBPACK_IMPORTED_MODULE_0__.useLayoutEffect : react__WEBPACK_IMPORTED_MODULE_0__.useEffect;
function usePrevious(value) {
  const ref = react__WEBPACK_IMPORTED_MODULE_0__.useRef({
    value,
    prev: null
  });
  const current = ref.current.value;
  if (value !== current) {
    ref.current = {
      value,
      prev: current
    };
  }
  return ref.current.prev;
}
function useIntersectionObserver(ref, callback, intersectionObserverOptions = {}, options = {}) {
  react__WEBPACK_IMPORTED_MODULE_0__.useEffect(() => {
    if (!ref.current || options.disabled || typeof IntersectionObserver !== "function") {
      return;
    }
    const observer = new IntersectionObserver(([entry]) => {
      callback(entry);
    }, intersectionObserverOptions);
    observer.observe(ref.current);
    return () => {
      observer.disconnect();
    };
  }, [callback, intersectionObserverOptions, options.disabled, ref]);
}
function useForwardedRef(ref) {
  const innerRef = react__WEBPACK_IMPORTED_MODULE_0__.useRef(null);
  react__WEBPACK_IMPORTED_MODULE_0__.useImperativeHandle(ref, () => innerRef.current, []);
  return innerRef;
}

//# sourceMappingURL=utils.js.map


/***/ }),

/***/ "./node_modules/@tanstack/react-store/dist/esm/index.js":
/*!**************************************************************!*\
  !*** ./node_modules/@tanstack/react-store/dist/esm/index.js ***!
  \**************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Derived: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.Derived),
/* harmony export */   Effect: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.Effect),
/* harmony export */   Store: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.Store),
/* harmony export */   __depsThatHaveWrittenThisTick: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.__depsThatHaveWrittenThisTick),
/* harmony export */   __derivedToStore: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.__derivedToStore),
/* harmony export */   __flush: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.__flush),
/* harmony export */   __storeToDerived: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.__storeToDerived),
/* harmony export */   batch: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.batch),
/* harmony export */   isUpdaterFunction: () => (/* reexport safe */ _tanstack_store__WEBPACK_IMPORTED_MODULE_1__.isUpdaterFunction),
/* harmony export */   shallow: () => (/* binding */ shallow),
/* harmony export */   useStore: () => (/* binding */ useStore)
/* harmony export */ });
/* harmony import */ var use_sync_external_store_shim_with_selector_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! use-sync-external-store/shim/with-selector.js */ "./node_modules/use-sync-external-store/shim/with-selector.js");
/* harmony import */ var _tanstack_store__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/store */ "./node_modules/@tanstack/store/dist/esm/index.js");


function useStore(store, selector = (d) => d, options = {}) {
  const equal = options.equal ?? shallow;
  const slice = (0,use_sync_external_store_shim_with_selector_js__WEBPACK_IMPORTED_MODULE_0__.useSyncExternalStoreWithSelector)(
    store.subscribe,
    () => store.state,
    () => store.state,
    selector,
    equal
  );
  return slice;
}
function shallow(objA, objB) {
  if (Object.is(objA, objB)) {
    return true;
  }
  if (typeof objA !== "object" || objA === null || typeof objB !== "object" || objB === null) {
    return false;
  }
  if (objA instanceof Map && objB instanceof Map) {
    if (objA.size !== objB.size) return false;
    for (const [k, v] of objA) {
      if (!objB.has(k) || !Object.is(v, objB.get(k))) return false;
    }
    return true;
  }
  if (objA instanceof Set && objB instanceof Set) {
    if (objA.size !== objB.size) return false;
    for (const v of objA) {
      if (!objB.has(v)) return false;
    }
    return true;
  }
  if (objA instanceof Date && objB instanceof Date) {
    if (objA.getTime() !== objB.getTime()) return false;
    return true;
  }
  const keysA = getOwnKeys(objA);
  if (keysA.length !== getOwnKeys(objB).length) {
    return false;
  }
  for (let i = 0; i < keysA.length; i++) {
    if (!Object.prototype.hasOwnProperty.call(objB, keysA[i]) || !Object.is(objA[keysA[i]], objB[keysA[i]])) {
      return false;
    }
  }
  return true;
}
function getOwnKeys(obj) {
  return Object.keys(obj).concat(
    Object.getOwnPropertySymbols(obj)
  );
}

//# sourceMappingURL=index.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/link.js":
/*!*************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/link.js ***!
  \*************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   preloadWarning: () => (/* binding */ preloadWarning)
/* harmony export */ });
const preloadWarning = "Error preloading route! ☝️";

//# sourceMappingURL=link.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/load-matches.js":
/*!*********************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/load-matches.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   componentTypes: () => (/* binding */ componentTypes),
/* harmony export */   loadMatches: () => (/* binding */ loadMatches),
/* harmony export */   loadRouteChunk: () => (/* binding */ loadRouteChunk),
/* harmony export */   routeNeedsPreload: () => (/* binding */ routeNeedsPreload)
/* harmony export */ });
/* harmony import */ var _tanstack_store__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @tanstack/store */ "./node_modules/@tanstack/store/dist/esm/scheduler.js");
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");
/* harmony import */ var _not_found_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./not-found.js */ "./node_modules/@tanstack/router-core/dist/esm/not-found.js");
/* harmony import */ var _root_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./root.js */ "./node_modules/@tanstack/router-core/dist/esm/root.js");
/* harmony import */ var _redirect_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./redirect.js */ "./node_modules/@tanstack/router-core/dist/esm/redirect.js");






const triggerOnReady = (inner) => {
  if (!inner.rendered) {
    inner.rendered = true;
    return inner.onReady?.();
  }
};
const resolvePreload = (inner, matchId) => {
  return !!(inner.preload && !inner.router.state.matches.some((d) => d.id === matchId));
};
const buildMatchContext = (inner, index, includeCurrentMatch = true) => {
  const context = {
    ...inner.router.options.context ?? {}
  };
  const end = includeCurrentMatch ? index : index - 1;
  for (let i = 0; i <= end; i++) {
    const innerMatch = inner.matches[i];
    if (!innerMatch) continue;
    const m = inner.router.getMatch(innerMatch.id);
    if (!m) continue;
    Object.assign(context, m.__routeContext, m.__beforeLoadContext);
  }
  return context;
};
const _handleNotFound = (inner, err) => {
  const routeCursor = inner.router.routesById[err.routeId ?? ""] ?? inner.router.routeTree;
  if (!routeCursor.options.notFoundComponent && inner.router.options?.defaultNotFoundComponent) {
    routeCursor.options.notFoundComponent = inner.router.options.defaultNotFoundComponent;
  }
  (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_1__["default"])(
    routeCursor.options.notFoundComponent,
    "No notFoundComponent found. Please set a notFoundComponent on your route or provide a defaultNotFoundComponent to the router."
  );
  const matchForRoute = inner.matches.find((m) => m.routeId === routeCursor.id);
  (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_1__["default"])(matchForRoute, "Could not find match for route: " + routeCursor.id);
  inner.updateMatch(matchForRoute.id, (prev) => ({
    ...prev,
    status: "notFound",
    error: err,
    isFetching: false
  }));
  if (err.routerCode === "BEFORE_LOAD" && routeCursor.parentRoute) {
    err.routeId = routeCursor.parentRoute.id;
    _handleNotFound(inner, err);
  }
};
const handleRedirectAndNotFound = (inner, match, err) => {
  if (!(0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(err) && !(0,_not_found_js__WEBPACK_IMPORTED_MODULE_3__.isNotFound)(err)) return;
  if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(err) && err.redirectHandled && !err.options.reloadDocument) {
    throw err;
  }
  if (match) {
    match._nonReactive.beforeLoadPromise?.resolve();
    match._nonReactive.loaderPromise?.resolve();
    match._nonReactive.beforeLoadPromise = void 0;
    match._nonReactive.loaderPromise = void 0;
    const status = (0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(err) ? "redirected" : "notFound";
    match._nonReactive.error = err;
    inner.updateMatch(match.id, (prev) => ({
      ...prev,
      status,
      isFetching: false,
      error: err
    }));
    if ((0,_not_found_js__WEBPACK_IMPORTED_MODULE_3__.isNotFound)(err) && !err.routeId) {
      err.routeId = match.routeId;
    }
    match._nonReactive.loadPromise?.resolve();
  }
  if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(err)) {
    inner.rendered = true;
    err.options._fromLocation = inner.location;
    err.redirectHandled = true;
    err = inner.router.resolveRedirect(err);
    throw err;
  } else {
    _handleNotFound(inner, err);
    throw err;
  }
};
const shouldSkipLoader = (inner, matchId) => {
  const match = inner.router.getMatch(matchId);
  if (!inner.router.isServer && match._nonReactive.dehydrated) {
    return true;
  }
  if (inner.router.isServer && match.ssr === false) {
    return true;
  }
  return false;
};
const handleSerialError = (inner, index, err, routerCode) => {
  const { id: matchId, routeId } = inner.matches[index];
  const route = inner.router.looseRoutesById[routeId];
  if (err instanceof Promise) {
    throw err;
  }
  err.routerCode = routerCode;
  inner.firstBadMatchIndex ??= index;
  handleRedirectAndNotFound(inner, inner.router.getMatch(matchId), err);
  try {
    route.options.onError?.(err);
  } catch (errorHandlerErr) {
    err = errorHandlerErr;
    handleRedirectAndNotFound(inner, inner.router.getMatch(matchId), err);
  }
  inner.updateMatch(matchId, (prev) => {
    prev._nonReactive.beforeLoadPromise?.resolve();
    prev._nonReactive.beforeLoadPromise = void 0;
    prev._nonReactive.loadPromise?.resolve();
    return {
      ...prev,
      error: err,
      status: "error",
      isFetching: false,
      updatedAt: Date.now(),
      abortController: new AbortController()
    };
  });
};
const isBeforeLoadSsr = (inner, matchId, index, route) => {
  const existingMatch = inner.router.getMatch(matchId);
  const parentMatchId = inner.matches[index - 1]?.id;
  const parentMatch = parentMatchId ? inner.router.getMatch(parentMatchId) : void 0;
  if (inner.router.isShell()) {
    existingMatch.ssr = route.id === _root_js__WEBPACK_IMPORTED_MODULE_4__.rootRouteId;
    return;
  }
  if (parentMatch?.ssr === false) {
    existingMatch.ssr = false;
    return;
  }
  const parentOverride = (tempSsr2) => {
    if (tempSsr2 === true && parentMatch?.ssr === "data-only") {
      return "data-only";
    }
    return tempSsr2;
  };
  const defaultSsr = inner.router.options.defaultSsr ?? true;
  if (route.options.ssr === void 0) {
    existingMatch.ssr = parentOverride(defaultSsr);
    return;
  }
  if (typeof route.options.ssr !== "function") {
    existingMatch.ssr = parentOverride(route.options.ssr);
    return;
  }
  const { search, params } = existingMatch;
  const ssrFnContext = {
    search: makeMaybe(search, existingMatch.searchError),
    params: makeMaybe(params, existingMatch.paramsError),
    location: inner.location,
    matches: inner.matches.map((match) => ({
      index: match.index,
      pathname: match.pathname,
      fullPath: match.fullPath,
      staticData: match.staticData,
      id: match.id,
      routeId: match.routeId,
      search: makeMaybe(match.search, match.searchError),
      params: makeMaybe(match.params, match.paramsError),
      ssr: match.ssr
    }))
  };
  const tempSsr = route.options.ssr(ssrFnContext);
  if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(tempSsr)) {
    return tempSsr.then((ssr) => {
      existingMatch.ssr = parentOverride(ssr ?? defaultSsr);
    });
  }
  existingMatch.ssr = parentOverride(tempSsr ?? defaultSsr);
  return;
};
const setupPendingTimeout = (inner, matchId, route, match) => {
  if (match._nonReactive.pendingTimeout !== void 0) return;
  const pendingMs = route.options.pendingMs ?? inner.router.options.defaultPendingMs;
  const shouldPending = !!(inner.onReady && !inner.router.isServer && !resolvePreload(inner, matchId) && (route.options.loader || route.options.beforeLoad || routeNeedsPreload(route)) && typeof pendingMs === "number" && pendingMs !== Infinity && (route.options.pendingComponent ?? inner.router.options?.defaultPendingComponent));
  if (shouldPending) {
    const pendingTimeout = setTimeout(() => {
      triggerOnReady(inner);
    }, pendingMs);
    match._nonReactive.pendingTimeout = pendingTimeout;
  }
};
const preBeforeLoadSetup = (inner, matchId, route) => {
  const existingMatch = inner.router.getMatch(matchId);
  if (!existingMatch._nonReactive.beforeLoadPromise && !existingMatch._nonReactive.loaderPromise)
    return;
  setupPendingTimeout(inner, matchId, route, existingMatch);
  const then = () => {
    const match = inner.router.getMatch(matchId);
    if (match.preload && (match.status === "redirected" || match.status === "notFound")) {
      handleRedirectAndNotFound(inner, match, match.error);
    }
  };
  return existingMatch._nonReactive.beforeLoadPromise ? existingMatch._nonReactive.beforeLoadPromise.then(then) : then();
};
const executeBeforeLoad = (inner, matchId, index, route) => {
  const match = inner.router.getMatch(matchId);
  const prevLoadPromise = match._nonReactive.loadPromise;
  match._nonReactive.loadPromise = (0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.createControlledPromise)(() => {
    prevLoadPromise?.resolve();
  });
  const { paramsError, searchError } = match;
  if (paramsError) {
    handleSerialError(inner, index, paramsError, "PARSE_PARAMS");
  }
  if (searchError) {
    handleSerialError(inner, index, searchError, "VALIDATE_SEARCH");
  }
  setupPendingTimeout(inner, matchId, route, match);
  const abortController = new AbortController();
  const parentMatchId = inner.matches[index - 1]?.id;
  const parentMatch = parentMatchId ? inner.router.getMatch(parentMatchId) : void 0;
  parentMatch?.context ?? inner.router.options.context ?? void 0;
  let isPending = false;
  const pending = () => {
    if (isPending) return;
    isPending = true;
    inner.updateMatch(matchId, (prev) => ({
      ...prev,
      isFetching: "beforeLoad",
      fetchCount: prev.fetchCount + 1,
      abortController
      // Note: We intentionally don't update context here.
      // Context should only be updated after beforeLoad resolves to avoid
      // components seeing incomplete context during async beforeLoad execution.
    }));
  };
  const resolve = () => {
    match._nonReactive.beforeLoadPromise?.resolve();
    match._nonReactive.beforeLoadPromise = void 0;
    inner.updateMatch(matchId, (prev) => ({
      ...prev,
      isFetching: false
    }));
  };
  if (!route.options.beforeLoad) {
    (0,_tanstack_store__WEBPACK_IMPORTED_MODULE_0__.batch)(() => {
      pending();
      resolve();
    });
    return;
  }
  match._nonReactive.beforeLoadPromise = (0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.createControlledPromise)();
  const context = {
    ...buildMatchContext(inner, index, false),
    ...match.__routeContext
  };
  const { search, params, cause } = match;
  const preload = resolvePreload(inner, matchId);
  const beforeLoadFnContext = {
    search,
    abortController,
    params,
    preload,
    context,
    location: inner.location,
    navigate: (opts) => inner.router.navigate({
      ...opts,
      _fromLocation: inner.location
    }),
    buildLocation: inner.router.buildLocation,
    cause: preload ? "preload" : cause,
    matches: inner.matches,
    ...inner.router.options.additionalContext
  };
  const updateContext = (beforeLoadContext2) => {
    if (beforeLoadContext2 === void 0) {
      (0,_tanstack_store__WEBPACK_IMPORTED_MODULE_0__.batch)(() => {
        pending();
        resolve();
      });
      return;
    }
    if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(beforeLoadContext2) || (0,_not_found_js__WEBPACK_IMPORTED_MODULE_3__.isNotFound)(beforeLoadContext2)) {
      pending();
      handleSerialError(inner, index, beforeLoadContext2, "BEFORE_LOAD");
    }
    (0,_tanstack_store__WEBPACK_IMPORTED_MODULE_0__.batch)(() => {
      pending();
      inner.updateMatch(matchId, (prev) => ({
        ...prev,
        __beforeLoadContext: beforeLoadContext2
      }));
      resolve();
    });
  };
  let beforeLoadContext;
  try {
    beforeLoadContext = route.options.beforeLoad(beforeLoadFnContext);
    if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(beforeLoadContext)) {
      pending();
      return beforeLoadContext.catch((err) => {
        handleSerialError(inner, index, err, "BEFORE_LOAD");
      }).then(updateContext);
    }
  } catch (err) {
    pending();
    handleSerialError(inner, index, err, "BEFORE_LOAD");
  }
  updateContext(beforeLoadContext);
  return;
};
const handleBeforeLoad = (inner, index) => {
  const { id: matchId, routeId } = inner.matches[index];
  const route = inner.router.looseRoutesById[routeId];
  const serverSsr = () => {
    if (inner.router.isServer) {
      const maybePromise = isBeforeLoadSsr(inner, matchId, index, route);
      if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(maybePromise)) return maybePromise.then(queueExecution);
    }
    return queueExecution();
  };
  const execute = () => executeBeforeLoad(inner, matchId, index, route);
  const queueExecution = () => {
    if (shouldSkipLoader(inner, matchId)) return;
    const result = preBeforeLoadSetup(inner, matchId, route);
    return (0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(result) ? result.then(execute) : execute();
  };
  return serverSsr();
};
const executeHead = (inner, matchId, route) => {
  const match = inner.router.getMatch(matchId);
  if (!match) {
    return;
  }
  if (!route.options.head && !route.options.scripts && !route.options.headers) {
    return;
  }
  const assetContext = {
    matches: inner.matches,
    match,
    params: match.params,
    loaderData: match.loaderData
  };
  return Promise.all([
    route.options.head?.(assetContext),
    route.options.scripts?.(assetContext),
    route.options.headers?.(assetContext)
  ]).then(([headFnContent, scripts, headers]) => {
    const meta = headFnContent?.meta;
    const links = headFnContent?.links;
    const headScripts = headFnContent?.scripts;
    const styles = headFnContent?.styles;
    return {
      meta,
      links,
      headScripts,
      headers,
      scripts,
      styles
    };
  });
};
const getLoaderContext = (inner, matchId, index, route) => {
  const parentMatchPromise = inner.matchPromises[index - 1];
  const { params, loaderDeps, abortController, cause } = inner.router.getMatch(matchId);
  const context = buildMatchContext(inner, index);
  const preload = resolvePreload(inner, matchId);
  return {
    params,
    deps: loaderDeps,
    preload: !!preload,
    parentMatchPromise,
    abortController,
    context,
    location: inner.location,
    navigate: (opts) => inner.router.navigate({
      ...opts,
      _fromLocation: inner.location
    }),
    cause: preload ? "preload" : cause,
    route,
    ...inner.router.options.additionalContext
  };
};
const runLoader = async (inner, matchId, index, route) => {
  try {
    const match = inner.router.getMatch(matchId);
    try {
      if (!inner.router.isServer || match.ssr === true) {
        loadRouteChunk(route);
      }
      const loaderResult = route.options.loader?.(
        getLoaderContext(inner, matchId, index, route)
      );
      const loaderResultIsPromise = route.options.loader && (0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(loaderResult);
      const willLoadSomething = !!(loaderResultIsPromise || route._lazyPromise || route._componentsPromise || route.options.head || route.options.scripts || route.options.headers || match._nonReactive.minPendingPromise);
      if (willLoadSomething) {
        inner.updateMatch(matchId, (prev) => ({
          ...prev,
          isFetching: "loader"
        }));
      }
      if (route.options.loader) {
        const loaderData = loaderResultIsPromise ? await loaderResult : loaderResult;
        handleRedirectAndNotFound(
          inner,
          inner.router.getMatch(matchId),
          loaderData
        );
        if (loaderData !== void 0) {
          inner.updateMatch(matchId, (prev) => ({
            ...prev,
            loaderData
          }));
        }
      }
      if (route._lazyPromise) await route._lazyPromise;
      const headResult = executeHead(inner, matchId, route);
      const head = headResult ? await headResult : void 0;
      const pendingPromise = match._nonReactive.minPendingPromise;
      if (pendingPromise) await pendingPromise;
      if (route._componentsPromise) await route._componentsPromise;
      inner.updateMatch(matchId, (prev) => ({
        ...prev,
        error: void 0,
        status: "success",
        isFetching: false,
        updatedAt: Date.now(),
        ...head
      }));
    } catch (e) {
      let error = e;
      const pendingPromise = match._nonReactive.minPendingPromise;
      if (pendingPromise) await pendingPromise;
      if ((0,_not_found_js__WEBPACK_IMPORTED_MODULE_3__.isNotFound)(e)) {
        await route.options.notFoundComponent?.preload?.();
      }
      handleRedirectAndNotFound(inner, inner.router.getMatch(matchId), e);
      try {
        route.options.onError?.(e);
      } catch (onErrorError) {
        error = onErrorError;
        handleRedirectAndNotFound(
          inner,
          inner.router.getMatch(matchId),
          onErrorError
        );
      }
      const headResult = executeHead(inner, matchId, route);
      const head = headResult ? await headResult : void 0;
      inner.updateMatch(matchId, (prev) => ({
        ...prev,
        error,
        status: "error",
        isFetching: false,
        ...head
      }));
    }
  } catch (err) {
    const match = inner.router.getMatch(matchId);
    if (match) {
      const headResult = executeHead(inner, matchId, route);
      if (headResult) {
        const head = await headResult;
        inner.updateMatch(matchId, (prev) => ({
          ...prev,
          ...head
        }));
      }
      match._nonReactive.loaderPromise = void 0;
    }
    handleRedirectAndNotFound(inner, match, err);
  }
};
const loadRouteMatch = async (inner, index) => {
  const { id: matchId, routeId } = inner.matches[index];
  let loaderShouldRunAsync = false;
  let loaderIsRunningAsync = false;
  const route = inner.router.looseRoutesById[routeId];
  const commitContext = () => {
    inner.updateMatch(matchId, (prev) => ({
      ...prev,
      context: buildMatchContext(inner, index)
    }));
  };
  if (shouldSkipLoader(inner, matchId)) {
    if (inner.router.isServer) {
      const headResult = executeHead(inner, matchId, route);
      if (headResult) {
        const head = await headResult;
        inner.updateMatch(matchId, (prev) => ({
          ...prev,
          ...head
        }));
      }
      return inner.router.getMatch(matchId);
    }
  } else {
    const prevMatch = inner.router.getMatch(matchId);
    if (prevMatch._nonReactive.loaderPromise) {
      if (prevMatch.status === "success" && !inner.sync && !prevMatch.preload) {
        return prevMatch;
      }
      await prevMatch._nonReactive.loaderPromise;
      const match2 = inner.router.getMatch(matchId);
      const error = match2._nonReactive.error || match2.error;
      if (error) {
        handleRedirectAndNotFound(inner, match2, error);
      }
    } else {
      const age = Date.now() - prevMatch.updatedAt;
      const preload = resolvePreload(inner, matchId);
      const staleAge = preload ? route.options.preloadStaleTime ?? inner.router.options.defaultPreloadStaleTime ?? 3e4 : route.options.staleTime ?? inner.router.options.defaultStaleTime ?? 0;
      const shouldReloadOption = route.options.shouldReload;
      const shouldReload = typeof shouldReloadOption === "function" ? shouldReloadOption(getLoaderContext(inner, matchId, index, route)) : shouldReloadOption;
      const nextPreload = !!preload && !inner.router.state.matches.some((d) => d.id === matchId);
      const match2 = inner.router.getMatch(matchId);
      match2._nonReactive.loaderPromise = (0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.createControlledPromise)();
      if (nextPreload !== match2.preload) {
        inner.updateMatch(matchId, (prev) => ({
          ...prev,
          preload: nextPreload
        }));
      }
      const { status, invalid } = match2;
      loaderShouldRunAsync = status === "success" && (invalid || (shouldReload ?? age > staleAge));
      if (preload && route.options.preload === false) ;
      else if (loaderShouldRunAsync && !inner.sync) {
        loaderIsRunningAsync = true;
        (async () => {
          try {
            await runLoader(inner, matchId, index, route);
            commitContext();
            const match3 = inner.router.getMatch(matchId);
            match3._nonReactive.loaderPromise?.resolve();
            match3._nonReactive.loadPromise?.resolve();
            match3._nonReactive.loaderPromise = void 0;
          } catch (err) {
            if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(err)) {
              await inner.router.navigate(err.options);
            }
          }
        })();
      } else if (status !== "success" || loaderShouldRunAsync && inner.sync) {
        await runLoader(inner, matchId, index, route);
      } else {
        const headResult = executeHead(inner, matchId, route);
        if (headResult) {
          const head = await headResult;
          inner.updateMatch(matchId, (prev) => ({
            ...prev,
            ...head
          }));
        }
      }
    }
  }
  const match = inner.router.getMatch(matchId);
  if (!loaderIsRunningAsync) {
    match._nonReactive.loaderPromise?.resolve();
    match._nonReactive.loadPromise?.resolve();
  }
  clearTimeout(match._nonReactive.pendingTimeout);
  match._nonReactive.pendingTimeout = void 0;
  if (!loaderIsRunningAsync) match._nonReactive.loaderPromise = void 0;
  match._nonReactive.dehydrated = void 0;
  if (!loaderIsRunningAsync) {
    commitContext();
  }
  const nextIsFetching = loaderIsRunningAsync ? match.isFetching : false;
  if (nextIsFetching !== match.isFetching || match.invalid !== false) {
    inner.updateMatch(matchId, (prev) => ({
      ...prev,
      isFetching: nextIsFetching,
      invalid: false
    }));
    return inner.router.getMatch(matchId);
  } else {
    return match;
  }
};
async function loadMatches(arg) {
  const inner = Object.assign(arg, {
    matchPromises: []
  });
  if (!inner.router.isServer && inner.router.state.matches.some((d) => d._forcePending)) {
    triggerOnReady(inner);
  }
  try {
    for (let i = 0; i < inner.matches.length; i++) {
      const beforeLoad = handleBeforeLoad(inner, i);
      if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(beforeLoad)) await beforeLoad;
    }
    const max = inner.firstBadMatchIndex ?? inner.matches.length;
    for (let i = 0; i < max; i++) {
      inner.matchPromises.push(loadRouteMatch(inner, i));
    }
    await Promise.all(inner.matchPromises);
    const readyPromise = triggerOnReady(inner);
    if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(readyPromise)) await readyPromise;
  } catch (err) {
    if ((0,_not_found_js__WEBPACK_IMPORTED_MODULE_3__.isNotFound)(err) && !inner.preload) {
      const readyPromise = triggerOnReady(inner);
      if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.isPromise)(readyPromise)) await readyPromise;
      throw err;
    }
    if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_5__.isRedirect)(err)) {
      throw err;
    }
  }
  return inner.matches;
}
async function loadRouteChunk(route) {
  if (!route._lazyLoaded && route._lazyPromise === void 0) {
    if (route.lazyFn) {
      route._lazyPromise = route.lazyFn().then((lazyRoute) => {
        const { id: _id, ...options } = lazyRoute.options;
        Object.assign(route.options, options);
        route._lazyLoaded = true;
        route._lazyPromise = void 0;
      });
    } else {
      route._lazyLoaded = true;
    }
  }
  if (!route._componentsLoaded && route._componentsPromise === void 0) {
    const loadComponents = () => {
      const preloads = [];
      for (const type of componentTypes) {
        const preload = route.options[type]?.preload;
        if (preload) preloads.push(preload());
      }
      if (preloads.length)
        return Promise.all(preloads).then(() => {
          route._componentsLoaded = true;
          route._componentsPromise = void 0;
        });
      route._componentsLoaded = true;
      route._componentsPromise = void 0;
      return;
    };
    route._componentsPromise = route._lazyPromise ? route._lazyPromise.then(loadComponents) : loadComponents();
  }
  return route._componentsPromise;
}
function makeMaybe(value, error) {
  if (error) {
    return { status: "error", error };
  }
  return { status: "success", value };
}
function routeNeedsPreload(route) {
  for (const componentType of componentTypes) {
    if (route.options[componentType]?.preload) {
      return true;
    }
  }
  return false;
}
const componentTypes = [
  "component",
  "errorComponent",
  "pendingComponent",
  "notFoundComponent"
];

//# sourceMappingURL=load-matches.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/lru-cache.js":
/*!******************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/lru-cache.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createLRUCache: () => (/* binding */ createLRUCache)
/* harmony export */ });
function createLRUCache(max) {
  const cache = /* @__PURE__ */ new Map();
  let oldest;
  let newest;
  const touch = (entry) => {
    if (!entry.next) return;
    if (!entry.prev) {
      entry.next.prev = void 0;
      oldest = entry.next;
      entry.next = void 0;
      if (newest) {
        entry.prev = newest;
        newest.next = entry;
      }
    } else {
      entry.prev.next = entry.next;
      entry.next.prev = entry.prev;
      entry.next = void 0;
      if (newest) {
        newest.next = entry;
        entry.prev = newest;
      }
    }
    newest = entry;
  };
  return {
    get(key) {
      const entry = cache.get(key);
      if (!entry) return void 0;
      touch(entry);
      return entry.value;
    },
    set(key, value) {
      if (cache.size >= max && oldest) {
        const toDelete = oldest;
        cache.delete(toDelete.key);
        if (toDelete.next) {
          oldest = toDelete.next;
          toDelete.next.prev = void 0;
        }
        if (toDelete === newest) {
          newest = void 0;
        }
      }
      const existing = cache.get(key);
      if (existing) {
        existing.value = value;
        touch(existing);
      } else {
        const entry = { key, value, prev: newest };
        if (newest) newest.next = entry;
        newest = entry;
        if (!oldest) oldest = entry;
        cache.set(key, entry);
      }
    },
    clear() {
      cache.clear();
      oldest = void 0;
      newest = void 0;
    }
  };
}

//# sourceMappingURL=lru-cache.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/new-process-route-tree.js":
/*!*******************************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/new-process-route-tree.js ***!
  \*******************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SEGMENT_TYPE_OPTIONAL_PARAM: () => (/* binding */ SEGMENT_TYPE_OPTIONAL_PARAM),
/* harmony export */   SEGMENT_TYPE_PARAM: () => (/* binding */ SEGMENT_TYPE_PARAM),
/* harmony export */   SEGMENT_TYPE_PATHNAME: () => (/* binding */ SEGMENT_TYPE_PATHNAME),
/* harmony export */   SEGMENT_TYPE_WILDCARD: () => (/* binding */ SEGMENT_TYPE_WILDCARD),
/* harmony export */   findFlatMatch: () => (/* binding */ findFlatMatch),
/* harmony export */   findRouteMatch: () => (/* binding */ findRouteMatch),
/* harmony export */   findSingleMatch: () => (/* binding */ findSingleMatch),
/* harmony export */   parseSegment: () => (/* binding */ parseSegment),
/* harmony export */   processRouteMasks: () => (/* binding */ processRouteMasks),
/* harmony export */   processRouteTree: () => (/* binding */ processRouteTree),
/* harmony export */   trimPathRight: () => (/* binding */ trimPathRight)
/* harmony export */ });
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _lru_cache_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./lru-cache.js */ "./node_modules/@tanstack/router-core/dist/esm/lru-cache.js");
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");



const SEGMENT_TYPE_PATHNAME = 0;
const SEGMENT_TYPE_PARAM = 1;
const SEGMENT_TYPE_WILDCARD = 2;
const SEGMENT_TYPE_OPTIONAL_PARAM = 3;
const PARAM_W_CURLY_BRACES_RE = /^([^{]*)\{\$([a-zA-Z_$][a-zA-Z0-9_$]*)\}([^}]*)$/;
const OPTIONAL_PARAM_W_CURLY_BRACES_RE = /^([^{]*)\{-\$([a-zA-Z_$][a-zA-Z0-9_$]*)\}([^}]*)$/;
const WILDCARD_W_CURLY_BRACES_RE = /^([^{]*)\{\$\}([^}]*)$/;
function parseSegment(path, start, output = new Uint16Array(6)) {
  const next = path.indexOf("/", start);
  const end = next === -1 ? path.length : next;
  const part = path.substring(start, end);
  if (!part || !part.includes("$")) {
    output[0] = SEGMENT_TYPE_PATHNAME;
    output[1] = start;
    output[2] = start;
    output[3] = end;
    output[4] = end;
    output[5] = end;
    return output;
  }
  if (part === "$") {
    const total = path.length;
    output[0] = SEGMENT_TYPE_WILDCARD;
    output[1] = start;
    output[2] = start;
    output[3] = total;
    output[4] = total;
    output[5] = total;
    return output;
  }
  if (part.charCodeAt(0) === 36) {
    output[0] = SEGMENT_TYPE_PARAM;
    output[1] = start;
    output[2] = start + 1;
    output[3] = end;
    output[4] = end;
    output[5] = end;
    return output;
  }
  const wildcardBracesMatch = part.match(WILDCARD_W_CURLY_BRACES_RE);
  if (wildcardBracesMatch) {
    const prefix = wildcardBracesMatch[1];
    const pLength = prefix.length;
    output[0] = SEGMENT_TYPE_WILDCARD;
    output[1] = start + pLength;
    output[2] = start + pLength + 1;
    output[3] = start + pLength + 2;
    output[4] = start + pLength + 3;
    output[5] = path.length;
    return output;
  }
  const optionalParamBracesMatch = part.match(OPTIONAL_PARAM_W_CURLY_BRACES_RE);
  if (optionalParamBracesMatch) {
    const prefix = optionalParamBracesMatch[1];
    const paramName = optionalParamBracesMatch[2];
    const suffix = optionalParamBracesMatch[3];
    const pLength = prefix.length;
    output[0] = SEGMENT_TYPE_OPTIONAL_PARAM;
    output[1] = start + pLength;
    output[2] = start + pLength + 3;
    output[3] = start + pLength + 3 + paramName.length;
    output[4] = end - suffix.length;
    output[5] = end;
    return output;
  }
  const paramBracesMatch = part.match(PARAM_W_CURLY_BRACES_RE);
  if (paramBracesMatch) {
    const prefix = paramBracesMatch[1];
    const paramName = paramBracesMatch[2];
    const suffix = paramBracesMatch[3];
    const pLength = prefix.length;
    output[0] = SEGMENT_TYPE_PARAM;
    output[1] = start + pLength;
    output[2] = start + pLength + 2;
    output[3] = start + pLength + 2 + paramName.length;
    output[4] = end - suffix.length;
    output[5] = end;
    return output;
  }
  output[0] = SEGMENT_TYPE_PATHNAME;
  output[1] = start;
  output[2] = start;
  output[3] = end;
  output[4] = end;
  output[5] = end;
  return output;
}
function parseSegments(defaultCaseSensitive, data, route, start, node, depth, onRoute) {
  onRoute?.(route);
  let cursor = start;
  {
    const path = route.fullPath ?? route.from;
    const length = path.length;
    const caseSensitive = route.options?.caseSensitive ?? defaultCaseSensitive;
    while (cursor < length) {
      const segment = parseSegment(path, cursor, data);
      let nextNode;
      const start2 = cursor;
      const end = segment[5];
      cursor = end + 1;
      depth++;
      const kind = segment[0];
      switch (kind) {
        case SEGMENT_TYPE_PATHNAME: {
          const value = path.substring(segment[2], segment[3]);
          if (caseSensitive) {
            const existingNode = node.static?.get(value);
            if (existingNode) {
              nextNode = existingNode;
            } else {
              node.static ??= /* @__PURE__ */ new Map();
              const next = createStaticNode(
                route.fullPath ?? route.from
              );
              next.parent = node;
              next.depth = depth;
              nextNode = next;
              node.static.set(value, next);
            }
          } else {
            const name = value.toLowerCase();
            const existingNode = node.staticInsensitive?.get(name);
            if (existingNode) {
              nextNode = existingNode;
            } else {
              node.staticInsensitive ??= /* @__PURE__ */ new Map();
              const next = createStaticNode(
                route.fullPath ?? route.from
              );
              next.parent = node;
              next.depth = depth;
              nextNode = next;
              node.staticInsensitive.set(name, next);
            }
          }
          break;
        }
        case SEGMENT_TYPE_PARAM: {
          const prefix_raw = path.substring(start2, segment[1]);
          const suffix_raw = path.substring(segment[4], end);
          const actuallyCaseSensitive = caseSensitive && !!(prefix_raw || suffix_raw);
          const prefix = !prefix_raw ? void 0 : actuallyCaseSensitive ? prefix_raw : prefix_raw.toLowerCase();
          const suffix = !suffix_raw ? void 0 : actuallyCaseSensitive ? suffix_raw : suffix_raw.toLowerCase();
          const existingNode = node.dynamic?.find(
            (s) => s.caseSensitive === actuallyCaseSensitive && s.prefix === prefix && s.suffix === suffix
          );
          if (existingNode) {
            nextNode = existingNode;
          } else {
            const next = createDynamicNode(
              SEGMENT_TYPE_PARAM,
              route.fullPath ?? route.from,
              actuallyCaseSensitive,
              prefix,
              suffix
            );
            nextNode = next;
            next.depth = depth;
            next.parent = node;
            node.dynamic ??= [];
            node.dynamic.push(next);
          }
          break;
        }
        case SEGMENT_TYPE_OPTIONAL_PARAM: {
          const prefix_raw = path.substring(start2, segment[1]);
          const suffix_raw = path.substring(segment[4], end);
          const actuallyCaseSensitive = caseSensitive && !!(prefix_raw || suffix_raw);
          const prefix = !prefix_raw ? void 0 : actuallyCaseSensitive ? prefix_raw : prefix_raw.toLowerCase();
          const suffix = !suffix_raw ? void 0 : actuallyCaseSensitive ? suffix_raw : suffix_raw.toLowerCase();
          const existingNode = node.optional?.find(
            (s) => s.caseSensitive === actuallyCaseSensitive && s.prefix === prefix && s.suffix === suffix
          );
          if (existingNode) {
            nextNode = existingNode;
          } else {
            const next = createDynamicNode(
              SEGMENT_TYPE_OPTIONAL_PARAM,
              route.fullPath ?? route.from,
              actuallyCaseSensitive,
              prefix,
              suffix
            );
            nextNode = next;
            next.parent = node;
            next.depth = depth;
            node.optional ??= [];
            node.optional.push(next);
          }
          break;
        }
        case SEGMENT_TYPE_WILDCARD: {
          const prefix_raw = path.substring(start2, segment[1]);
          const suffix_raw = path.substring(segment[4], end);
          const actuallyCaseSensitive = caseSensitive && !!(prefix_raw || suffix_raw);
          const prefix = !prefix_raw ? void 0 : actuallyCaseSensitive ? prefix_raw : prefix_raw.toLowerCase();
          const suffix = !suffix_raw ? void 0 : actuallyCaseSensitive ? suffix_raw : suffix_raw.toLowerCase();
          const next = createDynamicNode(
            SEGMENT_TYPE_WILDCARD,
            route.fullPath ?? route.from,
            actuallyCaseSensitive,
            prefix,
            suffix
          );
          nextNode = next;
          next.parent = node;
          next.depth = depth;
          node.wildcard ??= [];
          node.wildcard.push(next);
        }
      }
      node = nextNode;
    }
    if ((route.path || !route.children) && !route.isRoot) {
      const isIndex = path.endsWith("/");
      if (!isIndex) node.notFound = route;
      if (!node.route || !node.isIndex && isIndex) {
        node.route = route;
        node.fullPath = route.fullPath ?? route.from;
      }
      node.isIndex ||= isIndex;
    }
  }
  if (route.children)
    for (const child of route.children) {
      parseSegments(
        defaultCaseSensitive,
        data,
        child,
        cursor,
        node,
        depth,
        onRoute
      );
    }
}
function sortDynamic(a, b) {
  if (a.prefix && b.prefix && a.prefix !== b.prefix) {
    if (a.prefix.startsWith(b.prefix)) return -1;
    if (b.prefix.startsWith(a.prefix)) return 1;
  }
  if (a.suffix && b.suffix && a.suffix !== b.suffix) {
    if (a.suffix.endsWith(b.suffix)) return -1;
    if (b.suffix.endsWith(a.suffix)) return 1;
  }
  if (a.prefix && !b.prefix) return -1;
  if (!a.prefix && b.prefix) return 1;
  if (a.suffix && !b.suffix) return -1;
  if (!a.suffix && b.suffix) return 1;
  if (a.caseSensitive && !b.caseSensitive) return -1;
  if (!a.caseSensitive && b.caseSensitive) return 1;
  return 0;
}
function sortTreeNodes(node) {
  if (node.static) {
    for (const child of node.static.values()) {
      sortTreeNodes(child);
    }
  }
  if (node.staticInsensitive) {
    for (const child of node.staticInsensitive.values()) {
      sortTreeNodes(child);
    }
  }
  if (node.dynamic?.length) {
    node.dynamic.sort(sortDynamic);
    for (const child of node.dynamic) {
      sortTreeNodes(child);
    }
  }
  if (node.optional?.length) {
    node.optional.sort(sortDynamic);
    for (const child of node.optional) {
      sortTreeNodes(child);
    }
  }
  if (node.wildcard?.length) {
    node.wildcard.sort(sortDynamic);
    for (const child of node.wildcard) {
      sortTreeNodes(child);
    }
  }
}
function createStaticNode(fullPath) {
  return {
    kind: SEGMENT_TYPE_PATHNAME,
    depth: 0,
    static: null,
    staticInsensitive: null,
    dynamic: null,
    optional: null,
    wildcard: null,
    route: null,
    fullPath,
    parent: null,
    isIndex: false,
    notFound: null
  };
}
function createDynamicNode(kind, fullPath, caseSensitive, prefix, suffix) {
  return {
    kind,
    depth: 0,
    static: null,
    staticInsensitive: null,
    dynamic: null,
    optional: null,
    wildcard: null,
    route: null,
    fullPath,
    parent: null,
    isIndex: false,
    notFound: null,
    caseSensitive,
    prefix,
    suffix
  };
}
function processRouteMasks(routeList, processedTree) {
  const segmentTree = createStaticNode("/");
  const data = new Uint16Array(6);
  for (const route of routeList) {
    parseSegments(false, data, route, 1, segmentTree, 0);
  }
  sortTreeNodes(segmentTree);
  processedTree.masksTree = segmentTree;
  processedTree.flatCache = (0,_lru_cache_js__WEBPACK_IMPORTED_MODULE_1__.createLRUCache)(1e3);
}
function findFlatMatch(path, processedTree) {
  path ||= "/";
  const cached = processedTree.flatCache.get(path);
  if (cached) return cached;
  const result = findMatch(path, processedTree.masksTree);
  processedTree.flatCache.set(path, result);
  return result;
}
function findSingleMatch(from, caseSensitive, fuzzy, path, processedTree) {
  from ||= "/";
  path ||= "/";
  const key = caseSensitive ? `case\0${from}` : from;
  let tree = processedTree.singleCache.get(key);
  if (!tree) {
    tree = createStaticNode("/");
    const data = new Uint16Array(6);
    parseSegments(caseSensitive, data, { from }, 1, tree, 0);
    processedTree.singleCache.set(key, tree);
  }
  return findMatch(path, tree, fuzzy);
}
function findRouteMatch(path, processedTree, fuzzy = false) {
  const key = fuzzy ? path : `nofuzz\0${path}`;
  const cached = processedTree.matchCache.get(key);
  if (cached !== void 0) return cached;
  path ||= "/";
  const result = findMatch(
    path,
    processedTree.segmentTree,
    fuzzy
  );
  if (result) result.branch = buildRouteBranch(result.route);
  processedTree.matchCache.set(key, result);
  return result;
}
function trimPathRight(path) {
  return path === "/" ? path : path.replace(/\/{1,}$/, "");
}
function processRouteTree(routeTree, caseSensitive = false, initRoute) {
  const segmentTree = createStaticNode(routeTree.fullPath);
  const data = new Uint16Array(6);
  const routesById = {};
  const routesByPath = {};
  let index = 0;
  parseSegments(caseSensitive, data, routeTree, 1, segmentTree, 0, (route) => {
    initRoute?.(route, index);
    (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_0__["default"])(
      !(route.id in routesById),
      `Duplicate routes found with id: ${String(route.id)}`
    );
    routesById[route.id] = route;
    if (index !== 0 && route.path) {
      const trimmedFullPath = trimPathRight(route.fullPath);
      if (!routesByPath[trimmedFullPath] || route.fullPath.endsWith("/")) {
        routesByPath[trimmedFullPath] = route;
      }
    }
    index++;
  });
  sortTreeNodes(segmentTree);
  const processedTree = {
    segmentTree,
    singleCache: (0,_lru_cache_js__WEBPACK_IMPORTED_MODULE_1__.createLRUCache)(1e3),
    matchCache: (0,_lru_cache_js__WEBPACK_IMPORTED_MODULE_1__.createLRUCache)(1e3),
    flatCache: null,
    masksTree: null
  };
  return {
    processedTree,
    routesById,
    routesByPath
  };
}
function findMatch(path, segmentTree, fuzzy = false) {
  const parts = path.split("/");
  const leaf = getNodeMatch(path, parts, segmentTree, fuzzy);
  if (!leaf) return null;
  const params = extractParams(path, parts, leaf);
  const isFuzzyMatch = "**" in leaf;
  if (isFuzzyMatch) params["**"] = leaf["**"];
  const route = isFuzzyMatch ? leaf.node.notFound ?? leaf.node.route : leaf.node.route;
  return {
    route,
    params
  };
}
function extractParams(path, parts, leaf) {
  const list = buildBranch(leaf.node);
  let nodeParts = null;
  const params = {};
  for (let partIndex = 0, nodeIndex = 0, pathIndex = 0; nodeIndex < list.length; partIndex++, nodeIndex++, pathIndex++) {
    const node = list[nodeIndex];
    const part = parts[partIndex];
    const currentPathIndex = pathIndex;
    if (part) pathIndex += part.length;
    if (node.kind === SEGMENT_TYPE_PARAM) {
      nodeParts ??= leaf.node.fullPath.split("/");
      const nodePart = nodeParts[nodeIndex];
      const preLength = node.prefix?.length ?? 0;
      const isCurlyBraced = nodePart.charCodeAt(preLength) === 123;
      if (isCurlyBraced) {
        const sufLength = node.suffix?.length ?? 0;
        const name = nodePart.substring(
          preLength + 2,
          nodePart.length - sufLength - 1
        );
        const value = part.substring(preLength, part.length - sufLength);
        params[name] = decodeURIComponent(value);
      } else {
        const name = nodePart.substring(1);
        params[name] = decodeURIComponent(part);
      }
    } else if (node.kind === SEGMENT_TYPE_OPTIONAL_PARAM) {
      if (leaf.skipped & 1 << nodeIndex) {
        partIndex--;
        continue;
      }
      nodeParts ??= leaf.node.fullPath.split("/");
      const nodePart = nodeParts[nodeIndex];
      const preLength = node.prefix?.length ?? 0;
      const sufLength = node.suffix?.length ?? 0;
      const name = nodePart.substring(
        preLength + 3,
        nodePart.length - sufLength - 1
      );
      const value = node.suffix || node.prefix ? part.substring(preLength, part.length - sufLength) : part;
      if (value) params[name] = decodeURIComponent(value);
    } else if (node.kind === SEGMENT_TYPE_WILDCARD) {
      const n = node;
      const value = path.substring(
        currentPathIndex + (n.prefix?.length ?? 0),
        path.length - (n.suffix?.length ?? 0)
      );
      const splat = decodeURIComponent(value);
      params["*"] = splat;
      params._splat = splat;
      break;
    }
  }
  return params;
}
function buildRouteBranch(route) {
  const list = [route];
  while (route.parentRoute) {
    route = route.parentRoute;
    list.push(route);
  }
  list.reverse();
  return list;
}
function buildBranch(node) {
  const list = Array(node.depth + 1);
  do {
    list[node.depth] = node;
    node = node.parent;
  } while (node);
  return list;
}
function getNodeMatch(path, parts, segmentTree, fuzzy) {
  const trailingSlash = !(0,_utils_js__WEBPACK_IMPORTED_MODULE_2__.last)(parts);
  const pathIsIndex = trailingSlash && path !== "/";
  const partsLength = parts.length - (trailingSlash ? 1 : 0);
  const stack = [
    {
      node: segmentTree,
      index: 1,
      skipped: 0,
      depth: 1,
      statics: 1,
      dynamics: 0,
      optionals: 0
    }
  ];
  let wildcardMatch = null;
  let bestFuzzy = null;
  let bestMatch = null;
  while (stack.length) {
    const frame = stack.pop();
    let { node, index, skipped, depth, statics, dynamics, optionals } = frame;
    if (fuzzy && node.notFound && isFrameMoreSpecific(bestFuzzy, frame)) {
      bestFuzzy = frame;
    }
    const isBeyondPath = index === partsLength;
    if (isBeyondPath) {
      if (node.route && (!pathIsIndex || node.isIndex)) {
        if (isFrameMoreSpecific(bestMatch, frame)) {
          bestMatch = frame;
        }
        if (statics === partsLength && node.isIndex) return bestMatch;
      }
      if (!node.optional && !node.wildcard) continue;
    }
    const part = isBeyondPath ? void 0 : parts[index];
    let lowerPart;
    if (node.wildcard && isFrameMoreSpecific(wildcardMatch, frame)) {
      for (const segment of node.wildcard) {
        const { prefix, suffix } = segment;
        if (prefix) {
          if (isBeyondPath) continue;
          const casePart = segment.caseSensitive ? part : lowerPart ??= part.toLowerCase();
          if (!casePart.startsWith(prefix)) continue;
        }
        if (suffix) {
          if (isBeyondPath) continue;
          const end = parts.slice(index).join("/").slice(-suffix.length);
          const casePart = segment.caseSensitive ? end : end.toLowerCase();
          if (casePart !== suffix) continue;
        }
        wildcardMatch = {
          node: segment,
          index,
          skipped,
          depth,
          statics,
          dynamics,
          optionals
        };
        break;
      }
    }
    if (node.optional) {
      const nextSkipped = skipped | 1 << depth;
      const nextDepth = depth + 1;
      for (let i = node.optional.length - 1; i >= 0; i--) {
        const segment = node.optional[i];
        stack.push({
          node: segment,
          index,
          skipped: nextSkipped,
          depth: nextDepth,
          statics,
          dynamics,
          optionals
        });
      }
      if (!isBeyondPath) {
        for (let i = node.optional.length - 1; i >= 0; i--) {
          const segment = node.optional[i];
          const { prefix, suffix } = segment;
          if (prefix || suffix) {
            const casePart = segment.caseSensitive ? part : lowerPart ??= part.toLowerCase();
            if (prefix && !casePart.startsWith(prefix)) continue;
            if (suffix && !casePart.endsWith(suffix)) continue;
          }
          stack.push({
            node: segment,
            index: index + 1,
            skipped,
            depth: nextDepth,
            statics,
            dynamics,
            optionals: optionals + 1
          });
        }
      }
    }
    if (!isBeyondPath && node.dynamic && part) {
      for (let i = node.dynamic.length - 1; i >= 0; i--) {
        const segment = node.dynamic[i];
        const { prefix, suffix } = segment;
        if (prefix || suffix) {
          const casePart = segment.caseSensitive ? part : lowerPart ??= part.toLowerCase();
          if (prefix && !casePart.startsWith(prefix)) continue;
          if (suffix && !casePart.endsWith(suffix)) continue;
        }
        stack.push({
          node: segment,
          index: index + 1,
          skipped,
          depth: depth + 1,
          statics,
          dynamics: dynamics + 1,
          optionals
        });
      }
    }
    if (!isBeyondPath && node.staticInsensitive) {
      const match = node.staticInsensitive.get(
        lowerPart ??= part.toLowerCase()
      );
      if (match) {
        stack.push({
          node: match,
          index: index + 1,
          skipped,
          depth: depth + 1,
          statics: statics + 1,
          dynamics,
          optionals
        });
      }
    }
    if (!isBeyondPath && node.static) {
      const match = node.static.get(part);
      if (match) {
        stack.push({
          node: match,
          index: index + 1,
          skipped,
          depth: depth + 1,
          statics: statics + 1,
          dynamics,
          optionals
        });
      }
    }
  }
  if (bestMatch && wildcardMatch) {
    return isFrameMoreSpecific(wildcardMatch, bestMatch) ? bestMatch : wildcardMatch;
  }
  if (bestMatch) return bestMatch;
  if (wildcardMatch) return wildcardMatch;
  if (fuzzy && bestFuzzy) {
    let sliceIndex = bestFuzzy.index;
    for (let i = 0; i < bestFuzzy.index; i++) {
      sliceIndex += parts[i].length;
    }
    const splat = sliceIndex === path.length ? "/" : path.slice(sliceIndex);
    return {
      node: bestFuzzy.node,
      skipped: bestFuzzy.skipped,
      "**": decodeURIComponent(splat)
    };
  }
  return null;
}
function isFrameMoreSpecific(prev, next) {
  if (!prev) return true;
  return next.statics > prev.statics || next.statics === prev.statics && (next.dynamics > prev.dynamics || next.dynamics === prev.dynamics && (next.optionals > prev.optionals || next.optionals === prev.optionals && (next.node.isIndex > prev.node.isIndex || next.node.isIndex === prev.node.isIndex && next.depth > prev.depth)));
}

//# sourceMappingURL=new-process-route-tree.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/not-found.js":
/*!******************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/not-found.js ***!
  \******************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isNotFound: () => (/* binding */ isNotFound),
/* harmony export */   notFound: () => (/* binding */ notFound)
/* harmony export */ });
function notFound(options = {}) {
  options.isNotFound = true;
  if (options.throw) throw options;
  return options;
}
function isNotFound(obj) {
  return !!obj?.isNotFound;
}

//# sourceMappingURL=not-found.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/path.js":
/*!*************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/path.js ***!
  \*************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   cleanPath: () => (/* binding */ cleanPath),
/* harmony export */   exactPathTest: () => (/* binding */ exactPathTest),
/* harmony export */   interpolatePath: () => (/* binding */ interpolatePath),
/* harmony export */   joinPaths: () => (/* binding */ joinPaths),
/* harmony export */   removeTrailingSlash: () => (/* binding */ removeTrailingSlash),
/* harmony export */   resolvePath: () => (/* binding */ resolvePath),
/* harmony export */   trimPath: () => (/* binding */ trimPath),
/* harmony export */   trimPathLeft: () => (/* binding */ trimPathLeft),
/* harmony export */   trimPathRight: () => (/* binding */ trimPathRight)
/* harmony export */ });
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");
/* harmony import */ var _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./new-process-route-tree.js */ "./node_modules/@tanstack/router-core/dist/esm/new-process-route-tree.js");


function joinPaths(paths) {
  return cleanPath(
    paths.filter((val) => {
      return val !== void 0;
    }).join("/")
  );
}
function cleanPath(path) {
  return path.replace(/\/{2,}/g, "/");
}
function trimPathLeft(path) {
  return path === "/" ? path : path.replace(/^\/{1,}/, "");
}
function trimPathRight(path) {
  const len = path.length;
  return len > 1 && path[len - 1] === "/" ? path.replace(/\/{1,}$/, "") : path;
}
function trimPath(path) {
  return trimPathRight(trimPathLeft(path));
}
function removeTrailingSlash(value, basepath) {
  if (value?.endsWith("/") && value !== "/" && value !== `${basepath}/`) {
    return value.slice(0, -1);
  }
  return value;
}
function exactPathTest(pathName1, pathName2, basepath) {
  return removeTrailingSlash(pathName1, basepath) === removeTrailingSlash(pathName2, basepath);
}
function resolvePath({
  base,
  to,
  trailingSlash = "never",
  cache
}) {
  const isAbsolute = to.startsWith("/");
  const isBase = !isAbsolute && to === ".";
  let key;
  if (cache) {
    key = isAbsolute ? to : isBase ? base : base + "\0" + to;
    const cached = cache.get(key);
    if (cached) return cached;
  }
  let baseSegments;
  if (isBase) {
    baseSegments = base.split("/");
  } else if (isAbsolute) {
    baseSegments = to.split("/");
  } else {
    baseSegments = base.split("/");
    while (baseSegments.length > 1 && (0,_utils_js__WEBPACK_IMPORTED_MODULE_0__.last)(baseSegments) === "") {
      baseSegments.pop();
    }
    const toSegments = to.split("/");
    for (let index = 0, length = toSegments.length; index < length; index++) {
      const value = toSegments[index];
      if (value === "") {
        if (!index) {
          baseSegments = [value];
        } else if (index === length - 1) {
          baseSegments.push(value);
        } else ;
      } else if (value === "..") {
        baseSegments.pop();
      } else if (value === ".") ;
      else {
        baseSegments.push(value);
      }
    }
  }
  if (baseSegments.length > 1) {
    if ((0,_utils_js__WEBPACK_IMPORTED_MODULE_0__.last)(baseSegments) === "") {
      if (trailingSlash === "never") {
        baseSegments.pop();
      }
    } else if (trailingSlash === "always") {
      baseSegments.push("");
    }
  }
  let segment;
  let joined = "";
  for (let i = 0; i < baseSegments.length; i++) {
    if (i > 0) joined += "/";
    const part = baseSegments[i];
    if (!part) continue;
    segment = (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.parseSegment)(part, 0, segment);
    const kind = segment[0];
    if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_PATHNAME) {
      joined += part;
      continue;
    }
    const end = segment[5];
    const prefix = part.substring(0, segment[1]);
    const suffix = part.substring(segment[4], end);
    const value = part.substring(segment[2], segment[3]);
    if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_PARAM) {
      joined += prefix || suffix ? `${prefix}{$${value}}${suffix}` : `$${value}`;
    } else if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_WILDCARD) {
      joined += prefix || suffix ? `${prefix}{$}${suffix}` : "$";
    } else {
      joined += `${prefix}{-$${value}}${suffix}`;
    }
  }
  joined = cleanPath(joined);
  const result = joined || "/";
  if (key && cache) cache.set(key, result);
  return result;
}
function encodeParam(key, params, decodeCharMap) {
  const value = params[key];
  if (typeof value !== "string") return value;
  if (key === "_splat") {
    return encodeURI(value);
  } else {
    return encodePathParam(value, decodeCharMap);
  }
}
function interpolatePath({
  path,
  params,
  decodeCharMap
}) {
  let isMissingParams = false;
  const usedParams = {};
  if (!path || path === "/")
    return { interpolatedPath: "/", usedParams, isMissingParams };
  if (!path.includes("$"))
    return { interpolatedPath: path, usedParams, isMissingParams };
  const length = path.length;
  let cursor = 0;
  let segment;
  let joined = "";
  while (cursor < length) {
    const start = cursor;
    segment = (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.parseSegment)(path, start, segment);
    const end = segment[5];
    cursor = end + 1;
    if (start === end) continue;
    const kind = segment[0];
    if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_PATHNAME) {
      joined += "/" + path.substring(start, end);
      continue;
    }
    if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_WILDCARD) {
      const splat = params._splat;
      usedParams._splat = splat;
      usedParams["*"] = splat;
      const prefix = path.substring(start, segment[1]);
      const suffix = path.substring(segment[4], end);
      if (!splat) {
        isMissingParams = true;
        if (prefix || suffix) {
          joined += "/" + prefix + suffix;
        }
        continue;
      }
      const value = encodeParam("_splat", params, decodeCharMap);
      joined += "/" + prefix + value + suffix;
      continue;
    }
    if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_PARAM) {
      const key = path.substring(segment[2], segment[3]);
      if (!isMissingParams && !(key in params)) {
        isMissingParams = true;
      }
      usedParams[key] = params[key];
      const prefix = path.substring(start, segment[1]);
      const suffix = path.substring(segment[4], end);
      const value = encodeParam(key, params, decodeCharMap) ?? "undefined";
      joined += "/" + prefix + value + suffix;
      continue;
    }
    if (kind === _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_1__.SEGMENT_TYPE_OPTIONAL_PARAM) {
      const key = path.substring(segment[2], segment[3]);
      const valueRaw = params[key];
      if (valueRaw == null) continue;
      usedParams[key] = valueRaw;
      const prefix = path.substring(start, segment[1]);
      const suffix = path.substring(segment[4], end);
      const value = encodeParam(key, params, decodeCharMap) ?? "";
      joined += "/" + prefix + value + suffix;
      continue;
    }
  }
  if (path.endsWith("/")) joined += "/";
  const interpolatedPath = joined || "/";
  return { usedParams, interpolatedPath, isMissingParams };
}
function encodePathParam(value, decodeCharMap) {
  let encoded = encodeURIComponent(value);
  if (decodeCharMap) {
    for (const [encodedChar, char] of decodeCharMap) {
      encoded = encoded.replaceAll(encodedChar, char);
    }
  }
  return encoded;
}

//# sourceMappingURL=path.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/qss.js":
/*!************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/qss.js ***!
  \************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   decode: () => (/* binding */ decode),
/* harmony export */   encode: () => (/* binding */ encode)
/* harmony export */ });
function encode(obj, stringify = String) {
  const result = new URLSearchParams();
  for (const key in obj) {
    const val = obj[key];
    if (val !== void 0) {
      result.set(key, stringify(val));
    }
  }
  return result.toString();
}
function toValue(str) {
  if (!str) return "";
  if (str === "false") return false;
  if (str === "true") return true;
  return +str * 0 === 0 && +str + "" === str ? +str : str;
}
function decode(str) {
  const searchParams = new URLSearchParams(str);
  const result = {};
  for (const [key, value] of searchParams.entries()) {
    const previousValue = result[key];
    if (previousValue == null) {
      result[key] = toValue(value);
    } else if (Array.isArray(previousValue)) {
      previousValue.push(toValue(value));
    } else {
      result[key] = [previousValue, toValue(value)];
    }
  }
  return result;
}

//# sourceMappingURL=qss.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/redirect.js":
/*!*****************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/redirect.js ***!
  \*****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isRedirect: () => (/* binding */ isRedirect),
/* harmony export */   isResolvedRedirect: () => (/* binding */ isResolvedRedirect),
/* harmony export */   parseRedirect: () => (/* binding */ parseRedirect),
/* harmony export */   redirect: () => (/* binding */ redirect)
/* harmony export */ });
function redirect(opts) {
  opts.statusCode = opts.statusCode || opts.code || 307;
  if (!opts.reloadDocument && typeof opts.href === "string") {
    try {
      new URL(opts.href);
      opts.reloadDocument = true;
    } catch {
    }
  }
  const headers = new Headers(opts.headers);
  if (opts.href && headers.get("Location") === null) {
    headers.set("Location", opts.href);
  }
  const response = new Response(null, {
    status: opts.statusCode,
    headers
  });
  response.options = opts;
  if (opts.throw) {
    throw response;
  }
  return response;
}
function isRedirect(obj) {
  return obj instanceof Response && !!obj.options;
}
function isResolvedRedirect(obj) {
  return isRedirect(obj) && !!obj.options.href;
}
function parseRedirect(obj) {
  if (obj !== null && typeof obj === "object" && obj.isSerializedRedirect) {
    return redirect(obj);
  }
  return void 0;
}

//# sourceMappingURL=redirect.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/rewrite.js":
/*!****************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/rewrite.js ***!
  \****************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   composeRewrites: () => (/* binding */ composeRewrites),
/* harmony export */   executeRewriteInput: () => (/* binding */ executeRewriteInput),
/* harmony export */   executeRewriteOutput: () => (/* binding */ executeRewriteOutput),
/* harmony export */   rewriteBasepath: () => (/* binding */ rewriteBasepath)
/* harmony export */ });
/* harmony import */ var _path_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./path.js */ "./node_modules/@tanstack/router-core/dist/esm/path.js");

function composeRewrites(rewrites) {
  return {
    input: ({ url }) => {
      for (const rewrite of rewrites) {
        url = executeRewriteInput(rewrite, url);
      }
      return url;
    },
    output: ({ url }) => {
      for (let i = rewrites.length - 1; i >= 0; i--) {
        url = executeRewriteOutput(rewrites[i], url);
      }
      return url;
    }
  };
}
function rewriteBasepath(opts) {
  const trimmedBasepath = (0,_path_js__WEBPACK_IMPORTED_MODULE_0__.trimPath)(opts.basepath);
  const normalizedBasepath = `/${trimmedBasepath}`;
  const normalizedBasepathWithSlash = `${normalizedBasepath}/`;
  const checkBasepath = opts.caseSensitive ? normalizedBasepath : normalizedBasepath.toLowerCase();
  const checkBasepathWithSlash = opts.caseSensitive ? normalizedBasepathWithSlash : normalizedBasepathWithSlash.toLowerCase();
  return {
    input: ({ url }) => {
      const pathname = opts.caseSensitive ? url.pathname : url.pathname.toLowerCase();
      if (pathname === checkBasepath) {
        url.pathname = "/";
      } else if (pathname.startsWith(checkBasepathWithSlash)) {
        url.pathname = url.pathname.slice(normalizedBasepath.length);
      }
      return url;
    },
    output: ({ url }) => {
      url.pathname = (0,_path_js__WEBPACK_IMPORTED_MODULE_0__.joinPaths)(["/", trimmedBasepath, url.pathname]);
      return url;
    }
  };
}
function executeRewriteInput(rewrite, url) {
  const res = rewrite?.input?.({ url });
  if (res) {
    if (typeof res === "string") {
      return new URL(res);
    } else if (res instanceof URL) {
      return res;
    }
  }
  return url;
}
function executeRewriteOutput(rewrite, url) {
  const res = rewrite?.output?.({ url });
  if (res) {
    if (typeof res === "string") {
      return new URL(res);
    } else if (res instanceof URL) {
      return res;
    }
  }
  return url;
}

//# sourceMappingURL=rewrite.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/root.js":
/*!*************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/root.js ***!
  \*************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   rootRouteId: () => (/* binding */ rootRouteId)
/* harmony export */ });
const rootRouteId = "__root__";

//# sourceMappingURL=root.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/route.js":
/*!**************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/route.js ***!
  \**************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   BaseRootRoute: () => (/* binding */ BaseRootRoute),
/* harmony export */   BaseRoute: () => (/* binding */ BaseRoute),
/* harmony export */   BaseRouteApi: () => (/* binding */ BaseRouteApi)
/* harmony export */ });
/* harmony import */ var tiny_invariant__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! tiny-invariant */ "./node_modules/tiny-invariant/dist/esm/tiny-invariant.js");
/* harmony import */ var _path_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./path.js */ "./node_modules/@tanstack/router-core/dist/esm/path.js");
/* harmony import */ var _not_found_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./not-found.js */ "./node_modules/@tanstack/router-core/dist/esm/not-found.js");
/* harmony import */ var _root_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./root.js */ "./node_modules/@tanstack/router-core/dist/esm/root.js");




class BaseRoute {
  constructor(options) {
    this.init = (opts) => {
      this.originalIndex = opts.originalIndex;
      const options2 = this.options;
      const isRoot = !options2?.path && !options2?.id;
      this.parentRoute = this.options.getParentRoute?.();
      if (isRoot) {
        this._path = _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId;
      } else if (!this.parentRoute) {
        (0,tiny_invariant__WEBPACK_IMPORTED_MODULE_0__["default"])(
          false,
          `Child Route instances must pass a 'getParentRoute: () => ParentRoute' option that returns a Route instance.`
        );
      }
      let path = isRoot ? _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId : options2?.path;
      if (path && path !== "/") {
        path = (0,_path_js__WEBPACK_IMPORTED_MODULE_1__.trimPathLeft)(path);
      }
      const customId = options2?.id || path;
      let id = isRoot ? _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId : (0,_path_js__WEBPACK_IMPORTED_MODULE_1__.joinPaths)([
        this.parentRoute.id === _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId ? "" : this.parentRoute.id,
        customId
      ]);
      if (path === _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId) {
        path = "/";
      }
      if (id !== _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId) {
        id = (0,_path_js__WEBPACK_IMPORTED_MODULE_1__.joinPaths)(["/", id]);
      }
      const fullPath = id === _root_js__WEBPACK_IMPORTED_MODULE_3__.rootRouteId ? "/" : (0,_path_js__WEBPACK_IMPORTED_MODULE_1__.joinPaths)([this.parentRoute.fullPath, path]);
      this._path = path;
      this._id = id;
      this._fullPath = fullPath;
      this._to = fullPath;
    };
    this.addChildren = (children) => {
      return this._addFileChildren(children);
    };
    this._addFileChildren = (children) => {
      if (Array.isArray(children)) {
        this.children = children;
      }
      if (typeof children === "object" && children !== null) {
        this.children = Object.values(children);
      }
      return this;
    };
    this._addFileTypes = () => {
      return this;
    };
    this.updateLoader = (options2) => {
      Object.assign(this.options, options2);
      return this;
    };
    this.update = (options2) => {
      Object.assign(this.options, options2);
      return this;
    };
    this.lazy = (lazyFn) => {
      this.lazyFn = lazyFn;
      return this;
    };
    this.options = options || {};
    this.isRoot = !options?.getParentRoute;
    if (options?.id && options?.path) {
      throw new Error(`Route cannot have both an 'id' and a 'path' option.`);
    }
  }
  get to() {
    return this._to;
  }
  get id() {
    return this._id;
  }
  get path() {
    return this._path;
  }
  get fullPath() {
    return this._fullPath;
  }
}
class BaseRouteApi {
  constructor({ id }) {
    this.notFound = (opts) => {
      return (0,_not_found_js__WEBPACK_IMPORTED_MODULE_2__.notFound)({ routeId: this.id, ...opts });
    };
    this.id = id;
  }
}
class BaseRootRoute extends BaseRoute {
  constructor(options) {
    super(options);
  }
}

//# sourceMappingURL=route.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/router.js":
/*!***************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/router.js ***!
  \***************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   PathParamError: () => (/* binding */ PathParamError),
/* harmony export */   RouterCore: () => (/* binding */ RouterCore),
/* harmony export */   SearchParamError: () => (/* binding */ SearchParamError),
/* harmony export */   defaultSerializeError: () => (/* binding */ defaultSerializeError),
/* harmony export */   getInitialRouterState: () => (/* binding */ getInitialRouterState),
/* harmony export */   getLocationChangeInfo: () => (/* binding */ getLocationChangeInfo),
/* harmony export */   getMatchedRoutes: () => (/* binding */ getMatchedRoutes),
/* harmony export */   lazyFn: () => (/* binding */ lazyFn),
/* harmony export */   trailingSlashOptions: () => (/* binding */ trailingSlashOptions)
/* harmony export */ });
/* harmony import */ var _tanstack_store__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! @tanstack/store */ "./node_modules/@tanstack/store/dist/esm/store.js");
/* harmony import */ var _tanstack_store__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @tanstack/store */ "./node_modules/@tanstack/store/dist/esm/scheduler.js");
/* harmony import */ var _tanstack_history__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @tanstack/history */ "./node_modules/@tanstack/history/dist/esm/index.js");
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");
/* harmony import */ var _new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./new-process-route-tree.js */ "./node_modules/@tanstack/router-core/dist/esm/new-process-route-tree.js");
/* harmony import */ var _path_js__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! ./path.js */ "./node_modules/@tanstack/router-core/dist/esm/path.js");
/* harmony import */ var _lru_cache_js__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./lru-cache.js */ "./node_modules/@tanstack/router-core/dist/esm/lru-cache.js");
/* harmony import */ var _not_found_js__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./not-found.js */ "./node_modules/@tanstack/router-core/dist/esm/not-found.js");
/* harmony import */ var _scroll_restoration_js__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./scroll-restoration.js */ "./node_modules/@tanstack/router-core/dist/esm/scroll-restoration.js");
/* harmony import */ var _searchParams_js__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./searchParams.js */ "./node_modules/@tanstack/router-core/dist/esm/searchParams.js");
/* harmony import */ var _root_js__WEBPACK_IMPORTED_MODULE_10__ = __webpack_require__(/*! ./root.js */ "./node_modules/@tanstack/router-core/dist/esm/root.js");
/* harmony import */ var _redirect_js__WEBPACK_IMPORTED_MODULE_11__ = __webpack_require__(/*! ./redirect.js */ "./node_modules/@tanstack/router-core/dist/esm/redirect.js");
/* harmony import */ var _load_matches_js__WEBPACK_IMPORTED_MODULE_12__ = __webpack_require__(/*! ./load-matches.js */ "./node_modules/@tanstack/router-core/dist/esm/load-matches.js");
/* harmony import */ var _rewrite_js__WEBPACK_IMPORTED_MODULE_13__ = __webpack_require__(/*! ./rewrite.js */ "./node_modules/@tanstack/router-core/dist/esm/rewrite.js");













function defaultSerializeError(err) {
  if (err instanceof Error) {
    const obj = {
      name: err.name,
      message: err.message
    };
    if (true) {
      obj.stack = err.stack;
    }
    return obj;
  }
  return {
    data: err
  };
}
const trailingSlashOptions = {
  always: "always",
  never: "never",
  preserve: "preserve"
};
function getLocationChangeInfo(routerState) {
  const fromLocation = routerState.resolvedLocation;
  const toLocation = routerState.location;
  const pathChanged = fromLocation?.pathname !== toLocation.pathname;
  const hrefChanged = fromLocation?.href !== toLocation.href;
  const hashChanged = fromLocation?.hash !== toLocation.hash;
  return { fromLocation, toLocation, pathChanged, hrefChanged, hashChanged };
}
class RouterCore {
  /**
   * @deprecated Use the `createRouter` function instead
   */
  constructor(options) {
    this.tempLocationKey = `${Math.round(
      Math.random() * 1e7
    )}`;
    this.resetNextScroll = true;
    this.shouldViewTransition = void 0;
    this.isViewTransitionTypesSupported = void 0;
    this.subscribers = /* @__PURE__ */ new Set();
    this.isScrollRestoring = false;
    this.isScrollRestorationSetup = false;
    this.startTransition = (fn) => fn();
    this.update = (newOptions) => {
      if (newOptions.notFoundRoute) {
        console.warn(
          "The notFoundRoute API is deprecated and will be removed in the next major version. See https://tanstack.com/router/v1/docs/framework/react/guide/not-found-errors#migrating-from-notfoundroute for more info."
        );
      }
      const prevOptions = this.options;
      const prevBasepath = this.basepath ?? prevOptions?.basepath ?? "/";
      const basepathWasUnset = this.basepath === void 0;
      const prevRewriteOption = prevOptions?.rewrite;
      this.options = {
        ...prevOptions,
        ...newOptions
      };
      this.isServer = this.options.isServer ?? typeof document === "undefined";
      this.pathParamsDecodeCharMap = this.options.pathParamsAllowedCharacters ? new Map(
        this.options.pathParamsAllowedCharacters.map((char) => [
          encodeURIComponent(char),
          char
        ])
      ) : void 0;
      if (!this.history || this.options.history && this.options.history !== this.history) {
        if (!this.options.history) {
          if (!this.isServer) {
            this.history = (0,_tanstack_history__WEBPACK_IMPORTED_MODULE_2__.createBrowserHistory)();
          }
        } else {
          this.history = this.options.history;
        }
      }
      this.origin = this.options.origin;
      if (!this.origin) {
        if (!this.isServer && window?.origin && window.origin !== "null") {
          this.origin = window.origin;
        } else {
          this.origin = "http://localhost";
        }
      }
      if (this.history) {
        this.updateLatestLocation();
      }
      if (this.options.routeTree !== this.routeTree) {
        this.routeTree = this.options.routeTree;
        this.buildRouteTree();
      }
      if (!this.__store && this.latestLocation) {
        this.__store = new _tanstack_store__WEBPACK_IMPORTED_MODULE_0__.Store(getInitialRouterState(this.latestLocation), {
          onUpdate: () => {
            this.__store.state = {
              ...this.state,
              cachedMatches: this.state.cachedMatches.filter(
                (d) => !["redirected"].includes(d.status)
              )
            };
          }
        });
        (0,_scroll_restoration_js__WEBPACK_IMPORTED_MODULE_8__.setupScrollRestoration)(this);
      }
      let needsLocationUpdate = false;
      const nextBasepath = this.options.basepath ?? "/";
      const nextRewriteOption = this.options.rewrite;
      const basepathChanged = basepathWasUnset || prevBasepath !== nextBasepath;
      const rewriteChanged = prevRewriteOption !== nextRewriteOption;
      if (basepathChanged || rewriteChanged) {
        this.basepath = nextBasepath;
        const rewrites = [];
        if ((0,_path_js__WEBPACK_IMPORTED_MODULE_5__.trimPath)(nextBasepath) !== "") {
          rewrites.push(
            (0,_rewrite_js__WEBPACK_IMPORTED_MODULE_13__.rewriteBasepath)({
              basepath: nextBasepath
            })
          );
        }
        if (nextRewriteOption) {
          rewrites.push(nextRewriteOption);
        }
        this.rewrite = rewrites.length === 0 ? void 0 : rewrites.length === 1 ? rewrites[0] : (0,_rewrite_js__WEBPACK_IMPORTED_MODULE_13__.composeRewrites)(rewrites);
        if (this.history) {
          this.updateLatestLocation();
        }
        needsLocationUpdate = true;
      }
      if (needsLocationUpdate && this.__store) {
        this.__store.state = {
          ...this.state,
          location: this.latestLocation
        };
      }
      if (typeof window !== "undefined" && "CSS" in window && typeof window.CSS?.supports === "function") {
        this.isViewTransitionTypesSupported = window.CSS.supports(
          "selector(:active-view-transition-type(a)"
        );
      }
    };
    this.updateLatestLocation = () => {
      this.latestLocation = this.parseLocation(
        this.history.location,
        this.latestLocation
      );
    };
    this.buildRouteTree = () => {
      const { routesById, routesByPath, processedTree } = (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_4__.processRouteTree)(
        this.routeTree,
        this.options.caseSensitive,
        (route, i) => {
          route.init({
            originalIndex: i
          });
        }
      );
      if (this.options.routeMasks) {
        (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_4__.processRouteMasks)(this.options.routeMasks, processedTree);
      }
      this.routesById = routesById;
      this.routesByPath = routesByPath;
      this.processedTree = processedTree;
      const notFoundRoute = this.options.notFoundRoute;
      if (notFoundRoute) {
        notFoundRoute.init({
          originalIndex: 99999999999
        });
        this.routesById[notFoundRoute.id] = notFoundRoute;
      }
    };
    this.subscribe = (eventType, fn) => {
      const listener = {
        eventType,
        fn
      };
      this.subscribers.add(listener);
      return () => {
        this.subscribers.delete(listener);
      };
    };
    this.emit = (routerEvent) => {
      this.subscribers.forEach((listener) => {
        if (listener.eventType === routerEvent.type) {
          listener.fn(routerEvent);
        }
      });
    };
    this.parseLocation = (locationToParse, previousLocation) => {
      const parse = ({
        href,
        state
      }) => {
        const fullUrl = new URL(href, this.origin);
        const url = (0,_rewrite_js__WEBPACK_IMPORTED_MODULE_13__.executeRewriteInput)(this.rewrite, fullUrl);
        const parsedSearch = this.options.parseSearch(url.search);
        const searchStr = this.options.stringifySearch(parsedSearch);
        url.search = searchStr;
        const fullPath = url.href.replace(url.origin, "");
        return {
          href: fullPath,
          publicHref: href,
          url,
          pathname: (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.decodePath)(url.pathname),
          searchStr,
          search: (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousLocation?.search, parsedSearch),
          hash: url.hash.split("#").reverse()[0] ?? "",
          state: (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousLocation?.state, state)
        };
      };
      const location = parse(locationToParse);
      const { __tempLocation, __tempKey } = location.state;
      if (__tempLocation && (!__tempKey || __tempKey === this.tempLocationKey)) {
        const parsedTempLocation = parse(__tempLocation);
        parsedTempLocation.state.key = location.state.key;
        parsedTempLocation.state.__TSR_key = location.state.__TSR_key;
        delete parsedTempLocation.state.__tempLocation;
        return {
          ...parsedTempLocation,
          maskedLocation: location
        };
      }
      return location;
    };
    this.resolvePathCache = (0,_lru_cache_js__WEBPACK_IMPORTED_MODULE_6__.createLRUCache)(1e3);
    this.resolvePathWithBase = (from, path) => {
      const resolvedPath = (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.resolvePath)({
        base: from,
        to: (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.cleanPath)(path),
        trailingSlash: this.options.trailingSlash,
        cache: this.resolvePathCache
      });
      return resolvedPath;
    };
    this.matchRoutes = (pathnameOrNext, locationSearchOrOpts, opts) => {
      if (typeof pathnameOrNext === "string") {
        return this.matchRoutesInternal(
          {
            pathname: pathnameOrNext,
            search: locationSearchOrOpts
          },
          opts
        );
      }
      return this.matchRoutesInternal(pathnameOrNext, locationSearchOrOpts);
    };
    this.getMatchedRoutes = (pathname) => {
      return getMatchedRoutes({
        pathname,
        routesById: this.routesById,
        processedTree: this.processedTree
      });
    };
    this.cancelMatch = (id) => {
      const match = this.getMatch(id);
      if (!match) return;
      match.abortController.abort();
      clearTimeout(match._nonReactive.pendingTimeout);
      match._nonReactive.pendingTimeout = void 0;
    };
    this.cancelMatches = () => {
      const currentPendingMatches = this.state.matches.filter(
        (match) => match.status === "pending"
      );
      const currentLoadingMatches = this.state.matches.filter(
        (match) => match.isFetching === "loader"
      );
      const matchesToCancelArray = /* @__PURE__ */ new Set([
        ...this.state.pendingMatches ?? [],
        ...currentPendingMatches,
        ...currentLoadingMatches
      ]);
      matchesToCancelArray.forEach((match) => {
        this.cancelMatch(match.id);
      });
    };
    this.buildLocation = (opts) => {
      const build = (dest = {}) => {
        const currentLocation = dest._fromLocation || this.pendingBuiltLocation || this.latestLocation;
        const allCurrentLocationMatches = this.matchRoutes(currentLocation, {
          _buildLocation: true
        });
        const lastMatch = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.last)(allCurrentLocationMatches);
        if (dest.from && "development" !== "production" && dest._isNavigate) {
          const allFromMatches = this.getMatchedRoutes(dest.from).matchedRoutes;
          const matchedFrom = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.findLast)(allCurrentLocationMatches, (d) => {
            return comparePaths(d.fullPath, dest.from);
          });
          const matchedCurrent = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.findLast)(allFromMatches, (d) => {
            return comparePaths(d.fullPath, lastMatch.fullPath);
          });
          if (!matchedFrom && !matchedCurrent) {
            console.warn(`Could not find match for from: ${dest.from}`);
          }
        }
        const defaultedFromPath = dest.unsafeRelative === "path" ? currentLocation.pathname : dest.from ?? lastMatch.fullPath;
        const fromPath = this.resolvePathWithBase(defaultedFromPath, ".");
        const fromSearch = lastMatch.search;
        const fromParams = { ...lastMatch.params };
        const nextTo = dest.to ? this.resolvePathWithBase(fromPath, `${dest.to}`) : this.resolvePathWithBase(fromPath, ".");
        const nextParams = dest.params === false || dest.params === null ? {} : (dest.params ?? true) === true ? fromParams : Object.assign(
          fromParams,
          (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.functionalUpdate)(dest.params, fromParams)
        );
        const interpolatedNextTo = (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.interpolatePath)({
          path: nextTo,
          params: nextParams
        }).interpolatedPath;
        const destRoutes = this.matchRoutes(interpolatedNextTo, void 0, {
          _buildLocation: true
        }).map((d) => this.looseRoutesById[d.routeId]);
        if (Object.keys(nextParams).length > 0) {
          for (const route of destRoutes) {
            const fn = route.options.params?.stringify ?? route.options.stringifyParams;
            if (fn) {
              Object.assign(nextParams, fn(nextParams));
            }
          }
        }
        const nextPathname = opts.leaveParams ? (
          // Use the original template path for interpolation
          // This preserves the original parameter syntax including optional parameters
          nextTo
        ) : (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.decodePath)(
          (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.interpolatePath)({
            path: nextTo,
            params: nextParams,
            decodeCharMap: this.pathParamsDecodeCharMap
          }).interpolatedPath
        );
        let nextSearch = fromSearch;
        if (opts._includeValidateSearch && this.options.search?.strict) {
          const validatedSearch = {};
          destRoutes.forEach((route) => {
            if (route.options.validateSearch) {
              try {
                Object.assign(
                  validatedSearch,
                  validateSearch(route.options.validateSearch, {
                    ...validatedSearch,
                    ...nextSearch
                  })
                );
              } catch {
              }
            }
          });
          nextSearch = validatedSearch;
        }
        nextSearch = applySearchMiddleware({
          search: nextSearch,
          dest,
          destRoutes,
          _includeValidateSearch: opts._includeValidateSearch
        });
        nextSearch = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(fromSearch, nextSearch);
        const searchStr = this.options.stringifySearch(nextSearch);
        const hash = dest.hash === true ? currentLocation.hash : dest.hash ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.functionalUpdate)(dest.hash, currentLocation.hash) : void 0;
        const hashStr = hash ? `#${hash}` : "";
        let nextState = dest.state === true ? currentLocation.state : dest.state ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.functionalUpdate)(dest.state, currentLocation.state) : {};
        nextState = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(currentLocation.state, nextState);
        const fullPath = `${nextPathname}${searchStr}${hashStr}`;
        const url = new URL(fullPath, this.origin);
        const rewrittenUrl = (0,_rewrite_js__WEBPACK_IMPORTED_MODULE_13__.executeRewriteOutput)(this.rewrite, url);
        return {
          publicHref: rewrittenUrl.pathname + rewrittenUrl.search + rewrittenUrl.hash,
          href: fullPath,
          url: rewrittenUrl,
          pathname: nextPathname,
          search: nextSearch,
          searchStr,
          state: nextState,
          hash: hash ?? "",
          unmaskOnReload: dest.unmaskOnReload
        };
      };
      const buildWithMatches = (dest = {}, maskedDest) => {
        const next = build(dest);
        let maskedNext = maskedDest ? build(maskedDest) : void 0;
        if (!maskedNext) {
          const params = {};
          if (this.options.routeMasks) {
            const match = (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_4__.findFlatMatch)(
              next.pathname,
              this.processedTree
            );
            if (match) {
              Object.assign(params, match.params);
              const {
                from: _from,
                params: maskParams,
                ...maskProps
              } = match.route;
              const nextParams = maskParams === false || maskParams === null ? {} : (maskParams ?? true) === true ? params : Object.assign(params, (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.functionalUpdate)(maskParams, params));
              maskedDest = {
                from: opts.from,
                ...maskProps,
                params: nextParams
              };
              maskedNext = build(maskedDest);
            }
          }
        }
        if (maskedNext) {
          next.maskedLocation = maskedNext;
        }
        return next;
      };
      if (opts.mask) {
        return buildWithMatches(opts, {
          from: opts.from,
          ...opts.mask
        });
      }
      return buildWithMatches(opts);
    };
    this.commitLocation = ({
      viewTransition,
      ignoreBlocker,
      ...next
    }) => {
      const isSameState = () => {
        const ignoredProps = [
          "key",
          // TODO: Remove in v2 - use __TSR_key instead
          "__TSR_key",
          "__TSR_index",
          "__hashScrollIntoViewOptions"
        ];
        ignoredProps.forEach((prop) => {
          next.state[prop] = this.latestLocation.state[prop];
        });
        const isEqual = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.deepEqual)(next.state, this.latestLocation.state);
        ignoredProps.forEach((prop) => {
          delete next.state[prop];
        });
        return isEqual;
      };
      const isSameUrl = (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.trimPathRight)(this.latestLocation.href) === (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.trimPathRight)(next.href);
      const previousCommitPromise = this.commitLocationPromise;
      this.commitLocationPromise = (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.createControlledPromise)(() => {
        previousCommitPromise?.resolve();
      });
      if (isSameUrl && isSameState()) {
        this.load();
      } else {
        let {
          // eslint-disable-next-line prefer-const
          maskedLocation,
          // eslint-disable-next-line prefer-const
          hashScrollIntoView,
          // don't pass url into history since it is a URL instance that cannot be serialized
          // eslint-disable-next-line prefer-const
          url: _url,
          ...nextHistory
        } = next;
        if (maskedLocation) {
          nextHistory = {
            ...maskedLocation,
            state: {
              ...maskedLocation.state,
              __tempKey: void 0,
              __tempLocation: {
                ...nextHistory,
                search: nextHistory.searchStr,
                state: {
                  ...nextHistory.state,
                  __tempKey: void 0,
                  __tempLocation: void 0,
                  __TSR_key: void 0,
                  key: void 0
                  // TODO: Remove in v2 - use __TSR_key instead
                }
              }
            }
          };
          if (nextHistory.unmaskOnReload ?? this.options.unmaskOnReload ?? false) {
            nextHistory.state.__tempKey = this.tempLocationKey;
          }
        }
        nextHistory.state.__hashScrollIntoViewOptions = hashScrollIntoView ?? this.options.defaultHashScrollIntoView ?? true;
        this.shouldViewTransition = viewTransition;
        this.history[next.replace ? "replace" : "push"](
          nextHistory.publicHref,
          nextHistory.state,
          { ignoreBlocker }
        );
      }
      this.resetNextScroll = next.resetScroll ?? true;
      if (!this.history.subscribers.size) {
        this.load();
      }
      return this.commitLocationPromise;
    };
    this.buildAndCommitLocation = ({
      replace,
      resetScroll,
      hashScrollIntoView,
      viewTransition,
      ignoreBlocker,
      href,
      ...rest
    } = {}) => {
      if (href) {
        const currentIndex = this.history.location.state.__TSR_index;
        const parsed = (0,_tanstack_history__WEBPACK_IMPORTED_MODULE_2__.parseHref)(href, {
          __TSR_index: replace ? currentIndex : currentIndex + 1
        });
        rest.to = parsed.pathname;
        rest.search = this.options.parseSearch(parsed.search);
        rest.hash = parsed.hash.slice(1);
      }
      const location = this.buildLocation({
        ...rest,
        _includeValidateSearch: true
      });
      this.pendingBuiltLocation = location;
      const commitPromise = this.commitLocation({
        ...location,
        viewTransition,
        replace,
        resetScroll,
        hashScrollIntoView,
        ignoreBlocker
      });
      Promise.resolve().then(() => {
        if (this.pendingBuiltLocation === location) {
          this.pendingBuiltLocation = void 0;
        }
      });
      return commitPromise;
    };
    this.navigate = async ({ to, reloadDocument, href, ...rest }) => {
      if (!reloadDocument && href) {
        try {
          new URL(`${href}`);
          reloadDocument = true;
        } catch {
        }
      }
      if (reloadDocument) {
        if (!href) {
          const location = this.buildLocation({ to, ...rest });
          href = location.url.href;
        }
        if (!rest.ignoreBlocker) {
          const historyWithBlockers = this.history;
          const blockers = historyWithBlockers.getBlockers?.() ?? [];
          for (const blocker of blockers) {
            if (blocker?.blockerFn) {
              const shouldBlock = await blocker.blockerFn({
                currentLocation: this.latestLocation,
                nextLocation: this.latestLocation,
                // External URLs don't have a next location in our router
                action: "PUSH"
              });
              if (shouldBlock) {
                return Promise.resolve();
              }
            }
          }
        }
        if (rest.replace) {
          window.location.replace(href);
        } else {
          window.location.href = href;
        }
        return Promise.resolve();
      }
      return this.buildAndCommitLocation({
        ...rest,
        href,
        to,
        _isNavigate: true
      });
    };
    this.beforeLoad = () => {
      this.cancelMatches();
      this.updateLatestLocation();
      if (this.isServer) {
        const nextLocation = this.buildLocation({
          to: this.latestLocation.pathname,
          search: true,
          params: true,
          hash: true,
          state: true,
          _includeValidateSearch: true
        });
        if (this.latestLocation.publicHref !== nextLocation.publicHref || nextLocation.url.origin !== this.origin) {
          const href = this.getParsedLocationHref(nextLocation);
          throw (0,_redirect_js__WEBPACK_IMPORTED_MODULE_11__.redirect)({ href });
        }
      }
      const pendingMatches = this.matchRoutes(this.latestLocation);
      this.__store.setState((s) => ({
        ...s,
        status: "pending",
        statusCode: 200,
        isLoading: true,
        location: this.latestLocation,
        pendingMatches,
        // If a cached moved to pendingMatches, remove it from cachedMatches
        cachedMatches: s.cachedMatches.filter(
          (d) => !pendingMatches.some((e) => e.id === d.id)
        )
      }));
    };
    this.load = async (opts) => {
      let redirect2;
      let notFound;
      let loadPromise;
      loadPromise = new Promise((resolve) => {
        this.startTransition(async () => {
          try {
            this.beforeLoad();
            const next = this.latestLocation;
            const prevLocation = this.state.resolvedLocation;
            if (!this.state.redirect) {
              this.emit({
                type: "onBeforeNavigate",
                ...getLocationChangeInfo({
                  resolvedLocation: prevLocation,
                  location: next
                })
              });
            }
            this.emit({
              type: "onBeforeLoad",
              ...getLocationChangeInfo({
                resolvedLocation: prevLocation,
                location: next
              })
            });
            await (0,_load_matches_js__WEBPACK_IMPORTED_MODULE_12__.loadMatches)({
              router: this,
              sync: opts?.sync,
              matches: this.state.pendingMatches,
              location: next,
              updateMatch: this.updateMatch,
              // eslint-disable-next-line @typescript-eslint/require-await
              onReady: async () => {
                this.startTransition(() => {
                  this.startViewTransition(async () => {
                    let exitingMatches = [];
                    let enteringMatches = [];
                    let stayingMatches = [];
                    (0,_tanstack_store__WEBPACK_IMPORTED_MODULE_1__.batch)(() => {
                      this.__store.setState((s) => {
                        const previousMatches = s.matches;
                        const newMatches = s.pendingMatches || s.matches;
                        exitingMatches = previousMatches.filter(
                          (match) => !newMatches.some((d) => d.id === match.id)
                        );
                        enteringMatches = newMatches.filter(
                          (match) => !previousMatches.some((d) => d.id === match.id)
                        );
                        stayingMatches = newMatches.filter(
                          (match) => previousMatches.some((d) => d.id === match.id)
                        );
                        return {
                          ...s,
                          isLoading: false,
                          loadedAt: Date.now(),
                          matches: newMatches,
                          pendingMatches: void 0,
                          /**
                           * When committing new matches, cache any exiting matches that are still usable.
                           * Routes that resolved with `status: 'error'` or `status: 'notFound'` are
                           * deliberately excluded from `cachedMatches` so that subsequent invalidations
                           * or reloads re-run their loaders instead of reusing the failed/not-found data.
                           */
                          cachedMatches: [
                            ...s.cachedMatches,
                            ...exitingMatches.filter(
                              (d) => d.status !== "error" && d.status !== "notFound"
                            )
                          ]
                        };
                      });
                      this.clearExpiredCache();
                    });
                    [
                      [exitingMatches, "onLeave"],
                      [enteringMatches, "onEnter"],
                      [stayingMatches, "onStay"]
                    ].forEach(([matches, hook]) => {
                      matches.forEach((match) => {
                        this.looseRoutesById[match.routeId].options[hook]?.(
                          match
                        );
                      });
                    });
                  });
                });
              }
            });
          } catch (err) {
            if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_11__.isRedirect)(err)) {
              redirect2 = err;
              if (!this.isServer) {
                this.navigate({
                  ...redirect2.options,
                  replace: true,
                  ignoreBlocker: true
                });
              }
            } else if ((0,_not_found_js__WEBPACK_IMPORTED_MODULE_7__.isNotFound)(err)) {
              notFound = err;
            }
            this.__store.setState((s) => ({
              ...s,
              statusCode: redirect2 ? redirect2.status : notFound ? 404 : s.matches.some((d) => d.status === "error") ? 500 : 200,
              redirect: redirect2
            }));
          }
          if (this.latestLoadPromise === loadPromise) {
            this.commitLocationPromise?.resolve();
            this.latestLoadPromise = void 0;
            this.commitLocationPromise = void 0;
          }
          resolve();
        });
      });
      this.latestLoadPromise = loadPromise;
      await loadPromise;
      while (this.latestLoadPromise && loadPromise !== this.latestLoadPromise) {
        await this.latestLoadPromise;
      }
      let newStatusCode = void 0;
      if (this.hasNotFoundMatch()) {
        newStatusCode = 404;
      } else if (this.__store.state.matches.some((d) => d.status === "error")) {
        newStatusCode = 500;
      }
      if (newStatusCode !== void 0) {
        this.__store.setState((s) => ({
          ...s,
          statusCode: newStatusCode
        }));
      }
    };
    this.startViewTransition = (fn) => {
      const shouldViewTransition = this.shouldViewTransition ?? this.options.defaultViewTransition;
      delete this.shouldViewTransition;
      if (shouldViewTransition && typeof document !== "undefined" && "startViewTransition" in document && typeof document.startViewTransition === "function") {
        let startViewTransitionParams;
        if (typeof shouldViewTransition === "object" && this.isViewTransitionTypesSupported) {
          const next = this.latestLocation;
          const prevLocation = this.state.resolvedLocation;
          const resolvedViewTransitionTypes = typeof shouldViewTransition.types === "function" ? shouldViewTransition.types(
            getLocationChangeInfo({
              resolvedLocation: prevLocation,
              location: next
            })
          ) : shouldViewTransition.types;
          if (resolvedViewTransitionTypes === false) {
            fn();
            return;
          }
          startViewTransitionParams = {
            update: fn,
            types: resolvedViewTransitionTypes
          };
        } else {
          startViewTransitionParams = fn;
        }
        document.startViewTransition(startViewTransitionParams);
      } else {
        fn();
      }
    };
    this.updateMatch = (id, updater) => {
      this.startTransition(() => {
        const matchesKey = this.state.pendingMatches?.some((d) => d.id === id) ? "pendingMatches" : this.state.matches.some((d) => d.id === id) ? "matches" : this.state.cachedMatches.some((d) => d.id === id) ? "cachedMatches" : "";
        if (matchesKey) {
          this.__store.setState((s) => ({
            ...s,
            [matchesKey]: s[matchesKey]?.map(
              (d) => d.id === id ? updater(d) : d
            )
          }));
        }
      });
    };
    this.getMatch = (matchId) => {
      const findFn = (d) => d.id === matchId;
      return this.state.cachedMatches.find(findFn) ?? this.state.pendingMatches?.find(findFn) ?? this.state.matches.find(findFn);
    };
    this.invalidate = (opts) => {
      const invalidate = (d) => {
        if (opts?.filter?.(d) ?? true) {
          return {
            ...d,
            invalid: true,
            ...opts?.forcePending || d.status === "error" || d.status === "notFound" ? { status: "pending", error: void 0 } : void 0
          };
        }
        return d;
      };
      this.__store.setState((s) => ({
        ...s,
        matches: s.matches.map(invalidate),
        cachedMatches: s.cachedMatches.map(invalidate),
        pendingMatches: s.pendingMatches?.map(invalidate)
      }));
      this.shouldViewTransition = false;
      return this.load({ sync: opts?.sync });
    };
    this.getParsedLocationHref = (location) => {
      let href = location.url.href;
      if (this.origin && location.url.origin === this.origin) {
        href = href.replace(this.origin, "") || "/";
      }
      return href;
    };
    this.resolveRedirect = (redirect2) => {
      if (!redirect2.options.href) {
        const location = this.buildLocation(redirect2.options);
        const href = this.getParsedLocationHref(location);
        redirect2.options.href = location.href;
        redirect2.headers.set("Location", href);
      }
      if (!redirect2.headers.get("Location")) {
        redirect2.headers.set("Location", redirect2.options.href);
      }
      return redirect2;
    };
    this.clearCache = (opts) => {
      const filter = opts?.filter;
      if (filter !== void 0) {
        this.__store.setState((s) => {
          return {
            ...s,
            cachedMatches: s.cachedMatches.filter(
              (m) => !filter(m)
            )
          };
        });
      } else {
        this.__store.setState((s) => {
          return {
            ...s,
            cachedMatches: []
          };
        });
      }
    };
    this.clearExpiredCache = () => {
      const filter = (d) => {
        const route = this.looseRoutesById[d.routeId];
        if (!route.options.loader) {
          return true;
        }
        const gcTime = (d.preload ? route.options.preloadGcTime ?? this.options.defaultPreloadGcTime : route.options.gcTime ?? this.options.defaultGcTime) ?? 5 * 60 * 1e3;
        const isError = d.status === "error";
        if (isError) return true;
        const gcEligible = Date.now() - d.updatedAt >= gcTime;
        return gcEligible;
      };
      this.clearCache({ filter });
    };
    this.loadRouteChunk = _load_matches_js__WEBPACK_IMPORTED_MODULE_12__.loadRouteChunk;
    this.preloadRoute = async (opts) => {
      const next = this.buildLocation(opts);
      let matches = this.matchRoutes(next, {
        throwOnError: true,
        preload: true,
        dest: opts
      });
      const activeMatchIds = new Set(
        [...this.state.matches, ...this.state.pendingMatches ?? []].map(
          (d) => d.id
        )
      );
      const loadedMatchIds = /* @__PURE__ */ new Set([
        ...activeMatchIds,
        ...this.state.cachedMatches.map((d) => d.id)
      ]);
      (0,_tanstack_store__WEBPACK_IMPORTED_MODULE_1__.batch)(() => {
        matches.forEach((match) => {
          if (!loadedMatchIds.has(match.id)) {
            this.__store.setState((s) => ({
              ...s,
              cachedMatches: [...s.cachedMatches, match]
            }));
          }
        });
      });
      try {
        matches = await (0,_load_matches_js__WEBPACK_IMPORTED_MODULE_12__.loadMatches)({
          router: this,
          matches,
          location: next,
          preload: true,
          updateMatch: (id, updater) => {
            if (activeMatchIds.has(id)) {
              matches = matches.map((d) => d.id === id ? updater(d) : d);
            } else {
              this.updateMatch(id, updater);
            }
          }
        });
        return matches;
      } catch (err) {
        if ((0,_redirect_js__WEBPACK_IMPORTED_MODULE_11__.isRedirect)(err)) {
          if (err.options.reloadDocument) {
            return void 0;
          }
          return await this.preloadRoute({
            ...err.options,
            _fromLocation: next
          });
        }
        if (!(0,_not_found_js__WEBPACK_IMPORTED_MODULE_7__.isNotFound)(err)) {
          console.error(err);
        }
        return void 0;
      }
    };
    this.matchRoute = (location, opts) => {
      const matchLocation = {
        ...location,
        to: location.to ? this.resolvePathWithBase(
          location.from || "",
          location.to
        ) : void 0,
        params: location.params || {},
        leaveParams: true
      };
      const next = this.buildLocation(matchLocation);
      if (opts?.pending && this.state.status !== "pending") {
        return false;
      }
      const pending = opts?.pending === void 0 ? !this.state.isLoading : opts.pending;
      const baseLocation = pending ? this.latestLocation : this.state.resolvedLocation || this.state.location;
      const match = (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_4__.findSingleMatch)(
        next.pathname,
        opts?.caseSensitive ?? false,
        opts?.fuzzy ?? false,
        baseLocation.pathname,
        this.processedTree
      );
      if (!match) {
        return false;
      }
      if (location.params) {
        if (!(0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.deepEqual)(match.params, location.params, { partial: true })) {
          return false;
        }
      }
      if (opts?.includeSearch ?? true) {
        return (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.deepEqual)(baseLocation.search, next.search, { partial: true }) ? match.params : false;
      }
      return match.params;
    };
    this.hasNotFoundMatch = () => {
      return this.__store.state.matches.some(
        (d) => d.status === "notFound" || d.globalNotFound
      );
    };
    this.update({
      defaultPreloadDelay: 50,
      defaultPendingMs: 1e3,
      defaultPendingMinMs: 500,
      context: void 0,
      ...options,
      caseSensitive: options.caseSensitive ?? false,
      notFoundMode: options.notFoundMode ?? "fuzzy",
      stringifySearch: options.stringifySearch ?? _searchParams_js__WEBPACK_IMPORTED_MODULE_9__.defaultStringifySearch,
      parseSearch: options.parseSearch ?? _searchParams_js__WEBPACK_IMPORTED_MODULE_9__.defaultParseSearch
    });
    if (typeof document !== "undefined") {
      self.__TSR_ROUTER__ = this;
    }
  }
  isShell() {
    return !!this.options.isShell;
  }
  isPrerendering() {
    return !!this.options.isPrerendering;
  }
  get state() {
    return this.__store.state;
  }
  get looseRoutesById() {
    return this.routesById;
  }
  matchRoutesInternal(next, opts) {
    const matchedRoutesResult = this.getMatchedRoutes(next.pathname);
    const { foundRoute, routeParams } = matchedRoutesResult;
    let { matchedRoutes } = matchedRoutesResult;
    let isGlobalNotFound = false;
    if (
      // If we found a route, and it's not an index route and we have left over path
      foundRoute ? foundRoute.path !== "/" && routeParams["**"] : (
        // Or if we didn't find a route and we have left over path
        (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.trimPathRight)(next.pathname)
      )
    ) {
      if (this.options.notFoundRoute) {
        matchedRoutes = [...matchedRoutes, this.options.notFoundRoute];
      } else {
        isGlobalNotFound = true;
      }
    }
    const globalNotFoundRouteId = (() => {
      if (!isGlobalNotFound) {
        return void 0;
      }
      if (this.options.notFoundMode !== "root") {
        for (let i = matchedRoutes.length - 1; i >= 0; i--) {
          const route = matchedRoutes[i];
          if (route.children) {
            return route.id;
          }
        }
      }
      return _root_js__WEBPACK_IMPORTED_MODULE_10__.rootRouteId;
    })();
    const matches = [];
    const getParentContext = (parentMatch) => {
      const parentMatchId = parentMatch?.id;
      const parentContext = !parentMatchId ? this.options.context ?? void 0 : parentMatch.context ?? this.options.context ?? void 0;
      return parentContext;
    };
    matchedRoutes.forEach((route, index) => {
      const parentMatch = matches[index - 1];
      const [preMatchSearch, strictMatchSearch, searchError] = (() => {
        const parentSearch = parentMatch?.search ?? next.search;
        const parentStrictSearch = parentMatch?._strictSearch ?? void 0;
        try {
          const strictSearch = validateSearch(route.options.validateSearch, { ...parentSearch }) ?? void 0;
          return [
            {
              ...parentSearch,
              ...strictSearch
            },
            { ...parentStrictSearch, ...strictSearch },
            void 0
          ];
        } catch (err) {
          let searchParamError = err;
          if (!(err instanceof SearchParamError)) {
            searchParamError = new SearchParamError(err.message, {
              cause: err
            });
          }
          if (opts?.throwOnError) {
            throw searchParamError;
          }
          return [parentSearch, {}, searchParamError];
        }
      })();
      const loaderDeps = route.options.loaderDeps?.({
        search: preMatchSearch
      }) ?? "";
      const loaderDepsHash = loaderDeps ? JSON.stringify(loaderDeps) : "";
      const { interpolatedPath, usedParams } = (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.interpolatePath)({
        path: route.fullPath,
        params: routeParams,
        decodeCharMap: this.pathParamsDecodeCharMap
      });
      const matchId = (
        // route.id for disambiguation
        route.id + // interpolatedPath for param changes
        interpolatedPath + // explicit deps
        loaderDepsHash
      );
      const existingMatch = this.getMatch(matchId);
      const previousMatch = this.state.matches.find(
        (d) => d.routeId === route.id
      );
      const strictParams = existingMatch?._strictParams ?? usedParams;
      let paramsError = void 0;
      if (!existingMatch) {
        const strictParseParams = route.options.params?.parse ?? route.options.parseParams;
        if (strictParseParams) {
          try {
            Object.assign(
              strictParams,
              strictParseParams(strictParams)
            );
          } catch (err) {
            if ((0,_not_found_js__WEBPACK_IMPORTED_MODULE_7__.isNotFound)(err) || (0,_redirect_js__WEBPACK_IMPORTED_MODULE_11__.isRedirect)(err)) {
              paramsError = err;
            } else {
              paramsError = new PathParamError(err.message, {
                cause: err
              });
            }
            if (opts?.throwOnError) {
              throw paramsError;
            }
          }
        }
      }
      Object.assign(routeParams, strictParams);
      const cause = previousMatch ? "stay" : "enter";
      let match;
      if (existingMatch) {
        match = {
          ...existingMatch,
          cause,
          params: previousMatch ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousMatch.params, routeParams) : routeParams,
          _strictParams: strictParams,
          search: previousMatch ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousMatch.search, preMatchSearch) : (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(existingMatch.search, preMatchSearch),
          _strictSearch: strictMatchSearch
        };
      } else {
        const status = route.options.loader || route.options.beforeLoad || route.lazyFn || (0,_load_matches_js__WEBPACK_IMPORTED_MODULE_12__.routeNeedsPreload)(route) ? "pending" : "success";
        match = {
          id: matchId,
          ssr: this.isServer ? void 0 : route.options.ssr,
          index,
          routeId: route.id,
          params: previousMatch ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousMatch.params, routeParams) : routeParams,
          _strictParams: strictParams,
          pathname: interpolatedPath,
          updatedAt: Date.now(),
          search: previousMatch ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousMatch.search, preMatchSearch) : preMatchSearch,
          _strictSearch: strictMatchSearch,
          searchError: void 0,
          status,
          isFetching: false,
          error: void 0,
          paramsError,
          __routeContext: void 0,
          _nonReactive: {
            loadPromise: (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.createControlledPromise)()
          },
          __beforeLoadContext: void 0,
          context: {},
          abortController: new AbortController(),
          fetchCount: 0,
          cause,
          loaderDeps: previousMatch ? (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.replaceEqualDeep)(previousMatch.loaderDeps, loaderDeps) : loaderDeps,
          invalid: false,
          preload: false,
          links: void 0,
          scripts: void 0,
          headScripts: void 0,
          meta: void 0,
          staticData: route.options.staticData || {},
          fullPath: route.fullPath
        };
      }
      if (!opts?.preload) {
        match.globalNotFound = globalNotFoundRouteId === route.id;
      }
      match.searchError = searchError;
      const parentContext = getParentContext(parentMatch);
      match.context = {
        ...parentContext,
        ...match.__routeContext,
        ...match.__beforeLoadContext
      };
      matches.push(match);
    });
    matches.forEach((match, index) => {
      const route = this.looseRoutesById[match.routeId];
      const existingMatch = this.getMatch(match.id);
      if (!existingMatch && opts?._buildLocation !== true) {
        const parentMatch = matches[index - 1];
        const parentContext = getParentContext(parentMatch);
        if (route.options.context) {
          const contextFnContext = {
            deps: match.loaderDeps,
            params: match.params,
            context: parentContext ?? {},
            location: next,
            navigate: (opts2) => this.navigate({ ...opts2, _fromLocation: next }),
            buildLocation: this.buildLocation,
            cause: match.cause,
            abortController: match.abortController,
            preload: !!match.preload,
            matches
          };
          match.__routeContext = route.options.context(contextFnContext) ?? void 0;
        }
        match.context = {
          ...parentContext,
          ...match.__routeContext,
          ...match.__beforeLoadContext
        };
      }
    });
    return matches;
  }
}
class SearchParamError extends Error {
}
class PathParamError extends Error {
}
const normalize = (str) => str.endsWith("/") && str.length > 1 ? str.slice(0, -1) : str;
function comparePaths(a, b) {
  return normalize(a) === normalize(b);
}
function lazyFn(fn, key) {
  return async (...args) => {
    const imported = await fn();
    return imported[key || "default"](...args);
  };
}
function getInitialRouterState(location) {
  return {
    loadedAt: 0,
    isLoading: false,
    isTransitioning: false,
    status: "idle",
    resolvedLocation: void 0,
    location,
    matches: [],
    pendingMatches: [],
    cachedMatches: [],
    statusCode: 200
  };
}
function validateSearch(validateSearch2, input) {
  if (validateSearch2 == null) return {};
  if ("~standard" in validateSearch2) {
    const result = validateSearch2["~standard"].validate(input);
    if (result instanceof Promise)
      throw new SearchParamError("Async validation not supported");
    if (result.issues)
      throw new SearchParamError(JSON.stringify(result.issues, void 0, 2), {
        cause: result
      });
    return result.value;
  }
  if ("parse" in validateSearch2) {
    return validateSearch2.parse(input);
  }
  if (typeof validateSearch2 === "function") {
    return validateSearch2(input);
  }
  return {};
}
function getMatchedRoutes({
  pathname,
  routesById,
  processedTree
}) {
  const routeParams = {};
  const trimmedPath = (0,_path_js__WEBPACK_IMPORTED_MODULE_5__.trimPathRight)(pathname);
  let foundRoute = void 0;
  const match = (0,_new_process_route_tree_js__WEBPACK_IMPORTED_MODULE_4__.findRouteMatch)(trimmedPath, processedTree, true);
  if (match) {
    foundRoute = match.route;
    Object.assign(routeParams, match.params);
  }
  const matchedRoutes = match?.branch || [routesById[_root_js__WEBPACK_IMPORTED_MODULE_10__.rootRouteId]];
  return { matchedRoutes, routeParams, foundRoute };
}
function applySearchMiddleware({
  search,
  dest,
  destRoutes,
  _includeValidateSearch
}) {
  const allMiddlewares = destRoutes.reduce(
    (acc, route) => {
      const middlewares = [];
      if ("search" in route.options) {
        if (route.options.search?.middlewares) {
          middlewares.push(...route.options.search.middlewares);
        }
      } else if (route.options.preSearchFilters || route.options.postSearchFilters) {
        const legacyMiddleware = ({
          search: search2,
          next
        }) => {
          let nextSearch = search2;
          if ("preSearchFilters" in route.options && route.options.preSearchFilters) {
            nextSearch = route.options.preSearchFilters.reduce(
              (prev, next2) => next2(prev),
              search2
            );
          }
          const result = next(nextSearch);
          if ("postSearchFilters" in route.options && route.options.postSearchFilters) {
            return route.options.postSearchFilters.reduce(
              (prev, next2) => next2(prev),
              result
            );
          }
          return result;
        };
        middlewares.push(legacyMiddleware);
      }
      if (_includeValidateSearch && route.options.validateSearch) {
        const validate = ({ search: search2, next }) => {
          const result = next(search2);
          try {
            const validatedSearch = {
              ...result,
              ...validateSearch(route.options.validateSearch, result) ?? void 0
            };
            return validatedSearch;
          } catch {
            return result;
          }
        };
        middlewares.push(validate);
      }
      return acc.concat(middlewares);
    },
    []
  ) ?? [];
  const final = ({ search: search2 }) => {
    if (!dest.search) {
      return {};
    }
    if (dest.search === true) {
      return search2;
    }
    return (0,_utils_js__WEBPACK_IMPORTED_MODULE_3__.functionalUpdate)(dest.search, search2);
  };
  allMiddlewares.push(final);
  const applyNext = (index, currentSearch) => {
    if (index >= allMiddlewares.length) {
      return currentSearch;
    }
    const middleware = allMiddlewares[index];
    const next = (newSearch) => {
      return applyNext(index + 1, newSearch);
    };
    return middleware({ search: currentSearch, next });
  };
  return applyNext(0, search);
}

//# sourceMappingURL=router.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/scroll-restoration.js":
/*!***************************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/scroll-restoration.js ***!
  \***************************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   defaultGetScrollRestorationKey: () => (/* binding */ defaultGetScrollRestorationKey),
/* harmony export */   getCssSelector: () => (/* binding */ getCssSelector),
/* harmony export */   handleHashScroll: () => (/* binding */ handleHashScroll),
/* harmony export */   restoreScroll: () => (/* binding */ restoreScroll),
/* harmony export */   scrollRestorationCache: () => (/* binding */ scrollRestorationCache),
/* harmony export */   setupScrollRestoration: () => (/* binding */ setupScrollRestoration),
/* harmony export */   storageKey: () => (/* binding */ storageKey)
/* harmony export */ });
/* harmony import */ var _utils_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./utils.js */ "./node_modules/@tanstack/router-core/dist/esm/utils.js");

function getSafeSessionStorage() {
  try {
    if (typeof window !== "undefined" && typeof window.sessionStorage === "object") {
      return window.sessionStorage;
    }
  } catch {
  }
  return void 0;
}
const storageKey = "tsr-scroll-restoration-v1_3";
const throttle = (fn, wait) => {
  let timeout;
  return (...args) => {
    if (!timeout) {
      timeout = setTimeout(() => {
        fn(...args);
        timeout = null;
      }, wait);
    }
  };
};
function createScrollRestorationCache() {
  const safeSessionStorage = getSafeSessionStorage();
  if (!safeSessionStorage) {
    return null;
  }
  const persistedState = safeSessionStorage.getItem(storageKey);
  let state = persistedState ? JSON.parse(persistedState) : {};
  return {
    state,
    // This setter is simply to make sure that we set the sessionStorage right
    // after the state is updated. It doesn't necessarily need to be a functional
    // update.
    set: (updater) => (state = (0,_utils_js__WEBPACK_IMPORTED_MODULE_0__.functionalUpdate)(updater, state) || state, safeSessionStorage.setItem(storageKey, JSON.stringify(state)))
  };
}
const scrollRestorationCache = createScrollRestorationCache();
const defaultGetScrollRestorationKey = (location) => {
  return location.state.__TSR_key || location.href;
};
function getCssSelector(el) {
  const path = [];
  let parent;
  while (parent = el.parentNode) {
    path.push(
      `${el.tagName}:nth-child(${Array.prototype.indexOf.call(parent.children, el) + 1})`
    );
    el = parent;
  }
  return `${path.reverse().join(" > ")}`.toLowerCase();
}
let ignoreScroll = false;
function restoreScroll({
  storageKey: storageKey2,
  key,
  behavior,
  shouldScrollRestoration,
  scrollToTopSelectors,
  location
}) {
  let byKey;
  try {
    byKey = JSON.parse(sessionStorage.getItem(storageKey2) || "{}");
  } catch (error) {
    console.error(error);
    return;
  }
  const resolvedKey = key || window.history.state?.__TSR_key;
  const elementEntries = byKey[resolvedKey];
  ignoreScroll = true;
  scroll: {
    if (shouldScrollRestoration && elementEntries && Object.keys(elementEntries).length > 0) {
      for (const elementSelector in elementEntries) {
        const entry = elementEntries[elementSelector];
        if (elementSelector === "window") {
          window.scrollTo({
            top: entry.scrollY,
            left: entry.scrollX,
            behavior
          });
        } else if (elementSelector) {
          const element = document.querySelector(elementSelector);
          if (element) {
            element.scrollLeft = entry.scrollX;
            element.scrollTop = entry.scrollY;
          }
        }
      }
      break scroll;
    }
    const hash = (location ?? window.location).hash.split("#", 2)[1];
    if (hash) {
      const hashScrollIntoViewOptions = window.history.state?.__hashScrollIntoViewOptions ?? true;
      if (hashScrollIntoViewOptions) {
        const el = document.getElementById(hash);
        if (el) {
          el.scrollIntoView(hashScrollIntoViewOptions);
        }
      }
      break scroll;
    }
    const scrollOptions = { top: 0, left: 0, behavior };
    window.scrollTo(scrollOptions);
    if (scrollToTopSelectors) {
      for (const selector of scrollToTopSelectors) {
        if (selector === "window") continue;
        const element = typeof selector === "function" ? selector() : document.querySelector(selector);
        if (element) element.scrollTo(scrollOptions);
      }
    }
  }
  ignoreScroll = false;
}
function setupScrollRestoration(router, force) {
  if (!scrollRestorationCache && !router.isServer) {
    return;
  }
  const shouldScrollRestoration = force ?? router.options.scrollRestoration ?? false;
  if (shouldScrollRestoration) {
    router.isScrollRestoring = true;
  }
  if (router.isServer || router.isScrollRestorationSetup || !scrollRestorationCache) {
    return;
  }
  router.isScrollRestorationSetup = true;
  ignoreScroll = false;
  const getKey = router.options.getScrollRestorationKey || defaultGetScrollRestorationKey;
  window.history.scrollRestoration = "manual";
  const onScroll = (event) => {
    if (ignoreScroll || !router.isScrollRestoring) {
      return;
    }
    let elementSelector = "";
    if (event.target === document || event.target === window) {
      elementSelector = "window";
    } else {
      const attrId = event.target.getAttribute(
        "data-scroll-restoration-id"
      );
      if (attrId) {
        elementSelector = `[data-scroll-restoration-id="${attrId}"]`;
      } else {
        elementSelector = getCssSelector(event.target);
      }
    }
    const restoreKey = getKey(router.state.location);
    scrollRestorationCache.set((state) => {
      const keyEntry = state[restoreKey] ||= {};
      const elementEntry = keyEntry[elementSelector] ||= {};
      if (elementSelector === "window") {
        elementEntry.scrollX = window.scrollX || 0;
        elementEntry.scrollY = window.scrollY || 0;
      } else if (elementSelector) {
        const element = document.querySelector(elementSelector);
        if (element) {
          elementEntry.scrollX = element.scrollLeft || 0;
          elementEntry.scrollY = element.scrollTop || 0;
        }
      }
      return state;
    });
  };
  if (typeof document !== "undefined") {
    document.addEventListener("scroll", throttle(onScroll, 100), true);
  }
  router.subscribe("onRendered", (event) => {
    const cacheKey = getKey(event.toLocation);
    if (!router.resetNextScroll) {
      router.resetNextScroll = true;
      return;
    }
    if (typeof router.options.scrollRestoration === "function") {
      const shouldRestore = router.options.scrollRestoration({
        location: router.latestLocation
      });
      if (!shouldRestore) {
        return;
      }
    }
    restoreScroll({
      storageKey,
      key: cacheKey,
      behavior: router.options.scrollRestorationBehavior,
      shouldScrollRestoration: router.isScrollRestoring,
      scrollToTopSelectors: router.options.scrollToTopSelectors,
      location: router.history.location
    });
    if (router.isScrollRestoring) {
      scrollRestorationCache.set((state) => {
        state[cacheKey] ||= {};
        return state;
      });
    }
  });
}
function handleHashScroll(router) {
  if (typeof document !== "undefined" && document.querySelector) {
    const hashScrollIntoViewOptions = router.state.location.state.__hashScrollIntoViewOptions ?? true;
    if (hashScrollIntoViewOptions && router.state.location.hash !== "") {
      const el = document.getElementById(router.state.location.hash);
      if (el) {
        el.scrollIntoView(hashScrollIntoViewOptions);
      }
    }
  }
}

//# sourceMappingURL=scroll-restoration.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/searchParams.js":
/*!*********************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/searchParams.js ***!
  \*********************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   defaultParseSearch: () => (/* binding */ defaultParseSearch),
/* harmony export */   defaultStringifySearch: () => (/* binding */ defaultStringifySearch),
/* harmony export */   parseSearchWith: () => (/* binding */ parseSearchWith),
/* harmony export */   stringifySearchWith: () => (/* binding */ stringifySearchWith)
/* harmony export */ });
/* harmony import */ var _qss_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./qss.js */ "./node_modules/@tanstack/router-core/dist/esm/qss.js");

const defaultParseSearch = parseSearchWith(JSON.parse);
const defaultStringifySearch = stringifySearchWith(
  JSON.stringify,
  JSON.parse
);
function parseSearchWith(parser) {
  return (searchStr) => {
    if (searchStr[0] === "?") {
      searchStr = searchStr.substring(1);
    }
    const query = (0,_qss_js__WEBPACK_IMPORTED_MODULE_0__.decode)(searchStr);
    for (const key in query) {
      const value = query[key];
      if (typeof value === "string") {
        try {
          query[key] = parser(value);
        } catch (_err) {
        }
      }
    }
    return query;
  };
}
function stringifySearchWith(stringify, parser) {
  const hasParser = typeof parser === "function";
  function stringifyValue(val) {
    if (typeof val === "object" && val !== null) {
      try {
        return stringify(val);
      } catch (_err) {
      }
    } else if (hasParser && typeof val === "string") {
      try {
        parser(val);
        return stringify(val);
      } catch (_err) {
      }
    }
    return val;
  }
  return (search) => {
    const searchStr = (0,_qss_js__WEBPACK_IMPORTED_MODULE_0__.encode)(search, stringifyValue);
    return searchStr ? `?${searchStr}` : "";
  };
}

//# sourceMappingURL=searchParams.js.map


/***/ }),

/***/ "./node_modules/@tanstack/router-core/dist/esm/utils.js":
/*!**************************************************************!*\
  !*** ./node_modules/@tanstack/router-core/dist/esm/utils.js ***!
  \**************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   createControlledPromise: () => (/* binding */ createControlledPromise),
/* harmony export */   decodePath: () => (/* binding */ decodePath),
/* harmony export */   deepEqual: () => (/* binding */ deepEqual),
/* harmony export */   findLast: () => (/* binding */ findLast),
/* harmony export */   functionalUpdate: () => (/* binding */ functionalUpdate),
/* harmony export */   isModuleNotFoundError: () => (/* binding */ isModuleNotFoundError),
/* harmony export */   isPlainArray: () => (/* binding */ isPlainArray),
/* harmony export */   isPlainObject: () => (/* binding */ isPlainObject),
/* harmony export */   isPromise: () => (/* binding */ isPromise),
/* harmony export */   last: () => (/* binding */ last),
/* harmony export */   replaceEqualDeep: () => (/* binding */ replaceEqualDeep)
/* harmony export */ });
function last(arr) {
  return arr[arr.length - 1];
}
function isFunction(d) {
  return typeof d === "function";
}
function functionalUpdate(updater, previous) {
  if (isFunction(updater)) {
    return updater(previous);
  }
  return updater;
}
const hasOwn = Object.prototype.hasOwnProperty;
function replaceEqualDeep(prev, _next) {
  if (prev === _next) {
    return prev;
  }
  const next = _next;
  const array = isPlainArray(prev) && isPlainArray(next);
  if (!array && !(isPlainObject(prev) && isPlainObject(next))) return next;
  const prevItems = array ? prev : getEnumerableOwnKeys(prev);
  if (!prevItems) return next;
  const nextItems = array ? next : getEnumerableOwnKeys(next);
  if (!nextItems) return next;
  const prevSize = prevItems.length;
  const nextSize = nextItems.length;
  const copy = array ? new Array(nextSize) : {};
  let equalItems = 0;
  for (let i = 0; i < nextSize; i++) {
    const key = array ? i : nextItems[i];
    const p = prev[key];
    const n = next[key];
    if (p === n) {
      copy[key] = p;
      if (array ? i < prevSize : hasOwn.call(prev, key)) equalItems++;
      continue;
    }
    if (p === null || n === null || typeof p !== "object" || typeof n !== "object") {
      copy[key] = n;
      continue;
    }
    const v = replaceEqualDeep(p, n);
    copy[key] = v;
    if (v === p) equalItems++;
  }
  return prevSize === nextSize && equalItems === prevSize ? prev : copy;
}
function getEnumerableOwnKeys(o) {
  const keys = [];
  const names = Object.getOwnPropertyNames(o);
  for (const name of names) {
    if (!Object.prototype.propertyIsEnumerable.call(o, name)) return false;
    keys.push(name);
  }
  const symbols = Object.getOwnPropertySymbols(o);
  for (const symbol of symbols) {
    if (!Object.prototype.propertyIsEnumerable.call(o, symbol)) return false;
    keys.push(symbol);
  }
  return keys;
}
function isPlainObject(o) {
  if (!hasObjectPrototype(o)) {
    return false;
  }
  const ctor = o.constructor;
  if (typeof ctor === "undefined") {
    return true;
  }
  const prot = ctor.prototype;
  if (!hasObjectPrototype(prot)) {
    return false;
  }
  if (!prot.hasOwnProperty("isPrototypeOf")) {
    return false;
  }
  return true;
}
function hasObjectPrototype(o) {
  return Object.prototype.toString.call(o) === "[object Object]";
}
function isPlainArray(value) {
  return Array.isArray(value) && value.length === Object.keys(value).length;
}
function deepEqual(a, b, opts) {
  if (a === b) {
    return true;
  }
  if (typeof a !== typeof b) {
    return false;
  }
  if (Array.isArray(a) && Array.isArray(b)) {
    if (a.length !== b.length) return false;
    for (let i = 0, l = a.length; i < l; i++) {
      if (!deepEqual(a[i], b[i], opts)) return false;
    }
    return true;
  }
  if (isPlainObject(a) && isPlainObject(b)) {
    const ignoreUndefined = opts?.ignoreUndefined ?? true;
    if (opts?.partial) {
      for (const k in b) {
        if (!ignoreUndefined || b[k] !== void 0) {
          if (!deepEqual(a[k], b[k], opts)) return false;
        }
      }
      return true;
    }
    let aCount = 0;
    if (!ignoreUndefined) {
      aCount = Object.keys(a).length;
    } else {
      for (const k in a) {
        if (a[k] !== void 0) aCount++;
      }
    }
    let bCount = 0;
    for (const k in b) {
      if (!ignoreUndefined || b[k] !== void 0) {
        bCount++;
        if (bCount > aCount || !deepEqual(a[k], b[k], opts)) return false;
      }
    }
    return aCount === bCount;
  }
  return false;
}
function createControlledPromise(onResolve) {
  let resolveLoadPromise;
  let rejectLoadPromise;
  const controlledPromise = new Promise((resolve, reject) => {
    resolveLoadPromise = resolve;
    rejectLoadPromise = reject;
  });
  controlledPromise.status = "pending";
  controlledPromise.resolve = (value) => {
    controlledPromise.status = "resolved";
    controlledPromise.value = value;
    resolveLoadPromise(value);
    onResolve?.(value);
  };
  controlledPromise.reject = (e) => {
    controlledPromise.status = "rejected";
    rejectLoadPromise(e);
  };
  return controlledPromise;
}
function isModuleNotFoundError(error) {
  if (typeof error?.message !== "string") return false;
  return error.message.startsWith("Failed to fetch dynamically imported module") || error.message.startsWith("error loading dynamically imported module") || error.message.startsWith("Importing a module script failed");
}
function isPromise(value) {
  return Boolean(
    value && typeof value === "object" && typeof value.then === "function"
  );
}
function findLast(array, predicate) {
  for (let i = array.length - 1; i >= 0; i--) {
    const item = array[i];
    if (predicate(item)) return item;
  }
  return void 0;
}
function decodeSegment(segment) {
  try {
    return decodeURI(segment);
  } catch {
    return segment.replaceAll(/%[0-9A-F]{2}/gi, (match) => {
      try {
        return decodeURI(match);
      } catch {
        return match;
      }
    });
  }
}
function decodePath(path, decodeIgnore) {
  if (!path) return path;
  const re = decodeIgnore ? new RegExp(`${decodeIgnore.join("|")}`, "gi") : /%25|%5C/gi;
  let cursor = 0;
  let result = "";
  let match;
  while (null !== (match = re.exec(path))) {
    result += decodeSegment(path.slice(cursor, match.index)) + match[0];
    cursor = re.lastIndex;
  }
  return result + decodeSegment(cursor ? path.slice(cursor) : path);
}

//# sourceMappingURL=utils.js.map


/***/ }),

/***/ "./node_modules/@tanstack/store/dist/esm/derived.js":
/*!**********************************************************!*\
  !*** ./node_modules/@tanstack/store/dist/esm/derived.js ***!
  \**********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Derived: () => (/* binding */ Derived)
/* harmony export */ });
/* harmony import */ var _store_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./store.js */ "./node_modules/@tanstack/store/dist/esm/store.js");
/* harmony import */ var _scheduler_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./scheduler.js */ "./node_modules/@tanstack/store/dist/esm/scheduler.js");


class Derived {
  constructor(options) {
    this.listeners = /* @__PURE__ */ new Set();
    this._subscriptions = [];
    this.lastSeenDepValues = [];
    this.getDepVals = () => {
      const l = this.options.deps.length;
      const prevDepVals = new Array(l);
      const currDepVals = new Array(l);
      for (let i = 0; i < l; i++) {
        const dep = this.options.deps[i];
        prevDepVals[i] = dep.prevState;
        currDepVals[i] = dep.state;
      }
      this.lastSeenDepValues = currDepVals;
      return {
        prevDepVals,
        currDepVals,
        prevVal: this.prevState ?? void 0
      };
    };
    this.recompute = () => {
      var _a, _b;
      this.prevState = this.state;
      const depVals = this.getDepVals();
      this.state = this.options.fn(depVals);
      (_b = (_a = this.options).onUpdate) == null ? void 0 : _b.call(_a);
    };
    this.checkIfRecalculationNeededDeeply = () => {
      for (const dep of this.options.deps) {
        if (dep instanceof Derived) {
          dep.checkIfRecalculationNeededDeeply();
        }
      }
      let shouldRecompute = false;
      const lastSeenDepValues = this.lastSeenDepValues;
      const { currDepVals } = this.getDepVals();
      for (let i = 0; i < currDepVals.length; i++) {
        if (currDepVals[i] !== lastSeenDepValues[i]) {
          shouldRecompute = true;
          break;
        }
      }
      if (shouldRecompute) {
        this.recompute();
      }
    };
    this.mount = () => {
      this.registerOnGraph();
      this.checkIfRecalculationNeededDeeply();
      return () => {
        this.unregisterFromGraph();
        for (const cleanup of this._subscriptions) {
          cleanup();
        }
      };
    };
    this.subscribe = (listener) => {
      var _a, _b;
      this.listeners.add(listener);
      const unsub = (_b = (_a = this.options).onSubscribe) == null ? void 0 : _b.call(_a, listener, this);
      return () => {
        this.listeners.delete(listener);
        unsub == null ? void 0 : unsub();
      };
    };
    this.options = options;
    this.state = options.fn({
      prevDepVals: void 0,
      prevVal: void 0,
      currDepVals: this.getDepVals().currDepVals
    });
  }
  registerOnGraph(deps = this.options.deps) {
    const toSort = /* @__PURE__ */ new Set();
    for (const dep of deps) {
      if (dep instanceof Derived) {
        dep.registerOnGraph();
        this.registerOnGraph(dep.options.deps);
      } else if (dep instanceof _store_js__WEBPACK_IMPORTED_MODULE_0__.Store) {
        let relatedLinkedDerivedVals = _scheduler_js__WEBPACK_IMPORTED_MODULE_1__.__storeToDerived.get(dep);
        if (!relatedLinkedDerivedVals) {
          relatedLinkedDerivedVals = [this];
          _scheduler_js__WEBPACK_IMPORTED_MODULE_1__.__storeToDerived.set(dep, relatedLinkedDerivedVals);
        } else if (!relatedLinkedDerivedVals.includes(this)) {
          relatedLinkedDerivedVals.push(this);
          toSort.add(relatedLinkedDerivedVals);
        }
        let relatedStores = _scheduler_js__WEBPACK_IMPORTED_MODULE_1__.__derivedToStore.get(this);
        if (!relatedStores) {
          relatedStores = /* @__PURE__ */ new Set();
          _scheduler_js__WEBPACK_IMPORTED_MODULE_1__.__derivedToStore.set(this, relatedStores);
        }
        relatedStores.add(dep);
      }
    }
    for (const arr of toSort) {
      arr.sort((a, b) => {
        if (a instanceof Derived && a.options.deps.includes(b)) return 1;
        if (b instanceof Derived && b.options.deps.includes(a)) return -1;
        return 0;
      });
    }
  }
  unregisterFromGraph(deps = this.options.deps) {
    for (const dep of deps) {
      if (dep instanceof Derived) {
        this.unregisterFromGraph(dep.options.deps);
      } else if (dep instanceof _store_js__WEBPACK_IMPORTED_MODULE_0__.Store) {
        const relatedLinkedDerivedVals = _scheduler_js__WEBPACK_IMPORTED_MODULE_1__.__storeToDerived.get(dep);
        if (relatedLinkedDerivedVals) {
          relatedLinkedDerivedVals.splice(
            relatedLinkedDerivedVals.indexOf(this),
            1
          );
        }
        const relatedStores = _scheduler_js__WEBPACK_IMPORTED_MODULE_1__.__derivedToStore.get(this);
        if (relatedStores) {
          relatedStores.delete(dep);
        }
      }
    }
  }
}

//# sourceMappingURL=derived.js.map


/***/ }),

/***/ "./node_modules/@tanstack/store/dist/esm/effect.js":
/*!*********************************************************!*\
  !*** ./node_modules/@tanstack/store/dist/esm/effect.js ***!
  \*********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Effect: () => (/* binding */ Effect)
/* harmony export */ });
/* harmony import */ var _derived_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./derived.js */ "./node_modules/@tanstack/store/dist/esm/derived.js");

class Effect {
  constructor(opts) {
    const { eager, fn, ...derivedProps } = opts;
    this._derived = new _derived_js__WEBPACK_IMPORTED_MODULE_0__.Derived({
      ...derivedProps,
      fn: () => {
      },
      onUpdate() {
        fn();
      }
    });
    if (eager) {
      fn();
    }
  }
  mount() {
    return this._derived.mount();
  }
}

//# sourceMappingURL=effect.js.map


/***/ }),

/***/ "./node_modules/@tanstack/store/dist/esm/index.js":
/*!********************************************************!*\
  !*** ./node_modules/@tanstack/store/dist/esm/index.js ***!
  \********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Derived: () => (/* reexport safe */ _derived_js__WEBPACK_IMPORTED_MODULE_0__.Derived),
/* harmony export */   Effect: () => (/* reexport safe */ _effect_js__WEBPACK_IMPORTED_MODULE_1__.Effect),
/* harmony export */   Store: () => (/* reexport safe */ _store_js__WEBPACK_IMPORTED_MODULE_2__.Store),
/* harmony export */   __depsThatHaveWrittenThisTick: () => (/* reexport safe */ _scheduler_js__WEBPACK_IMPORTED_MODULE_4__.__depsThatHaveWrittenThisTick),
/* harmony export */   __derivedToStore: () => (/* reexport safe */ _scheduler_js__WEBPACK_IMPORTED_MODULE_4__.__derivedToStore),
/* harmony export */   __flush: () => (/* reexport safe */ _scheduler_js__WEBPACK_IMPORTED_MODULE_4__.__flush),
/* harmony export */   __storeToDerived: () => (/* reexport safe */ _scheduler_js__WEBPACK_IMPORTED_MODULE_4__.__storeToDerived),
/* harmony export */   batch: () => (/* reexport safe */ _scheduler_js__WEBPACK_IMPORTED_MODULE_4__.batch),
/* harmony export */   isUpdaterFunction: () => (/* reexport safe */ _types_js__WEBPACK_IMPORTED_MODULE_3__.isUpdaterFunction)
/* harmony export */ });
/* harmony import */ var _derived_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./derived.js */ "./node_modules/@tanstack/store/dist/esm/derived.js");
/* harmony import */ var _effect_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./effect.js */ "./node_modules/@tanstack/store/dist/esm/effect.js");
/* harmony import */ var _store_js__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! ./store.js */ "./node_modules/@tanstack/store/dist/esm/store.js");
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! ./types.js */ "./node_modules/@tanstack/store/dist/esm/types.js");
/* harmony import */ var _scheduler_js__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! ./scheduler.js */ "./node_modules/@tanstack/store/dist/esm/scheduler.js");






//# sourceMappingURL=index.js.map


/***/ }),

/***/ "./node_modules/@tanstack/store/dist/esm/scheduler.js":
/*!************************************************************!*\
  !*** ./node_modules/@tanstack/store/dist/esm/scheduler.js ***!
  \************************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   __depsThatHaveWrittenThisTick: () => (/* binding */ __depsThatHaveWrittenThisTick),
/* harmony export */   __derivedToStore: () => (/* binding */ __derivedToStore),
/* harmony export */   __flush: () => (/* binding */ __flush),
/* harmony export */   __storeToDerived: () => (/* binding */ __storeToDerived),
/* harmony export */   batch: () => (/* binding */ batch)
/* harmony export */ });
const __storeToDerived = /* @__PURE__ */ new WeakMap();
const __derivedToStore = /* @__PURE__ */ new WeakMap();
const __depsThatHaveWrittenThisTick = {
  current: []
};
let __isFlushing = false;
let __batchDepth = 0;
const __pendingUpdates = /* @__PURE__ */ new Set();
const __initialBatchValues = /* @__PURE__ */ new Map();
function __flush_internals(relatedVals) {
  for (const derived of relatedVals) {
    if (__depsThatHaveWrittenThisTick.current.includes(derived)) {
      continue;
    }
    __depsThatHaveWrittenThisTick.current.push(derived);
    derived.recompute();
    const stores = __derivedToStore.get(derived);
    if (stores) {
      for (const store of stores) {
        const relatedLinkedDerivedVals = __storeToDerived.get(store);
        if (!(relatedLinkedDerivedVals == null ? void 0 : relatedLinkedDerivedVals.length)) continue;
        __flush_internals(relatedLinkedDerivedVals);
      }
    }
  }
}
function __notifyListeners(store) {
  const value = {
    prevVal: store.prevState,
    currentVal: store.state
  };
  for (const listener of store.listeners) {
    listener(value);
  }
}
function __notifyDerivedListeners(derived) {
  const value = {
    prevVal: derived.prevState,
    currentVal: derived.state
  };
  for (const listener of derived.listeners) {
    listener(value);
  }
}
function __flush(store) {
  if (__batchDepth > 0 && !__initialBatchValues.has(store)) {
    __initialBatchValues.set(store, store.prevState);
  }
  __pendingUpdates.add(store);
  if (__batchDepth > 0) return;
  if (__isFlushing) return;
  try {
    __isFlushing = true;
    while (__pendingUpdates.size > 0) {
      const stores = Array.from(__pendingUpdates);
      __pendingUpdates.clear();
      for (const store2 of stores) {
        const prevState = __initialBatchValues.get(store2) ?? store2.prevState;
        store2.prevState = prevState;
        __notifyListeners(store2);
      }
      for (const store2 of stores) {
        const derivedVals = __storeToDerived.get(store2);
        if (!derivedVals) continue;
        __depsThatHaveWrittenThisTick.current.push(store2);
        __flush_internals(derivedVals);
      }
      for (const store2 of stores) {
        const derivedVals = __storeToDerived.get(store2);
        if (!derivedVals) continue;
        for (const derived of derivedVals) {
          __notifyDerivedListeners(derived);
        }
      }
    }
  } finally {
    __isFlushing = false;
    __depsThatHaveWrittenThisTick.current = [];
    __initialBatchValues.clear();
  }
}
function batch(fn) {
  __batchDepth++;
  try {
    fn();
  } finally {
    __batchDepth--;
    if (__batchDepth === 0) {
      const pendingUpdateToFlush = __pendingUpdates.values().next().value;
      if (pendingUpdateToFlush) {
        __flush(pendingUpdateToFlush);
      }
    }
  }
}

//# sourceMappingURL=scheduler.js.map


/***/ }),

/***/ "./node_modules/@tanstack/store/dist/esm/store.js":
/*!********************************************************!*\
  !*** ./node_modules/@tanstack/store/dist/esm/store.js ***!
  \********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   Store: () => (/* binding */ Store)
/* harmony export */ });
/* harmony import */ var _scheduler_js__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./scheduler.js */ "./node_modules/@tanstack/store/dist/esm/scheduler.js");
/* harmony import */ var _types_js__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! ./types.js */ "./node_modules/@tanstack/store/dist/esm/types.js");


class Store {
  constructor(initialState, options) {
    this.listeners = /* @__PURE__ */ new Set();
    this.subscribe = (listener) => {
      var _a, _b;
      this.listeners.add(listener);
      const unsub = (_b = (_a = this.options) == null ? void 0 : _a.onSubscribe) == null ? void 0 : _b.call(_a, listener, this);
      return () => {
        this.listeners.delete(listener);
        unsub == null ? void 0 : unsub();
      };
    };
    this.prevState = initialState;
    this.state = initialState;
    this.options = options;
  }
  setState(updater) {
    var _a, _b, _c;
    this.prevState = this.state;
    if ((_a = this.options) == null ? void 0 : _a.updateFn) {
      this.state = this.options.updateFn(this.prevState)(updater);
    } else {
      if ((0,_types_js__WEBPACK_IMPORTED_MODULE_1__.isUpdaterFunction)(updater)) {
        this.state = updater(this.prevState);
      } else {
        this.state = updater;
      }
    }
    (_c = (_b = this.options) == null ? void 0 : _b.onUpdate) == null ? void 0 : _c.call(_b);
    (0,_scheduler_js__WEBPACK_IMPORTED_MODULE_0__.__flush)(this);
  }
}

//# sourceMappingURL=store.js.map


/***/ }),

/***/ "./node_modules/@tanstack/store/dist/esm/types.js":
/*!********************************************************!*\
  !*** ./node_modules/@tanstack/store/dist/esm/types.js ***!
  \********************************************************/
/***/ ((__unused_webpack___webpack_module__, __webpack_exports__, __webpack_require__) => {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   isUpdaterFunction: () => (/* binding */ isUpdaterFunction)
/* harmony export */ });
function isUpdaterFunction(updater) {
  return typeof updater === "function";
}

//# sourceMappingURL=types.js.map


/***/ })

}]);
//# sourceMappingURL=tanstack-router.js.map