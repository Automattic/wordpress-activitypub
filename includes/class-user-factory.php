<?php
namespace Activitypub;

use WP_Error;
use WP_User_Query;
use Activitypub\Model\User;
use Activitypub\Model\Blog_User;

class User_Factory {
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
		$user_id = (int) $user_id;

		if ( self::BLOG_USER_ID === $user_id ) {
			return new Blog_User( $user_id );
		} elseif ( self::APPLICATION_USER_ID === $user_id ) {
			return new Application_User( $user_id );
		} else {
			$user = get_user_by( 'ID', $user_id );
			if ( ! $user || ! \user_can( $user, 'publish_posts' ) ) {
				return new WP_Error(
					'activitypub_user_not_found',
					\__( 'User not found', 'activitypub' ),
					array( 'status' => 404 )
				);
			}

			return new User( $user->ID );
		}
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
		if ( get_option( 'activitypub_blog_identifier', null ) === $username ) {
			return self::get_by_id( self::BLOG_USER_ID );
		}

		// check for 'activitypub_username' meta
		$user = new WP_User_Query(
			array(
				'number'         => 1,
				'hide_empty'     => true,
				'fields'         => 'ID',
				'meta_query' => array(
					'relation' => 'OR',
					array(
						'key'     => 'activitypub_identifier',
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
		$resource_host = \str_replace( 'www.', '', \substr( \strrchr( $resource, '@' ), 1 ) );
		$blog_host = \str_replace( 'www.', '', \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) );

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
}
