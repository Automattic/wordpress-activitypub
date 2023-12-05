<?php
class Test_Activitypub_Followers extends WP_UnitTestCase {
	public static $users = array(
		'username@example.org' => array(
			'id' => 'https://example.org/users/username',
			'url' => 'https://example.org/users/username',
			'inbox' => 'https://example.org/users/username/inbox',
			'name'  => 'username',
			'preferredUsername'  => 'username',
		),
		'jon@example.com' => array(
			'id' => 'https://example.com/author/jon',
			'url' => 'https://example.com/author/jon',
			'inbox' => 'https://example.com/author/jon/inbox',
			'name'  => 'jon',
			'preferredUsername'  => 'jon',
		),
		'doe@example.org' => array(
			'id' => 'https://example.org/author/doe',
			'url' => 'https://example.org/author/doe',
			'inbox' => 'https://example.org/author/doe/inbox',
			'name'  => 'doe',
			'preferredUsername'  => 'doe',
		),
		'sally@example.org' => array(
			'id' => 'http://sally.example.org',
			'url' => 'http://sally.example.org',
			'inbox' => 'http://sally.example.org/inbox',
			'name'  => 'jon',
			'preferredUsername'  => 'jon',
		),
		'12345@example.com' => array(
			'id' => 'https://12345.example.com',
			'url' => 'https://12345.example.com',
			'inbox' => 'https://12345.example.com/inbox',
			'name'  => '12345',
			'preferredUsername'  => '12345',
		),
		'user2@example.com' => array(
			'id' => 'https://user2.example.com',
			'url' => 'https://user2.example.com',
			'inbox' => 'https://user2.example.com/inbox',
			'name'  => 'úser2',
			'preferredUsername'  => 'user2',
		),
		'error@example.com' => array(
			'url' => 'https://error.example.com',
			'name'  => 'error',
			'preferredUsername'  => 'error',
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

	public function test_add_follower_error() {
		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$follower = 'error@example.com';

		$result = \Activitypub\Collection\Followers::add_follower( 1, $follower );

		$this->assertTrue( is_wp_error( $result ) );

		$follower2 = 'https://error.example.com';

		$result = \Activitypub\Collection\Followers::add_follower( 1, $follower2 );

		$this->assertTrue( is_wp_error( $result ) );

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertEmpty( $db_followers );
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
		$this->assertEquals( 'úser2', $follower2->get_name() );
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
			add_post_meta( $follower->get__id(), 'activitypub_errors', 'error ' . $i );
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

	public function test_add_duplicate_follower() {
		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$follower = 'https://12345.example.com';

		\Activitypub\Collection\Followers::add_follower( 1, $follower );
		\Activitypub\Collection\Followers::add_follower( 1, $follower );
		\Activitypub\Collection\Followers::add_follower( 1, $follower );
		\Activitypub\Collection\Followers::add_follower( 1, $follower );
		\Activitypub\Collection\Followers::add_follower( 1, $follower );
		\Activitypub\Collection\Followers::add_follower( 1, $follower );

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertContains( $follower, $db_followers );

		$follower = current( $db_followers );
		$meta     = get_post_meta( $follower->get__id(), 'activitypub_user_id' );

		$this->assertCount( 1, $meta );
	}

	public function test_migration() {
		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$followers = array(
			'https://example.com/author/jon',
			'https://example.og/errors',
			'https://example.org/author/doe',
			'http://sally.example.org',
			'https://error.example.com',
			'https://example.net/error',
		);

		$user_id = 1;

		add_user_meta( $user_id, 'activitypub_followers', $followers, true );

		\Activitypub\Migration::maybe_migrate();

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertCount( 3, $db_followers );
	}

	/**
	 * @dataProvider extract_name_from_uri_content_provider
	 */
	public function test_extract_name_from_uri( $uri, $name ) {
		$follower = new \Activitypub\Model\Follower();

		$follower->set_id( $uri );

		$this->assertEquals( $name, $follower->get_name() );
	}

	public function test_get_inboxes() {
		for ( $i = 0; $i < 30; $i++ ) {
			$meta = array(
				'id' => 'https://example.org/users/' . $i,
				'url' => 'https://example.org/users/' . $i,
				'inbox' => 'https://example.org/users/' . $i . '/inbox',
				'name'  => 'user' . $i,
				'preferredUsername'  => 'user' . $i,
				'publicKey' => 'https://example.org/users/' . $i . '#main-key',
				'publicKeyPem' => $i,
			);

			$follower = new \Activitypub\Model\Follower();
			$follower->from_array( $meta );

			$id = $follower->upsert();

			add_post_meta( $id, 'activitypub_user_id', 1 );
		}

		$inboxes = \Activitypub\Collection\Followers::get_inboxes( 1 );

		$this->assertCount( 30, $inboxes );

		wp_cache_delete( sprintf( \Activitypub\Collection\Followers::CACHE_KEY_INBOXES, 1 ), 'activitypub' );

		for ( $j = 0; $j < 5; $j++ ) {
			$k = $j + 100;
			$meta = array(
				'id' => 'https://example.org/users/' . $k,
				'url' => 'https://example.org/users/' . $k,
				'inbox' => 'https://example.org/users/' . $j . '/inbox',
				'name'  => 'user' . $k,
				'preferredUsername'  => 'user' . $k,
				'publicKey' => 'https://example.org/users/' . $k . '#main-key',
				'publicKeyPem' => $k,
			);

			$follower = new \Activitypub\Model\Follower();
			$follower->from_array( $meta );

			$id = $follower->upsert();

			add_post_meta( $id, 'activitypub_user_id', 1 );
		}

		$inboxes2 = \Activitypub\Collection\Followers::get_inboxes( 1 );

		$this->assertCount( 30, $inboxes2 );
	}

	public function test_get_all_followers() {
		for ( $i = 0; $i < 30; $i++ ) {
			$meta = array(
				'id' => 'https://example.org/users/' . $i,
				'url' => 'https://example.org/users/' . $i,
				'inbox' => 'https://example.org/users/' . $i . '/inbox',
				'name'  => 'user' . $i,
				'preferredUsername'  => 'user' . $i,
				'publicKey' => 'https://example.org/users/' . $i . '#main-key',
				'publicKeyPem' => $i,
			);

			$follower = new \Activitypub\Model\Follower();
			$follower->from_array( $meta );

			$id = $follower->upsert();

			add_post_meta( $id, 'activitypub_user_id', 1 );
		}

		$followers = \Activitypub\Collection\Followers::get_all_followers();

		$this->assertCount( 30, $followers );
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
}
