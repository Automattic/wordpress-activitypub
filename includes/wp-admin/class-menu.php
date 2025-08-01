<?php
/**
 * Menu file.
 *
 * @package Activitypub
 */

namespace Activitypub\WP_Admin;

use function Activitypub\user_can_activitypub;

/**
 * ActivityPub Menu Class.
 */
class Menu {

	/**
	 * Add admin menu entry.
	 */
	public static function admin_menu() {
		$settings_page = \add_options_page(
			\_x( 'Welcome', 'page title', 'activitypub' ),
			'ActivityPub',
			'manage_options',
			'activitypub',
			array( Settings::class, 'settings_page' )
		);

		\add_action( 'load-' . $settings_page, array( Settings::class, 'add_settings_help_tab' ) );
		\add_action( 'load-users.php', array( Settings::class, 'add_users_help_tab' ) );
		\add_action( 'load-' . $settings_page, array( Admin::class, 'add_settings_list_tables' ) );
		\add_action( 'load-' . $settings_page, array( Screen_Options::class, 'add_settings_list_options' ) );

		// User has to be able to publish posts.
		if ( user_can_activitypub( \get_current_user_id() ) ) {
			$followers_list_page = \add_users_page(
				\__( 'Followers ⁂', 'activitypub' ),
				\__( 'Followers ⁂', 'activitypub' ),
				'activitypub',
				'activitypub-followers-list',
				array( Admin::class, 'followers_list_page' )
			);

			\add_action( 'load-' . $followers_list_page, array( Admin::class, 'add_followers_list_table' ) );
			\add_action( 'load-' . $followers_list_page, array( Screen_Options::class, 'add_followers_list_options' ) );

			if ( '1' === \get_option( 'activitypub_following_ui', '0' ) ) {
				$following_list_page = \add_users_page(
					\__( 'Following ⁂', 'activitypub' ),
					\__( 'Following ⁂', 'activitypub' ),
					'activitypub',
					'activitypub-following-list',
					array( Admin::class, 'following_list_page' )
				);

				\add_action( 'load-' . $following_list_page, array( Admin::class, 'add_following_list_table' ) );
				\add_action( 'load-' . $following_list_page, array( Settings::class, 'add_following_help_tab' ) );
				\add_action( 'load-' . $following_list_page, array( Screen_Options::class, 'add_following_list_options' ) );
			}

			// Only show blocked actors page if user has blocked actors.
			$blocked_actors_list_page = \add_users_page(
				\__( 'Blocked Actors ⁂', 'activitypub' ),
				\__( 'Blocked Actors ⁂', 'activitypub' ),
				'activitypub',
				'activitypub-blocked-actors-list',
				array( Admin::class, 'blocked_actors_list_page' )
			);

			\add_action( 'load-' . $blocked_actors_list_page, array( Admin::class, 'add_blocked_actors_list_table' ) );
			\add_action( 'load-' . $blocked_actors_list_page, array( Screen_Options::class, 'add_blocked_actors_list_options' ) );

			\add_users_page(
				\__( 'Extra Fields ⁂', 'activitypub' ),
				\__( 'Extra Fields ⁂', 'activitypub' ),
				'activitypub',
				\esc_url( \admin_url( '/edit.php?post_type=ap_extrafield' ) )
			);
		}
	}
}
