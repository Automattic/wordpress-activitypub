<?php
namespace Activitypub\Peer;

/**
 * ActivityPub Mentions DB-Class
 *
 * @author Django Doucet
 */
class Mentions {

	public static function init() {
		//	\add_filter( 'wp_comment_reply', array( '\ActivityPub\Peer\Mentions', 'test_reply_comment_func'), 900, 2);
		// \add_action( 'rest_api_init', array( '\Activitypub\Rest\Inbox', 'register_routes' ) );
		// \add_filter( 'rest_pre_serve_request', array( '\Activitypub\Rest\Inbox', 'serve_request' ), 11, 4 );
		// \add_action( 'activitypub_inbox_follow', array( '\Activitypub\Rest\Inbox', 'handle_follow' ), 10, 2 );
		// \add_action( 'activitypub_inbox_unfollow', array( '\Activitypub\Rest\Inbox', 'handle_unfollow' ), 10, 2 );
	}

	public static function get_mentions( $debug = '') {
		$args = array(
			'post_type'  => 'activitypub_mentions',//TODO: rename to activitypub
			'post_status'  => array( 'followers_only', 'private', 'private_message', 'unlisted', 'public' ),
			'numberposts' => -1
		);
		$mentions = get_posts( $args );

		// if ( ! $mentions ) {
		// 	return array();
		// }

		$current_user_id = get_current_user_id();
		$personal_mentions = array();
		if ( $debug ) {
			echo "<details><summary>get_mentions()</summary><pre>"; print_r($mentions);	echo "</pre></details>";
		}

		foreach ( $mentions as $mention ) :
		    //$local_user = get_post_meta( $mention->ID, 'local_user', true );
		    $local_user = $mention->post_author;
		    $target_user = get_post_meta( $mention->ID, 'target_user', true );
				if ( $current_user_id == $local_user ) {
					$personal_mentions[] = $mention;
				}
		endforeach;
		return $personal_mentions;
	}

	public static function get_views( ) {
		$views = array();
		$current = ( !empty($_REQUEST['mention_type']) ? $_REQUEST['mention_type'] : 'all');

		$class = ($current == 'all' ? ' class="current"' :'');
		$all_url = remove_query_arg('mention_type');
		$views['all'] = "<a href='{$all_url }' {$class} >All</a>";

		$private_message_url = add_query_arg('mention_type','private_message');
		$class = ($current == 'private_message' ? ' class="current"' :'');
		$views['private_message'] = "<a href='{$private_message_url}' {$class} >Private messages</a>";

		$followers_only_url = add_query_arg('mention_type','followers_only');
		$class = ($current == 'followers_only' ? ' class="current"' :'');
		$views['followers_only'] = "<a href='{$followers_only_url}' {$class} >Followers only</a>";

		$unlisted_url = add_query_arg('mention_type','Unlisted');
		$class = ($current == 'activitypub_ul' ? ' class="current"' :'');
		$views['activitypub_ul'] = "<a href='{$unlisted_url}' {$class} >Unlisted</a>";

		$public_url = add_query_arg('mention_type','Public');
		$class = ($current == 'activitypub_public' ? ' class="current"' :'');
		$views['activitypub_public'] = "<a href='{$public_url}' {$class} >Public</a>";

   return $views;
	}

	public static function count_mentions( ) {
		$debug = 'debug';
		$mentions = self::get_mentions( $debug );
		return \count( $mentions );
	}

