<?php
namespace Activitypub\Collection;

use WP_Error;
use WP_User_Query;
use Activitypub\Model\User;
use Activitypub\Model\Blog_User;
use Activitypub\Model\Application_User;

use function Activitypub\is_user_disabled;

class Users {
	/**
	 * The ID of the Blog User
	 *
	 * @var int
	 */
	const BLOG_USER_ID = 0;

	/**
	 * The ID of the Application User
	 *
	 * @var int
	 */
	const APPLICATION_USER_ID = -1;

	/**
	 * Get the User by ID
	 *
	 * @param int $user_id The User-ID.
	 *
	 * @return \Acitvitypub\Model\User The User.
	 */
	public static function get_by_id( $user_id ) {
		if ( is_string( $user_id ) || is_numeric( $user_id ) ) {
			$user_id = (int) $user_id;
		}

		if ( is_user_disabled( $user_id ) ) {
			return new WP_Error(
				'activitypub_user_not_found',
				\__( 'User not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		if ( self::BLOG_USER_ID === $user_id ) {
			return Blog_User::from_wp_user( $user_id );
		} elseif ( self::APPLICATION_USER_ID === $user_id ) {
			return Application_User::from_wp_user( $user_id );
		} elseif ( $user_id > 0 ) {
			return User::from_wp_user( $user_id );
		}

		return new WP_Error(
			'activitypub_user_not_found',
			\__( 'User not found', 'activitypub' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Get the User by username.
	 *
	 * @param string $username The User-Name.
	 *
	 * @return \Acitvitypub\Model\User The User.
	 */
	public static function get_by_username( $username ) {
		// check for blog user.
		if ( Blog_User::get_default_username() === $username ) {
			return self::get_by_id( self::BLOG_USER_ID );
		}

		$blog_id = get_option( 'activitypub_blog_user_identifier' );

		if (
			$blog_id === $username ||
			preg_replace( '/[.-_+]/', '', $blog_id ) === $username
		) {
			return self::get_by_id( self::BLOG_USER_ID );
		}

		// check for application user.
		if ( 'application' === $username ) {
			return self::get_by_id( self::APPLICATION_USER_ID );
		}

		// check for 'activitypub_username' meta
		$user = new WP_User_Query(
			array(
				'number'         => 1,
				'hide_empty'     => true,
				'fields'         => 'ID',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => 'activitypub_user_identifier',
						'value'   => $username,
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( $user->results ) {
			return self::get_by_id( $user->results[0] );
		}

		// check for login or nicename.
		$user = new WP_User_Query(
			array(
				'search'         => $username,
				'search_columns' => array( 'user_login', 'user_nicename' ),
				'number'         => 1,
				'hide_empty'     => true,
				'fields'         => 'ID',
			)
		);

		if ( $user->results ) {
			return self::get_by_id( $user->results[0] );
		}

		return new WP_Error(
			'activitypub_user_not_found',
			\__( 'User not found', 'activitypub' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Get the User by resource.
	 *
	 * @param string $resource The User-Resource.
	 *
	 * @return \Acitvitypub\Model\User The User.
	 */
	public static function get_by_resource( $resource ) {
		if ( \strpos( $resource, '@' ) === false ) {
			return new WP_Error(
				'activitypub_unsupported_resource',
				\__( 'Resource is invalid', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$resource = \str_replace( 'acct:', '', $resource );

		$resource_identifier = \substr( $resource, 0, \strrpos( $resource, '@' ) );
		$resource_host = self::normalize_host( \substr( \strrchr( $resource, '@' ), 1 ) );
		$blog_host = self::normalize_host( \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) );

		if ( $blog_host !== $resource_host ) {
			return new WP_Error(
				'activitypub_wrong_host',
				\__( 'Resource host does not match blog host', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return self::get_by_username( $resource_identifier );
	}

	/**
	 * Get the User by resource.
	 *
	 * @param string $resource The User-Resource.
	 *
	 * @return \Acitvitypub\Model\User The User.
	 */
	public static function get_by_various( $id ) {
		if ( is_numeric( $id ) ) {
			return self::get_by_id( $id );
		} elseif ( filter_var( $id, FILTER_VALIDATE_URL ) ) {
			return self::get_by_resource( $id );
		} else {
			return self::get_by_username( $id );
		}
	}

	/**
	 * Normalize the host.
	 *
	 * @param string $host The host.
	 *
	 * @return string The normalized host.
	 */
	public static function normalize_host( $host ) {
		return \str_replace( 'www.', '', $host );
	}
}
