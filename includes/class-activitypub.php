<?php
namespace Activitypub;

/**
 * ActivityPub Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub {
	public static function init() {
		add_filter( 'template_include', array( '\Activitypub\Activitypub', 'render_json_template' ), 99 );
		add_filter( 'query_vars', array( '\Activitypub\Activitypub', 'add_query_vars' ) );
		add_action( 'init', array( '\Activitypub\Activitypub', 'add_rewrite_endpoint' ) );
		add_filter( 'pre_get_avatar_data', array( '\Activitypub\Activitypub', 'pre_get_avatar_data' ), 11, 2 );

		add_post_type_support( 'post', 'activitypub' );
		add_post_type_support( 'page', 'activitypub' );

		$post_types = get_post_types_by_support( 'activitypub' );
		add_action( 'transition_post_status', array( '\Activitypub\Activitypub', 'schedule_post_activity' ), 10, 3 );
	}
	/**
	 * Return a AS2 JSON version of an author, post or page
	 *
	 * @param  string $template the path to the template object
	 *
	 * @return string the new path to the JSON template
	 */
	public static function render_json_template( $template ) {
		if ( ! is_author() && ! is_singular() ) {
			return $template;
		}

		if ( is_author() ) {
			$json_template = dirname( __FILE__ ) . '/../templates/json-author.php';
		} elseif ( is_singular() ) {
			$json_template = dirname( __FILE__ ) . '/../templates/json-post.php';
		}

		global $wp_query;

		if ( isset( $wp_query->query_vars['as2'] ) ) {
			return $json_template;
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			return $template;
		}

		// interpret accept header
		$pos = stripos( $_SERVER['HTTP_ACCEPT'], ';' );
		if ( $pos ) {
			$accept_header = substr( $_SERVER['HTTP_ACCEPT'], 0, $pos );
		} else {
			$accept_header = $_SERVER['HTTP_ACCEPT'];
		}
		// accept header as an array
		$accept = explode( ',', trim( $accept_header ) );

		if (
			! in_array( 'application/activity+json', $accept, true ) &&
			! in_array( 'application/ld+json', $accept, true ) &&
			! in_array( 'application/json', $accept, true )
		) {
			return $template;
		}

		return $json_template;
	}

	/**
	 * Add the 'photos' query variable so WordPress
	 * won't mangle it.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'as2';

		return $vars;
	}

	/**
	 * Add our rewrite endpoint to permalinks and pages.
	 */
	public static function add_rewrite_endpoint() {
		add_rewrite_endpoint( 'as2', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
	}

	/**
	 * Marks the post as "no webmentions sent yet"
	 *
	 * @param int $post_id
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'activitypub_send_post_activity', array( $post->ID ) );
		} elseif ( 'publish' === $new_status ) {
			wp_schedule_single_event( time() + wp_rand( 0, 120 ), 'activitypub_send_update_activity', array( $post->ID ) );
		}
	}

	/**
	 * Replaces the default avatar
	 *
	 * @param array             $args Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object
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

		$allowed_comment_types = apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if ( ! empty( $id_or_email->comment_type ) && ! in_array( $id_or_email->comment_type, (array) $allowed_comment_types, true ) ) {
			$args['url'] = false;
			/** This filter is documented in wp-includes/link-template.php */
			return apply_filters( 'get_avatar_data', $args, $id_or_email );
		}

		// check if comment has an avatar
		$avatar = self::get_avatar_url( $id_or_email->comment_ID );

		if ( $avatar ) {
			if ( ! isset( $args['class'] ) || ! is_array( $args['class'] ) ) {
				$args['class'] = array( 'u-photo' );
			} else {
				$args['class'][] = 'u-photo';
				$args['class']   = array_unique( $args['class'] );
			}
			$args['url']     = $avatar;
			$args['class'][] = 'avatar-activitypub';
		}

		return $args;
	}

	/**
	 * Function to retrieve Avatar URL if stored in meta
	 *
	 *
	 * @param int|WP_Comment $comment
	 *
	 * @return string $url
	 */
	public static function get_avatar_url( $comment ) {
		if ( is_numeric( $comment ) ) {
			$comment = get_comment( $comment );
		}
		return get_comment_meta( $comment->comment_ID, 'avatar_url', true );
	}
}
