<?php

class Test_Activitypub_Signature extends WP_UnitTestCase {
	public function test_signature_creation() {
		$user = Activitypub\Collection\Users::get_by_id( 1 );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );
		$public_key = Activitypub\Signature::get_public_key_for( $user->get__id() );
		$private_key = Activitypub\Signature::get_private_key_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	public function test_signature_legacy() {
		// check user
		$user = Activitypub\Collection\Users::get_by_id( 1 );

		$public_key = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// check application user
		$user = Activitypub\Collection\Users::get_by_id( -1 );

		$public_key = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		add_option( 'activitypub_application_user_public_key', $public_key );
		add_option( 'activitypub_application_user_private_key', $private_key );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		// check blog user
		\define( 'ACTIVITYPUB_DISABLE_BLOG_USER', false );
		$user = Activitypub\Collection\Users::get_by_id( 0 );

		$public_key = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		add_option( 'activitypub_blog_user_public_key', $public_key );
		add_option( 'activitypub_blog_user_private_key', $private_key );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	public function test_signature_consistancy() {
		// check user
		$user = Activitypub\Collection\Users::get_by_id( 1 );

		$public_key = 'public key ' . $user->get__id();
		$private_key = 'private key ' . $user->get__id();

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		update_user_meta( $user->get__id(), 'magic_sig_public_key', $public_key . '-update' );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', $private_key . '-update' );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}

	public function test_signature_consistancy2() {
		$user = Activitypub\Collection\Users::get_by_id( 1 );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );
		$public_key = Activitypub\Signature::get_public_key_for( $user->get__id() );
		$private_key = Activitypub\Signature::get_private_key_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );

		update_user_meta( $user->get__id(), 'magic_sig_public_key', 'test' );
		update_user_meta( $user->get__id(), 'magic_sig_private_key', 'test' );

		$key_pair = Activitypub\Signature::get_keypair_for( $user->get__id() );

		$this->assertNotEmpty( $key_pair );
		$this->assertEquals( $key_pair['public_key'], $public_key );
		$this->assertEquals( $key_pair['private_key'], $private_key );
	}
}
