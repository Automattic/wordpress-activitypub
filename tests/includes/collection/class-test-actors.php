<?php
/**
 * Test file for Activitypub Actors Collection.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Collection\Actors;

/**
 * Class Test_Actors
 *
 * @coversDefaultClass \Activitypub\Collection\Actors
 */
class Test_Actors extends \WP_UnitTestCase {

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );
		add_option( 'activitypub_blog_identifier', 'blog' );
		add_user_meta( 1, 'activitypub_user_identifier', 'admin' );
	}

	/**
	 * Test get_by_id.
	 *
	 * @covers ::get_by_id
	 */
	public function test_get_by_id() {
		// External user.
		$user_id = 'obenland@mastodon.social';

		$actor = Actors::get_by_id( $user_id );
		$this->assertWPError( $actor );
	}

	/**
	 * Test the create() method for remote actors.
	 *
	 * @covers ::create
	 */
	public function test_create_actor() {
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/jane-create',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/jane-create',
			'inbox'             => 'https://remote.example.com/actor/jane-create/inbox',
			'name'              => 'Jane',
			'preferredUsername' => 'jane',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);
		$post_id = Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$this->assertGreaterThan( 0, $post_id );
		$post = \get_post( $post_id );
		$this->assertInstanceOf( '\WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/jane-create', $post->guid );
		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test the update() method for remote actors.
	 *
	 * @covers ::update
	 */
	public function test_update_actor() {
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/jane-update',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/jane-update',
			'inbox'             => 'https://remote.example.com/actor/jane-update/inbox',
			'name'              => 'Jane',
			'preferredUsername' => 'jane',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);
		$post_id = Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$updated_actor         = $actor;
		$updated_actor['name'] = 'Jane Doe';
		$update_result         = Actors::update( $post_id, $updated_actor );
		$this->assertEquals( $post_id, $update_result );
		$updated_post = \get_post( $post_id );
		$this->assertInstanceOf( '\WP_Post', $updated_post );
		$actor_obj = Actors::get_actor( $updated_post );
		$this->assertEquals( 'Jane Doe', $actor_obj->get_name() );
		// Clean up.
		\wp_delete_post( $post_id, true );
	}

	/**
	 * Test the delete (wp_delete_post) operation for remote actors.
	 *
	 * @covers ::delete
	 */
	public function test_delete_actor() {
		$actor   = array(
			'id'                => 'https://remote.example.com/actor/jane-delete',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/jane-delete',
			'inbox'             => 'https://remote.example.com/actor/jane-delete/inbox',
			'name'              => 'Jane',
			'preferredUsername' => 'jane',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);
		$post_id = Actors::create( $actor );
		$this->assertIsInt( $post_id );
		$delete_result = \wp_delete_post( $post_id, true );
		$this->assertInstanceOf( '\WP_Post', $delete_result );
		$deleted_post = \get_post( $post_id );
		$this->assertNull( $deleted_post );
	}

	/**
	 * Test lookup_remote_by_uri.
	 *
	 * @covers ::lookup_remote_by_uri
	 */
	public function test_lookup_remote_by_uri() {
		// Create a remote actor.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/bob',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/bob',
			'inbox'             => 'https://remote.example.com/actor/bob/inbox',
			'name'              => 'Bob',
			'preferredUsername' => 'bob',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$id = Actors::create( $actor );
		$this->assertNotWPError( $id );

		// Should find the actor locally.
		$post = Actors::lookup_remote_by_uri( 'https://remote.example.com/actor/bob' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/bob', $post->guid );

		// Delete local post, mock remote fetch.
		\wp_delete_post( $id );

		add_filter(
			'activitypub_pre_http_get_remote_object',
			function ( $pre, $url_or_object ) use ( $actor ) {
				if ( $url_or_object === $actor['id'] ) {
					return $actor;
				}
				return $pre;
			},
			10,
			2
		);

		$post = Actors::lookup_remote_by_uri( 'https://remote.example.com/actor/bob' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/bob', $post->guid );

		remove_all_filters( 'activitypub_pre_http_get_remote_object' );
		\wp_delete_post( $post->ID );

		// Should return WP_Error for invalid URI.
		$not_found = Actors::lookup_remote_by_uri( '' );
		$this->assertWPError( $not_found );
	}

	/**
	 * Test get_remote_by_uri.
	 *
	 * @covers ::get_remote_by_uri
	 */
	public function test_get_remote_by_uri() {
		// Create a remote actor.
		$actor = array(
			'id'                => 'https://remote.example.com/actor/alice',
			'type'              => 'Person',
			'url'               => 'https://remote.example.com/actor/alice',
			'inbox'             => 'https://remote.example.com/actor/alice/inbox',
			'name'              => 'Alice',
			'preferredUsername' => 'alice',
			'endpoints'         => array(
				'sharedInbox' => 'https://remote.example.com/inbox',
			),
		);

		$id = Actors::create( $actor );
		$this->assertNotWPError( $id );

		// Should find the actor by guid.
		$post = Actors::get_remote_by_uri( 'https://remote.example.com/actor/alice' );
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( 'https://remote.example.com/actor/alice', $post->guid );

		// Should return WP_Error for non-existent URI.
		$not_found = Actors::get_remote_by_uri( 'https://remote.example.com/actor/doesnotexist' );
		$this->assertWPError( $not_found );

		// Should return WP_Error for empty URI.
		$empty = Actors::get_remote_by_uri( '' );
		$this->assertWPError( $empty );

		// Clean up.
		\wp_delete_post( $id );
	}

	/**
	 * Test get_by_various.
	 *
	 * @dataProvider the_resource_provider
	 * @covers ::get_by_various
	 *
	 * @param string $item     The resource.
	 * @param string $expected The expected class.
	 */
	public function test_get_by_various( $item, $expected ) {
		$path = wp_parse_url( $item, PHP_URL_PATH ) ?? '';

		if ( str_starts_with( $path, '/blog/' ) ) {
			add_filter(
				'home_url',
				// phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable, Generic.CodeAnalysis.UnusedFunctionParameter.Found
				function ( $url ) {
					return 'http://example.org/blog/';
				}
			);
		}

		$actors = Actors::get_by_resource( $item );
		$this->assertInstanceOf( $expected, $actors );
	}

	/**
	 * Resource provider.
	 *
	 * @return array[]
	 */
	public function the_resource_provider() {
		return array(
			array( 'http://example.org/?author=1', 'Activitypub\Model\User' ),
			array( 'https://example.org/?author=1', 'Activitypub\Model\User' ),
			array( 'https://example.org?author=1', 'Activitypub\Model\User' ),
			array( 'http://example.org/?author=7', 'WP_Error' ),
			array( 'acct:admin@example.org', 'Activitypub\Model\User' ),
			array( 'acct:blog@example.org', 'Activitypub\Model\Blog' ),
			array( 'acct:*@example.org', 'Activitypub\Model\Blog' ),
			array( 'acct:_@example.org', 'Activitypub\Model\Blog' ),
			array( 'acct:aksd@example.org', 'WP_Error' ),
			array( 'admin@example.org', 'Activitypub\Model\User' ),
			array( 'acct:application@example.org', 'Activitypub\Model\Application' ),
			array( 'http://example.org/@admin', 'Activitypub\Model\User' ),
			array( 'http://example.org/@blog', 'Activitypub\Model\Blog' ),
			array( 'https://example.org/@blog', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/@blog/', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/blog/@blog', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/blog/@blog/', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/error/@blog', 'WP_Error' ),
			array( 'http://example.org/error/@blog/', 'WP_Error' ),
			array( 'http://example.org/', 'Activitypub\Model\Blog' ),
			array( 'http://example.org', 'Activitypub\Model\Blog' ),
			array( 'https://example.org/', 'Activitypub\Model\Blog' ),
			array( 'https://example.org', 'Activitypub\Model\Blog' ),
			array( 'http://example.org/@blog/s', 'WP_Error' ),
			array( 'http://example.org/@blogs/', 'WP_Error' ),
		);
	}

	/**
	 * Test get_type_by_id()
	 *
	 * @covers ::get_type_by_id
	 */
	public function test_get_type_by_id() {
		$this->assertSame( 'application', Actors::get_type_by_id( Actors::APPLICATION_USER_ID ) );
		$this->assertSame( 'blog', Actors::get_type_by_id( Actors::BLOG_USER_ID ) );
		$this->assertSame( 'user', Actors::get_type_by_id( 1 ) );
		$this->assertSame( 'user', Actors::get_type_by_id( 2 ) );
	}

	/**
	 * Test if Actor mode will be respected properly
	 *
	 * @covers ::get_type_by_id
	 */
	public function test_disabled_blog_profile() {
		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_AND_BLOG_MODE );

		$resource = 'http://example.org/@blog';

		$this->assertEquals( 'Activitypub\Model\Blog', get_class( Actors::get_by_resource( $resource ) ) );

		\update_option( 'activitypub_actor_mode', ACTIVITYPUB_ACTOR_MODE );

		$this->assertWPError( Actors::get_by_resource( $resource ) );
	}

	/**
	 * Tests clear_errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors() {
		$actor = array(
			'id'                => 'https://example.com/author/jon',
			'type'              => 'Person',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
			'endpoints'         => array(
				'sharedInbox' => 'https://example.com/inbox',
			),
		);

		$id = Actors::upsert( $actor );
		$this->assertNotWPError( $id );

		// Add some errors.
		Actors::add_error( $id, 'Test error 1' );
		Actors::add_error( $id, 'Test error 2' );

		// Verify errors were added.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertCount( 2, $errors );

		// Clear errors.
		$cleared = Actors::clear_errors( $id );
		$this->assertTrue( $cleared );

		// Verify errors were cleared.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertEmpty( $errors );

		\wp_delete_post( $id );
	}

	/**
	 * Tests clear_errors with no errors.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors_no_errors() {
		$actor = array(
			'type'              => 'Person',
			'id'                => 'https://example.com/author/jon',
			'url'               => 'https://example.com/author/jon',
			'inbox'             => 'https://example.com/author/jon/inbox',
			'name'              => 'jon',
			'preferredUsername' => 'jon',
		);

		$id = Actors::upsert( $actor );
		$this->assertNotWPError( $id );

		// Clear errors when none exist.
		$cleared = Actors::clear_errors( $id );
		$this->assertFalse( $cleared );

		// Verify no errors exist.
		$errors = \get_post_meta( $id, '_activitypub_errors', false );
		$this->assertEmpty( $errors );
	}

	/**
	 * Tests clear_errors with invalid follower ID.
	 *
	 * @covers ::clear_errors
	 */
	public function test_clear_errors_invalid_id() {
		// Try to clear errors for non-existent follower.
		$cleared = Actors::clear_errors( 99999 );
		$this->assertFalse( $cleared );
	}
}
