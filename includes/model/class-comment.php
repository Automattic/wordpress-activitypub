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
		$this->comment = $comment;

		$this->comment_author_url = \get_author_posts_url( $this->comment->user_id );
		$this->safe_comment_id   = $this->generate_comment_id();
		$this->inReplyTo   = $this->generate_parent_url();
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
			'published' => \date( 'Y-m-d\TH:i:s\Z', \strtotime( $comment->comment_date ) ),
			'attributedTo' => $this->comment_author_url,
			'summary' => '',//$this->get_the_title(),
			'inReplyTo' => $this->inReplyTo,
			'content' => $comment->comment_content,
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $comment->comment_content,
			),
			'source' => \get_comment_link( $comment ),
			'url' => \get_comment_link( $comment ),//link for mastodon
			'to' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
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
	 * Who is being replied to
	 */
	public function generate_recipients() {
		$cc_recipients = null;
		$parent_comment = \get_comment( $this->comment->comment_parent );
		if ( $parent_comment ) {
			//reply to local (received) comment
			$self = \get_author_posts_url( $this->comment->comment_author );
			$recipient = $parent_comment->comment_author_url;
			//Add peer to cc and tag them
			$cc_recipients = \Activitypub\add_recipients( $recipient, $self );
		}
		return $cc_recipients;
	}

	/**
	 * Mention user being replied to
	 */
	public function generate_tags() {
		$parent_comment = \get_comment( $this->comment->comment_parent );
		if ( $parent_comment ) {
			//reply to a comment
			$recipient = $parent_comment->comment_author_url;
			$mention_tag = array(
				'type' => 'Mention',
				'href' => $recipient,
				'name' => \Activitypub\url_to_webfinger( $recipient ),
			);
			return $mention_tag;
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
		$comment_id = $comment_id[0] . '?' . $comment_id[1];
		return $comment_id;
	}
}