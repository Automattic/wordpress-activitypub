<?php
/**
 * Tests for opt-in remote actor cleanup.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Remote_Posts;
use Activitypub\Collection\Remote_Actor_Cleanup;
use Activitypub\Collection\Remote_Actors;

/**
 * Exercise queue processing without touching actors through public lookups.
 *
 * @coversDefaultClass \Activitypub\Collection\Remote_Actor_Cleanup
 */
class Test_Remote_Actor_Cleanup extends \WP_UnitTestCase {
	/**
	 * Create a stored actor without network requests.
	 *
	 * @return int Actor ID.
	 */
	private function actor() {
		return self::factory()->post->create(
			array(
				'post_type'   => Remote_Actors::POST_TYPE,
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Marking is idempotent and leaves unrelated actors alone.
	 */
	public function test_cleanup_is_opt_in() {
		$candidate = $this->actor();
		$retained  = $this->actor();
		$this->assertTrue( Remote_Actors::set_cache_only( $candidate, true ) );
		$this->assertTrue( Remote_Actors::set_cache_only( $candidate, true ) );
		Remote_Actor_Cleanup::cleanup();
		$this->assertNull( \get_post( $candidate ) );
		$this->assertNotNull( \get_post( $retained ) );
	}

	/**
	 * Refresh selection is maintenance rather than public access.
	 */
	public function test_maintenance_does_not_rescue_actor() {
		$id = $this->actor();
		Remote_Actors::set_cache_only( $id, true );
		Remote_Actors::get_outdated();
		Remote_Actors::get_faulty();
		$this->assertTrue( (bool) \get_post_meta( $id, Remote_Actor_Cleanup::CACHE_ONLY_META_KEY, true ) );
	}

	/**
	 * Blog ID zero must count as a relationship, and pending follows and blocks matter.
	 */
	public function test_native_relationships_protect_candidates() {
		foreach ( array( '_activitypub_following', '_activitypub_followed_by', '_activitypub_followed_by_pending', '_activitypub_blocked_by' ) as $key ) {
			$id = $this->actor();
			\add_post_meta( $id, $key, '0' );
			Remote_Actors::set_cache_only( $id, true );
			Remote_Actor_Cleanup::cleanup();
			$this->assertNotNull( \get_post( $id ) );
			$this->assertFalse( (bool) \get_post_meta( $id, Remote_Actor_Cleanup::CACHE_ONLY_META_KEY, true ) );
		}
	}

	/**
	 * Trashed interactions retain their actor IDs.
	 */
	public function test_trashed_interaction_protects_actor() {
		$id      = $this->actor();
		$comment = self::factory()->comment->create();
		\add_comment_meta( $comment, '_activitypub_remote_actor_id', $id );
		\wp_trash_comment( $comment );
		Remote_Actors::set_cache_only( $id, true );
		Remote_Actor_Cleanup::cleanup();
		$this->assertNotNull( \get_post( $id ) );
	}

	/**
	 * Only the collection that owns the metadata should protect an actor.
	 *
	 * @dataProvider post_reference_provider
	 * @param string $post_type Referencing post type.
	 * @param string $key Reference metadata key.
	 * @param bool   $preserved Whether this reference must preserve the actor.
	 */
	public function test_post_references_are_scoped_to_their_collection( $post_type, $key, $preserved ) {
		$id = $this->actor();
		\wp_update_post(
			array(
				'ID'   => $id,
				'guid' => 'https://example.com/actor/' . $id,
			)
		);
		$value = '_activitypub_remote_actor_id' === $key ? $id : \get_post( $id )->guid;
		self::factory()->post->create(
			array(
				'post_type'   => $post_type,
				'post_status' => 'trash',
				'meta_input'  => array( $key => $value ),
			)
		);
		Remote_Actors::set_cache_only( $id, true );
		Remote_Actor_Cleanup::cleanup();
		$this->assertSame( $preserved, (bool) \get_post( $id ) );
	}

	/**
	 * Native references and matching metadata on unrelated post types.
	 *
	 * @return array<string, array> Reference cases.
	 */
	public function post_reference_provider() {
		return array(
			'remote post author' => array( Remote_Posts::POST_TYPE, '_activitypub_remote_actor_id', true ),
			'inbox sender'       => array( Inbox::POST_TYPE, '_activitypub_activity_remote_actor', true ),
			'ordinary post'      => array( 'post', '_activitypub_remote_actor_id', false ),
			'wrong collection'   => array( Inbox::POST_TYPE, '_activitypub_remote_actor_id', false ),
		);
	}

	/**
	 * Stored outgoing activities retain referenced actors.
	 */
	public function test_outbox_reference_protects_actor() {
		$id       = $this->actor();
		$activity = new Activity();
		$activity->set_type( 'Like' );
		$activity->set_actor( 'https://example.com/local-actor' );
		$activity->set_object( \get_post( $id )->guid );
		$outbox_id = Outbox::add( $activity, 0 );
		$this->assertIsInt( $outbox_id );
		$this->assertSame( \get_post( $id )->guid, Outbox::get_activity( $outbox_id )->get_object() );
		Remote_Actors::set_cache_only( $id, true );
		Remote_Actor_Cleanup::cleanup();
		$this->assertNotNull( \get_post( $id ) );
	}

	/**
	 * Integrations can promote an actor while inspecting it.
	 */
	public function test_promotion_during_integration_check_protects_actor() {
		$id = $this->actor();
		Remote_Actors::set_cache_only( $id, true );
		$access = static function ( $in_use, $actor ) {
			Remote_Actors::set_cache_only( $actor, false );
			return $in_use;
		};
		\add_filter( 'activitypub_remote_actor_is_in_use', $access, 10, 2 );
		try {
			Remote_Actor_Cleanup::cleanup();
			$this->assertNotNull( \get_post( $id ) );
		} finally {
			\remove_filter( 'activitypub_remote_actor_is_in_use', $access, 10 );
		}
	}
}
