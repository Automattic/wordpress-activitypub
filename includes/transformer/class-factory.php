<?php
namespace Activitypub\Transformer;

use Activitypub\Transformer\Post;
use Activitypub\Transformer\Comment;

/**
 * Transformer Factory
 */
class Factory {
	public static function get_transformer( $object ) {
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
		 * @param Activitypub\Transformer\Base $transformer  The transformer to use.
		 * @param mixed                        $object       The object to transform.
		 * @param string                       $object_class The class of the object to transform.
		 *
		 * @return mixed The transformer to use.
		 */
		$transformer = apply_filters( 'activitypub_transformer', null, $object, get_class( $object ) );

		if ( $transformer ) {
			return $transformer;
		}

		// use default transformer
		switch ( get_class( $object ) ) {
			case 'WP_Post':
				return new Post( $object );
			case 'WP_Comment':
				return new Comment( $object );
			default:
				return null;
		}
	}
}
