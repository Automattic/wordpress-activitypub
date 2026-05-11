<?php
/**
 * Posts collection file.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Blocks;
use Activitypub\Hashtag;
use Activitypub\Link;

use function Activitypub\get_content_visibility;

/**
 * Posts collection.
 *
 * Provides CRUD methods for local WordPress posts created
 * via ActivityPub Client-to-Server (C2S) outbox.
 *
 * @see Remote_Posts for federated posts received via Server-to-Server (S2S).
 */
class Posts {
	/**
	 * Create a WordPress post from an ActivityPub activity.
	 *
	 * @since 8.1.0
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error The created post on success, WP_Error on failure.
	 */
	public static function create( $activity, $user_id, $visibility = null ) {
		// Verify the user has permission to create posts.
		if ( $user_id > 0 && ! \user_can( $user_id, 'publish_posts' ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You do not have permission to create posts.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$object = $activity['object'] ?? array();

		$object_type = $object['type'] ?? '';
		$content     = \wp_kses_post( $object['content'] ?? '' );
		$name        = \sanitize_text_field( $object['name'] ?? '' );
		$summary     = \wp_kses_post( $object['summary'] ?? '' );

		// Process content: autop, autolink, hashtags, and convert to blocks.
		$content = self::prepare_content( $content );

		// Use name as title for Articles, or generate from content for Notes.
		$title = $name;
		if ( empty( $title ) && ! empty( $content ) ) {
			$title = \wp_trim_words( \wp_strip_all_tags( $content ), 10, '...' );
		}

		// Determine visibility if not provided.
		if ( null === $visibility ) {
			$visibility = get_content_visibility( $activity );
		}

		$post_data = array(
			'post_author'  => $user_id > 0 ? $user_id : 0,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $summary,
			'post_status'  => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === $visibility ? 'private' : 'publish',
			'post_type'    => 'post',
			'meta_input'   => array(
				'activitypub_content_visibility' => $visibility,
			),
		);

		$post_id = \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set post format to 'status' for Notes so the transformer maps it back correctly.
		if ( 'Note' === $object_type ) {
			\set_post_format( $post_id, 'status' );
		}

		return \get_post( $post_id );
	}

	/**
	 * Update a WordPress post from an ActivityPub activity.
	 *
	 * @since 8.1.0
	 *
	 * @param \WP_Post    $post       The post to update.
	 * @param array       $activity   The activity data.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error The updated post on success, WP_Error on failure.
	 */
	public static function update( $post, $activity, $visibility = null ) {
		$object = $activity['object'] ?? array();

		$content = \wp_kses_post( $object['content'] ?? '' );
		$name    = \sanitize_text_field( $object['name'] ?? '' );
		$summary = \wp_kses_post( $object['summary'] ?? '' );

		// Process content: autop, autolink, hashtags, and convert to blocks.
		$content = self::prepare_content( $content );

		// Use name as title for Articles, or generate from content for Notes.
		$title = $name;
		if ( empty( $title ) && ! empty( $content ) ) {
			$title = \wp_trim_words( \wp_strip_all_tags( $content ), 10, '...' );
		}

		// Determine visibility if not provided.
		if ( null === $visibility ) {
			$visibility = get_content_visibility( $activity );
		}

		$post_data = array(
			'ID'           => $post->ID,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $summary,
			'meta_input'   => array(
				'activitypub_content_visibility' => $visibility,
			),
		);

		$post_id = \wp_update_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		return \get_post( $post_id );
	}

	/**
	 * Delete (trash) a WordPress post.
	 *
	 * @since 8.1.0
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return \WP_Post|false|null Post data on success, false or null on failure.
	 */
	public static function delete( $post_id ) {
		return \wp_trash_post( $post_id );
	}

	/**
	 * Prepare content for storage as a WordPress post.
	 *
	 * Applies wpautop (for plain text), autolinks bare URLs,
	 * converts hashtags to links, and wraps in block markup.
	 *
	 * @since 8.1.0
	 *
	 * @param string $content The HTML or plain-text content.
	 *
	 * @return string The processed content with block markup.
	 */
	public static function prepare_content( $content ) {
		if ( empty( $content ) ) {
			return '';
		}

		// Wrap plain text in paragraphs if it has no block-level HTML.
		if ( ! \preg_match( '/<(p|h[1-6]|ul|ol|blockquote|figure|hr|img|div|pre|table)\b/i', $content ) ) {
			$content = \wpautop( $content );
		}

		// Convert bare URLs to links.
		$content = Link::the_content( $content );

		// Convert #hashtags to links.
		$content = Hashtag::the_content( $content );

		// Convert HTML to block markup.
		$content = Blocks::convert_from_html( $content );

		return $content;
	}
}
