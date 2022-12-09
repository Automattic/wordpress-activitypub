<?php
/**
 * This is the class for integrating ActivityPub into the Friends Plugin.
 *
 * @since 0.14
 *
 * @package ActivityPub
 * @author Alex Kirk
 */
namespace Activitypub;

class Friends_Feed_Parser_ActivityPub extends \Friends\Feed_Parser {
	const SLUG = 'activitypub';
	const NAME = 'ActivityPub';
	const URL = 'https://www.w3.org/TR/activitypub/';
	private $friends_feed;

	/**
	 * Constructor.
	 *
	 * @param      \Friends\Feed  $friends_feed  The friends feed
	 */
	public function __construct( \Friends\Feed $friends_feed ) {
		$this->friends_feed = $friends_feed;

		\add_action( 'activitypub_inbox', array( $this, 'handle_received_activity' ), 10, 3 );
		\add_action( 'friends_user_feed_activated', array( $this, 'queue_follow_user' ), 10 );
		\add_action( 'friends_user_feed_deactivated', array( $this, 'queue_unfollow_user' ), 10 );
		\add_action( 'friends_feed_parser_activitypub_follow', array( $this, 'follow_user' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_unfollow', array( $this, 'unfollow_user' ), 10, 2 );
		\add_filter( 'friends_rewrite_incoming_url', array( $this, 'friends_rewrite_incoming_url' ), 10, 2 );

		\add_filter( 'the_content', array( $this, 'the_content' ), 99, 2 );
		\add_filter( 'activitypub_extract_mentions', array( $this, 'activitypub_extract_mentions' ), 10, 2 );
	}

	/**
	 * Allow logging a message via an action.
	 * @param string $message The message to log.
	 * @param array $objects Optional objects as meta data.
	 * @return void
	 */
	private function log( $message, $objects = array() ) {
		do_action( 'friends_activitypub_log', $message, $objects );
	}

	/**
	 * Determines if this is a supported feed and to what degree we feel it's supported.
	 *
	 * @param      string      $url        The url.
	 * @param      string      $mime_type  The mime type.
	 * @param      string      $title      The title.
	 * @param      string|null $content    The content, it can't be assumed that it's always available.
	 *
	 * @return     int  Return 0 if unsupported, a positive value representing the confidence for the feed, use 10 if you're reasonably confident.
	 */
	public function feed_support_confidence( $url, $mime_type, $title, $content = null ) {
		if ( preg_match( '/^@?[^@]+@((?:[a-z0-9-]+\.)+[a-z]+)$/i', $url ) ) {
			return 10;
		}

		return 0;
	}

	/**
	 * Format the feed title and autoselect the posts feed.
	 *
	 * @param      array $feed_details  The feed details.
	 *
	 * @return     array  The (potentially) modified feed details.
	 */
	public function update_feed_details( $feed_details ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $feed_details['url'] );
		if ( ! $meta || is_wp_error( $meta ) ) {
			return $meta;
		}

		if ( isset( $meta['name'] ) ) {
			$feed_details['title'] = $meta['name'];
		} elseif ( isset( $meta['preferredUsername'] ) ) {
			$feed_details['title'] = $meta['preferredUsername'];
		}

		if ( isset( $meta['id'] ) ) {
			$feed_details['url'] = $meta['id'];
		}

		return $feed_details;
	}

	/**
	 * Rewrite a Mastodon style URL @username@server to a URL via webfinger.
	 *
	 * @param      string  $url           The URL to filter.
	 * @param      string  $incoming_url  Potentially a mastodon identifier.
	 *
	 * @return     <type>  ( description_of_the_return_value )
	 */
	public function friends_rewrite_incoming_url( $url, $incoming_url ) {
		if ( preg_match( '/^@?' . ACTIVITYPUB_USERNAME_REGEXP . '$/i', $incoming_url ) ) {
			$resolved_url = \Activitypub\Rest\Webfinger::resolve( $incoming_url );
			if ( ! is_wp_error( $resolved_url ) ) {
				return $resolved_url;
			}
		}
		return $url;
	}

	/**
	 * Discover the feeds available at the URL specified.
	 *
	 * @param      string $content  The content for the URL is already provided here.
	 * @param      string $url      The url to search.
	 *
	 * @return     array  A list of supported feeds at the URL.
	 */
	public function discover_available_feeds( $content, $url ) {
		$discovered_feeds = array();

		$meta = \Activitypub\get_remote_metadata_by_actor( $url );
		if ( $meta && ! is_wp_error( $meta ) ) {
			$discovered_feeds[ $meta['id'] ] = array(
				'type'        => 'application/activity+json',
				'rel'         => 'self',
				'post-format' => 'status',
				'parser'      => self::SLUG,
				'autoselect'  => true,
			);
		}

		return $discovered_feeds;
	}

	/**
	 * Fetches a feed and returns the processed items.
	 *
	 * @param      string $url        The url.
	 *
	 * @return     array            An array of feed items.
	 */
	public function fetch_feed( $url ) {
		// There is no feed to fetch, we'll receive items via ActivityPub.
		return array();
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 * @param string $type  The type of the activity.
	 */
	public function handle_received_activity( $object, $user_id, $type ) {
		if ( ! in_array(
			$type,
			array(
				// We don't need to handle 'Accept' types since it's handled by the ActivityPub plugin itself.
				'create',
				'announce',
			),
			true
		) ) {
			return false;
		}
		$actor_url = $object['actor'];
		$user_feed = false;
		if ( \wp_http_validate_url( $actor_url ) ) {
			// Let's check if we follow this actor. If not it might be a different URL representation.
			$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
		}

		if ( is_wp_error( $user_feed ) || ! \wp_http_validate_url( $actor_url ) ) {
			$meta = \Activitypub\get_remote_metadata_by_actor( $actor_url );
			if ( ! $meta || ! isset( $meta['url'] ) ) {
				$this->log( 'Received invalid meta for ' . $actor_url );
				return false;
			}

			$actor_url = $meta['url'];
			if ( ! \wp_http_validate_url( $actor_url ) ) {
				$this->log( 'Received invalid meta url for ' . $actor_url );
				return false;
			}
		}

		$user_feed = $this->friends_feed->get_user_feed_by_url( $actor_url );
		if ( ! $user_feed || is_wp_error( $user_feed ) ) {
			$this->log( 'We\'re not following ' . $actor_url );
			// We're not following this user.
			return false;
		}

		switch ( $type ) {
			case 'create':
				return $this->handle_incoming_post( $object['object'], $user_feed );
			case 'announce':
				return $this->handle_incoming_announce( $object['object'], $user_feed, $user_id );

		}

		return true;
	}

	/**
	 * Map the Activity type to a post fomat.
	 *
	 * @param      string  $type   The type.
	 *
	 * @return     string  The determined post format.
	 */
	private function map_type_to_post_format( $type ) {
		return 'status';
	}

	/**
	 * We received a post for a feed, handle it.
	 *
	 * @param      array               $object     The object from ActivityPub.
	 * @param      \Friends\User_Feed  $user_feed  The user feed.
	 */
	private function handle_incoming_post( $object, \Friends\User_Feed $user_feed ) {
		$permalink = $object['id'];
		if ( isset( $object['url'] ) ) {
			$permalink = $object['url'];
		}

		$data = array(
			'permalink' => $permalink,
			'content' => $object['content'],
			'post_format' => $this->map_type_to_post_format( $object['type'] ),
			'date' => $object['published'],
		);

		if ( isset( $object['attributedTo'] ) ) {
			$meta = \Activitypub\get_remote_metadata_by_actor( $object['attributedTo'] );
			$this->log( 'Attributed to ' . $object['attributedTo'], compact( 'meta' ) );
			if ( isset( $meta['name'] ) ) {
				$data['author'] = $meta['name'];
			} elseif ( isset( $meta['preferredUsername'] ) ) {
				$data['author'] = $meta['preferredUsername'];
			}
		}

		if ( ! empty( $object['attachment'] ) ) {
			foreach ( $object['attachment'] as $attachment ) {
				if ( ! isset( $attachment['type'] ) || ! isset( $attachment['mediaType'] ) ) {
					continue;
				}
				if ( 'Document' !== $attachment['type'] || strpos( $attachment['mediaType'], 'image/' ) !== 0 ) {
					continue;
				}

				$data['content'] .= PHP_EOL;
				$data['content'] .= '<!-- wp:image -->';
				$data['content'] .= '<p><img src="' . esc_url( $attachment['url'] ) . '" width="' . esc_attr( $attachment['width'] ) . '"  height="' . esc_attr( $attachment['height'] ) . '" class="size-full" /></p>';
				$data['content'] .= '<!-- /wp:image  -->';
			}
			$meta = \Activitypub\get_remote_metadata_by_actor( $object['attributedTo'] );
			$this->log( 'Attributed to ' . $object['attributedTo'], compact( 'meta' ) );
			if ( isset( $meta['name'] ) ) {
				$data['author'] = $meta['name'];
			} elseif ( isset( $meta['preferredUsername'] ) ) {
				$data['author'] = $meta['preferredUsername'];
			}
		}

		$this->log(
			'Received feed item',
			array(
				'url' => $permalink,
				'data' => $data,
			)
		);
		$item = new \Friends\Feed_Item( $data );

		$this->friends_feed->process_incoming_feed_items( array( $item ), $user_feed );

		return true;
	}

	/**
	 * We received an announced URL (boost) for a feed, handle it.
	 *
	 * @param      array               $url     The announced URL.
	 * @param      \Friends\User_Feed  $user_feed  The user feed.
	 */
	private function handle_incoming_announce( $url, \Friends\User_Feed $user_feed, $user_id ) {
		if ( ! \wp_http_validate_url( $url ) ) {
			$this->log( 'Received invalid announce', compact( 'url' ) );
			return false;
		}
		$this->log( 'Received announce for ' . $url );

		$response = \Activitypub\safe_remote_get( $url, $user_id );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}
		$json = \wp_remote_retrieve_body( $response );
		$object = \json_decode( $json, true );
		if ( ! $object ) {
			$this->log( 'Received invalid json', compact( 'json' ) );
			return false;
		}
		$this->log( 'Received response', compact( 'url', 'object' ) );

		return $this->handle_incoming_post( $object, $user_feed );
	}

	/**
	 * Prepare to follow the user via a scheduled event.
	 *
	 * @param      \Friends\User_Feed  $user_feed  The user feed.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_follow_user( \Friends\User_Feed $user_feed ) {
		if ( self::SLUG !== $user_feed->get_parser() ) {
			return;
		}

		$args = array( $user_feed->get_url(), get_current_user_id() );

		$unfollow_timestamp = wp_next_scheduled( 'friends_feed_parser_activitypub_unfollow', $args );
		if ( $unfollow_timestamp ) {
			// If we just unfollowed, we don't want the event to potentially be executed after our follow event.
			wp_unschedule_event( $unfollow_timestamp, $args );
		}

		if ( wp_next_scheduled( 'friends_feed_parser_activitypub_follow', $args ) ) {
			return;
		}

		return \wp_schedule_single_event( \time(), 'friends_feed_parser_activitypub_follow', $args );
	}

	/**
	 * Follow a user via ActivityPub at a URL.
	 *
	 * @param      string  $url    The url.
	 * @param      int  $user_id   The current user id.
	 */
	public function follow_user( $url, $user_id ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $url );
		$to = $meta['id'];
		$inbox = \Activitypub\get_inbox_by_actor( $to );
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Model\Activity( 'Follow', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object( $to );
		$activity->set_id( $actor . '#follow-' . \preg_replace( '~^https?://~', '', $to ) );
		$activity = $activity->to_json();
		\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
	}

	/**
	 * Prepare to unfollow the user via a scheduled event.
	 *
	 * @param      \Friends\User_Feed  $user_feed  The user feed.
	 *
	 * @return     bool|WP_Error              Whether the event was queued.
	 */
	public function queue_unfollow_user( \Friends\User_Feed $user_feed ) {
		if ( self::SLUG !== $user_feed->get_parser() ) {
			return false;
		}

		$args = array( $user_feed->get_url(), get_current_user_id() );

		$follow_timestamp = wp_next_scheduled( 'friends_feed_parser_activitypub_follow', $args );
		if ( $follow_timestamp ) {
			// If we just followed, we don't want the event to potentially be executed after our unfollow event.
			wp_unschedule_event( $follow_timestamp, $args );
		}

		if ( wp_next_scheduled( 'friends_feed_parser_activitypub_unfollow', $args ) ) {
			return true;
		}

		return \wp_schedule_single_event( \time(), 'friends_feed_parser_activitypub_unfollow', $args );
	}

	/**
	 * Unfllow a user via ActivityPub at a URL.
	 *
	 * @param      string  $url    The url.
	 * @param      int  $user_id   The current user id.
	 */
	public function unfollow_user( $url, $user_id ) {
		$meta = \Activitypub\get_remote_metadata_by_actor( $url );
		$to = $meta['id'];
		$inbox = \Activitypub\get_inbox_by_actor( $to );
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Model\Activity( 'Undo', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( $actor );
		$activity->set_object(
			array(
				'type' => 'Follow',
				'actor' => $actor,
				'object' => $to,
				'id' => $to,
			)
		);
		$activity->set_id( $actor . '#unfollow-' . \preg_replace( '~^https?://~', '', $to ) );
		$activity = $activity->to_json();
		\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
	}

	public function get_possible_mentions() {
		static $users = null;
		if ( is_null( $users ) || true ) {
			$feeds = \Friends\User_Feed::get_by_parser( 'activitypub' );
			$users = array();
			foreach ( $feeds as $feed ) {
				$user = $feed->get_friend_user();
				$slug = sanitize_title( $user->user_nicename );
				$users[ '@' . $slug ] = $feed->get_url();
			}
		}
		return $users;
	}

	/**
	 * Extract the mentions from the post_content.
	 *
	 * @param array $mentions The already found mentions.
	 * @param string $post_content The post content.
	 * @return mixed The discovered mentions.
	 */
	public function activitypub_extract_mentions( $mentions, $post_content ) {
		$users = $this->get_possible_mentions();
		preg_match_all( '/@(?:[a-zA-Z0-9_-]+)/', $post_content, $matches );
		foreach ( $matches[0] as $match ) {
			if ( isset( $users[ $match ] ) ) {
				$mentions[ $match ] = $users[ $match ];
			}
		}
		return $mentions;
	}


	public function the_content( $the_content ) {
		$the_content = \preg_replace_callback( '/@(?:[a-zA-Z0-9_-]+)/', array( $this, 'replace_with_links' ), $the_content );

		return $the_content;
	}

	public function replace_with_links( $result ) {
		$users = $this->get_possible_mentions();
		if ( isset( $users[ $result[0] ] ) ) {
			return \Activitypub\Mention::replace_with_links( array( $users[ $result[0] ] ) );
		}

		return $result[0];
	}
}
