<?php
/**
 * Jetpack integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Http;
use Automattic\Jetpack\Connection\Manager;

use function Activitypub\is_activity_object;

/**
 * Jetpack integration class.
 */
class Jetpack {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( ! \defined( 'IS_WPCOM' ) ) {
			\add_filter( 'jetpack_sync_options_whitelist', array( self::class, 'add_sync_options' ) );
			\add_filter( 'jetpack_sync_post_meta_whitelist', array( self::class, 'add_sync_meta' ) );
			\add_filter( 'jetpack_sync_comment_meta_whitelist', array( self::class, 'add_sync_comment_meta' ) );
			\add_filter( 'jetpack_sync_whitelisted_comment_types', array( self::class, 'add_comment_types' ) );
			\add_filter( 'jetpack_json_api_comment_types', array( self::class, 'add_comment_types' ) );
			\add_filter( 'jetpack_api_include_comment_types_count', array( self::class, 'add_comment_types' ) );
		}

		if (
			( \defined( 'IS_WPCOM' ) && IS_WPCOM ) ||
			( \class_exists( '\Automattic\Jetpack\Connection\Manager' ) && ( new Manager() )->is_user_connected() )
		) {
			\add_filter( 'activitypub_following_row_actions', array( self::class, 'add_reader_link' ), 10, 2 );
			\add_filter( 'pre_option_activitypub_following_ui', array( self::class, 'pre_option_activitypub_following_ui' ) );
		}

		\add_action( 'load-post-new.php', array( self::class, 'adapt_post_share' ) );

