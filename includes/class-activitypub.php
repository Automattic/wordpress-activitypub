<?php
namespace Activitypub;

/**
 * ActivityPub Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'template_include', array( '\Activitypub\Activitypub', 'render_json_template' ), 99 );
		\add_filter( 'query_vars', array( '\Activitypub\Activitypub', 'add_query_vars' ) );
		\add_filter( 'pre_get_avatar_data', array( '\Activitypub\Activitypub', 'pre_get_avatar_data' ), 11, 2 );

		// Add support for ActivityPub to custom post types
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array();

		foreach ( $post_types as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}

		\add_action( 'transition_post_status', array( '\Activitypub\Activitypub', 'schedule_post_activity' ), 10, 3 );
		\add_action( 'wp_trash_post', array( '\Activitypub\Activitypub', 'trash_post' ), 1 );
		\add_action( 'untrash_post', array( '\Activitypub\Activitypub', 'untrash_post' ), 1 );
	}

	/**
	 * Return a AS2 JSON version of an author, post or page.
	 *
	 * @param  string $template The path to the template object.
	 *
	 * @return string The new path to the JSON template.
	 */
	public static function render_json_template( $template ) {
		if ( ! \is_author() && ! \is_singular() && ! \is_home() ) {
			return $template;
		}

		// check if user can publish posts
		if ( \is_author() && ! user_can( \get_the_author_meta( 'ID' ), 'publish_posts' ) ) {
			return $template;
		}

		if ( \is_author() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/author-json.php';
		} elseif ( \is_singular() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/post-json.php';
		} elseif ( \is_home() ) {
			$json_template = ACTIVITYPUB_PLUGIN_DIR . '/templates/blog-json.php';
		}

		global $wp_query;

		if ( isset( $wp_query->query_vars['activitypub'] ) ) {
			return $json_template;
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return $template;
		}

		$accept_header = $_SERVER['HTTP_ACCEPT'];

		if (
			\stristr( $accept_header, 'application/activity+json' ) ||
			\stristr( $accept_header, 'application/ld+json' )
		) {
			return $json_template;
		}

		// Accept header as an array.
		$accept = \explode( ',', \trim( $accept_header ) );

		if (
			\in_array( 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', $accept, true ) ||
			\in_array( 'application/activity+json', $accept, true ) ||
			\in_array( 'application/ld+json', $accept, true ) ||
			\in_array( 'application/json', $accept, true )
		) {
			return $json_template;
		}

		return $template;
	}

	/**
	 * Add the 'activitypub' query variable so WordPress won't mangle it.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub';

		return $vars;
	}

	/**
	 * Schedule Activities.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		// Do not send activities if post is password protected.
		if ( \post_password_required( $post ) ) {
			return;
		}

		// Check if post-type supports ActivityPub.
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$activitypub_post = new \Activitypub\Model\Post( $post );

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_post_activity', array( $activitypub_post ) );
		} elseif ( 'publish' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_update_activity', array( $activitypub_post ) );
		} elseif ( 'trash' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_delete_activity', array( $activitypub_post ) );
		}
	}

	/**
	 * Replaces the default avatar.
	 *
	 * @param array             $args        Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object.
	 *
	 * @return array $args
	 */
	public static function pre_get_avatar_data( $args, $id_or_email ) {
		if (
			! $id_or_email instanceof \WP_Comment ||
			! isset( $id_or_email->comment_type ) ||
			$id_or_email->user_id
		) {
			return $args;
		}

		$allowed_comment_types = \apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if ( ! empty( $id_or_email->comment_type ) && ! \in_array( $id_or_email->comment_type, (array) $allowed_comment_types, true ) ) {
			$args['url'] = false;
			/** This filter is documented in wp-includes/link-template.php */
			return \apply_filters( 'get_avatar_data', $args, $id_or_email );
		}

		// Check if comment has an avatar.
		$avatar = self::get_avatar_url( $id_or_email->comment_ID );

		if ( $avatar ) {
			if ( ! isset( $args['class'] ) || ! \is_array( $args['class'] ) ) {
				$args['class'] = array( 'u-photo' );
			} else {
				$args['class'][] = 'u-photo';
				$args['class']   = \array_unique( $args['class'] );
			}
			$args['url']     = $avatar;
			$args['class'][] = 'avatar-activitypub';
		}

		return $args;
	}

	/**
	 * Function to retrieve Avatar URL if stored in meta.
	 *
	 * @param int|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function get_avatar_url( $comment ) {
		if ( \is_numeric( $comment ) ) {
			$comment = \get_comment( $comment );
		}
		return \get_comment_meta( $comment->comment_ID, 'avatar_url', true );
	}

	/**
	 * Store permalink in meta, to send delete Activity
	 *
	 * @param string $post_id The Post ID
	 *
	 * @return void
	 */
	public static function trash_post( $post_id ) {
		\add_post_meta( $post_id, 'activitypub_canonical_url', \get_permalink( $post_id ), true );
	}

	/**
	 * Delete permalink from meta
	 *
	 * @param string $post_id The Post ID
	 *
	 * @return void
	 */
	public static function untrash_post( $post_id ) {
		\delete_post_meta( $post_id, 'activitypub_canonical_url' );
	}
}
