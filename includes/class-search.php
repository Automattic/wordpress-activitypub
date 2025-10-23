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
		\add_filter( 'pre_get_posts', array( self::class, 'enhance_public_search' ) );
		\add_action( 'load-edit-comments.php', array( self::class, 'enhance_admin_comment_search' ) );
	}

	/**
	 * Enhance public search functionality to check for URLs and ActivityPub objects.
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return \WP_Query The modified query.
	 */
	public static function enhance_public_search( $query ) {
		// Check user capabilities.
		if ( ! current_user_can( 'activitypub' ) ) {
			return $query;
		}

		// Only enhance main search queries on frontend.
		if ( ! $query->is_main_query() || ! $query->is_search() || \is_admin() ) {
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
			// Ensure the imported comment is approved/published.
			\wp_set_comment_status( $imported, 'approve' );
			$comment_link   = \get_comment_link( $imported );
			$validated_link = \wp_validate_redirect( $comment_link, \home_url() );
			if ( $validated_link ) {
				\wp_safe_redirect( $validated_link );
				exit;
			}
		}

		// Fall back to classic search if import failed or no redirect.
		return $query;
	}

	/**
	 * Handle admin comment search to check for URLs and ActivityPub objects.
	 * Runs on admin_init to avoid infinite loops.
	 */
	public static function enhance_admin_comment_search() {
		// Check user capabilities.
		if ( ! current_user_can( 'activitypub' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search_term = isset( $_GET['s'] ) ? \sanitize_text_field( \wp_unslash( $_GET['s'] ) ) : '';
		if ( empty( $search_term ) ) {
			return;
		}

		// Check if search term is a URL.
		if ( ! \wp_http_validate_url( $search_term ) ) {
			return;
		}

		// Try to import ActivityPub object.
		$imported = self::try_import_activitypub_object( $search_term );

		if ( $imported ) {
			// Ensure the imported comment is approved/published.
			\wp_set_comment_status( $imported, 'approve' );
			$comment_link   = \get_comment_link( $imported );
			$validated_link = \wp_validate_redirect( $comment_link, \home_url() );
			if ( $validated_link ) {
				\wp_safe_redirect( $validated_link );
				exit;
			}
		}
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
