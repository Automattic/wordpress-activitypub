<?php
namespace Activitypub\Integration;

use Activitypub\Activity\Extended_Object\Event;
use Activitypub\Transformer\Base;
use Activitypub\Transformer\Post;

/**
 * Compatibility with the Tribe The Events Calendar plugin.
 *
 * This is a transformer for the Tribe The Events Calendar plugin,
 * that extends the default transformer for WordPress posts.
 *
 * @see https://wordpress.org/plugins/the-events-calendar/
 */
class The_Events_Calendar extends Post {

	/**
	 * Base constructor.
	 *
	 * @param WP_Post|WP_Comment $wp_object The WordPress object
	 */
	public function __construct( $wp_object ) {
		$this->wp_object = \tribe_get_event( $wp_object );
	}

	public function to_object() {
		$event = $this->wp_object;
		//print_r($this->wp_object);
		$object = new Event();

		if ( 'canceled' === $event->event_status ) {
			$object->set_status( 'CANCELLED' );
		}

		return $object;
	}
}
