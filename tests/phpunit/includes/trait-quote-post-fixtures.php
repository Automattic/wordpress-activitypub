<?php
/**
 * Shared fixtures for FEP-044f quote post tests.
 *
 * @package Activitypub
 */

namespace Activitypub\Tests;

use Activitypub\Collection\Outbox;

use function Activitypub\get_object_id;

/**
 * Fixtures for tests that publish a local quote post and answer its QuoteRequest.
 *
 * The using class must provide a static `$user_id` of an author with the `activitypub` capability.
 */
trait Quote_Post_Fixtures {
	/**
	 * The remote object mock, kept so tear_down() can remove it.
	 *
	 * @var callable
	 */
	protected $quoted_object_filter;

	/**
	 * Serve the quoted notes and their author without HTTP.
	 */
	protected function add_quoted_object_mock() {
		$this->quoted_object_filter = function ( $pre, $url ) {
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
		\add_filter( 'activitypub_pre_http_get_remote_object', $this->quoted_object_filter, 10, 2 );
	}

	/**
	 * Remove the remote object mock.
	 */
	protected function remove_quoted_object_mock() {
		\remove_filter( 'activitypub_pre_http_get_remote_object', $this->quoted_object_filter );
	}

	/**
	 * Create a published quote post and return its ID.
	 *
	 * @param string $url The quoted URL.
	 *
	 * @return int Post ID.
	 */
	protected function create_quote_post( $url = 'https://remote.example/notes/1' ) {
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
	protected function get_quote_requests( $post_id ) {
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
					return get_object_id( \get_post( $post_id ) ) === ( $activity['instrument'] ?? '' );
				}
			)
		);
	}

	/**
	 * Build the Accept the quoted author's server would send for our request.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $result  Stamp URI.
	 * @param string $actor   Sender.
	 *
	 * @return array Accept activity.
	 */
	protected function build_accept( $post_id, $result = 'https://remote.example/stamps/1', $actor = 'https://remote.example/users/alice' ) {
		$requests = $this->get_quote_requests( $post_id );

		return array(
			'type'   => 'Accept',
			'actor'  => $actor,
			'object' => array(
				'id'         => $requests ? $requests[0]->guid : '',
				'type'       => 'QuoteRequest',
				'actor'      => \get_author_posts_url( self::$user_id ),
				'object'     => 'https://remote.example/notes/1',
				'instrument' => get_object_id( \get_post( $post_id ) ),
			),
			'result' => $result,
		);
	}

	/**
	 * Build the Reject the quoted author's server would send for our request.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $actor   Sender.
	 *
	 * @return array Reject activity.
	 */
	protected function build_reject( $post_id, $actor = 'https://remote.example/users/alice' ) {
		$reject         = $this->build_accept( $post_id, '', $actor );
		$reject['type'] = 'Reject';
		unset( $reject['result'] );

		return $reject;
	}

	/**
	 * Mock a QuoteAuthorization stamp for the given post.
	 *
	 * @param int   $post_id   Post ID.
	 * @param array $overrides Fields to override.
	 *
	 * @return callable The filter, to remove later.
	 */
	protected function mock_stamp( $post_id, $overrides = array() ) {
		$stamp  = \array_merge(
			array(
				'id'                => 'https://remote.example/stamps/1',
				'type'              => 'QuoteAuthorization',
				'attributedTo'      => 'https://remote.example/users/alice',
				'interactingObject' => get_object_id( \get_post( $post_id ) ),
				'interactionTarget' => 'https://remote.example/notes/1',
			),
			$overrides
		);
		$filter = function ( $pre, $url ) use ( $stamp ) {
			return 'https://remote.example/stamps/1' === $url ? $stamp : $pre;
		};
		\add_filter( 'activitypub_pre_http_get_remote_object', $filter, 10, 2 );

		return $filter;
	}

	/**
	 * Mock the HTTP response the stamp URL gives when fetched directly (tombstone check).
	 *
	 * @param int    $code Response code.
	 * @param string $body Response body.
	 *
	 * @return callable The filter, to remove later.
	 */
	protected function mock_stamp_response( $code, $body = '' ) {
		$filter = function ( $pre, $args, $url ) use ( $code, $body ) {
			if ( 'https://remote.example/stamps/1' !== $url ) {
				return $pre;
			}

			return array(
				'response' => array( 'code' => $code ),
				'headers'  => array( 'content-type' => 'application/activity+json' ),
				'body'     => $body,
			);
		};
		\add_filter( 'pre_http_request', $filter, 10, 3 );

		return $filter;
	}

	/**
	 * Count Update outbox items for a post.
	 *
	 * @param int $post_id Post ID.
	 *
	 * @return int Count.
	 */
	protected function count_updates( $post_id ) {
		return \count(
			\get_posts(
				array(
					'post_type'   => Outbox::POST_TYPE,
					'post_status' => 'any',
					'numberposts' => -1,
					'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
						array(
							'key'   => '_activitypub_activity_type',
							'value' => 'Update',
						),
						array(
							'key'   => '_activitypub_object_id',
							'value' => get_object_id( \get_post( $post_id ) ),
						),
					),
				)
			)
		);
	}
}
