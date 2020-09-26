<?php
namespace Activitypub\Model;

/**
 * ActivityPub Comment Class
 *
 * @author Django Doucet
 */
class Comment {
	private $comment;

	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public function __construct( $comment = null ) {
		$this->comment = \get_comment( $comment );
	}

	public function get_comment() {
		return $this->comment;
	}

	public function get_comment_author() {
		return $this->comment->comment_author;
	}

	public function to_array() {
		$comment = $this->comment;
		error_log( 'to_array()' );
	//	error_log( print_r($comment, true) );
		$ap_object = \get_comment_meta( $comment->comment_ID, 'ap_object', true );
		// if($ap_object){
		// 	\error_log('is_ap_object:');
		// 	\error_log( print_r( $ap_object, true ) );
		// }
		$parent_ap_object = \get_comment_meta( $comment->comment_parent, 'ap_object', true );
		if($parent_ap_object){
			// \error_log('parent_ap_object:');
			// \error_log( print_r( \unserialize($parent_ap_object), true ) );
            $parent_ap_object = \unserialize($parent_ap_object);

		}
		$cc_recipients = $mentions = null;
		$self = $comment->comment_author_url;
		$parent_comment = \get_comment( $comment->comment_parent );
		if ( $parent_comment ) {
			$recipient = $parent_comment->comment_author_url;
			$cc_recipients = \Activitypub\add_recipients( $recipient, $self );
			$mentions = \Activitypub\tag_user( $recipient );
			$inReplyTo = \get_comment_meta( $comment->comment_parent, 'source_url', true );
		} else {
			$inReplyTo = $comment->comment_parent;
		}
		
		//ID must be unique https://www.w3.org/TR/activitypub/#obj-id
		$comment_id = \Activitypub\normalize_comment_url( $comment );
		
		// error_log( '$cc_recipients: ' . print_r( $cc_recipients, true ) );
		// error_log( '$mentions: ' . print_r( $mentions, true ) );
		//comment_id $source_url = get_comment_meta( $comment->comment_ID, 'source_url', true );
		
	//error_log('$inReplyTo: ' . $inReplyTo );
	//error_log( print_r($inReplyTo, true) );
		if( empty( $ap_object ) ) {
			$array = array(
				'id' => $comment_id, //\get_comment_link( $comment ),
				'type' => 'Note',
				'published' => \date( 'Y-m-d\TH:i:s\Z', \strtotime( $comment->comment_date ) ),
				'attributedTo' => \esc_url_raw( $comment->comment_author_url ),
				'summary' => '',//$this->get_the_title(),
				'inReplyTo' => \esc_url_raw( $inReplyTo ),
				'content' => $comment->comment_content,
				'contentMap' => array(
					\strstr( \get_locale(), '_', true ) => $comment->comment_content,
				),
				'source' => \get_comment_link( $comment ),
				'url' => \get_comment_link( $comment ),
				'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
				//'to' => array( 'https://www.w3.org/ns/activitystreams#Public', $self ),
				'cc' => $cc_recipients,
				'tag' => $mentions,
			);
		} else {
			$array = unserialize( $ap_object );
		}

	//error_log( 'to_array' );
		return \apply_filters( 'activitypub_post', $array );
	}

	public function to_json() {
		return \wp_json_encode( $this->to_array(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
	}

}