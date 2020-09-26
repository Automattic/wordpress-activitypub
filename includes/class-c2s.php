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
		\add_action( 'wp_dashboard_setup', array( '\Activitypub\C2S', 'activitypub_dashboard_widgets') );
		\add_action( 'add_meta_boxes', array( '\Activitypub\C2S', 'add_audience_metabox') );
		\add_action( 'add_meta_boxes_comment', array( '\Activitypub\C2S', 'add_audience_metabox' ) );
		\add_action( 'save_post_activitypub', array( '\Activitypub\C2S', 'save_post_audience' ) );
	//	\add_action( 'comment_post', array( '\Activitypub\C2S', 'save_post_audience' ) );	
		\add_filter( 'wp_insert_post_data', array( '\Activitypub\C2S', 'save_post_parent' ), 99, 2 );
		\add_action( 'comment_form_logged_in_after', array( '\Activitypub\C2S', 'post_audience_html' ) );
		\add_filter( 'comment_form_default_fields', array( '\Activitypub\C2S', 'true_phone_number_field' ) );
	}

	public static function activitypub_dashboard_widgets() {
		wp_add_dashboard_widget(
			'dashboard_compose_widget',                          // Widget slug.
			esc_html__( 'Activitypub Mention', 'activitypub' ), // Title.
			[self::class, 'dashboard_compose_widget_render'], // Display function.
		);
		wp_add_dashboard_widget(
			'dashboard_inbox_widget',                          // Widget slug.
			esc_html__( 'Activitypub Inbox', 'activitypub' ), // Title.
			[self::class, 'dashboard_inbox_widget_render'], // Display function.
		);

		// Globalize the metaboxes array, this holds all the widgets for wp-admin.
		global $wp_meta_boxes;
		$default_dashboard = $wp_meta_boxes['dashboard']['normal']['core'];
		$ap_widget_backup = array( 
			'dashboard_compose_widget' => $default_dashboard['dashboard_compose_widget'],
			'dashboard_inbox_widget' => $default_dashboard['dashboard_inbox_widget'],
		);
		unset( $default_dashboard['dashboard_compose_widget'] );
		unset( $default_dashboard['dashboard_inbox_widget'] );

		// Merge the two arrays together so our widget is at the beginning.
		$sorted_dashboard = array_merge( $ap_widget_backup, $default_dashboard );
		$wp_meta_boxes['dashboard']['normal']['core'] = $sorted_dashboard;
	}

	/**
	 * Compose Dashboard widget
	 */
	public static function dashboard_compose_widget_render( $post, $callback_args ) {
		//esc_html_e( "Hello World, this is my Compose Widget!", "activitypub" );
		global $post;
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$post    = get_default_post_to_edit( 'post', true );
		$post_ID = (int) $post->ID;
		?>
		<form action="<?php echo admin_url('/post.php');?>">
		<?php
		\Activitypub\C2S::ap_dashboard_body($post);
		\Activitypub\C2S::post_audience_html($post);
		// save post
		?>
		<p class="submit">
			<input type="hidden" name="action" id="quickpost-action" value="post-quickdraft-save" />
			<input type="hidden" name="post_ID" value="<?php echo $post->ID; ?>" />
			<input type="hidden" name="post_type" value="activitypub_mentions" />
			<?php wp_nonce_field( 'add-post' ); ?>
			<?php submit_button( __( 'Save Draft' ), 'primary', 'save', false, array( 'id' => 'save-post' ) ); ?>
			<br class="clear" />
		</p>
		</form>
		<?php
	}

	public static function dashboard_inbox_widget_render( $post, $callback_args ) {
		esc_html_e( "Hello World, this is my Inbox Widget!", "activitypub" );
		$mentions = \Activitypub\Peer\Mentions::get_mentions();

		//echo human_time_diff( '2020-05-28 10:14:50', current_datetime('Y-m-d H:i:s') );
		echo '<details><summary>DEBUG</summary><pre>'; print_r($mentions); echo '</pre></details>';
	}
	
	/**
	 * Adds ActivityPub Metabox to supported post types
	 */
	public static function add_audience_metabox() {
		$ap_post_types = \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) ? \get_option( 'activitypub_support_post_types', array( 'post', 'page' ) ) : array();
		$ap_post_types[] = 'activitypub_mentions';
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
	 */
    public static function save_post_audience($post_id) {
		//wp_verify_nonce('ap_audience_meta');
        if (array_key_exists('_ap_audience', $_POST)) {
			\error_log('array_key_exists-_ap_audience: ' . $_POST['_ap_audience']);
            update_post_meta(
                $post_id,
                '_ap_audience',
                $_POST['_ap_audience']
            );
		}
		if (array_key_exists('_ap_mentions', $_POST)) {
			\error_log('array_key_exists-_ap_mentions: ' . $_POST['_ap_mentions']);
            update_post_meta(
                $post_id,
                '_ap_mentions',
                $_POST['_ap_mentions']
            );
		}
		// if (array_key_exists('_ap_replyto', $_POST)) {
		// 	\error_log('array_key_exists-_ap_replyto: ' . $_POST['_ap_replyto']);
        //     update_post_meta(
        //         $post_id,
        //         '_ap_replyto',
        //         $_POST['_ap_replyto']
        //     );
		// }
		// if (array_key_exists('parent_id', $_POST)) {
		// 	\error_log('array_key_exists-_ap_replyto: ' . $_POST['parent_id']);
		// 	remove_action( 'save_post_activitypub', 'save_post_audience' );
 
		// 	// update the post, which calls save_post again
		// 	wp_update_post( 
		// 		array( 'ID' => $post_id, 
		// 		'post_parent' => $_POST['parent_id'] 
		// 		) 
		// 	);
	
		// 	// re-hook this function
		// 	add_action( 'save_post_activitypub', 'save_post_audience' );
        // }
	}
	
	/**
	 * Saves post as child of parent for reply graph 
	 */	
	public static function save_post_parent($data, $postarr){
		if ( isset( $postarr["post_parent"] ) ) {
			$data["post_parent"] = $postarr["post_parent"];
		}
		// if ( $postarr["post_status"] === 'publish' && $postarr["_ap_audience"] === 'private_message' ) {
		// 	$data["post_status"] = $postarr["_ap_audience"];
		// }
		// error_log( 'C2S:save_post_parent:data: ' . print_r($data,true));
		// error_log( 'C2S:save_post_parent:postarr: ' . print_r($postarr,true));
		return $data;
	}

	/**
	 * Audience fields 
	 * 
	 */
    public static function post_audience_html($post)
    {
		wp_nonce_field( 'ap_audience_meta', 'ap_audience_meta_nonce' );
		//$audience = $mentions = null;
		if ( isset ( $post->ID ) ) {
			$audience = get_post_meta($post->ID, '_ap_audience', true);
			$mentions = get_post_meta($post->ID, '_ap_mentions', true);
			//$replyto = get_post_meta($post->ID, '_ap_replyto', true);
			if ( isset( $post->post_parent ) ){
				$replyto = $post_parent = $post->post_parent;
			}
		}
		if (array_key_exists('_ap_audience', $_REQUEST)) {
			$audience = $_REQUEST['_ap_audience'];
		}
		if (array_key_exists('_ap_mentions', $_REQUEST)) {
			\error_log('array_key_exists-_ap_mentions: ' . $_REQUEST['_ap_mentions']);
            $mentions = $_REQUEST['_ap_mentions'];
		}
		// if (array_key_exists('_ap_replyto', $_REQUEST)) {
		// 	\error_log('array_key_exists-_ap_replyto: ' . $_REQUEST['_ap_replyto']);
		// 	$replyto = $_REQUEST['_ap_replyto'];
		// 	$replyto = get_post_meta( $post_parent, '_source_url', true);
		// }
		if (array_key_exists('post_parent', $_REQUEST)) {
			\error_log('array_key_exists-parent_id: ' . $_REQUEST['post_parent']);
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
			<label for="_ap_mentions">
				<?php _e( 'Mention user by uri', 'activitypub' ); ?>
			</label>
			<input type="text" id="ap_mentions" name="_ap_mentions" placeholder="<?php echo 'https://example.social/users/Gilgamesh'; //$user_webfinger; ?>" value="<?php echo esc_attr( $mentions ); ?>" />
		</div>
		<!-- <div class="input-text-wrap" id="replyto-wrap">
			<label for="_ap_replyto">
				<?php _e( 'In reply to', 'activitypub' ); ?>
			</label>
			<input type="text" id="ap_replyto" name="_ap_replyto" placeholder="<?php echo 'https://example.com/@alice/hello-world'; ?>" value="<?php echo esc_attr( $replyto ); ?>" />
		</div> -->
		<div class="select-wrap" id="aundience-wrap">
			<label for="_ap_audience"><?php _e('Post audience', 'activitypub' ); ?></label>
			<select name="_ap_audience" id="ap_audience" class="postbox">
				<option value="pubilc" <?php selected($audience, 'public'); ?>><?php _e('Public', 'activitypub' ); ?></option>
				<option value="unlisted" <?php selected($audience, 'unlisted'); ?>><?php _e('Unlisted', 'activitypub' ); ?></option>
				<option value="followers_only" <?php selected($audience, 'followers_only'); ?>><?php _e('Followers only', 'activitypub' ); ?></option>
				<option value="private_message" <?php selected($audience, 'private_message'); ?>><?php _e('Private message', 'activitypub' ); ?></option>
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