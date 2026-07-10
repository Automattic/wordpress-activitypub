<?php
/**
 * Interactions collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Comment;
use Activitypub\Emoji;
use Activitypub\Webfinger;

use function Activitypub\get_remote_metadata_by_actor;
use function Activitypub\is_ap_post;
use function Activitypub\is_post_disabled;
use function Activitypub\object_id_to_comment;
use function Activitypub\object_to_uri;
use function Activitypub\url_to_commentid;

/**
 * ActivityPub Interactions Collection.
 */
class Interactions {
	const INSERT = 'insert';
	const UPDATE = 'update';

	/**
	 * Add a comment to a post.
	 *
	 * When $user_id is provided, comment author data is built from the
	 * local WordPress user instead of fetching remote actor metadata.
	 *
	 * @param array    $activity The activity-object.
	 * @param int|null $user_id  Optional. Local user ID for outbox replies.
	 *
	 * @return int|false|\WP_Error The comment ID or false or WP_Error on failure.
	 */
	public static function add_comment( $activity, $user_id = null ) {
		// Determine target URL from reply or quote.
		$parent_comment_id = 0;
		$is_quote          = false;

		if ( ! empty( $activity['object']['inReplyTo'] ) ) {
			// Regular reply.
			$target_url        = object_to_uri( $activity['object']['inReplyTo'] );
			$parent_comment_id = url_to_commentid( $target_url );
		} else {
			// Check for quote.
			$target_url = self::get_quote_url( $activity );

			if ( ! $target_url ) {
				return false;
			}

			$is_quote = true;
		}

		$comment_post_id = self::resolve_post_id( $target_url, $parent_comment_id );

		if ( ! $comment_post_id ) {
			// Not a reply to a post or comment.
			return false;
		}

		$comment_data = self::activity_to_comment( $activity, $user_id );

		if ( ! $comment_data ) {
			return false;
		}

		if ( $is_quote ) {
			$comment_data['comment_type'] = 'quote';

			if ( ! empty( $activity['object']['content'] ) ) {
				$pattern                         = '/<p[^>]*class=["\']quote-inline["\'][^>]*>.*?<\/p>/is';
				$cleaned_content                 = \preg_replace( $pattern, '', $activity['object']['content'], 1 );
				$comment_data['comment_content'] = \wp_kses_post( $cleaned_content );
			}
		}

		$comment_data['comment_post_ID'] = $comment_post_id;
		$comment_data['comment_parent']  = $parent_comment_id ? $parent_comment_id : 0;

		return self::persist( $comment_data );
	}

	/**
	 * Update a comment.
	 *
	 * @param array $activity The activity object.
	 *
	 * @return array|string|int|\WP_Error|false The comment data or false on failure.
	 */
	public static function update_comment( $activity ) {
		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		if ( \is_wp_error( $meta ) || ! \is_array( $meta ) ) {
			return $meta;
		}

		// Determine comment_ID.
		$comment      = object_id_to_comment( \esc_url_raw( $activity['object']['id'] ) );
		$comment_data = \get_comment( $comment, ARRAY_A );

		if ( ! $comment_data ) {
			return false;
		}

		/*
		 * Only the comment's author may update it. The comment maps to the remote actor that
		 * created it via _activitypub_remote_actor_id; that actor post's guid is the
		 * (signature-bound) actor URI. The Update's actor must match it, otherwise a remote
		 * server could rewrite another actor's comment by sending an Update whose object.id
		 * points at it.
		 *
		 * Comments created before this mapping existed have no owner recorded; those are let
		 * through for backward compatibility (matching the Undo path) rather than becoming
		 * permanently un-editable. On mismatch, return a WP_Error rather than false: false would
		 * make the Update handler fall back to Create (which re-dispatches to Update for an
		 * existing comment and recurses), while the unchanged comment array would be read as a
		 * successful update and relayed onward. A WP_Error is handled but unsuccessful: no Create
		 * fallback, and the handled-update success flag stays false.
		 */
		$owner = \get_post( (int) \get_comment_meta( $comment_data['comment_ID'], '_activitypub_remote_actor_id', true ) );
		if ( $owner instanceof \WP_Post && object_to_uri( $activity['actor'] ) !== $owner->guid ) {
			return new \WP_Error(
				'activitypub_update_forbidden',
				\__( 'The Update actor does not own the target comment.', 'activitypub' )
			);
		}

		// Found a local comment id.
		$comment_data['comment_author'] = \sanitize_text_field( empty( $meta['name'] ) ? $meta['preferredUsername'] : $meta['name'] );

		/*
		 * Wrap emoji in content with blocks for runtime replacement.
		 * Note: Remote images in comments are stripped for security (only emoji allowed).
		 */
		$content                         = Emoji::wrap_in_content( $activity['object']['content'], $activity['object'] );
		$comment_data['comment_content'] = \addslashes( $content );

		return self::persist( $comment_data, self::UPDATE );
	}

