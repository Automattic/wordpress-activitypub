<?php
namespace Activitypub\Integration;

use Activitypub\Activity\Extended_Object\Event;
use Activitypub\Transformer\Base;
use Activitypub\Transformer\Post;
use function Activitypub\get_rest_url_by_path;

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
		$event_tickets = null;
		if ( ! empty( $event->tickets ) ) {
			$event_tickets = $event->tickets;
		}
		//print_r( $event );
		$object = new Event();
		$object = $this->transform_object_properties( $object );

		$object->set_type( 'Event' );
		$object->set_name( $event->post_title );

		$object->set_timezone( $event->timezone );

		$end_date = \strtotime( $event->end_date );
		$object->set_end_time( \gmdate( 'Y-m-d\TH:i:s\Z', $end_date ) );

		$start_date = \strtotime( $event->start_date );
		$object->set_start_time( \gmdate( 'Y-m-d\TH:i:s\Z', $start_date ) );

		if ( 'canceled' === $event->event_status ) {
			$object->set_status( 'CANCELLED' );
		}

		if ( 'open' === $event->comment_status ) {
			$object->set_comments_enabled( true );
		} else {
			$object->set_comments_enabled( false );
		}

		$object->set_category( 'MEETING' );

		//free, restricted, external
		$object->set_join_mode( 'free' );
		if ( $event_tickets ) {
			$object->set_join_mode( 'restricted' );
			$object->set_external_participation_url( $event_tickets['link']->anchor );
		}

		if ( ! empty( $event->venues ) && ! empty( $event->venues[0] ) ) {
			$event_venue = $event->venues[0];
			$address = [
				'addressCountry' => $event_venue->country,
				'addressLocality' => $event_venue->city,
				'addressRegion' => $event_venue->province,
				'postalCode' => $event_venue->zip,
				'streetAddress' => $event_venue->address,
			];
			$object->set_location(
				$event_venue->permalink,
				$event_venue->post_name,
				$address
			);
		}

		$object->set_anonymous_participation_enabled( false );
		$object->set_in_language( 'de' );
		$object->set_is_online( false );
		if ( class_exists( 'Tribe\Events\Virtual\Event_Meta' ) &&
			$event->virtual_event_type &&
			Tribe\Events\Virtual\Event_Meta::$value_virtual_event_type === $event->virtual_event_type
		) {
			$object->set_is_online( true );
		}
		if ( $event_tickets && function_exists( '\tribe_get_event_capacity' ) ) {
			$object->set_maximum_attendee_capacity( \tribe_get_event_capacity( $event ) );
			$object->set_participant_count( count( \tribe_tickets_get_attendees( $event->ID ) ) );
			$object->set_remaining_attendee_capacity( \tribe_events_count_available_tickets( $event ) );
		}

		$published = \strtotime( $event->post_date_gmt );
		$object->set_published( \gmdate( 'Y-m-d\TH:i:s\Z', $published ) );

		$updated = \strtotime( $event->post_modified_gmt );
		if ( $updated > $published ) {
			$object->set_updated( \gmdate( 'Y-m-d\TH:i:s\Z', $updated ) );
		}

		$object->set_content_map(
			array(
				$this->get_locale() => $this->get_content(),
			)
		);
		$path = sprintf( 'actors/%d/followers', intval( $event->post_author ) );

		$object->set_to(
			array(
				'https://www.w3.org/ns/activitystreams#Public',
				get_rest_url_by_path( $path ),
			)
		);

		return $object;
	}
}
