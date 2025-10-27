<?php
/**
 * Transformer Factory Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Base_Object;
use Activitypub\Comment as Comment_Helper;
use Activitypub\Http;

use function Activitypub\get_user_id;
use function Activitypub\is_post_disabled;
use function Activitypub\user_can_activitypub;

/**
 * Transformer Factory.
 */
class Factory {
	/**
	 * Get the transformer for a given object.
	 *
	 * @param mixed $data The object to transform.
	 *
	 * @return Base|\WP_Error The transformer to use, or an error.
	 */
	public static function get_transformer( $data ) {
		if ( \is_string( $data ) && \filter_var( $data, FILTER_VALIDATE_URL ) ) {
			$response = Http::get_remote_object( $data );

			if ( \is_wp_error( $response ) ) {
				return $response;
			}

			$class = 'json';
			$data  = $response;
		} elseif ( \is_array( $data ) || \is_string( $data ) ) {
			$class = 'json';
		} elseif ( \is_object( $data ) ) {
			$class = \get_class( $data );
		} else {
			return new \WP_Error( 'invalid_object', __( 'Invalid object', 'activitypub' ) );
		}

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
		 * @param null|Base $transformer  The transformer to use. Default null.
		 * @param mixed     $data         The object to transform.
		 * @param string    $object_class The class of the object to transform.
		 *
		 * @return mixed The transformer to use.
		 */
		$transformer = \apply_filters( 'activitypub_transformer', null, $data, $class );

		if ( $transformer ) {
			if (
				! \is_object( $transformer ) ||
				! $transformer instanceof Base
			) {
				return new \WP_Error( 'invalid_transformer', __( 'Invalid transformer', 'activitypub' ) );
			}

			return $transformer;
		}

		// Use default transformer.
		switch ( $class ) {
			case 'WP_Post':
				if ( 'attachment' === $data->post_type && ! is_post_disabled( $data ) ) {
					return new Attachment( $data );
				} elseif ( ! is_post_disabled( $data ) && get_user_id( $data->post_author ) ) {
					return new Post( $data );
				}
				break;
			case 'WP_Comment':
				if ( Comment_Helper::should_be_federated( $data ) ) {
					return new Comment( $data );
				}
				break;
			case 'WP_User':
				if ( user_can_activitypub( $data->ID ) ) {
					return new User( $data );
				}
				break;
			case 'json':
				return new Json( $data );
		}

		if ( $data instanceof Base_Object ) {
			return new Activity_Object( $data );
		}

		return new \WP_Error( 'invalid_object', __( 'Invalid object', 'activitypub' ) );
	}
}
