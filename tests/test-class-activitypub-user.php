<?php
class Test_Activitypub_User extends WP_UnitTestCase {

	public function test_activitypub_cap() {
		$userdata = array(
			'user_email' => 'subscriber@example.com',
			'first_name' => 'Max',
			'last_name'  => 'Mustermann',
			'user_login' => 'subscriber',
			'user_pass'  => 'subscriber',
			'role'       => 'subscriber',
		);

		$user_id = wp_insert_user( $userdata );
		$can     = user_can( $user_id, 'activitypub' );

		$this->assertFalse( $can );

		$userdata = array(
			'user_email' => 'editor@example.com',
			'first_name' => 'Max',
			'last_name'  => 'Mustermann',
			'user_login' => 'editor',
			'user_pass'  => 'editor',
			'role'       => 'editor',
		);

		$user_id = wp_insert_user( $userdata );
		$can     = user_can( $user_id, 'activitypub' );

		$this->assertTrue( $can );
	}
}
