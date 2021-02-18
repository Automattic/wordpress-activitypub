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
	 * Initialize the class
	 */
	public function __construct( $comment = null ) {
		$this->comment = $comment;

		$this->comment_author_url = \get_author_posts_url( $this->comment->user_id );
		$this->safe_comment_id   = $this->generate_comment_id();
		$this->inReplyTo   = $this->generate_parent_url();
		$this->contentWarning   = $this->generate_content_warning();
		$this->permalink   = $this->generate_permalink();
		$this->cc_recipients = $this->generate_recipients();
		$this->tags        = $this->generate_tags();
	}

	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}
	}

	public function to_array() {
		$comment = $this->comment;

		$array = array(
			'id' => \Activitypub\Model\Comment::normalize_comment_id( $comment ),
			'type' => 'Note',
			'published' => \date( 'Y-m-d\TH:i:s\Z', \strtotime( $comment->comment_date_gmt ) ),
			'attributedTo' => $this->comment_author_url,
			'summary' => $this->contentWarning,
			'inReplyTo' => $this->inReplyTo,
			'content' => $comment->comment_content,
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $comment->comment_content,
			),
			'source' => \get_comment_link( $comment ),
			'url' => \get_comment_link( $comment ),//link for mastodon
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),//audience logic
			'cc' => $this->cc_recipients,
			'tag' => $this->tags,
		);

		return \apply_filters( 'activitypub_comment', $array );
	}

	public function to_json() {
		return \wp_json_encode( $this->to_array(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
	}

	public function generate_comment_author_link() {
		return \get_author_posts_url( $this->comment->comment_author );
	}

	public function generate_permalink() {
		$comment = $this->comment;
		$permalink = \get_comment_link( $comment );

		// replace 'trashed' for delete activity
		return \str_replace( '__trashed', '', $permalink );
	}

	/**
	 * What is status is being replied to
	 * Comment ID or Post ID
	 */
	public function generate_parent_url() {
		$comment = $this->comment;
		$parent_comment = \get_comment( $comment->comment_parent );
		if ( $parent_comment ) {
			//reply to local (received) comment
			$inReplyTo = \get_comment_meta( $comment->comment_parent, 'source_url', true );
		} else {
			//reply to local post
			$inReplyTo = \get_permalink( $comment->comment_post_ID );
		}
		return $inReplyTo;
	}

	/**
	 * Generate Content Warning from peer
	 * If peer used CW let's just copy it
	 * TODO: Move to preprocess_comment / row_actions
	 * Add option for wrapping CW in Details/Summary markup
	 * Figure out some CW syntax: [shortcode-style], {brackets-style}? 
	 * So it can be inserted into reply textbox, and removed or modified at will
	 */
	public function generate_content_warning() {
		$comment = $this->comment;
		$contentWarning = null;
		$parent_comment = \get_comment( $comment->comment_parent );
		if ( $parent_comment ) {
			//get (received) comment
			$ap_object = \unserialize( \get_comment_meta( $comment->comment_parent, 'ap_object', true ) );
			if ( isset( $ap_object['object']['summary'] ) ) {
				$contentWarning = $ap_object['object']['summary'];
			}
		}
		/*$summary = \get_comment_meta( $this->comment->comment_ID, 'summary', true ) ;
		if ( !empty( $summary ) ) {
				$contentWarning = \Activitypub\add_summary( $summary );
		} */
		return $contentWarning;
	}

	/**
	 * Who is being replied to
	 */
	public function generate_recipients() {
		//TODO Add audience logic get parent audience
		$recipients = array( AS_PUBLIC );
		$mentions = \get_comment_meta( $this->comment->comment_ID, 'mentions', true ) ;
		if ( !empty( $mentions ) ) {
			foreach ($mentions as $mention) {
				$recipients[] = $mention['href'];
			}
		} 
		return $recipients;
	}

	/**
	 * Mention user being replied to
	 */
	public function generate_tags() {
		$mentions = \get_comment_meta( $this->comment->comment_ID, 'mentions', true ) ;
		if ( !empty( $mentions ) ) {
			foreach ($mentions as $mention) {
				$mention_tags[] = array(
					'type' => 'Mention',
					'href' => $mention['href'],
					'name' => '@' . $mention['name'],
				);
			}
			return $mention_tags;
		}
	}

	/**
	 * Transform comment url, replace #fragment with ?query
	 * 
	 * AP Object ID must be unique
	 * 
	 * https://www.w3.org/TR/activitypub/#obj-id
	 * https://github.com/tootsuite/mastodon/issues/13879
	 */
	public function normalize_comment_id( $comment ) {
		$comment_id = explode( '#comment-', \get_comment_link( $comment ) );
		$comment_id = $comment_id[0] . '?comment-' . $comment_id[1];
		return $comment_id;
	}
}