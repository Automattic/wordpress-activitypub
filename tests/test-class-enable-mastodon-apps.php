<?php
class Test_Enable_Mastodon_Apps extends WP_UnitTestCase {
	public static $users = array(
		'username@example.org' => array(
			'id' => 'https://example.org/users/username',
			'url' => 'https://example.org/users/username',
			'inbox' => 'https://example.org/users/username/inbox',
			'name'  => 'username',
			'preferredUsername'  => 'username',
			'published' => '2024-01-01T00:00:00+00:00',
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

		if ( ! class_exists( '\Enable_Mastodon_Apps\Entity\Entity' ) ) {
			self::markTestSkipped( 'The Enable_Mastodon_Apps plugin is not active.' );
		}
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		add_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
		_delete_all_posts();
	}

	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		remove_filter( 'pre_http_request', array( $this, 'pre_http_request' ), 10, 3 );
		parent::tear_down();
	}

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

	public function test_api_account_followers_internal() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		foreach ( $followers as $follower ) {
			$response = \Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$account = new \Enable_Mastodon_Apps\Entity\Account();
		$this->assertEquals( 0, $account->followers_count );

		$account = apply_filters( 'mastodon_api_account', $account, 1 );
		$this->assertEquals( 3, $account->followers_count );
	}

	public static function pre_http_request( $preempt, $request, $url ) {
		switch( $url ) {
			case 'https://example.org/.well-known/webfinger?resource=acct%3Ausername%40example.org':
			case 'https://example.org/.well-known/webfinger?resource=https%3A%2F%2Fexample.org%2Fusers%2Fusername':
				return array(
					'headers'  => array(
						'content-type' => 'text/json',
					),
					'body'     => json_encode( array(
						'subject' => 'acct:username@example.org',
						'links'   => array(
							array(
								'rel'  => 'self',
								'type' => 'application/activity+json',
								'href' => 'https://example.org/users/username',
							),
						),
					) ),
					'response' => array(
						'code' => 200,
					),
				);

			case 'https://example.org/users/username':
				return array(
					'headers'  => array(
						'content-type' => 'application/activity+json',
					),
					'body'     => json_encode( self::$users['username@example.org'] ),
					'response' => array(
						'code' => 200,
					),
				);
		}
		return $preempt;
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
