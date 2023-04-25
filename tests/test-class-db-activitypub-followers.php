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
		'sally@example.org' => array(
			'url' => 'http://sally.example.org',
			'inbox' => 'http://sally.example.org/inbox',
			'name'  => 'jon',
			'prefferedUsername'  => 'jon',
		),
	);

	public function test_get_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' );

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertEquals( 3, \count( $db_followers ) );

		$this->assertSame( array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' ), $db_followers );
	}

	public function test_add_follower() {
		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$follower = 'https://example.com/author/' . \time();
		\Activitypub\Collection\Followers::add_follower( 1, $follower );

		$db_followers = \Activitypub\Collection\Followers::get_followers( 1 );

		$this->assertContains( $follower, $db_followers );
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
