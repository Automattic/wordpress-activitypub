<?php
class Test_Activitypub_Users_Collection extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();

		add_option( 'activitypub_blog_user_identifier', 'blog' );
		add_user_meta( 1, 'activitypub_user_identifier', 'admin' );
	}
	/**
	 * @dataProvider the_resource_provider
	 */
	public function test_get_by_various( $resource, $expected ) {
		$user = Activitypub\Collection\Users::get_by_resource( $resource );

		$this->assertInstanceOf( $expected, $user );
	}

	public function the_resource_provider() {
		return array(
			array( 'http://example.org/?author=1', 'Activitypub\Activity\Actor' ),
			array( 'https://example.org/?author=1', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org/?author=7', 'WP_Error' ),
			array( 'acct:admin@example.org', 'Activitypub\Activity\Actor' ),
			array( 'acct:blog@example.org', 'Activitypub\Activity\Actor' ),
			array( 'acct:*@example.org', 'Activitypub\Activity\Actor' ),
			array( 'acct:_@example.org', 'Activitypub\Activity\Actor' ),
			array( 'acct:aksd@example.org', 'WP_Error' ),
			array( 'admin@example.org', 'Activitypub\Activity\Actor' ),
			array( 'acct:application@example.org', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org/@admin', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org/@blog', 'Activitypub\Activity\Actor' ),
			array( 'https://example.org/@blog', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org/@blog/', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org/', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org', 'Activitypub\Activity\Actor' ),
			array( 'https://example.org/', 'Activitypub\Activity\Actor' ),
			array( 'https://example.org', 'Activitypub\Activity\Actor' ),
			array( 'http://example.org/@blog/s', 'WP_Error' ),
			array( 'http://example.org/@blogs/', 'WP_Error' ),
		);
	}
}
