<?php
namespace Activitypub;

class Migration {
	public static function init() {
		\add_action( 'activitypub_schedule_migration', array( self::class, 'maybe_migrate' ) );
	}

	public static function get_target_version() {
		return get_plugin_version();
	}

	public static function get_version() {
		return get_option( 'activitypub_db_version', 0 );
	}

	/**
	 * Whether the database structure is up to date.
	 *
	 * @return bool
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

		$version_from_db = self::get_version();

		if ( version_compare( $version_from_db, '1.0.0', '<' ) ) {
			self::migrate_from_0_17();
			self::migrate_from_0_16();
		}

		update_option( 'activitypub_db_version', self::get_target_version() );
	}

	/**
	 * Updates the DB-schema of the followers-list
	 *
	 * @return void
	 */
	public static function migrate_from_0_17() {
		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			$followers = get_user_meta( $user_id, 'activitypub_followers', true );

			if ( $followers ) {
				foreach ( $followers as $follower ) {
					Collection\Followers::add_follower( $user_id, $follower );
				}
			}
		}
	}

	/**
	 * Updates the custom template to use shortcodes instead of the deprecated templates.
	 *
	 * @return void
	 */
	public static function migrate_from_0_16() {
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
}
