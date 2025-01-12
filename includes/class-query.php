<?php
/**
 * Query class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Transformer\Factory;

/**
 * Singleton class to handle and store the ActivityPub query.
 */
class Query {

	/**
	 * The singleton instance.
	 *
	 * @var Query
	 */
	private static $instance;

	/**
	 * The query object.
	 *
	 * @var object
	 */
	private $activitypub_object;

	/**
	 * Whether the current request is an ActivityPub request.
	 *
	 * @var bool
	 */
	private $is_activitypub_request;

	/**
	 * The constructor.
	 *
	 * Transform the queried object to an ActivityPub object.
	 *
	 * @todo Handle Actors and Replies.
	 */
	private function __construct() {
		$wp_object = \get_queried_object();

		if ( ! $wp_object ) {
			$this->activitypub_object = null;
			return;
		}

		$transformer = Factory::get_transformer( $wp_object );

		if ( ! $transformer || is_wp_error( $transformer ) ) {
			$this->activitypub_object = null;
			return;
		}

		$this->activitypub_object = $transformer->to_object();
	}

	/**
	 * Get the singleton instance.
	 *
	 * @return Query The singleton instance.
	 */
	public static function get_instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Check if the current request has a queried object.
	 *
	 * @return bool True if the request has a queried object, false otherwise.
	 */
	public function has_queried_object() {
		return null !== $this->activitypub_object;
	}

	/**
	 * Get the query object.
	 *
	 * @return object The query object.
	 */
	public function get_queried_object() {
		return $this->activitypub_object;
	}

	/**
	 * Get the query object ID.
	 *
	 * @return int The query object ID.
	 */
	public function get_queried_object_id() {
		if ( ! $this->has_queried_object() ) {
			return null;
		}

		return $this->activitypub_object->get_id();
	}

	/**
	 * Check if the current request is an ActivityPub request.
	 *
	 * @return bool True if the request is an ActivityPub request, false otherwise.
	 */
	public function is_activitypub_request() {
		if ( $this->is_activitypub_request ) {
			return $this->is_activitypub_request;
		}

		global $wp_query;

		// One can trigger an ActivityPub request by adding ?activitypub to the URL.
		if ( isset( $wp_query->query_vars['activitypub'] ) ) {
			return true;
		}

		/*
		* The other (more common) option to make an ActivityPub request
		* is to send an Accept header.
		*/
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$accept = sanitize_text_field( wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );

			/*
			* $accept can be a single value, or a comma separated list of values.
			* We want to support both scenarios,
			* and return true when the header includes at least one of the following:
			* - application/activity+json
			* - application/ld+json
			* - application/json
			*/
			if ( preg_match( '/(application\/(ld\+json|activity\+json|json))/i', $accept ) ) {
				// Set the query var to true, to speed up the next check.
				$wp_query->query_vars['activitypub'] = true;
				$this->is_activitypub_request        = true;
				return true;
			}
		}

		$this->is_activitypub_request = false;
		return false;
	}
}
