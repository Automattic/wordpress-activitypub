<?php
/**
 * Jetpack integration file.
 *
 * @package Activitypub
 */

namespace Activitypub\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Comment;

/**
 * Jetpack integration class.
 */
class Jetpack {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		if ( ! \defined( 'IS_WPCOM' ) ) {
			\add_filter( 'jetpack_sync_post_meta_whitelist', array( self::class, 'add_sync_meta' ) );
			\add_filter( 'jetpack_sync_comment_meta_whitelist', array( self::class, 'add_sync_comment_meta' ) );
			\add_filter( 'jetpack_sync_whitelisted_comment_types', array( self::class, 'add_comment_types' ) );
			\add_filter( 'jetpack_json_api_comment_types', array( self::class, 'add_comment_types' ) );
			\add_filter( 'jetpack_api_include_comment_types_count', array( self::class, 'add_comment_types' ) );
		}

		if (
			( \defined( 'IS_WPCOM' ) && IS_WPCOM ) ||
			( \class_exists( '\Jetpack' ) && \Jetpack::is_connection_ready() )
		) {
			\add_filter( 'activitypub_following_row_actions', array( self::class, 'add_reader_link' ), 10, 2 );
			\add_filter( 'pre_option_activitypub_following_ui', array( self::class, 'pre_option_activitypub_following_ui' ) );
		}
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
	 * @return array
	 */
	public static function add_comment_types( $comment_types ) {
		// jetpack_sync_whitelisted_comment_types runs on plugins_loaded, before comment types are registered.
		if ( 'jetpack_sync_whitelisted_comment_types' === current_filter() ) {
			Comment::register_comment_types();
		}

		return array_unique( \array_merge( $comment_types, Comment::get_comment_type_slugs() ) );
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

		if ( isset( $feed['feed_id'] ) ) {
			$url = sprintf( 'https://wordpress.com/reader/feed/%d', (int) $feed['feed_id'] );
		} else {
			$url = sprintf( 'https://wordpress.com/reader/feeds/lookup/%s', rawurlencode( $item['identifier'] ) );
		}

		return array_merge(
			array(
				'reader' => sprintf(
					'<a href="%s" target="_blank">%s</a>',
					esc_url( $url ),
					esc_html__( 'View Feed', 'activitypub' )
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
}