	/**
	 * Adds an incoming Like, Announce, ... as a comment to a post.
	 *
	 * @param array $activity Activity array.
	 *
	 * @return array|string|int|\WP_Error|false Comment data or `false` on failure.
	 */
	public static function add_reaction( $activity ) {
		$url               = object_to_uri( $activity['object'] );
		$parent_comment_id = url_to_commentid( $url );
		$comment_post_id   = self::resolve_post_id( $url, $parent_comment_id );

		if ( ! $comment_post_id ) {
			// Not a reply to a post or comment.
			return false;
		}

		$comment_type = Comment::get_comment_type_by_activity_type( $activity['type'] );
		if ( ! $comment_type ) {
			// Not a valid comment type.
			return false;
		}

		$comment_data = self::activity_to_comment( $activity );
		if ( ! $comment_data ) {
			return false;
		}

		$comment_data['comment_post_ID']           = $comment_post_id;
		$comment_data['comment_parent']            = $parent_comment_id ? $parent_comment_id : 0;
		$comment_data['comment_content']           = \esc_html( $comment_type['excerpt'] );
		$comment_data['comment_type']              = \esc_attr( $comment_type['type'] );
		$comment_data['comment_meta']['source_id'] = \esc_url_raw( $activity['id'] );

		return self::persist( $comment_data );
	}

	/**
	 * Resolve an interaction target to its WordPress post ID.
	 *
	 * @since unreleased
	 *
	 * @param string $url               The target URL.
	 * @param int    $parent_comment_id Optional. The resolved parent comment ID.
	 *
	 * @return int The post ID, or 0 when the target is unknown.
	 */
	private static function resolve_post_id( $url, $parent_comment_id = 0 ) {
		if ( ! $url ) {
			return 0;
		}

		$url     = \esc_url_raw( $url );
		$post_id = \url_to_postid( $url );

		if ( ! $post_id ) {
			$remote_post = Remote_Posts::get_by_guid( $url );
			if ( $remote_post instanceof \WP_Post ) {
				$post_id = $remote_post->ID;
			}
		}

		if ( ! $post_id && $parent_comment_id ) {
			$parent_comment = \get_comment( $parent_comment_id );
			if ( $parent_comment instanceof \WP_Comment ) {
				$post_id = $parent_comment->comment_post_ID;
			}
		}

		return (int) $post_id;
	}

