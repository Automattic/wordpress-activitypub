<?php
/**
 * Actors collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Actor;
use Activitypub\Application;
use Activitypub\Model\Blog;
use Activitypub\Model\User;
use Activitypub\Signature;

use function Activitypub\is_user_type_disabled;
use function Activitypub\normalize_host;
use function Activitypub\normalize_url;
use function Activitypub\object_to_uri;
use function Activitypub\url_to_authorid;
use function Activitypub\user_can_activitypub;

/**
 * Actors collection.
 *
 * Provides methods to retrieve, create, update, and manage ActivityPub actors (users, blogs, applications, and remote actors).
 */
class Actors {
	/**
	 * The ID of the Blog User.
	 *
	 * @var int
	 */
	const BLOG_USER_ID = 0;

	/**
	 * The ID of the former Application user.
	 *
	 * @deprecated 9.1.0 The Application is no longer a user-like actor, see {@see \Activitypub\Application}. Retained for backward compatibility and legacy data handling.
	 *
	 * @var int
	 */
	const APPLICATION_USER_ID = -1;

	/**
	 * Get the Actor by ID.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return Actor|User|Blog|Application|\WP_Error Actor object or WP_Error if not found or not permitted.
	 */
	public static function get_by_id( $user_id ) {
		if ( \is_numeric( $user_id ) ) {
			$user_id = (int) $user_id;
		}

		/**
		 * Filter the actor before resolving by ID.
		 *
		 * Allows third-party plugins to register custom virtual actors
		 * resolved by ID, mirroring the `activitypub_pre_get_by_username`
		 * filter for username lookups.
		 *
		 * @since 8.1.0
		 *
		 * @param null $pre     The pre-existing value.
		 * @param int  $user_id The user ID.
		 */
		$pre = \apply_filters( 'activitypub_pre_get_by_id', null, $user_id );
		if ( null !== $pre ) {
			return $pre;
		}

		if ( ! user_can_activitypub( $user_id ) ) {
			return new \WP_Error(
				'activitypub_user_not_found',
				\__( 'Actor not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		switch ( $user_id ) {
			case self::BLOG_USER_ID:
				return new Blog();
			default:
				return User::from_wp_user( $user_id );
		}
	}

	/**
	 * Get the Actor by username.
	 *
	 * @param string $username Name of the actor.
	 *
	 * @return User|Blog|Application|\WP_Error Actor object or WP_Error if not found.
	 */
	public static function get_by_username( $username ) {
		/**
		 * Filter the username before we do anything else.
		 *
		 * @param null   $pre      The pre-existing value.
		 * @param string $username The username.
		 */
		$pre = \apply_filters( 'activitypub_pre_get_by_username', null, $username );
		if ( null !== $pre ) {
			return $pre;
		}

		$id = self::get_id_by_username( $username );
		if ( \is_wp_error( $id ) ) {
			return $id;
		}

		return self::get_by_id( $id );
	}

	/**
	 * Get the Actor by username.
	 *
	 * @param string $username Name of the actor.
	 *
	 * @return int|\WP_Error Actor id or WP_Error if not found.
	 */
	public static function get_id_by_username( $username ) {
		// Check for blog user.
		if (
			Blog::get_default_username() === $username ||
			\get_option( 'activitypub_blog_identifier' ) === $username
		) {
			if ( is_user_type_disabled( 'blog' ) ) {
				return new \WP_Error(
					'activitypub_user_not_found',
					\__( 'Actor not found', 'activitypub' ),
					array( 'status' => 404 )
				);
			}

			return self::BLOG_USER_ID;
		}

		/*
		 * The 'application' identifier is reserved for the signing-only Application
		 * actor, which is served only through the dedicated /application endpoint.
		 * Never resolve it to a regular user, even on sites that happen to have a
		 * user named "application". Compare lowercased: the user lookups below are
		 * case-insensitive, so the reservation has to be too.
		 */
		if ( Application::USERNAME === \strtolower( $username ) ) {
			return new \WP_Error(
				'activitypub_user_not_found',
				\__( 'Actor not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		// Check for 'activitypub_username' meta.
		$user = new \WP_User_Query(
			array(
				'count_total' => false,
				'number'      => 1,
				'hide_empty'  => true,
				'fields'      => 'ID',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'  => array(
					'relation' => 'OR',
					array(
						'key'     => '_activitypub_user_identifier',
						'value'   => $username,
						'compare' => 'LIKE',
					),
				),
			)
		);

		if ( $user->get_results() ) {
			return \current( $user->get_results() );
		}

		$username = \str_replace( array( '*', '%' ), '', $username );

		// Check for login or nicename.
		$user = new \WP_User_Query(
			array(
				'count_total'    => false,
				'search'         => $username,
				'search_columns' => array( 'user_login', 'user_nicename' ),
				'number'         => 1,
				'hide_empty'     => true,
				'fields'         => 'ID',
			)
		);

		if ( $user->get_results() ) {
			return \current( $user->get_results() );
		}

		return new \WP_Error(
			'activitypub_user_not_found',
			\__( 'Actor not found', 'activitypub' ),
			array( 'status' => 404 )
		);
	}

	/**
	 * Get the Actor by resource URI (acct, http(s), etc).
	 *
	 * @param string $uri The actor resource URI.
	 *
	 * @return User|Blog|Application|\WP_Error Actor object or WP_Error if not found.
	 */
	public static function get_by_resource( $uri ) {
		$id = self::get_id_by_resource( $uri );
		if ( \is_wp_error( $id ) ) {
			return $id;
		}

		return self::get_by_id( $id );
	}

	/**
	 * Get the Actor by resource URI (acct, http(s), etc).
	 *
	 * @param string $uri The actor resource URI.
	 *
	 * @return int|\WP_Error Actor id or WP_Error if not found.
	 */
	public static function get_id_by_resource( $uri ) {
		$uri = object_to_uri( $uri );

		if ( ! $uri ) {
			return new \WP_Error(
				'activitypub_no_uri',
				\__( 'No URI provided', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$scheme = 'acct';
		$match  = array();
		// Try to extract the scheme and the host.
		if ( \preg_match( '/^([a-zA-Z^:]+):(.*)$/i', $uri, $match ) ) {
			// Extract the scheme.
			$scheme = \esc_attr( $match[1] );
		}

		// @todo: handle old domain URIs here before we serve a new domain below when we shouldn't.
		// Although maybe passing through to ::get_by_username() is enough?

		switch ( $scheme ) {
			// Check for http(s) URIs.
			case 'http':
			case 'https':
				// Check for http(s)://blog.example.com/@username.
				$resource_path = \wp_parse_url( $uri, PHP_URL_PATH );

				if ( $resource_path ) {
					$blog_path = \wp_parse_url( \home_url(), PHP_URL_PATH );

					if ( $blog_path ) {
						$resource_path = \str_replace( $blog_path, '', $resource_path );
					}

					$resource_path = \trim( $resource_path, '/' );

					if ( \str_starts_with( $resource_path, '@' ) ) {
						$identifier = \str_replace( '@', '', $resource_path );
						$identifier = \trim( $identifier, '/' );

						return self::get_id_by_username( $identifier );
					}
				}

				// Check for http(s)://blog.example.com/author/username.
				$user_id = url_to_authorid( $uri );

				if ( \is_int( $user_id ) ) {
					return $user_id;
				}

				// Check for http(s)://blog.example.com/.
				$normalized_uri = normalize_url( $uri );

				if (
					normalize_url( \site_url() ) === $normalized_uri ||
					normalize_url( \home_url() ) === $normalized_uri
				) {
					return self::BLOG_USER_ID;
				}

				return new \WP_Error(
					'activitypub_no_user_found',
					\__( 'Actor not found', 'activitypub' ),
					array( 'status' => 404 )
				);
			// Check for acct URIs.
			case 'acct':
				$uri        = \str_replace( 'acct:', '', $uri );
				$identifier = \substr( $uri, 0, \strrpos( $uri, '@' ) );
				$host       = normalize_host( \substr( \strrchr( $uri, '@' ), 1 ) );
				$blog_host  = normalize_host( \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) );

				if ( $blog_host !== $host && normalize_host( \get_option( 'activitypub_old_host' ) ) !== $host ) {
					return new \WP_Error(
						'activitypub_wrong_host',
						\__( 'Resource host does not match blog host', 'activitypub' ),
						array( 'status' => 404 )
					);
				}

				// Prepare wildcards https://github.com/mastodon/mastodon/issues/22213.
				if ( \in_array( $identifier, array( '_', '*', '' ), true ) ) {
					return self::BLOG_USER_ID;
				}

				return self::get_id_by_username( $identifier );
			default:
				return new \WP_Error(
					'activitypub_wrong_scheme',
					\__( 'Wrong scheme', 'activitypub' ),
					array( 'status' => 404 )
				);
		}
	}

	/**
	 * Get the Actor by various identifier types (ID, URI, username, or email).
	 *
	 * @param string|int $id Actor identifier (user ID, URI, username, or email).
	 *
	 * @return User|Blog|Application|\WP_Error Actor object or WP_Error if not found.
	 */
	public static function get_by_various( $id ) {
		$id = self::get_id_by_various( $id );
		if ( \is_wp_error( $id ) ) {
			return $id;
		}

		return self::get_by_id( $id );
	}

	/**
	 * Get the Actor by various identifier types (ID, URI, username, or email).
	 *
	 * @param string|int $id Actor identifier (user ID, URI, username, or email).
	 *
	 * @return int|\WP_Error Actor id or WP_Error if not found.
	 */
	public static function get_id_by_various( $id ) {
		if ( \is_numeric( $id ) ) {
			$id = (int) $id;
		} elseif (
			// Is URL.
			\filter_var( $id, FILTER_VALIDATE_URL ) ||
			// Is acct.
			\str_starts_with( $id, 'acct:' ) ||
			// Is email.
			\filter_var( $id, FILTER_VALIDATE_EMAIL )
		) {
			$id = self::get_id_by_resource( $id );
		} else {
			$id = self::get_id_by_username( $id );
		}

		return $id;
	}

	/**
	 * Get the collection of all local user actors.
	 *
	 * @return Actor[] Array of User actor objects.
	 */
	public static function get_collection() {
		if ( is_user_type_disabled( 'user' ) ) {
			return array();
		}

		$users = \get_users(
			array(
				'capability__in' => array( 'activitypub' ),
			)
		);

		$return = array();

		foreach ( $users as $user ) {
			$actor = User::from_wp_user( $user->ID );

			if ( \is_wp_error( $actor ) ) {
				continue;
			}

			$return[] = $actor;
		}

		return $return;
	}

	/**
	 * Search local actors by name or username.
	 *
	 * Backs the actor autocomplete endpoint. Matches the search term against the WordPress user's
	 * login, nicename, and display name; the Blog actor is included when its name or identifier
	 * matches. Disabled actor types are excluded, mirroring get_collection()/get_all().
	 *
	 * @since 9.1.0
	 *
	 * @param string $query  The search term.
	 * @param int    $number Optional. Maximum number of actors to return. Default 10.
	 *
	 * @return Actor[] The matching local actor objects.
	 */
	public static function search( $query, $number = 10 ) {
		$actors = array();

		if ( ! is_user_type_disabled( 'blog' ) ) {
			$blog = new Blog();
			if ( false !== \stripos( $blog->get_name(), $query ) || false !== \stripos( (string) $blog->get_preferred_username(), $query ) ) {
				$actors[] = $blog;
			}
		}

		// Only query as many users as the Blog actor left room for, so a match is never fetched then trimmed.
		$remaining = $number - \count( $actors );

		if ( $remaining > 0 && ! is_user_type_disabled( 'user' ) ) {
			$users = \get_users(
				array(
					'capability__in' => array( 'activitypub' ),
					'search'         => '*' . $query . '*',
					'search_columns' => array( 'user_login', 'user_nicename', 'display_name' ),
					'number'         => $remaining,
				)
			);

			foreach ( $users as $user ) {
				$actor = User::from_wp_user( $user->ID );
				if ( ! \is_wp_error( $actor ) ) {
					$actors[] = $actor;
				}
			}
		}

		return $actors;
	}

	/**
	 * Get all active actors, including the Blog actor if enabled.
	 *
	 * @return int[] Array of User and Blog actor IDs.
	 */
	public static function get_all_ids() {
		$user_ids = array();

		if ( ! is_user_type_disabled( 'user' ) ) {
			$user_ids = \get_users(
				array(
					'fields'         => 'ID',
					'capability__in' => array( 'activitypub' ),
				)
			);
		}

		// Also include the blog actor if active.
		if ( ! is_user_type_disabled( 'blog' ) ) {
			$user_ids[] = self::BLOG_USER_ID;
		}

		return \array_map( 'intval', $user_ids );
	}

	/**
	 * Get all active actors, including the Blog actor if enabled.
	 *
	 * @return Actor[] Array of User and Blog actor objects.
	 */
	public static function get_all() {
		$user_ids = self::get_all_ids();

		$actors = \array_map( array( self::class, 'get_by_id' ), $user_ids );

		// Filter out any WP_Error instances.
		return \array_filter(
			$actors,
			static function ( $actor ) {
				return ! \is_wp_error( $actor );
			}
		);
	}

	/**
	 * Returns the actor type based on the user ID.
	 *
	 * @param int $user_id The user ID to check.
	 *
	 * @return string Actor type: 'user' or 'blog'.
	 */
	public static function get_type_by_id( $user_id ) {
		$user_id = (int) $user_id;

		if ( self::BLOG_USER_ID === $user_id ) {
			return 'blog';
		}

		return 'user';
	}

	/**
	 * Return the public key for a given actor.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Deprecated. Keys are never rotated; new pairs are only generated when none is stored.
	 *
	 * @return string The public key.
	 */
	public static function get_public_key( $user_id, $force = false ) {
		if ( $force ) {
			\_deprecated_argument( __METHOD__, '9.1.0', \esc_html__( 'Keys are never rotated; new pairs are only generated when none is stored.', 'activitypub' ) );
		}

		$key_pair = self::get_keypair( $user_id );

		return $key_pair['public_key'];
	}

	/**
	 * Return the private key for a given actor.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Deprecated. Keys are never rotated; new pairs are only generated when none is stored.
	 *
	 * @return string The private key.
	 */
	public static function get_private_key( $user_id, $force = false ) {
		if ( $force ) {
			\_deprecated_argument( __METHOD__, '9.1.0', \esc_html__( 'Keys are never rotated; new pairs are only generated when none is stored.', 'activitypub' ) );
		}

		$key_pair = self::get_keypair( $user_id );

		return $key_pair['private_key'];
	}

	/**
	 * Return the key pair for a given actor.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array The key pair.
	 */
	public static function get_keypair( $user_id ) {
		return Signature::get_key_pair(
			self::get_signature_options_key( $user_id ),
			function () use ( $user_id ) {
				return self::check_legacy_key_pair( $user_id );
			}
		);
	}

	/**
	 * Return the option key for a given user.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return string The option key.
	 */
	protected static function get_signature_options_key( $user_id ) {
		if ( $user_id > 0 ) {
			$user = \get_userdata( $user_id );
			// Sanitize username because it could include spaces and special chars.
			$user_id = \sanitize_title( $user->user_login );
		}

		return 'activitypub_keypair_for_' . $user_id;
	}

	/**
	 * Check if there is a legacy key pair
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array|bool The key pair or false.
	 */
	protected static function check_legacy_key_pair( $user_id ) {
		switch ( $user_id ) {
			case 0:
				$public_key  = \get_option( 'activitypub_blog_user_public_key' );
				$private_key = \get_option( 'activitypub_blog_user_private_key' );
				break;
			default:
				$public_key  = \get_user_meta( $user_id, 'magic_sig_public_key', true );
				$private_key = \get_user_meta( $user_id, 'magic_sig_private_key', true );
				break;
		}

		if ( ! empty( $public_key ) && \is_string( $public_key ) && ! empty( $private_key ) && \is_string( $private_key ) ) {
			return array(
				'private_key' => $private_key,
				'public_key'  => $public_key,
			);
		}

		return false;
	}

	/**
	 * Determine if social graph (followers and following) should be shown for a given user.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return bool True if social graph should be shown, false otherwise.
	 */
	public static function show_social_graph( $user_id ) {
		if ( self::BLOG_USER_ID === (int) $user_id ) {
			return ! (bool) \get_option( 'activitypub_hide_social_graph' );
		} else {
			return ! (bool) \get_user_option( 'activitypub_hide_social_graph', $user_id );
		}
	}
}
