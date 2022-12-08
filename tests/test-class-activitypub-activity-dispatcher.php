<?php
class Test_Activitypub_Activity_Dispatcher extends WP_UnitTestCase {
	public static $users = array();
	public function test_dispatch_mentions() {
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => '@alex hello',
			)
		);

		self::$users['https://example.com/alex'] = array(
			'url' => 'https://example.com/alex',
			'inbox' => 'https://example.com/alex/inbox',
			'name'  => 'alex',
		);

		add_filter(
			'activitypub_extract_mentions',
			function( $mentions ) {
				$mentions[] = 'https://example.com/alex';
				return $mentions;
			},
			10
		);

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		$activitypub_post = new \Activitypub\Model\Post( $post );
		\Activitypub\Activity_Dispatcher::send_post_activity( $activitypub_post );

		$this->assertSame( 1, $pre_http_request->get_call_count() );
		$all_args = $pre_http_request->get_args();
		$first_call_args = $all_args[0];
		$this->assertEquals( 'https://example.com/alex/inbox', $first_call_args[2] );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public function set_up() {
		parent::set_up();

		add_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ), 10, 3 );
		add_filter( 'http_response', array( get_called_class(), 'http_response' ), 10, 3 );
		add_filter( 'http_request_host_is_external', array( get_called_class(), 'http_request_host_is_external' ), 10, 2 );
		add_filter( 'http_request_args', array( get_called_class(), 'http_request_args' ), 10, 2 );
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );

		_delete_all_posts();
	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ) );
		remove_filter( 'http_response', array( get_called_class(), 'http_response' ) );
		remove_filter( 'http_request_host_is_external', array( get_called_class(), 'http_request_host_is_external' ) );
		remove_filter( 'http_request_args', array( get_called_class(), 'http_request_args' ) );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
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
		if ( in_array( parse_url( $url, PHP_URL_HOST ), array( 'example.com', 'example.org' ), true ) ) {
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
