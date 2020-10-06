<?php
namespace Activitypub\Rest;

/**
 * ActivityPub Inbox REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'rest_api_init', array( '\Activitypub\Rest\Inbox', 'register_routes' ) );
		\add_filter( 'rest_pre_serve_request', array( '\Activitypub\Rest\Inbox', 'serve_request' ), 11, 4 );
		\add_action( 'activitypub_inbox_follow', array( '\Activitypub\Rest\Inbox', 'handle_follow' ), 10, 2 );
		\add_action( 'activitypub_inbox_unfollow', array( '\Activitypub\Rest\Inbox', 'handle_unfollow' ), 10, 2 );
		//\add_action( 'activitypub_inbox_like', array( '\Activitypub\Rest\Inbox', 'handle_reaction' ), 10, 2 );
		//\add_action( 'activitypub_inbox_announce', array( '\Activitypub\Rest\Inbox', 'handle_reaction' ), 10, 2 );
		\add_action( 'activitypub_inbox_create', array( '\Activitypub\Rest\Inbox', 'handle_create' ), 10, 2 );
		
	//	\add_action( 'pre_get_posts', array( '\Activitypub\Rest\Inbox', 'filter_private_messages' ), 11 );
	//	\add_action( 'wp_count_comments', array( '\Activitypub\Rest\Inbox', 'count_comments' ), 11, 2 );
	//	\add_filter( 'comments_clauses', array( '\Activitypub\Rest\Inbox', 'ap_personal_comment_list'), 11 );
	//	\add_action( 'admin_head', array( '\Activitypub\Rest\Inbox', 'comments_styles' ), 21 );
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			'activitypub/1.0', '/inbox', array(
				array(
					'methods'  => \WP_REST_Server::EDITABLE,
					'callback' => array( '\Activitypub\Rest\Inbox', 'shared_inbox' ),
					'permission_callback' => '__return_true'
				),
			)
		);

		\register_rest_route(
			'activitypub/1.0', '/users/(?P<user_id>\d+)/inbox', array(
				array(
					'methods'  => \WP_REST_Server::EDITABLE,
					'callback' => array( '\Activitypub\Rest\Inbox', 'user_inbox' ),
					'args'     => self::request_parameters(),
					'permission_callback' => '__return_true'
				),
			)
		);

		// \register_rest_route(
		// 	'activitypub/1.0', '/users/(?P<user_id>\d+)/inbox', array(
		// 		array(
		// 			'methods'  =>  \WP_REST_Server::READABLE,
		// 			'callback' => array( '\Activitypub\Rest\Inbox', 'user_inbox_get' ),
		// 			'args'     => self::get_parameters(),
		// 			'permission_callback' => '__return_true'
		// 		),
		// 	)
		// );
	}

	/**
	 * Hooks into the REST API request to verify the signature.
	 *
	 * @param bool                      $served  Whether the request has already been served.
	 * @param WP_HTTP_ResponseInterface $result  Result to send to the client. Usually a WP_REST_Response.
	 * @param WP_REST_Request           $request Request used to generate the response.
	 * @param WP_REST_Server            $server  Server instance.
	 *
	 * @return true
	 */
	public static function serve_request( $served, $result, $request, $server ) {
		if ( '/activitypub' !== \substr( $request->get_route(), 0, 12 ) ) {
			return $served;
		}

		$signature = $request->get_header( 'signature' );

		if ( ! $signature ) {
			return $served;
		}

		$headers = $request->get_headers();

		// verify signature
		//\Activitypub\Signature::verify_signature( $headers, $key );

		return $served;
	}

	/**
	 * Renders the user-inbox
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function user_inbox( $request ) {
		$user_id = $request->get_param( 'user_id' );

//		error_log( 'user_inbox $request: ' . print_r( $request, true ) );
		$data = $request->get_params();
		$type = $request->get_param( 'type' );

		\do_action( 'activitypub_inbox', $data, $user_id, $type );
		\do_action( "activitypub_inbox_{$type}", $data, $user_id );

		return new \WP_REST_Response( array(), 202 );
	}

	/**
	 * Renders the user-inbox
	 *
	 * @param  WP_REST_Request   $request
	 *
	 * @return WP_REST_Response
	 */
	public static function user_inbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );

		$json = new \stdClass();

		$json->{'@context'} = \Activitypub\get_context();
		$json->id = \get_rest_url( null, "/activitypub/1.0/users/$user_id/inbox" ); // phpcs:ignore
		$json->type = 'OrderedCollection';
		
		$response = new \WP_REST_Response( $json, 200 );

		$response->header( 'Content-Type', 'application/activity+json' );

		return $response;
	}

	/**
	 * The shared inbox
	 *
	 * @param  [type] $request [description]
	 *
	 * @return WP_Error not yet implemented
	 */
	public static function shared_inbox( $request ) {

	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'integer',
		);

		$params['id'] = array(
			'required' => true,
			'type' => 'string',
			'validate_callback' => function( $param, $request, $key ) {
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return ! \Activitypub\is_blacklisted( $param );
			},
			'sanitize_callback' => 'esc_url_raw',
		);

		$params['actor'] = array(
			'required' => true,
			//'type' => array( 'object', 'string' ),
			'validate_callback' => function( $param, $request, $key ) {
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return ! \Activitypub\is_blacklisted( $param );
			},
			'sanitize_callback' => function( $param, $request, $key ) {
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return \esc_url_raw( $param );
			},
		);

		$params['type'] = array(
			'required' => true,
			//'type' => 'enum',
			//'enum' => array( 'Create' ),
			'sanitize_callback' => function( $param, $request, $key ) {
				return \strtolower( $param );
			},
		);

		$params['object'] = array(
			'required' => true,
			//'type' => 'object',
		);

		return $params;
	}

	/**
	 * The supported parameters
	 *
	 * @return array list of parameters
	 */
	public static function get_parameters() {
		$params = array();

		$params['page'] = array(
			'type' => 'integer',
		);

		$params['user_id'] = array(
			'required' => true,
			'type' => 'integer',
		);

		$params['id'] = array(
//			'required' => true,
			'type' => 'string',
			'validate_callback' => function( $param, $request, $key ) {
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return ! \Activitypub\is_blacklisted( $param );
			},
			'sanitize_callback' => 'esc_url_raw',
		);

		$params['actor'] = array(
//			'required' => true,
			//'type' => array( 'object', 'string' ),
			'validate_callback' => function( $param, $request, $key ) {
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return ! \Activitypub\is_blacklisted( $param );
			},
			'sanitize_callback' => function( $param, $request, $key ) {
				if ( ! \is_string( $param ) ) {
					$param = $param['id'];
				}
				return \esc_url_raw( $param );
			},
		);

		return $params;
	}

	/**
	 * Handles "Follow" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_follow( $object, $user_id ) {
		// save follower
		\Activitypub\Peer\Followers::add_follower( $object['actor'], $user_id );

		// get inbox
		$inbox = \Activitypub\get_inbox_by_actor( $object['actor'] );

		// send "Accept" activity
		$activity = new \Activitypub\Model\Activity( 'Accept', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activity->set_object( $object );
		$activity->set_actor( \get_author_posts_url( $user_id ) );
		$activity->set_to( $object['actor'] );
		$activity->set_id( \get_author_posts_url( $user_id ) . '#follow' . \preg_replace( '~^https?://~', '', $object['actor'] ) );

		$activity = $activity->to_simple_json();
		$response = \Activitypub\safe_remote_post( $inbox, $activity, $user_id );
	}

	/**
	 * Handles "Unfollow" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_unfollow( $object, $user_id ) {
		\Activitypub\Peer\Followers::remove_follower( $object['actor'], $user_id );
	}

	/**
	 * Handles "Reaction" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_reaction( $object, $user_id ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );

		$commentdata = array(
			'comment_post_ID' => \url_to_postid( $object['object'] ),
			'comment_author' => \esc_attr( $meta['name'] ),
			'comment_author_email' => '',
			'comment_author_url' => \esc_url_raw( $object['actor'] ),
			'comment_content' => \esc_url_raw( $object['actor'] ),
			'comment_type' => \esc_attr( \strtolower( $object['type'] ) ),
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_url' => \esc_url_raw( $object['attributedTo'] ),
				'avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		$state = \wp_new_comment( $commentdata, true );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_create( $object, $user_id ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );
		
		$comment_parent = $comment_parent_ID = 0;
		$comment_post_ID = \url_to_postid( $object['object']['inReplyTo'] );
		//if not a direct reply to a post
		if ( $comment_post_ID === 0 ) {
			//verify if reply to a local or remote received comment
			$comment_parent_ID = \Activitypub\url_to_commentid( \esc_url_raw( $object['object']['inReplyTo'] ) );
			if ( !is_null( $comment_parent_ID ) ) {
				//replied to a local comment (which has a post_ID)
				$comment_parent = get_comment( $comment_parent_ID );
				$comment_post_ID = $comment_parent->comment_post_ID;
			}
		}

		//not all implementaions use url
		if ( isset( $object['object']['url'] ) ) {
			$source_url = \esc_url_raw( $object['object']['url'] );
		} else {
			//could also try $object['object']['source']?
			$source_url = \esc_url_raw( $object['object']['id'] );
		}

		// if no name is set use peer username
		if ( !empty( $meta['name'] ) ) {
			$name = \esc_attr( $meta['name'] );
		} else {
			$name = \esc_attr( $meta['preferredUsername'] );
		}
		
		//Only create comments for public replies to posts
// ??? Only create comments for public replies to PUBLIC posts
// ??? Why not private replies to posts (should we manage private comments? [the global comments system])	
		if ( ( in_array( 'https://www.w3.org/ns/activitystreams#Public', $object['to'] )
			|| in_array( 'https://www.w3.org/ns/activitystreams#Public', $object['cc'] ) )
			&& ( !empty( $comment_post_ID ) 
			|| !empty ( $comment_parent ) 
			) ) {
			
			$commentdata = array(
				'comment_post_ID' => $comment_post_ID,
				'comment_author' => $name,
				'comment_author_url' => \esc_url_raw( $object['actor'] ),
				'comment_content' => \wp_filter_kses( $object['object']['content'] ),
				'comment_type' => 'activitypub',
				'comment_author_email' => '',
				'comment_parent' => $comment_parent_ID,
				'comment_meta' => array(
					//'local_user' => $object['user_id'],//access  get_post_field ('post_author', 'comment_post_ID');
					'inReplyTo' => \esc_url_raw( $object['object']['inReplyTo'] ),//needed? (if replying to someone else on thread, but not received)non-wp status - comment_post_ID, comment_parent
					'source_url' => $source_url,
					'avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
					'ap_object' => \serialize( $object ), //$object for inbox-forwarding
					'protocol' => 'activitypub',
				),
			);

			// disable flood control
			\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

			$state = \wp_new_comment( $commentdata, true );

			// re-add flood control
			\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		} else {
			//Not a public reply to a public post
// TODO if $object['attachment']... append to content
			$title = $summary = null;
			if ( isset( $object['object']['summary'] ) ) {
				$title = \wp_trim_words( $object['object']['summary'], 10 );
				$summary = \wp_strip_all_tags( $object['object']['summary'] );
			}
			$to = get_user_by( 'id', $object['user_id'] );
			$to_url = get_author_posts_url( $object['user_id'] );
			error_log( 'inbox:handle_create:to: ' . $to_url );
			// TODO if empty( $object['object']['summary'] ) trim+filter  $object['object']['content']  
			$postdata = array(
				'post_content' => \wp_filter_kses( $object['object']['content'] ),
				'post_title' => $title,
				'post_excerpt' => $summary,
				'post_status' => 'private_message',
				'post_type' => 'activitypub_mentions',//activitypub
				//'post_hierarchy' => comment_parent_ID
				'post_parent' => \esc_url_raw( $object['object']['inReplyTo'] ),
				'meta_input' => array(
					'_local_user' => $object['user_id'],
					'_ap_object' => $object,
					'_read_status' => 'unread',
					//'mention_type' => \esc_html($object['object']['type']),
					'_inreplyto' => \esc_url_raw( $object['object']['inReplyTo'] ),
					'_author' => $name,
					'_author_url' => \esc_url_raw( $object['actor'] ),
					'_source_url' => $source_url,
					'_avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
					'_protocol' => 'activitypub',
				),
			);

			//NON-Public
			if ( !in_array( 'https://www.w3.org/ns/activitystreams#Public', $object['to'] )
					&& !in_array( 'https://www.w3.org/ns/activitystreams#Public', $object['cc'] ) ) {

						if ( in_array($object['object']['attributedTo'] . '/followers', $object['to']) || in_array($object['object']['attributedTo'] . '/followers', $object['cc']) ) {
							//Followers Only
							error_log( 'AP: Followers Only: ' . $to->user_login  );
							$postdata['post_status'] = 'followers_only';
							$postdata['meta_input']['mention_type'] = 'activitypub_fo';
						} elseif ( in_array( get_author_posts_url( $object['user_id'] ), $object['to']) || in_array(get_author_posts_url($to->ID), $object['cc']) ) {
							//Private Message
							error_log( 'AP: Private Message to: ' . $to->user_login );
							$postdata['post_status'] = 'private_message';
							$postdata['meta_input']['mention_type'] = 'activitypub_dm';
						} else {
							error_log( 'AP: WTF some type of non public mention to: ' . $to->user_login );
						}
			} elseif ( empty( \url_to_postid( $object['object']['inReplyTo'] ) ) ) {
				// This should catch public mentions (non-reply)
				// https://www.w3.org/ns/activitystreams#Public
				if ( !in_array( 'https://www.w3.org/ns/activitystreams#Public', $object['to'] )
						&& in_array( 'https://www.w3.org/ns/activitystreams#Public', $object['cc'] ) ) {
							error_log( 'AP: Unlisted mention : ' . $to->user_login );
							$postdata['post_status'] = 'unlisted';//or public?
							$postdata['meta_input']['mention_type'] = 'activitypub_ul';
				} else {
					error_log( 'AP: Public mention : ' . $to->user_login );
					$postdata['post_status'] = 'public';
					$postdata['meta_input']['ap_object'] = \serialize( $object );
					$postdata['meta_input']['mention_type'] = 'activitypub_public';//or just 'activitypub'
				}

			} else {
				//Don't know wtf would end up here
				error_log( 'AP: WTF : ' . $to->user_login );
			}
			$post_id = \wp_insert_post($postdata);

			if( !is_wp_error($post_id) ) {
				error_log($post_id);
		    	wp_send_json_success(array('post_id' => $post_id), 200);
		  } else {
				error_log($post_id->get_error_message());
		    	wp_send_json_error($post_id->get_error_message());
		  }
		}

	}

	public static function comments_styles() {
		echo '<style>';
		echo '.wp-list-table>tbody>.activitypub_dm{ background: #fcc !important; }';
		echo '.wp-list-table>tbody>.activitypub_fo{ background: #cfc !important; }';
		echo '</style>';
	}

	//\add_action( 'init', '\Activitypub\add_rewrite_rules', 1 );
	public static function filter_private_messages( $query ) {
		$ap_public_comments = array( 'activitypub_ul', 'activitypub' );
		$ap_private_comments = array( 'activitypub_dm', 'activitypub_fo' );
		//make public static constants for public/private comment_types

		//if register setting for public comment moderation or not? if not merge arrays
		$ap_types = array_merge( $ap_public_comments, $ap_private_comments );
		if (is_admin()){

			$current_screen = get_current_screen();
			if( $current_screen->parent_base === 'activitypub-mentions-list'){
				//echo "<details><summary>current_screen</summary><pre>"; print_r( $current_screen ); echo "</pre></details>";
			 	//echo "<details><summary>query</summary><pre>"; print_r( $query ); echo "</pre></details>";
				//$query->set( 'post_status', 'private_message' );
				// $query->query_vars['post_type'] = 'activitypub_messages';
				// $query->query_vars['post_status'] = 'private_message';
				//$query->query_vars['type__not_in'] = $ap_types;
				//$query->query_vars['type__not_in'] = in_array( $query->query_vars['type__not_in'], $ap_types);
			}
		}
	}

	// public static function count_comments( $count, $post_id ) {
	// 	$ap_public_comments = array( 'activitypub_ul', 'activitypub' );
	// 	$ap_private_comments = array( 'activitypub_dm', 'activitypub_fo' );
	// 	$ap_types = array_merge( $ap_public_comments, $ap_private_comments );
	// 	$current_screen = get_current_screen();
	// 	//$comments_count = wp_count_comments();
	//
	// 	if( !is_null( $current_screen ) ){
	// 		if( $current_screen->id === 'edit-comments' ){
	// 			// echo "<pre> count_comments </pre>";
	// 			// echo "<pre>"; print_r( $count ); echo "</pre>";
	// 			//$query->query_vars['type__not_in'] = in_array( $query->query_vars['type__not_in'], $ap_types);
	// 		}
	// 	}
	// 	if ( 0 === $post_id ){
	// 		var_dump( 'count_comments site' );
	// 		// echo "<pre>"; print_r( $count ); echo "</pre>";
	// 	}
	// }

	// public static function ap_personal_comment_list($clauses){
	// 	if ( is_admin() ) {
	// 		global $current_user, $wpdb;
	// 		$clauses['join'] = "wp_posts";
	// 		$clauses['where'] .= " AND wp_posts.post_author = ".$current_user->ID." AND wp_comments.comment_post_ID = wp_posts.ID";
	// 	};
	// 	// echo "<pre> comments_clauses </pre>";
	// 	// echo "<pre style='padding-left:180px;'>"; print_r( $clauses ); echo "</pre>";
	// 	return $clauses;
	// }


	//\add_action( 'pre_comment_approved', array( '\Activitypub\Rest\Inbox', 'filter_comments' ), 11, 2 );
	// public static function filter_comments( $approved, $data ) {
	//  /* only allow 'my_custom_comment_type' when is required explicitly */
	//  //echo "<pre>"; print_r( $query ); echo "</pre>";
	//  return isset($data['comment_type']) && $data['comment_type'] === 'activitypub_dm'
	// 	 ? 1
	// 	 : $approved;
	// 			//	array( 'activitypub_dm', 'activitypub_fo' )
	// }
}
