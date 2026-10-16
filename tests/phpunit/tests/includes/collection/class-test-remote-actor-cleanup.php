<?php
/**
 * Tests for opt-in remote actor cleanup.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Collection;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Outbox;
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
		$this->assertTrue( Remote_Actors::mark_for_cleanup( $candidate ) );
		$this->assertTrue( Remote_Actors::mark_for_cleanup( $candidate ) );
		Remote_Actor_Cleanup::cleanup();
		$this->assertNull( \get_post( $candidate ) );
		$this->assertNotNull( \get_post( $retained ) );
	}

	/**
	 * Refresh selection is maintenance rather than public access.
	 */
	public function test_maintenance_does_not_rescue_actor() {
		$id = $this->actor();
		Remote_Actors::mark_for_cleanup( $id );
		Remote_Actors::get_outdated();
		Remote_Actors::get_faulty();
		$this->assertTrue( \has_term( Remote_Actor_Cleanup::CANDIDATE, Remote_Actor_Cleanup::TAXONOMY, $id ) );
	}

	/**
	 * Blog ID zero must count as a relationship, and pending follows and blocks matter.
	 */
	public function test_native_relationships_protect_candidates() {
		foreach ( array( '_activitypub_following', '_activitypub_followed_by', '_activitypub_followed_by_pending', '_activitypub_blocked_by' ) as $key ) {
			$id = $this->actor();
			\add_post_meta( $id, $key, '0' );
			Remote_Actors::mark_for_cleanup( $id );
			Remote_Actor_Cleanup::cleanup();
			$this->assertNotNull( \get_post( $id ) );
			$this->assertFalse( \has_term( Remote_Actor_Cleanup::CANDIDATE, Remote_Actor_Cleanup::TAXONOMY, $id ) );
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
		Remote_Actors::mark_for_cleanup( $id );
		Remote_Actor_Cleanup::cleanup();
		$this->assertNotNull( \get_post( $id ) );
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
		Remote_Actors::mark_for_cleanup( $id );
		Remote_Actor_Cleanup::cleanup();
		$this->assertNotNull( \get_post( $id ) );
	}

	/**
	 * Integrations can veto deletion or rescue an actor through a lookup.
	 */
	public function test_access_during_integration_check_rescues_actor() {
		$id = $this->actor();
		Remote_Actors::mark_for_cleanup( $id );
		$access = static function ( $in_use, $actor ) {
			Remote_Actors::get( $actor->ID );
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