	/**
	 * Get interaction(s) by ID.
	 *
	 * @param string $url The URL/ID to get interactions for.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_by_id( $url ) {
		$args = array(
			'nopaging'   => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
				),
				array(
					'relation' => 'OR',
					array(
						'key'   => 'source_url',
						'value' => $url,
					),
					array(
						'key'   => 'source_id',
						'value' => $url,
					),
				),
			),
		);

		$query = new \WP_Comment_Query( $args );
		return $query->comments;
	}

	/**
	 * Get interaction(s) for a given URL/ID.
	 *
	 * @deprecated 7.6.0 Use {@see Interactions::get_by_id()}.
	 *
	 * @param string $url The URL/ID to get interactions for.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_interaction_by_id( $url ) {
		\_deprecated_function( __METHOD__, '7.6.0', 'Activitypub\Collection\Interactions::get_by_id' );

		return self::get_by_id( $url );
	}

	/**
	 * Get interaction(s) by actor.
	 *
	 * @param string $actor The Actor-URL.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_by_actor( $actor ) {
		$meta = get_remote_metadata_by_actor( $actor );

		// Get URL, because $actor seems to be the ID.
		if ( $meta && ! \is_wp_error( $meta ) && isset( $meta['url'] ) ) {
			$actor = object_to_uri( $meta['url'] );
		}

		$args = array(
			'nopaging'   => true,
			'author_url' => $actor,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
				),
			),
		);

		return \get_comments( $args );
	}

	/**
	 * Get interaction(s) by remote actor ID.
	 *
	 * This is an optimized query that uses the remote actor post ID directly
	 * instead of querying by author_url.
	 *
	 * @param int $remote_actor_id The remote actor post ID.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_by_remote_actor_id( $remote_actor_id ) {
		$args = array(
			'nopaging'   => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
				'relation' => 'AND',
				array(
					'key'   => 'protocol',
					'value' => 'activitypub',
				),
				array(
					'key'   => '_activitypub_remote_actor_id',
					'value' => $remote_actor_id,
				),
			),
		);

		return \get_comments( $args );
	}

	/**
	 * Get interaction(s) for a given actor.
	 *
	 * @deprecated 7.6.0 Use {@see Interactions::get_by_actor()}.
	 *
	 * @param string $actor The Actor-URL.
	 *
	 * @return array The interactions as WP_Comment objects.
	 */
	public static function get_interactions_by_actor( $actor ) {
		\_deprecated_function( __METHOD__, '7.6.0', 'Activitypub\Collection\Interactions::get_by_actor' );

		return self::get_by_actor( $actor );
	}

	/**
	 * Adds line breaks to the list of allowed comment tags.
	 *
	 * @param  array  $allowed_tags Allowed HTML tags.
	 * @param  string $context      Optional. Context. Default empty.
	 *
	 * @return array Filtered tag list.
	 */
	public static function allowed_comment_html( $allowed_tags, $context = '' ) {
		if ( 'pre_comment_content' !== $context ) {
			// Do nothing.
			return $allowed_tags;
		}

		// Add `p` and `br` to the list of allowed tags.
		if ( ! \array_key_exists( 'br', $allowed_tags ) ) {
			$allowed_tags['br'] = array();
		}

		if ( ! \array_key_exists( 'p', $allowed_tags ) ) {
			$allowed_tags['p'] = array();
		}

		// Add `img` for custom emoji support with strict validation.
		$emoji_html = Emoji::get_kses_allowed_html();
		if ( ! \array_key_exists( 'img', $allowed_tags ) ) {
			$allowed_tags['img'] = $emoji_html['img'];
		}

		return $allowed_tags;
	}

	/**
	 * Force Akismet's comment nonce check to `inactive` while persisting.
	 *
	 * Inbound activities have no browser-issued nonce, so Akismet's nonce
	 * verification cannot apply to this submission route. A named method (rather
	 * than an anonymous closure) is used so it can be removed by reference again.
	 *
	 * @return string Always `inactive`.
	 */
	public static function akismet_comment_nonce_inactive() {
		return 'inactive';
	}

