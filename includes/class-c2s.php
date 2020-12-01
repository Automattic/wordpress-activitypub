<?php
namespace Activitypub;

/**
 * ActivityPub C2S Class
 *
 * @author Django Doucet
 */
class C2S {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_filter( 'manage_activitypub_posts_columns', array( '\Activitypub\C2S', 'activitypub_posts_columns' ), 10 );
		\add_filter( 'manage_activitypub_posts_sortable_columns', array( '\Activitypub\C2S', 'activitypub_posts_sortable_columns' ), 10 );
		\add_filter( 'manage_activitypub_posts_custom_column', array( '\Activitypub\C2S', 'activitypub_posts_custom_columns' ), 20, 2 );
		\add_filter( 'page_row_actions', array( '\Activitypub\C2S', 'activitypub_post_row_actions' ), 10, 2 );
		\add_action( 'load-edit.php', array( '\Activitypub\C2S', 'activitypub_post_actions') );
		\add_filter( 'wp_revisions_to_keep', array( '\Activitypub\C2S', 'activitypub_inbox_revisions' ), 10, 2 );
		
		\add_action( 'admin_enqueue_scripts', array( '\Activitypub\C2S', 'scripts_reply_comments' ), 10, 2 );
		\add_filter( 'comment_row_actions', array( '\Activitypub\C2S', 'reply_comments_actions' ), 10, 2 );

