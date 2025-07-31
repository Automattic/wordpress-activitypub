<?php
/**
 * ActivityPub Search Enhancement Class
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Interactions;

/**
 * ActivityPub Search Enhancement Class
 *
 * This class enhances WordPress search functionality to detect URLs in search queries
 * and attempt to import ActivityPub objects if found, otherwise falls back to classic search.
 */
class Search {

	/**
	 * Initialize the search enhancement.
	 */
	public static function init() {
		\add_filter( 'pre_get_posts', array( self::class, 'enhance_search' ) );
	}

	/**
	 * Enhance search functionality to check for URLs and ActivityPub objects.
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return \WP_Query The modified query.
	 */
	public static function enhance_search( $query ) {
		// Only enhance main search queries on frontend.
		if ( ! $query->is_main_query() || ! $query->is_search() || is_admin() ) {
			return $query;
		}

		// Check for a valid user session.
		if ( ! is_user_logged_in() ) {
			return $query;
		}

		$search_term = $query->get( 's' );
		if ( empty( $search_term ) ) {
			return $query;
		}

		// Check if search term is a URL.
		if ( ! \wp_http_validate_url( $search_term ) ) {
			return $query;
		}

		// Try to import ActivityPub object.
		$imported = self::try_import_activitypub_object( $search_term );

		if ( $imported ) {
			$comment_link = \get_comment_link( $imported );
			$validated_link = \wp_validate_redirect( $comment_link, home_url() );
			if ( $validated_link ) {
				\wp_safe_redirect( $validated_link );
				exit;
			}
		}

		// Fall back to classic search if import failed or no redirect.
		return $query;
	}

	/**
	 * Try to import an ActivityPub reply from a URL.
	 *
	 * @param string $url The URL to check and import.
	 *
	 * @return int|false The imported comment ID or false on failure.
	 */
	private static function try_import_activitypub_object( $url ) {
		// Check if it's already imported.
		$existing = Comment::url_to_commentid( $url );
		if ( $existing ) {
			return $existing;
		}

		// Try to fetch as ActivityPub object.
		$object = Http::get_remote_object( $url );
		if ( \is_wp_error( $object ) ) {
			return false;
		}

		// Check if it's a reply (has inReplyTo).
		if ( empty( $object['inReplyTo'] ) ) {
			return false;
		}

		$activity = array(
			'type'   => 'Create',
			'actor'  => $object['attributedTo'],
			'object' => $object,
		);

		// Import the reply as a comment.
		return Interactions::add_comment( $activity );
	}
}
