<?php
class Test_Functions extends ActivityPub_TestCase_Cache_HTTP {
	public function invalid_http_response() {
		return $this->assertTrue( false ); // should not be called.
	}

	public function test_get_remote_metadata_by_actor() {
		$metadata = \ActivityPub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );

		add_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8, 3 );
		foreach ( array( 'user', 'test' ) as $username ) {
			foreach ( array( 'example.org', 'example.net' ) as $domain ) {
				foreach ( array( '@', '' ) as $leading_at ) {
					$metadata = \ActivityPub\get_remote_metadata_by_actor( $username . '@' . $domain );
					$this->assertEquals( sprintf( 'https://%s/users/%s/', $domain, $username ), $metadata['url'], $username . '@' . $domain );
					$this->assertEquals( $username, $metadata['name'], $username . '@' . $domain );
				}
				$metadata = \ActivityPub\get_remote_metadata_by_actor( sprintf( 'https://%s/users/%s/', $domain, $username ) );
				$this->assertEquals( sprintf( 'https://%s/users/%s/', $domain, $username ), $metadata['url'], $username . '@' . $domain );
				$this->assertEquals( $username, $metadata['name'], $username . '@' . $domain );
			}
		}
		remove_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8 );
	}
}