		\add_action( 'add_meta_boxes', array( '\Activitypub\C2S', 'add_audience_metabox') );
		\add_action( 'save_post_activitypub', array( '\Activitypub\C2S', 'save_post_audience' ) );
		\add_action( 'comment_form_logged_in_after', array( '\Activitypub\C2S', 'post_audience_html' ) );
	//	\add_filter( 'comments_clauses', array( '\Activitypub\C2S', 'filter_private_comments'), 10, 2 );
	}

	public static function activitypub_inbox_revisions ( $num,  $post ) {
		if ( $post->post_status === 'inbox') {
			$num = 1;
		}
		return $num;
	}

	public static function activitypub_posts_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'actor':
				$user = \wp_get_current_user();
				$status = \get_post_field( 'post_status', $post_id, 'display' ); 
				$author_meta = \get_post_meta( $post_id );
				if ( $status === 'inbox' || $status === 'moderation' ) {
					$author = $author_meta['_author'][0];
					$author_url = $author_meta['_author_url'][0];
					$avatar_url = $author_meta['_avatar_url'][0];
					$webfinger = \Activitypub\url_to_webfinger( $author_url );
					echo "<strong><img src='$avatar_url' class='avatar avatar-32 photo' width='32' height='32' loading='lazy'>$author</strong><br><a href='$author_url'>$author_url</a>";
				} else {
					$author_url = \get_author_posts_url( $user->ID );
					echo "<strong>" . get_avatar( $user->ID, 32 ) . $user->display_name . "</strong><br><a href='$author_url'>$author_url</a>";
				}
				break;

			case 'type':
				$audience = \get_post_meta( $post_id, '_audience', true );
				if ( !empty( $audience ) ) {
					echo $audience;
				} else {
					_e( '', 'activitypub' );
				}
				break;
	 
			case 'content':
				echo \get_post_field( 'post_content', $post_id, 'display' ); 
				break;
		}
	}

	public static function activitypub_posts_sortable_columns( $columns ) {
		$custom_col_order = array(
			$columns['type'] => __( 'Type', 'activitypub' ),
			$columns['author'] => __( 'Author', 'activitypub' ),
		);
		return $custom_col_order;
	}

	public static function activitypub_posts_columns( $columns ) {
		//\error_log( 'columns: ' . print_r( $columns, true ) );
		$custom_col_order = array(
			'cb' => $columns['cb'],
			'actor' => __( 'Actor', 'activitypub' ),
			'type' => __( 'Type', 'activitypub' ),
			'title' => $columns['title'],
			'content' => __( 'Content', 'activitypub' ),
			'date' => $columns['date'],
		);
		return $custom_col_order;
	}

	/**
	 * page_row_actions
	 */
	public static function activitypub_post_row_actions( $actions, $post ) {
		if ( $post->post_type == "mention" ) {
			
			if ( $post->post_status == "inbox" ) {

				$trash = $actions['trash'];

				// Get post attributes for Reply & Quick reply
				$mentions = \Activitypub\get_recipients( $post->post_ID, true );
				$summary = \Activitypub\get_summary( $post->post_ID );

				// Reply to this post
				$reply_url = admin_url( 'post-new.php?post_type=mention&post_parent=' . $post->ID . '&_mentions=' . $mentions .'&title=' . $summary );
				$reply_link = add_query_arg( array( 'action' => 'reply' ), $reply_url );
				$actions = array(
					'reply' => sprintf( '<a href="%1$s">%2$s</a>',
					esc_url( $reply_link ),
					esc_html( __( 'Reply', 'activitypub' ) ) )
				);

				// Quick Reply to this mention
				/*$reply_format = '<button type="button" data-post-id="%d" data-action="%s" class="%s button-link" aria-expanded="false" aria-label="%s" data-recipients="%s" data-summary="%s">%s</button>';
				$actions['inline hide-if-no-js'] = sprintf(
					$reply_format,
					$post->post_ID,
					'replyto',
					'postinline',
					esc_attr__( 'Reply to this mention' ),
					$mentions,
					$summary,
					__( 'Quick reply', 'activitypub' )
				);*/

				// Block user
				/*$block_url = admin_url( 'edit.php?post_type=activitypub&post=' . $post->ID );
				$block_link = wp_nonce_url( add_query_arg( array( 'action' => 'block' ), $block_url ) );
				$actions = array_merge( $actions, array(
					'block' => sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
							esc_url( $block_link ), 
							__( 'Block this user', 'activitypub' ),
							__( 'Block', 'activitypub' )
						) 
					) 
				);*/
					
				// Report post to moderation.
				$report_url = admin_url( 'edit.php?post_type=mention&post=' . $post->ID );
				$report_link = wp_nonce_url( add_query_arg( array( 'action' => 'report' ), $report_url ) );
				$actions = array_merge( $actions, array(
					'report' => sprintf( '<a href="%1$s" title="%2$s">%3$s</a>',
							esc_url( $report_link ), 
							__( 'Report this mention to moderation', 'activitypub' ),
							__( 'Report', 'activitypub' )
						) 
					) 
				);
			
				$actions['trash'] = $trash;
			}
		}
	 
		return $actions;
	}

	/**
	* Row Actions
 	* load-edit.php
 	*/
	public static function activitypub_post_actions() {
		
		if(	isset( $_GET['post_type'] ) && $_GET['post_type'] === 'mention') {

			if(	isset( $_GET['action'] ) ) {
				// Report post for moderation (Change status)
				if(	$_GET['action'] === 'report') {
					$post_id = $_GET['post'];
					$update_post = array(
						'post_type' => 'mention',
						'ID' => $post_id,
						'edit_date' => false,
						'post_status' => 'moderation',
					);
					$u_post_id = wp_update_post($update_post);
					if( is_wp_error( $u_post_id ) ) {
						error_log( $u_post_id );
						//wp_send_json_success( array( 'post_id' => $u_post_id ), 200 );
					} 
				}
				// Block actor
				// if( $_GET['action'] === 'block' ) {
				// 	$post_id = $_GET['post'];
				// }
			}
			
		}		
	}

	public static function reply_comments_actions( $actions, $comment ) {
		//unset( $actions['reply'] );
		$recipients = \Activitypub\get_recipients( $comment->comment_ID );
		$summary = \Activitypub\get_summary( $comment->comment_ID );

		//TODO revise for non-js reply action
		// Public Reply
		$reply_button = '<button type="button" data-comment-id="%d" data-post-id="%d" data-action="%s" class="%s button-link" aria-expanded="false" aria-label="%s" data-recipients="%s" data-summary="%s">%s</button>';
		$actions['reply'] = sprintf(
            $reply_button,
            $comment->comment_ID,
            $comment->comment_post_ID,
            'replyto',
            'vim-r comment-inline',
			esc_attr__( 'Reply to this comment' ),
			$recipients,
			$summary,
            __( 'Reply', 'activitypub' )
		);
		
		// Private
		// $actions['private_reply'] = sprintf(
        //     $format,
        //     $comment->comment_ID,
        //     $comment->comment_post_ID,
        //     'private_replyto',
        //     'vim-r comment-inline',
		// 	esc_attr__( 'Reply in private to this comment' ),
		// 	$recipients,
		// 	$summary,
        //     __( 'Private reply', 'activitypub' )
		// );

		return $actions;
	}

	public static function scripts_reply_comments( $hook ) {
		if ('edit-comments.php' !== $hook) {
			return;
		}
		wp_enqueue_script( 'activitypub_client', 
			plugin_dir_url(__FILE__) . '/activitypub-client.js', 
			array('jquery'), 
			filemtime( plugin_dir_path(__FILE__) . '/activitypub-client.js' ), 
			true 
		);
	}
	
	/**
	 * Adds ActivityPub Metabox to supported post types
	 */
	public static function add_audience_metabox() {
		$ap_post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page', 'mention' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array();
		$ap_post_types[] = 'mention';
		//$ap_post_types[] = 'comment';//TODO
        foreach ($ap_post_types as $post_types) {
            add_meta_box(
                'activitypub_post_audience',// Unique ID
                __( 'Audience', 'activitypub' ), // Box title
                [self::class, 'post_audience_html'],// Content callback, must be of type callable
                $post_types,// Post types, Comments
				'side',
				'high',
			);
        }
	}

	/**
	 * Save Audience and Mentions Meta Fields
	 * save_post_activitypub
	 */
    public static function save_post_audience( $post_id ) {
		//wp_verify_nonce('ap_audience_meta');
        //     update_post_meta(
        //         $post_id,
        //     );
		// }
		// }
	}

	/**
	 * Audience fields 
	 * 
	 */
    public static function post_audience_html($post)
    {
		wp_nonce_field( 'ap_audience_meta', 'ap_audience_meta_nonce' );
		$audience = $mentions = null;
		if ( isset ( $post->ID ) ) {
			$audience = get_post_meta($post->ID, '_audience', true);
			$mentions = get_post_meta($post->ID, '_mentions', true);
//			$replyto = get_post_meta( $post->ID, '_inreplyto', true);
			if ( isset( $post->post_parent ) ){
				$replyto = $post_parent = $post->post_parent;
			}
		}
		if (array_key_exists('_audience', $_REQUEST)) {
			$audience = $_REQUEST['_audience'];
		}
		if (array_key_exists('_mentions', $_REQUEST)) {
            $mentions = $_REQUEST['_mentions'];
		}
		// 	$replyto = get_post_meta( $post_parent, '_source_url', true);
		// }
		if (array_key_exists('post_parent', $_REQUEST)) {
			$post_parent = $replyto = $_REQUEST['post_parent'];
        }
		
		?>
		<style>
			label {
				display: inline-block;
    			margin-bottom: 4px;
			}
			label + select,
			label + textarea,
			label + input[type=text] {
				display: block;
				width: 100%;
			}
			div[class*=wrap]{
				padding-bottom: 20px;
			}
		</style>
		<?php
		//TODO: if Private message selected above, mention required
		$user_webfinger = \Activitypub\url_to_webfinger( \get_author_posts_url( \get_current_user_id() ))
		//add webfinger lookup function ajax/rest
		?>
		<div class="input-text-wrap" id="mentions-wrap">
			<label for="_mentions">
				<?php _e( 'Mention user by webfinger', 'activitypub' ); ?>
			</label>
			<input type="text" id="mentions" name="_mentions" placeholder="<?php echo '@gilgamesh@example.social'; //$user_webfinger; ?>" value="<?php echo esc_attr( $mentions ); ?>" />
		</div>
		<!-- <div class="input-text-wrap" id="replyto-wrap">
			<label for="_inreplyto">
				<?php //_e( 'In reply to', 'activitypub' ); ?>
			</label>
			<input type="text" id="inreplyto" name="_inreplyto" placeholder="<?php //echo 'https://example.com/@alice/hello-world'; ?>" value="<?php //echo esc_attr( $replyto ); ?>" />
		</div> -->
		<div class="select-wrap" id="aundience-wrap">
			<label for="_audience"><?php _e('Post audience', 'activitypub' ); ?></label>
			<select name="_audience" id="audience" class="postbox">
				<option value="pubilc" <?php selected($audience, 'public'); ?>><?php _e('Public', 'activitypub' ); ?></option>
				<option value="unlisted" <?php selected($audience, 'unlisted'); ?>><?php _e('Unlisted', 'activitypub' ); ?></option>
				<option value="followers_only" <?php selected($audience, 'followers_only'); ?>><?php _e('Followers only', 'activitypub' ); ?></option>
				<option value="private" <?php selected($audience, 'private'); ?>><?php _e('Private', 'activitypub' ); ?></option>
			</select>
		</div>
		<input type="hidden" name="post_parent" value="<?php echo $post_parent; ?>" />
        <?php 
	}
	
	/**
	 * Add Title and Content Fields
	 */
	public static function ap_dashboard_body($post) {
		// TODO: Check QuickPress need post id
		$post    = get_default_post_to_edit( 'post', true );
		// $user_id = get_current_user_id();
		// // Don't create an option if this is a super admin who does not belong to this site.
		// if ( in_array( get_current_blog_id(), array_keys( get_blogs_of_user( $user_id ) ), true ) ) {
		// 	update_user_option( $user_id, 'dashboard_quick_press_last_post_id', (int) $post->ID ); // Save post_ID.
		// }
		?>
		<div class="input-text-wrap" id="title-wrap">
			<label for="title">
				<?php
				/** This filter is documented in wp-admin/edit-form-advanced.php */
				echo apply_filters( 'enter_title_here', __( 'Content Warning' ), $post );
				?>
			</label>
			<input type="text" name="post_title" id="title" autocomplete="off" />
		</div>

		<div class="textarea-wrap" id="content-wrap">
			<label for="content"><?php _e( 'Content' ); ?></label>
			<textarea name="content" id="content" placeholder="<?php esc_attr_e( 'What&#8217;s on your mind?' ); ?>" class="mceEditor" rows="3" cols="15" autocomplete="off"></textarea>
		</div>
		<?php
	} 
	
}
/*

https://developer.wordpress.org/reference/functions/wp_comment_reply/

https://wordpress.stackexchange.com/questions/97553/adding-another-state-spam-reject-approve-to-wordpress-comments/97689#97689

https://shibashake.com/wordpress-theme/expand-the-wordpress-comments-quick-edit-menu

https://codepixelz.com/wordpress-101/wordpress-ajax-form/

https://www.smashingmagazine.com/2012/05/adding-custom-fields-in-wordpress-comment-form/


https://artisansweb.net/how-to-customize-comment-form-in-wordpress/
// Add phone number field

    function add_review_phone_field_on_comment_form() {
        echo '<p class="comment-form-phone uk-margin-top"><label for="phone">' . __( 'Phone', 'text-domain' ) . '</label><span class="required">*</span><input class="uk-input uk-width-large uk-display-block" type="text" name="phone" id="phone"/></p>';
    }
    add_action( 'comment_form_logged_in_after', 'add_review_phone_field_on_comment_form' );
    add_action( 'comment_form_after_fields', 'add_review_phone_field_on_comment_form' );


    // Save phone number
    add_action( 'comment_post', 'save_comment_review_phone_field' );
    function save_comment_review_phone_field( $comment_id ){
        if( isset( $_POST['phone'] ) )
          update_comment_meta( $comment_id, 'phone', esc_attr( $_POST['phone'] ) );
    }

    function print_review_phone( $id ) {
        $val = get_comment_meta( $id, "phone", true );
        $title = $val ? '<strong class="review-phone">' . $val . '</strong>' : '';
        return $title;
	}
	*/