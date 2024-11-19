<?php
/**
 * Test file for Activitypub Rest Inbox.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Rest Inbox.
 *
 * @coversDefaultClass \Activitypub\Rest\Inbox
 */
class Test_Activitypub_Rest_Inbox extends WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		\add_option( 'permalink_structure', '/%postname%/' );

		\Activitypub\Rest\Server::add_hooks();
	}

	/**
	 * Tear down the test.
	 */
	public function tear_down() {
		\delete_option( 'permalink_structure' );
		\add_filter( 'activitypub_defer_signature_verification', '__return_false' );
	}

	/**
	 * Test the inbox signature issue.
	 */
	public function test_inbox_signature_issue() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_false' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );

		$this->assertEquals( 401, $response->get_status() );
		$this->assertEquals( 'activitypub_signature_verification', $response->get_data()['code'] );
	}

	/**
	 * Test missing attribute.
	 */
	public function test_missing_attribute() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'    => 'https://remote.example/@id',
			'type'  => 'Follow',
			'actor' => 'https://remote.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		$response = \rest_do_request( $request );

		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_missing_callback_param', $response->get_data()['code'] );
		$this->assertEquals( 'object', $response->get_data()['data']['params'][0] );
	}

	/**
	 * Test follow request.
	 */
	public function test_follow_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test follow request global inbox.
	 */
	public function test_follow_request_global_inbox() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Follow',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test create request with a remote actor.
	 */
	public function test_create_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Invalid request, because of an invalid object.
		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		// Valid request, because of a valid object.
		$json['object'] = array(
			'id'        => 'https://remote.example/post/test',
			'type'      => 'Note',
			'content'   => 'Hello, World!',
			'inReplyTo' => 'https://local.example/post/test',
			'published' => '2020-01-01T00:00:00Z',
		);
		$request        = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test create request global inbox.
	 */
	public function test_create_request_global_inbox() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		// Invalid request, because of an invalid object.
		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Create',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/@test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 400, $response->get_status() );
		$this->assertEquals( 'rest_invalid_param', $response->get_data()['code'] );

		// Valid request, because of a valid object.
		$json['object'] = array(
			'id'        => 'https://remote.example/post/test',
			'type'      => 'Note',
			'content'   => 'Hello, World!',
			'inReplyTo' => 'https://local.example/post/test',
			'published' => '2020-01-01T00:00:00Z',
		);
		$request        = new \WP_REST_Request( 'POST', '/activitypub/1.0/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test update request.
	 */
	public function test_update_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Update',
			'actor'  => 'https://remote.example/@test',
			'object' => array(
				'id'        => 'https://remote.example/post/test',
				'type'      => 'Note',
				'content'   => 'Hello, World!',
				'inReplyTo' => 'https://local.example/post/test',
				'published' => '2020-01-01T00:00:00Z',
			),
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test like request.
	 */
	public function test_like_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Like',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/post/test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test announce request.
	 */
	public function test_announce_request() {
		\add_filter( 'activitypub_defer_signature_verification', '__return_true' );

		$json = array(
			'id'     => 'https://remote.example/@id',
			'type'   => 'Announce',
			'actor'  => 'https://remote.example/@test',
			'object' => 'https://local.example/post/test',
		);

		$request = new \WP_REST_Request( 'POST', '/activitypub/1.0/users/1/inbox' );
		$request->set_header( 'Content-Type', 'application/activity+json' );
		$request->set_body( \wp_json_encode( $json ) );

		// Dispatch the request.
		$response = \rest_do_request( $request );
		$this->assertEquals( 202, $response->get_status() );
	}

	/**
	 * Test whether an activity is public.
	 *
	 * @dataProvider the_data_provider
	 *
	 * @param array $data  The data.
	 * @param bool  $check The check.
	 */
	public function test_is_activity_public( $data, $check ) {
		$this->assertEquals( $check, Activitypub\is_activity_public( $data ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array[]
	 */
	public function the_data_provider() {
		return array(
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to'     => 'https://www.w3.org/ns/activitystreams#Public',
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'to'     => array(
						'https://www.w3.org/ns/activitystreams#Public',
					),
					'object' => array(),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(),
				),
				false,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => 'https://www.w3.org/ns/activitystreams#Public',
					),
				),
				true,
			),
			array(
				array(
					'cc'     => array(
						'https://example.org/@test',
						'https://example.com/@test2',
					),
					'object' => array(
						'to' => array(
							'https://www.w3.org/ns/activitystreams#Public',
						),
					),
				),
				true,
			),
		);
	}
}
