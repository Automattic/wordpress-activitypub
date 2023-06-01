<?php

class Test_Activitypub_Signature_Verification extends WP_UnitTestCase {
	public $server;
	public function setUp() : void {
		parent::setUp();

		/**
		 * Global $wp_rest_server variable
		 *
		 * @var WP_REST_Server $wp_rest_server Mock REST server.
		 */
		global $wp_rest_server;
		add_filter( 'pre_get_remote_key', array( get_called_class(), 'pre_get_remote_key' ), 10, 2 );
		$wp_rest_server = new \WP_REST_Server();
		$this->server   = $wp_rest_server;

		do_action( 'rest_api_init' );

	}

	/**
	 * Tear down after test ends
	 */
	public function tearDown() : void {
		remove_filter( 'pre_get_remote_key', array( get_called_class(), 'pre_get_remote_key' ) );
		parent::tearDown();

		global $wp_rest_server;
		$wp_rest_server = null;

	}

	public function test_activity_signature() {

		$pre_http_request = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );

		// Activity for generate_digest
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'hello world',
			)
		);
		$remote_actor = \get_author_posts_url( 2 );
		$activitypub_post = new \Activitypub\Model\Post( $post );
		$activitypub_activity = new Activitypub\Model\Activity( 'Create' );
		$activitypub_activity->from_post( $activitypub_post );
		$activitypub_activity->add_cc( $remote_actor );
		$activity = $activitypub_activity->to_json();

		// generate_digest & generate_signature
		$digest = Activitypub\Signature::generate_digest( $activity );
		$date = gmdate( 'D, d M Y H:i:s T' );
		$signature = Activitypub\Signature::generate_signature( 1, 'POST', $remote_actor, $date, $digest );

		$this->assertRegExp( '/keyId="http:\/\/example\.org\/\?author=1#main-key",algorithm="rsa-sha256",headers="\(request-target\) host date digest",signature="[^"]*"/', $signature );

		//  Signed headers
		$url_parts = wp_parse_url( $remote_actor );
		$route = $url_parts['path'] . '?' . $url_parts['query'];
		$host = $url_parts['host'];

		$headers = array(
			'digest' => [ "SHA-256=$digest" ],
			'signature' => [ $signature ],
			'date' => [ $date ],
			'host' => [ $host ],
			'(request-target)' =>  [ 'POST ' . $route ]
		);

		// Start verification
		// parse_signature_header, get_signed_data, get_public_key
		$signature_block = Activitypub\Signature::parse_signature_header( $headers['signature'] );
		$signed_headers = $signature_block['headers'];
		$signed_data = Activitypub\Signature::get_signed_data( $signed_headers, $signature_block, $headers );
		$public_key = Activitypub\Signature::get_public_key( 1 );

		// signature_verification
		$verified = \openssl_verify( $signed_data, $signature_block['signature'], $public_key, 'rsa-sha256' ) > 0;
		$this->assertTrue( $verified );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public function test_rest_activity_signature() {

		$pre_http_request = new MockAction();
		// $pre_get_remote_key = new MockAction();
		add_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10, 3 );
		add_filter( 'pre_get_remote_key', array( get_called_class(), 'pre_get_remote_key' ), 10, 2 );

		// Activity Object
		$post = \wp_insert_post(
			array(
				'post_author' => 1,
				'post_content' => 'hello world',
			)
		);
		$remote_actor = \get_author_posts_url( 2 );
		$remote_actor_inbox = \get_rest_url( null, 'activitypub/1.0/inbox' );
		$activitypub_post = new \Activitypub\Model\Post( $post );
		$activitypub_activity = new Activitypub\Model\Activity( 'Create' );
		$activitypub_activity->from_post( $activitypub_post );
		$activitypub_activity->add_cc( $remote_actor_inbox );
		$activity = $activitypub_activity->to_json();

		// generate_digest & generate_signature
		$digest = Activitypub\Signature::generate_digest( $activity );
		$date = gmdate( 'D, d M Y H:i:s T' );
		$signature = Activitypub\Signature::generate_signature( 1, 'POST', $remote_actor, $date, $digest );

		//  Signed headers
		$url_parts = wp_parse_url( $remote_actor );
		$route = add_query_arg( $url_parts['query'], $url_parts['path'] );
		$host = $url_parts['host'];

		$request  = new WP_REST_Request( 'POST', ACTIVITYPUB_REST_NAMESPACE . '/inbox' );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'digest', "SHA-256=$digest" );
		$request->set_header( 'signature', $signature );
		$request->set_header( 'date', $date );
		$request->set_header( 'host', $host );
		$request->set_body( $activity );

		// Start verification
		$verified = \Activitypub\Signature::verify_http_signature( $request );
		// $this->assertTRUE( $verified );

		remove_filter( 'pre_get_remote_key', array( get_called_class(), 'pre_get_remote_key' ) );
		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}

	public static function pre_get_remote_key( $pre, $key_id ) {
		$query = wp_parse_url( $key_id, PHP_URL_QUERY );
		parse_str( $query, $output );
		if ( is_int( $output['author'] ) ) {
			return ActivityPub\Signature::get_public_key( int( $output['author'] ) );
		}
		return $pre;
	}

}
