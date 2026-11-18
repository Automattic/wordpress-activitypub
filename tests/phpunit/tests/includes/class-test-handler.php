<?php
/**
 * Handler Test Class
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Outbox;
use Activitypub\Handler;
use WP_UnitTestCase;

use function Activitypub\add_to_outbox;
use function Activitypub\snake_to_camel_case;

/**
 * Handler Test Class
 */
class Test_Handler extends WP_UnitTestCase {

	/**
	 * The user ID.
	 *
	 * @var int
	 */
	protected $user_id;

	/**
	 * Set up the test.
	 */
	public function set_up() {
		parent::set_up();
		$this->user_id = self::factory()->user->create(
			array(
				'role' => 'administrator',
			)
		);
	}

	/**
	 * Test the inherit functionality
	 */
	public function test_activity() {
		// Create a mock inherit activity.
		$activity = new Activity();
		$activity->set_type( 'Move' );
		$activity->set_id( 'https://example.com/activity/1' );
		$activity->set_to( array( 'https://example.com/to' ) );
		$activity->set_cc( array( 'https://example.com/cc' ) );

		$id = add_to_outbox( $activity, null, $this->user_id );

		$outbox_item     = get_post( $id );
		$outbox_activity = Outbox::get_activity( $outbox_item );

		$this->assertEquals( 'Move', $outbox_activity->get_type() );
	}

	/**
	 * No request handler answers with an Accept for a post that is not public.
	 *
	 * Loops over every registered `*_request` inbox handler, so a new request type has to
	 * decide this before its tests pass.
	 */
	public function test_request_handlers_do_not_accept_non_public_posts() {
		global $wp_filter;

		Handler::register_handlers();

		$types = array();
		foreach ( \array_keys( $wp_filter ) as $hook ) {
			if ( \preg_match( '/^activitypub_inbox_([a-z_]+_request)$/', $hook, $matches ) ) {
				$types[] = $matches[1];
			}
		}

		$this->assertContains( 'quote_request', $types );
		$this->assertContains( 'feature_request', $types );

		\update_option( 'activitypub_default_quote_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );
		\update_option( 'activitypub_default_feature_policy', ACTIVITYPUB_INTERACTION_POLICY_ANYONE );

		$actor      = 'https://remote.example/users/alice';
		$instrument = 'https://remote.example/objects/1';
		$fetch      = static function ( $pre, $id ) use ( $actor, $instrument ) {
			if ( $instrument !== $id ) {
				return $pre;
			}

			return array(
				'id'           => $instrument,
				'type'         => 'Note',
				'attributedTo' => $actor,
			);
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10, 2 );

		$posts = array(
			'published'          => array( 'post_status' => 'publish' ),
			'draft'              => array( 'post_status' => 'draft' ),
			'private'            => array( 'post_status' => 'private' ),
			'password protected' => array(
				'post_status'   => 'publish',
				'post_password' => 'secret',
			),
		);

		foreach ( $posts as $label => $post_args ) {
			$post_id = self::factory()->post->create( \array_merge( array( 'post_author' => $this->user_id ), $post_args ) );

			foreach ( $types as $type ) {
				$activity = array(
					'id'         => \sprintf( 'https://remote.example/activities/%s/%d', $type, $post_id ),
					'type'       => \ucfirst( snake_to_camel_case( $type ) ),
					'actor'      => $actor,
					'object'     => \add_query_arg( 'p', $post_id, \home_url( '/' ) ),
					'instrument' => $instrument,
				);

				\do_action( \sprintf( 'activitypub_inbox_%s', $type ), $activity, $this->user_id, null, Inbox::CONTEXT_INBOX );

				$accepts = \get_posts(
					array(
						'post_type'   => Outbox::POST_TYPE,
						'post_status' => 'any',
						'fields'      => 'ids',
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						'meta_query'  => array(
							array(
								'key'   => '_activitypub_activity_type',
								'value' => 'Accept',
							),
						),
					)
				);

				if ( 'published' === $label && 'quote_request' === $type ) {
					// The control case: a public post is accepted, so the other cases are not refused for another reason.
					$this->assertCount( 1, $accepts, 'A quote of a public post is accepted.' );
					\wp_delete_post( $accepts[0], true );
					continue;
				}

				if ( 'published' === $label ) {
					continue;
				}

				$this->assertEmpty( $accepts, \sprintf( '%s answers a %s post with an Accept.', $type, $label ) );
			}
		}

		\remove_filter( 'activitypub_pre_http_get_remote_object', $fetch, 10 );
		\delete_option( 'activitypub_default_quote_policy' );
		\delete_option( 'activitypub_default_feature_policy' );
	}
}
