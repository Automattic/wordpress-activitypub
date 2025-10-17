<?php
/**
 * Query class.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Extended_Object\Quote_Authorization;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
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
	 * @link https://www.w3.org/TR/activitystreams-vocabulary/#dfn-object
	 *
	 * @var object
	 */
	private $activitypub_object;

	/**
	 * The ActivityPub object ID.
	 *
	 * @link https://www.w3.org/TR/activitystreams-vocabulary/#dfn-id
	 *
	 * @var string
	 */
	private $activitypub_object_id;

	/**
	 * Whether the current request is an ActivityPub request.
	 *
	 * @var bool
	 */
	private $is_activitypub_request;

	/**
	 * Whether the current request is from the old host.
	 *
	 * @var bool
	 */
	private $is_old_host_request;

	/**
	 * The constructor.
	 */
	private function __construct() {
		// Do nothing.
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
	 * Get the ActivityPub object.
	 *
	 * @return object The ActivityPub object.
	 */
	public function get_activitypub_object() {
		if ( $this->activitypub_object ) {
			return $this->activitypub_object;
		}

		if ( $this->prepare_activitypub_data() ) {
			return $this->activitypub_object;
		}

		$queried_object = $this->get_queried_object();
		$transformer    = Factory::get_transformer( $queried_object );

		if ( $transformer && ! \is_wp_error( $transformer ) ) {
			$this->activitypub_object = $transformer->to_object();
		}

		return $this->activitypub_object;
	}

	/**
	 * Get the ActivityPub object ID.
	 *
	 * @return string The ActivityPub object ID.
	 */
	public function get_activitypub_object_id() {
		if ( $this->activitypub_object_id ) {
			return $this->activitypub_object_id;
		}

		if ( $this->prepare_activitypub_data() ) {
			return $this->activitypub_object_id;
		}

		$queried_object = $this->get_queried_object();
		$transformer    = Factory::get_transformer( $queried_object );

		if ( $transformer && ! \is_wp_error( $transformer ) ) {
			$this->activitypub_object_id = $transformer->to_id();
		}

		return $this->activitypub_object_id;
	}

	/**
	 * Prepare and set both ActivityPub object and ID for Outbox activities and virtual objects.
	 *
	 * @return bool True if an object was found and set, false otherwise.
	 */
	private function prepare_activitypub_data() {
		$queried_object = $this->get_queried_object();

		if ( $queried_object instanceof \WP_Post && \get_query_var( 'stamp' ) ) {
			return $this->maybe_get_stamp();
		}

		// Check for Outbox Activity.
		if (
			$queried_object instanceof \WP_Post &&
			Outbox::POST_TYPE === $queried_object->post_type
		) {
			$activitypub_object = Outbox::maybe_get_activity( $queried_object );

			// Check if the Outbox Activity is public.
			if ( ! \is_wp_error( $activitypub_object ) ) {
				$this->activitypub_object    = $activitypub_object;
				$this->activitypub_object_id = $this->activitypub_object->get_id();
				return true;
			}
		}

		if ( ! $queried_object ) {
			// If the object is not a valid ActivityPub object, try to get a virtual object.
			$activitypub_object = $this->maybe_get_virtual_object();

			if ( $activitypub_object ) {
				$this->activitypub_object    = $activitypub_object;
				$this->activitypub_object_id = $this->activitypub_object->get_id();
				return true;
			}
		}

		return false;
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

		// Check Comment by ID.
		if ( ! $queried_object ) {
			$comment_id = \get_query_var( 'c' );
			if ( $comment_id ) {
				$queried_object = \get_comment( $comment_id );
			}
		}

		// Check Post by ID (works for custom post types).
		if ( ! $queried_object ) {
			$post_id = \get_query_var( 'p' );
			if ( $post_id ) {
				$queried_object = \get_post( $post_id );
			}
		}

		// Try to get Author by ID.
		if ( ! $queried_object ) {
			$url       = $this->get_request_url();
			$author_id = url_to_authorid( $url );
			if ( $author_id ) {
				$queried_object = \get_user_by( 'id', $author_id );
			}
		}

		/**
		 * Filters the queried object.
		 *
		 * @param \WP_Term|\WP_Post_Type|\WP_Post|\WP_User|\WP_Comment|null $queried_object The queried object.
		 */
		return apply_filters( 'activitypub_queried_object', $queried_object );
	}

	/**
	 * Get the virtual object.
	 *
	 * Virtual objects are objects that are not stored in the database, but are created on the fly.
	 * The plugins currently supports two virtual objects: The Blog-Actor and the Application-Actor.
	 *
	 * @see \Activitypub\Model\Blog
	 * @see \Activitypub\Model\Application
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
			$author_id = $url;
		}

		$user = Actors::get_by_various( $author_id );

		if ( \is_wp_error( $user ) || ! $user ) {
			return null;
		}

		return $user;
	}

	/**
	 * Get the request URL.
	 *
	 * @return string|null The request URL.
	 */
	public function get_request_url() {
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
		if ( ! isset( $this->is_activitypub_request ) ) {
			global $wp_query;

			$this->is_activitypub_request = false;

			// One can trigger an ActivityPub request by adding `?activitypub` to the URL.
			if ( isset( $wp_query->query_vars['activitypub'] ) || isset( $_GET['activitypub'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				\defined( 'ACTIVITYPUB_REQUEST' ) || \define( 'ACTIVITYPUB_REQUEST', true );
				$this->is_activitypub_request = true;

				// The other (more common) option to make an ActivityPub request  is to send an Accept header.
			} elseif ( isset( $_SERVER['HTTP_ACCEPT'] ) ) {
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
					\defined( 'ACTIVITYPUB_REQUEST' ) || \define( 'ACTIVITYPUB_REQUEST', true );
					$this->is_activitypub_request = true;
				}
			}
		}

		/**
		 * Filters whether the current request is an ActivityPub request.
		 *
		 * @param bool $is_activitypub_request True if the request is an ActivityPub request, false otherwise.
		 */
		return \apply_filters( 'activitypub_is_activitypub_request', $this->is_activitypub_request );
	}

	/**
	 * Check if content negotiation is allowed for a request.
	 *
	 * @return bool True if content negotiation is allowed, false otherwise.
	 */
	public function should_negotiate_content() {
		$return           = false;
		$always_negotiate = array( 'p', 'c', 'author', 'actor', 'stamp', 'preview', 'activitypub' );
		$url              = \wp_parse_url( $this->get_request_url(), PHP_URL_QUERY );
		$query            = array();
		\wp_parse_str( $url, $query );

		// Check if any of the query params are in the `$always_negotiate` array.
		if ( \array_intersect( \array_keys( $query ), $always_negotiate ) ) {
			$return = true;
		}

		if ( \get_option( 'activitypub_content_negotiation', '1' ) ) {
			$return = true;
		}

		/**
		 * Filters whether content negotiation should be forced.
		 *
		 * @param bool $return Whether content negotiation should be forced.
		 */
		return \apply_filters( 'activitypub_should_negotiate_content', $return );
	}

	/**
	 * Check if the current request is from the old host.
	 *
	 * @return bool True if the request is from the old host, false otherwise.
	 */
	public function is_old_host_request() {
		if ( isset( $this->is_old_host_request ) ) {
			return $this->is_old_host_request;
		}

		$old_host = \get_option( 'activitypub_old_host' );

		if ( ! $old_host ) {
			$this->is_old_host_request = false;
			return false;
		}

		$request_host = isset( $_SERVER['HTTP_HOST'] ) ? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		$referer_host = isset( $_SERVER['HTTP_REFERER'] ) ? \wp_parse_url( \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_REFERER'] ) ), PHP_URL_HOST ) : '';

		// Check if the domain matches either the request domain or referer.
		$check                     = $old_host === $request_host || $old_host === $referer_host;
		$this->is_old_host_request = $check;

		return $check;
	}

	/**
	 * Fake an old host request.
	 *
	 * @param bool $state Optional. The state to set. Default true.
	 */
	public function set_old_host_request( $state = true ) {
		$this->is_old_host_request = $state;
	}

	/**
	 * Maybe get a QuoteAuthorization object from a stamp.
	 *
	 * @return bool True if the object was prepared, false otherwise.
	 */
	private function maybe_get_stamp() {
		require_once ABSPATH . 'wp-admin/includes/post.php';

		$stamp = \get_query_var( 'stamp' );
		$meta  = \get_post_meta_by_id( (int) $stamp );

		if ( ! $meta ) {
			return false;
		}

		$post     = $this->get_queried_object();
		$user_uri = get_user_id( $post->post_author );

		if ( ! $user_uri ) {
			return false;
		}

		$stamp_uri = \add_query_arg(
			array(
				'p'     => $post->ID,
				'stamp' => $meta->meta_id,
			),
			\home_url( '/' )
		);

		$activitypub_object = new Quote_Authorization();
		$activitypub_object->set_id( $stamp_uri );
		$activitypub_object->set_attributed_to( $user_uri );
		$activitypub_object->set_interacting_object( $meta->meta_value );
		$activitypub_object->set_interaction_target( get_post_id( $post->ID ) );

		$this->activitypub_object    = $activitypub_object;
		$this->activitypub_object_id = $activitypub_object->get_id();

		return true;
	}
}
