<?php
namespace Activitypub\Model;

/**
 * ActivityPub Comment Class
 *
 * @author Django Doucet
 */
class Comment {
	private $comment;
	private $updated;
	private $deleted;

	/**
	 * Initialize the class
	 */
	public function __construct( $comment = null ) {
		$this->comment = $comment;
		$this->id = $this->generate_comment_id();
		$this->comment_author_url = \get_author_posts_url( $this->comment->user_id );
		$this->safe_comment_id   = $this->generate_comment_id();
		$this->inReplyTo   = $this->generate_parent_url();
		$this->contentWarning   = $this->generate_content_warning();
		$this->permalink   = $this->generate_permalink();
		$this->context   = $this->generate_context();
		$this->to_recipients = $this->generate_mention_recipients();
		$this->tags        = $this->generate_tags();
		$this->update        = $this->generate_update();
		$this->deleted        = $this->generate_trash();
		$this->replies        = $this->generate_replies();
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
			'id' => $this->safe_comment_id,
			'type' => 'Note',
			'published' => \date( 'Y-m-d\TH:i:s\Z', \strtotime( $comment->comment_date_gmt ) ),
			'attributedTo' => $this->comment_author_url,
			'summary' => $this->contentWarning,
			'inReplyTo' => $this->inReplyTo,
			'content' => $comment->comment_content,
			'contentMap' => array(
				\strstr( \get_locale(), '_', true ) => $comment->comment_content,
			),
			'context' => $this->context,
			//'source' => \get_comment_link( $comment ), //non-conforming, see https://www.w3.org/TR/activitypub/#source-property
			'url' => \get_comment_link( $comment ), //link for mastodon
			'to' => $this->to_recipients,
			'cc' => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'tag' => $this->tags,
			'replies' => $this->replies,
		);
		if ( $this->replies ) {
			$array['replies'] = $this->replies;
		}
		if ( $this->update ) {
			$array['updated'] = $this->update;
		}
		if ( $this->deleted ) {
			$array['deleted'] = $this->deleted;
		}

