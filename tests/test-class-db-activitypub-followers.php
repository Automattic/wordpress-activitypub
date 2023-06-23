<?php
class Test_Db_Activitypub_Followers extends WP_UnitTestCase {
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
		'doe@example.org' => array(
			'id' => 'https://example.org/author/doe',
			'url' => 'https://example.org/author/doe',
			'inbox' => 'https://example.org/author/doe/inbox',
			'name'  => 'doe',
			'prefferedUsername'  => 'doe',
		),
		'sally@example.org' => array(
			'id' => 'http://sally.example.org',
			'url' => 'http://sally.example.org',
			'inbox' => 'http://sally.example.org/inbox',
			'name'  => 'jon',
			'prefferedUsername'  => 'jon',
		),
		'12345@example.com' => array(
			'id' => 'https://12345.example.com',
			'url' => 'https://12345.example.com',
			'inbox' => 'https://12345.example.com/inbox',
			'name'  => '12345',
			'prefferedUsername'  => '12345',
		),
		'user2@example.com' => array(
			'id' => 'https://user2.example.com',
			'url' => 'https://user2.example.com',
			'inbox' => 'https://user2.example.com/inbox',
			'name'  => 'user2',
			'prefferedUsername'  => 'user2',
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

		$db_followers = array_map(
			function( $item ) {
				return $item->get_url();
			},
			$db_followers
		);

		$this->assertEquals( array( 'http://sally.example.org', 'https://example.org/author/doe', 'https://example.com/author/jon' ), $db_followers );
	}

	public function test_add_follower() {
		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$follower = 'https://12345.example.com';
		$follower2 = 'https://user2.example.com';
		\Activitypub\Collection\Followers::add_follower( 1, $follower );
		\Activitypub\Collection\Followers::add_follower( 2, $follower );
		\Activitypub\Collection\Followers::add_follower( 2, $follower2 );

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );
		$db_followers2 = \Activitypub\Collection\Followers::get_followers( 2 );

		$this->assertContains( $follower, $db_followers );
		$this->assertContains( $follower2, $db_followers2 );
	}

	public function test_get_follower() {
		$followers = array( 'https://example.com/author/jon' );
		$followers2 = array( 'https://user2.example.com' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		foreach ( $followers2 as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 2, $follower );
		}

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_url() );

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'http://sally.example.org' );
		$this->assertNull( $follower );

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://user2.example.com' );
		$this->assertNull( $follower );

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_url() );

		$follower2 = \Activitypub\Collection\Followers::get_follower( 2, 'https://user2.example.com' );
		$this->assertEquals( 'https://user2.example.com', $follower2->get_url() );
	}

	public function test_delete_follower() {
		$followers = array(
			'https://example.com/author/jon',
			'https://example.org/author/doe',
		);
		$followers2 = array( 'https://user2.example.com' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
			\Activitypub\Collection\Followers::add_follower( 2, $follower );
		}

		foreach ( $followers2 as $follower2 ) {
			\Activitypub\Collection\Followers::add_follower( 2, $follower2 );
		}

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower->get_url() );

		$followers = \Activitypub\Collection\Followers::get_followers( 1 );
		$this->assertEquals( 2, count( $followers ) );

		$follower2 = \Activitypub\Collection\Followers::get_follower( 2, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower2->get_url() );

		\Activitypub\Collection\Followers::remove_follower( 1, 'https://example.com/author/jon' );

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://example.com/author/jon' );
		$this->assertNull( $follower );

		$follower2 = \Activitypub\Collection\Followers::get_follower( 2, 'https://example.com/author/jon' );
		$this->assertEquals( 'https://example.com/author/jon', $follower2->get_url() );

		$followers = \Activitypub\Collection\Followers::get_followers( 1 );
		$this->assertEquals( 1, count( $followers ) );
	}

	public function test_get_outdated_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'https://example.com/author/jon' );

		global $wpdb;

		//eg. time one year ago..
		$time = time() - 804800;
		$mysql_time_format = 'Y-m-d H:i:s';

		$post_modified = gmdate( $mysql_time_format, $time );
		$post_modified_gmt = gmdate( $mysql_time_format, ( $time + get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
		$post_id = $follower->get__id();

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE $wpdb->posts SET post_modified = %s, post_modified_gmt = %s WHERE ID = %s",
				array(
					$post_modified,
					$post_modified_gmt,
					$post_id,
				)
			)
		);

		clean_post_cache( $post_id );

		$followers = \Activitypub\Collection\Followers::get_outdated_followers();
		$this->assertEquals( 1, count( $followers ) );
		$this->assertEquals( 'https://example.com/author/jon', $followers[0] );
	}

	public function test_get_faulty_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'http://sally.example.org' );

		for ( $i = 1; $i <= 15; $i++ ) {
			add_post_meta( $follower->get__id(), 'errors', 'error ' . $i );
		}

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'http://sally.example.org' );
		$count = $follower->count_errors();

		$followers = \Activitypub\Collection\Followers::get_faulty_followers();

		$this->assertEquals( 1, count( $followers ) );
		$this->assertEquals( 'http://sally.example.org', $followers[0] );

		$follower->reset_errors();

		$follower = \Activitypub\Collection\Followers::get_follower( 1, 'http://sally.example.org' );
		$count = $follower->count_errors();

		$followers = \Activitypub\Collection\Followers::get_faulty_followers();

		$this->assertEquals( 0, count( $followers ) );
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
