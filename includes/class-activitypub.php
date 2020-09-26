<?php
namespace Activitypub;

/**
 * ActivityPub Class
 *
 * @author Matthias Pfefferle
 */
class Activitypub {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'template_include', array( '\Activitypub\Activitypub', 'render_json_template' ), 99 );
		\add_filter( 'query_vars', array( '\Activitypub\Activitypub', 'add_query_vars' ) );
		\add_action( 'init', array( '\Activitypub\Activitypub', 'add_rewrite_endpoint' ) );
		\add_filter( 'pre_get_avatar_data', array( '\Activitypub\Activitypub', 'pre_get_avatar_data' ), 11, 2 );
		\add_action( 'wp_head', array( '\Activitypub\Activitypub', 'author_atom_uri' ), 2 );
		\add_filter( 'status_edit_pre', array( '\Activitypub\Activitypub', 'set_post_type_status_private'), 10, 2 );
		// Add support for ActivityPub to custom post types
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array();
		foreach ( $post_types as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}

		\add_action( 'transition_post_status', array( '\Activitypub\Activitypub', 'schedule_post_activity' ), 10, 3 );
		\add_action( 'transition_comment_status', array( '\Activitypub\Activitypub', 'schedule_comment_activity' ), 10, 3 );
		//\add_action( 'transition_comment_status', array( '\Activitypub\Activitypub', 'schedule_inbox_forward_activity' ), 10, 3 );
	}

	/**
	 * Return a AS2 JSON version of an author, post or page
	 *
	 * @param  string $template the path to the template object
	 *
	 * @return string the new path to the JSON template
	 */
	public static function render_json_template( $template ) {
		if ( ! \is_author() && ! \is_singular() ) {
			return $template;
		}

		if ( \is_author() ) {
			$json_template = \dirname( __FILE__ ) . '/../templates/json-author.php';
		} elseif ( \is_singular() ) {
			$json_template = \dirname( __FILE__ ) . '/../templates/json-post.php';
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

		// accept header as an array
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
	 * Add the 'photos' query variable so WordPress
	 * won't mangle it.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'activitypub';

		return $vars;
	}

	/**
	 * Add our rewrite endpoint to permalinks and pages.
	 */
	public static function add_rewrite_endpoint() {
		\add_rewrite_endpoint( 'activitypub', EP_AUTHORS | EP_PERMALINK | EP_PAGES );
	}

	/**
	 * ActivityPub Audience sets Post status to private (but also immediately publishes post)
	 * 
	 * TODO: Unexpected outcome of set audience+save_draft (Private Publish)
	 * 
	 * @param int $post_id
	 * @param string $status
	 */
	public static function set_post_type_status_private( $status, $post_id ) {
		\error_log( '$status: ' . $status );
		$audience = \get_post_meta( $post_id, '_ap_audience_meta_key' );
		if ( in_array( 'private_message', $audience ) || in_array( 'followers_only', $audience ) ) {
			$status = 'private';
		}
		return $status;
	}

	/**
	 * Schedule Activities
	 *
	 * @param int $post_id
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		// do not send activities if post is password protected
		if ( \post_password_required( $post ) ) {
			return;
		}
		error_log('schedule_post_activity ($new_status):' . $new_status . ': ' . $post->ID );
		// check if post-type supports ActivityPub
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$audience = \get_post_meta( $post->ID, '_ap_audience_meta_key' );
		
		if ( 'publish' === $new_status && $new_status !== $old_status ) {
			if ( in_array( 'private_message', $audience ) || in_array( 'followers_only', $audience ) ) {
				$new_status = 'private_message';
				error_log('schedule_post_activity ($new_status):' . $new_status . ': ' . $post->ID );
				\wp_schedule_single_event( \time(), 'activitypub_send_private_activity', array( $post->ID ) );
			} else {
				\wp_schedule_single_event( \time(), 'activitypub_send_post_activity', array( $post->ID ) );
			}
		} elseif ( 'publish' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_private_activity', array( $post->ID ) );
		} elseif ( 'publish' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_update_activity', array( $post->ID ) );
		} elseif ( 'trash' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_delete_activity', array( get_permalink( $post ) ) );
		}
	}

	/**
	 * Schedule Activities
	 *
	 * @param int $comment
	 */
	public static function schedule_comment_activity( $new_status, $old_status, $comment ) {
		\error_log( 'schedule_comment_activity');
		
		// error_log( 'schedule_comment_activity: $new_status: ' . $new_status);
		// error_log( 'schedule_comment_activity: $old_status: ' . $old_status );
		
		\error_log( print_r( $comment, true ) );

		//reply to federated, 

		//$ap_object = get_comment_meta( $comment->comment_ID, 'ap_object', true );
		
		if ( 'approved' === $new_status && 'approved' !== $old_status ) {
			$ap_object = get_comment_meta( $comment->comment_ID, 'ap_object', true );
			if ( $ap_object ) {
				\error_log( 'schedule_inbox_forward_activity: ID: ' . $comment->comment_ID );
				\error_log( print_r( $comment, true ) );
				\wp_schedule_single_event( \time(), 'activitypub_inbox_forward_activity', array( $comment->comment_ID ) );
			} else {
				$replyto = comment_author_url( $comment->comment_parent );
				\error_log( 'schedule_comment_activity: ID: ' . $comment->comment_ID );
				error_log( 'schedule_comment_activity: replyto: ' . $replyto );
				\wp_schedule_single_event( \time(), 'send_comment_activity', array( $comment->comment_ID ) );
			}
			
			
		// } elseif ( 'approved' === $new_status && ( $ap_object ) ) {
		// 	error_log( 'approved: $replyto: ' . $replyto );
		// 	\wp_schedule_single_event( \time(), 'activitypub_send_comment_activity', array( $comment->comment_ID ) );
		} else {
			error_log( 'not_approved: other comment action: ' . print_r( $comment, true ) );
		}
	}

	/**
	 * Schedule Activities
	 *
	 * @param int $post_id
	 */
	public static function schedule_inbox_forward_activity( $new_status, $old_status, $comment ) {
		error_log( 'schedule_inbox_forward_activity: ID: ' . $comment->comment_ID );
		$ap_object = get_comment_meta( $comment->comment_ID, 'ap_object', true );
		error_log( print_r( unserialize( $ap_object ), true ) );
		if ( !$ap_object ) {
			return;
		}
		// error_log( 'schedule_inbox_forward_activity: $new_status: ' . $new_status);
		// error_log( 'schedule_inbox_forward_activity: $old_status: ' . $old_status );
		
		//foreign comment has just been approved: forward should happen here, no?
		//error_log( print_r( $comment, true ) );

		//$ap_object = get_comment_meta( $comment->comment_ID, 'ap_object', true );
		$replyto = $comment->comment_author_url;
		if ( ( 'approved' === $new_status && 'approved' !== $old_status ) && ( $ap_object ) ) {
			//error_log( 'schedule_inbox_forward_activity>replyto: ' . $replyto );
			//error_log( print_r( , true ) );
			\wp_schedule_single_event( \time(), 'activitypub_inbox_forward_activity', array( $comment->comment_ID ) );
		// } elseif ( 'approved' === $new_status && ( $ap_object ) ) {
		// 	error_log( 'approved: $replyto: ' . $replyto );
		// 	\wp_schedule_single_event( \time(), 'activitypub_send_comment_activity', array( $comment->comment_ID ) );
		} else {
			error_log( 'schedule_inbox_forward_activity: not_approved: $from: ' . $replyto );
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

		$allowed_comment_types = \apply_filters( 'get_avatar_comment_types', array( 'comment', 'activitypub' ) );
		if ( ! empty( $id_or_email->comment_type ) && ! \in_array( $id_or_email->comment_type, (array) $allowed_comment_types, true ) ) {
			$args['url'] = false;
			/** This filter is documented in wp-includes/link-template.php */
			return \apply_filters( 'get_avatar_data', $args, $id_or_email );
		}

		// check if comment has an avatar
		$avatar = self::get_avatar_url( $id_or_email->comment_ID, ['default' => 'default'] );

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
	 * Function to retrieve Avatar URL if stored in meta
	 *
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

	public static function author_atom_uri(){
		if ( is_author() ) {
			$obj_id = get_queried_object_id();
			$current_url = get_author_posts_url( $obj_id );
			?><link rel="alternate" type="application/rss+xml" title="<?php echo bloginfo('name') . ' Author Atom Feed '; ?>" href="<?php echo $current_url . 'feed/atom/'; ?>" ><?php 
		}
	}
}