	public static function test_reply_comment_func($str, $input) {
		error_log('test_reply_comment_func');
	    extract($input);
	    $table_row = TRUE;
	    if ($mode == 'single') {
	        $wp_list_table = _get_list_table('WP_Post_Comments_List_Table');
	    } else {
	        $wp_list_table = _get_list_table('WP_Comments_List_Table');
	    }
	    // Get editor string
	    ob_start();
	    $quicktags_settings = array('buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,spell,close');
	    wp_editor('', 'replycontent', array('media_buttons' => false, 'tinymce' => false, 'quicktags' => $quicktags_settings, 'tabindex' => 104));
	    $editorStr = ob_get_contents();
	    ob_end_clean();
	    // Get nonce string
	    ob_start();
	    wp_nonce_field("replyto-comment", "_ajax_nonce-replyto-comment", false);
	    if (current_user_can("unfiltered_html"))
	        wp_nonce_field("unfiltered-html-comment", "_wp_unfiltered_html_comment", false);
	    $nonceStr = ob_get_contents();
	    ob_end_clean();
	    $content = '<form method="get" action="">';
	    if ($table_row) :
	        $content .= '<table style="display:none;"><tbody id="com-reply"><tr id="replyrow" style="display:none;"><td colspan="' . $wp_list_table->get_column_count() . '" class="colspanchange">';
	    else :
	        $content .= '<div id="com-reply" style="display:none;"><div id="replyrow" style="display:none;">';
	    endif;
	    $content .= '<div id="replyhead" style="display:none;"><h5>Reply to Comment</h5>'
	            . '</div>';
	    $content .= '<div id="addhead" style="display:none;"><h5>Add new Comment</h5></div>'
	            . '<p style="margin:10px 0;"> Svar til siden:
	            <select name="backend_meta">
	            <option value="val1">1</option>
	            <option value="val2">2</option>
	            <option value="val3">3</option>
	            </select></p>'
	            . '<div id="edithead" style="display:none;">';
	    $content .= '
	                <div class="inside">
	                <label for="author">Name</label>
	                <input type="text" name="newcomment_author" size="50" value="" tabindex="101" id="author" />
	                </div>
	                <div class="inside">
	                <label for="author-email">E-mail</label>
	                <input type="text" name="newcomment_author_email" size="50" value="" tabindex="102" id="author-email" />
	                </div>
	                <div class="inside">
	                <label for="author-url">URL</label>
	                <input type="text" id="author-url" name="newcomment_author_url" size="103" value="" tabindex="103" />
	                </div>
	                <div style="clear:both;"></div>';
	    $content .= '</div>';
	    // Add editor
	    $content .= "<div id='replycontainer'>n";
	    $content .= $editorStr;
	    $content .= "</div>n";
	    $content .= '
	            <p id="replysubmit" class="submit">
	            <a href="#comments-form" class="cancel button-secondary alignleft" tabindex="106">Cancel</a>
	            <a href="#comments-form" class="save button-primary alignright" tabindex="104">
	            <span id="addbtn" style="display:none;">Add Comment</span>
	            <span id="savebtn" style="display:none;">Update Comment</span>
	            <span id="replybtn" style="display:none;">Submit Reply</span></a>
	            <img class="waiting" style="display:none;" src="' . esc_url(admin_url("images/wpspin_light.gif")) . '" alt="" />
	            <span class="error" style="display:none;"></span>
	            <br class="clear" />
	            </p>';
	    $content .= '
	            <input type="hidden" name="user_ID" id="user_ID" value="' . get_current_user_id() . '" />
	            <input type="hidden" name="action" id="action" value="" />
	            <input type="hidden" name="comment_ID" id="comment_ID" value="" />
	            <input type="hidden" name="comment_post_ID" id="comment_post_ID" value="" />
	            <input type="hidden" name="status" id="status" value="" />
	            <input type="hidden" name="position" id="position" value="' . $position . '" />
	            <input type="hidden" name="checkbox" id="checkbox" value="';
	    if ($checkbox)
	        $content .= '1';
	    else
	        $content .= '0';
	    $content .= '" />n';
	    $content .= '<input type="hidden" name="mode" id="mode" value="' . esc_attr($mode) . '" />';
	    $content .= $nonceStr;
	    $content .="n";
	    if ($table_row) :
	        $content .= '</td></tr></tbody></table>';
	    else :
	        $content .= '</div></div>';
	    endif;
	    $content .= "n</form>n";
			apply_filters( 'wp_comment_reply', $content, $args );
	}


}
