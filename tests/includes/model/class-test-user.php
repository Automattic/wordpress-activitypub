<?php
/**
 * Test file for Activitypub User.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Model\User;

/**
 * Test class for Activitypub User.
 *
 * @coversDefaultClass \Activitypub\Model\User
 */
class Test_User extends \WP_UnitTestCase {

	/**
	 * Test the activitypub capability.
	 */
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

	/**
	 * Test that on attachment pages the user avatar is returned.
	 *
	 * @ticket https://github.com/Automattic/wordpress-activitypub/issues/1459
	 * @covers ::get_icon
	 */
	public function test_icon() {
		$user_id = self::factory()->user->create( array( 'role' => 'author' ) );
		$user    = User::from_wp_user( $user_id );

		// Add attachment.
		$attachment_id = self::factory()->attachment->create_upload_object( AP_TESTS_DIR . '/assets/test.jpg' );

		// Navigate to attachment page.
		$this->go_to( get_attachment_link( $attachment_id ) );

		$icon = $user->get_icon();

		$this->assertArrayHasKey( 'url', $icon );
		$this->assertNotSame( wp_get_attachment_url( $attachment_id ), $icon['url'] );
	}
}
