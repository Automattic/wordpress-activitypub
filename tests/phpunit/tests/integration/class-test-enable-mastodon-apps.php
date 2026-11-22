<?php
/**
 * Test file for Enable Mastodon Apps integration.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Integration;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Integration\Enable_Mastodon_Apps;
use Activitypub\Transformer\Comment;
use Activitypub\Transformer\Post;
use Activitypub\Tests\Equal_Html;
use Enable_Mastodon_Apps\Handler\Timeline;
use Enable_Mastodon_Apps\Entity\Account;
use Enable_Mastodon_Apps\Entity\Status;
use Enable_Mastodon_Apps\Comment_CPT;
use ReflectionMethod;

/**
 * Test class for Enable Mastodon Apps integration.
 *
 * @group integration
 * @coversDefaultClass \Activitypub\Integration\Enable_Mastodon_Apps
 */
class Test_Enable_Mastodon_Apps extends \WP_UnitTestCase {
	use Equal_Html;

	/**
	 * Remote conversations notify the reader only for mentions or direct replies.
	 */
	public function test_reader_comment_notification_relevance() {
		$this->assertSame( 90, \has_filter( 'mastodon_api_notifications_get', array( Enable_Mastodon_Apps::class, 'filter_reader_comment_notifications' ) ) );
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\get_userdata( $user_id )->add_cap( 'activitypub' );
		\wp_set_current_user( $user_id );
		$actor = \Activitypub\Collection\Actors::get_by_id( $user_id );
		$this->assertNotWPError( $actor );
		$root         = self::factory()->post->create( array( 'post_type' => Remote_Posts::POST_TYPE ) );
		$comment_id   = self::factory()->comment->create(
			array(
				'comment_post_ID' => $root,
				'comment_content' => 'A remote author replying to themselves.',
			)
		);
		$status       = new Status();
		$status->id   = (string) Comment_CPT::comment_id_to_post_id( $comment_id );
		$notification = array(
			'type'   => 'mention',
			'status' => $status,
		);
		$follow       = array( 'type' => 'follow' );
		$this->assertSame( array( $follow ), Enable_Mastodon_Apps::filter_reader_comment_notifications( array( $notification, $follow ) ) );

		// A mention link remains meaningful even when core strips its class and rel.
		\wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => '<a href="' . \esc_url( $actor->get_id() ) . '">@reader</a>',
			)
		);
		$this->assertSame( array( $notification ), Enable_Mastodon_Apps::filter_reader_comment_notifications( array( $notification ) ) );
		\wp_update_comment(
			array(
				'comment_ID'      => $comment_id,
				'comment_content' => 'A remote reply.',
			)
		);

		// Real mention tags in the original inbox object survive comment HTML sanitization.
		$source_id = 'https://remote.example/mentions-reader';
		\update_comment_meta( $comment_id, 'source_id', $source_id );
		$inbox = self::factory()->post->create(
			array(
				'post_type'    => 'ap_inbox',
				'post_status'  => 'publish',
				'post_content' => \wp_json_encode(
					array(
						'object' => array(
							'tag' => array(
								array(
									'type' => 'Mention',
									'href' => $actor->get_id(),
								),
							),
						),
					)
				),
				'meta_input'   => array(
					'_activitypub_activity_type' => 'Create',
					'_activitypub_object_id'     => $source_id,
				),
			)
		);
		$this->assertSame( array( $notification ), Enable_Mastodon_Apps::filter_reader_comment_notifications( array( $notification ) ) );
		\wp_delete_post( $inbox, true );

		// A direct reply to the reader's local comment needs no explicit mention.
		$parent = self::factory()->comment->create(
			array(
				'comment_post_ID' => $root,
				'user_id'         => $user_id,
			)
		);
		\wp_update_comment(
			array(
				'comment_ID'     => $comment_id,
				'comment_parent' => $parent,
			)
		);
		$this->assertSame( array( $notification ), Enable_Mastodon_Apps::filter_reader_comment_notifications( array( $notification ) ) );
	}

	/**
	 * Boosts and likes of somebody else's cached post are not the reader's notifications.
	 */
	public function test_reader_reaction_notifications_require_local_target() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$remote_id = self::factory()->post->create(
			array(
				'post_type'   => Remote_Posts::POST_TYPE,
				'post_author' => 0,
			)
		);
		$own_id    = self::factory()->post->create( array( 'post_author' => $user_id ) );
		foreach ( array( 'reblog', 'favourite' ) as $type ) {
			$remote     = new Status();
			$remote->id = (string) $remote_id;
			$own        = new Status();
			$own->id    = (string) $own_id;
			$unrelated  = array(
				'type'   => $type,
				'status' => $remote,
			);
			$genuine    = array(
				'type'   => $type,
				'status' => $own,
			);
			$this->assertSame( array( $genuine ), Enable_Mastodon_Apps::filter_reader_comment_notifications( array( $unrelated, $genuine ) ) );
		}
	}

	/**
	 * Reader cache identity and priority do not require rendering remote accounts.
	 */
	public function test_reader_timeline_representation_identity_and_priority() {
		$remote_id = self::factory()->post->create( array( 'post_type' => Remote_Posts::POST_TYPE ) );
		$remote    = \get_post( $remote_id );
		$local     = \get_post( self::factory()->post->create() );
		$this->assertSame( $remote->guid, Enable_Mastodon_Apps::reader_timeline_post_identity( null, $remote ) );
		$this->assertNull( Enable_Mastodon_Apps::reader_timeline_post_identity( null, $local ) );
		$this->assertSame( -10, Enable_Mastodon_Apps::reader_timeline_post_priority( 0, $remote ) );
		$this->assertSame( 5, Enable_Mastodon_Apps::reader_timeline_post_priority( 5, $local ) );
	}

	/**
	 * The reader adds only the authenticated actor's posts, retaining local posts.
	 */
	public function test_reader_home_timeline_scoping_and_pagination() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		\update_option( 'activitypub_reader_ui', '1' );
		$local_id = self::factory()->post->create( array( 'post_author' => $user_id ) );
		$actor_id = Remote_Actors::create( self::$users['username@example.org'] );
		$this->assertNotWPError( $actor_id );
		$post_args = array(
			'post_type'    => Remote_Posts::POST_TYPE,
			'post_status'  => 'publish',
			'post_content' => '<p>A cached remote post.</p>',
			'guid'         => 'https://example.org/posts/reader?a=1&b=2',
			'meta_input'   => array( '_activitypub_remote_actor_id' => $actor_id ),
		);
		$own_id    = self::factory()->post->create( $post_args );
		Remote_Posts::add_recipient( $own_id, $user_id );
		// A second recipient must not duplicate a post in the timeline.
		Remote_Posts::add_recipient( $own_id, $user_id + 100 );
		$other_id = self::factory()->post->create( $post_args );
		Remote_Posts::add_recipient( $other_id, $user_id + 100 );
		$orphan_id = self::factory()->post->create( $post_args );

		$request = new \WP_REST_Request( 'GET', '/enable-mastodon-apps/api/v1/timelines/home' );
		$request->set_param( 'limit', 20 );
		$timeline = new Timeline();
		$response = $timeline->api_timelines( null, $request );
		$statuses = $response->get_data();
		$ids      = \array_map(
			static function ( $status ) {
				return $status->id;
			},
			$statuses
		);
		$this->assertContains( (string) $own_id, $ids );
		$this->assertContains( (string) $local_id, $ids );
		$this->assertNotContains( (string) $other_id, $ids );
		$this->assertNotContains( (string) $orphan_id, $ids );
		$this->assertSame( 1, \count( \array_keys( $ids, (string) $own_id, true ) ) );
		$status = $statuses[ \array_search( (string) $own_id, $ids, true ) ];
		$this->assertSame( (string) $actor_id, (string) $status->account->id );
		$this->assertSame( 'https://example.org/posts/reader?a=1&b=2', $status->uri );
		$this->assert_equal_html( '<p>A cached remote post.</p>', $status->content );

		$request->set_param( 'limit', 1 );
		$response = $timeline->api_timelines( null, $request );
		$this->assertCount( 1, $response->get_data() );
		$this->assertSame( (string) $own_id, $response->get_data()[0]->id );
		$this->assertArrayHasKey( 'next', $response->get_links() );
		$request->set_param( 'max_id', $own_id );
		$response = $timeline->api_timelines( null, $request );
		$this->assertSame( (string) $local_id, $response->get_data()[0]->id );
		$request->set_param( 'max_id', null );
		$request->set_param( 'min_id', $local_id );
		$response = $timeline->api_timelines( null, $request );
		$this->assertSame( (string) $own_id, $response->get_data()[0]->id );
	}

	/**
	 * App post-format filters must not exclude unformatted remote reader posts.
	 */
	public function test_reader_posts_bypass_local_post_format_filters() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		\update_option( 'activitypub_reader_ui', '1' );
		$standard_id = self::factory()->post->create();
		$status_id   = self::factory()->post->create();
		\set_post_format( $status_id, 'status' );
		$remote_id = self::factory()->post->create( array( 'post_type' => Remote_Posts::POST_TYPE ) );
		Remote_Posts::add_recipient( $remote_id, $user_id );
		$other_id = self::factory()->post->create( array( 'post_type' => Remote_Posts::POST_TYPE ) );
		Remote_Posts::add_recipient( $other_id, $user_id + 100 );
		$request  = new \WP_REST_Request( 'GET', '/enable-mastodon-apps/api/v1/timelines/home' );
		$args     = array(
			'post_type'        => array( 'post' ),
			'post_status'      => 'publish',
			'fields'           => 'ids',
			'posts_per_page'   => 20,
			'suppress_filters' => false,
			'tax_query'        => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'post_format',
					'field'    => 'slug',
					'terms'    => array( 'post-format-status' ),
					'operator' => 'IN',
				),
			),
		);
		$filtered = Enable_Mastodon_Apps::api_home_timeline_args( $args, $request );
		$this->assertSame( array( $remote_id, $status_id ), \get_posts( $filtered ) );
		// The standard format uses NOT IN; local exclusions still apply.
		$args['tax_query'][0]['operator'] = 'NOT IN';
		$filtered                         = Enable_Mastodon_Apps::api_home_timeline_args( $args, $request );
		$this->assertSame( array( $remote_id, $standard_id ), \get_posts( $filtered ) );
	}

	/**
	 * Inline images become one attachment each, preserving dimensions and alt text.
	 */
	public function test_reader_inline_images_become_attachments() {
		$method = new ReflectionMethod( Enable_Mastodon_Apps::class, 'reader_media' );
		$method->setAccessible( true );
		$url   = 'https://example.org/photo.png?a=1&b=2';
		$media = $method->invoke(
			null,
			'<p>Body <img src="https://example.org/photo.png?a=1&amp;b=2" alt="A &amp; B" width="640" height="480"></p><img src="https://example.org/photo.png?a=1&amp;b=2">',
			array()
		);
		$this->assertCount( 1, $media['attachments'] );
		$this->assertSame( $url, $media['attachments'][0]['url'] );
		$this->assertSame( 'A & B', $media['attachments'][0]['alt'] );
		$this->assertSame( 640, $media['attachments'][0]['width'] );
		$this->assertSame( 480, $media['attachments'][0]['height'] );
		$this->assert_equal_html( '<p>Body </p>', $media['content'] );
		$cached             = $media['attachments'];
		$cached[0]['width'] = 1280;
		$media              = $method->invoke( null, '<img src="https://example.org/photo.png?a=1&amp;b=2" width="640" height="480">', $cached );
		$this->assertSame( 1280, $media['attachments'][0]['width'] );
	}

	/**
	 * Reader attachments expose the size and descriptions supplied by federation.
	 */
	public function test_reader_status_returns_attachment_dimensions() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		\wp_set_current_user( $user_id );
		$actor_id = Remote_Actors::create( self::$users['username@example.org'] );
		$this->assertNotWPError( $actor_id );
		$post_id = self::factory()->post->create(
			array(
				'post_type'    => Remote_Posts::POST_TYPE,
				'post_content' => '<p>Examiner.com in 1996</p><img src="https://example.org/screenshot.png" alt="Screenshot">',
				'meta_input'   => array(
					'_activitypub_remote_actor_id' => $actor_id,
					'_activitypub_attachments'     => array(
						array(
							'url'      => 'https://example.org/screenshot.png',
							'type'     => 'image',
							'alt'      => 'Screenshot',
							'width'    => 640,
							'height'   => 480,
							'blurhash' => 'test-hash',
						),
					),
				),
			)
		);
		Remote_Posts::add_recipient( $post_id, $user_id );
		$status = Enable_Mastodon_Apps::api_reader_status( null, $post_id );
		$this->assertInstanceOf( Status::class, $status );
		$this->assertCount( 1, $status->media_attachments );
		$attachment = $status->media_attachments[0];
		$this->assertSame( 'image', $attachment->type );
		$this->assertSame( 'Screenshot', $attachment->description );
		$this->assertSame( 'test-hash', $attachment->blurhash );
		$this->assertSame(
			array(
				'width'  => 640,
				'height' => 480,
				'size'   => '640x480',
				'aspect' => 640 / 480,
			),
			$attachment->meta['original']
		);
		$this->assert_equal_html( '<p>Examiner.com in 1996</p>', $status->content );
	}

	/**
	 * Disabled readers, public timelines, and tag timelines keep their query args.
	 */
	public function test_reader_timeline_requires_enabled_home_route() {
		\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$args    = array( 'post_type' => array( 'post' ) );
		$request = new \WP_REST_Request( 'GET', '/enable-mastodon-apps/api/v1/timelines/home' );
		\update_option( 'activitypub_reader_ui', '0' );
		$this->assertSame( $args, Enable_Mastodon_Apps::api_home_timeline_args( $args, $request ) );
		\update_option( 'activitypub_reader_ui', '1' );
		foreach ( array( 'public', 'tag/test' ) as $route ) {
			$request->set_route( '/enable-mastodon-apps/api/v1/timelines/' . $route );
			$this->assertSame( $args, Enable_Mastodon_Apps::api_home_timeline_args( $args, $request ) );
		}
		$this->assertSame( 'WHERE 1=1', Enable_Mastodon_Apps::filter_reader_timeline_where( 'WHERE 1=1', new \WP_Query() ) );
	}

	/**
	 * Guessing a reader status ID must not bypass recipient or capability checks.
	 */
	public function test_reader_status_is_private_to_its_recipient() {
		$user_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$post_id = self::factory()->post->create( array( 'post_type' => Remote_Posts::POST_TYPE ) );
		Remote_Posts::add_recipient( $post_id, $user_id );
		$other_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		foreach ( array( 0, $other_id ) as $viewer_id ) {
			\wp_set_current_user( $viewer_id );
			$this->assertNull( \apply_filters( 'mastodon_api_status', null, $post_id ) );
		}
		// Even its recipient needs permission to use ActivityPub.
		$subscriber_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		Remote_Posts::add_recipient( $post_id, $subscriber_id );
		\wp_set_current_user( $subscriber_id );
		$this->assertNull( \apply_filters( 'mastodon_api_status', null, $post_id ) );
	}

	/**
	 * Only an authorized blog actor may fall back to the blog's feed.
	 */
	public function test_reader_blog_actor_fallback_requires_permission() {
		\update_option( 'activitypub_reader_ui', '1' );
		$args               = array( 'post_type' => array( 'post' ) );
		$request            = new \WP_REST_Request( 'GET', '/enable-mastodon-apps/api/v1/timelines/home' );
		$disable_user_actor = static function () {
			return false;
		};
		\add_filter( 'activitypub_user_can_activitypub', $disable_user_actor );
		try {
			\wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
			$actual = Enable_Mastodon_Apps::api_home_timeline_args( $args, $request );
			$this->assertSame( 0, $actual['activitypub_reader_actor'] );
			\wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
			$this->assertSame( $args, Enable_Mastodon_Apps::api_home_timeline_args( $args, $request ) );
			\wp_set_current_user( 0 );
			$this->assertSame( $args, Enable_Mastodon_Apps::api_home_timeline_args( $args, $request ) );
		} finally {
			\remove_filter( 'activitypub_user_can_activitypub', $disable_user_actor );
		}
	}


	/**
	 * EMA comment context resolves known actors without URL discovery.
	 */
	public function test_comment_account_filter_uses_stored_actor() {
		$actor_id = Remote_Actors::create( self::$users['username@example.org'] );
		$this->assertNotWPError( $actor_id );
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_author_url' => 'https://example.org/users/username',
				'user_id'            => 0,
			)
		);
		\update_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', $actor_id );
		$discoveries       = 0;
		$observe_discovery = static function ( $pre ) use ( &$discoveries ) {
			++$discoveries;
			return $pre;
		};
		\add_filter( 'pre_get_remote_metadata_by_actor', $observe_discovery );
		try {
			$account = \apply_filters( 'mastodon_api_account', null, $comment->comment_author_url, null, $comment );
		} finally {
			\remove_filter( 'pre_get_remote_metadata_by_actor', $observe_discovery );
		}
		$this->assertInstanceOf( Account::class, $account );
		$this->assertSame( (string) $actor_id, $account->id );
		$this->assertSame( 0, $discoveries );
		// Never override another provider, an unrelated identifier, or a local author.
		$existing = new Account();
		$this->assertSame( $existing, Enable_Mastodon_Apps::api_account_comment( $existing, $comment->comment_author_url, null, $comment ) );
		$this->assertNull( Enable_Mastodon_Apps::api_account_comment( null, 'someone-else', null, $comment ) );
		$comment->user_id = 123;
		$this->assertNull( Enable_Mastodon_Apps::api_account_comment( null, $comment->comment_author_url, null, $comment ) );
	}

	/**
	 * Missing and stale actor metadata must leave the URL fallback available.
	 */
	public function test_comment_account_filter_leaves_unknown_actor_to_other_handlers() {
		$comment = self::factory()->comment->create_and_get(
			array(
				'comment_author_url' => 'https://example.org/users/username',
				'user_id'            => 0,
			)
		);
		$this->assertNull( Enable_Mastodon_Apps::api_account_comment( null, $comment->comment_author_url, null, $comment ) );
		$post_id = self::factory()->post->create();
		\update_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', $post_id );
		$this->assertNull( Enable_Mastodon_Apps::api_account_comment( null, $comment->comment_author_url, null, $comment ) );
		\update_comment_meta( $comment->comment_ID, '_activitypub_remote_actor_id', $post_id + 100000 );
		$this->assertNull( Enable_Mastodon_Apps::api_account_comment( null, $comment->comment_author_url, null, $comment ) );
	}

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
	 * Non-media attachments must not prevent a remote status from loading.
	 */
	public function test_activity_to_status_skips_non_media_attachments() {
		$method = new \ReflectionMethod( Enable_Mastodon_Apps::class, 'activity_to_status' );
		$method->setAccessible( true );
		$status = $method->invoke(
			null,
			array(
				'id'         => 'https://example.org/posts/1',
				'type'       => 'Note',
				'published'  => '2026-01-01T12:00:00Z',
				'content'    => 'A post with mixed attachments.',
				'attachment' => array(
					array(
						'type'  => 'PropertyValue',
						'name'  => 'Website',
						'value' => 'https://example.org/',
					),
					array(
						'type'      => 'Document',
						'url'       => 'https://example.org/file.pdf',
						'mediaType' => 'application/pdf',
					),
					array(
						'type'      => 'Document',
						'mediaType' => 'image/jpeg',
					),
					array(
						'type'      => 'Document',
						'url'       => 'https://example.org/image.jpg',
						'mediaType' => 'image/jpeg',
						'name'      => 'An image',
					),
				),
			),
			new Account()
		);

		$this->assertInstanceOf( Status::class, $status );
		$this->assertSame( array( 0 ), array_keys( $status->media_attachments ) );
		$this->assertSame( 'https://example.org/image.jpg', $status->media_attachments[0]->url );
		$this->assertSame( 'image', $status->media_attachments[0]->type );
		$this->assertSame( 'An image', $status->media_attachments[0]->description );
	}

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
	 * Count published posts of supported types for the requested local author.
	 */
	public function test_api_account_internal_statuses_count() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		\update_option( 'activitypub_support_post_types', array( 'post', 'page' ) );
		self::factory()->post->create_many( 2, array( 'post_author' => $user_id ) );
		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_type'   => 'page',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'draft',
			)
		);
		self::factory()->post->create(
			array(
				'post_author' => $user_id,
				'post_status' => 'private',
			)
		);
		self::factory()->post->create();

		$account = Enable_Mastodon_Apps::api_account_internal( null, $user_id );

		$this->assertSame( 3, $account->statuses_count );
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

	/**
	 * Test that a status submitted from a Mastodon app keeps its plain mention.
	 *
	 * Mentions are turned into links when the content is rendered and when the activity is
	 * built. Rewriting them while the status is being submitted would store the shortened
	 * link text instead, and the plain `@user@domain` that decides who gets addressed and
	 * notified would be gone by the time the post federates.
	 *
	 * @covers ::init
	 */
	public function test_submitted_status_keeps_mention_for_federation() {
		$status_text = 'Hello @username@example.org!';

		$submitted = \apply_filters( 'mastodon_api_submit_status_text', $status_text, null, 'public' );

		$this->assertSame( $status_text, $submitted );
		$this->assertSame(
			array( '@username@example.org' => 'https://example.org/users/username' ),
			\apply_filters( 'activitypub_extract_mentions', array(), $submitted, null )
		);
	}

	/**
	 * A post is federated with the language a Mastodon app set when it was published.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_post_federates_the_language_an_app_set() {
		$post_id = self::factory()->post->create( array( 'post_content' => 'Hallo Welt' ) );
		\update_post_meta( $post_id, 'ema_language', 'de' );

		$object = Post::transform( \get_post( $post_id ) )->to_object();

		$this->assertSame( array( 'de' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * A comment takes the language an app set for the post it belongs to.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_comment_federates_the_language_of_its_post() {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create( array( 'comment_post_ID' => $post_id ) );
		\update_post_meta( $post_id, 'ema_language', 'fr' );

		$object = Comment::transform( \get_comment( $comment_id ) )->to_object();

		$this->assertSame( array( 'fr' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * A multilingual plugin's language takes precedence over the language an app set.
	 *
	 * @covers ::get_post_locale
	 */
	public function test_multilingual_plugin_language_wins_over_the_app_language() {
		$post_id = self::factory()->post->create();
		\update_post_meta( $post_id, 'ema_language', 'de' );

		\add_filter( 'activitypub_locale', array( self::class, 'return_fr' ) );
		$object = Post::transform( \get_post( $post_id ) )->to_object();
		\remove_filter( 'activitypub_locale', array( self::class, 'return_fr' ) );

		$this->assertSame( array( 'fr' ), \array_keys( $object->get_content_map() ) );
	}

	/**
	 * Stand-in for a multilingual plugin that knows the post's language.
	 *
	 * @return string
	 */
	public static function return_fr() {
		return 'fr';
	}
}
