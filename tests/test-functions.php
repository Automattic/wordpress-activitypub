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
	}
	/**
	 * @dataProvider example_actors
	 */
	public function test_get_example_metadata_by_actor( $actor, $domain, $username ) {
		add_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8, 3 );
		$metadata = \ActivityPub\get_remote_metadata_by_actor( $actor );
		$this->assertEquals( sprintf( 'https://%s/users/%s/', $domain, $username ), $metadata['url'], $actor );
		$this->assertEquals( $username, $metadata['name'], $actor );
		remove_filter( 'pre_http_request', array( $this, 'invalid_http_response' ), 8 );
	}

	public function example_actors() {
		$actors = array();
		foreach ( array( 'user', 'test' ) as $username ) {
			foreach ( array( 'example.org', 'example.net', 'example2.com' ) as $domain ) {
				foreach ( array( '@', '' ) as $leading_at ) {
					$actors[] = array( $leading_at . $username . '@' . $domain, $domain, $username );
				}
				$actors[] = array( sprintf( 'https://%s/users/%s/', $domain, $username ), $domain, $username );
				$actors[] = array( sprintf( 'https://%s/users/%s', $domain, $username ), $domain, $username );
				$actors[] = array( sprintf( 'https://%s/@%s', $domain, $username ), $domain, $username );
			}
		}
		return $actors;
	}
}
