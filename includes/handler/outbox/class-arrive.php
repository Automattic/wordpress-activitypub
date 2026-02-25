<?php
/**
 * Outbox Arrive handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Posts;

use function Activitypub\is_activity_public;

/**
 * Handle outgoing Arrive activities.
 *
 * @since unreleased
 */
class Arrive {
	/**
	 * Initialize the class, registering WordPress hooks.
	 *
	 * @since unreleased
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_arrive', array( self::class, 'handle_arrive' ), 10, 3 );
		\add_action( 'activitypub_outbox_arrive_sent', array( self::class, 'save_location' ), 10, 4 );
	}

	/**
	 * Handle outgoing "Arrive" activities from local actors.
	 *
	 * Arrive is an intransitive activity indicating that the actor
	 * has arrived at a location. Creates a WordPress post so the
	 * check-in appears on the blog. Location geodata is saved via
	 * the `activitypub_outbox_arrive_sent` action.
	 *
	 * @since unreleased
	 *
	 * @param array       $data       The activity data array.
	 * @param int         $user_id    The user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error|false The created post, error, or false.
	 */
	public static function handle_arrive( $data, $user_id = null, $visibility = null ) {
		if ( ! is_activity_public( $data ) ) {
			return false;
		}

		$location_name = self::get_location_name( $data['location'] ?? null );

		$title = $location_name
			? sprintf(
				/* translators: %s: location name */
				\__( 'Checked in at %s', 'activitypub' ),
				$location_name
			)
			: \__( 'Check-in', 'activitypub' );

		/*
		 * Synthesize a Create-style activity for Posts::create().
		 * Arrive is intransitive (no object), so we build a Note
		 * from the activity-level content/summary.
		 */
		$activity = array(
			'object' => array(
				'type'    => 'Note',
				'name'    => $title,
				'content' => $data['content'] ?? self::get_summary( $data ),
			),
			'to'     => $data['to'] ?? array(),
			'cc'     => $data['cc'] ?? array(),
		);

		$post = Posts::create( $activity, $user_id, $visibility );

		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		/**
		 * Fires after an outgoing Arrive activity has created a post.
		 *
		 * @param int        $post_id    The created post ID.
		 * @param array|null $location   The location data from the activity.
		 * @param array      $data       The activity data.
		 * @param int        $user_id    The user ID.
		 */
		\do_action( 'activitypub_outbox_arrive_sent', $post->ID, $data['location'] ?? null, $data, $user_id );

		return $post;
	}

	/**
	 * Save location geodata on the created post.
	 *
	 * Hooked to `activitypub_outbox_arrive_sent`. Uses the standard
	 * `geo_*` meta keys that the Post transformer reads back when
	 * converting to ActivityPub Place objects.
	 *
	 * @since unreleased
	 *
	 * @param int        $post_id  The post ID.
	 * @param array|null $location The ActivityPub location data.
	 * @param array      $data     The activity data.
	 * @param int        $user_id  The user ID.
	 */
	public static function save_location( $post_id, $location, $data, $user_id ) {
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
	 * Extract the summary string from an Arrive activity.
	 *
	 * Supports both `summary` (string) and `summaryMap` (language map).
	 *
	 * @param array $data The activity data.
	 *
	 * @return string The summary text.
	 */
	private static function get_summary( $data ) {
		if ( ! empty( $data['summary'] ) ) {
			return $data['summary'];
		}

		if ( ! empty( $data['summaryMap'] ) && \is_array( $data['summaryMap'] ) ) {
			return \reset( $data['summaryMap'] );
		}

		return '';
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
