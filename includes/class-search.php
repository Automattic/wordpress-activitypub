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
	 * Lead a search for a URL to the stored reply, or to the comments screen to import it, see enhance_admin_comment_search().
	 *
	 * @param \WP_Query $query The WP_Query instance.
	 * @return \WP_Query The unchanged query, when it does not redirect.
	 */
	public static function enhance_public_search( $query ) {
		// Only enhance main search queries on frontend.
		if ( ! $query->is_main_query() || ! $query->is_search() || $query->is_feed() || \is_admin() ) {
			return $query;
		}

		// Check user capabilities, the comments screen needs `edit_posts`.
		if ( ! \current_user_can( 'activitypub' ) || ! \current_user_can( 'edit_posts' ) ) {
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

		$existing = Comment::url_to_commentid( $search_term );
		if ( $existing ) {
			self::redirect_to_comment( $existing );
			return $query;
		}

		// A URL that leads to nothing from the Fediverse is a plain search, as on the comments screen.
		$reply = self::is_importable_reply( $search_term );
		if ( \is_wp_error( $reply ) && 'activitypub_import_not_found' === $reply->get_error_code() ) {
			return $query;
		}

		// The comments screen offers to import the reply, or says why it cannot be imported.
		\wp_safe_redirect( \add_query_arg( 's', \rawurlencode( $search_term ), \admin_url( 'edit-comments.php' ) ) );
		exit;
	}

	/**
	 * Lead a comment search for a URL to the stored reply, or offer to import it.
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

		// The reason a requested import failed, see import_reply().
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$failed = isset( $_GET['activitypub_import'] ) ? \sanitize_key( $_GET['activitypub_import'] ) : '';

		/*
		 * A reply that is not stored yet is only imported on request, see import_reply().
		 * The search checks it without storing anything, so the import is only offered when it works.
		 */
		$reply = self::is_importable_reply( $search_term );

		// A URL that leads to nothing from the Fediverse is a plain search.
		if ( ! $failed && \is_wp_error( $reply ) && 'activitypub_import_not_found' === $reply->get_error_code() ) {
			return;
		}

		// Why the reply cannot be imported, or an empty string when it can.
		$reason = '';
		if ( \is_wp_error( $reply ) ) {
			$reason = \esc_html( $reply->get_error_message() );
		} elseif ( 'comment_duplicate' === $failed ) {
			$reason = \sprintf(
				/* translators: %s: Link to the comments that wait for moderation. */
				\esc_html__( 'The same comment from this author is already stored, maybe it is %s.', 'activitypub' ),
				\sprintf( '<a href="%s">%s</a>', \esc_url( \admin_url( 'edit-comments.php?comment_status=moderated' ) ), \esc_html__( 'waiting for moderation', 'activitypub' ) )
			);
		} elseif ( $failed ) {
			$reason = \esc_html__( 'Storing it failed.', 'activitypub' );
		}

		\add_action(
			'admin_notices',
			static function () use ( $search_term, $reason, $reply ) {
				if ( $reason ) {
					\wp_admin_notice(
						\sprintf(
							/* translators: %s: Why the reply cannot be imported. */
							\esc_html__( '⁂ This reply cannot be imported: %s', 'activitypub' ),
							$reason
						),
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
						/* translators: 1: The author of the reply, 2: The post it replies to, 3: The import link. */
						\esc_html__( '⁂ Reply from %1$s to %2$s. %3$s', 'activitypub' ),
						\sprintf( '<code>%s</code>', \esc_html( $reply['activity']['actor'] ) ),
						\sprintf( '<a href="%s">%s</a>', \esc_url( \get_permalink( $reply['post_id'] ) ), \esc_html( \get_the_title( $reply['post_id'] ) ) ),
						\sprintf( '<a href="%s" class="button">%s</a>', \esc_url( $import_url ), \esc_html__( 'Import reply', 'activitypub' ) )
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

		$imported = false;
		if ( \wp_http_validate_url( $url ) ) {
			$imported = self::try_import_activitypub_object( $url );

			if ( $imported && ! \is_wp_error( $imported ) ) {
				self::redirect_to_comment( $imported );
			}
		}

		\wp_safe_redirect(
			\add_query_arg(
				array(
					's'                  => \rawurlencode( $url ),
					'activitypub_import' => \is_wp_error( $imported ) ? $imported->get_error_code() : 'failed',
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
	 * Check whether the object at a URL can be imported as a reply, without storing anything.
	 *
	 * @since unreleased
	 *
	 * @param string $url The URL to check.
	 *
	 * @return array|\WP_Error The Create activity and the ID of the post it replies to, or the reason it cannot be imported.
	 */
	private static function is_importable_reply( $url ) {
		$object = Proxy::get( $url );
		if ( \is_wp_error( $object ) || ! \is_array( $object ) ) {
			return new \WP_Error( 'activitypub_import_not_found', \__( 'This URL does not lead to anything from the Fediverse.', 'activitypub' ) );
		}

		$activity = array(
			'type'   => 'Create',
			'actor'  => object_to_id( $object['attributedTo'] ?? null ),
			'object' => $object,
		);

		if ( ! is_activity_reply( $activity ) ) {
			return new \WP_Error( 'activitypub_import_not_a_reply', \__( 'This URL is not a reply.', 'activitypub' ) );
		}

		if ( ! $activity['actor'] ) {
			return new \WP_Error( 'activitypub_import_no_author', \__( 'This reply has no author.', 'activitypub' ) );
		}

		// Interactions::add_comment() refuses both, the check only says why.
		if ( ! is_same_host( $activity['actor'], object_to_id( $object ) ) ) {
			return new \WP_Error( 'activitypub_import_other_host', \__( 'The author of this reply is not on the server it comes from.', 'activitypub' ) );
		}

		$target  = object_to_id( $object['inReplyTo'] );
		$post_id = get_target_post_id( $target, url_to_commentid( $target ) );

		// The notice shows the post's title, so it has to be one anyone can read. Stored remote posts are public.
		if ( ! $post_id || ( ! is_ap_post( $post_id ) && ! is_post_publicly_queryable( $post_id ) ) ) {
			return new \WP_Error( 'activitypub_import_not_here', \__( 'This is not a reply to one of your posts.', 'activitypub' ) );
		}

		// The checks the inbox runs before a received reply is stored, see Handler\Create::handle_create().
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			return new \WP_Error( 'activitypub_import_not_public', \__( 'This reply is not public.', 'activitypub' ) );
		}

		if ( is_self_ping( $object['id'] ?? '' ) ) {
			return new \WP_Error( 'activitypub_import_self', \__( 'This is a comment from your own site.', 'activitypub' ) );
		}

		/*
		 * The current request is the admin's, not the author's, so its address and user agent
		 * are left out of the disallowed list here and when the comment is stored.
		 */
		$ap_activity = Activity::init_from_array( $activity );
		if (
			Moderation::activity_is_blocked_site_wide( $ap_activity ) ||
			Moderation::activity_is_blocked_by_content_owner( $ap_activity ) ||
			\wp_check_comment_disallowed_list( $ap_activity->to_json( false ), '', '', $ap_activity->get_content(), '', '' )
		) {
			return new \WP_Error( 'activitypub_import_blocked', \__( 'This reply is blocked by your moderation settings.', 'activitypub' ) );
		}

		return array(
			'activity' => $activity,
			'post_id'  => $post_id,
		);
	}

	/**
	 * Try to import an ActivityPub reply from a URL.
	 *
	 * @param string $url The URL to check and import.
	 *
	 * @return int|false|\WP_Error The imported comment ID, false when it cannot be imported, or the error storing it.
	 */
	private static function try_import_activitypub_object( $url ) {
		// Check if it's already imported.
		$existing = Comment::url_to_commentid( $url );
		if ( $existing ) {
			return $existing;
		}

		$reply = self::is_importable_reply( $url );
		if ( \is_wp_error( $reply ) ) {
			return false;
		}

		$activity = $reply['activity'];

		$no_address = static function ( $comment_data ) {
			$comment_data['comment_author_IP'] = '';
			$comment_data['comment_agent']     = '';

			return $comment_data;
		};
		\add_filter( 'preprocess_comment', $no_address );

		// Import the reply as a comment; a failed import is no comment to lead to.
		$imported = Interactions::add_comment( $activity );

		\remove_filter( 'preprocess_comment', $no_address );

		return $imported;
	}
}
