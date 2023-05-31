<?php

class Test_Activitypub_Rest_Post_Signature_Verification extends WP_UnitTestCase {

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
		$this->assertTRUE( $verified );

		remove_filter( 'pre_http_request', array( $pre_http_request, 'filter' ), 10 );
	}


}
