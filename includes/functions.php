<?php
namespace Activitypub;

use WP_Query;
use WP_Error;
use Activitypub\Http;
use Activitypub\Comment;
use Activitypub\Webfinger;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Users;

/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_context() {
	$context = Activity::JSON_LD_CONTEXT;

	return \apply_filters( 'activitypub_json_context', $context );
}

function safe_remote_post( $url, $body, $user_id ) {
	return Http::post( $url, $body, $user_id );
}

function safe_remote_get( $url ) {
	return Http::get( $url );
}

/**
 * Returns a users WebFinger "resource"
 *
 * @param int $user_id The User-ID.
 *
 * @return string The User-Resource.
 */
function get_webfinger_resource( $user_id ) {
	return Webfinger::get_user_resource( $user_id );
}

/**
 * Requests the Meta-Data from the Actors profile
 *
 * @param string $actor  The Actor URL.
 * @param bool   $cached If the result should be cached.
 *
 * @return array|WP_Error The Actor profile as array or WP_Error on failure.
 */
function get_remote_metadata_by_actor( $actor, $cached = true ) {
	$pre = apply_filters( 'pre_get_remote_metadata_by_actor', false, $actor );
	if ( $pre ) {
		return $pre;
	}

	if ( is_array( $actor ) ) {
		if ( array_key_exists( 'id', $actor ) ) {
			$actor = $actor['id'];
		} elseif ( array_key_exists( 'url', $actor ) ) {
			$actor = $actor['url'];
		} else {
			return new WP_Error(
				'activitypub_no_valid_actor_identifier',
				\__( 'The "actor" identifier is not valid', 'activitypub' ),
				array( 'status' => 404, 'actor' => $actor )
			);
		}
	}

	if ( preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $actor ) ) {
		$actor = Webfinger::resolve( $actor );
	}

	if ( ! $actor ) {
		return new WP_Error(
			'activitypub_no_valid_actor_identifier',
			\__( 'The "actor" identifier is not valid', 'activitypub' ),
			array( 'status' => 404, 'actor' => $actor )
		);
	}

	if ( is_wp_error( $actor ) ) {
		return $actor;
	}

	$transient_key = 'activitypub_' . $actor;

	// only check the cache if needed.
	if ( $cached ) {
		$metadata = \get_transient( $transient_key );

		if ( $metadata ) {
			return $metadata;
		}
	}

	if ( ! \wp_http_validate_url( $actor ) ) {
		$metadata = new WP_Error(
			'activitypub_no_valid_actor_url',
			\__( 'The "actor" is no valid URL', 'activitypub' ),
			array( 'status' => 400, 'actor' => $actor )
		);
		return $metadata;
	}

	$response = Http::get( $actor );

	if ( \is_wp_error( $response ) ) {
		return $response;
	}

	$metadata = \wp_remote_retrieve_body( $response );
	$metadata = \json_decode( $metadata, true );

	if ( ! $metadata ) {
		$metadata = new WP_Error(
			'activitypub_invalid_json',
			\__( 'No valid JSON data', 'activitypub' ),
			array( 'status' => 400, 'actor' => $actor )
		);
		return $metadata;
	}

	\set_transient( $transient_key, $metadata, WEEK_IN_SECONDS );

	return $metadata;
}

/**
 * Returns the followers of a given user.
 *
 * @param int $user_id The User-ID.
 *
 * @return array The followers.
 */
function get_followers( $user_id ) {
	return Followers::get_followers( $user_id );
}

/**
 * Count the number of followers for a given user.
 *
 * @param int $user_id The User-ID.
 *
 * @return int The number of followers.
 */
function count_followers( $user_id ) {
	return Followers::count_followers( $user_id );
}

/**
 * Examine a url and try to determine the author ID it represents.
 *
 * Checks are supposedly from the hosted site blog.
 *
 * @param string $url Permalink to check.
 *
 * @return int User ID, or 0 on failure.
 */
