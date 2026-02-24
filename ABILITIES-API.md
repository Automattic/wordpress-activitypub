# ActivityPub Abilities API

WordPress Abilities API (WP 6.9+) integration for the ActivityPub plugin. Exposes ActivityPub functionality as discoverable, permissioned abilities for automation, workflows, and plugin interoperability.

## Why?

- **Plugin interoperability**: Other plugins can discover and call abilities in a consistent way
- **Automation & workflows**: WP-Cron, WP-CLI, CI/CD, low-code tools can call abilities safely
- **Self-documenting endpoints**: JSON Schema validation, easier tooling/testing
- **REST exposure**: Standard `/wp-json/wp-abilities/v1/` endpoints

## Categories

| Slug | Label | Description |
|------|-------|-------------|
| `activitypub-discovery` | Discovery | Look up and discover remote actors in the Fediverse. |
| `activitypub-social` | Social | Manage followers, following, and social connections. |

## Implemented Abilities

### Discovery (`activitypub-discovery`)

| Ability | Description | Class |
|---------|-------------|-------|
| `activitypub/resolve-handle` | Look up WebFinger data for a handle. | `Ability\Webfinger` |
| `activitypub/get-actor` | Fetch profile information for a remote actor. | `Ability\Actor` |

### Social (`activitypub-social`)

| Ability | Description | Class |
|---------|-------------|-------|
| `activitypub/get-followers` | List followers for a local actor. | `Ability\Followers` |
| `activitypub/get-following` | List accounts being followed by a local actor. | `Ability\Following` |
| `activitypub/follow` | Follow a remote actor. | `Ability\Following` |
| `activitypub/unfollow` | Unfollow a remote actor. | `Ability\Following` |

### Planned

| Ability | Category | Description |
|---------|----------|-------------|
| `activitypub/publish-note` | publish | Publish a Note to the Fediverse. |
| `activitypub/announce-post` | publish | Boost/announce an existing post. |
| `activitypub/block-actor` | moderation | Block a specific actor. |
| `activitypub/block-domain` | moderation | Block an entire domain. |
| `activitypub/unblock` | moderation | Unblock actor or domain. |
| `activitypub/get-inbox` | moderation | Get recent inbox activities. |
| `activitypub/retry-delivery` | moderation | Retry failed activity delivery. |

## Architecture

### File Structure

```
activitypub.php                      # Hooks into wp_abilities_api_*_init
includes/
├── class-abilities.php              # Category registration + orchestration + extensibility hooks
├── ability/
│   ├── class-actor.php              # activitypub/get-actor
│   ├── class-followers.php          # activitypub/get-followers
│   ├── class-following.php          # activitypub/get-following, follow, unfollow
│   └── class-webfinger.php          # activitypub/resolve-handle
```

### Registration Flow

1. `activitypub.php` hooks `Abilities::register_categories` into `wp_abilities_api_categories_init`
2. `activitypub.php` hooks `Abilities::register_abilities` into `wp_abilities_api_init`
3. `Abilities::register_categories()` registers categories, then fires `activitypub_register_ability_categories`
4. `Abilities::register_abilities()` calls each ability class's `register()`, then fires `activitypub_register_abilities`

Ability classes are grouped by domain (e.g. `Webfinger`, `Actor`), not one-per-ability. A single class can register abilities across multiple categories.

### Ability Class Template

```php
<?php
namespace Activitypub\Ability;

class Example {
	public static function register() {
		\wp_register_ability(
			'activitypub/example',
			array(
				'label'               => \__( 'Example Ability', 'activitypub' ),
				'description'         => \__( 'Does something useful.', 'activitypub' ),
				'category'            => 'activitypub-discovery',
				'execute_callback'    => array( self::class, 'execute' ),
				'permission_callback' => array( self::class, 'permission_callback' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'param' => array(
							'type'        => 'string',
							'description' => \__( 'A required parameter.', 'activitypub' ),
						),
					),
					'required'             => array( 'param' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'object',
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	public static function permission_callback( $input = null ) {
		return \current_user_can( 'activitypub' );
	}

	public static function execute( $input ) {
		// Implementation here.
		return array( 'result' => 'value' );
	}
}
```

### Extensibility

Third-party plugins can register additional categories and abilities:

```php
\add_action( 'activitypub_register_ability_categories', function () {
	\wp_register_ability_category( 'my-plugin-category', array( ... ) );
} );

\add_action( 'activitypub_register_abilities', function () {
	\wp_register_ability( 'my-plugin/my-ability', array( ... ) );
} );
```

## Existing Services

- `Webfinger::get_data()` — WebFinger lookups
- `Collection\Remote_Actors::fetch_by_various()`, `::get_actor()` — Remote actor profiles
- `Collection\Followers::query()` — Follower lists
- `Collection\Following::query()` — Following lists
- `Collection\Outbox::reschedule()` — Retry delivery
- `Moderation` class — Block/unblock logic

## Testing

```bash
# List categories
curl -u admin:PASSWORD http://localhost:8888/wp-json/wp-abilities/v1/categories

# List discovery abilities
curl -u admin:PASSWORD 'http://localhost:8888/wp-json/wp-abilities/v1/abilities?category=activitypub-discovery'

# Resolve a handle (GET — readonly abilities require GET)
curl -u admin:PASSWORD 'http://localhost:8888/wp-json/wp-abilities/v1/abilities/activitypub/resolve-handle/run?input[handle]=user@example.com'

# Get actor
curl -u admin:PASSWORD 'http://localhost:8888/wp-json/wp-abilities/v1/abilities/activitypub/get-actor/run?input[actor]=user@example.com'
```
