<?php
/**
 * Test file for Activitypub Follower.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Model;

use Activitypub\Collection\Actors;
use Activitypub\Model\Follower;

/**
 * Tests the Follower class.
 *
 * @coversDefaultClass \Activitypub\Model\Follower
 */
class Test_Follower extends \WP_UnitTestCase {
	/**
	 * Tests clear_errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors() {
		$actor = array(
			'id'                => 'https://example.com/author/jon',
			'type'              => 'Person',
			'name'              => 'Jon Doe',
			'preferredUsername' => 'jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'publicKey'         => 'publicKey',
			'publicKeyPem'      => 'publicKeyPem',
		);

		$id = Actors::upsert( $actor );

		// Add some errors.
		Actors::add_error( $id, 'Test error 1' );
		Actors::add_error( $id, 'Test error 2' );

		// Verify errors were added.
		$count = Actors::count_errors( $id );
		$this->assertEquals( 2, $count );

		// Clear errors.
		$cleared = Actors::clear_errors( $id );
		$this->assertTrue( $cleared );

		// Verify errors were cleared.
		$count = Actors::count_errors( $id );
		$this->assertEquals( 0, $count );
	}

	/**
	 * Tests clear_errors with no errors.
	 *
	 * @expectedDeprecated Activitypub\Model\Follower
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
	 * Tests save.
	 *
	 * @expectedDeprecated Activitypub\Model\Follower
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
