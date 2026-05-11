<?php
/**
 * Outbox Arrive handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Posts;

use function Activitypub\add_to_outbox;
use function Activitypub\is_activity_public;

/**
 * Handle outgoing Arrive activities.
 *
 * @since 8.1.0
 */
class Arrive {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @since 8.1.0
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_arrive', array( self::class, 'handle_arrive' ), 10, 3 );
	}

	/**
	 * Handle outgoing "Arrive" activities from local actors.
	 *
	 * Arrive is an intransitive activity (no object) indicating that
	 * the actor has arrived at a location. Per the ActivityPub spec,
	 * the server must preserve the original activity type, so this
	 * handler adds the Arrive directly to the outbox as-is.
	 *
	 * As a local side effect, a WordPress post is created so the
	 * check-in appears on the blog with location geodata.
	 *
	 * @since 8.1.0
	 *
	 * @param array       $data       The activity data array.
	 * @param int         $user_id    The user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return int|\WP_Error|false The outbox post ID, error, or false.
	 */
	public static function handle_arrive( $data, $user_id = null, $visibility = null ) {
		// Create a blog post for public check-ins so they appear on the site.
		if ( is_activity_public( $data ) ) {
			$post = self::create_checkin_post( $data, $user_id, $visibility );

			if ( ! \is_wp_error( $post ) ) {
				$data['url'] = \get_permalink( $post );
			}
		}

		/*
		 * Add the original Arrive activity to the outbox directly.
		 * This preserves the intransitive activity type per the
		 * ActivityPub spec (Section 6) instead of wrapping it in Create.
		 */
		$outbox_id = add_to_outbox( $data, null, $user_id, $visibility );

		if ( ! $outbox_id ) {
			return new \WP_Error(
				'activitypub_outbox_error',
				\__( 'Failed to add Arrive activity to outbox.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		return $outbox_id;
	}

	/**
	 * Create a WordPress post from the Arrive activity.
	 *
	 * Creates a blog post with the check-in content and saves
	 * location geodata so it can be displayed on the site.
	 *
	 * @since 8.1.0
	 *
	 * @param array       $data       The activity data.
	 * @param int         $user_id    The user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error The created post or error.
	 */
	private static function create_checkin_post( $data, $user_id, $visibility ) {
		$location      = $data['location'] ?? null;
		$location_name = self::get_location_name( $location );

		$title = $location_name
			? sprintf(
				/* translators: %s: location name */
				\__( 'Checked in at %s', 'activitypub' ),
				$location_name
			)
			: \__( 'Check-in', 'activitypub' );

		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'name'    => $title,
				'content' => $data['content'] ?? $data['summary'] ?? '',
			),
			'to'     => $data['to'] ?? array(),
			'cc'     => $data['cc'] ?? array(),
		);

		$post = Posts::create( $activity, $user_id, $visibility );

		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		self::save_location( $post->ID, $location );

		/**
		 * Fires after an Arrive activity has created a local blog post.
		 *
		 * @since 8.1.0
		 *
		 * @param int        $post_id  The created post ID.
		 * @param array|null $location The location data from the activity.
		 * @param array      $data     The activity data.
		 * @param int        $user_id  The user ID.
		 */
		\do_action( 'activitypub_outbox_arrive_sent', $post->ID, $location, $data, $user_id );

		return $post;
	}

	/**
	 * Save location geodata on a post.
	 *
	 * Uses the standard `geo_*` meta keys that the Post transformer
	 * reads back when converting to ActivityPub Place objects.
	 *
	 * @since 8.1.0
	 *
	 * @param int        $post_id  The post ID.
	 * @param array|null $location The ActivityPub location data.
	 */
	private static function save_location( $post_id, $location ) {
		if ( ! \is_array( $location ) ) {
			return;
		}

		if ( ! empty( $location['name'] ) ) {
			\update_post_meta( $post_id, 'geo_address', \sanitize_text_field( $location['name'] ) );
		}

		if ( isset( $location['latitude'] ) && \is_numeric( $location['latitude'] ) ) {
			\update_post_meta( $post_id, 'geo_latitude', (float) $location['latitude'] );
		}

		if ( isset( $location['longitude'] ) && \is_numeric( $location['longitude'] ) ) {
			\update_post_meta( $post_id, 'geo_longitude', (float) $location['longitude'] );
		}

		if ( ! empty( $location['name'] ) || ( isset( $location['latitude'] ) && isset( $location['longitude'] ) ) ) {
			\update_post_meta( $post_id, 'geo_public', '1' );
		}
	}

	/**
	 * Extract a human-readable name from an ActivityPub location.
	 *
	 * @param mixed $location The location data (array or string).
	 *
	 * @return string|null The location name or null.
	 */
	private static function get_location_name( $location ) {
		if ( \is_array( $location ) && ! empty( $location['name'] ) ) {
			return \sanitize_text_field( $location['name'] );
		}

		if ( \is_string( $location ) && ! empty( $location ) ) {
			return \sanitize_text_field( $location );
		}

		return null;
	}
}
