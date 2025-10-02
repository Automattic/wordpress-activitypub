<?php
/**
 * Actors collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Actor;
use Activitypub\Model\Application;
use Activitypub\Model\Blog;
use Activitypub\Model\User;

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
	 * The ID of the Application User.
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
		if ( is_numeric( $user_id ) ) {
			$user_id = (int) $user_id;
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
			case self::APPLICATION_USER_ID:
				return new Application();
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
		$pre = apply_filters( 'activitypub_pre_get_by_username', null, $username );
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

		// Check for application user.
		if ( 'application' === $username ) {
			return self::APPLICATION_USER_ID;
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

		$username = str_replace( array( '*', '%' ), '', $username );

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
		if ( preg_match( '/^([a-zA-Z^:]+):(.*)$/i', $uri, $match ) ) {
			// Extract the scheme.
			$scheme = \esc_attr( $match[1] );
		}

		// @todo: handle old domain URIs here before we serve a new domain below when we shouldn't.
		// Although maybe passing through to ::get_by_username() is enough?

		switch ( $scheme ) {
			// Check for http(s) URIs.
			case 'http':
			case 'https':
				// Check locally stored remote Actor.
				$post = Remote_Actors::get_by_uri( $uri );

				if ( ! \is_wp_error( $post ) ) {
					return $post->ID;
				}

				// Check for http(s)://blog.example.com/@username.
				$resource_path = \wp_parse_url( $uri, PHP_URL_PATH );

				if ( $resource_path ) {
					$blog_path = \wp_parse_url( \home_url(), PHP_URL_PATH );

					if ( $blog_path ) {
						$resource_path = \str_replace( $blog_path, '', $resource_path );
					}

					$resource_path = \trim( $resource_path, '/' );

					if ( str_starts_with( $resource_path, '@' ) ) {
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
					normalize_url( site_url() ) === $normalized_uri ||
					normalize_url( home_url() ) === $normalized_uri
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

				if ( $blog_host !== $host && get_option( 'activitypub_old_host' ) !== $host ) {
					return new \WP_Error(
						'activitypub_wrong_host',
						\__( 'Resource host does not match blog host', 'activitypub' ),
						array( 'status' => 404 )
					);
				}

				// Prepare wildcards https://github.com/mastodon/mastodon/issues/22213.
				if ( in_array( $identifier, array( '_', '*', '' ), true ) ) {
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
		if ( is_numeric( $id ) ) {
			$id = (int) $id;
		} elseif (
			// Is URL.
			filter_var( $id, FILTER_VALIDATE_URL ) ||
			// Is acct.
			str_starts_with( $id, 'acct:' ) ||
			// Is email.
			filter_var( $id, FILTER_VALIDATE_EMAIL )
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
	 * Get all active actors, including the Blog actor if enabled.
	 *
	 * @return array Array of User and Blog actor objects.
	 */
	public static function get_all() {
		$return = array();

		if ( ! is_user_type_disabled( 'user' ) ) {
			$users = \get_users(
				array(
					'capability__in' => array( 'activitypub' ),
				)
			);

			foreach ( $users as $user ) {
				$actor = User::from_wp_user( $user->ID );

				if ( \is_wp_error( $actor ) ) {
					continue;
				}

				$return[] = $actor;
			}
		}

		// Also include the blog actor if active.
		if ( ! is_user_type_disabled( 'blog' ) ) {
			$blog_actor = self::get_by_id( self::BLOG_USER_ID );
			if ( ! \is_wp_error( $blog_actor ) ) {
				$return[] = $blog_actor;
			}
		}

		return $return;
	}

	/**
	 * Returns the actor type based on the user ID.
	 *
	 * @param int $user_id The user ID to check.
	 *
	 * @return string Actor type: 'user', 'blog', or 'application'.
	 */
	public static function get_type_by_id( $user_id ) {
		$user_id = (int) $user_id;

		if ( self::APPLICATION_USER_ID === $user_id ) {
			return 'application';
		}

		if ( self::BLOG_USER_ID === $user_id ) {
			return 'blog';
		}

		return 'user';
	}

	/**
	 * Return the public key for a given actor.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return string The public key.
	 */
	public static function get_public_key( $user_id, $force = false ) {
		if ( $force ) {
			self::generate_key_pair( $user_id );
		}

		$key_pair = self::get_keypair( $user_id );

		return $key_pair['public_key'];
	}

	/**
	 * Return the private key for a given actor.
	 *
	 * @param int  $user_id The WordPress User ID.
	 * @param bool $force   Optional. Force the generation of a new key pair. Default false.
	 *
	 * @return string The private key.
	 */
	public static function get_private_key( $user_id, $force = false ) {
		if ( $force ) {
			self::generate_key_pair( $user_id );
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
		$option_key = self::get_signature_options_key( $user_id );
		$key_pair   = \get_option( $option_key );

		if ( ! $key_pair ) {
			$key_pair = self::generate_key_pair( $user_id );
		}

		return $key_pair;
	}

	/**
	 * Generates the pair of keys.
	 *
	 * @param int $user_id The WordPress User ID.
	 *
	 * @return array The key pair.
	 */
	protected static function generate_key_pair( $user_id ) {
		$option_key = self::get_signature_options_key( $user_id );
		$key_pair   = self::check_legacy_key_pair( $user_id );

		if ( $key_pair ) {
			\add_option( $option_key, $key_pair );

			return $key_pair;
		}

		$config = array(
			'digest_alg'       => 'sha512',
			'private_key_bits' => 2048,
			'private_key_type' => \OPENSSL_KEYTYPE_RSA,
		);

		$key         = \openssl_pkey_new( $config );
		$private_key = null;
		$detail      = array();
		if ( $key ) {
			\openssl_pkey_export( $key, $private_key );

			$detail = \openssl_pkey_get_details( $key );
		}

		// Check if keys are valid.
		if (
			empty( $private_key ) || ! is_string( $private_key ) ||
			! isset( $detail['key'] ) || ! is_string( $detail['key'] )
		) {
			return array(
				'private_key' => null,
				'public_key'  => null,
			);
		}

		$key_pair = array(
			'private_key' => $private_key,
			'public_key'  => $detail['key'],
		);

		// Persist keys.
		\add_option( $option_key, $key_pair );

		return $key_pair;
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
			case -1:
				$public_key  = \get_option( 'activitypub_application_user_public_key' );
				$private_key = \get_option( 'activitypub_application_user_private_key' );
				break;
			default:
				$public_key  = \get_user_meta( $user_id, 'magic_sig_public_key', true );
				$private_key = \get_user_meta( $user_id, 'magic_sig_private_key', true );
				break;
		}

		if ( ! empty( $public_key ) && is_string( $public_key ) && ! empty( $private_key ) && is_string( $private_key ) ) {
			return array(
				'private_key' => $private_key,
				'public_key'  => $public_key,
			);
		}

		return false;
	}

	/**
	 * Returns all Inboxes for all known remote Actors.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_inboxes()}
	 *
	 * @return array The list of Inboxes.
	 */
	public static function get_inboxes() {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_inboxes' );
		return Remote_Actors::get_inboxes();
	}

	/**
	 * Upsert (insert or update) a remote actor as a custom post type.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::upsert()}
	 *
	 * @param array|Actor $actor ActivityPub actor object (array or actor, must include 'id').
	 *
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function upsert( $actor ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::upsert' );
		return Remote_Actors::upsert( $actor );
	}

	/**
	 * Create a remote actor as a custom post type.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::create()}
	 *
	 * @param array|Actor $actor ActivityPub actor object (array or Actor, must include 'id').
	 *
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function create( $actor ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::create' );
		return Remote_Actors::create( $actor );
	}

	/**
	 * Update a remote Actor object by actor URL (guid).
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::update()}
	 *
	 * @param int|\WP_Post $post  The post ID or object.
	 * @param array|Actor  $actor The ActivityPub actor object as associative array (must include 'id').
	 *
	 * @return int|\WP_Error The post ID or WP_Error.
	 */
	public static function update( $post, $actor ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::update' );
		return Remote_Actors::update( $post, $actor );
	}

	/**
	 * Delete a remote actor object by actor URL (guid).
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::delete()}
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $post_id ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::delete' );
		return Remote_Actors::delete( $post_id );
	}

	/**
	 * Get a remote actor post by actor URI (guid).
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_by_uri()}
	 *
	 * @param string $actor_uri The actor URI.
	 *
	 * @return \WP_Post|\WP_Error Post object or WP_Error if not found.
	 */
	public static function get_remote_by_uri( $actor_uri ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_by_uri' );
		return Remote_Actors::get_by_uri( $actor_uri );
	}

	/**
	 * Lookup a remote actor post by actor URI (guid), fetching from remote if not found locally.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::fetch_by_uri()}
	 *
	 * @param string $actor_uri The actor URI.
	 *
	 * @return \WP_Post|\WP_Error Post object or WP_Error if not found.
	 */
	public static function fetch_remote_by_uri( $actor_uri ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::fetch_by_uri' );
		return Remote_Actors::fetch_by_uri( $actor_uri );
	}

	/**
	 * Store an error that occurred when sending an ActivityPub message to a follower.
	 *
	 * The error will be stored in post meta.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::add_error()}
	 *
	 * @param int              $post_id The ID of the WordPress Custom-Post-Type.
	 * @param string|\WP_Error $error   The error message.
	 *
	 * @return int|false The meta ID on success, false on failure.
	 */
	public static function add_error( $post_id, $error ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::add_error' );
		return Remote_Actors::add_error( $post_id, $error );
	}

	/**
	 * Count the errors for an actor.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::count_errors()}
	 *
	 * @param int $post_id The ID of the WordPress Custom-Post-Type.
	 *
	 * @return int The number of errors.
	 */
	public static function count_errors( $post_id ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::count_errors' );
		return Remote_Actors::count_errors( $post_id );
	}

	/**
	 * Get all error messages for an actor.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_errors()}
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string[] Array of error messages.
	 */
	public static function get_errors( $post_id ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_errors' );
		return Remote_Actors::get_errors( $post_id );
	}

	/**
	 * Clear all errors for an actor.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::clear_errors()}
	 *
	 * @param int $post_id The ID of the WordPress Custom-Post-Type.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_errors( $post_id ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::clear_errors' );
		return Remote_Actors::clear_errors( $post_id );
	}

	/**
	 * Get all remote actors (Custom Post Type) that had errors.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_faulty()}
	 *
	 * @param int $number Optional. Number of actors to return. Default 20.
	 *
	 * @return \WP_Post[] Array of faulty actor posts.
	 */
	public static function get_faulty( $number = 20 ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_faulty' );
		return Remote_Actors::get_faulty( $number );
	}

	/**
	 * Get all remote actor posts not updated for a given time.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_outdated()}
	 *
	 * @param int $number     Optional. Limits the result. Default 50.
	 * @param int $older_than Optional. The time in seconds. Default DAY_IN_SECONDS.
	 *
	 * @return \WP_Post[] The list of actors.
	 */
	public static function get_outdated( $number = 50, $older_than = DAY_IN_SECONDS ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_outdated' );
		return Remote_Actors::get_outdated( $number, $older_than );
	}

	/**
	 * Convert a custom post type input to an Activitypub\Activity\Actor.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_actor()}
	 *
	 * @param int|\WP_Post $post The post ID or object.
	 *
	 * @return Actor|\WP_Error The actor object or WP_Error on failure.
	 */
	public static function get_actor( $post ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_actor' );
		return Remote_Actors::get_actor( $post );
	}

	/**
	 * Get public key from key_id.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::get_public_key()}
	 *
	 * @param string $key_id The URL to the public key.
	 *
	 * @return resource|\WP_Error The public key resource or WP_Error.
	 */
	public static function get_remote_key( $key_id ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Activitypub\Collection\Remote_Actors::get_public_key' );
		return Remote_Actors::get_public_key( $key_id );
	}

	/**
	 * Normalize actor identifier to a URI.
	 *
	 * Handles webfinger addresses, URLs without schemes, objects, and arrays.
	 *
	 * @deprecated 7.4.0 Use {@see Remote_Actors::normalize_identifier()}
	 *
	 * @param string|object|array $actor Actor URI, webfinger address, actor object, or array.
	 * @return string|null Normalized actor URI or null if unable to resolve.
	 */
	public static function normalize_identifier( $actor ) {
		_deprecated_function( __METHOD__, '7.4.0', 'Remote_Actors::normalize_identifier' );
		return Remote_Actors::normalize_identifier( $actor );
	}
}