function url_to_authorid( $url ) {
	global $wp_rewrite;

	// check if url hase the same host
	if ( \wp_parse_url( \home_url(), \PHP_URL_HOST ) !== \wp_parse_url( $url, \PHP_URL_HOST ) ) {
		return 0;
	}

	// first, check to see if there is a 'author=N' to match against
	if ( \preg_match( '/[?&]author=(\d+)/i', $url, $values ) ) {
		$id = \absint( $values[1] );
		if ( $id ) {
			return $id;
		}
	}

	// check to see if we are using rewrite rules
	$rewrite = $wp_rewrite->wp_rewrite_rules();

	// not using rewrite rules, and 'author=N' method failed, so we're out of options
	if ( empty( $rewrite ) ) {
		return 0;
	}

	// generate rewrite rule for the author url
	$author_rewrite = $wp_rewrite->get_author_permastruct();
	$author_regexp = \str_replace( '%author%', '', $author_rewrite );

	// match the rewrite rule with the passed url
	if ( \preg_match( '/https?:\/\/(.+)' . \preg_quote( $author_regexp, '/' ) . '([^\/]+)/i', $url, $match ) ) {
		$user = \get_user_by( 'slug', $match[2] );
		if ( $user ) {
			return $user->ID;
		}
	}

	return 0;
}

/**
 * Verify if url is a wp_ap_comment,
 * Or if it is a previously received remote comment
 *
 * @return int comment_id
 */
function is_comment() {
	$comment_id = get_query_var( 'c', null );

	if ( ! is_null( $comment_id ) ) {
		$comment = \get_comment( $comment_id );

		// Only return local origin comments
		if ( $comment && $comment->user_id ) {
			return $comment_id;
		}
	}

	return false;
}

/**
 * Check for Tombstone Objects
 *
 * @see https://www.w3.org/TR/activitypub/#delete-activity-outbox
 *
 * @param WP_Error $wp_error A WP_Error-Response of an HTTP-Request
 *
 * @return boolean true if HTTP-Code is 410 or 404
 */
function is_tombstone( $wp_error ) {
	if ( ! is_wp_error( $wp_error ) ) {
		return false;
	}

	if ( in_array( (int) $wp_error->get_error_code(), array( 404, 410 ), true ) ) {
		return true;
	}

	return false;
}

/**
 * Get the REST URL relative to this plugin's namespace.
 *
 * @param string $path Optional. REST route path. Otherwise this plugin's namespaced root.
 *
 * @return string REST URL relative to this plugin's namespace.
 */
function get_rest_url_by_path( $path = '' ) {
	// we'll handle the leading slash.
	$path = ltrim( $path, '/' );
	$namespaced_path = sprintf( '/%s/%s', ACTIVITYPUB_REST_NAMESPACE, $path );
	return \get_rest_url( null, $namespaced_path );
}

/**
 * Convert a string from camelCase to snake_case.
 *
 * @param string $string The string to convert.
 *
 * @return string The converted string.
 */
// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
function camel_to_snake_case( $string ) {
	return strtolower( preg_replace( '/(?<!^)[A-Z]/', '_$0', $string ) );
}

/**
 * Convert a string from snake_case to camelCase.
 *
 * @param string $string The string to convert.
 *
 * @return string The converted string.
 */
// phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.stringFound
function snake_to_camel_case( $string ) {
	return lcfirst( str_replace( '_', '', ucwords( $string, '_' ) ) );
}

/**
 * Escapes a Tag, to be used as a hashtag.
 *
 * @param string $string The string to escape.
 *
 * @return string The escaped hastag.
 */
function esc_hashtag( $string ) {

	$hashtag = \wp_specialchars_decode( $string, ENT_QUOTES );
	// Remove all characters that are not letters, numbers, or underscores.
	$hashtag = \preg_replace( '/emoji-regex(*SKIP)(?!)|[^\p{L}\p{Nd}_]+/u', '_', $hashtag );

	// Capitalize every letter that is preceded by an underscore.
	$hashtag = preg_replace_callback(
		'/_(.)/',
		function ( $matches ) {
			return '' . strtoupper( $matches[1] );
		},
		$hashtag
	);

	// Add a hashtag to the beginning of the string.
	$hashtag = ltrim( $hashtag, '#' );
	$hashtag = '#' . $hashtag;

	/**
	 * Allow defining your own custom hashtag generation rules.
	 *
	 * @param string $hashtag The hashtag to be returned.
	 * @param string $string  The original string.
	 */
	$hashtag = apply_filters( 'activitypub_esc_hashtag', $hashtag, $string );

	return esc_html( $hashtag );
}

