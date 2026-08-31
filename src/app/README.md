<!--
Canonical architecture guide for the ActivityPub admin app. This documents the
TARGET state we build toward, not necessarily today's code. Contributors and
agents: treat this as the source of truth for how new screens should be built.
-->

# ActivityPub Admin App

The admin app is the React single-page application that powers the **Social Web**
screen (`admin.php?page=activitypub-social-web`). This document describes the
architecture we build toward — the final picture, not a migration log.

## Overview

The app is a WordPress **7.0+ Script-Module** single-page application. It boots
through core's `@wordpress/boot` package (`initSinglePage`), declares its screens
in a **route registry**, renders lists with **DataViews**, and reads/writes data
through **`@wordpress/core-data`**.

> **Scope:** this app is the **Social Web** screen only — feeds and actor lists
> (followers, following, blocked). It does **not** own the plugin's settings.
> Those remain on the classic `Settings → ActivityPub` pages and are out of scope
> for this app.

The app is **gated to WordPress 7.0+**. On 7.0 and later the boot app loads; on
6.5–6.x the same admin page renders the classic server-side screens instead, so
the plugin keeps working without the modern stack. See
[Compatibility gate](#compatibility-gate).

This mirrors how WordPress core itself builds new admin pages (for example
`options-connectors.php` and the Font Library). Aligning with core means we
inherit its routing, data preloading, and module loading rather than maintaining
our own.

## Bootstrap

In the target architecture the PHP side (`includes/wp-admin/class-app.php`) will
do four things, all behind the [WP 7.0 gate](#compatibility-gate):

1. **Render one root node.** The page outputs a single container plus the small
   critical-CSS block that hides the legacy admin chrome:

   ```php
   <div id="activitypub-app-root" class="boot-layout-container"></div>
   ```

2. **Preload REST data.** Use `rest_preload_api_request` to warm the site entity
   and settings, then install the preloading middleware so the first render needs
   no round-trips:

   ```php
   wp_add_inline_script(
       'wp-api-fetch',
       sprintf( 'wp.apiFetch.use( wp.apiFetch.createPreloadingMiddleware( %s ) );', wp_json_encode( $preload_data ) ),
       'after'
   );
   ```

3. **Boot the app.** Load core's boot prerequisites
   (`wp-includes/js/dist/script-modules/boot/index.min.asset.php`) as a classic
   script so all module globals are present, then pass the mount ID and route
   registry to the loader module through script-module data:

   ```php
   add_filter(
       'script_module_data_@activitypub/app',
       static function ( $data ) use ( $routes ) {
           $data['mountId'] = 'activitypub-app-root';
           $data['routes']  = $routes;

           return $data;
       }
   );
   ```

4. **Register the route modules.** For each registered route, register its
   `content` and `route` script modules, then register the app loader module with
   static dependencies on `@wordpress/boot` plus every `route_module`, and dynamic
   dependencies on every `content_module`. Enqueue it with
   `wp_enqueue_script_module()`. The loader statically imports
   `@wordpress/boot`, reads its script-module data from
   `wp-script-module-data-@activitypub/app`, and calls `initSinglePage()`.

`initSinglePage` owns the React root, routing, and rendering. The app does **not**
call `createRoot` itself and does **not** bundle a router — those concerns belong
to core's boot module.

## Route registry

A screen is a **pair of script modules** registered against a path:

- **`route` module** — small, statically imported, runs early. Exports a `route`
  object that supplies the document title and warms data before the screen paints:

  ```js
  import { resolveSelect } from '@wordpress/data';
  import { store as coreStore } from '@wordpress/core-data';
  import { __ } from '@wordpress/i18n';

  export const route = {
      title: () => __( 'Followers', 'activitypub' ),
      loader: async () => {
          await resolveSelect( coreStore ).getEntityRecords( 'postType', 'ap_actor', { per_page: 20 } );
      },
  };
  ```

- **`content` module** — the screen component, dynamically imported so it is
  code-split and only loaded when its route is visited.

Routes are registered in PHP (the registry that `initSinglePage` receives), one
entry per path with its `content_module` and `route_module` handles. This
replaces any in-app router.

## Screens

### Lists — DataViews

Tabular screens (feed, followers, following, blocked actors) use
[`DataViews`](https://www.npmjs.com/package/@wordpress/dataviews). Field
definitions live in `src/app/components/fields/`; the screen wires fields, the
view state, and actions (including bulk actions) to the data:

```jsx
<DataViews
    data={ records }
    fields={ fields }
    view={ view }
    onChangeView={ setView }
    actions={ actions }
    paginationInfo={ { totalItems, totalPages } }
/>
```

DataViews is the replacement for PHP `WP_List_Table`. No list screen should ship
a server-rendered table.

## Data layer

- **`@wordpress/core-data` is the default.** Read and write entities with
  `useEntityRecord` / `useEntityRecords` against the REST API. The plugin's custom
  post types `ap_post` (feed) and `ap_actor` (followers/following) and their
  taxonomies (`ap_object_type`, `ap_tag`) are the entities behind the lists.
- **Those routes are gated and self-scoping.** They are served by the plugin's own
  controllers (`includes/rest/`), not core's, so they require the `activitypub`
  capability and return nothing to logged-out visitors. Collections are scoped to
  the current user automatically: `user_id` on `ap_post` and `follower_of` on
  `ap_actor` are clamped unless the caller can `list_users`, so a screen never has
  to pass the viewer's own ID to stay within its own data. A screen that needs a
  relationship these routes do not expose yet — the site's *following* list lives
  in `_activitypub_followed_by`, which is not registered for REST — needs that
  wired up first.
- **The `activitypub/app` `@wordpress/data` store is for UI/app state only** —
  things with no REST representation, such as the active actor selection (which is
  persisted through `@wordpress/preferences`). Do not duplicate entity data in it.
- **Preload** the entities the first screen needs (site fields + `/wp/v2/settings`
  OPTIONS) so the initial paint is request-free.

## Components & layout

- Build UI from `@wordpress/components` primitives (`Panel`, `Card`, the
  `*Control` family, `Button`, `Spinner`, `Navigator` for in-app navigation).
- The app shell provides the layout (the three-panel sidebar / stage / inspector
  arrangement) and notifications (`@wordpress/notices` snackbars).
- Use `@wordpress/element` and `@wordpress/i18n`; never import React or build
  translation strings by hand.

## Directory layout

```
src/app/
├── index.tsx                 # App shell wiring (providers, layout) — NOT a createRoot entry
├── store/                    # activitypub/app @wordpress/data store (UI/app state only)
├── contexts/                 # SettingsProvider, ObjectTypeProvider, …
├── hooks/                    # core-data wrappers (use-feed, use-followers, …)
├── components/
│   ├── layout/               # three-panel shell
│   ├── fields/               # DataViews field definitions (shared across lists)
│   └── …                     # sidebar, actor-switcher, avatar, panel, …
└── routes/
    └── <screen>/
        ├── route.ts          # route module: title() + loader()
        └── content.tsx       # content module: the screen component (DataViews)
```

Every screen is a `route` + `content` module pair under `routes/<screen>/`.

## Conventions for a new screen

1. Create `src/app/routes/<screen>/route.ts` exporting `route = { title, loader }`;
   warm the entities the screen needs in `loader` via `resolveSelect`.
2. Create `src/app/routes/<screen>/content.tsx` as the default-exported component.
3. Define the list's fields in `src/app/components/fields/` and render `DataViews`.
4. Read/write data with `useEntityRecord` / `useEntityRecords`; only reach for the
   `activitypub/app` store for state that has no REST representation.
5. Register the route's `content_module` and `route_module` in the PHP route
   registry so `initSinglePage` picks it up.
6. Add user-facing strings through `@wordpress/i18n` with the `activitypub` text
   domain.
7. Render REST `rendered` fields as-is. Never decode them first — see below.

## Rendering remote content

Everything the reader displays is authored by a remote actor, so treat every
`rendered` field as hostile input that the server has already made *inert for
script execution*. That is a narrower guarantee than "safe": `content.rendered`
is `the_content` output, so `do_shortcode()` and `do_blocks()` have run after
kses, and kses does not strip HTML comments. It is enough to render as HTML; it
is not a promise that kses vetted every byte.

**Never run `decodeEntities()` (or any other unescaping pass) on a value that
ends up in `dangerouslySetInnerHTML`.** An entity-encoded REST field is
*already* the safe representation: remote content is stored through
`Sanitize::content()` → `Sanitize::clean_remote_html()`, which deliberately leaves a
payload like `&lt;iframe srcdoc="…"&gt;` as inert text. Decoding it on the client
turns it back into live markup and undoes every server-side escaping decision at
once.

`Sanitize::clean_remote_html()` is `wp_kses_post()` minus the `style` attribute.
See `includes/class-sanitize.php` for why.

`safeHTML()` from `@wordpress/dom` will not save you: it removes `<script>`
elements and `on*` attributes and nothing else, so `<iframe srcdoc>`, `<object>`,
`<embed>`, `<form action>` and `javascript:` URLs all pass straight through. It
is defence in depth, not a sanitiser.

```tsx
// Wrong: decoding first revives markup kses stored as text.
const html = safeHTML( decodeEntities( item.content?.rendered || '' ) );

// Right: the stored value is already safe, and entities render as characters.
const html = safeHTML( item.content?.rendered || '' );
```

`decodeEntities()` is fine on values rendered as a **React child**, where React
escapes them as text (`{ decodeEntities( actor.name ) }`) — the rule is about
values that reach `innerHTML`.

When writing tests that cover an `innerHTML` path, do not mock `decodeEntities`
or `safeHTML`. Stubbing `decodeEntities` to the identity function makes such a
test pass whether or not the behaviour is correct. Mocking them in tests for
React-child rendering is fine.

## Compatibility gate

The boot stack requires WordPress 7.0. `class-app.php` exposes a single
`App::is_supported()` gate that the menu and admin bar consult: the Social Web
page is only registered (and the app booted) when it returns `true`, so on
WordPress < 7.0 the page is not added at all — there is no broken or empty
screen. The classic actor lists (followers, following, blocked) remain available
under **Users** independently of this gate.

`is_supported()` detects the boot stack by **capability**, not by
`version_compare()`:

```php
public static function is_supported() {
    return function_exists( 'wp_register_script_module' ) && is_array( self::get_boot_asset() );
}
```

It checks for the Script Modules API plus core's `@wordpress/boot` module asset
(`wp-includes/js/dist/script-modules/boot/index.min.asset.php`), which ships in
7.0. This is deliberate: a `is_wp_version_compatible( '7.0' )` check would lock
out pre-release builds — `7.0-alpha`/`7.0-RC1` fail `version_compare( …, '7.0',
'>=' )` yet already ship the boot module — so early adopters would wrongly lose
the app.

The boot app is still an early opt-in: `includes/wp-admin/class-menu.php` gates
it behind the `activitypub_reader_ui` option **and** `App::is_supported()`. The
opt-in is the bridge until the stack is stable and on by default; when the
plugin's minimum supported version reaches 7.0 the gate (and the opt-in) can be
removed entirely.
