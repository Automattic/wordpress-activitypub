<?php
/**
 * Test file for Activitypub Activity Dispatcher.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity_Dispatcher;

/**
 * Test class for Activitypub Activity Dispatcher.
 *
 * @coversDefaultClass \Activitypub\Activity_Dispatcher
 */
class Test_Activity_Dispatcher extends ActivityPub_TestCase_Cache_HTTP {

	/**
	 * Actors.
	 *
	 * @var array[]
	 */
	public static $actors = array(
		'username@example.org' => array(
			'id'                => 'https://example.org/users/username',
			'url'               => 'https://example.org/users/username',
			'inbox'             => 'https://example.org/users/username/inbox',
			'name'              => 'username',
			'preferredUsername' => 'username',
		),
		'jon@example.com'      => array(
			'id'                => 'https://example.com/author/jon',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		),
	);

	/**
	 * Set up the test case.
	 */
	public function set_up() {
		parent::set_up();
		add_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ), 10, 2 );
		_delete_all_posts();
	}

	/**
	 * Tear down the test case.
	 */
	public function tear_down() {
		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_metadata_by_actor' ) );
		parent::tear_down();
	}

	/**
	 * Test dispatch activity.
	 *
	 * @covers ::send_activity
	 */
	public function test_dispatch_activity() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/users/username' );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new \MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		Activity_Dispatcher::send_activity( get_post( $post ), 'Create' );

		$this->assertSame( 2, $pre_http_request->get_call_count() );
		$all_args        = $pre_http_request->get_args();
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

	/**
	 * Test dispatch mentions.
	 *
	 * @covers ::send_activity
	 */
	public function test_dispatch_mentions() {
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => '@alex hello',
			)
		);

		self::$actors['https://example.com/alex'] = array(
			'id'    => 'https://example.com/alex',
			'url'   => 'https://example.com/alex',
			'inbox' => 'https://example.com/alex/inbox',
			'name'  => 'alex',
		);

		add_filter(
			'activitypub_extract_mentions',
			function ( $mentions ) {
				$mentions[] = 'https://example.com/alex';
				return $mentions;
			}
		);

		$pre_http_request = new \MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		Activity_Dispatcher::send_activity( get_post( $post ), 'Create' );

		$this->assertSame( 1, $pre_http_request->get_call_count() );
		$all_args        = $pre_http_request->get_args();
		$first_call_args = $all_args[0];
		$this->assertEquals( 'https://example.com/alex/inbox', $first_call_args[2] );

		$body = json_decode( $first_call_args[1]['body'], true );
		$this->assertArrayHasKey( 'id', $body );

		remove_all_filters( 'activitypub_from_post_object' );
		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	/**
	 * Test dispatch mentions.
	 *
	 * @covers ::send_activity_or_announce
	 */
	public function test_dispatch_announce() {
		add_filter( 'activitypub_is_user_type_disabled', '__return_false' );

		$followers = array( 'https://example.com/author/jon' );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( \Activitypub\Collection\Actors::BLOG_USER_ID, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new \MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		Activity_Dispatcher::send_activity_or_announce( get_post( $post ), 'Create' );

		$all_args        = $pre_http_request->get_args();
		$first_call_args = $all_args[0];

		$this->assertSame( 1, $pre_http_request->get_call_count() );

		$user = new \Activitypub\Model\Blog();

		$json = json_decode( $first_call_args[1]['body'] );
		$this->assertEquals( 'Announce', $json->type );
		$this->assertEquals( $user->get_id(), $json->actor );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	/**
	 * Test dispatch blog activity.
	 *
	 * @covers ::send_activity_or_announce
	 */
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
			\Activitypub\Collection\Followers::add_follower( \Activitypub\Collection\Actors::BLOG_USER_ID, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new \MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		Activity_Dispatcher::send_activity_or_announce( get_post( $post ), 'Create' );

		$all_args        = $pre_http_request->get_args();
		$first_call_args = $all_args[0];

		$this->assertSame( 1, $pre_http_request->get_call_count() );

		$user = new \Activitypub\Model\Blog();

		$json = json_decode( $first_call_args[1]['body'] );
		$this->assertEquals( 'Create', $json->type );
		$this->assertEquals( $user->get_id(), $json->actor );
		$this->assertEquals( $user->get_id(), $json->object->attributedTo );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	/**
	 * Test dispatch fallback activity.
	 *
	 * @covers ::send_activity
	 */
	public function test_dispatch_fallback_activity() {
		$followers = array( 'https://example.com/author/jon' );

		add_filter( 'activitypub_is_user_type_disabled', '__return_false' );

		add_filter(
			'activitypub_is_user_disabled',
			function ( $disabled, $user_id ) {
				if ( 1 === (int) $user_id ) {
					return true;
				}

				return false;
			},
			10,
			2
		);

		$this->assertFalse( \Activitypub\is_single_user() );

		foreach ( $followers as $follower ) {
			\Activitypub\Collection\Followers::add_follower( \Activitypub\Collection\Actors::BLOG_USER_ID, $follower );
			\Activitypub\Collection\Followers::add_follower( 1, $follower );
		}

		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'hello',
			)
		);

		$pre_http_request = new \MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		Activity_Dispatcher::send_activity( get_post( $post ), 'Create' );

		$all_args        = $pre_http_request->get_args();
		$first_call_args = $all_args[0];

		$this->assertSame( 1, $pre_http_request->get_call_count() );

		$user = new \Activitypub\Model\Blog();

		$json = json_decode( $first_call_args[1]['body'] );
		$this->assertEquals( 'Create', $json->type );
		$this->assertEquals( $user->get_id(), $json->actor );
		$this->assertEquals( $user->get_id(), $json->object->attributedTo );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	/**
	 * Filters remote metadata by actor.
	 *
	 * @param array|bool $pre The metadata for the given URL.
	 * @param string     $actor The URL of the actor.
	 * @return array|bool
	 */
	public static function pre_get_remote_metadata_by_actor( $pre, $actor ) {
		if ( isset( self::$actors[ $actor ] ) ) {
			return self::$actors[ $actor ];
		}
		foreach ( self::$actors as $data ) {
			if ( $data['url'] === $actor ) {
				return $data;
			}
		}
		return $pre;
	}

	/**
	 * Filters the arguments used in an HTTP request.
	 *
	 * @param array  $args The arguments for the HTTP request.
	 * @param string $url  The request URL.
	 * @return array
	 */
	public static function http_request_args( $args, $url ) {
		if ( in_array( wp_parse_url( $url, PHP_URL_HOST ), array( 'example.com', 'example.org' ), true ) ) {
			$args['reject_unsafe_urls'] = false;
		}
		return $args;
	}

	/**
	 * Filters the return value of an HTTP request.
	 *
	 * @param bool   $preempt Whether to preempt an HTTP request's return value.
	 * @param array  $request {
	 *      Array of HTTP request arguments.
	 *
	 *      @type string $method Request method.
	 *      @type string $body   Request body.
	 * }
	 * @param string $url The request URL.
	 * @return array Array containing 'headers', 'body', 'response'.
	 */
	public static function pre_http_request( $preempt, $request, $url ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
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

	/**
	 * Filters the return value of an HTTP request.
	 *
	 * @param array  $response Response array.
	 * @param array  $args     Request arguments.
	 * @param string $url      Request URL.
	 * @return array
	 */
	public static function http_response( $response, $args, $url ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		return $response;
	}
}
