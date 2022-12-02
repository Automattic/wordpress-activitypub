<?php

class Test_Friends_Feed_Parser_ActivityPub extends \WP_UnitTestCase {
	public static $users = array();

	public function set_up() {
		if ( ! class_exists( '\Friends\Friends' ) ) {
			return $this->markTestSkipped( 'The Friends plugin is not loaded.' );
		}
		parent::set_up();

		// Manually activate the REST server.
		global $wp_rest_server;
		$wp_rest_server = new \Spy_REST_Server();
		$this->server   = $wp_rest_server;
		do_action( 'rest_api_init' );

		add_filter(
			'rest_url',
			function() {
				return get_option( 'home' ) . '/wp-json/';
			}
		);

		add_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ), 10, 3 );
		add_filter( 'http_request_host_is_external', array( get_called_class(), 'http_request_host_is_external' ), 10, 2 );
		add_filter( 'http_request_args', array( get_called_class(), 'http_request_args' ), 10, 2 );
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );

	}

	public function tear_down() {
		remove_filter( 'pre_http_request', array( get_called_class(), 'pre_http_request' ) );
		remove_filter( 'http_request_host_is_external', array( get_called_class(), 'http_request_host_is_external' ) );
		remove_filter( 'http_request_args', array( get_called_class(), 'http_request_args' ) );
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
	}

	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		if ( isset( self::$users[ $actor ] ) ) {
			return self::$users[ $actor ];
		}
		return $pre;
	}
	public static function http_request_host_is_external( $in, $host ) {
		if ( in_array( $host, array( 'mastodon.local' ), true ) ) {
			return true;
		}
		return $in;
	}
	public static function http_request_args( $args, $url ) {
		if ( in_array( parse_url( $url, PHP_URL_HOST ), array( 'mastodon.local' ), true ) ) {
			$args['reject_unsafe_urls'] = false;
		}
		return $args;
	}
	public static function pre_http_request( $preempt, $request, $url ) {
		$home_url = home_url();

		// Pretend the url now is the requested one.
		update_option( 'home', $p['scheme'] . '://' . $p['host'] );
		$rest_prefix = home_url() . '/wp-json';

		if ( false === strpos( $url, $rest_prefix ) ) {
			// Restore the old home_url.
			update_option( 'home', $home_url );
			return $preempt;
		}

		$url = substr( $url, strlen( $rest_prefix ) );
		$r   = new \WP_REST_Request( $request['method'], $url );
		if ( ! empty( $request['body'] ) ) {
			foreach ( $request['body'] as $key => $value ) {
				$r->set_param( $key, $value );
			}
		}
		global $wp_rest_server;
		$response = $wp_rest_server->dispatch( $r );
		// Restore the old url.
		update_option( 'home', $home_url );

		return apply_filters(
			'fake_http_response',
			array(
				'headers'  => array(
					'content-type' => 'text/json',
				),
				'body'     => wp_json_encode( $response->data ),
				'response' => array(
					'code' => $response->status,
				),
			),
			$p['scheme'] . '://' . $p['host'],
			$url,
			$request
		);
	}

	public function test_incoming_post() {
		$now = time() - 10;
		$status_id = 123;

		$friend_name = 'Alex';
		$actor = 'https://mastodon.local/users/alex';

		$friend_id = $this->factory->user->create(
			array(
				'user_login' => 'alex-mastodon.local',
				'display_name' => $friend_name,
				'role'       => 'friend',
			)
		);
		\Friends\User_Feed::save(
			new \Friends\User( $friend_id ),
			$actor,
			array(
				'parser' => 'activitypub',
			)
		);

		self::$users[ $actor ] = array(
			'url' => $actor,
			'name'  => $friend_name,
		);
		self::$users['https://mastodon.local/@alex'] = self::$users[ $actor ];

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $friend_id,
			)
		);

		$this->assertEquals( 0, count( $posts ) );

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_param( 'type', 'Create' );
		$request->set_param( 'id', 'test1' );
		$request->set_param( 'actor', $actor );
		$date = date( \DATE_W3C, $now++ );
		$content = 'Test ' . $date . ' ' . rand();
		$request->set_param(
			'object',
			array(
				'type' => 'Note',
				'id' => 'test1',
				'attributedTo' => $actor,
				'content' => $content,
				'url' => 'https://mastodon.local/users/alex/statuses/' . ( $status_id++ ),
				'published' => $date,
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $friend_id,
			)
		);

		$this->assertEquals( 1, count( $posts ) );
		$this->assertEquals( $content, $posts[0]->post_content );
		$this->assertEquals( $friend_id, $posts[0]->post_author );

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/' . get_current_user_id() . '/inbox' );
		$request->set_param( 'type', 'Create' );
		$request->set_param( 'id', 'test1' );
		$request->set_param( 'actor', 'https://mastodon.local/@alex' );
		$date = date( \DATE_W3C, $now++ );
		$content = 'Test ' . $date . ' ' . rand();
		$request->set_param(
			'object',
			array(
				'type' => 'Note',
				'id' => 'test2',
				'attributedTo' => 'https://mastodon.local/@alex',
				'content' => $content,
				'url' => 'https://mastodon.local/users/alex/statuses/' . ( $status_id++ ),
				'published' => $date,
			)
		);

		$response = $this->server->dispatch( $request );
		$this->assertEquals( 202, $response->get_status() );

		$posts = get_posts(
			array(
				'post_type' => \Friends\Friends::CPT,
				'author' => $friend_id,
			)
		);

		$this->assertEquals( 2, count( $posts ) );
		$this->assertEquals( $content, $posts[0]->post_content );
		$this->assertEquals( $friend_id, $posts[0]->post_author );
	}
}
