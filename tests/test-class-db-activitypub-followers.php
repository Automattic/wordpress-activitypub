<?php
class Test_Db_Activitypub_Followers extends WP_UnitTestCase {
	public static $users = array(
		'username@example.org' => array(
			'url' => 'https://example.org/users/username',
			'inbox' => 'https://example.org/users/username/inbox',
			'name'  => 'username',
			'prefferedUsername'  => 'username',
		),
		'jon@example.com' => array(
			'url' => 'https://example.com/author/jon',
			'inbox' => 'https://example.com/author/jon/inbox',
			'name'  => 'jon',
			'prefferedUsername'  => 'jon',
		),
		'doe@example.org' => array(
			'url' => 'https://example.org/author/doe',
			'inbox' => 'https://example.org/author/doe/inbox',
			'name'  => 'doe',
			'prefferedUsername'  => 'doe',
		),
		'sally@example.org' => array(
			'url' => 'http://sally.example.org',
			'inbox' => 'http://sally.example.org/inbox',
			'name'  => 'jon',
			'prefferedUsername'  => 'jon',
		),
		'12345@example.com' => array(
			'url' => 'https://12345.example.com',
			'inbox' => 'https://12345.example.com/inbox',
			'name'  => '12345',
			'prefferedUsername'  => '12345',
		),
	);

	public function set_up() {
		parent::set_up();
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		_delete_all_posts();
	}

	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	public function test_get_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			$response = \Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertEquals( 3, \count( $db_followers ) );

		$this->assertSame( array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' ), $db_followers );
	}

	public function test_add_follower() {
		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$follower = 'https://12345.example.com';
		\Activitypub\Collection\Followers::add_follower( 1, $follower );

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertContains( $follower, $db_followers );
	}

	public function test_get_follower() {
		$followers = array( 'https://example.com/author/jon' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_actor() );

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'http://sally.example.org' );
		$this->assertNull( $follower );
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
}