/**
 * Check if a request is for an ActivityPub request.
 *
 * @return bool False by default.
 */
function is_activitypub_request() {
	global $wp_query;

	/*
	 * ActivityPub requests are currently only made for
	 * author archives, singular posts, and the homepage.
	 */
	if ( ! \is_author() && ! \is_singular() && ! \is_home() && ! defined( '\REST_REQUEST' ) ) {
		return false;
	}

	// Check if the current post type supports ActivityPub.
	if ( \is_singular() ) {
		$queried_object = \get_queried_object();
		$post_type      = \get_post_type( $queried_object );

		if ( ! \post_type_supports( $post_type, 'activitypub' ) ) {
			return false;
		}
	}

	// Check if header already sent.
	if ( ! \headers_sent() && ACTIVITYPUB_SEND_VARY_HEADER ) {
		// Send Vary header for Accept header.
		\header( 'Vary: Accept' );
	}

	// One can trigger an ActivityPub request by adding ?activitypub to the URL.
	// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.VariableRedeclaration
	global $wp_query;
	if ( isset( $wp_query->query_vars['activitypub'] ) ) {
		return true;
	}

	/*
	 * The other (more common) option to make an ActivityPub request
	 * is to send an Accept header.
	 */
	if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
		$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );

		/*
		 * $accept can be a single value, or a comma separated list of values.
		 * We want to support both scenarios,
		 * and return true when the header includes at least one of the following:
		 * - application/activity+json
		 * - application/ld+json
		 * - application/json
		 */
		if ( preg_match( '/(application\/(ld\+json|activity\+json|json))/i', $accept ) ) {
			return true;
		}
	}

	return false;
}

/**
 * This function checks if a user is disabled for ActivityPub.
 *
 * @param int $user_id The User-ID.
 *
 * @return boolean True if the user is disabled, false otherwise.
 */
function is_user_disabled( $user_id ) {
	$return = false;

	switch ( $user_id ) {
		// if the user is the application user, it's always enabled.
		case \Activitypub\Collection\Users::APPLICATION_USER_ID:
			$return = false;
			break;
		// if the user is the blog user, it's only enabled in single-user mode.
		case \Activitypub\Collection\Users::BLOG_USER_ID:
			if ( is_user_type_disabled( 'blog' ) ) {
				$return = true;
				break;
			}

			$return = false;
			break;
		// if the user is any other user, it's enabled if it can publish posts.
		default:
			if ( ! \get_user_by( 'id', $user_id ) ) {
				$return = true;
				break;
			}

			if ( is_user_type_disabled( 'user' ) ) {
				$return = true;
				break;
			}

			if ( ! \user_can( $user_id, 'activitypub' ) ) {
				$return = true;
				break;
			}

			$return = false;
			break;
	}

	return apply_filters( 'activitypub_is_user_disabled', $return, $user_id );
}

/**
 * Checks if a User-Type is disabled for ActivityPub.
 *
 * This function is used to check if the 'blog' or 'user'
 * type is disabled for ActivityPub.
 *
 * @param enum $type Can be 'blog' or 'user'.
 *
 * @return boolean True if the user type is disabled, false otherwise.
 */
