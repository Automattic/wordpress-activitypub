<?php
/**
 * Test file for Activitypub Signature Verification.
 *
 * @package Activitypub
 */

/**
 * Test class for Activitypub Signature Verification.
 *
 * @coversDefaultClass \Activitypub\Signature
 */
class Test_Activitypub_Signature_Verification extends WP_UnitTestCase {

	/**
	 * Test activity signature.
	 *
	 * @covers ::generate_digest
	 * @covers ::generate_signature
	 * @covers ::parse_signature_header
	 * @covers ::get_signed_data
	 * @covers ::get_public_key_for
	 * @covers ::verify_http_signature
	 */
	public function test_activity_signature() {
		// Activity for generate_digest.
		$post                 = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'hello world',
			)
		);
		$remote_actor         = \get_author_posts_url( 2 );
		$activitypub_post     = \Activitypub\Transformer\Post::transform( get_post( $post ) )->to_object();
		$activitypub_activity = new Activitypub\Activity\Activity( 'Create' );
		$activitypub_activity->set_type( 'Create' );
		$activitypub_activity->set_object( $activitypub_post );
		$activitypub_activity->add_cc( $remote_actor );
		$activity = $activitypub_activity->to_json();

		// Generate_digest & generate_signature.
		$digest    = Activitypub\Signature::generate_digest( $activity );
		$date      = gmdate( 'D, d M Y H:i:s T' );
		$signature = Activitypub\Signature::generate_signature( 1, 'POST', $remote_actor, $date, $digest );

		$this->assertRegExp( '/keyId="http:\/\/example\.org\/\?author=1#main-key",algorithm="rsa-sha256",headers="\(request-target\) host date digest",signature="[^"]*"/', $signature );

		// Signed headers.
		$url_parts = wp_parse_url( $remote_actor );
		$route     = $url_parts['path'] . '?' . $url_parts['query'];
		$host      = $url_parts['host'];

		$headers = array(
			'digest'           => array( $digest ),
			'signature'        => array( $signature ),
			'date'             => array( $date ),
			'host'             => array( $host ),
			'(request-target)' => array( 'post ' . $route ),
		);

		// Start verification.
		// Parse_signature_header, get_signed_data, get_public_key.
		$signature_block = Activitypub\Signature::parse_signature_header( $headers['signature'][0] );
		$signed_headers  = $signature_block['headers'];
		$signed_data     = Activitypub\Signature::get_signed_data( $signed_headers, $signature_block, $headers );

		$user = Activitypub\Collection\Actors::get_by_id( 1 );

		$public_key = Activitypub\Signature::get_public_key_for( $user->get__id() );

		// Signature_verification.
		$verified = \openssl_verify( $signed_data, $signature_block['signature'], $public_key, 'rsa-sha256' ) > 0;
		$this->assertTrue( $verified );
	}

	/**
	 * Test REST activity signature.
	 *
	 * @covers ::generate_digest
	 * @covers ::generate_signature
	 * @covers ::verify_http_signature
	 */
	public function test_rest_activity_signature() {
		add_filter(
			'pre_get_remote_metadata_by_actor',
			function ( $json, $actor ) {
				$user       = Activitypub\Collection\Actors::get_by_id( 1 );
				$public_key = Activitypub\Signature::get_public_key_for( $user->get__id() );

				// Return ActivityPub Profile with signature.
				return array(
					'id'        => $actor,
					'type'      => 'Person',
					'publicKey' => array(
						'id'           => $actor . '#main-key',
						'owner'        => $actor,
						'publicKeyPem' => $public_key,
					),
				);
			},
			10,
			2
		);

		// Activity Object.
		$post = \wp_insert_post(
			array(
				'post_author'  => 1,
				'post_content' => 'hello world',
			)
		);

		$remote_actor_inbox   = Activitypub\get_rest_url_by_path( '/inbox' );
		$activitypub_post     = \Activitypub\Transformer\Post::transform( \get_post( $post ) )->to_object();
		$activitypub_activity = new Activitypub\Activity\Activity();
		$activitypub_activity->set_type( 'Create' );
		$activitypub_activity->set_object( $activitypub_post );
		$activitypub_activity->add_cc( $remote_actor_inbox );
		$activity = $activitypub_activity->to_json();

		// Generate_digest & generate_signature.
		$digest    = Activitypub\Signature::generate_digest( $activity );
		$date      = gmdate( 'D, d M Y H:i:s T' );
		$signature = Activitypub\Signature::generate_signature( 1, 'POST', $remote_actor_inbox, $date, $digest );

		// Signed headers.
		$url_parts = wp_parse_url( $remote_actor_inbox );
		$route     = $url_parts['path'] . '?' . $url_parts['query'];
		$host      = $url_parts['host'];

		$request = new WP_REST_Request( 'POST', $route );
		$request->set_header( 'content-type', 'application/activity+json' );
		$request->set_header( 'digest', $digest );
		$request->set_header( 'signature', $signature );
		$request->set_header( 'date', $date );
		$request->set_header( 'host', $host );
		$request->set_body( $activity );

		// Start verification.
		$verified = \Activitypub\Signature::verify_http_signature( $request );
		$this->assertTrue( $verified );

		remove_filter( 'pre_get_remote_metadata_by_actor', array( get_called_class(), 'pre_get_remote_key' ), 10, 2 );
	}
}
