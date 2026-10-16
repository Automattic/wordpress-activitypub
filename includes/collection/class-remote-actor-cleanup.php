<?php
/**
 * Opt-in cleanup of cached remote actors.
 *
 * @package Activitypub
 */

namespace Activitypub\Collection;

use Activitypub\Moderation;

/**
 * Queue cache entries for deletion without treating remote accounts as deleted.
 *
 * @since unreleased
 */
class Remote_Actor_Cleanup {
	/**
	 * Private taxonomy used as an indexed candidate queue.
	 *
	 * @var string
	 */
	const TAXONOMY = 'ap_actor_cleanup';

	/**
	 * Shared candidate term.
	 *
	 * @var string
	 */
	const CANDIDATE = 'candidate';

	/**
	 * Nominate an actor; no existing actors are nominated automatically.
	 *
	 * @param int $id Remote actor post ID.
	 * @return bool Whether the actor was marked.
	 */
	public static function mark( $id ) {
		if ( $id <= 0 ) {
			return false;
		}
		$post = \get_post( $id );
		if ( ! $post || Remote_Actors::POST_TYPE !== $post->post_type ) {
			return false;
		}
		$result = \wp_set_object_terms( $post->ID, self::CANDIDATE, self::TAXONOMY, true );
		if ( \is_wp_error( $result ) ) {
			return false;
		}
		if ( ! \wp_next_scheduled( 'activitypub_cleanup_actor_candidates' ) ) {
			\wp_schedule_event( \time() + DAY_IN_SECONDS, 'daily', 'activitypub_cleanup_actor_candidates' );
		}
		return true;
	}

	/**
	 * Rescue an actor on public API access. Unmarked actors only read term cache.
	 *
	 * @param int $id Remote actor post ID.
	 * @return void
	 */
	public static function accessed( $id ) {
		$terms = \get_the_terms( $id, self::TAXONOMY );
		if ( ! $terms || \is_wp_error( $terms ) ) {
			return;
		}
		foreach ( $terms as $term ) {
			if ( self::CANDIDATE === $term->slug ) {
				\wp_remove_object_terms( $id, $term->term_id, self::TAXONOMY );
				return;
			}
		}
	}

	/**
	 * Process one bounded batch using stored posts, without rescuing candidates.
	 *
	 * @param int $batch Continuation batch number used by the async scheduler.
	 * @return int[]|null Next batch arguments when progress was made.
	 */
	public static function cleanup( $batch = 0 ) {
		$limit = \max( 1, (int) \apply_filters( 'activitypub_actor_cleanup_batch_size', 20 ) );
		$query = new \WP_Query(
			array(
				'post_type'      => Remote_Actors::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				'tax_query'      => array(
					array(
						'taxonomy' => self::TAXONOMY,
						'field'    => 'slug',
						'terms'    => self::CANDIDATE,
					),
				),
			)
		);
		if ( ! $query->posts ) {
			return null;
		}

		// Parse stored outgoing activities once per batch, not once per actor.
		$outbox_uris = self::get_outbox_uris();
		$progress    = false;
		foreach ( $query->posts as $actor ) {
			$in_use = isset( $outbox_uris[ $actor->guid ] ) || self::is_in_use( $actor );
			/**
			 * Allow integrations to preserve references unknown to ActivityPub.
			 *
			 * Returning false cannot override a native reference.
			 *
			 * @since unreleased
			 *
			 * @param bool     $in_use Whether an integration needs the actor.
			 * @param \WP_Post $actor  Stored actor, without triggering access cancellation.
			 */
			$in_use = \apply_filters( 'activitypub_remote_actor_is_in_use', false, $actor ) || $in_use;
			if ( $in_use ) {
				self::accessed( $actor->ID );
				$progress = true;
				continue;
			}
			// An integration may have accessed the actor while inspecting it.
			if ( \has_term( self::CANDIDATE, self::TAXONOMY, $actor ) ) {
				$progress = (bool) Remote_Actors::delete( $actor->ID ) || $progress;
			} else {
				$progress = true;
			}
		}
		if ( $progress && \count( $query->posts ) === $limit ) {
			return array( $batch + 1 );
		}
		return null;
	}

	/**
	 * Check durable native references, including blog ID zero and trashed content.
	 *
	 * @param \WP_Post $actor Stored actor.
	 * @return bool Whether the actor is referenced.
	 */
	private static function is_in_use( $actor ) {
		foreach ( array( Followers::FOLLOWER_META_KEY, Following::FOLLOWING_META_KEY, Following::PENDING_META_KEY, Moderation::BLOCKED_ACTORS_META_KEY ) as $key ) {
			if ( \metadata_exists( 'post', $actor->ID, $key ) ) {
				return true;
			}
		}
		$query = new \WP_Query(
			array(
				'post_type'      => \array_values( \get_post_types() ),
				'post_status'    => \array_values( \get_post_stati() ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_activitypub_remote_actor_id',
						'value' => $actor->ID,
					),
					array(
						'key'   => '_activitypub_activity_remote_actor',
						'value' => $actor->guid,
					),
				),
			)
		);
		if ( $query->posts ) {
			return true;
		}
		$comments = \get_comments(
			array(
				'status'     => 'any',
				'number'     => 1,
				'fields'     => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'   => '_activitypub_remote_actor_id',
						'value' => $actor->ID,
					),
				),
			)
		);
		return ! empty( $comments ) || ! empty(
			\get_comments(
				array(
					'status'     => 'any',
					'number'     => 1,
					'fields'     => 'ids',
					'author_url' => $actor->guid,
				)
			)
		);
	}

	/**
	 * Collect URI references without searching JSON text or touching actor APIs.
	 *
	 * @return array<string, true> Referenced strings from stored outgoing activities.
	 */
	private static function get_outbox_uris() {
		$uris = array();
		$page = 1;
		do {
			$query = new \WP_Query(
				array(
					'post_type'      => Outbox::POST_TYPE,
					'post_status'    => \array_values( \get_post_stati() ),
					'posts_per_page' => 100,
					'paged'          => $page++,
					'orderby'        => 'ID',
					'order'          => 'ASC',
					'no_found_rows'  => true,
				)
			);
			foreach ( $query->posts as $post ) {
				$data = \json_decode( $post->post_content, true );
				if ( \is_array( $data ) ) {
					\array_walk_recursive(
						$data,
						static function ( $value ) use ( &$uris ) {
							if ( \is_string( $value ) ) {
								$uris[ $value ] = true;
							}
						}
					);
				}
			}
			$count = \count( $query->posts );
		} while ( 100 === $count );
		return $uris;
	}
}
