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

	public function __construct( \Friends\Feed $friends_feed ) {
		$this->friends_feed = $friends_feed;

		\add_action( 'activitypub_inbox_create', array( $this, 'handle_received_activity' ), 10, 2 );
		\add_action( 'activitypub_inbox_accept', array( $this, 'handle_received_activity' ), 10, 2 );
		\add_action( 'friends_user_feed_activated', array( $this, 'queue_follow_user' ), 10 );
		\add_action( 'friends_user_feed_deactivated', array( $this, 'queue_unfollow_user' ), 10 );
		\add_action( 'friends_feed_parser_activitypub_follow', array( $this, 'follow_user' ), 10, 2 );
		\add_action( 'friends_feed_parser_activitypub_unfollow', array( $this, 'unfollow_user' ), 10, 2 );
		\add_filter( 'friends_rewrite_incoming_url', array( $this, 'friends_rewrite_incoming_url' ), 10, 2 );
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
		if ( $meta && ! is_wp_error( $meta ) ) {
		if ( isset( $meta['preferredUsername'] ) ) {
				$feed_details['title'] = $meta['preferredUsername'];
			}
			$feed_details['url'] = $meta['id'];
		}

		return $feed_details;
	}

	public function friends_rewrite_incoming_url( $url, $incoming_url ) {
		if ( preg_match( '/^@?[^@]+@((?:[a-z0-9-]+\.)+[a-z]+)$/i', $incoming_url ) ) {
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
				'post-format' => 'autodetect',
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
	 */
	public function handle_received_activity( $object, $user_id ) {
		$user_feed = $this->friends_feed->get_user_feed_by_url( $object['actor'] );
		if ( is_wp_error( $user_feed ) ) {
			$meta = \Activitypub\get_remote_metadata_by_actor( $object['actor'] );
			$user_feed = $this->friends_feed->get_user_feed_by_url( $meta['url'] );
			if ( is_wp_error( $user_feed ) ) {
				// We're not following this user.
				return false;
			}
		}
		switch ( $object['type'] ) {
			case 'Accept':
				// nothing to do.
				break;
			case 'Create':
				$this->handle_incoming_post( $object['object'], $user_feed );

		}

		return true;
	}

	private function map_type_to_post_format( $type ) {
		return 'status';
	}

	private function handle_incoming_post( $object, \Friends\User_Feed $user_feed ) {
		$item = new \Friends\Feed_Item(
			array(
				'permalink' => $object['url'],
				// 'title' => '',
				'content' => $object['content'],
				'post_format' => $this->map_type_to_post_format( $object['type'] ),
				'date' => $object['published'],
			)
		);

		$this->friends_feed->process_incoming_feed_items( array( $item ), $user_feed );
	}

	public function queue_follow_user( \Friends\User_Feed $user_feed ) {
		if ( self::SLUG != $user_feed->get_parser() ) {
			return;
		}

		$args = array( $user_feed->get_id(), get_current_user_id() );

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

	public function follow_user( $user_feed_id, $user_id ) {
		$user_feed = \Friends\User_Feed::get_by_id( $user_feed_id );
		if ( self::SLUG != $user_feed->get_parser() ) {
			return;
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $user_feed->get_url() );
		$to = $meta['id'];
		$inbox = \Activitypub\get_inbox_by_actor( $to );
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Model\Activity( 'Follow', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( \get_author_posts_url( $user_id ) );
		$activity->set_object( $to );
		$activity->set_id( $actor . '#follow-' . \preg_replace( '~^https?://~', '', $to ) );
		$activity = $activity->to_json();
		\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
	}

	public function queue_unfollow_user( \Friends\User_Feed $user_feed ) {
		if ( self::SLUG != $user_feed->get_parser() ) {
			return;
		}

		$args = array( $user_feed->get_id(), get_current_user_id() );

		$follow_timestamp = wp_next_scheduled( 'friends_feed_parser_activitypub_follow', $args );
		if ( $follow_timestamp ) {
			// If we just followed, we don't want the event to potentially be executed after our unfollow event.
			wp_unschedule_event( $follow_timestamp, $args );
		}

		if ( wp_next_scheduled( 'friends_feed_parser_activitypub_unfollow', $args ) ) {
			return;
		}

		return \wp_schedule_single_event( \time(), 'friends_feed_parser_activitypub_unfollow', $args );
	}

	public function unfollow_user( $user_feed_id, $user_id ) {
		$user_feed = \Friends\User_Feed::get_by_id( $user_feed_id );
		if ( self::SLUG != $user_feed->get_parser() ) {
			return;
		}

		$meta = \Activitypub\get_remote_metadata_by_actor( $user_feed->get_url() );
		$to = $meta['id'];
		$inbox = \Activitypub\get_inbox_by_actor( $to );
		$actor = \get_author_posts_url( $user_id );

		$activity = new \Activitypub\Model\Activity( 'Unfollow', \Activitypub\Model\Activity::TYPE_SIMPLE );
		$activity->set_to( null );
		$activity->set_cc( null );
		$activity->set_actor( \get_author_posts_url( $user_id ) );
		$activity->set_object( $to );
		$activity->set_id( $actor . '#unfollow-' . \preg_replace( '~^https?://~', '', $to ) );
		$activity = $activity->to_json();
		\Activitypub\safe_remote_post( $inbox, $activity, $user_id );
	}
}