function is_user_type_disabled( $type ) {
	switch ( $type ) {
		case 'blog':
			if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) ) {
				if ( ACTIVITYPUB_SINGLE_USER_MODE ) {
					$return = false;
					break;
				}
			}

			if ( \defined( 'ACTIVITYPUB_DISABLE_BLOG_USER' ) ) {
				$return = ACTIVITYPUB_DISABLE_BLOG_USER;
				break;
			}

			if ( '1' !== \get_option( 'activitypub_enable_blog_user', '0' ) ) {
				$return = true;
				break;
			}

			$return = false;
			break;
		case 'user':
			if ( \defined( 'ACTIVITYPUB_SINGLE_USER_MODE' ) ) {
				if ( ACTIVITYPUB_SINGLE_USER_MODE ) {
					$return = true;
					break;
				}
			}

			if ( \defined( 'ACTIVITYPUB_DISABLE_USER' ) ) {
				$return = ACTIVITYPUB_DISABLE_USER;
				break;
			}

			if ( '1' !== \get_option( 'activitypub_enable_users', '1' ) ) {
				$return = true;
				break;
			}

			$return = false;
			break;
		default:
			$return = new WP_Error(
				'activitypub_wrong_user_type',
				__( 'Wrong user type', 'activitypub' ),
				array( 'status' => 400 )
			);
			break;
	}

	return apply_filters( 'activitypub_is_user_type_disabled', $return, $type );
}

/**
 * Check if the blog is in single-user mode.
 *
 * @return boolean True if the blog is in single-user mode, false otherwise.
 */
function is_single_user() {
	if (
		false === is_user_type_disabled( 'blog' ) &&
		true === is_user_type_disabled( 'user' )
	) {
		return true;
	}

	return false;
}

/**
 * Check if a site supports the block editor.
 *
 * @return boolean True if the site supports the block editor, false otherwise.
 */
function site_supports_blocks() {
	if ( \version_compare( \get_bloginfo( 'version' ), '5.9', '<' ) ) {
		return false;
	}

	if ( ! \function_exists( 'register_block_type_from_metadata' ) ) {
		return false;
	}

	/**
	 * Allow plugins to disable block editor support,
	 * thus disabling blocks registered by the ActivityPub plugin.
	 *
	 * @param boolean $supports_blocks True if the site supports the block editor, false otherwise.
	 */
	return apply_filters( 'activitypub_site_supports_blocks', true );
}

/**
 * Check if data is valid JSON.
 *
 * @param string $data The data to check.
 *
 * @return boolean True if the data is JSON, false otherwise.
 */
function is_json( $data ) {
	return \is_array( \json_decode( $data, true ) ) ? true : false;
}

/**
 * Check if a blog is public based on the `blog_public` option
 *
 * @return bollean True if public, false if not
 */
function is_blog_public() {
	return (bool) apply_filters( 'activitypub_is_blog_public', \get_option( 'blog_public', 1 ) );
}

/**
 * Sanitize a URL
 *
 * @param string $value The URL to sanitize
 *
 * @return string|null The sanitized URL or null if invalid
 */
function sanitize_url( $value ) {
	if ( filter_var( $value, FILTER_VALIDATE_URL ) === false ) {
		return null;
	}

	return esc_url_raw( $value );
}

/**
 * Extract recipient URLs from Activity object
 *
 * @param array $data
 *
 * @return array The list of user URLs
 */
function extract_recipients_from_activity( $data ) {
	$recipient_items = array();

	foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $i ) {
		if ( array_key_exists( $i, $data ) ) {
			if ( is_array( $data[ $i ] ) ) {
				$recipient = $data[ $i ];
			} else {
				$recipient = array( $data[ $i ] );
			}
			$recipient_items = array_merge( $recipient_items, $recipient );
		}

		if ( is_array( $data['object'] ) && array_key_exists( $i, $data['object'] ) ) {
			if ( is_array( $data['object'][ $i ] ) ) {
				$recipient = $data['object'][ $i ];
			} else {
				$recipient = array( $data['object'][ $i ] );
			}
			$recipient_items = array_merge( $recipient_items, $recipient );
		}
	}

	$recipients = array();

	// flatten array
	foreach ( $recipient_items as $recipient ) {
		if ( is_array( $recipient ) ) {
			// check if recipient is an object
			if ( array_key_exists( 'id', $recipient ) ) {
				$recipients[] = $recipient['id'];
			}
		} else {
			$recipients[] = $recipient;
		}
	}

	return array_unique( $recipients );
}

