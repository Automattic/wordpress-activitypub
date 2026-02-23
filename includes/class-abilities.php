<?php
/**
 * Abilities API integration.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub;

use Activitypub\Ability\Actor;
use Activitypub\Ability\Followers;
use Activitypub\Ability\Following;
use Activitypub\Ability\Webfinger;

/**
 * Abilities class.
 *
 * Handles registration of ActivityPub ability categories and abilities
 * for the WordPress Abilities API (WP 6.9+).
 *
 * @since unreleased
 */
class Abilities {

	/**
	 * Register ActivityPub ability categories.
	 *
	 * Hooked into `wp_abilities_api_categories_init`.
	 *
	 * @since unreleased
	 */
	public static function register_categories() {
		\wp_register_ability_category(
			'activitypub-discovery',
			array(
				'label'       => \__( 'Discovery', 'activitypub' ),
				'description' => \__( 'Look up and discover remote actors in the Fediverse.', 'activitypub' ),
			)
		);

		\wp_register_ability_category(
			'activitypub-social',
			array(
				'label'       => \__( 'Social', 'activitypub' ),
				'description' => \__( 'Manage followers, following, and social connections.', 'activitypub' ),
			)
		);

		\wp_register_ability_category(
			'activitypub-publish',
			array(
				'label'       => \__( 'Publish', 'activitypub' ),
				'description' => \__( 'Publish and share content to the Fediverse.', 'activitypub' ),
			)
		);

		\wp_register_ability_category(
			'activitypub-moderation',
			array(
				'label'       => \__( 'Moderation', 'activitypub' ),
				'description' => \__( 'Moderate actors, domains, and activity delivery.', 'activitypub' ),
			)
		);

		/**
		 * Fires after built-in ability categories are registered.
		 *
		 * Use this hook to register additional ability categories.
		 *
		 * @since unreleased
		 */
		\do_action( 'activitypub_register_ability_categories' );
	}

	/**
	 * Register all ActivityPub abilities.
	 *
	 * Hooked into `wp_abilities_api_init`.
	 *
	 * @since unreleased
	 */
	public static function register_abilities() {
		Actor::register();
		Followers::register();
		Following::register();
		Webfinger::register();

		/**
		 * Fires after built-in abilities are registered.
		 *
		 * Use this hook to register additional abilities.
		 *
		 * @since unreleased
		 */
		\do_action( 'activitypub_register_abilities' );
	}
}
