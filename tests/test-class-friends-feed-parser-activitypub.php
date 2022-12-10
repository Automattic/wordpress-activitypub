<?php

class Test_Friends_Feed_Parser_ActivityPub extends ActivityPub_TestCase_Cache_HTTP {
	public static $users = array();
	private $friend_id;
	private $friend_name;
	private $friend_nicename;
	private $actor;

	public function test_incoming_post() {
		update_user_option( 'activitypub_friends_show_replies', '1', $this->friend_id );
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
			)
		);

		$post_count = count( $posts );

		// Let's post a new Note through the REST API.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = 'Test ' . $date . ' ' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_param( 'type', 'Create' );
		$request->set_param( 'id', $id );
		$request->set_param( 'actor', $this->actor );

		$attachment_url = 'https://mastodon.local/files/original/1234.png';
		$attachment_width = 400;
		$attachment_height = 600;
		$request->set_param(
			'object',
			array(
				'type' => 'Note',
				'id' => $id,
				'attributedTo' => $this->actor,
				'content' => $content,
				'url' => 'https://mastodon.local/users/akirk/statuses/' . ( $status_id++ ),
				'published' => $date,
				'attachment' => array(
					array(
						'type' => 'Document',
						'mediaType' => 'image/png',
						'url' => $attachment_url,
						'name' => '',
						'blurhash' => '',
						'width' => $attachment_width,
						'height' => $attachment_height,

					),
				),
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertStringContainsString( '<img src="' . esc_url( $attachment_url ) . '" width="' . esc_attr( $attachment_width ) . '" height="' . esc_attr( $attachment_height ) . '"', $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );

		// Do another test post, this time with a URL that has an @-id.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = 'Test ' . $date . ' ' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_param( 'type', 'Create' );
		$request->set_param( 'id', $id );
		$request->set_param( 'actor', 'https://mastodon.local/@akirk' );
		$request->set_param(
			'object',
			array(
				'type' => 'Note',
				'id' => $id,
				'attributedTo' => 'https://mastodon.local/@akirk',
				'content' => $content,
				'url' => 'https://mastodon.local/users/akirk/statuses/' . ( $status_id++ ),
				'published' => $date,
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 2, count( $posts ) );
		$this->assertEquals( $content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
		$this->assertEquals( $this->friend_name, get_post_meta( $posts[0]->ID, 'author', true ) );

		delete_user_option( 'activitypub_friends_show_replies', $this->friend_id );

	}

	public function test_incoming_mention_of_others() {
		$now = time() - 10;
		$status_id = 123;

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
			)
		);

		$post_count = count( $posts );

		// Let's post a new Note through the REST API.
		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;
		$content = '<a rel="mention" class="u-url mention" href="https://example.org/users/abc">@<span>abc</span></a> Test ' . $date . ' ' . wp_rand();

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_param( 'type', 'Create' );
		$request->set_param( 'id', $id );
		$request->set_param( 'actor', $this->actor );

		$request->set_param(
			'object',
			array(
				'type' => 'Note',
				'id' => $id,
				'attributedTo' => $this->actor,
				'content' => $content,
				'url' => 'https://mastodon.local/users/akirk/statuses/' . ( $status_id++ ),
				'published' => $date,
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
				'post_status' => 'trash',
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringStartsWith( $content, $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
	}

	public function test_incoming_announce() {
		$now = time() - 10;
		$status_id = 123;

		self::$users['https://notiz.blog/author/matthias-pfefferle/'] = array(
			'url' => 'https://notiz.blog/author/matthias-pfefferle/',
			'name'  => 'Matthias Pfefferle',
		);

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
			)
		);

		$post_count = count( $posts );

		$date = gmdate( \DATE_W3C, $now++ );
		$id = 'test' . $status_id;

		$object = 'https://notiz.blog/2022/11/14/the-at-protocol/';

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_param( 'type', 'Announce' );
		$request->set_param( 'id', $id );
		$request->set_param( 'actor', $this->actor );
		$request->set_param( 'published', $date );
		$request->set_param( 'object', $object );

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$p = wp_parse_url( $object );
		$cache = __DIR__ . '/fixtures/' . sanitize_title( $p['host'] . '-' . $p['path'] ) . '.json';
		$this->assertFileExists( $cache );

		$object = json_decode( wp_remote_retrieve_body( json_decode( file_get_contents( $cache ), true ) ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $this->friend_id,
			)
		);

		$this->assertEquals( $post_count + 1, count( $posts ) );
		$this->assertStringContainsString( 'Dezentrale Netzwerke', $posts[0]->post_content );
		$this->assertEquals( $this->friend_id, $posts[0]->post_author );
		$this->assertEquals( 'Matthias Pfefferle', get_post_meta( $posts[0]->ID, 'author', true ) );
	}

	public function test_friend_mentions() {
		add_filter( 'activitypub_cache_possible_friend_mentions', '__return_false' );
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => '@' . sanitize_title( $this->friend_nicename ) . '  hello',
			)
		);

		$activitypub_post = new \Activitypub\Model\Post( $post );

		$activitypub_activity = new \Activitypub\Model\Activity( 'Create', \Activitypub\Model\Activity::TYPE_FULL );
		$activitypub_activity->from_post( $activitypub_post );

		$this->assertContains(
			array(
				'type' => 'Mention',
				'href' => $this->actor,
				'name' => '@' . $this->friend_nicename,
			),
			$activitypub_post->get_tags()
		);

		$this->assertContains( \get_rest_url( null, '/activitypub/1.0/users/1/followers' ), $activitypub_activity->get_cc() );
		$this->assertContains( $this->actor, $activitypub_activity->get_cc() );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_all_filters( 'activitypub_cache_possible_friend_mentions' );

		\wp_trash_post( $post );

	}

	public function set_up() {
		if ( ! class_exists( '\Friends\Friends' ) ) {
			return $this->markTestSkipped( 'The Friends plugin is not loaded.' );
		}
		parent::set_up();

		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );

		$user_id = $this->factory->user->create(
			array(
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $user_id );

		$this->friend_name = 'Alex Kirk';
		$this->actor = 'https://mastodon.local/users/akirk';

		$user_feed = \Friends\User_Feed::get_by_url( $this->actor );
		if ( is_wp_error( $user_feed ) ) {
			$this->friend_id = $this->factory->user->create(
				array(
					'user_login' => 'akirk.blog',
					'display_name' => $this->friend_name,
					'nicename' => $this->friend_nicename,
					'role'       => 'friend',
				)
			);
			\Friends\User_Feed::save(
				new \Friends\User( $this->friend_id ),
				$this->actor,
				array(
					'parser' => 'activitypub',
				)
			);
		} else {
			$this->friend_id = $user_feed->get_friend_user()->ID;
		}
		$userdata = get_userdata( $this->friend_id );
		$this->friend_nicename = $userdata->user_nicename;

		self::$users[ $this->actor ] = array(
			'url' => $this->actor,
			'name'  => $this->friend_name,
		);
		self::$users['https://mastodon.local/@akirk'] = self::$users[ $this->actor ];

		_delete_all_posts();
	}

	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
		}
		return $pre;
	}
}
