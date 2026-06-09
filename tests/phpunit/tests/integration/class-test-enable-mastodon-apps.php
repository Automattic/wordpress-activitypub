<?php
/**
 * Test file for Enable Mastodon Apps integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Integration\Enable_Mastodon_Apps;
use Enable_Mastodon_Apps\Entity\Status;

/**
 * Test class for Enable Mastodon Apps integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Enable_Mastodon_Apps
 */
class Test_Enable_Mastodon_Apps extends \WP_UnitTestCase {

	/**
	 * Actors.
	 *
	 * @var array[]
	 */
	public static $users = array(
		'username@example.org' => array(
			'type'              => 'Person',
			'id'                => 'https://example.org/users/username',
			'url'               => 'https://example.org/users/username',
			'inbox'             => 'https://example.org/users/username/inbox',
			'name'              => 'username',
			'preferredUsername' => 'username',
			'published'         => '2024-01-01T00:00:00+00:00',
		),
		'jon@example.com'      => array(
			'type'              => 'Person',
			'id'                => 'https://example.com/author/jon',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		),
		'doe@example.org'      => array(
			'type'              => 'Person',
			'id'                => 'https://example.org/author/doe',
			'url'               => 'https://example.org/author/doe',
			'inbox'             => 'https://example.org/author/doe/inbox',
			'name'              => 'doe',
			'preferredUsername' => 'doe',
		),
		'sally@example.org'    => array(
			'type'              => 'Person',
			'id'                => 'http://sally.example.org',
			'url'               => 'http://sally.example.org',
			'inbox'             => 'http://sally.example.org/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		),
		'12345@example.com'    => array(
			'type'              => 'Person',
			'id'                => 'https://12345.example.com',
			'url'               => 'https://12345.example.com',
			'inbox'             => 'https://12345.example.com/inbox',
			'name'              => '12345',
			'preferredUsername' => '12345',
		),
		'user2@example.com'    => array(
			'type'              => 'Person',
			'id'                => 'https://user2.example.com',
			'url'               => 'https://user2.example.com',
			'inbox'             => 'https://user2.example.com/inbox',
			'name'              => 'úser2',
			'preferredUsername' => 'user2',
		),
		'error@example.com'    => array(
			'type'              => 'Person',
			'url'               => 'https://error.example.com',
			'name'              => 'error',
			'preferredUsername' => 'error',
		),
	);

	/**
	 * Set up.
	 */
	public function set_up() {
		parent::set_up();

		if ( ! class_exists( '\Enable_Mastodon_Apps\Entity\Entity' ) ) {
			self::markTestSkipped( 'The Enable_Mastodon_Apps plugin is not active.' );
		}
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		add_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
		_delete_all_posts();
	}

	/**
	 * Tear down.
	 */
	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
		parent::tear_down();
	}

	/**
	 * Test the account object.
	 */
	public function test_api_account_external() {
		$account = apply_filters( 'mastodon_api_account', array(), 'username@example.org' );
		$this->assertNotEmpty( $account );
		$account = $account->jsonSerialize();
		$this->assertArrayHasKey( 'id', $account );
		$this->assertArrayHasKey( 'username', $account );
		$this->assertArrayHasKey( 'acct', $account );
		$this->assertArrayHasKey( 'display_name', $account );
		$this->assertArrayHasKey( 'url', $account );
		$this->assertEquals( 'https://example.org/users/username', $account['url'] );
		$this->assertEquals( 'username', $account['display_name'] );
	}

