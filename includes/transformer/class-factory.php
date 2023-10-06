<?php
namespace Activitypub\Transformer;

use Activitypub\Transformer\Post;
use Activitypub\Transformer\Comment;

/**
 * Transformer Factory
 */
class Factor {
	public static function get_transformer( $object ) {
		switch ( get_class( $object ) ) {
			case 'WP_Post':
				return new Post( $object );
			case 'WP_Comment':
				return new Comment( $object );
			default:
				return apply_filters( 'activitypub_transformer', null, $object, get_class( $object ) );
		}
	}
}
