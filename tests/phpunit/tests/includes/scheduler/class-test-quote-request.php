<?php
/**
 * Test file for the Quote Request scheduler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\Tests\Quote_Post_Fixtures;

use function Activitypub\get_object_id;

/**
 * Test class for Activitypub\Scheduler\Quote_Request.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Quote_Request
 */
class Test_Quote_Request extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {
	use Quote_Post_Fixtures;



	/**
	 * Mock the quoted objects and their author.
	 */
	public function set_up() {
		parent::set_up();

		$this->add_quoted_object_mock();
	}

	/**
	 * Remove the mock.
	 */
	public function tear_down() {
		$this->remove_quoted_object_mock();
		parent::tear_down();
	}

	/**
	 * Return the newest Create/Update outbox item for a post, decoded.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return array The activity.
	 */
	private function get_latest_post_activity( $post_id ) {
		$items = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => 1,
				'orderby'     => 'ID',
				'order'       => 'DESC',
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_activitypub_activity_type',
						'value'   => array( 'Create', 'Update' ),
						'compare' => 'IN',
					),
					array(
						'key'   => '_activitypub_object_id',
						'value' => get_object_id( \get_post( $post_id ) ),
					),
				),
			)
		);

		return $items ? \json_decode( $items[0]->post_content, true ) : array();
	}

	/**
	 * Publishing a quote post sends exactly one QuoteRequest to the quoted author.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_publish_sends_quote_request() {
		$post_id  = $this->create_quote_post();
		$requests = $this->get_quote_requests( $post_id );

		$this->assertCount( 1, $requests );

		$activity = \json_decode( $requests[0]->post_content, true );
		$this->assertSame( 'QuoteRequest', $activity['type'] );
		$this->assertSame( 'https://remote.example/notes/1', $activity['object'] );
		$this->assertSame( get_object_id( \get_post( $post_id ) ), $activity['instrument'] );
		$this->assertSame( array( 'https://remote.example/users/alice' ), $activity['to'] );
		$this->assertSame( \ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE, \get_post_meta( $requests[0]->ID, 'activitypub_content_visibility', true ) );
		$this->assertSame( 'https://remote.example/notes/1', \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
	}

	/**
	 * An unrelated edit does not send a second request.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_update_does_not_resend_request() {
		$post_id = $this->create_quote_post();

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:paragraph --><p>edit</p><!-- /wp:paragraph -->' . PHP_EOL . '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/1"} /-->',
			)
		);

		$this->assertCount( 1, $this->get_quote_requests( $post_id ) );
	}

	/**
	 * Changing the quoted URL clears the old state and sends a new request.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_changed_url_resends_request() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/2"} /-->',
			)
		);

		$this->assertCount( 2, $this->get_quote_requests( $post_id ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertSame( 'https://remote.example/notes/2', \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
	}

	/**
	 * A URL change whose new object cannot be fetched clears the stale state for the old
	 * URL, and only records the new one once a request for it actually goes out.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_changed_url_clears_request_meta_when_new_request_fails() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );

		$filter = function ( $pre, $url ) {
			return 'https://remote.example/notes/gone' === $url ? new \WP_Error( 'http_request_failed', 'nope' ) : $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/gone"} /-->',
			)
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		// The fetch for the new URL failed: no new request went out, but the old URL's state is gone.
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_rejected', true ) );
		$this->assertCount( 1, $this->get_quote_requests( $post_id ) );

		// A further edit that reaches the quoted object sends the request and records it.
		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/2"} /-->',
			)
		);

		$this->assertCount( 2, $this->get_quote_requests( $post_id ) );
		$this->assertSame( 'https://remote.example/notes/2', \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
	}

	/**
	 * No request for stamped, rejected or non-quote posts.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_no_request_when_stamped_rejected_or_plain() {
		$stamped = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/1"} /-->',
			)
		);
		\update_post_meta( $stamped, '_activitypub_quote_request', 'https://remote.example/notes/1' );
		\update_post_meta( $stamped, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );
		\wp_publish_post( $stamped );
		$this->assertCount( 0, $this->get_quote_requests( $stamped ) );

		$rejected = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/1"} /-->',
			)
		);
		\update_post_meta( $rejected, '_activitypub_quote_request', 'https://remote.example/notes/1' );
		\update_post_meta( $rejected, '_activitypub_quote_rejected', '1' );
		\wp_publish_post( $rejected );
		$this->assertCount( 0, $this->get_quote_requests( $rejected ) );

		$plain = self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>no quote</p><!-- /wp:paragraph -->',
			)
		);
		$this->assertCount( 0, $this->get_quote_requests( $plain ) );
	}

	/**
	 * Quoting our own object needs no consent, so no request is sent.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_self_quote_sends_no_request() {
		$own    = Actors::get_by_id( self::$user_id )->get_id();
		$filter = function ( $pre, $url ) use ( $own ) {
			if ( 'https://remote.example/notes/mine' === $url ) {
				return array(
					'id'           => 'https://remote.example/notes/mine',
					'type'         => 'Note',
					'attributedTo' => $own,
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$post_id = $this->create_quote_post( 'https://remote.example/notes/mine' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertCount( 0, $this->get_quote_requests( $post_id ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
	}

	/**
	 * An unreachable quoted object sends no request and records nothing.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_unreachable_quoted_object_sends_no_request() {
		$filter = function ( $pre, $url ) {
			return 'https://remote.example/notes/gone' === $url ? new \WP_Error( 'http_request_failed', 'nope' ) : $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		$post_id = $this->create_quote_post( 'https://remote.example/notes/gone' );

		\remove_filter( 'activitypub_pre_http_get_remote_object', $filter );

		$this->assertCount( 0, $this->get_quote_requests( $post_id ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
	}

	/**
	 * Two posts quoting the same object each keep their own pending request.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_two_posts_quoting_same_url_both_get_requests() {
		$first  = $this->create_quote_post();
		$second = $this->create_quote_post();

		$first_requests  = $this->get_quote_requests( $first );
		$second_requests = $this->get_quote_requests( $second );

		$this->assertCount( 1, $first_requests );
		$this->assertCount( 1, $second_requests );
		$this->assertSame( 'pending', $first_requests[0]->post_status );
		$this->assertSame( 'pending', $second_requests[0]->post_status );
	}

	/**
	 * The first Update after a URL change carries the new quote without the old stamp.
	 *
	 * @covers ::maybe_send_request
	 */
	public function test_first_update_after_url_change_has_no_stamp() {
		$post_id = $this->create_quote_post();
		\update_post_meta( $post_id, '_activitypub_quote_authorization', 'https://remote.example/stamps/1' );

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/2"} /-->',
			)
		);

		$activity = $this->get_latest_post_activity( $post_id );

		$this->assertSame( 'Update', $activity['type'] );
		$this->assertSame( 'https://remote.example/notes/2', $activity['object']['quote'] );
		$this->assertArrayNotHasKey( 'quoteAuthorization', $activity['object'] );
	}
}
