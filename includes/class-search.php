<?php
/**
 * ActivityPub Search Enhancement Class
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Interactions;

/**
 * ActivityPub Search Enhancement Class
 *
 * This class enhances WordPress search functionality to detect URLs in search queries
 * and lead to the reply stored under that URL, otherwise falls back to classic search.
 * A reply that is not stored yet can be imported from the comments screen.
 */
class Search {

	/**
	 * Initialize the search enhancement.
	 *
	 * @return void
	 */
	public static function init() {
		\add_filter( 'pre_get_posts', array( self::class, 'enhance_public_search' ) );
		\add_action( 'load-edit-comments.php', array( self::class, 'enhance_admin_comment_search' ) );
		\add_action( 'admin_post_activitypub_import_reply', array( self::class, 'import_reply' ) );
	}

	/**
	 * Enhance public search functionality to check for URLs and ActivityPub objects.
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return \WP_Query The modified query.
	 */
	public static function enhance_public_search( $query ) {
		// Check user capabilities.
		if ( ! \current_user_can( 'activitypub' ) ) {
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

		// Only a reply that is already stored, a search does not fetch.
		$existing = Comment::url_to_commentid( $search_term );
		if ( $existing ) {
			self::redirect_to_comment( $existing );
		}

		// Fall back to classic search if no reply is stored under the URL or no redirect.
		return $query;
	}

	/**
	 * Handle admin comment search to check for URLs and ActivityPub objects.
	 * Runs on admin_init to avoid infinite loops.
	 *
	 * @return void
	 */
	public static function enhance_admin_comment_search() {
		// Check user capabilities.
		if ( ! \current_user_can( 'activitypub' ) ) {
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

		$existing = Comment::url_to_commentid( $search_term );
		if ( $existing ) {
			self::redirect_to_comment( $existing );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$failed = isset( $_GET['activitypub_import'] ) && 'failed' === $_GET['activitypub_import'];

		// A reply that is not stored yet is only imported on request, see import_reply().
		\add_action(
			'admin_notices',
			static function () use ( $search_term, $failed ) {
				if ( $failed ) {
					\wp_admin_notice(
						\esc_html__( 'No reply from the Fediverse could be imported from this URL.', 'activitypub' ),
						array( 'type' => 'error' )
					);
					return;
				}

				$import_url = \wp_nonce_url(
					\add_query_arg(
						array(
							'action' => 'activitypub_import_reply',
							'url'    => \rawurlencode( $search_term ),
						),
						\admin_url( 'admin-post.php' )
					),
					'activitypub_import_reply'
				);

				\wp_admin_notice(
					\sprintf(
						/* translators: 1: The host of the URL, 2: The import link. */
						\esc_html__( 'No reply from %1$s is stored under this URL yet. %2$s', 'activitypub' ),
						\sprintf( '<code>%s</code>', \esc_html( \wp_parse_url( $search_term, PHP_URL_HOST ) ) ),
						\sprintf( '<a href="%s" class="button">%s</a>', \esc_url( $import_url ), \esc_html__( 'Import this reply', 'activitypub' ) )
					),
					array(
						'type'           => 'info',
						'paragraph_wrap' => true,
					)
				);
			}
		);
	}

	/**
	 * Import a reply from the Fediverse, requested from the comments screen.
	 *
	 * @since unreleased
	 *
	 * @return void
	 */
	public static function import_reply() {
		\check_admin_referer( 'activitypub_import_reply' );

		if ( ! \current_user_can( 'activitypub' ) ) {
			\wp_die( \esc_html__( 'Sorry, you are not allowed to import replies.', 'activitypub' ), '', array( 'response' => 403 ) );
		}

		$url = isset( $_GET['url'] ) ? \sanitize_url( \wp_unslash( $_GET['url'] ) ) : '';

		if ( \wp_http_validate_url( $url ) ) {
			$imported = self::try_import_activitypub_object( $url );

			if ( $imported ) {
				self::redirect_to_comment( $imported );
			}
		}

		\wp_safe_redirect(
			\add_query_arg(
				array(
					's'                  => \rawurlencode( $url ),
					'activitypub_import' => 'failed',
				),
				\admin_url( 'edit-comments.php' )
			)
		);
		exit;
	}

	/**
	 * Redirect to a comment, or to its moderation view when it is not approved.
	 *
	 * @since unreleased
	 *
	 * @param int $comment_id The comment ID.
	 *
	 * @return void Exits when it redirects.
	 */
	private static function redirect_to_comment( $comment_id ) {
		// The comment keeps the status moderation gave it; a search must not approve it.
		if ( '1' !== \get_comment( $comment_id )->comment_approved && \current_user_can( 'moderate_comments' ) ) {
			// Admin::edit_comment() sends received replies on to their moderation view.
			\wp_safe_redirect( \admin_url( 'comment.php?action=editcomment&c=' . $comment_id ) );
			exit;
		}

		$comment_link   = \get_comment_link( $comment_id );
		$validated_link = \wp_validate_redirect( $comment_link, \home_url() );
		if ( $validated_link ) {
			\wp_safe_redirect( $validated_link );
			exit;
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
		$object = Proxy::get( $url );
		if ( \is_wp_error( $object ) ) {
			return false;
		}

		// Check if it's a reply (has inReplyTo) with an author.
		$actor = object_to_uri( $object['attributedTo'] ?? null );
		if ( empty( $object['inReplyTo'] ) || ! $actor ) {
			return false;
		}

		$activity = array(
			'type'   => 'Create',
			'actor'  => $actor,
			'object' => $object,
		);

		/*
		 * The checks the inbox runs before a received reply is stored, see Handler\Create::handle_create().
		 * The current request is the admin's, not the author's, so its address and user agent
		 * are left out of the disallowed list here and when the comment is stored.
		 */
		$ap_activity = Activity::init_from_array( $activity );
		if (
			ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ||
			is_self_ping( $object['id'] ?? '' ) ||
			Moderation::activity_is_blocked_site_wide( $ap_activity ) ||
			Moderation::activity_is_blocked_by_content_owner( $ap_activity ) ||
			\wp_check_comment_disallowed_list( $ap_activity->to_json( false ), '', '', $ap_activity->get_content(), '', '' )
		) {
			return false;
		}

		$no_address = static function ( $comment_data ) {
			$comment_data['comment_author_IP'] = '';
			$comment_data['comment_agent']     = '';

			return $comment_data;
		};
		\add_filter( 'preprocess_comment', $no_address );

		// Import the reply as a comment; a failed import is no comment to lead to.
		$imported = Interactions::add_comment( $activity );

		\remove_filter( 'preprocess_comment', $no_address );

		return \is_wp_error( $imported ) ? false : $imported;
	}
}
