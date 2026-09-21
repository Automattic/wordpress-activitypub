<?php
/**
 * Test file for the Quote Request scheduler.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests\Scheduler;

use Activitypub\Collection\Outbox;

/**
 * Test class for Activitypub\Scheduler\Quote_Request.
 *
 * @coversDefaultClass \Activitypub\Scheduler\Quote_Request
 */
class Test_Quote_Request extends \Activitypub\Tests\ActivityPub_Outbox_TestCase {

	/**
	 * Remote object mock.
	 *
	 * @var callable
	 */
	protected $remote_object_filter;

	/**
	 * Mock the quoted objects and their author.
	 */
	public function set_up() {
		parent::set_up();

		$this->remote_object_filter = function ( $pre, $url ) {
			if ( \in_array( $url, array( 'https://remote.example/notes/1', 'https://remote.example/notes/2' ), true ) ) {
				return array(
					'id'           => $url,
					'type'         => 'Note',
					'attributedTo' => 'https://remote.example/users/alice',
				);
			}
			if ( 'https://remote.example/users/alice' === $url ) {
				return array(
					'id'    => 'https://remote.example/users/alice',
					'type'  => 'Person',
					'inbox' => 'https://remote.example/users/alice/inbox',
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $this->remote_object_filter, 10, 2 );
	}

	/**
	 * Remove the mock.
	 */
	public function tear_down() {
		\remove_filter( 'activitypub_pre_http_get_remote_object', $this->remote_object_filter );
		parent::tear_down();
	}

	/**
	 * Create a published quote post and return its ID.
	 *
	 * @param string $url The quoted URL.
	 *
	 * @return int Post ID.
	 */
	private function create_quote_post( $url = 'https://remote.example/notes/1' ) {
		return self::factory()->post->create(
			array(
				'post_author'  => self::$user_id,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:activitypub/quote {"url":"' . $url . '"} /-->',
			)
		);
	}

	/**
	 * Return the QuoteRequest outbox items for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return \WP_Post[] Outbox items.
	 */
	private function get_quote_requests( $post_id ) {
		$items = \get_posts(
			array(
				'post_type'   => Outbox::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'meta_key'    => '_activitypub_activity_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => 'QuoteRequest', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		// The outbox stores the quoted URI as object id; our post is the request's instrument.
		return \array_values(
			\array_filter(
				$items,
				function ( $item ) use ( $post_id ) {
					$activity = \json_decode( $item->post_content, true );
					return \get_permalink( $post_id ) === ( $activity['instrument'] ?? '' );
				}
			)
		);
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
						'value' => \get_permalink( $post_id ),
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
		$this->assertSame( \get_permalink( $post_id ), $activity['instrument'] );
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
