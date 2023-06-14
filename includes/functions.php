<?php
namespace Activitypub;

/**
 * Returns the ActivityPub default JSON-context
 *
 * @return array the activitypub context
 */
function get_context() {
	$context = Model\Activity::CONTEXT;

	return \apply_filters( 'activitypub_json_context', $context );
}

function safe_remote_post( $url, $body, $user_id ) {
	return \Activitypub\Http::post( $url, $body, $user_id );
}

function safe_remote_get( $url ) {
	return \Activitypub\Http::get( $url );
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
		return null;
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
		$metadata = new \WP_Error( 'activitypub_no_valid_actor_url', \__( 'The "actor" is no valid URL', 'activitypub' ), $actor );
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
		$metadata = new \WP_Error( 'activitypub_invalid_json', \__( 'No valid JSON data', 'activitypub' ), $actor );
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
	return Collection\Followers::get_followers( $user_id );
}

/**
 * Count the number of followers for a given user.
 *
 * @param int $user_id The User-ID.
 *
 * @return int The number of followers.
 */
function count_followers( $user_id ) {
	return Collection\Followers::count_followers( $user_id );
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
function snake_to_camel_case( $string ) {
	return lcfirst( str_replace( '_', '', ucwords( $string, '_' ) ) );
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
	if ( ! \is_author() && ! \is_singular() && ! \is_home() && ! defined( 'REST_REQUEST' ) && ! REST_REQUEST ) {
		return false;
	}

	// One can trigger an ActivityPub request by adding ?activitypub to the URL.
	global $wp_query;
	if ( isset( $wp_query->query_vars['activitypub'] ) ) {
		return true;
	}

	/*
	 * The other (more common) option to make an ActivityPub request
	 * is to send an Accept header.
	 */
	if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
		$accept = $_SERVER['HTTP_ACCEPT'];

		/*
		 * $accept can be a single value, or a comma separated list of values.
		 * We want to support both scenarios,
		 * and return true when the header includes at least one of the following:
		 * - application/activity+json
		 * - application/ld+json
		 */
		if ( preg_match( '/(application\/(ld\+json|activity\+json))/', $accept ) ) {
			return true;
		}
	}

	return false;
}

/**
 * Check if the current site is in single-user mode.
 *
 * @return boolean
 */
function is_single_user_mode() {
	return ACTIVITYPUB_SINGLE_USER_MODE;
}
