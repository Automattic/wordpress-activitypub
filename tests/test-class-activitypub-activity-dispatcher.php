<?php
class Test_Activitypub_Activity_Dispatcher extends ActivityPub_TestCase_Cache_HTTP {
	public static $users = array(
		'username@example.org' => array(
			'id' => 'https://example.org/users/username',
			'url' => 'https://example.org/users/username',
			'inbox' => 'https://example.org/users/username/inbox',
			'name'  => 'username',
			'prefferedUsername'  => 'username',
		),
		'jon@example.com' => array(
			'id' => 'https://example.com/author/jon',
			'url' => 'https://example.com/author/jon',
			'inbox' => 'https://example.com/author/jon/inbox',
			'name'  => 'jon',
			'prefferedUsername'  => 'jon',
		),
	);

	public function test_dispatch_activity() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/users/username' );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		\Activitypub\Activity_Dispatcher::send_activity( get_post( $post ), 'Create' );

		$this->assertSame( 2, $pre_http_request->get_call_count() );
		$all_args = $pre_http_request->get_args();
		$first_call_args = array_shift( $all_args );

		$this->assertEquals( 'https://example.com/author/jon/inbox', $first_call_args[2] );

		$second_call_args = array_shift( $all_args );
		$this->assertEquals( 'https://example.org/users/username/inbox', $second_call_args[2] );

		$json = json_decode( $second_call_args[1]['body'] );
		$this->assertEquals( 'Create', $json->type );
		$this->assertEquals( 'http://example.org/?author=1', $json->actor );
		$this->assertEquals( 'http://example.org/?author=1', $json->object->attributedTo );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public function test_dispatch_mentions() {
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => '@alex hello',
			)
		);

		self::$users['https://example.com/alex'] = array(
			'id' => 'https://example.com/alex',
			'url' => 'https://example.com/alex',
			'inbox' => 'https://example.com/alex/inbox',
			'name'  => 'alex',
		);

		add_filter(
			'activitypub_extract_mentions',
			function ( $mentions ) {
				$mentions[] = 'https://example.com/alex';
				return $mentions;
			},
			10
		);

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		\Activitypub\Activity_Dispatcher::send_activity( get_post( $post ), 'Create' );

		$this->assertSame( 1, $pre_http_request->get_call_count() );
		$all_args = $pre_http_request->get_args();
		$first_call_args = $all_args[0];
		$this->assertEquals( 'https://example.com/alex/inbox', $first_call_args[2] );

		$body = json_decode( $first_call_args[1]['body'], true );
		$this->assertArrayHasKey( 'id', $body );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public function test_dispatch_announce() {
		add_filter( 'activitypub_is_user_type_disabled', '__return_false' );

		$followers = array( 'https://example.com/author/jon' );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( \Activitypub\Collection\Users::BLOG_USER_ID, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		\Activitypub\Activity_Dispatcher::send_activity_or_announce( get_post( $post ), 'Create' );

		$all_args = $pre_http_request->get_args();
		$first_call_args = $all_args[0];

		$this->assertSame( 1, $pre_http_request->get_call_count() );

		$user = new \Activitypub\Model\Blog();

		$json = json_decode( $first_call_args[1]['body'] );
		$this->assertEquals( 'Announce', $json->type );
		$this->assertEquals( $user->get_url(), $json->actor );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public function test_dispatch_blog_activity() {
		$followers = array( 'https://example.com/author/jon' );

		add_filter(
			'activitypub_is_user_type_disabled',
			function ( $value, $type ) {
				if ( 'blog' === $type ) {
					return false;
				} else {
					return true;
				}
			},
			10,
			2
		);

		$this->assertTrue( \Activitypub\is_single_user() );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( \Activitypub\Collection\Users::BLOG_USER_ID, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		\Activitypub\Activity_Dispatcher::send_activity_or_announce( get_post( $post ), 'Create' );

		$all_args = $pre_http_request->get_args();
		$first_call_args = $all_args[0];

		$this->assertSame( 1, $pre_http_request->get_call_count() );

		$user = new \Activitypub\Model\Blog();

		$json = json_decode( $first_call_args[1]['body'] );
		$this->assertEquals( 'Create', $json->type );
		$this->assertEquals( $user->get_url(), $json->actor );
		$this->assertEquals( $user->get_url(), $json->object->attributedTo );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
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
		foreach ( self::$users as $username => $data ) {
			if ( $data['url'] === $actor ) {
				return $data;
			}
		}
		return $pre;
	}

	public static function http_request_host_is_external( $in, $host ) {
		if ( in_array( $host, array( 'example.com', 'example.org' ), true ) ) {
			return true;
		}
		return $in;
	}
	public static function http_request_args( $args, $url ) {
		if ( in_array( wp_parse_url( $url, PHP_URL_HOST ), array( 'example.com', 'example.org' ), true ) ) {
			$args['reject_unsafe_urls'] = false;
		}
		return $args;
	}

	public static function pre_http_request( $preempt, $request, $url ) {
		return array(
			'headers'  => array(
				'content-type' => 'text/json',
			),
			'body'     => '',
			'response' => array(
				'code' => 202,
			),
		);
	}

	public static function http_response( $response, $args, $url ) {
		return $response;
	}
}
