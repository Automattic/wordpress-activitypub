<?php
/**
 * ActivityPub Upgrader Class.
 *
 * @package Activitypub
 */

namespace Activitypub;

/**
 * Class to handle plugin upgrades.
 */
class Upgrader {
	/**
	 * Initialize the Upgrader.
	 */
	public static function init() {
		self::maybe_upgrade();
		\add_action( 'activitypub_update_comment_counts', array( self::class, 'update_comment_counts' ), 10, 3 );
	}

	/**
	 * Maybe upgrade the plugin.
	 */
	public static function maybe_upgrade() {
		$version = \get_option( 'activitypub_version' );

		if ( ACTIVITYPUB_PLUGIN_VERSION === $version ) {
			return;
		}

		if ( ! $version || version_compare( $version, '4.5.0', '<' ) ) {
			self::upgrade_to_450();
		}

		\update_option( 'activitypub_version', ACTIVITYPUB_PLUGIN_VERSION );
	}

	/**
	 * Upgrade routine for version 4.5.0.
	 */
	private static function upgrade_to_450() {
		// Skip if already done.
		if ( \get_option( 'activitypub_450_comment_counts_updated' ) ) {
			return;
		}

		// Run the first batch immediately.
		self::update_comment_counts( 100, 0 );
	}

	/**
	 * Update comment counts for posts in batches.
	 *
	 * @see Comment::pre_wp_update_comment_count_now()
	 * @param int $batch_size Optional. Number of posts to process per batch. Default 100.
	 * @param int $offset Optional. Number of posts to skip. Default 0.
	 */
	public static function update_comment_counts( $batch_size = 100, $offset = 0 ) {
		global $wpdb;

		$lock_name = 'activitypub_update_comment_counts.lock';

		// Try to lock.
		$lock_result = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO `$wpdb->options` ( `option_name`, `option_value`, `autoload` ) VALUES (%s, %s, 'no') /* LOCK */", $lock_name, time() ) ); // phpcs:ignore WordPress.DB

		if ( ! $lock_result ) {
			$lock_result = \get_option( $lock_name );

			// Bail if we were unable to create a lock, or if the existing lock is still valid.
			if ( ! $lock_result || ( $lock_result > ( time() - HOUR_IN_SECONDS ) ) ) {
				\wp_schedule_single_event(
					time() + ( 5 * MINUTE_IN_SECONDS ),
					'activitypub_update_comment_counts',
					array(
						'batch_size' => $batch_size,
						'offset'     => $offset,
					)
				);
				return;
			}
		}

		// Update the lock, as by this point we've definitely got a lock.
		\update_option( $lock_name, time() );

		$comment_types  = Comment::get_comment_type_slugs();
		$type_inclusion = "AND comment_type IN ('" . implode( "','", $comment_types ) . "')";

		// Get and process this batch.
		$post_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT DISTINCT comment_post_ID FROM {$wpdb->comments} WHERE comment_approved = '1' {$type_inclusion} ORDER BY comment_post_ID LIMIT %d OFFSET %d",
				$batch_size,
				$offset
			)
		);

		if ( empty( $post_ids ) ) {
			\update_option( 'activitypub_450_comment_counts_updated', true );
			\delete_option( $lock_name );
			return;
		}

		foreach ( $post_ids as $post_id ) {
			\wp_update_comment_count( $post_id );
		}

		// Schedule next batch.
		\wp_schedule_single_event(
			time() + MINUTE_IN_SECONDS,
			'activitypub_update_comment_counts',
			array(
				'batch_size' => $batch_size,
				'offset'     => $offset + $batch_size,
			)
		);

		\delete_option( $lock_name );
	}
}
