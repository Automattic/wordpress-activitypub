<?php
/**
 * Query class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Actors;
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
	 * The ActivityPub object.
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
	 */
	private function __construct() {
		$wp_object = $this->get_queried_object();

		if ( ! $wp_object ) {
			// If the object is not a valid ActivityPub object, try to get a virtual object.
			$this->activitypub_object = $this->maybe_get_virtual_object();
			return;
		}

		$transformer = Factory::get_transformer( $wp_object );

		if ( $transformer && ! is_wp_error( $transformer ) ) {
			$this->activitypub_object = $transformer->to_object();
		}
	}

	/**
	 * The destructor.
	 */
	public function __destruct() {
		self::$instance = null;
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
	public function has_activitypub_object() {
		return null !== $this->activitypub_object;
	}

	/**
	 * Get the ActivityPub object.
	 *
	 * @return object The ActivityPub object.
	 */
	public function get_activitypub_object() {
		return $this->activitypub_object;
	}

	/**
	 * Get the ActivityPub object ID.
	 *
	 * @return int The ActivityPub object ID.
	 */
	public function get_activitypub_object_id() {
		if ( ! $this->has_activitypub_object() ) {
			return null;
		}

		return $this->activitypub_object->get_id();
	}

	/**
	 * Get the queried object.
	 *
	 * This adds support for Comments by `?c=123` IDs and Users by `?author=123` and `@username` IDs.
	 *
	 * @return \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|\WP_Comment|null The queried object.
	 */
	public function get_queried_object() {
		$queried_object = \get_queried_object();

		if ( $queried_object ) {
			return $queried_object;
		}

		// Check Comment by ID.
		$comment_id = \get_query_var( 'c' );
		if ( $comment_id ) {
			return \get_comment( $comment_id );
		}

		// Try to get Author by ID.
		$url       = $this->get_request_url();
		$author_id = url_to_authorid( $url );
		if ( $author_id ) {
			return \get_user_by( 'id', $author_id );
		}

		return null;
	}

	/**
	 * Get the queried object ID.
	 *
	 * @return int The queried object ID.
	 */
	public function get_queried_object_id() {
		if ( ! $this->get_queried_object() ) {
			return null;
		}

		return $this->get_queried_object()->ID;
	}

	/**
	 * Get the virtual object.
	 *
	 * Virtual objects are objects that are not stored in the database, but are created on the fly.
	 * The plugins currently supports two virtual objects: The Blog-Actor and the Application-Actor.
	 *
	 * @see \Activitypub\Blog
	 * @see \Activitypub\Application
	 *
	 * @return object|null The virtual object.
	 */
	protected function maybe_get_virtual_object() {
		$url = $this->get_request_url();

		if ( ! $url ) {
			return null;
		}

		$author_id = url_to_authorid( $url );

		if ( ! is_numeric( $author_id ) ) {
			return null;
		}

		$user = Actors::get_by_id( $author_id );

		if ( is_wp_error( $user ) || ! $user ) {
			return null;
		}

		return $user;
	}

	/**
	 * Get the request URL.
	 *
	 * @return string|null The request URL.
	 */
	protected function get_request_url() {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$url = \wp_unslash( $_SERVER['REQUEST_URI'] );
		$url = \WP_Http::make_absolute_url( $url, \home_url() );
		$url = \sanitize_url( $url );

		return $url;
	}

	/**
	 * Check if the current request is an ActivityPub request.
	 *
	 * @return bool True if the request is an ActivityPub request, false otherwise.
	 */
	public function is_activitypub_request() {
		if ( isset( $this->is_activitypub_request ) ) {
			return $this->is_activitypub_request;
		}

		global $wp_query;

		// One can trigger an ActivityPub request by adding ?activitypub to the URL.
		if ( isset( $wp_query->query_vars['activitypub'] ) ) {
			$this->is_activitypub_request = true;

			return true;
		}

		/*
		* The other (more common) option to make an ActivityPub request
		* is to send an Accept header.
		*/
		if ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
			$accept = \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_ACCEPT'] ) );

			/*
			* $accept can be a single value, or a comma separated list of values.
			* We want to support both scenarios,
			* and return true when the header includes at least one of the following:
			* - application/activity+json
			* - application/ld+json
			* - application/json
			*/
			if ( \preg_match( '/(application\/(ld\+json|activity\+json|json))/i', $accept ) ) {
				$this->is_activitypub_request = true;

				return true;
			}
		}

		$this->is_activitypub_request = false;

		return false;
	}
}
