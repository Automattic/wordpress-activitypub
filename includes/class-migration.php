<?php
namespace Activitypub;

class Migration {
	/**
	 * Which internal datastructure version we are running on.
	 *
	 * @var int
	 */
	private static $target_version = '1.0.0';

	public static function get_target_version() {
		return self::$target_version;
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
			self::migrate_to_1_0_0();
		}

		update_option( 'activitypub_db_version', self::$target_version );
	}

	/**
	 * The Migration for Plugin Version 1.0.0 and DB Version 1.0.0
	 *
	 * @since 5.0.0
	 *
	 * @return void
	 */
	public static function migrate_to_1_0_0() {
		foreach ( get_users( array( 'fields' => 'ID' ) ) as $user_id ) {
			$followes = get_user_meta( $user_id, 'activitypub_followers', true );

			if ( $followes ) {
				foreach ( $followes as $follower ) {
					Collection\Followers::add_follower( $user_id, $follower );
				}
			}
		}
	}
}
