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

		// Add support for ActivityPub to custom post types
		$post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page', 'mentions' ) );
		foreach ( $post_types as $post_type ) {
			\add_post_type_support( $post_type, 'activitypub' );
		}
		
		\add_action( 'pre_get_posts', array( '\Activitypub\Activitypub', 'private_inbox' ), 10, 2 );
		\add_filter( 'status_edit_pre', array( '\Activitypub\Activitypub', 'set_post_type_status_private' ), 10, 2 );
		\add_action( 'transition_post_status', array( '\Activitypub\Activitypub', 'preprocess_post' ), 1, 3 );
		\add_action( 'transition_post_status', array( '\Activitypub\Activitypub', 'schedule_post_activity' ), 10, 3 );
		\add_filter( 'preprocess_comment' , array( '\Activitypub\Activitypub', 'preprocess_comment' ) );
		\add_filter( 'comment_post' , array( '\Activitypub\Activitypub', 'postprocess_comment' ), 10, 3 );
		\add_action( 'transition_comment_status', array( '\Activitypub\Activitypub', 'schedule_comment_activity' ), 20, 3 );
		
		\add_filter( 'pre_get_avatar_data', array( '\Activitypub\Activitypub', 'pre_get_avatar_data' ), 11, 2 );
		\add_action( 'wp_head', array( '\Activitypub\Activitypub', 'author_atom_uri' ), 2 );// TODO test if needed 
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
	 * Private Inbox
	 * 
	 * pre_get_posts
	 */
	public static function private_inbox( $query ) {
		//TODO can the wp_post_count be updated? https://wordpress.stackexchange.com/a/151876/87622
		if( is_admin() && $query->is_main_query() ) {
			if ( $query->query['post_type'] === 'activitypub' ) {
				
				// Only show posts to their author
				$query->set( 'author', \get_current_user_id() );
				
				// Hide reported posts from all_posts list 
				if ( empty( $query->query['post_status'] ) ) {
					$query->set( 'post_status', array( 'publish', 'pending', 'draft', 'auto-draft', 'private', 'future', 'inbox' ) );
				}

				// Allow moderators access to reported posts
				// TODO create moderate_mentions capability (align with delete_posts or moderate_comments)
				if ( $query->query['post_status'] === 'moderation' && current_user_can( 'moderate_comments' ) ) {
					$query->set( 'author', '' );
				}

				// TODO orderby date desc
				// Orderby
				$orderby = $query->get( 'orderby');
 
				// if( 'author' == $orderby ) {
				// 	$query->set('meta_key','author');
				// 	$query->set('orderby','meta_value');
				// }
				// if( 'type' == $orderby ) {
				// 	$query->set('meta_key','type');
				// 	$query->set('orderby','meta_value');
				// }
			}
		}

		if( ! is_admin() ) {
			return;
		}
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
		$audience = \get_post_meta( $post_id, '_audience' );
		if ( in_array( 'private', $audience ) || in_array( 'followers_only', $audience ) ) {
			$status = 'private';
		}
		return $status;
	}

	/**
	 * Schedule Activities
	 * transition_post_status
	 * https://developer.wordpress.org/reference/hooks/transition_post_status/
	 */
	public static function preprocess_post( $new_status, $old_status, $post ) {
		if ( isset( $_POST['_audience'] ) ) {
            update_post_meta( $post->ID, '_audience', $_POST['_audience'] );
		}
		if ( isset( $_POST['_mentions'] ) ) {
            update_post_meta( $post->ID, '_mentions', $_POST['_mentions'] );
		}
		if ( isset( $_POST['post_content'] ) || isset( $_POST['post_parent'] ) ) {
			
			$update_post['ID'] = $post->ID;

			// Tag users
			$tagged_content = \Activitypub\transform_tags( $post->post_content );
			if ( ! empty ( $tagged_content['mentions'] ) ) {

				// TODO : How to not replace previously saved mentions?
				// Only hook on publish?
				\update_post_meta( $post->ID, '_mentions', $tagged_content['mentions'] );
				$update_post['post_content'] = $tagged_content['content'];

			}
			// Set parent_post
			if ( isset( $_POST['post_parent'] ) ) {
				$update_post['post_parent'] = $_POST['post_parent'];
			}

			\wp_update_post( $update_post, true );
		}
	}

	/**
	 * Schedule Post Activities
	 * https://developer.wordpress.org/reference/hooks/transition_post_status/
	 *
	 * @param int $post_id
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		// do not send activities if post is password protected
		if ( \post_password_required( $post ) ) {
			return;
		}

		// check if post-type supports ActivityPub
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		// do not send inbox or moderation activities
		if ( $new_status === 'inbox' || $new_status === 'moderation' ) {
			return;
		}
		$audience = \get_post_meta( $post->ID, '_audience' );
		$activitypub_post = new \Activitypub\Model\Post( $post );
		
		if ( 'publish' === $new_status && $new_status !== $old_status ) {
			if ( in_array( 'private', $audience ) || in_array( 'followers_only', $audience ) ) {
				//\wp_schedule_single_event( \time(), 'activitypub_send_private_activity', array( $activitypub_post ) );
			} else {
				\wp_schedule_single_event( \time(), 'activitypub_send_post_activity', array( $activitypub_post ) );
			}
		} elseif ( 'private' === $new_status ) {
			//\wp_schedule_single_event( \time(), 'activitypub_send_private_activity', array( $activitypub_post ) );
		} elseif ( 'publish' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_update_activity', array( $activitypub_post ) );
		} elseif ( 'trash' === $new_status ) {
			\wp_schedule_single_event( \time(), 'activitypub_send_delete_activity', array( get_permalink( $activitypub_post ) ) );
		}
	}

	/**
	 * preprocess local comments for federated replies
	 */
	public static function preprocess_comment( $commentdata ) {
		
		//must only process replies from local actors
		if ( !empty( $commentdata['user_id'] ) ) {
			//\error_log( 'is_local user' );//TODO Test
			//TODO TEST
			$post_type = \get_object_subtype( 'post', $commentdata['comment_post_ID'] );
			$ap_post_types = \get_option( 'activitypub_support_post_types' );
			if ( !\is_null( $ap_post_types ) ) {
				if ( in_array( $post_type, $ap_post_types ) ) {
					$commentdata['comment_type'] = 'activitypub';
					// transform webfinger mentions to links and add @mentions to cc
					$tagged_content = \Activitypub\transform_tags( $commentdata['comment_content'] );
					$commentdata['comment_content'] = $tagged_content['content'];
					$commentdata['comment_meta']['mentions'] = $tagged_content['mentions'];
				}
			}
		}
    	return $commentdata;
  	}

	/**
	 * postprocess_comment for federating replies and inbox-forwarding
	 */
	public static function postprocess_comment( $comment_id, $comment_approved, $commentdata ) {
		//Admin users comments bypass transition_comment_status (auto approved)

		//\error_log( 'postprocess_comment_handler: comment_status: ' . $comment_approved );
		if ( $commentdata['comment_type'] === 'activitypub' ) {
			if ( 
				( $comment_approved === 1 ) && 
				! empty( $commentdata['user_id'] ) &&
				( $user = get_userdata( $commentdata['user_id'] ) ) && // get the user data
				in_array( 'administrator', $user->roles )                   // check the roles
			)  {
				// Only for Admins?
				$mentions = \get_comment_meta( $comment_id, 'mentions', true );
				//\ActivityPub\Activity_Dispatcher::send_comment_activity( $comment_id ); // performance > followers collection
				\wp_schedule_single_event( \time(), 'activitypub_send_comment_activity', array( $comment_id ) );
				 
			} else {
				// TODO check that this is unused
				// TODO comment test as anon
				// TODO comment test as registered 
				// TODO comment test as anyother site settings
				

				// $replyto = get_comment_meta( $comment_id, 'replyto', true );
				
				//inbox forward prep
				// if ( !empty( $ap_object ) ) {
				// 	//if is remote user (has ap_object)
				// 	//error_log( print_r( $ap_object, true ) );
				// 	// TODO verify that deduplication check happens at object create.

				// 	//if to/cc/audience contains local followers collection 
				// 	//$local_user = \get_comment_author_url( $comment_id );
				// 	//$is_local_user = \Activitypub\url_to_authorid( $commentdata['comment_author_url'] );
					
				// }
			} 
		}
	}
	  
	/**
	 * Schedule Activities
	 *
	 * @param int $comment
	 */
	public static function schedule_comment_activity( $new_status, $old_status, $activitypub_comment ) {
		
		// TODO format $activitypub_comment = new \Activitypub\Model\Comment( $comment );
		if ( 'approved' === $new_status && 'approved' !== $old_status ) {
			//should only federate replies from local actors
			//should only federate replies to federated actors
			
			$ap_object = unserialize( \get_comment_meta( $activitypub_comment->comment_ID, 'ap_object', true ) );
			if ( empty( $ap_object ) ) {
				\wp_schedule_single_event( \time(), 'activitypub_send_comment_activity', array( $activitypub_comment->comment_ID ) );
			} else {
				$local_user = \get_author_posts_url( $ap_object['user_id'] );
				if ( !is_null( $local_user ) ) {
					if ( in_array( $local_user, $ap_object['to'] )
						|| in_array( $local_user, $ap_object['cc'] )
						|| in_array( $local_user, $ap_object['audience'] )
						|| in_array( $local_user, $ap_object['tag'] )
						) {
						//if inReplyTo, object, target and/or tag are (local-wp) objects 
							//\ActivityPub\Activity_Dispatcher::inbox_forward_activity( $activitypub_comment );
							\wp_schedule_single_event( \time(), 'activitypub_inbox_forward_activity', array( $activitypub_comment->comment_ID  ) );
					}
				}				
			}
		} elseif ( 'trash' === $new_status ) {
			 	\wp_schedule_single_event( \time(), 'activitypub_send_delete_comment_activity', array( $activitypub_comment ) );
		} else {
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