	/**
	 * Convert an Activity to a WP_Comment.
	 *
	 * When $user_id is provided, comment author data is built from the
	 * local WordPress user instead of fetching remote actor metadata.
	 *
	 * @param array    $activity The Activity array.
	 * @param int|null $user_id  Optional. Local user ID for outbox comments.
	 *
	 * @return array|false The comment data or false on failure.
	 */
	public static function activity_to_comment( $activity, $user_id = null ) {
		$comment_content = null;

		if ( $user_id ) {
			// Outbox: resolve author from the local WordPress user.
			$user = \get_userdata( $user_id );

			if ( ! $user ) {
				return false;
			}

			$comment_author       = $user->display_name;
			$comment_author_url   = $user->user_url;
			$comment_author_email = $user->user_email;
			$comment_content      = \wp_kses_post( $activity['object']['content'] ?? '' );
		} else {
			// S2S: resolve author from remote actor metadata.
			$actor = object_to_uri( $activity['actor'] ?? null );
			$actor = get_remote_metadata_by_actor( $actor );

			if ( ! $actor || \is_wp_error( $actor ) ) {
				return false;
			}

			$comment_author = null;
			if ( ! empty( $actor['name'] ) ) {
				$comment_author = $actor['name'];
			} elseif ( ! empty( $actor['preferredUsername'] ) ) {
				$comment_author = $actor['preferredUsername'];
			}

			if ( empty( $comment_author ) && \get_option( 'require_name_email' ) ) {
				return false;
			}

			$comment_author     = $comment_author ?? \__( 'Anonymous', 'activitypub' );
			$comment_author_url = \esc_url_raw( object_to_uri( $actor['url'] ?? $actor['id'] ) );

			$webfinger = Webfinger::uri_to_acct( $comment_author_url );
			if ( \is_wp_error( $webfinger ) ) {
				$comment_author_email = '';
			} else {
				$comment_author_email = \str_replace( 'acct:', '', $webfinger );
			}

			if ( isset( $activity['object']['content'] ) ) {
				/*
				 * Wrap emoji in content with blocks for runtime replacement.
				 * Note: Remote images in comments are stripped for security (only emoji allowed).
				 */
				$content         = Emoji::wrap_in_content( $activity['object']['content'], $activity['object'] );
				$comment_content = \addslashes( $content );
			}
		}

		$published = $activity['object']['published'] ?? $activity['published'] ?? 'now';
		$gm_date   = \gmdate( 'Y-m-d H:i:s', \strtotime( $published ) );

		$comment_data = array(
			'comment_author'       => $comment_author,
			'comment_author_url'   => $comment_author_url,
			'comment_content'      => $comment_content,
			'comment_type'         => 'comment',
			'comment_author_email' => $comment_author_email,
			'comment_date'         => \get_date_from_gmt( $gm_date ),
			'comment_date_gmt'     => $gm_date,
			'comment_meta'         => array(),
		);

		if ( $user_id ) {
			$comment_data['user_id'] = $user_id;
		} else {
			$comment_data['comment_meta']['protocol']  = 'activitypub';
			$comment_data['comment_meta']['source_id'] = \esc_url_raw( object_to_uri( $activity['object'] ) );

			// Store reference to remote actor post.
			$actor_uri = object_to_uri( $activity['actor'] ?? null );
			if ( $actor_uri ) {
				$remote_actor = Remote_Actors::get_by_uri( $actor_uri );
				if ( ! \is_wp_error( $remote_actor ) ) {
					$comment_data['comment_meta']['_activitypub_remote_actor_id'] = $remote_actor->ID;
				}
			}

			if ( isset( $activity['object']['url'] ) ) {
				$comment_data['comment_meta']['source_url'] = \esc_url_raw( object_to_uri( $activity['object']['url'] ) );
			}
		}

		return $comment_data;
	}

