<?php
/**
 * Test file for Activitypub Follower.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Model\Follower;
use Activitypub\Collection\Followers;

/**
 * Tests the Follower class.
 *
 * @package Activitypub
 */
class Test_Follower extends \WP_UnitTestCase {
	/**
	 * Tests clear_errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors() {
		// Mock request.
		$follower = new Follower();
		$follower->from_array(
			array(
				'id'                => 'https://example.com/author/jon',
				'type'              => 'Person',
				'name'              => 'Jon Doe',
				'preferredUsername' => 'jon',
				'inbox'             => 'https://example.com/author/jon/inbox',
				'publicKey'         => 'publicKey',
				'publicKeyPem'      => 'publicKeyPem',
			)
		);

		$id = $follower->upsert();
		$this->assertNotWPError( $id );

		// Add some errors.
		Followers::add_error( $follower->get__id(), 'Test error 1' );
		Followers::add_error( $follower->get__id(), 'Test error 2' );

		// Verify errors were added.
		$errors = $follower->get_errors();
		$this->assertCount( 2, $errors );

		// Clear errors.
		$cleared = $follower->clear_errors();
		$this->assertTrue( $cleared );

		// Verify errors were cleared.
		$errors = $follower->get_errors();
		$this->assertEmpty( $errors );
	}

	/**
	 * Tests clear_errors with no errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors_no_errors() {
		$follower = new Follower();
		$follower->from_array(
			array(
				'id'                => 'https://example.com/author/jon',
				'type'              => 'Person',
				'name'              => 'Jon Doe',
				'preferredUsername' => 'jon',
				'inbox'             => 'https://example.com/author/jon/inbox',
				'publicKey'         => 'publicKey',
				'publicKeyPem'      => 'publicKeyPem',
			)
		);
		$id = $follower->upsert();
		$this->assertNotWPError( $id );

		// Clear errors when none exist.
		$cleared = $follower->clear_errors();
		$this->assertFalse( $cleared );

		// Verify no errors exist.
		$errors = $follower->get_errors();
		$this->assertEmpty( $errors );
	}

	/**
	 * Tests clear_errors triggers _doing_it_wrong when ID is not set.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors_doing_it_wrong() {
		$this->setExpectedIncorrectUsage( 'Activitypub\Model\Follower::clear_errors' );

		$follower = new Follower();
		$follower->clear_errors();
	}

	/**
	 * Tests save.
	 *
	 * @covers ::save
	 */
	public function test_save() {
		// Mock request.
		$follower = new Follower();
		$follower->from_array(
			array(
				'id'                => 'https://example.com/author/jon',
				'type'              => 'Person',
				'name'              => 'Jon Doe',
				'preferredUsername' => 'jon',
				'summary'           => '<p>Summary 02\2024</p>',
				'inbox'             => 'https://example.com/author/jon/inbox',
				'publicKey'         => 'publicKey',
				'publicKeyPem'      => 'publicKeyPem',
			)
		);

		$id = $follower->upsert();
		$this->assertNotWPError( $id );

		\clean_post_cache( $id );

		$post     = \get_post( $id );
		$follower = Follower::init_from_cpt( $post );
		$this->assertEquals( 'Summary 02\2024', $follower->get_summary() );
		$this->assertEquals( '<p>Summary 02\2024</p>', json_decode( $post->post_content )->summary );
	}
}