		return \apply_filters( 'activitypub_comment', $array );
	}

	public function to_json() {
		return \wp_json_encode( $this->to_array(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_QUOT );
	}

	public function generate_comment_author_link() {
		return \get_author_posts_url( $this->comment->comment_author );
	}

	public function generate_comment_id() {
		return \Activitypub\set_ap_comment_id( $this->comment->comment_ID );
	}

	public function generate_permalink() {
		$comment = $this->comment;
		$permalink = \get_comment_link( $comment );
		// replace 'trashed' for delete activity
		return \str_replace( '__trashed', '', $permalink );
	}

	/**
	 * What is status being replied to
	 * Comment ID or Post ID
	 */
	public function generate_parent_url() {
		$comment = $this->comment;
		$parent_comment = \get_comment( $comment->comment_parent );
		if ( $comment->comment_parent ) {
			//is parent remote?
			$inReplyTo = \get_comment_meta( $comment->comment_parent, 'source_url', true );
			if ( ! $inReplyTo ) {
				$inReplyTo = add_query_arg(
					array(
						'p' => $comment->comment_post_ID,
						'ap_comment_id' => $comment->comment_parent,
					),
					trailingslashit( site_url() )
				);
			}
		} else { //parent is_post
			// Backwards compatibility
			$pretty_permalink = \get_post_meta( $comment->comment_post_ID, '_activitypub_permalink_compat', true ); // TODO finalize meta
			if ( $pretty_permalink ) {
				$inReplyTo = $pretty_permalink;
			} else {
				$inReplyTo = add_query_arg(
					array(
						'p' => $comment->comment_post_ID,
					),
					trailingslashit( site_url() )
				);
			}
		}
		return $inReplyTo;
	}

	public function generate_context() {
		$comment = $this->comment;
		// support pretty_permalinks
		$pretty_permalink = \get_post_meta( $comment->comment_post_ID, '_activitypub_permalink_compat', true );
		if ( $pretty_permalink ) {
			$context = $pretty_permalink;
		} else {
			$context = add_query_arg(
				array(
					'p' => $comment->comment_post_ID,
				),
				trailingslashit( site_url() )
			);
		}
		return $context;
	}

	/**
	 * Generate courtesy Content Warning
	 * If parent status used CW let's just copy it
	 * TODO: Move to preprocess_comment / row_actions
	 * Add option for wrapping CW in Details/Summary markup
	 * Figure out some CW syntax: [shortcode-style], {brackets-style}?
	 * So it can be inserted into reply textbox, and removed or modified at will
	 */
	public function generate_content_warning() {
		$comment = $this->comment;
		$contentWarning = null;

		// Temporarily generate Summary from parent
		$parent_comment = \get_comment( $comment->comment_parent );
		if ( $parent_comment ) {
			//get (received) comment
			$ap_object = \unserialize( \get_comment_meta( $comment->comment_parent, 'ap_object', true ) );
			if ( isset( $ap_object['object']['summary'] ) ) {
				$contentWarning = $ap_object['object']['summary'];
			}
		}
		// TODO Replace auto generate with Summary shortcode
		/*summary = \get_comment_meta( $this->comment->comment_ID, 'summary', true ) ;
		if ( !empty( $summary ) ) {
				$contentWarning = \Activitypub\add_summary( $summary );
		} */
		return $contentWarning;
	}

	/**
	 * Who is being replied to
	 */
	public function generate_mention_recipients() {
		$recipients = array( AS_PUBLIC );
		$mentions = \get_comment_meta( $this->comment->comment_ID, 'mentions', true );
		if ( ! empty( $mentions ) ) {
			foreach ( $mentions as $mention ) {
				$recipients[] = $mention['href'];
			}
		}
		return $recipients;
	}

	/**
	 * Mention user being replied to
	 */
	public function generate_tags() {
		$mentions = \get_comment_meta( $this->comment->comment_ID, 'mentions', true );
		if ( ! empty( $mentions ) ) {
			foreach ( $mentions as $mention ) {
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
	 * Generate updated datetime
	 */
	public function generate_update() {
		$comment = $this->comment;
		$updated = null;
		if ( \get_comment_meta( $comment->comment_ID, 'ap_last_modified', true ) ) {
			$updated = \wp_date( 'Y-m-d\TH:i:s\Z', \get_comment_meta( $comment->comment_ID, 'ap_last_modified', true ) );
		}
		return $updated;
	}

	/**
	 * Generate deleted datetime
	 */
	public function generate_trash() {
		$comment = $this->comment;
		$deleted = null;
		if ( 'trash' == $comment->status ) {
			$deleted = \date( 'Y-m-d\TH:i:s\Z', \strtotime( $comment->comment_date_gmt ) );
		}
		return $deleted;
	}

	/**
	 * Generate replies collections
	 */
	public function generate_replies() {
		$comment = $this->comment;
		$replies = [];
		$args = array(
			'post_id'       => $comment->comment_post_ID,
			'parent'        => $comment->comment_ID,
			'status'        => 'approve',
			'hierarchical'  => false,
		);
		$comments_list = \get_comments( $args );

		if ( $comments_list ) {
			$items = array();
			foreach ( $comments_list as $comment ) {
				// remote replies
				$source_url = \get_comment_meta( $comment->comment_ID, 'source_url', true );
				if ( ! empty( $source_url ) ){
					$items[] = $source_url;
				} else {
					// local replies
					$comment_url = \add_query_arg( //
						array(
							'p' => $comment->comment_post_ID,
							'ap_comment_id' => $comment->comment_ID,
						),
						trailingslashit( site_url() )
					);
					$items[] = $comment_url;
				}
			}
			
			$replies = (object) array(
				'type'  => 'Collection',
				'id'    => \add_query_arg( array( 'replies' => '' ), $this->id ),
				'first' => (object) array(
					'type'  => 'CollectionPage',
					'partOf' => \add_query_arg( array( 'replies' => '' ), $this->id ),
					'items' => $items,
				),
			);
		}
		return $replies;
	}
}