		// A Jetpack episode is an ordinary post, so its audio is enriched onto the already-assembled
		// attachments here, rather than through a dedicated transformer subclass like Podlove/SSP.
		\add_filter( 'activitypub_attachments', array( self::class, 'add_podcast_attachment' ), 10, 2 );
	}

	/**
	 * Add ActivityPub options to the Jetpack sync allow list.
	 *
	 * @since 8.1.0
	 *
	 * @param array $allow_list The Jetpack sync options allow list.
	 *
	 * @return array The allow list with ActivityPub options.
	 */
	public static function add_sync_options( $allow_list ) {
		$allow_list[] = 'activitypub_blog_identifier';
		$allow_list[] = 'activitypub_blog_description';
		$allow_list[] = 'activitypub_header_image';
		$allow_list[] = 'activitypub_actor_mode';

		return $allow_list;
	}

	/**
	 * Add ActivityPub meta keys to the Jetpack sync allow list.
	 *
	 * @param array $allow_list The Jetpack sync allow list.
	 *
	 * @return array The Jetpack sync allow list with ActivityPub meta keys.
	 */
	public static function add_sync_meta( $allow_list ) {
		$allow_list[] = Followers::FOLLOWER_META_KEY;
		$allow_list[] = Following::FOLLOWING_META_KEY;

		return $allow_list;
	}

	/**
	 * Add ActivityPub comment meta keys to the Jetpack sync allow list.
	 *
	 * @param array $allow_list The Jetpack sync allow list.
	 *
	 * @return array The Jetpack sync allow list with ActivityPub comment meta keys.
	 */
	public static function add_sync_comment_meta( $allow_list ) {
		$allow_list[] = 'avatar_url';

		return $allow_list;
	}

	/**
	 * Add custom comment types to the list of comment types.
	 *
	 * @param array $comment_types Default comment types.
	 *
	 * @return array The comment types with ActivityPub types added.
	 */
	public static function add_comment_types( $comment_types ) {
		$comment_types[] = 'like';
		$comment_types[] = 'quote';
		$comment_types[] = 'repost';

		return \array_unique( $comment_types );
	}

	/**
	 * Add a "Reader" link to the bulk actions dropdown on the following list screen.
	 *
	 * @param array $actions The bulk actions.
	 * @param array $item    The current following item.
	 *
	 * @return array The bulk actions with the "Reader" link.
	 */
	public static function add_reader_link( $actions, $item ) {
		// Do not show the link for pending follow requests.
		if ( 'pending' === $item['status'] ) {
			return $actions;
		}

		$feed = \get_post_meta( $item['id'], '_activitypub_actor_feed', true );

		// Generate Reader URL based on environment.
		if ( \defined( 'IS_WPCOM' ) && IS_WPCOM ) {
			if ( empty( $feed['feed_id'] ) ) {
				return $actions; // No feed_id available on WPCOM.
			}
			$url = \sprintf( 'https://wordpress.com/reader/feeds/%d', (int) $feed['feed_id'] );
		} else {
			$url = \sprintf( 'https://wordpress.com/reader/feeds/lookup/%s', \rawurlencode( $item['identifier'] ) );
		}

		return \array_merge(
			array(
				'reader' => \sprintf(
					'<a href="%1$s" target="_blank">%2$s<span class="screen-reader-text"> %3$s</span><span aria-hidden="true" class="dashicons dashicons-external"></span></a>',
					\esc_url( $url ),
					\esc_html__( 'View Feed', 'activitypub' ),
					/* translators: Hidden accessibility text. */
					\esc_html__( '(opens in a new tab)', 'activitypub' )
				),
			),
			$actions
		);
	}

	/**
	 * Force the ActivityPub Following UI to be enabled when Jetpack is active.
	 *
	 * @return string '1' to enable the ActivityPub Following UI.
	 */
	public static function pre_option_activitypub_following_ui() {
		return '1';
	}

	/**
	 * Adapt the parameters for a post share request to be compatible with the Federated Reply block.
	 */
	public static function adapt_post_share() {
		if ( ! isset( $_GET['is_post_share'], $_GET['url'] ) || ! $_GET['is_post_share'] ) { // phpcs:ignore WordPress.Security
			return;
		}

		$url = \sanitize_url( \wp_unslash( $_GET['url'] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		if ( is_activity_object( Http::get_remote_object( $url ) ) ) {
			$args = array(
				'post_type'   => 'post',
				'in_reply_to' => $url,
			);

			\wp_safe_redirect( \add_query_arg( $args, \admin_url( 'post-new.php' ) ) );
			exit;
		}
	}

	/**
	 * Federate a Jetpack podcast episode's audio as an ActivityPub attachment.
	 *
	 * A Jetpack episode stores its audio in the `jetpack/podcast-episode` block, read back through
	 * the podcast package's own {@see \Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags}. Reading it
	 * there (rather than relying on WordPress core's asynchronous `enclosure` meta) makes the audio,
	 * its cover art, and its mime type available on every transform. When the core-enclosure path has
	 * already added the same audio, the existing attachment is enriched with the episode cover art
	 * instead of being duplicated.
	 *
	 * @param array    $attachments The ActivityPub attachments.
	 * @param \WP_Post $post        The post being transformed.
	 *
	 * @return array The attachments, with the podcast episode audio added or enriched.
	 */
	public static function add_podcast_attachment( $attachments, $post ) {
		if ( ! \class_exists( '\Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags' ) ) {
			return $attachments;
		}

		$attrs = \Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags::get_block_attrs( $post );
		if ( empty( $attrs['mediaUrl'] ) ) {
			return $attachments;
		}

		$url  = \esc_url_raw( $attrs['mediaUrl'] );
		$icon = empty( $attrs['coverArt']['url'] ) ? '' : \esc_url_raw( $attrs['coverArt']['url'] );

		// The core-enclosure path may already have added this audio: enrich it with the cover art rather than duplicate it.
		foreach ( $attachments as $index => $attachment ) {
			if ( isset( $attachment['url'] ) && $attachment['url'] === $url ) {
				if ( $icon && empty( $attachment['icon'] ) ) {
					$attachments[ $index ]['icon'] = $icon;
				}
				return $attachments;
			}
		}

		$podcast = array(
			'type'      => \ucfirst( $attrs['mediaType'] ?? 'Audio' ),
			'url'       => $url,
			'mediaType' => \esc_attr( $attrs['mediaMimeType'] ?? '' ),
			'name'      => \esc_attr( \get_the_title( $post ) ),
		);

		if ( $icon ) {
			$podcast['icon'] = $icon;
		}

		\array_unshift( $attachments, $podcast );

		return $attachments;
	}
}