	/**
	 * Persist a comment.
	 *
	 * @param array  $comment_data The comment data array.
	 * @param string $action       Optional. Either 'insert' or 'update'. Default 'insert'.
	 *
	 * @return array|string|int|\WP_Error|false The comment data or false on failure
	 */
	public static function persist( $comment_data, $action = self::INSERT ) {
		if (
			is_post_disabled( $comment_data['comment_post_ID'] ) &&
			! is_ap_post( $comment_data['comment_post_ID'] )
		) {
			return false;
		}

		$is_insert          = self::INSERT === $action;
		$flood_priority     = \has_action( 'check_comment_flood', 'check_comment_flood_db' );
		$had_name_filter    = false !== \has_filter( 'pre_option_require_name_email', '__return_false' );
		$akismet_callback   = array( self::class, 'akismet_comment_nonce_inactive' );
		$had_akismet_filter = false !== \has_filter( 'akismet_comment_nonce', $akismet_callback );
		$kses_callback      = array( self::class, 'allowed_comment_html' );
		$had_allowed_html   = false !== \has_filter( 'wp_kses_allowed_html', $kses_callback );

		// Disable flood control.
		if ( false !== $flood_priority ) {
			\remove_action( 'check_comment_flood', 'check_comment_flood_db', $flood_priority );
		}

		// Do not require email for AP entries.
		if ( ! $had_name_filter ) {
			\add_filter( 'pre_option_require_name_email', '__return_false' );
		}

		// No nonce possible for this submission route.
		if ( ! $had_akismet_filter ) {
			\add_filter( 'akismet_comment_nonce', $akismet_callback );
		}

		if ( ! $had_allowed_html ) {
			\add_filter( 'wp_kses_allowed_html', $kses_callback, 10, 2 );
		}

		if ( $is_insert ) {
			$state = \wp_new_comment( $comment_data, true );
		} else {
			$state = \wp_update_comment( $comment_data, true );
		}

		if ( ! $had_allowed_html ) {
			\remove_filter( 'wp_kses_allowed_html', $kses_callback );
		}

		if ( ! $had_akismet_filter ) {
			\remove_filter( 'akismet_comment_nonce', $akismet_callback );
		}

		if ( ! $had_name_filter ) {
			\remove_filter( 'pre_option_require_name_email', '__return_false' );
		}

		// Restore flood control.
		if ( false !== $flood_priority ) {
			\add_action( 'check_comment_flood', 'check_comment_flood_db', $flood_priority, 4 );
		}

		if ( ! $is_insert && 1 === $state ) {
			return $comment_data;
		}

		return $state; // Either a comment ID, false, a WP_Error, or 0.
	}

	/**
	 * Get interaction counts grouped by comment type.
	 *
	 * Results are cached against WordPress's comment cache generation so inserts,
	 * updates, deletions, and comment meta changes invalidate them automatically.
	 *
	 * @since unreleased
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return array<string, int> Counts keyed by comment type.
	 */
	public static function get_counts( $post_id ) {
		$post_id      = \absint( $post_id );
		$last_changed = \wp_cache_get_last_changed( 'comment' );
		$cache_key    = 'interaction_counts_' . \md5( $post_id . ':' . $last_changed );
		$counts       = \wp_cache_get( $cache_key, 'activitypub' );

		if ( false !== $counts ) {
			return $counts;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Cached against the core comment cache generation.
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT comment_type, COUNT(*) AS total
				FROM {$wpdb->comments}
				WHERE comment_post_ID = %d
				AND comment_approved = '1'
				GROUP BY comment_type",
				$post_id
			),
			ARRAY_A
		);

		$counts = array();
		foreach ( $results as $result ) {
			$counts[ $result['comment_type'] ] = (int) $result['total'];
		}

		\wp_cache_set( $cache_key, $counts, 'activitypub' );

		return $counts;
	}

	/**
	 * Get the total number of interactions by type for a given ID.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $type    The type of interaction to count.
	 *
	 * @return int The total number of interactions.
	 */
	public static function count_by_type( $post_id, $type ) {
		$counts = self::get_counts( $post_id );
		$type   = \sanitize_key( $type );

		return $counts[ $type ] ?? 0;
	}

	/**
	 * Get the quote URL from an activity.
	 *
	 * Checks for quote properties in priority order: quote -> quoteUrl -> quoteUri -> _misskey_quote.
	 *
	 * @param array $activity The activity array.
	 *
	 * @return string|false The quote URL or false if not found.
	 */
	public static function get_quote_url( $activity ) {
		if ( ! empty( $activity['object']['quote'] ) ) {
			return object_to_uri( $activity['object']['quote'] );
		}

		if ( ! empty( $activity['object']['quoteUrl'] ) ) {
			return object_to_uri( $activity['object']['quoteUrl'] );
		}

		if ( ! empty( $activity['object']['quoteUri'] ) ) {
			return object_to_uri( $activity['object']['quoteUri'] );
		}

		if ( ! empty( $activity['object']['_misskey_quote'] ) ) {
			return object_to_uri( $activity['object']['_misskey_quote'] );
		}

		return false;
	}
}
