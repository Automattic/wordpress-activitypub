<?php
class Test_Db_Activitypub_Followers extends WP_UnitTestCase {
	public function test_get_followers() {
		$followers = array( 'https://example.com/author/jon', 'https://example.org/author/doe' );
		$followers[] = array(
			'type' => 'Person',
			'id' => 'http://sally.example.org',
			'name' => 'Sally Smith',
		);
		update_user_meta( 1, 'activitypub_followers', $followers );

		$db_followers = Db_Activitypub_Followers::get_followers( 1 );

		$this->assertEquals( 3, count( $db_followers ) );

		$this->assertSame( array( 'https://example.com/author/jon', 'https://example.org/author/doe', 'http://sally.example.org' ), $db_followers );
	}
}
