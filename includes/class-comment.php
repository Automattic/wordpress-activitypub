<?php
/**
 * ActivityPub Comment Class
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
use WP_Comment_Query;

/**
 * ActivityPub Comment Class.
 *
 * This class is a helper/utils class that provides a collection of static
 * methods that are used to handle comments.
 */
class Comment {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		self::register_comment_types();

		\add_filter( 'comment_reply_link', array( self::class, 'comment_reply_link' ), 10, 3 );
		\add_filter( 'comment_class', array( self::class, 'comment_class' ), 10, 3 );
		\add_filter( 'get_comment_link', array( self::class, 'remote_comment_link' ), 11, 3 );
		\add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_scripts' ) );
		\add_action( 'pre_get_comments', array( static::class, 'comment_query' ) );
		\add_filter( 'pre_comment_approved', array( static::class, 'pre_comment_approved' ), 10, 2 );
		\add_filter( 'get_avatar_comment_types', array( static::class, 'get_avatar_comment_types' ), 99 );
		\add_filter( 'pre_wp_update_comment_count_now', array( static::class, 'pre_wp_update_comment_count_now' ), 10, 3 );
	}

	/**
	 * Filter the comment reply link.
	 *
	 * We don't want to show the comment reply link for federated comments
	 * if the user is disabled for federation.
	 *
	 * @param string      $link    The HTML markup for the comment reply link.
	 * @param array       $args    An array of arguments overriding the defaults.
	 * @param \WP_Comment $comment The object of the comment being replied.
	 *
	 * @return string The filtered HTML markup for the comment reply link.
	 */
	public static function comment_reply_link( $link, $args, $comment ) {
		if ( self::are_comments_allowed( $comment ) ) {
			$user_id = get_current_user_id();
			if ( $user_id && self::was_received( $comment ) && \user_can( $user_id, 'activitypub' ) ) {
				return self::create_fediverse_reply_link( $link, $args );
			}

			return $link;
		}

		$attrs = array(
			'selectedComment' => self::generate_id( $comment ),
			'commentId'       => $comment->comment_ID,
		);

		$div = sprintf(
			'<div class="activitypub-remote-reply" data-attrs="%s"></div>',
			esc_attr( wp_json_encode( $attrs ) )
		);

		/**
		 * Filters the HTML markup for the ActivityPub remote comment reply container.
		 *
		 * @param string $div The HTML markup for the remote reply container. Default is a div
		 *                    with class 'activitypub-remote-reply' and data attributes for
		 *                    the selected comment ID and internal comment ID.
		 */
		return apply_filters( 'activitypub_comment_reply_link', $div );
	}

	/**
	 * Create a link to reply to a federated comment.
	 *
	 * This function adds a title attribute to the reply link to inform the user
	 * that the comment was received from the fediverse and the reply will be sent
	 * to the original author.
	 *
	 * @param string $link The HTML markup for the comment reply link.
	 * @param array  $args The args provided by the `comment_reply_link` filter.
	 *
	 * @return string The modified HTML markup for the comment reply link.
	 */
	private static function create_fediverse_reply_link( $link, $args ) {
		$str_to_replace = sprintf( '>%s<', $args['reply_text'] );
		$replace_with   = sprintf(
			' title="%s">%s<',
			esc_attr__( 'This comment was received from the fediverse and your reply will be sent to the original author', 'activitypub' ),
			esc_html__( 'Reply with federation', 'activitypub' )
		);
		return str_replace( $str_to_replace, $replace_with, $link );
	}

	/**
	 * Check if it is allowed to comment to a comment.
	 *
	 * Checks if the comment is local only or if the user can comment federated comments.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the user can comment, false otherwise.
	 */
	public static function are_comments_allowed( $comment ) {
		$comment = \get_comment( $comment );

		if ( ! self::was_received( $comment ) ) {
			return true;
		}

		$current_user = get_current_user_id();

		if ( ! $current_user ) {
			return false;
		}

		if ( is_single_user() && \user_can( $current_user, 'publish_posts' ) ) {
			// On a single user site, comments by users with the `publish_posts` capability will be federated as the blog user.
			$current_user = Actors::BLOG_USER_ID;
		}

		$is_user_disabled = is_user_disabled( $current_user );

		if ( $is_user_disabled ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a comment is federated.
	 *
	 * We consider a comment federated if comment was received via ActivityPub.
	 *
	 * Use this function to check if it is comment that was received via ActivityPub.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment is federated, false otherwise.
	 */
	public static function was_received( $comment ) {
		$comment = \get_comment( $comment );

		if ( ! $comment ) {
			return false;
		}

		$protocol = \get_comment_meta( $comment->comment_ID, 'protocol', true );

		if ( 'activitypub' === $protocol ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a comment was federated.
	 *
	 * This function checks if a comment was federated via ActivityPub.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment was federated, false otherwise.
	 */
	public static function was_sent( $comment ) {
		$comment = \get_comment( $comment );

		if ( ! $comment ) {
			return false;
		}

		$status = \get_comment_meta( $comment->comment_ID, 'activitypub_status', true );

		if ( $status ) {
			return true;
		}

		return false;
	}

	/**
	 * Check if a comment is local only.
	 *
	 * This function checks if a comment is local only and was not sent or received via ActivityPub.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment is local only, false otherwise.
	 */
	public static function is_local( $comment ) {
		if ( self::was_sent( $comment ) || self::was_received( $comment ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a comment should be federated.
	 *
	 * We consider a comment should be federated if it is authored by a user that is
	 * not disabled for federation and if it is a reply directly to the post or to a
	 * federated comment.
	 *
	 * Use this function to check if a comment should be federated.
	 *
	 * @param mixed $comment Comment object or ID.
	 *
	 * @return boolean True if the comment should be federated, false otherwise.
	 */
	public static function should_be_federated( $comment ) {
		// We should not federate federated comments.
		if ( self::was_received( $comment ) ) {
			return false;
		}

		$comment = \get_comment( $comment );
		$user_id = $comment->user_id;

		// Comments without user can't be federated.
		if ( ! $user_id ) {
			return false;
		}

		if ( is_single_user() && \user_can( $user_id, 'publish_posts' ) ) {
			// On a single user site, comments by users with the `publish_posts` capability will be federated as the blog user.
			$user_id = Actors::BLOG_USER_ID;
		}

		$is_user_disabled = is_user_disabled( $user_id );

		// User is disabled for federation.
		if ( $is_user_disabled ) {
			return false;
		}

		// It is a comment to the post and can be federated.
		if ( empty( $comment->comment_parent ) ) {
			return true;
		}

		// Check if parent comment is federated.
		$parent_comment = \get_comment( $comment->comment_parent );

		return ! self::is_local( $parent_comment );
	}

	/**
	 * Examine a comment ID and look up an existing comment it represents.
	 *
	 * @param string $id ActivityPub object ID (usually a URL) to check.
	 *
	 * @return \WP_Comment|false Comment object, or false on failure.
	 */
	public static function object_id_to_comment( $id ) {
		$comment_query = new WP_Comment_Query(
			array(
				'meta_key'   => 'source_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value' => $id,         // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		if ( ! $comment_query->comments ) {
			return false;
		}

		if ( count( $comment_query->comments ) > 1 ) {
			return false;
		}

		return $comment_query->comments[0];
	}

	/**
	 * Verify if URL is a local comment, or if it is a previously received
	 * remote comment (For threading comments locally).
	 *
	 * @param string $url The URL to check.
	 *
	 * @return string|null Comment ID or null if not found.
	 */
	public static function url_to_commentid( $url ) {
		if ( ! $url || ! filter_var( $url, \FILTER_VALIDATE_URL ) ) {
			return null;
		}

		// Check for local comment.
		if ( \wp_parse_url( \home_url(), \PHP_URL_HOST ) === \wp_parse_url( $url, \PHP_URL_HOST ) ) {
			$query = \wp_parse_url( $url, \PHP_URL_QUERY );

			if ( $query ) {
				parse_str( $query, $params );

				if ( ! empty( $params['c'] ) ) {
					$comment = \get_comment( $params['c'] );

					if ( $comment ) {
						return $comment->comment_ID;
					}
				}
			}
		}

		$args = array(
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query' => array(
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
		);

		$query    = new WP_Comment_Query();
		$comments = $query->query( $args );

		if ( $comments && is_array( $comments ) ) {
			return $comments[0]->comment_ID;
		}

		return null;
	}

	/**
	 * Filters the CSS classes to add an ActivityPub class.
	 *
	 * @param string[] $classes    An array of comment classes.
	 * @param string[] $css_class  An array of additional classes added to the list.
	 * @param string   $comment_id The comment ID as a numeric string.
	 *
	 * @return string[] An array of classes.
	 */
	public static function comment_class( $classes, $css_class, $comment_id ) {
		// Check if ActivityPub comment.
		if ( 'activitypub' === get_comment_meta( $comment_id, 'protocol', true ) ) {
			$classes[] = 'activitypub-comment';
		}

		return $classes;
	}

	/**
	 * Gets the public comment id via the WordPress comments meta.
	 *
	 * @param  int  $wp_comment_id The internal WordPress comment ID.
	 * @param  bool $fallback      Whether the code should fall back to `source_url` if `source_id` is not set.
	 *
	 * @return string|null           The ActivityPub id/url of the comment.
	 */
	public static function get_source_id( $wp_comment_id, $fallback = true ) {
		$comment_meta = \get_comment_meta( $wp_comment_id );

		if ( ! empty( $comment_meta['source_id'][0] ) ) {
			return $comment_meta['source_id'][0];
		} elseif ( ! empty( $comment_meta['source_url'][0] ) && $fallback ) {
			return $comment_meta['source_url'][0];
		}

		return null;
	}

	/**
	 * Gets the public comment url via the WordPress comments meta.
	 *
	 * @param  int  $wp_comment_id The internal WordPress comment ID.
	 * @param  bool $fallback      Whether the code should fall back to `source_id` if `source_url` is not set.
	 *
	 * @return string|null           The ActivityPub id/url of the comment.
	 */
	public static function get_source_url( $wp_comment_id, $fallback = true ) {
		$comment_meta = \get_comment_meta( $wp_comment_id );

		if ( ! empty( $comment_meta['source_url'][0] ) ) {
			return $comment_meta['source_url'][0];
		} elseif ( ! empty( $comment_meta['source_id'][0] ) && $fallback ) {
			return $comment_meta['source_id'][0];
		}

		return null;
	}

	/**
	 * Link remote comments to source url.
	 *
	 * @param string             $comment_link The comment link.
	 * @param object|\WP_Comment $comment      The comment object.
	 *
	 * @return string $url
	 */
	public static function remote_comment_link( $comment_link, $comment ) {
		if ( ! $comment || is_admin() ) {
			return $comment_link;
		}

		$public_comment_link = self::get_source_url( $comment->comment_ID );

		return $public_comment_link ?? $comment_link;
	}


	/**
	 * Generates an ActivityPub URI for a comment
	 *
	 * @param \WP_Comment|int $comment A comment object or comment ID.
	 *
	 * @return string ActivityPub URI for comment
	 */
	public static function generate_id( $comment ) {
		$comment = \get_comment( $comment );

		// Show external comment ID if it exists.
		$public_comment_link = self::get_source_id( $comment->comment_ID );

		if ( $public_comment_link ) {
			return $public_comment_link;
		}

		// Generate URI based on comment ID.
		return \add_query_arg( 'c', $comment->comment_ID, \trailingslashit( \home_url() ) );
	}

	/**
	 * Check if a post has remote comments
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return bool True if the post has remote comments, false otherwise.
	 */
	private static function post_has_remote_comments( $post_id ) {
		$comments = \get_comments(
			array(
				'post_id'    => $post_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'protocol',
						'value'   => 'activitypub',
						'compare' => '=',
					),
					array(
						'key'     => 'source_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		return ! empty( $comments );
	}

	/**
	 * Enqueue scripts for remote comments
	 */
	public static function enqueue_scripts() {
		if ( ! \is_singular() || \is_user_logged_in() ) {
			// Only on single pages, only for logged-out users.
			return;
		}

		if ( ! \post_type_supports( \get_post_type(), 'activitypub' ) ) {
			// Post type does not support ActivityPub.
			return;
		}

		if ( ! \comments_open() || ! \get_comments_number() ) {
			// No comments, no need to load the script.
			return;
		}

		if ( ! self::post_has_remote_comments( \get_the_ID() ) ) {
			// No remote comments, no need to load the script.
			return;
		}

		$handle     = 'activitypub-remote-reply';
		$data       = array(
			'namespace'        => ACTIVITYPUB_REST_NAMESPACE,
			'defaultAvatarUrl' => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		);
		$js         = sprintf( 'var _activityPubOptions = %s;', wp_json_encode( $data ) );
		$asset_file = ACTIVITYPUB_PLUGIN_DIR . 'build/remote-reply/index.asset.php';

		if ( \file_exists( $asset_file ) ) {
			$assets = require_once $asset_file;

			\wp_enqueue_script(
				$handle,
				\plugins_url( 'build/remote-reply/index.js', __DIR__ ),
				$assets['dependencies'],
				$assets['version'],
				true
			);
			\wp_add_inline_script( $handle, $js, 'before' );

			\wp_enqueue_style(
				$handle,
				\plugins_url( 'build/remote-reply/style-index.css', __DIR__ ),
				array( 'wp-components' ),
				$assets['version']
			);
		}
	}

	/**
	 * Get the comment type by activity type.
	 *
	 * @param string $activity_type The activity type.
	 *
	 * @return array|null The comment type.
	 */
	public static function get_comment_type_by_activity_type( $activity_type ) {
		$activity_type = \strtolower( $activity_type );
		$activity_type = \sanitize_key( $activity_type );
		$comment_types = self::get_comment_types();

		foreach ( $comment_types as $comment_type ) {
			if ( in_array( $activity_type, $comment_type['activity_types'], true ) ) {
				return $comment_type;
			}
		}

		return null;
	}

	/**
	 * Return the registered custom comment types.
	 *
	 * @return array The registered custom comment types
	 */
	public static function get_comment_types() {
		global $activitypub_comment_types;

		return $activitypub_comment_types;
	}

	/**
	 * Is this a registered comment type.
	 *
	 * @param string $slug The slug of the type.
	 *
	 * @return boolean True if registered.
	 */
	public static function is_registered_comment_type( $slug ) {
		$slug = \strtolower( $slug );
		$slug = \sanitize_key( $slug );

		$comment_types = self::get_comment_types();

		return isset( $comment_types[ $slug ] );
	}

	/**
	 * Return the registered custom comment type slugs.
	 *
	 * @return array The registered custom comment type slugs.
	 */
	public static function get_comment_type_slugs() {
		return array_keys( self::get_comment_types() );
	}

	/**
	 * Return the registered custom comment type slugs.
	 *
	 * @deprecated 4.5.0 Use get_comment_type_slugs instead.
	 *
	 * @return array The registered custom comment type slugs.
	 */
	public static function get_comment_type_names() {
		_deprecated_function( __METHOD__, '4.5.0', 'get_comment_type_slugs' );

		return self::get_comment_type_slugs();
	}

	/**
	 * Get the custom comment type.
	 *
	 * Check if the type is registered, if not, check if it is a custom type.
	 *
	 * It looks for the array key in the registered types and returns the array.
	 * If it is not found, it looks for the type in the custom types and returns the array.
	 *
	 * @param string $type The comment type.
	 *
	 * @return array The comment type.
	 */
	public static function get_comment_type( $type ) {
		$type = strtolower( $type );
		$type = sanitize_key( $type );

		$comment_types = self::get_comment_types();
		$type_array    = array();

		// Check array keys.
		if ( in_array( $type, array_keys( $comment_types ), true ) ) {
			$type_array = $comment_types[ $type ];
		}

		/**
		 * Filter the comment type.
		 *
		 * @param array $type_array The comment type.
		 */
		return apply_filters( "activitypub_comment_type_{$type}", $type_array );
	}

	/**
	 * Get a comment type attribute.
	 *
	 * @param string $type The comment type.
	 * @param string $attr The attribute to get.
	 *
	 * @return mixed The value of the attribute.
	 */
	public static function get_comment_type_attr( $type, $attr ) {
		$type_array = self::get_comment_type( $type );

		if ( $type_array && isset( $type_array[ $attr ] ) ) {
			$value = $type_array[ $attr ];
		} else {
			$value = '';
		}

		/**
		 * Filter the comment type attribute.
		 *
		 * @param mixed  $value The value of the attribute.
		 * @param string $type  The comment type.
		 */
		return apply_filters( "activitypub_comment_type_{$attr}", $value, $type );
	}

	/**
	 * Register the comment types used by the ActivityPub plugin.
	 */
	public static function register_comment_types() {
		register_comment_type(
			'repost',
			array(
				'label'          => __( 'Reposts', 'activitypub' ),
				'singular'       => __( 'Repost', 'activitypub' ),
				'description'    => __( 'A repost on the indieweb is a post that is purely a 100% re-publication of another (typically someone else\'s) post.', 'activitypub' ),
				'icon'           => '♻️',
				'class'          => 'p-repost',
				'type'           => 'repost',
				'collection'     => 'reposts',
				'activity_types' => array( 'announce' ),
				'excerpt'        => __( '&hellip; reposted this!', 'activitypub' ),
				/* translators: %d: Number of reposts */
				'count_single'   => _x( '%d repost', 'number of reposts', 'activitypub' ),
				/* translators: %d: Number of reposts */
				'count_plural'   => _x( '%d reposts', 'number of reposts', 'activitypub' ),
			)
		);

		register_comment_type(
			'like',
			array(
				'label'          => __( 'Likes', 'activitypub' ),
				'singular'       => __( 'Like', 'activitypub' ),
				'description'    => __( 'A like is a popular webaction button and in some cases post type on various silos such as Facebook and Instagram.', 'activitypub' ),
				'icon'           => '👍',
				'class'          => 'p-like',
				'type'           => 'like',
				'collection'     => 'likes',
				'activity_types' => array( 'like' ),
				'excerpt'        => __( '&hellip; liked this!', 'activitypub' ),
				/* translators: %d: Number of likes */
				'count_single'   => _x( '%d like', 'number of likes', 'activitypub' ),
				/* translators: %d: Number of likes */
				'count_plural'   => _x( '%d likes', 'number of likes', 'activitypub' ),
			)
		);
	}

	/**
	 * Show avatars on Activities if set.
	 *
	 * @param array $types List of avatar enabled comment types.
	 *
	 * @return array show avatars on Activities
	 */
	public static function get_avatar_comment_types( $types ) {
		$comment_types = self::get_comment_type_slugs();
		$types         = array_merge( $types, $comment_types );

		return array_unique( $types );
	}

	/**
	 * Excludes likes and reposts from comment queries.
	 *
	 * @author Jan Boddez
	 *
	 * @see https://github.com/janboddez/indieblocks/blob/a2d59de358031056a649ee47a1332ce9e39d4ce2/includes/functions.php#L423-L432
	 *
	 * @param WP_Comment_Query $query Comment count.
	 */
	public static function comment_query( $query ) {
		if ( ! $query instanceof WP_Comment_Query ) {
			return;
		}

		if ( is_admin() || ! is_singular() ) {
			return;
		}

		if ( ! empty( $query->query_vars['type__in'] ) ) {
			return;
		}

		// Exclude likes and reposts by the ActivityPub plugin.
		$query->query_vars['type__not_in'] = self::get_comment_type_slugs();
	}

	/**
	 * Filter the comment status before it is set.
	 *
	 * @param string $approved    The approved comment status.
	 * @param array  $commentdata The comment data.
	 *
	 * @return boolean `true` if the comment is approved, `false` otherwise.
	 */
	public static function pre_comment_approved( $approved, $commentdata ) {
		if ( $approved || \is_wp_error( $approved ) ) {
			return $approved;
		}

		if ( '1' !== \get_option( 'comment_previously_approved' ) ) {
			return $approved;
		}

		if (
			empty( $commentdata['comment_meta']['protocol'] ) ||
			'activitypub' !== $commentdata['comment_meta']['protocol']
		) {
			return $approved;
		}

		global $wpdb;

		$author     = $commentdata['comment_author'];
		$author_url = $commentdata['comment_author_url'];
		// phpcs:ignore
		$ok_to_comment = $wpdb->get_var( $wpdb->prepare( "SELECT comment_approved FROM $wpdb->comments WHERE comment_author = %s AND comment_author_url = %s and comment_approved = '1' LIMIT 1", $author, $author_url ) );

		if ( 1 === (int) $ok_to_comment ) {
			return 1;
		}

		return $approved;
	}

	/**
	 * Filters the comment count to exclude ActivityPub comment types.
	 *
	 * @param int|null $new_count The new comment count. Default null.
	 * @param int      $old_count The old comment count.
	 * @param int      $post_id   Post ID.
	 *
	 * @return int|null The updated comment count, or null to use the default query.
	 */
	public static function pre_wp_update_comment_count_now( $new_count, $old_count, $post_id ) {
		if ( null === $new_count ) {
			global $wpdb;

			$excluded_types = self::get_comment_type_slugs();

			// phpcs:ignore WordPress.DB
			$new_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->comments WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_type NOT IN ('" . implode( "','", $excluded_types ) . "')", $post_id ) );

		}

		return $new_count;
	}
}
