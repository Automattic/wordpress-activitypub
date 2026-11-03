<?php
/**
 * Test file for Avatars.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Remote_Actors;

/**
 * Test class for Avatars.
 *
 * @coversDefaultClass \Activitypub\Avatars
 */
class Test_Avatars extends \WP_UnitTestCase {
	/**
	 * The post the comments are on.
	 *
	 * @var int
	 */
	private $post_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();

		\update_option( 'show_avatars', '1' );
		$this->post_id = self::factory()->post->create();
	}

	/**
	 * Test that a comment shows its own avatar, stored under the hash of its URL.
	 *
	 * @covers ::pre_get_avatar_data
	 */
	public function test_comment_avatar_is_stored_by_url() {
		$avatar_url = 'https://example.com/avatar.png';
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_meta'    => array(
					'protocol'              => 'activitypub',
					'_activitypub_actor_id' => 'https://example.com/users/commenter',
					'avatar_url'            => $avatar_url,
				),
			)
		);

		$entity = null;
		$filter = static function ( $url, $context, $entity_id ) use ( &$entity ) {
			if ( 'avatar' === $context ) {
				$entity = $entity_id;
			}
			return $url;
		};
		\add_filter( 'activitypub_remote_media_url', $filter, 5, 3 );

		$url = \get_avatar_url( \get_comment( $comment_id ) );

		\remove_filter( 'activitypub_remote_media_url', $filter, 5 );

		$this->assertSame( $avatar_url, $url );
		$this->assertSame( \md5( $avatar_url ), $entity, 'The file is shared by every comment with this avatar.' );
	}

	/**
	 * Test that a comment from an actor without an icon shows the default avatar.
	 *
	 * @covers ::pre_get_avatar_data
	 */
	public function test_comment_without_avatar_shows_default() {
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_meta'    => array(
					'protocol'              => 'activitypub',
					'_activitypub_actor_id' => 'https://example.com/users/no-icon',
				),
			)
		);

		$this->assertSame( ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg', \get_avatar_url( \get_comment( $comment_id ) ) );
	}

	/**
	 * Test that a comment from before the actor ID was stored on it shows the actor post's avatar.
	 *
	 * @covers ::pre_get_avatar_data
	 */
	public function test_older_comment_shows_actor_post_avatar() {
		$actor_post_id = Remote_Actors::upsert(
			array(
				'id'                => 'https://example.com/users/older',
				'type'              => 'Person',
				'preferredUsername' => 'older',
				'inbox'             => 'https://example.com/users/older/inbox',
				'icon'              => array(
					'type' => 'Image',
					'url'  => 'https://example.com/actor-avatar.png',
				),
			)
		);
		$comment_id    = self::factory()->comment->create(
			array(
				'comment_post_ID' => $this->post_id,
				'comment_meta'    => array(
					'protocol'                     => 'activitypub',
					'_activitypub_remote_actor_id' => $actor_post_id,
					'avatar_url'                   => 'https://example.com/stale-avatar.png',
				),
			)
		);

		$this->assertSame( 'https://example.com/actor-avatar.png', \get_avatar_url( \get_comment( $comment_id ) ) );
	}
}
