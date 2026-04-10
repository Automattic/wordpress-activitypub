<?php
/**
 * Outbox Undo handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Collection\Outbox as Outbox_Collection;
use Activitypub\Moderation;

use function Activitypub\object_to_uri;
use function Activitypub\unfollow;

/**
 * Handle outgoing Undo activities.
 */
class Undo {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_undo', array( self::class, 'handle_undo' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Undo" activities from local actors.
	 *
	 * Resolves the referenced activity from the outbox and delegates
	 * to the appropriate collection method to reverse its side effects
	 * and create the Undo activity.
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return int|\WP_Error The undo outbox item ID, or WP_Error on failure.
	 */
	public static function handle_undo( $data, $user_id = null ) {
		$id = object_to_uri( $data['object'] ?? '' );

		if ( empty( $id ) ) {
			return $data;
		}

		$outbox_item = Outbox_Collection::get_by_guid( $id );

		if ( \is_wp_error( $outbox_item ) ) {
			return $data;
		}

		// Verify the user owns this outbox item (blog actor user_id === 0 can undo any).
		if ( $user_id > 0 && (int) $outbox_item->post_author !== $user_id ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only undo your own activities.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$activity_type = \get_post_meta( $outbox_item->ID, '_activitypub_activity_type', true );

		switch ( $activity_type ) {
			case 'Follow':
				$stored = \json_decode( $outbox_item->post_content, true );
				$target = object_to_uri( $stored['object'] ?? '' );

				if ( $target ) {
					return unfollow( $target, $user_id );
				}

				return $data;

			case 'Block':
				$stored    = \json_decode( $outbox_item->post_content, true );
				$actor_uri = \is_array( $stored ) ? object_to_uri( $stored['object'] ?? '' ) : '';

				if ( $actor_uri ) {
					Moderation::remove_user_block( $user_id, Moderation::TYPE_ACTOR, $actor_uri );
				}

				return Outbox_Collection::undo( $outbox_item );

			default:
				return Outbox_Collection::undo( $outbox_item );
		}
	}
}
