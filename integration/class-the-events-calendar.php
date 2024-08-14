<?php
namespace Activitypub\Integration;

use Activitypub\Activity\Extended_Object\Event;
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


	public function to_activity( $type ) {
		$event = $this->wp_object;
		$object = $this->to_object();

		$activity = new Event();

		return $activity;
	}
}
