<?php
class Test_Functions extends ActivityPub_TestCase_Cache_HTTP {
	public function test_get_remote_metadata_by_actor() {
		$metadata = \ActivityPub\get_remote_metadata_by_actor( 'pfefferle@notiz.blog' );
		$this->assertEquals( 'https://notiz.blog/author/matthias-pfefferle/', $metadata['url'] );
		$this->assertEquals( 'pfefferle', $metadata['preferredUsername'] );
		$this->assertEquals( 'Matthias Pfefferle', $metadata['name'] );
	}
}
