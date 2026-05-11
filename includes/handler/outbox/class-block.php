<?php
/**
 * Outbox Block handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler\Outbox;

use Activitypub\Moderation;

use function Activitypub\add_to_outbox;
use function Activitypub\object_to_uri;

/**
 * Handle outgoing Block activities.
 */
class Block {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'activitypub_outbox_block', array( self::class, 'handle_block' ), 10, 2 );
	}

	/**
	 * Handle outgoing "Block" activities from local actors.
	 *
	 * Blocks a remote actor using the Moderation system, then adds
	 * the activity to the outbox for federation.
	 *
	 * @since 8.1.0
	 *
	 * @param array $data    The activity data array.
	 * @param int   $user_id The user ID.
	 *
	 * @return array|int|\WP_Error The original data if unhandled, outbox post ID on success, or WP_Error on failure.
	 */
	public static function handle_block( $data, $user_id = null ) {
		$actor_uri = object_to_uri( $data['object'] ?? '' );

		if ( empty( $actor_uri ) ) {
			return $data;
		}

		$result = Moderation::add_user_block( $user_id, Moderation::TYPE_ACTOR, $actor_uri );

		if ( ! $result ) {
			return new \WP_Error(
				'activitypub_block_failed',
				\__( 'Failed to block the actor.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Block activities should only be sent to the blocked actor.
		$data['to'] = array( $actor_uri );
		unset( $data['cc'] );

		return add_to_outbox( $data, 'Block', $user_id, ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE );
	}
}
