<?php
/**
 * Test file for Outbox Arrive Handler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Handler\Outbox;

use Activitypub\Handler\Outbox\Arrive;
use Activitypub\Scheduler\Post;

/**
 * Test class for Outbox Arrive Handler.
 *
 * @coversDefaultClass \Activitypub\Handler\Outbox\Arrive
 */
class Test_Arrive extends \WP_UnitTestCase {

	/**
	 * Test Arrive creates a blog post and returns an outbox ID.
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_creates_post_and_returns_outbox_id() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$data = array(
			'type'     => 'Arrive',
			'actor'    => 'https://example.com/users/test',
			'location' => array(
				'type'      => 'Place',
				'name'      => 'Berlin',
				'latitude'  => 52.52,
				'longitude' => 13.405,
			),
			'content'  => 'Just arrived!',
			'to'       => array( 'https://www.w3.org/ns/activitystreams#Public' ),
			'cc'       => array( 'https://example.com/users/test/followers' ),
		);

		$result = Arrive::handle_arrive( $data, $user_id );

		$this->assertIsInt( $result, 'Handler should return an outbox post ID.' );
		$this->assertGreaterThan( 0, $result );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test Arrive saves location geodata on the blog post.
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_saves_geodata() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$data = array(
			'type'     => 'Arrive',
			'actor'    => 'https://example.com/users/test',
			'location' => array(
				'type'      => 'Place',
				'name'      => 'Ettlingen',
				'latitude'  => 48.9408,
				'longitude' => 8.4075,
			),
			'content'  => 'Checked in.',
			'to'       => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		Arrive::handle_arrive( $data, $user_id );

		// Find the most recent post by this user.
		$posts = \get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$this->assertNotEmpty( $posts, 'A blog post should be created.' );

		$post_id = $posts[0]->ID;

		$this->assertSame( 'Ettlingen', \get_post_meta( $post_id, 'geo_address', true ) );
		$this->assertEquals( 48.9408, (float) \get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertEquals( 8.4075, (float) \get_post_meta( $post_id, 'geo_longitude', true ) );
		$this->assertSame( '1', \get_post_meta( $post_id, 'geo_public', true ) );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test Arrive with name-only location (no coordinates).
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_with_name_only_location() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$data = array(
			'type'     => 'Arrive',
			'actor'    => 'https://example.com/users/test',
			'location' => array(
				'id'   => 'https://places.pub/relation/123',
				'name' => 'Karlsruhe',
			),
			'content'  => 'Hello!',
			'to'       => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		Arrive::handle_arrive( $data, $user_id );

		$posts = \get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$this->assertNotEmpty( $posts );

		$post_id = $posts[0]->ID;

		$this->assertSame( 'Karlsruhe', \get_post_meta( $post_id, 'geo_address', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, 'geo_latitude', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, 'geo_longitude', true ) );
		$this->assertSame( '1', \get_post_meta( $post_id, 'geo_public', true ) );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test Arrive without location still creates a post.
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_without_location() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$data = array(
			'type'    => 'Arrive',
			'actor'   => 'https://example.com/users/test',
			'content' => 'Somewhere!',
			'to'      => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		$result = Arrive::handle_arrive( $data, $user_id );

		$this->assertIsInt( $result );
		$this->assertGreaterThan( 0, $result );

		$posts = \get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$this->assertNotEmpty( $posts );
		$this->assertStringContainsString( 'Check-in', $posts[0]->post_title );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test Arrive uses summary when content is missing.
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_uses_summary_fallback() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$data = array(
			'type'     => 'Arrive',
			'actor'    => 'https://example.com/users/test',
			'location' => array( 'name' => 'Munich' ),
			'summary'  => 'Arrived at Munich',
			'to'       => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		Arrive::handle_arrive( $data, $user_id );

		$posts = \get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$this->assertNotEmpty( $posts );
		$this->assertStringContainsString( 'Arrived at Munich', $posts[0]->post_content );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}

	/**
	 * Test Arrive with non-public activity returns false.
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_non_public_returns_false() {
		$data = array(
			'type'     => 'Arrive',
			'actor'    => 'https://example.com/users/test',
			'location' => array( 'name' => 'Secret Place' ),
			'content'  => 'Private check-in.',
			'to'       => array( 'https://example.com/users/friend' ),
		);

		$result = Arrive::handle_arrive( $data, 1 );

		$this->assertFalse( $result );
	}

	/**
	 * Test Arrive sets status post format on the blog post.
	 *
	 * @covers ::handle_arrive
	 */
	public function test_arrive_sets_status_post_format() {
		\remove_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33 );

		$user_id = self::factory()->user->create( array( 'role' => 'editor' ) );

		$data = array(
			'type'     => 'Arrive',
			'actor'    => 'https://example.com/users/test',
			'location' => array( 'name' => 'Hamburg' ),
			'content'  => 'Arrived.',
			'to'       => array( 'https://www.w3.org/ns/activitystreams#Public' ),
		);

		Arrive::handle_arrive( $data, $user_id );

		$posts = \get_posts(
			array(
				'author'         => $user_id,
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		$this->assertNotEmpty( $posts );
		$this->assertSame( 'status', \get_post_format( $posts[0]->ID ) );

		\add_action( 'wp_after_insert_post', array( Post::class, 'triage' ), 33, 4 );
	}
}
