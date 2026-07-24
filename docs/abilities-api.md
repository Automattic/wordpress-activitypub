# ActivityPub Abilities API

The ActivityPub plugin exposes its functionality through the [WordPress Abilities API](https://make.wordpress.org/core/) (WordPress 6.9+). Abilities are discoverable, schema-validated, permissioned operations that other plugins, automation tools, WP-CLI, and the REST API can call by name — without depending on the plugin's internal classes.

## Contents

- [Why use abilities](#why-use-abilities)
- [Quick start](#quick-start)
- [Authorization](#authorization)
- [Categories](#categories)
- [Ability reference](#ability-reference)
  - [Discovery](#discovery)
  - [Social](#social)
- [REST API access](#rest-api-access)
- [Architecture](#architecture)
- [Extending the API](#extending-the-api)
- [Roadmap](#roadmap)

## Why use abilities

- **Stable contract.** Call `activitypub/get-followers` by name; the plugin can refactor `Collection\Followers` freely without breaking you.
- **Discoverable.** Abilities advertise their input/output JSON Schema, category, and annotations, so tooling can introspect them.
- **Permissioned.** Every ability runs through a permission callback before executing.
- **Multiple surfaces.** The same ability is reachable from PHP (`wp_run_ability()`), the REST API (`/wp-json/wp-abilities/v1/`), and any future Abilities-aware client.

## Quick start

Check availability before calling — the API only exists on WordPress 6.9+ and when the plugin is active:

```php
if ( function_exists( 'wp_get_ability' ) && wp_get_ability( 'activitypub/get-followers' ) ) {
	// The ability is registered and can be run.
}
```

Run an ability and handle the `WP_Error` result:

```php
$result = wp_run_ability(
	'activitypub/get-followers',
	array(
		'user_id'  => get_current_user_id(),
		'per_page' => 20,
		'page'     => 1,
	)
);

if ( ! is_wp_error( $result ) ) {
	foreach ( $result['followers'] as $follower ) {
		printf( "%s (%s)\n", $follower['name'], $follower['preferredUsername'] );
	}
	printf( "Total: %d\n", $result['total'] );
}
```

## Authorization

Every ability requires the current user to have the `activitypub` capability (`current_user_can( 'activitypub' )`). Some abilities apply additional rules:

| Rule | Applies to | Behavior |
|------|-----------|----------|
| **Ownership** | `get-followers`, `get-following`, `follow`, `unfollow` | Acting on another user's data requires `manage_options`, otherwise returns `403 activitypub_forbidden`. `user_id` defaults to the current user. |
| **Feature flag** | `follow`, `unfollow` | Requires the Following UI to be enabled (`activitypub_following_ui` option). Otherwise returns `403 activitypub_following_disabled`. |

Each ability also declares behavioral annotations (`readonly`, `destructive`, `idempotent`) so clients can reason about side effects before running them.

## Categories

| Slug | Label | Description |
|------|-------|-------------|
| `activitypub-discovery` | Discovery | Look up and discover remote actors in the Fediverse. |
| `activitypub-social` | Social | Manage followers, following, and social connections. |

## Ability reference

Every input is an object; `additionalProperties` is disallowed. All abilities return either the documented array or a `WP_Error`.

### Discovery

#### `activitypub/resolve-handle`

Resolve a WebFinger handle to its ActivityPub data. *Readonly · idempotent.*

| Input | Type | Required | Notes |
|-------|------|----------|-------|
| `handle` | string | yes | WebFinger handle, e.g. `user@example.com`. |

**Returns:** the WebFinger document (object). **Errors:** `WP_Error` if the handle cannot be resolved.

```php
$data = wp_run_ability( 'activitypub/resolve-handle', array( 'handle' => 'alice@example.com' ) );
```

#### `activitypub/get-actor`

Fetch profile information for a remote actor. *Readonly · idempotent.*

| Input | Type | Required | Notes |
|-------|------|----------|-------|
| `actor` | string | yes | Actor URL or WebFinger handle. |

**Returns:**

```jsonc
{
  "id": "https://example.com/users/alice",   // uri
  "type": "Person",
  "name": "Alice",
  "preferredUsername": "alice",
  "summary": "…",
  "inbox": "https://example.com/users/alice/inbox",
  "outbox": "https://example.com/users/alice/outbox",
  "followers": "https://example.com/users/alice/followers",
  "following": "https://example.com/users/alice/following",
  "icon": { /* … */ }
}
```

**Errors:** `WP_Error` if the actor cannot be fetched or parsed.

### Social

#### `activitypub/get-followers`

List followers for a local actor. *Readonly · idempotent.*

| Input | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `user_id` | integer | yes | — | The local actor's user ID. |
| `page` | integer | no | `1` | Page number. |
| `per_page` | integer | no | `20` | Results per page (capped at `100`). |

**Returns:** `{ "followers": Actor[], "total": integer }`, where each follower has `id`, `type`, `name`, `preferredUsername`, `followers`, `following`, `icon`. **Errors:** `400 activitypub_invalid_user_id`, `403 activitypub_forbidden` (another user's list without `manage_options`).

#### `activitypub/get-following`

List accounts a local actor follows. *Readonly · idempotent.*

| Input | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `user_id` | integer | yes | — | The local actor's user ID. |
| `page` | integer | no | `1` | Page number. |
| `per_page` | integer | no | `20` | Results per page (capped at `100`). |

**Returns:** `{ "following": Actor[], "total": integer }` (same `Actor` shape as above). **Errors:** `400 activitypub_invalid_user_id`, `403 activitypub_forbidden` (another user's list without `manage_options`).

#### `activitypub/follow`

Follow a remote actor. *Not readonly · not idempotent.* Requires the Following feature to be enabled.

| Input | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `actor` | string | yes | — | Actor URL or WebFinger handle to follow. |
| `user_id` | integer | no | current user | Local actor to act as (ownership rules apply). |

**Returns:** `{ "outbox_item_id": integer, "status": "pending" }`. **Errors:** `403 activitypub_following_disabled`, `403 activitypub_forbidden`, or a `WP_Error` from the follow request.

```php
$result = wp_run_ability( 'activitypub/follow', array( 'actor' => 'organizer@events.example.com' ) );
```

#### `activitypub/unfollow`

Unfollow a remote actor. *Not readonly · not idempotent.* Requires the Following feature to be enabled.

| Input | Type | Required | Default | Notes |
|-------|------|----------|---------|-------|
| `actor` | string | yes | — | Actor URL or WebFinger handle to unfollow. |
| `user_id` | integer | no | current user | Local actor to act as (ownership rules apply). |

**Returns:** `{ "success": true }`. **Errors:** `403 activitypub_following_disabled`, `403 activitypub_forbidden`, or a `WP_Error` from the unfollow request.

## REST API access

Abilities with `show_in_rest` are reachable under `/wp-json/wp-abilities/v1/`. Readonly abilities run via `GET`; others via `POST`.

```bash
# List categories.
curl -u admin:PASSWORD http://localhost:8888/wp-json/wp-abilities/v1/categories

# List Discovery abilities.
curl -u admin:PASSWORD 'http://localhost:8888/wp-json/wp-abilities/v1/abilities?category=activitypub-discovery'

# Run a readonly ability (GET).
curl -u admin:PASSWORD 'http://localhost:8888/wp-json/wp-abilities/v1/abilities/activitypub/get-actor/run?input[actor]=alice@example.com'

# Run a write ability (POST).
curl -u admin:PASSWORD -X POST -H 'Content-Type: application/json' \
  -d '{"input":{"actor":"alice@example.com"}}' \
  http://localhost:8888/wp-json/wp-abilities/v1/abilities/activitypub/follow/run
```

## Architecture

```
activitypub.php                      # Hooks Abilities::register_* into wp_abilities_api_*_init
includes/
├── class-abilities.php              # Category registration, orchestration, extensibility hooks
└── ability/
    ├── class-actor.php              # activitypub/get-actor
    ├── class-followers.php          # activitypub/get-followers
    ├── class-following.php          # activitypub/get-following, follow, unfollow
    └── class-webfinger.php          # activitypub/resolve-handle
```

Registration flow:

1. `activitypub.php` hooks `Abilities::register_categories()` into `wp_abilities_api_categories_init` and `Abilities::register_abilities()` into `wp_abilities_api_init`. These hooks only fire when the Abilities API is available, so no version check is needed.
2. `register_categories()` registers the categories, then fires `activitypub_register_ability_categories`.
3. `register_abilities()` calls each ability class's `register()`, then fires `activitypub_register_abilities`.

Ability classes are grouped by domain (`Webfinger`, `Actor`, `Followers`, `Following`), not one class per ability — a single class can register several abilities, even across categories.

## Extending the API

Third-party plugins register their own categories and abilities on the same hooks:

```php
add_action( 'activitypub_register_ability_categories', function () {
	wp_register_ability_category( 'my-plugin', array(
		'label'       => __( 'My Plugin', 'my-plugin' ),
		'description' => __( 'Custom abilities.', 'my-plugin' ),
	) );
} );

add_action( 'activitypub_register_abilities', function () {
	wp_register_ability( 'my-plugin/do-thing', array(
		'label'               => __( 'Do Thing', 'my-plugin' ),
		'description'         => __( 'Does the thing.', 'my-plugin' ),
		'category'            => 'my-plugin',
		'execute_callback'    => 'my_plugin_do_thing',
		'permission_callback' => '__return_true',
		'input_schema'        => array(
			'type'                 => 'object',
			'properties'           => array(
				'param' => array( 'type' => 'string' ),
			),
			'required'             => array( 'param' ),
			'additionalProperties' => false,
		),
		'output_schema'       => array( 'type' => 'object' ),
		'meta'                => array(
			'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
			'show_in_rest' => true,
		),
	) );
} );
```

## Roadmap

Abilities under consideration for future releases:

| Ability | Category | Description |
|---------|----------|-------------|
| `activitypub/publish-note` | Publish | Publish a Note to the Fediverse. |
| `activitypub/announce-post` | Publish | Boost/announce an existing post. |
| `activitypub/block-actor` | Moderation | Block a specific actor. |
| `activitypub/block-domain` | Moderation | Block an entire domain. |
| `activitypub/unblock` | Moderation | Unblock an actor or domain. |
| `activitypub/get-inbox` | Moderation | Get recent inbox activities. |
| `activitypub/retry-delivery` | Moderation | Retry failed activity delivery. |