/**
 * Check if passed Activity is Public
 *
 * @param array $data The Activity object as array
 *
 * @return boolean True if public, false if not
 */
function is_activity_public( $data ) {
	$recipients = extract_recipients_from_activity( $data );

	return in_array( 'https://www.w3.org/ns/activitystreams#Public', $recipients, true );
}

/**
 * Get active users based on a given duration
 *
 * @param int $duration The duration to check in month(s)
 *
 * @return int The number of active users
 */
function get_active_users( $duration = 1 ) {

	$duration = intval( $duration );
	$transient_key = sprintf( 'monthly_active_users_%d', $duration );
	$count = get_transient( $transient_key );

	if ( false === $count ) {
		global $wpdb;
		$query = "SELECT COUNT( DISTINCT post_author ) FROM {$wpdb->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_date <= DATE_SUB( NOW(), INTERVAL %d MONTH )";
		$query = $wpdb->prepare( $query, $duration );
		$count = $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		set_transient( $transient_key, $count, DAY_IN_SECONDS );
	}

	// if 0 authors where active
	if ( 0 === $count ) {
		return 0;
	}

	// if single user mode
	if ( is_single_user() ) {
		return 1;
	}

	// if blog user is disabled
	if ( is_user_disabled( Users::BLOG_USER_ID ) ) {
		return $count;
	}

	// also count blog user
	return $count + 1;
}

/**
 * Get the total number of users
 *
 * @return int The total number of users
 */
function get_total_users() {
	// if single user mode
	if ( is_single_user() ) {
		return 1;
	}

	$users = \get_users(
		array(
			'capability__in' => array( 'activitypub' ),
		)
	);

	if ( is_array( $users ) ) {
		$users = count( $users );
	} else {
		$users = 1;
	}

	// if blog user is disabled
	if ( is_user_disabled( Users::BLOG_USER_ID ) ) {
		return $users;
	}

	return $users + 1;
}

/**
 * Examine a comment ID and look up an existing comment it represents.
 *
 * @param string $id ActivityPub object ID (usually a URL) to check.
 *
 * @return int|boolean Comment ID, or false on failure.
 */
function object_id_to_comment( $id ) {
	return Comment::object_id_to_comment( $id );
}

/**
 * Verify if URL is a local comment,
 * Or if it is a previously received remote comment
 * (For threading comments locally)
 *
 * @param string $url The URL to check.
 *
 * @return int comment_ID or null if not found
 */
function url_to_commentid( $url ) {
	return Comment::url_to_commentid( $url );
}

/**
 * Get the URI of an ActivityPub object
 *
 * @param array $object The ActivityPub object
 *
 * @return string The URI of the ActivityPub object
 */
function object_to_uri( $object ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
	// check if it is already simple
	if ( ! $object || is_string( $object ) ) {
		return $object;
	}

	// check if it is a list, then take first item
	// this plugin does not support collections
	if ( array_is_list( $object ) ) {
		$object = $object[0];
	}

	// check if it is simplified now
	if ( is_string( $object ) ) {
		return $object;
	}

	// return part of Object that makes most sense
	switch ( $object['type'] ) {
		case 'Link':
			$object = $object['href'];
			break;
		default:
			$object = $object['id'];
			break;
	}

	return $object;
}

/**
 * Check if a comment should be federated.
 *
 * We consider a comment should be federated if it is authored by a user that is
 * not disabled for federation and if it is a reply directly to the post or to a
 * federated comment.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment should be federated, false otherwise.
 */
function should_comment_be_federated( $comment ) {
	return Comment::should_be_federated( $comment );
}

/**
 * Check if a comment was federated.
 *
 * This function checks if a comment was federated via ActivityPub.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment was federated, false otherwise.
 */
function was_comment_sent( $comment ) {
	return Comment::was_sent( $comment );
}

/**
 * Check if a comment is federated.
 *
 * We consider a comment federated if comment was received via ActivityPub.
 *
 * Use this function to check if it is comment that was received via ActivityPub.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment is federated, false otherwise.
 */
function was_comment_received( $comment ) {
	return Comment::was_received( $comment );
}

