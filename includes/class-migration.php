<?php
namespace Activitypub;

use Activitypub\Activitypub;
use Activitypub\Model\Blog;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Migration Class
 *
 * @author Matthias Pfefferle
 */
class Migration {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_migrate', array( self::class, 'async_migration' ) );

		self::maybe_migrate();
	}

	/**
	 * Get the target version.
	 *
	 * This is the version that the database structure will be updated to.
	 * It is the same as the plugin version.
	 *
	 * @return string The target version.
	 */
	public static function get_target_version() {
		return get_plugin_version();
	}

	/**
	 * The current version of the database structure.
	 *
	 * @return string The current version.
	 */
	public static function get_version() {
		return get_option( 'activitypub_db_version', 0 );
	}

	/**
	 * Locks the database migration process to prevent simultaneous migrations.
	 *
	 * @return void
	 */
	public static function lock() {
		\update_option( 'activitypub_migration_lock', \time() );
	}

	/**
	 * Unlocks the database migration process.
	 *
	 * @return void
	 */
	public static function unlock() {
		\delete_option( 'activitypub_migration_lock' );
	}

	/**
	 * Whether the database migration process is locked.
	 *
	 * @return boolean
	 */
	public static function is_locked() {
		$lock = \get_option( 'activitypub_migration_lock' );

		if ( ! $lock ) {
			return false;
		}

		$lock = (int) $lock;

		if ( $lock < \time() - 1800 ) {
			self::unlock();
			return false;
		}

		return true;
	}

	/**
	 * Whether the database structure is up to date.
	 *
	 * @return bool True if the database structure is up to date, false otherwise.
	 */
	public static function is_latest_version() {
		return (bool) version_compare(
			self::get_version(),
			self::get_target_version(),
			'=='
		);
	}

	/**
	 * Updates the database structure if necessary.
	 */
	public static function maybe_migrate() {
		if ( self::is_latest_version() ) {
			return;
		}

		if ( self::is_locked() ) {
			return;
		}

		self::lock();

		$version_from_db = self::get_version();

		// check for inital migration
		if ( ! $version_from_db ) {
			self::add_default_settings();
			$version_from_db = self::get_target_version();
		}

		// schedule the async migration
		if ( ! \wp_next_scheduled( 'activitypub_migrate', $version_from_db ) ) {
			\wp_schedule_single_event( \time(), 'activitypub_migrate', array( $version_from_db ) );
		}
		if ( version_compare( $version_from_db, '0.17.0', '<' ) ) {
			self::migrate_from_0_16();
		}
		if ( version_compare( $version_from_db, '1.3.0', '<' ) ) {
			self::migrate_from_1_2_0();
		}
		if ( version_compare( $version_from_db, '2.1.0', '<' ) ) {
			self::migrate_from_2_0_0();
		}
		if ( version_compare( $version_from_db, '2.3.0', '<' ) ) {
			self::migrate_from_2_2_0();
		}
		if ( version_compare( $version_from_db, '3.0.0', '<' ) ) {
			self::migrate_from_2_6_0();
		}

		update_option( 'activitypub_db_version', self::get_target_version() );

		self::unlock();
	}

	/**
	 * Asynchronously migrates the database structure.
	 *
	 * @param string $version_from_db The version from which to migrate.
	 */
	public static function async_migration( $version_from_db ) {
		if ( version_compare( $version_from_db, '1.0.0', '<' ) ) {
			self::migrate_from_0_17();
		}
	}

	/**
	 * Updates the custom template to use shortcodes instead of the deprecated templates.
	 *
	 * @return void
	 */
	private static function migrate_from_0_16() {
		// Get the custom template.
		$old_content = \get_option( 'activitypub_custom_post_content', ACTIVITYPUB_CUSTOM_POST_CONTENT );

		// If the old content exists but is a blank string, we're going to need a flag to updated it even
		// after setting it to the default contents.
		$need_update = false;

		// If the old contents is blank, use the defaults.
		if ( '' === $old_content ) {
			$old_content = ACTIVITYPUB_CUSTOM_POST_CONTENT;
			$need_update = true;
		}

		// Set the new content to be the old content.
		$content = $old_content;

		// Convert old templates to shortcodes.
		$content = \str_replace( '%title%', '[ap_title]', $content );
		$content = \str_replace( '%excerpt%', '[ap_excerpt]', $content );
		$content = \str_replace( '%content%', '[ap_content]', $content );
		$content = \str_replace( '%permalink%', '[ap_permalink type="html"]', $content );
		$content = \str_replace( '%shortlink%', '[ap_shortlink type="html"]', $content );
		$content = \str_replace( '%hashtags%', '[ap_hashtags]', $content );
		$content = \str_replace( '%tags%', '[ap_hashtags]', $content );

		// Store the new template if required.
		if ( $content !== $old_content || $need_update ) {
			\update_option( 'activitypub_custom_post_content', $content );
		}
	}

	/**
	 * Updates the DB-schema of the followers-list
	 *
	 * @return void
	 */
	public static function migrate_from_0_17() {
		// migrate followers
		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			$followers = get_user_meta( $user_id, 'activitypub_followers', true );

			if ( $followers ) {
				foreach ( $followers as $actor ) {
					Followers::add_follower( $user_id, $actor );
				}
			}
		}

		Activitypub::flush_rewrite_rules();
	}

	/**
	 * Clear the cache after updating to 1.3.0
	 *
	 * @return void
	 */
	private static function migrate_from_1_2_0() {
		$user_ids = \get_users(
			array(
				'fields'         => 'ID',
				'capability__in' => array( 'publish_posts' ),
			)
		);

		foreach ( $user_ids as $user_id ) {
			wp_cache_delete( sprintf( Followers::CACHE_KEY_INBOXES, $user_id ), 'activitypub' );
		}
	}

	/**
	 * Unschedule Hooks after updating to 2.0.0
	 *
	 * @return void
	 */
	private static function migrate_from_2_0_0() {
		wp_clear_scheduled_hook( 'activitypub_send_post_activity' );
		wp_clear_scheduled_hook( 'activitypub_send_update_activity' );
		wp_clear_scheduled_hook( 'activitypub_send_delete_activity' );

		wp_unschedule_hook( 'activitypub_send_post_activity' );
		wp_unschedule_hook( 'activitypub_send_update_activity' );
		wp_unschedule_hook( 'activitypub_send_delete_activity' );

		$object_type = \get_option( 'activitypub_object_type', ACTIVITYPUB_DEFAULT_OBJECT_TYPE );
		if ( 'article' === $object_type ) {
			\update_option( 'activitypub_object_type', 'wordpress-post-format' );
		}
	}

	/**
	 * Add the ActivityPub capability to all users that can publish posts
	 * Delete old meta to store followers
	 *
	 * @return void
	 */
	private static function migrate_from_2_2_0() {
		// add the ActivityPub capability to all users that can publish posts
		self::add_activitypub_capability();
	}

	/**
	 * Rename DB fields
	 *
	 * @return void
	 */
	private static function migrate_from_2_6_0() {
		wp_cache_flush();

		self::update_usermeta_key( 'activitypub_user_description', 'activitypub_description' );

		self::update_options_key( 'activitypub_blog_user_description', 'activitypub_blog_description' );
		self::update_options_key( 'activitypub_blog_user_identifier', 'activitypub_blog_identifier' );
	}

	/**
	 * Set the defaults needed for the plugin to work
	 *
	 * * Add the ActivityPub capability to all users that can publish posts
	 *
	 * @return void
	 */
	public static function add_default_settings() {
		self::add_activitypub_capability();
	}

	/**
	 * Add the ActivityPub capability to all users that can publish posts
	 *
	 * @return void
	 */
	private static function add_activitypub_capability() {
		// get all WP_User objects that can publish posts
		$users = \get_users(
			array(
				'capability__in' => array( 'publish_posts' ),
			)
		);

		// add ActivityPub capability to all users that can publish posts
		foreach ( $users as $user ) {
			$user->add_cap( 'activitypub' );
		}
	}

	/**
	 * Rename meta keys.
	 *
	 * @param string $old The old commentmeta key
	 * @param string $new The new commentmeta key
	 */
	private static function update_usermeta_key( $old, $new ) { // phpcs:ignore
		global $wpdb;

		$wpdb->update( // phpcs:ignore
			$wpdb->usermeta,
			array( 'meta_key' => $new ), // phpcs:ignore
			array( 'meta_key' => $old ), // phpcs:ignore
			array( '%s' ),
			array( '%s' )
		);
	}

	/**
	 * Rename option keys.
	 *
	 * @param string $old The old option key
	 * @param string $new The new option key
	 */
	   private static function update_options_key( $old, $new ) { // phpcs:ignore
		global $wpdb;

		$wpdb->update( // phpcs:ignore
			$wpdb->options,
			array( 'option_name' => $new ), // phpcs:ignore
			array( 'option_name' => $old ), // phpcs:ignore
			array( '%s' ),
			array( '%s' )
		);
	}
}
