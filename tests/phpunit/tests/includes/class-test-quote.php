<?php
/**
 * Test file for the outgoing quote handshake.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;
use Activitypub\Quote;
use Activitypub\Transformer\Post;

/**
 * Test class for Activitypub\Quote.
 *
 * @coversDefaultClass \Activitypub\Quote
 */
class Test_Quote extends \WP_UnitTestCase {

	/**
	 * Author user ID.
	 *
	 * @var int
	 */
	protected static $user_id;

	/**
	 * Remote object mock.
	 *
	 * @var callable
	 */
	protected $remote_object_filter;

	/**
	 * Create the author.
	 *
	 * @param \WP_UnitTest_Factory $factory Factory.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		self::$user_id = $factory->user->create( array( 'role' => 'author' ) );
		\get_user_by( 'id', self::$user_id )->add_cap( 'activitypub' );
	}

	/**
	 * Mock the quoted object and its author.
	 */
	public function set_up() {
		parent::set_up();

		$this->remote_object_filter = function ( $pre, $url ) {
			if ( 'https://remote.example/notes/1' === $url ) {
				return array(
					'id'           => 'https://remote.example/notes/1',
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
		$this->assertSame( $requests[0]->guid, \get_post_meta( $post_id, '_activitypub_quote_request', true ) );
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

		$second = function ( $pre, $url ) {
			if ( 'https://remote.example/notes/2' === $url ) {
				return array(
					'id'           => 'https://remote.example/notes/2',
					'type'         => 'Note',
					'attributedTo' => 'https://remote.example/users/alice',
				);
			}
			return $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $second, 10, 2 );

		\wp_update_post(
			array(
				'ID'           => $post_id,
				'post_content' => '<!-- wp:activitypub/quote {"url":"https://remote.example/notes/2"} /-->',
			)
		);

		\remove_filter( 'activitypub_pre_http_get_remote_object', $second );

		$this->assertCount( 2, $this->get_quote_requests( $post_id ) );
		$this->assertEmpty( \get_post_meta( $post_id, '_activitypub_quote_authorization', true ) );
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
}
