<?php
/**
 * Transformer Factory Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use WP_Error;

use function Activitypub\is_user_disabled;
use function Activitypub\is_post_disabled;
use function Activitypub\is_local_comment;
/**
 * Transformer Factory.
 */
class Factory {
	/**
	 * Get the transformer for a given object.
	 *
	 * @param mixed $data The object to transform.
	 *
	 * @return Base|WP_Error The transformer to use, or an error.
	 */
	public static function get_transformer( $data ) {
		if ( ! \is_object( $data ) ) {
			return new WP_Error( 'invalid_object', __( 'Invalid object', 'activitypub' ) );
		}

		$class = \get_class( $data );

		/**
		 * Filter the transformer for a given object.
		 *
		 * Add your own transformer based on the object class or the object type.
		 *
		 * Example usage:
		 *
		 * // Filter be object class
		 * add_filter( 'activitypub_transformer', function( $transformer, $object, $object_class ) {
		 *     if ( $object_class === 'WP_Post' ) {
		 *         return new My_Post_Transformer( $object );
		 *     }
		 *     return $transformer;
		 * }, 10, 3 );
		 *
		 * // Filter be object type
		 * add_filter( 'activitypub_transformer', function( $transformer, $object, $object_class ) {
		 *     if ( $object->post_type === 'event' ) {
		 *         return new My_Event_Transformer( $object );
		 *     }
		 *     return $transformer;
		 * }, 10, 3 );
		 *
		 * @param Base   $transformer  The transformer to use.
		 * @param mixed  $data         The object to transform.
		 * @param string $object_class The class of the object to transform.
		 *
		 * @return mixed The transformer to use.
		 */
		$transformer = \apply_filters( 'activitypub_transformer', null, $data, $class );

		if ( $transformer ) {
			if (
				! \is_object( $transformer ) ||
				! $transformer instanceof Base
			) {
				return new WP_Error( 'invalid_transformer', __( 'Invalid transformer', 'activitypub' ) );
			}

			return $transformer;
		}

		// Use default transformer.
		switch ( $class ) {
			case 'WP_Post':
				if ( 'attachment' === $data->post_type && ! is_post_disabled( $data ) ) {
					return new Attachment( $data );
				} elseif ( ! is_post_disabled( $data ) ) {
					return new Post( $data );
				}
				break;
			case 'WP_Comment':
				if ( ! is_local_comment( $data ) ) {
					return new Comment( $data );
				}
				break;
			case 'WP_User':
				if ( ! is_user_disabled( $data->ID ) ) {
					return new User( $data );
				}
				break;
		}

		return null;
	}
}
