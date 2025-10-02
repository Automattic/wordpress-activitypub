<?php
/**
 * Remote Actors collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Activity\Actor;
use Activitypub\Http;
use Activitypub\Sanitize;
use Activitypub\Webfinger;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\is_actor;
use function Activitypub\object_to_uri;

/**
 * Remote Actors collection class.
 */
class Remote_Actors {
	/**
	 * Post type for storing remote actors.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ap_actor';

	/**
	 * Cache key for the followers inbox.
	 *
	 * @var string
	 */
	const CACHE_KEY_INBOXES = 'actor_inboxes';

	/**
	 * Returns all Inboxes for all known remote Actors.
	 *
	 * @return array The list of Inboxes.
	 */
	public static function get_inboxes() {
		$inboxes = \wp_cache_get( self::CACHE_KEY_INBOXES, 'activitypub' );

		if ( $inboxes ) {
			return $inboxes;
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$results = $wpdb->get_col(
			"SELECT DISTINCT meta_value FROM {$wpdb->postmeta}
			WHERE meta_key = '_activitypub_inbox'
			AND meta_value IS NOT NULL"
		);

		$inboxes = \array_filter( $results );
		\wp_cache_set( self::CACHE_KEY_INBOXES, $inboxes, 'activitypub' );

		return $inboxes;
	}

	/**
	 * Upsert (insert or update) a remote actor as a custom post type.
	 *
	 * @param array|Actor $actor ActivityPub actor object (array or actor, must include 'id').
	 *
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function upsert( $actor ) {
		if ( \is_array( $actor ) ) {
			$actor = Actor::init_from_array( $actor );
		}

		$post = self::get_by_uri( $actor->get_id() );

		if ( ! \is_wp_error( $post ) ) {
			return self::update( $post, $actor );
		}

		return self::create( $actor );
	}

	/**
	 * Create a remote actor as a custom post type.
	 *
	 * @param array|Actor $actor ActivityPub actor object (array or Actor, must include 'id').
	 *
	 * @return int|\WP_Error Post ID on success, WP_Error on failure.
	 */
	public static function create( $actor ) {
		if ( \is_array( $actor ) ) {
			$actor = Actor::init_from_array( $actor );
		}

		$args = self::prepare_custom_post_type( $actor );

		if ( \is_wp_error( $args ) ) {
			return $args;
		}

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		$post_id = \wp_insert_post( $args );

		if ( $has_kses ) {
			// Restore KSES filters.
			\kses_init_filters();
		}

		return $post_id;
	}

	/**
	 * Update a remote Actor object by actor URL (guid).
	 *
	 * @param int|\WP_Post $post  The post ID or object.
	 * @param array|Actor  $actor The ActivityPub actor object as associative array (must include 'id').
	 *
	 * @return int|\WP_Error The post ID or WP_Error.
	 */
	public static function update( $post, $actor ) {
		if ( \is_array( $actor ) ) {
			$actor = Actor::init_from_array( $actor );
		}

		$post = \get_post( $post, ARRAY_A );

		if ( ! $post ) {
			return new \WP_Error(
				'activitypub_actor_not_found',
				\__( 'Actor not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$args = self::prepare_custom_post_type( $actor );

		if ( \is_wp_error( $args ) ) {
			return $args;
		}

		$args = \wp_parse_args( $args, $post );

		$has_kses = false !== \has_filter( 'content_save_pre', 'wp_filter_post_kses' );
		if ( $has_kses ) {
			// Prevent KSES from corrupting JSON in post_content.
			\kses_remove_filters();
		}

		$post_id = \wp_update_post( $args );

		if ( $has_kses ) {
			// Restore KSES filters.
			\kses_init_filters();
		}

		return $post_id;
	}

	/**
	 * Delete a remote actor object by actor URL (guid).
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function delete( $post_id ) {
		return \wp_delete_post( $post_id );
	}

	/**
	 * Get a remote actor post by actor URI (guid).
	 *
	 * @param string $actor_uri The actor URI.
	 *
	 * @return \WP_Post|\WP_Error Post object or WP_Error if not found.
	 */
	public static function get_by_uri( $actor_uri ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s AND post_type=%s",
				esc_sql( $actor_uri ),
				esc_sql( self::POST_TYPE )
			)
		);

		if ( ! $post_id ) {
			return new \WP_Error(
				'activitypub_actor_not_found',
				\__( 'Actor not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return \get_post( $post_id );
	}

	/**
	 * Fetch a remote actor post by either actor URI or acct, fetching from remote if not found locally.
	 *
	 * @param string $uri_or_acct The actor URI or acct identifier.
	 *
	 * @return \WP_Post|\WP_Error Post object or WP_Error if not found.
	 */
	public static function fetch_by_various( $uri_or_acct ) {
		if ( \filter_var( $uri_or_acct, FILTER_VALIDATE_URL ) ) {
			return self::fetch_by_uri( $uri_or_acct );
		}

		if ( preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $uri_or_acct ) ) {
			return self::fetch_by_acct( $uri_or_acct );
		}

		return new \WP_Error(
			'activitypub_invalid_actor_identifier',
			'The actor identifier is not supported',
			array( 'status' => 400 )
		);
	}

	/**
	 * Lookup a remote actor post by actor URI (guid), fetching from remote if not found locally.
	 *
	 * @param string $actor_uri The actor URI.
	 *
	 * @return \WP_Post|\WP_Error Post object or WP_Error if not found.
	 */
	public static function fetch_by_uri( $actor_uri ) {
		$post = self::get_by_uri( $actor_uri );

		if ( ! \is_wp_error( $post ) ) {
			return $post;
		}

		$object = Http::get_remote_object( $actor_uri, false );

		if ( \is_wp_error( $object ) ) {
			return $object;
		}

		if ( ! is_actor( $object ) ) {
			return new \WP_Error(
				'activitypub_no_actor',
				\__( 'Object is not an Actor', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$post_id = self::upsert( $object );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return \get_post( $post_id );
	}

	/**
	 * Fetch a remote actor post by acct, fetching from remote if not found locally.
	 *
	 * @param string $acct The acct identifier.
	 *
	 * @return \WP_Post|\WP_Error Post object or WP_Error if not found.
	 */
	public static function fetch_by_acct( $acct ) {
		$acct = Sanitize::webfinger( $acct );

		// Check local DB for acct post meta.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_activitypub_acct' AND meta_value=%s",
				$acct
			)
		);

		if ( $post_id ) {
			return \get_post( $post_id );
		}

		$profile_uri = Webfinger::resolve( $acct );

		if ( \is_wp_error( $profile_uri ) ) {
			return $profile_uri;
		}

		$post = self::fetch_by_uri( $profile_uri );

		if ( ! \is_wp_error( $post ) ) {
			\update_post_meta( $post->ID, '_activitypub_acct', $acct );
		}

		return $post;
	}

	/**
	 * Store an error that occurred when sending an ActivityPub message to a follower.
	 *
	 * The error will be stored in post meta.
	 *
	 * @param int              $post_id The ID of the WordPress Custom-Post-Type.
	 * @param string|\WP_Error $error   The error message.
	 *
	 * @return int|false The meta ID on success, false on failure.
	 */
	public static function add_error( $post_id, $error ) {
		if ( \is_string( $error ) ) {
			$error_message = $error;
		} elseif ( \is_wp_error( $error ) ) {
			$error_message = $error->get_error_message();
		} else {
			$error_message = \__(
				'Unknown Error or misconfigured Error-Message',
				'activitypub'
			);
		}

		return \add_post_meta(
			$post_id,
			'_activitypub_errors',
			$error_message
		);
	}

	/**
	 * Count the errors for an actor.
	 *
	 * @param int $post_id The ID of the WordPress Custom-Post-Type.
	 *
	 * @return int The number of errors.
	 */
	public static function count_errors( $post_id ) {
		return \count( \get_post_meta( $post_id, '_activitypub_errors', false ) );
	}

	/**
	 * Get all error messages for an actor.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return string[] Array of error messages.
	 */
	public static function get_errors( $post_id ) {
		return \get_post_meta( $post_id, '_activitypub_errors', false );
	}

	/**
	 * Clear all errors for an actor.
	 *
	 * @param int $post_id The ID of the WordPress Custom-Post-Type.
	 *
	 * @return bool True on success, false on failure.
	 */
	public static function clear_errors( $post_id ) {
		return \delete_post_meta( $post_id, '_activitypub_errors' );
	}

	/**
	 * Get all remote actors (Custom Post Type) that had errors.
	 *
	 * @param int $number Optional. Number of actors to return. Default 20.
	 *
	 * @return \WP_Post[] Array of faulty actor posts.
	 */
	public static function get_faulty( $number = 20 ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_activitypub_errors',
					'compare' => 'EXISTS',
				),
				array(
					'key'     => '_activitypub_inbox',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_activitypub_inbox',
					'value'   => '',
					'compare' => '=',
				),
			),
		);

		return ( new \WP_Query() )->query( $args );
	}

	/**
	 * Get all remote actor posts not updated for a given time.
	 *
	 * @param int $number     Optional. Limits the result. Default 50.
	 * @param int $older_than Optional. The time in seconds. Default DAY_IN_SECONDS.
	 *
	 * @return \WP_Post[] The list of actors.
	 */
	public static function get_outdated( $number = 50, $older_than = DAY_IN_SECONDS ) {
		$args = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => $number,
			'orderby'        => 'modified',
			'order'          => 'ASC',
			'post_status'    => 'any', // 'any' includes 'trash'.
			'date_query'     => array(
				array(
					'column' => 'post_modified_gmt',
					'before' => \gmdate( 'Y-m-d', \time() - $older_than ),
				),
			),
		);

		return ( new \WP_Query() )->query( $args );
	}

	/**
	 * Convert a custom post type input to an Activitypub\Activity\Actor.
	 *
	 * @param int|\WP_Post $post The post ID or object.
	 *
	 * @return Actor|\WP_Error The actor object or WP_Error on failure.
	 */
	public static function get_actor( $post ) {
		$post = \get_post( $post );

		if ( ! $post ) {
			return new \WP_Error(
				'activitypub_actor_not_found',
				\__( 'Actor not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$json = $post->post_content;

		if ( empty( $json ) ) {
			$json = \get_post_meta( $post->ID, '_activitypub_actor_json', true );
		}

		$actor = Actor::init_from_json( $json );

		if ( \is_wp_error( $actor ) ) {
			self::add_error( $post->ID, $actor );
		}

		return $actor;
	}

	/**
	 * Prepare actor object for insert or update as a custom post type.
	 *
	 * @param Actor $actor The actor data.
	 *
	 * @return array|\WP_Error Array of post arguments or WP_Error on failure.
	 */
	private static function prepare_custom_post_type( $actor ) {
		if ( ! $actor instanceof Actor ) {
			return new \WP_Error(
				'activitypub_invalid_actor_data',
				\__( 'Invalid actor data', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		if ( ! empty( $actor->get_endpoints()['sharedInbox'] ) ) {
			$inbox = $actor->get_endpoints()['sharedInbox'];
		} elseif ( ! empty( $actor->get_inbox() ) ) {
			$inbox = $actor->get_inbox();
		} else {
			return new \WP_Error(
				'activitypub_invalid_actor_data',
				\__( 'Invalid actor data', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'guid'         => \esc_url_raw( $actor->get_id() ),
			'post_title'   => \wp_strip_all_tags( \wp_slash( $actor->get_name() ?? $actor->get_preferred_username() ) ),
			'post_author'  => 0,
			'post_type'    => self::POST_TYPE,
			'post_content' => \wp_slash( $actor->to_json() ),
			'post_excerpt' => \wp_kses( \wp_slash( (string) $actor->get_summary() ), 'user_description' ),
			'post_status'  => 'publish',
			'meta_input'   => array(
				'_activitypub_inbox' => $inbox,
			),
		);
	}

	/**
	 * Normalize actor identifier to a URI.
	 *
	 * Handles webfinger addresses, URLs without schemes, objects, and arrays.
	 *
	 * @param string|object|array $actor Actor URI, webfinger address, actor object, or array.
	 * @return string|null Normalized actor URI or null if unable to resolve.
	 */
	public static function normalize_identifier( $actor ) {
		$actor = object_to_uri( $actor );
		if ( ! is_string( $actor ) ) {
			return null;
		}

		$actor = \trim( $actor, '@' );

		// If it's an email-like webfinger address, resolve it.
		if ( \filter_var( $actor, FILTER_VALIDATE_EMAIL ) ) {
			$resolved = \Activitypub\Webfinger::resolve( $actor );
			return \is_wp_error( $resolved ) ? null : object_to_uri( $resolved );
		}

		// If it's a URL without scheme, add https://.
		if ( empty( \wp_parse_url( $actor, PHP_URL_SCHEME ) ) ) {
			$actor = \esc_url_raw( 'https://' . \ltrim( $actor, '/' ) );
		}

		return $actor;
	}

	/**
	 * Get public key from key_id.
	 *
	 * @param string $key_id The URL to the public key.
	 *
	 * @return resource|\WP_Error The public key resource or WP_Error.
	 */
	public static function get_public_key( $key_id ) {
		$actor = get_remote_metadata_by_actor( \strip_fragment_from_url( $key_id ) );
		if ( \is_wp_error( $actor ) ) {
			return new \WP_Error( 'activitypub_no_remote_profile_found', 'No Profile found or Profile not accessible', array( 'status' => 401 ) );
		}

		if ( isset( $actor['publicKey']['publicKeyPem'] ) ) {
			$key_resource = \openssl_pkey_get_public( \rtrim( $actor['publicKey']['publicKeyPem'] ) );
			if ( $key_resource ) {
				return $key_resource;
			}
		}

		return new \WP_Error( 'activitypub_no_remote_key_found', 'No Public-Key found', array( 'status' => 401 ) );
	}

	/**
	 * Get the acct of a remote actor.
	 *
	 * @uses Webfinger::uri_to_acct to resolve the acct by the actor URI.
	 * @uses Webfinger::guess       to guess a acct if the actors acct is not resolvable.
	 *
	 * @param int $id The ID of the remote actor.
	 *
	 * @return string The acct of the remote actor or empty string on failure.
	 */
	public static function get_acct( $id ) {
		$acct = \get_post_meta( $id, '_activitypub_acct', true );

		if ( $acct ) {
			return $acct;
		}

		$post = \get_post( $id );

		if ( ! $post ) {
			return '';
		}

		$acct = Webfinger::uri_to_acct( $post->guid );

		if ( \is_wp_error( $acct ) ) {
			$actor = self::get_actor( $post );
			$acct  = Webfinger::guess( $actor );
		}

		$acct = Sanitize::webfinger( $acct );

		\update_post_meta( $id, '_activitypub_acct', $acct );

		return $acct;
	}
}
