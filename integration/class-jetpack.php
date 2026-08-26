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
use Automattic\Jetpack\Podcast\Feed\Customize_Feed;
use Automattic\Jetpack\Podcast\Feed\Episode_Block_Tags;
use Automattic\Jetpack\Podcast\Settings as Podcast_Settings;

use function Activitypub\get_enclosures;
use function Activitypub\get_max_attachments;
use function Activitypub\is_activity_object;
use function Activitypub\normalize_url;

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

		// Enriched onto the already-assembled attachments rather than through a transformer subclass
		// like Podlove/SSP: a subclass is winner-take-all, so a site running one of those alongside a
		// Jetpack podcast would get one behaviour instead of both.
		\add_filter( 'activitypub_attachments', array( self::class, 'add_podcast_attachments' ), 10, 2 );
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
	 * Federate a podcast episode's audio as an ActivityPub attachment.
	 *
	 * Jetpack has two podcast surfaces, developed separately, and an episode can come from either:
	 *
	 * - Posts to Podcast writes a `jetpack/podcast-episode` block carrying the audio it produced.
	 * - Jetpack Podcast treats every post in the configured podcast category as an episode, whose
	 *   audio is an ordinary WordPress enclosure.
	 *
	 * Neither survives the transformer on its own. Episode audio is usually hosted off-site, so it
	 * has no attachment ID, and {@see \Activitypub\Transformer\Base::filter_unique_attachments()}
	 * drops every media entry without one, meaning that audio never reaches this filter. It is
	 * resolved here instead, and either added or, when the media library already contributed it,
	 * given the artwork the podcast feed shows for the same episode.
	 *
	 * @param array    $attachments The ActivityPub attachments.
	 * @param \WP_Post $post        The post being transformed.
	 *
	 * @return array The attachments, with the episode audio added or enriched.
	 */
	public static function add_podcast_attachments( $attachments, $post ) {
		$is_show_episode = self::is_show_episode( $post );
		$episode         = self::get_episode_audio( $post, $is_show_episode );

		if ( ! $episode ) {
			return $attachments;
		}

		$icon  = self::get_cover_art( $post, $episode['coverArt'] ?? '', $is_show_episode );
		$index = self::find_attachment_by_url( $attachments, $episode['url'] );

		if ( null !== $index ) {
			/*
			 * The audio is already attached, so only the artwork is missing. It is replaced rather
			 * than filled in: the transformer falls back to the site icon for any audio without a
			 * poster, and the show's own cover is the better answer for an episode.
			 */
			if ( $icon ) {
				$attachments[ $index ]['icon'] = $icon;
			}

			return $attachments;
		}

		$audio = array(
			'type' => $episode['type'],
			'url'  => $episode['url'],
			'name' => \html_entity_decode( \wp_strip_all_tags( \get_the_title( $post ) ), ENT_QUOTES, 'UTF-8' ),
		);

		// An episode attached by URL carries no mime type, and omitting the property beats sending
		// an empty one, which stops a receiver classifying the attachment at all.
		if ( $episode['mediaType'] ) {
			$audio['mediaType'] = $episode['mediaType'];
		}

		if ( $icon ) {
			$audio['icon'] = $icon;
		}

		\array_unshift( $attachments, $audio );

		// The transformer trimmed to the configured maximum before this filter ran, so prepending
		// the audio would otherwise put the post one attachment over a limit the site chose. An
		// episode that arrived alone cannot exceed it, and the maximum is never 0 here because the
		// transformer returns before this filter in that case.
		if ( \count( $attachments ) > 1 ) {
			$attachments = \array_slice( $attachments, 0, get_max_attachments( $post->ID ) );
		}

		return $attachments;
	}

	/**
	 * Resolve the audio of a podcast episode.
	 *
	 * A Posts to Podcast episode keeps its generated audio in the `jetpack/podcast-episode` block.
	 * A Jetpack Podcast episode instead carries an ordinary enclosure, which only counts as an
	 * episode when the post is filed in the configured podcast category, since any post may have an
	 * enclosure without being part of the show.
	 *
	 * @param \WP_Post $post            The post being transformed.
	 * @param bool     $is_show_episode Whether the post is filed in the configured podcast category.
	 *
	 * @return array|null The episode `type`, `url`, `mediaType` and `coverArt`, or null when the post is not an episode.
	 */
	private static function get_episode_audio( $post, $is_show_episode ) {
		$attrs = self::get_episode_block_attrs( $post );

		if ( ! empty( $attrs['mediaUrl'] ) ) {
			// Sanitizing drops an unsafe scheme, and an attachment without a URL is invalid.
			$url = \esc_url_raw( $attrs['mediaUrl'] );

			if ( $url ) {
				return array(
					'type'      => \ucfirst( $attrs['mediaType'] ?? 'audio' ),
					'url'       => $url,
					'mediaType' => \esc_attr( $attrs['mediaMimeType'] ?? '' ),
					'coverArt'  => empty( $attrs['coverArt']['url'] ) ? '' : \esc_url_raw( $attrs['coverArt']['url'] ),
				);
			}
		}

		if ( ! $is_show_episode ) {
			return null;
		}

		foreach ( get_enclosures( $post->ID ) as $enclosure ) {
			$mime_type = $enclosure['mediaType'] ?? '';

			if ( ! \str_starts_with( $mime_type, 'audio/' ) ) {
				continue;
			}

			$url = \esc_url_raw( $enclosure['url'] );

			if ( $url ) {
				return array(
					'type'      => 'Audio',
					'url'       => $url,
					'mediaType' => \esc_attr( $mime_type ),
				);
			}
		}

		return null;
	}

	/**
	 * Resolve the cover art for a podcast episode.
	 *
	 * The episode's own artwork wins, then the post's featured image, then the show image. That is
	 * the order the podcast feed covers an item with, so a federated episode carries the artwork
	 * subscribers already see. The show image applies only to an episode of the show itself, so a
	 * generated episode on an unrelated post does not advertise the podcast's cover.
	 *
	 * @param \WP_Post $post            The post being transformed.
	 * @param string   $cover_art       The episode's own artwork, when it has any.
	 * @param bool     $is_show_episode Whether the post is filed in the configured podcast category.
	 *
	 * @return string The cover art URL, or an empty string when none is set.
	 */
	private static function get_cover_art( $post, $cover_art, $is_show_episode ) {
		if ( $cover_art ) {
			return $cover_art;
		}

		$thumbnail = \get_the_post_thumbnail_url( $post, 'full' );

		if ( $thumbnail ) {
			return \esc_url_raw( $thumbnail );
		}

		if ( ! $is_show_episode || ! \method_exists( Podcast_Settings::class, 'raw_show_image_url' ) ) {
			return '';
		}

		return \esc_url_raw( (string) Podcast_Settings::raw_show_image_url() );
	}

	/**
	 * Test whether a post is an episode of the site's own podcast.
	 *
	 * @param \WP_Post $post The post being transformed.
	 *
	 * @return bool Whether the post is in the configured podcast category.
	 */
	private static function is_show_episode( $post ) {
		if ( ! \method_exists( Customize_Feed::class, 'resolve_category_id' ) ) {
			return false;
		}

		// The category can be stored as an ID or as an archive slug, and only Jetpack knows which applies.
		$category_id = (int) Customize_Feed::resolve_category_id();

		return $category_id && \in_category( $category_id, $post );
	}

	/**
	 * Read the attributes of the post's `jetpack/podcast-episode` block.
	 *
	 * @param \WP_Post $post The post being transformed.
	 *
	 * @return array The block attributes, empty when the post has no episode block.
	 */
	private static function get_episode_block_attrs( $post ) {
		if ( ! \method_exists( Episode_Block_Tags::class, 'get_block_attrs' ) ) {
			return array();
		}

		return (array) Episode_Block_Tags::get_block_attrs( $post );
	}

	/**
	 * Find the attachment carrying a given media URL.
	 *
	 * Matched on host and path so the same file is still recognised when the stored enclosure and
	 * the episode block disagree on the scheme, which is the case on every site that moved to HTTPS
	 * after publishing.
	 *
	 * @param array  $attachments The ActivityPub attachments.
	 * @param string $url         The media URL to look for.
	 *
	 * @return int|string|null The attachment key, or null when the media is not in the list.
	 */
	private static function find_attachment_by_url( $attachments, $url ) {
		$needle = normalize_url( $url );

		foreach ( $attachments as $index => $attachment ) {
			if ( isset( $attachment['url'] ) && normalize_url( $attachment['url'] ) === $needle ) {
				return $index;
			}
		}

		return null;
	}
}
