<?php
/**
 * Users collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

/**
 * Users collection.
 *
 * @deprecated version 4.2.0
 */
class Users extends Actors {
	/**
	 * Get the User by ID.
	 *
	 * @param int $user_id The User-ID.
	 *
	 * @return User|Blog|Application|WP_Error The User or WP_Error if user not found.
	 */
	public static function get_by_id( $user_id ) {
		_deprecated_function( __METHOD__, '4.2.0', 'Activitypub\Collection\Actors::get_by_id' );

		return parent::get_by_id( $user_id );
	}

	/**
	 * Get the User by username.
	 *
	 * @param string $username The User-Name.
	 *
	 * @return User|Blog|Application|WP_Error The User or WP_Error if user not found.
	 */
	public static function get_by_username( $username ) {
		_deprecated_function( __METHOD__, '4.2.0', 'Activitypub\Collection\Actors::get_by_username' );

		return parent::get_by_username( $username );
	}

	/**
	 * Get the User by resource.
	 *
	 * @param string $uri The User-Resource.
	 *
	 * @return User|WP_Error The User or WP_Error if user not found.
	 */
	public static function get_by_resource( $uri ) {
		_deprecated_function( __METHOD__, '4.2.0', 'Activitypub\Collection\Actors::get_by_resource' );

		return parent::get_by_resource( $uri );
	}

	/**
	 * Get the User by resource.
	 *
	 * @param string $id The User-Resource.
	 *
	 * @return User|Blog|Application|WP_Error The User or WP_Error if user not found.
	 */
	public static function get_by_various( $id ) {
		_deprecated_function( __METHOD__, '4.2.0', 'Activitypub\Collection\Actors::get_by_various' );

		return parent::get_by_various( $id );
	}

	/**
	 * Get the User collection.
	 *
	 * @return array The User collection.
	 */
	public static function get_collection() {
		_deprecated_function( __METHOD__, '4.2.0', 'Activitypub\Collection\Actors::get_collection' );

		return parent::get_collection();
	}
}