/**
 * Check if a comment is local only.
 *
 * This function checks if a comment is local only and was not sent or received via ActivityPub.
 *
 * @param mixed $comment Comment object or ID.
 *
 * @return boolean True if the comment is local only, false otherwise.
 */
function is_local_comment( $comment ) {
	return Comment::is_local( $comment );
}

/**
 * Mark a WordPress object as federated.
 *
 * @param WP_Comment|WP_Post|mixed $wp_object
 *
 * @return void
 */
function set_wp_object_state( $wp_object, $state ) {
	$meta_key = 'activitypub_status';

	if ( $wp_object instanceof \WP_Post ) {
		\update_post_meta( $wp_object->ID, $meta_key, $state );
	} elseif ( $wp_object instanceof \WP_Comment ) {
		\update_comment_meta( $wp_object->comment_ID, $meta_key, $state );
	} else {
		\apply_filters( 'activitypub_mark_wp_object_as_federated', $wp_object );
	}
}

/**
 * Get the federation state of a WordPress object.
 *
 * @param WP_Comment|WP_Post|mixed $wp_object
 *
 * @return string|false The state of the object or false if not found.
 */
function get_wp_object_state( $wp_object ) {
	$meta_key = 'activitypub_status';

	if ( $wp_object instanceof \WP_Post ) {
		return \get_post_meta( $wp_object->ID, $meta_key, true );
	} elseif ( $wp_object instanceof \WP_Comment ) {
		return \get_comment_meta( $wp_object->comment_ID, $meta_key, true );
	} else {
		return \apply_filters( 'activitypub_get_wp_object_state', false, $wp_object );
	}
}

/**
 * Get the description of a post type.
 *
 * Set some default descriptions for the default post types.
 *
 * @param WP_Post_Type $post_type The post type object.
 *
 * @return string The description of the post type.
 */
function get_post_type_description( $post_type ) {
	$description = '';

	switch ( $post_type->name ) {
		case 'post':
			$description = '';
			break;
		case 'page':
			$description = '';
			break;
		case 'attachment':
			$description = ' - ' . __( 'The attachments that you have uploaded to a post (images, videos, documents or other files).', 'activitypub' );
			break;
		default:
			if ( ! empty( $post_type->description ) ) {
				$description = ' - ' . $post_type->description;
			}
	}

	return apply_filters( 'activitypub_post_type_description', $description, $post_type->name, $post_type );
}

/**
 * Get the masked WordPress version to only show the major and minor version.
 *
 * @return string The masked version.
 */
function get_masked_wp_version() {
	// only show the major and minor version
	$version = get_bloginfo( 'version' );
	// strip the RC or beta part
	$version = preg_replace( '/-.*$/', '', $version );
	$version = explode( '.', $version );
	$version = array_slice( $version, 0, 2 );

	return implode( '.', $version );
}

/**
 * Get the enclosures of a post.
 *
 * @param int $post_id The post ID.
 *
 * @return array The enclosures.
 */
function get_enclosures( $post_id ) {
	$enclosures = get_post_meta( $post_id, 'enclosure' );

	if ( ! $enclosures ) {
		return array();
	}

	$enclosures = array_map(
		function ( $enclosure ) {
			$attributes = explode( "\n", $enclosure );

			if ( ! isset( $attributes[0] ) || ! \wp_http_validate_url( $attributes[0] ) ) {
				return false;
			}

			return array(
				'url' => $attributes[0],
				'length' => isset( $attributes[1] ) ? trim( $attributes[1] ) : null,
				'mediaType' => isset( $attributes[2] ) ? trim( $attributes[2] ) : null,
			);
		},
		$enclosures
	);

	return array_filter( $enclosures );
}

/**
 * Retrieves the IDs of the ancestors of a comment.
 *
 * Adaption of `get_post_ancestors` from WordPress core.
 *
 * @see https://developer.wordpress.org/reference/functions/get_post_ancestors/
 *
 * @param int|WP_Comment $comment Comment ID or comment object.
 *
 * @return WP_Comment[] Array of ancestor comments or empty array if there are none.
 */
