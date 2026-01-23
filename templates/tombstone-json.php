<?php
/**
 * Tombstone JSON template.
 *
 * @package Activitypub
 */

use Activitypub\Activity\Base_Object;
use Activitypub\Query;
use Activitypub\Transformer\Factory;

$query          = Query::get_instance();
$queried_object = $query->get_queried_object();
$object         = null;

// For soft-deleted posts, use the transformer to create a full Tombstone.
if ( $queried_object ) {
	/**
	 * The transformer for the queried object.
	 *
	 * @var \Activitypub\Transformer\Post|\WP_Error $transformer
	 */
	$transformer = Factory::get_transformer( $queried_object );
	if ( $transformer && ! \is_wp_error( $transformer ) ) {
		$object = $transformer->to_tombstone();
	}
}

// Fallback for permanently deleted posts.
if ( ! $object ) {
	$object = new Base_Object();
	$object->set_id( $query->get_request_url() );
	$object->set_type( 'Tombstone' );
}

/**
 * Fires before an ActivityPub object is generated and sent to the client.
 *
 * @param Activitypub\Activity\Base_Object $object The ActivityPub object.
 */
\do_action( 'activitypub_json_pre', $object );

\header( 'Content-Type: application/activity+json' );
echo $object->to_json(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

/**
 * Fires after an ActivityPub object is generated and sent to the client.
 *
 * @param Activitypub\Activity\Base_Object $object The ActivityPub object.
 */
\do_action( 'activitypub_json_post', $object );
