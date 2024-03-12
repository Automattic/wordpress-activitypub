<?php
namespace Activitypub\Collection;

use WP_Error;
use WP_User_Query;
use Activitypub\Transformer\User;

use function Activitypub\url_to_authorid;
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

		$user = null;

		if ( self::BLOG_USER_ID === $user_id ) {
			$args = array(
				'role'    => 'activitypub_blog',
				'orderby' => 'user_nicename',
				'order'   => 'ASC',
			);

			$users = get_users( $args );
			$user  = current( $users );
		} elseif ( self::APPLICATION_USER_ID === $user_id ) {
			$args = array(
				'role'    => 'activitypub_application',
				'orderby' => 'user_nicename',
				'order'   => 'ASC',
			);

			$users = get_users( $args );
			$user  = current( $users );
		} elseif ( $user_id > 0 ) {
			$user = get_user_by( 'id', $user_id );
		}

		if ( $user ) {
			return User::transform( $user )->to_actor();
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
		if ( get_option( 'activitypub_blog_user_identifier' ) === $username ) {
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

		$username = str_replace( array( '*', '%' ), '', $username );

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
		$scheme = 'acct';
		$match = array();
		// try to extract the scheme and the host
		if ( preg_match( '/^([a-zA-Z^:]+):(.*)$/i', $resource, $match ) ) {
			// extract the scheme
			$scheme = esc_attr( $match[1] );
		}

		switch ( $scheme ) {
			// check for http(s) URIs
			case 'http':
			case 'https':
				$url_parts = wp_parse_url( $resource );

				// check for http(s)://blog.example.com/@username
				if (
					isset( $url_parts['path'] ) &&
					str_starts_with( $url_parts['path'], '/@' )
				) {
					$identifier = str_replace( '/@', '', $url_parts['path'] );
					$identifier = untrailingslashit( $identifier );

					return self::get_by_username( $identifier );
				}

				// check for http(s)://blog.example.com/author/username
				$user_id = url_to_authorid( $resource );

				if ( $user_id ) {
					return self::get_by_id( $user_id );
				}

				// check for http(s)://blog.example.com/
				if (
					self::normalize_url( site_url() ) === self::normalize_url( $resource ) ||
					self::normalize_url( home_url() ) === self::normalize_url( $resource )
				) {
					return self::get_by_id( self::BLOG_USER_ID );
				}

				return new WP_Error(
					'activitypub_no_user_found',
					\__( 'User not found', 'activitypub' ),
					array( 'status' => 404 )
				);
			// check for acct URIs
			case 'acct':
				$resource   = \str_replace( 'acct:', '', $resource );
				$identifier = \substr( $resource, 0, \strrpos( $resource, '@' ) );
				$host       = self::normalize_host( \substr( \strrchr( $resource, '@' ), 1 ) );
				$blog_host  = self::normalize_host( \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) );

				if ( $blog_host !== $host ) {
					return new WP_Error(
						'activitypub_wrong_host',
						\__( 'Resource host does not match blog host', 'activitypub' ),
						array( 'status' => 404 )
					);
				}

				// prepare wildcards https://github.com/mastodon/mastodon/issues/22213
				if ( in_array( $identifier, array( '_', '*', '' ), true ) ) {
					return self::get_by_id( self::BLOG_USER_ID );
				}

				return self::get_by_username( $identifier );
			default:
				return new WP_Error(
					'activitypub_wrong_scheme',
					\__( 'Wrong scheme', 'activitypub' ),
					array( 'status' => 404 )
				);
		}
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
		} elseif (
			// is URL
			filter_var( $id, FILTER_VALIDATE_URL ) ||
			// is acct
			str_starts_with( $id, 'acct:' )
		) {
			return self::get_by_resource( $id );
		} else {
			return self::get_by_username( $id );
		}
	}

	/**
	 * Normalize a host.
	 *
	 * @param string $host The host.
	 *
	 * @return string The normalized host.
	 */
	public static function normalize_host( $host ) {
		return \str_replace( 'www.', '', $host );
	}

	/**
	 * Normalize a URL.
	 *
	 * @param string $url The URL.
	 *
	 * @return string The normalized URL.
	 */
	public static function normalize_url( $url ) {
		$url = \untrailingslashit( $url );
		$url = \str_replace( 'https://', '', $url );
		$url = \str_replace( 'http://', '', $url );
		$url = \str_replace( 'www.', '', $url );

		return $url;
	}

	/**
	 * Get the User collection.
	 *
	 * @return array The User collection.
	 */
	public static function get_collection() {
		$users = \get_users(
			array(
				'capability__in' => array( 'activitypub' ),
			)
		);

		$return = array();

		foreach ( $users as $user ) {
			$return[] = User::from_wp_user( $user->ID );
		}

		return $return;
	}
}
