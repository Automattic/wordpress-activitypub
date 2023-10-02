<?php
namespace Activitypub;

use WP_Error;
use Activitypub\Http;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Followers;

/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_context() {
	$context = Activity::CONTEXT;

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
 * @return array The Actor profile as array
 */
function get_remote_metadata_by_actor( $actor, $cached = true ) {
	$pre = apply_filters( 'pre_get_remote_metadata_by_actor', false, $actor );
	if ( $pre ) {
		return $pre;
	}
	if ( preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $actor ) ) {
		$actor = Webfinger::resolve( $actor );
	}

	if ( ! $actor ) {
		return new WP_Error( 'activitypub_no_valid_actor_identifier', \__( 'The "actor" identifier is not valid', 'activitypub' ), array( 'status' => 404, 'actor' => $actor ) );
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
		$metadata = new WP_Error( 'activitypub_no_valid_actor_url', \__( 'The "actor" is no valid URL', 'activitypub' ), array( 'status' => 400, 'actor' => $actor ) );
		\set_transient( $transient_key, $metadata, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $metadata;
	}

	$short_timeout = function() {
		return 3;
	};
	add_filter( 'activitypub_remote_get_timeout', $short_timeout );
	$response = Http::get( $actor );
	remove_filter( 'activitypub_remote_get_timeout', $short_timeout );
	if ( \is_wp_error( $response ) ) {
		\set_transient( $transient_key, $response, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $response;
	}

	$metadata = \wp_remote_retrieve_body( $response );
	$metadata = \json_decode( $metadata, true );

	\set_transient( $transient_key, $metadata, WEEK_IN_SECONDS );

	if ( ! $metadata ) {
		$metadata = new WP_Error( 'activitypub_invalid_json', \__( 'No valid JSON data', 'activitypub' ), array( 'status' => 400, 'actor' => $actor ) );
		\set_transient( $transient_key, $metadata, HOUR_IN_SECONDS ); // Cache the error for a shorter period.
		return $metadata;
	}

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
	if ( \wp_parse_url( \site_url(), \PHP_URL_HOST ) !== \wp_parse_url( $url, \PHP_URL_HOST ) ) {
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

			if ( ! \user_can( $user_id, 'publish_posts' ) ) {
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
			$return = new WP_Error( 'activitypub_wrong_user_type', __( 'Wrong user type', 'activitypub' ), array( 'status' => 400 ) );
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

if ( ! function_exists( 'get_self_link' ) ) {
	/**
	 * Returns the link for the currently displayed feed.
	 *
	 * @return string Correct link for the atom:self element.
	 */
	function get_self_link() {
		$host = wp_parse_url( home_url() );
		$path = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		return esc_url( apply_filters( 'self_link', set_url_scheme( 'http://' . $host['host'] . $path ) ) );
	}
}

/**
 * Check if a site supports the block editor.
 *
 * @return boolean True if the site supports the block editor, false otherwise.
 */
function site_supports_blocks() {
	if ( \version_compare( \get_bloginfo( 'version' ), '5.9', '>=' ) ) {
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
