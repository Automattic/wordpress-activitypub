<?php
/**
 * Test file for Activitypub Signature.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Signature;
use Activitypub\Collection\Actors;

/**
 * Test class for Signature.
 *
 * @coversDefaultClass \Activitypub\Signature
 */
class Test_Signature extends \WP_UnitTestCase {
	/**
	 * Tear down.
	 */
	public function tear_down() {
		parent::tear_down();
		\delete_option( 'activitypub_keypair_for_0' );
		\delete_option( 'activitypub_keypair_for_-1' );
		\delete_option( 'activitypub_keypair_for_admin' );
		\delete_option( 'activitypub_blog_user_public_key' );
		\delete_option( 'activitypub_blog_user_private_key' );
		\delete_option( 'activitypub_application_user_public_key' );
		\delete_option( 'activitypub_application_user_private_key' );
		\delete_option( 'activitypub_actor_mode' );
		\delete_user_meta( 1, 'magic_sig_public_key' );
		\delete_user_meta( 1, 'magic_sig_private_key' );
	}

	/**
	 * Test signature creation.
	 *
	 * @covers ::get_keypair_for
	 * @covers ::get_public_key_for
	 * @covers ::get_private_key_for
	 */
	public function test_signature_creation() {
		$user = Actors::get_by_id( 1 );

		$key_pair    = Signature::get_keypair_for( $user->get__id() );
		$public_key  = Signature::get_public_key_for( $user->get__id() );
		$private_key = Signature::get_private_key_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature legacy.
	 *
	 * @covers ::get_keypair_for
	 */
	public function test_signature_legacy() {
		// Check user.
		$user = Actors::get_by_id( 1 );

		\delete_option( 'activitypub_keypair_for_admin' );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// Check application user.
		$user = Actors::get_by_id( Actors::APPLICATION_USER_ID );

		\delete_option( 'activitypub_keypair_for_-1' );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		\add_option( 'activitypub_application_user_public_key', $public_key );
		\add_option( 'activitypub_application_user_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// Check blog user.
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		$user = Actors::get_by_id( Actors::BLOG_USER_ID );
		\delete_option( 'activitypub_actor_mode' );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		\delete_option( 'activitypub_keypair_for_0' );

		\add_option( 'activitypub_blog_user_public_key', $public_key );
		\add_option( 'activitypub_blog_user_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature consistancy.
	 *
	 * @covers ::get_keypair_for
	 */
	public function test_signature_consistancy() {
		// Check user.
		$user = Actors::get_by_id( 1 );

		$public_key  = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key . '-update' );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key . '-update' );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	/**
	 * Test signature consistancy 2.
	 *
	 * @covers ::get_keypair_for
	 */
	public function test_signature_consistancy2() {
		$user = Actors::get_by_id( 1 );

		$key_pair    = Signature::get_keypair_for( $user->get__id() );
		$public_key  = Signature::get_public_key_for( $user->get__id() );
		$private_key = Signature::get_private_key_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		update_user_meta( $user->get__id(), 'magic_sig_public_key', 'test' );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', 'test' );

		$key_pair = Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}
}