function get_comment_ancestors( $comment ) {
	$comment = \get_comment( $comment );

	// phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual
	if ( ! $comment || empty( $comment->comment_parent ) || $comment->comment_parent == $comment->comment_ID ) {
		return array();
	}

	$ancestors = array();

	$id          = (int) $comment->comment_parent;
	$ancestors[] = $id;

	// phpcs:ignore Generic.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition
	while ( $id > 0 ) {
		$ancestor = \get_comment( $id );
		$parent_id = (int) $ancestor->comment_parent;

		// Loop detection: If the ancestor has been seen before, break.
		if ( empty( $parent_id ) || ( $parent_id === (int) $comment->comment_ID ) || in_array( $parent_id, $ancestors, true ) ) {
			break;
		}

		$id          = $parent_id;
		$ancestors[] = $id;
	}

	return $ancestors;
}

/**
 * Change the display of large numbers on the site.
 *
 * @author Jeremy Herve
 *
 * @see https://wordpress.org/support/topic/abbreviate-numbers-with-k/
 *
 * @param string $formatted Converted number in string format.
 * @param float  $number    The number to convert based on locale.
 * @param int    $decimals  Precision of the number of decimal places.
 *
 * @return string Converted number in string format.
 */
function custom_large_numbers( $formatted, $number, $decimals ) {
	global $wp_locale;

	$decimals      = 0;
	$decimal_point = '.';
	$thousands_sep = ',';

	if ( isset( $wp_locale ) ) {
		$decimals      = (int) $wp_locale->number_format['decimal_point'];
		$decimal_point = $wp_locale->number_format['decimal_point'];
		$thousands_sep = $wp_locale->number_format['thousands_sep'];
	}

	if ( $number < 1000 ) { // any number less than a Thousand.
		return \number_format( $number, $decimals, $decimal_point, $thousands_sep );
	} elseif ( $number < 1000000 ) { // any number less than a million
		return \number_format( $number / 1000, $decimals, $decimal_point, $thousands_sep ) . 'K';
	} elseif ( $number < 1000000000 ) { // any number less than a billion
		return \number_format( $number / 1000000, $decimals, $decimal_point, $thousands_sep ) . 'M';
	} else { // at least a billion
		return \number_format( $number / 1000000000, $decimals, $decimal_point, $thousands_sep ) . 'B';
	}

	// Default fallback. We should not get here.
	return $formatted;
}

/**
 * Normalize a URL.
 *
 * @param string $url The URL.
 *
 * @return string The normalized URL.
 */
function normalize_url( $url ) {
	$url = \untrailingslashit( $url );
	$url = \str_replace( 'https://', '', $url );
	$url = \str_replace( 'http://', '', $url );
	$url = \str_replace( 'www.', '', $url );

	return $url;
}

/**
 * Normalize a host.
 *
 * @param string $host The host.
 *
 * @return string The normalized host.
 */
function normalize_host( $host ) {
	return \str_replace( 'www.', '', $host );
}

/**
 * Get the Extra Fields of an Actor
 *
 * @param int $user_id The User-ID.
 *
 * @return array The extra fields.
 */
function get_actor_extra_fields( $user_id ) {
	$extra_fields = new WP_Query(
		array(
			'post_type' => 'ap_extrafield',
			'nopaging'  => true,
			'status'    => 'publish',
			'author'    => $user_id,
			'orderby'   => 'menu_order',
			'order'     => 'ASC',
		)
	);

	if ( $extra_fields->have_posts() ) {
		$extra_fields = $extra_fields->posts;
	} else {
		$extra_fields = array();
	}

	return apply_filters( 'activitypub_get_actor_extra_fields', $extra_fields, $user_id );
}

/**
 * Get the reply intent URI.
 *
 * @return string The reply intent URI.
 */
function get_reply_intent_uri() {
	return sprintf(
		'javascript:(()=>{window.open(\'%s\'+encodeURIComponent(window.location.href));})();',
		esc_url( \admin_url( 'post-new.php?in_reply_to=' ) )
	);
}
