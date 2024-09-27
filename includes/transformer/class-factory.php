<?php
namespace Activitypub\Transformer;

use WP_Error;
use Activitypub\Transformer\Base;
use Activitypub\Transformer\Post;
use Activitypub\Transformer\Comment;
use Activitypub\Transformer\Attachment;

/**
 * Transformer Factory
 */
class Factory {
	/**
	 * @param  mixed $object                      The object to transform
	 * @return \Activitypub\Transformer|\WP_Error The transformer to use, or an error.
	 */
	public static function get_transformer( $object ) { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.objectFound
		if ( ! \is_object( $object ) ) {
			return new WP_Error( 'invalid_object', __( 'Invalid object', 'activitypub' ) );
		}

		$class = \get_class( $object );

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
		 * @param mixed  $object       The object to transform.
		 * @param string $object_class The class of the object to transform.
		 *
		 * @return mixed The transformer to use.
		 */
		$transformer = \apply_filters( 'activitypub_transformer', null, $object, $class );

		if ( $transformer ) {
			if (
				! \is_object( $transformer ) ||
				! $transformer instanceof Base
			) {
				return new WP_Error( 'invalid_transformer', __( 'Invalid transformer', 'activitypub' ) );
			}

			return $transformer;
		}

		// use default transformer
		switch ( $class ) {
			case 'WP_Post':
				if ( 'attachment' === $object->post_type ) {
					return new Attachment( $object );
				}
				return new Post( $object );
			case 'WP_Comment':
				return new Comment( $object );
			default:
				return null;
		}
	}
}