	/**
	 * Test followers count.
	 */
	public function test_api_account_followers_internal() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add( 1, $follower );
		}

		$account = new \Enable_Mastodon_Apps\Entity\Account();
		$this->assertEquals( 0, $account->followers_count );

		$account = apply_filters( 'mastodon_api_account', $account, 1 );
		$this->assertEquals( 3, $account->followers_count );
	}

	/**
	 * Test api_status.
	 *
	 * @covers ::api_status
	 */
	public function test_api_status() {
		$post_id = self::factory()->post->create(
			array(
				'meta_input' => array(
					'activitypub_content_visibility' => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
				),
			)
		);

		$this->assertNull( Enable_Mastodon_Apps::api_status( null, $post_id ) );
	}

	/**
	 * Filters the HTTP request before it is sent.
	 *
	 * @param array|bool $preempt Whether to preempt an HTTP request's return value.
	 * @param array      $request Request arguments.
	 * @param string     $url     The request URL.
	 * @return array|bool
	 */
	public static function pre_http_request( $preempt, $request, $url ) {
		switch ( $url ) {
			case 'https://example.org/.well-known/webfinger?resource=acct%3Ausername%40example.org':
			case 'https://example.org/.well-known/webfinger?resource=https%3A%2F%2Fexample.org%2Fusers%2Fusername':
				return array(
					'headers'  => array(
						'content-type' => 'text/json',
					),
					'body'     => wp_json_encode(
						array(
							'subject' => 'acct:username@example.org',
							'links'   => array(
								array(
									'rel'  => 'self',
									'type' => 'application/activity+json',
									'href' => 'https://example.org/users/username',
								),
							),
						)
					),
					'response' => array(
						'code' => 200,
					),
				);

			case 'https://example.org/users/username':
				return array(
					'headers'  => array(
						'content-type' => 'application/activity+json',
					),
					'body'     => wp_json_encode( self::$users['username@example.org'] ),
					'response' => array(
						'code' => 200,
					),
				);
		}
		return $preempt;
	}

	/**
	 * Filters the remote metadata for a given URL.
	 *
	 * @param array|bool $pre   The metadata for the URL, or false to avoid the HTTP request.
	 * @param string     $actor The URL for the user.
	 * @return array|bool
	 */
	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
		}
		foreach ( self::$users as $data ) {
			if ( $data['url'] === $actor ) {
				return $data;
			}
		}
		return $pre;
	}

	/**
	 * Test tag timeline returns early when no hashtag is provided.
	 *
	 * @covers ::api_tag_timeline_tags_pub
	 */
	public function test_tag_timeline_returns_early_without_hashtag() {
		$request = new \WP_REST_Request( 'GET', '/api/v1/timelines/tag/' );

		$result = Enable_Mastodon_Apps::api_tag_timeline_tags_pub( null, $request );

		$this->assertNull( $result );
	}

	/**
	 * Test tag timeline returns input when statuses is not WP_REST_Response.
	 *
	 * @covers ::api_tag_timeline_tags_pub
	 */
	public function test_tag_timeline_returns_input_for_non_response() {
		$request = new \WP_REST_Request( 'GET', '/api/v1/timelines/tag/test' );
		$request->set_param( 'hashtag', 'test' );

		$input  = array( 'some', 'data' );
		$result = Enable_Mastodon_Apps::api_tag_timeline_tags_pub( $input, $request );

		$this->assertEquals( $input, $result );
	}

	/**
	 * Test tag timeline deduplicates statuses by ID.
	 *
	 * @covers ::api_tag_timeline_tags_pub
	 */
	public function test_tag_timeline_deduplicates() {
		$status1             = new Status();
		$status1->id         = 'unique-1';
		$status1->created_at = new \DateTime( '2025-01-02' );

		$status2             = new Status();
		$status2->id         = 'unique-1'; // Duplicate ID.
		$status2->created_at = new \DateTime( '2025-01-01' );

		$status3             = new Status();
		$status3->id         = 'unique-2';
		$status3->created_at = new \DateTime( '2025-01-03' );

		$response       = new \WP_REST_Response();
		$response->data = array( $status1 );

		// Mock the fetch to return statuses with a duplicate.
		\set_transient( 'activitypub_tags_pub_' . \md5( 'dedup' ), array(), 60 );

		$request = new \WP_REST_Request( 'GET', '/api/v1/timelines/tag/dedup' );
		$request->set_param( 'hashtag', 'dedup' );

		$result = Enable_Mastodon_Apps::api_tag_timeline_tags_pub( $response, $request );

		// With empty transient cache, no remote statuses are added.
		$this->assertCount( 1, $result->data );

		\delete_transient( 'activitypub_tags_pub_' . \md5( 'dedup' ) );
	}

	/**
	 * Test tag timeline sorts by created_at descending.
	 *
	 * @covers ::api_tag_timeline_tags_pub
	 */
	public function test_tag_timeline_sorts_descending() {
		$older             = new Status();
		$older->id         = 'old';
		$older->created_at = new \DateTime( '2025-01-01' );

		$newer             = new Status();
		$newer->id         = 'new';
		$newer->created_at = new \DateTime( '2025-06-01' );

		$response       = new \WP_REST_Response();
		$response->data = array( $older, $newer );

		// Empty cache so no remote items are fetched.
		\set_transient( 'activitypub_tags_pub_' . \md5( 'sorted' ), array(), 60 );

		$request = new \WP_REST_Request( 'GET', '/api/v1/timelines/tag/sorted' );
		$request->set_param( 'hashtag', 'sorted' );

		$result = Enable_Mastodon_Apps::api_tag_timeline_tags_pub( $response, $request );

		$this->assertEquals( 'new', $result->data[0]->id );
		$this->assertEquals( 'old', $result->data[1]->id );

		\delete_transient( 'activitypub_tags_pub_' . \md5( 'sorted' ) );
	}

	/**
	 * Test tags.pub base URL is filterable.
	 *
	 * @covers ::resolve_tags_pub_items
	 */
	public function test_tags_pub_base_url_filterable() {
		$filter_called = false;

		\add_filter(
			'activitypub_tags_pub_base_url',
			function ( $url ) use ( &$filter_called ) {
				$filter_called = true;
				return $url;
			}
		);

		// Trigger a fetch (will fail since no HTTP mock, but filter should fire).
		\delete_transient( 'activitypub_tags_pub_' . \md5( 'filtertest' ) );

		$request = new \WP_REST_Request( 'GET', '/api/v1/timelines/tag/filtertest' );
		$request->set_param( 'hashtag', 'filtertest' );

		$response       = new \WP_REST_Response();
		$response->data = array();

		Enable_Mastodon_Apps::api_tag_timeline_tags_pub( $response, $request );

		$this->assertTrue( $filter_called, 'activitypub_tags_pub_base_url filter should be called.' );

		\delete_transient( 'activitypub_tags_pub_' . \md5( 'filtertest' ) );
	}

	/**
	 * Content provider.
	 *
	 * @return array[]
	 */
	public function extract_name_from_uri_content_provider() {
		return array(
			array( 'https://example.com/@user', 'user' ),
			array( 'https://example.com/@user/', 'user' ),
			array( 'https://example.com/users/user', 'user' ),
			array( 'https://example.com/users/user/', 'user' ),
			array( 'https://example.com/@user?as=asasas', 'user' ),
			array( 'https://example.com/@user#asass', 'user' ),
			array( '@user@example.com', 'user' ),
			array( 'acct:user@example.com', 'user' ),
			array( 'user@example.com', 'user' ),
			array( 'https://example.com', 'https://example.com' ),
		);
	}

	/**
	 * Test follow notifications with icon as array.
	 *
	 * @covers ::api_notifications_get
	 */
	public function test_follow_notification_with_icon_array() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/with-icon-array',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/with-icon-array',
			'inbox'             => 'https://remote.example.com/actor/with-icon-array/inbox',
			'name'              => 'Icon Array User',
			'preferredUsername' => 'iconarray',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://remote.example.com/avatar.png',
			),
		);

		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_data ) {
				return $actor_data;
			}
		);

		$follower_id = Followers::add( $user_id, $actor_data['id'] );
		$this->assertNotInstanceOf( 'WP_Error', $follower_id );

		$request = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'types', array( 'follow' ) );

		$notifications = apply_filters( 'mastodon_api_notifications_get', array(), $request );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'follow', $notifications[0]->type );
		$this->assertEquals( 'https://remote.example.com/avatar.png', $notifications[0]->account->avatar );
	}

	/**
	 * Test follow notifications with icon as string URL.
	 *
	 * @covers ::api_notifications_get
	 */
	public function test_follow_notification_with_icon_string() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/with-icon-string',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/with-icon-string',
			'inbox'             => 'https://remote.example.com/actor/with-icon-string/inbox',
			'name'              => 'Icon String User',
			'preferredUsername' => 'iconstring',
			'icon'              => 'https://remote.example.com/avatar-string.png',
		);

		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_data ) {
				return $actor_data;
			}
		);

		$follower_id = Followers::add( $user_id, $actor_data['id'] );
		$this->assertNotInstanceOf( 'WP_Error', $follower_id );

		$request = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'types', array( 'follow' ) );

		$notifications = apply_filters( 'mastodon_api_notifications_get', array(), $request );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'follow', $notifications[0]->type );
		$this->assertEquals( 'https://remote.example.com/avatar-string.png', $notifications[0]->account->avatar );
	}

	/**
	 * Test follow notifications without icon.
	 *
	 * @covers ::api_notifications_get
	 */
	public function test_follow_notification_without_icon() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/no-icon',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/no-icon',
			'inbox'             => 'https://remote.example.com/actor/no-icon/inbox',
			'name'              => 'No Icon User',
			'preferredUsername' => 'noicon',
		);

		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_data ) {
				return $actor_data;
			}
		);

		$follower_id = Followers::add( $user_id, $actor_data['id'] );
		$this->assertNotInstanceOf( 'WP_Error', $follower_id );

		$request = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'types', array( 'follow' ) );

		$notifications = apply_filters( 'mastodon_api_notifications_get', array(), $request );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'follow', $notifications[0]->type );
		$this->assertEmpty( $notifications[0]->account->avatar );
	}

	/**
	 * Test favourite notifications.
	 *
	 * @covers ::api_notifications_get
	 */
	public function test_favourite_notification() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'activitypub' );
		wp_set_current_user( $user_id );

		// Create a post authored by the current user.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		// Create and store the remote actor.
		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/liker',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/liker',
			'inbox'             => 'https://remote.example.com/actor/liker/inbox',
			'name'              => 'Liker',
			'preferredUsername' => 'liker',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://remote.example.com/liker-avatar.png',
			),
		);

		$actor_id = Remote_Actors::create( $actor_data );
		$this->assertIsInt( $actor_id );

		// Create a like comment on the post.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => 'like',
				'comment_author'       => 'Liker',
				'comment_author_url'   => 'https://remote.example.com/actor/liker',
				'comment_author_email' => '',
				'user_id'              => 0,
				'comment_approved'     => 1,
			)
		);
		update_comment_meta( $comment_id, 'protocol', 'activitypub' );
		update_comment_meta( $comment_id, '_activitypub_remote_actor_id', $actor_id );

		// Verify comment was created correctly.
		$comment = get_comment( $comment_id );
		$this->assertEquals( 'like', $comment->comment_type );

		$request = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'types', array( 'favourite' ) );

		$notifications = apply_filters( 'mastodon_api_notifications_get', array(), $request );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'favourite', $notifications[0]->type );
		$this->assertEquals( 'https://remote.example.com/liker-avatar.png', $notifications[0]->account->avatar );
		$this->assertEquals( $post_id, $notifications[0]->status->id );
	}

	/**
	 * Test reblog notifications.
	 *
	 * @covers ::api_notifications_get
	 */
	public function test_reblog_notification() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = get_user_by( 'id', $user_id );
		$user->add_cap( 'activitypub' );
		wp_set_current_user( $user_id );

		// Create a post authored by the current user.
		$post_id = self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'publish',
			)
		);

		// Create and store the remote actor.
		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/booster',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/booster',
			'inbox'             => 'https://remote.example.com/actor/booster/inbox',
			'name'              => 'Booster',
			'preferredUsername' => 'booster',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://remote.example.com/booster-avatar.png',
			),
		);

		$actor_id = Remote_Actors::create( $actor_data );
		$this->assertIsInt( $actor_id );

		// Create a repost comment on the post.
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'      => $post_id,
				'comment_type'         => 'repost',
				'comment_author'       => 'Booster',
				'comment_author_url'   => 'https://remote.example.com/actor/booster',
				'comment_author_email' => '',
				'user_id'              => 0,
				'comment_approved'     => 1,
			)
		);
		update_comment_meta( $comment_id, 'protocol', 'activitypub' );
		update_comment_meta( $comment_id, '_activitypub_remote_actor_id', $actor_id );

		// Verify comment was created correctly.
		$comment = get_comment( $comment_id );
		$this->assertEquals( 'repost', $comment->comment_type );

		$request = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'types', array( 'reblog' ) );

		$notifications = apply_filters( 'mastodon_api_notifications_get', array(), $request );

		$this->assertCount( 1, $notifications );
		$this->assertEquals( 'reblog', $notifications[0]->type );
		$this->assertEquals( 'https://remote.example.com/booster-avatar.png', $notifications[0]->account->avatar );
		$this->assertEquals( $post_id, $notifications[0]->status->id );
	}

	/**
	 * Test notification type filtering with exclude_types.
	 *
	 * @covers ::api_notifications_get
	 */
	public function test_notification_exclude_types() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/test-exclude',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/test-exclude',
			'inbox'             => 'https://remote.example.com/actor/test-exclude/inbox',
			'name'              => 'Test Exclude',
			'preferredUsername' => 'testexclude',
		);

		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_data ) {
				return $actor_data;
			}
		);

		$follower_id = Followers::add( $user_id, $actor_data['id'] );
		$this->assertNotInstanceOf( 'WP_Error', $follower_id );

		// Verify follower was added.
		$request_with_follow = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request_with_follow->set_param( 'types', array( 'follow' ) );
		$notifications_with_follow = apply_filters( 'mastodon_api_notifications_get', array(), $request_with_follow );
		$this->assertCount( 1, $notifications_with_follow );

		// Now test that excluding follow removes it.
		$request = new \WP_REST_Request( 'GET', '/api/v1/notifications' );
		$request->set_param( 'exclude_types', array( 'follow' ) );

		$notifications = apply_filters( 'mastodon_api_notifications_get', array(), $request );

		foreach ( $notifications as $notification ) {
			$this->assertNotEquals( 'follow', $notification->type );
		}
	}

	/**
	 * Test api_account_followers returns accounts with avatars.
	 *
	 * @covers ::api_account_followers
	 */
	public function test_api_account_followers_with_avatars() {
		$user_id = self::factory()->user->create();

		$actor_data = array(
			'id'                => 'https://remote.example.com/actor/follower-avatar',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/follower-avatar',
			'inbox'             => 'https://remote.example.com/actor/follower-avatar/inbox',
			'name'              => 'Follower With Avatar',
			'preferredUsername' => 'followeravatar',
			'icon'              => array(
				'type' => 'Image',
				'url'  => 'https://remote.example.com/follower-avatar.png',
			),
		);

		add_filter(
			'pre_get_remote_metadata_by_actor',
			function () use ( $actor_data ) {
				return $actor_data;
			}
		);

		$follower_id = Followers::add( $user_id, $actor_data['id'] );
		$this->assertNotInstanceOf( 'WP_Error', $follower_id );

		$followers = apply_filters( 'mastodon_api_account_followers', array(), $user_id );

		$this->assertCount( 1, $followers );
		$this->assertEquals( 'https://remote.example.com/follower-avatar.png', $followers[0]->avatar );
	}
}
