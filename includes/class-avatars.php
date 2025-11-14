<?php
/**
 * Avatars class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Remote_Actors;

/**
 * ActivityPub Avatars class.
 */
class Avatars {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_filter( 'pre_get_avatar_data', array( self::class, 'pre_get_avatar_data' ), 11, 2 );
	}

	/**
	 * Replaces the default avatar.
	 *
	 * @param array             $args        Arguments passed to get_avatar_data(), after processing.
	 * @param int|string|object $id_or_email A user ID, email address, or comment object.
	 *
	 * @return array $args
	 */
	public static function pre_get_avatar_data( $args, $id_or_email ) {
		if (
			! $id_or_email instanceof \WP_Comment ||
			! isset( $id_or_email->comment_type ) ||
			$id_or_email->user_id
		) {
			return $args;
		}

		/**
		 * Filter allowed comment types for avatars.
		 *
		 * @param array $allowed_comment_types Array of allowed comment types.
		 */
		$allowed_comment_types = \apply_filters( 'get_avatar_comment_types', array( 'comment' ) );
		if ( ! \in_array( $id_or_email->comment_type ?: 'comment', $allowed_comment_types, true ) ) {
			return $args;
		}

		$avatar = null;

		// First, try to get avatar from remote actor.
		$remote_actor_id = \get_comment_meta( $id_or_email->comment_ID, '_activitypub_remote_actor_id', true );
		if ( $remote_actor_id ) {
			$avatar = Remote_Actors::get_avatar_url( $remote_actor_id );
		}

		// Fall back to avatar_url comment meta for backward compatibility.
		if ( ! $avatar ) {
			$avatar = \get_comment_meta( $id_or_email->comment_ID, 'avatar_url', true );
		}

		if ( $avatar ) {
			if ( empty( $args['class'] ) ) {
				$args['class'] = array();
			} elseif ( \is_string( $args['class'] ) ) {
				$args['class'] = \explode( ' ', $args['class'] );
			}

			/** This filter is documented in wp-includes/link-template.php */
			$args['url']     = \apply_filters( 'get_avatar_url', $avatar, $id_or_email, $args );
			$args['class'][] = 'avatar';
			$args['class'][] = 'avatar-activitypub';
			$args['class'][] = 'avatar-' . (int) $args['size'];
			$args['class'][] = 'photo';
			$args['class'][] = 'u-photo';
			$args['class']   = \array_unique( $args['class'] );
		}

		return $args;
	}
}
