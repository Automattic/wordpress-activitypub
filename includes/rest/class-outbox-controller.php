<?php
/**
 * Outbox Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Outbox;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;

use function Activitypub\add_to_outbox;
use function Activitypub\camel_to_snake_case;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub Outbox Controller.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox_Controller extends \WP_REST_Controller {
	use Collection;

	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The base of this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = '(?:users|actors)/(?P<user_id>[-]?\d+)/outbox';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args'   => array(
					'user_id' => array(
						'description'       => 'The ID of the user or actor.',
						'type'              => 'integer',
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'page'     => array(
							'description' => 'Current page of the collection.',
							'type'        => 'integer',
							'minimum'     => 1,
							// No default so we can differentiate between Collection and CollectionPage requests.
						),
						'per_page' => array(
							'description' => 'Maximum number of items to be returned in result set.',
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		\add_filter( 'activitypub_rest_outbox_array', array( $this, 'overload_total_items' ), 10, 2 );
	}

	/**
	 * Validates the user_id parameter.
	 *
	 * @param mixed $user_id The user_id parameter.
	 * @return bool|\WP_Error True if the user_id is valid, WP_Error otherwise.
	 */
	public function validate_user_id( $user_id ) {
		$user = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		return true;
	}

	/**
	 * Retrieves a collection of outbox items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page    = $request->get_param( 'page' ) ?? 1;
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_id( $user_id );

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 *
		 * @param \WP_REST_Request $request The request object.
		 */
		\do_action( 'activitypub_rest_outbox_pre', $request );

		/**
		 * Filters the list of activity types to include in the outbox.
		 *
		 * @param string[] $activity_types The list of activity types.
		 */
		$activity_types = apply_filters( 'rest_activitypub_outbox_activity_types', array( 'Announce', 'Create', 'Like', 'Update' ) );

		$args = array(
			'posts_per_page' => $request->get_param( 'per_page' ),
			'author'         => $user_id > 0 ? $user_id : null,
			'paged'          => $page,
			'post_type'      => Outbox::POST_TYPE,
			'post_status'    => 'any',

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_activitypub_activity_actor',
					'value' => Actors::get_type_by_id( $user_id ),
				),
			),
		);

		if ( get_current_user_id() !== $user_id && ! current_user_can( 'activitypub' ) ) {
			$args['meta_query'][] = array(
				'key'     => '_activitypub_activity_type',
				'value'   => $activity_types,
				'compare' => 'IN',
			);

			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'activitypub_content_visibility',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'   => 'activitypub_content_visibility',
					'value' => ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC,
				),
			);
		}

		/**
		 * Filters WP_Query arguments when querying Outbox items via the REST API.
		 *
		 * Enables adding extra arguments or setting defaults for an outbox collection request.
		 *
		 * @param array            $args    Array of arguments for WP_Query.
		 * @param \WP_REST_Request $request The REST API request.
		 */
		$args = \apply_filters( 'activitypub_rest_outbox_query', $args, $request );

		$outbox_query = new \WP_Query();
		$query_result = $outbox_query->query( $args );

		$response = array(
			'@context'     => Base_Object::JSON_LD_CONTEXT,
			'id'           => get_rest_url_by_path( sprintf( 'actors/%d/outbox', $user_id ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'actor'        => $user->get_id(),
			'type'         => 'OrderedCollection',
			'totalItems'   => (int) $outbox_query->found_posts,
			'orderedItems' => array(),
		);

		\update_postmeta_cache( \wp_list_pluck( $query_result, 'ID' ) );
		foreach ( $query_result as $outbox_item ) {
			if ( ! $outbox_item instanceof \WP_Post ) {
				/**
				 * Action triggered when an outbox item is not a WP_Post.
				 *
				 * @param mixed            $outbox_item  The outbox item.
				 * @param array            $args         The arguments used to query the outbox.
				 * @param array            $query_result The result of the query.
				 * @param \WP_REST_Request $request      The request object.
				 */
				do_action( 'activitypub_rest_outbox_item_error', $outbox_item, $args, $query_result, $request );

				continue;
			}

			$response['orderedItems'][] = $this->prepare_item_for_response( $outbox_item, $request );
		}

		$response = $this->prepare_collection_response( $response, $request );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		/**
		 * Filter the ActivityPub outbox array.
		 *
		 * @param array            $response The ActivityPub outbox array.
		 * @param \WP_REST_Request $request  The request object.
		 */
		$response = \apply_filters( 'activitypub_rest_outbox_array', $response, $request );

		/**
		 * Action triggered after the ActivityPub profile has been created and sent to the client.
		 *
		 * @param \WP_REST_Request $request The request object.
		 */
		\do_action( 'activitypub_rest_outbox_post', $request );

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Prepares the item for the REST response.
	 *
	 * @param mixed            $item    WordPress representation of the item.
	 * @param \WP_REST_Request $request Request object.
	 * @return array Response object on success, or WP_Error object on failure.
	 */
	public function prepare_item_for_response( $item, $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$activity = Outbox::get_activity( $item->ID );

		return $activity->to_array( false );
	}

	/**
	 * Retrieves the outbox schema, conforming to JSON Schema.
	 *
	 * @return array Collection schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$item_schema = array(
			'type' => 'object',
		);

		$schema = $this->get_collection_schema( $item_schema );

		// Add outbox-specific properties.
		$schema['title']                   = 'outbox';
		$schema['properties']['actor']     = array(
			'description' => 'The actor who owns this outbox.',
			'type'        => 'string',
			'format'      => 'uri',
			'required'    => true,
		);
		$schema['properties']['generator'] = array(
			'description' => 'The software used to generate the collection.',
			'type'        => 'string',
			'format'      => 'uri',
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Overload total items.
	 *
	 * The `totalItems` property is used by Mastodon to show the overall
	 * number of federated posts and comments.
	 *
	 * @param array            $response The response array.
	 * @param \WP_REST_Request $request  The request object.
	 *
	 * @return array The modified response array.
	 */
	public function overload_total_items( $response, $request ) {
		$posts = new \WP_Query(
			array(
				'post_status'   => 'publish',
				'author'        => $request->get_param( 'user_id' ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				'meta_query'    => array(
					array(
						'key'     => 'activitypub_status',
						'compare' => 'EXISTS',
					),
				),
				'fields'        => 'ids',
				'no_found_rows' => false,
				'number'        => 1,
			)
		);

		$comments = new \WP_Comment_Query(
			array(
				'status'        => 'approve',
				'user_id'       => $request->get_param( 'user_id' ),
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'      => 'activitypub_status',
				'fields'        => 'ids',
				'no_found_rows' => false,
				'number'        => 1,
			)
		);

		$response['totalItems'] = (int) $posts->found_posts + (int) $comments->found_comments;

		return $response;
	}

	/**
	 * Permission check for creating items (C2S).
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function create_item_permissions_check( $request ) {
		// Check if C2S is enabled.
		if ( ! OAuth_Server::is_c2s_enabled() ) {
			return new \WP_Error(
				'activitypub_c2s_disabled',
				\__( 'Client-to-Server (C2S) support is not enabled.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		// Must be authenticated via OAuth with 'write' scope.
		$permission = OAuth_Server::check_oauth_permission( $request, Scope::WRITE );
		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		// Token user must match actor in URL.
		$user_id = $request->get_param( 'user_id' );
		$token   = OAuth_Server::get_current_token();

		if ( ! $token || $token->get_user_id() !== $user_id ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You can only post to your own outbox.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Create an item in the outbox (C2S).
	 *
	 * Follows the same pattern as the Inbox controller:
	 * 1. Store the activity in the outbox
	 * 2. Trigger action hooks for handlers to process
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
	 */
	public function create_item( \WP_REST_Request $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_id( $user_id );
		$data    = $request->get_json_params();

		if ( empty( $data ) ) {
			return new \WP_Error(
				'activitypub_invalid_request',
				\__( 'Request body must be a valid ActivityPub object or activity.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Determine if this is an Activity or a bare Object.
		$type        = $data['type'] ?? '';
		$is_activity = in_array( $type, Activity::TYPES, true );

		// If it's a bare object, wrap it in a Create activity.
		if ( ! $is_activity ) {
			$data = $this->wrap_in_create( $data, $user );
		}

		$activity_type = camel_to_snake_case( $data['type'] ?? '' );

		// Determine visibility from addressing.
		$visibility = $this->determine_visibility( $data );

		// Add to outbox - this handles storage and triggers federation.
		$outbox_id = add_to_outbox( $data, null, $user_id, $visibility );

		if ( ! $outbox_id || \is_wp_error( $outbox_id ) ) {
			return new \WP_Error(
				'activitypub_outbox_error',
				\__( 'Failed to add activity to outbox.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Get the stored activity for hooks.
		$activity = Outbox::get_activity( $outbox_id );

		/**
		 * Fires for each outbox activity.
		 *
		 * @param array                          $data     The activity data array.
		 * @param int                            $user_id  The user ID.
		 * @param string                         $type     The activity type (snake_case).
		 * @param \Activitypub\Activity\Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_outbox', $data, $user_id, $activity_type, $activity );

		/**
		 * Fires for specific outbox activity types.
		 *
		 * The dynamic portion of the hook name, `$activity_type`, refers to the
		 * activity type in snake_case (e.g., 'create', 'update', 'delete', 'like').
		 *
		 * @param array                          $data     The activity data array.
		 * @param int                            $user_id  The user ID.
		 * @param \Activitypub\Activity\Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_outbox_' . $activity_type, $data, $user_id, $activity );

		/**
		 * Fires after an outbox activity has been stored.
		 *
		 * @param array                          $data       The activity data array.
		 * @param int                            $user_id    The user ID.
		 * @param string                         $type       The activity type (snake_case).
		 * @param \Activitypub\Activity\Activity $activity   The Activity object.
		 * @param int                            $outbox_id  The outbox post ID.
		 */
		\do_action( 'activitypub_handled_outbox', $data, $user_id, $activity_type, $activity, $outbox_id );

		/**
		 * Fires after a specific outbox activity type has been stored.
		 *
		 * @param array                          $data       The activity data array.
		 * @param int                            $user_id    The user ID.
		 * @param \Activitypub\Activity\Activity $activity   The Activity object.
		 * @param int                            $outbox_id  The outbox post ID.
		 */
		\do_action( 'activitypub_handled_outbox_' . $activity_type, $data, $user_id, $activity, $outbox_id );

		if ( \is_wp_error( $activity ) ) {
			return $activity;
		}

		$result = $activity->to_array( false );

		// Return 201 Created with Location header.
		$response = new \WP_REST_Response( $result, 201 );
		$response->header( 'Location', $result['id'] ?? \get_the_guid( $outbox_id ) );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Wrap a bare object in a Create activity.
	 *
	 * @param array $object_data The object data.
	 * @param mixed $user        The user/actor.
	 * @return array The wrapped Create activity.
	 */
	private function wrap_in_create( $object_data, $user ) {
		// Copy addressing from object to activity.
		$addressing = array();
		foreach ( array( 'to', 'bto', 'cc', 'bcc', 'audience' ) as $field ) {
			if ( ! empty( $object_data[ $field ] ) ) {
				$addressing[ $field ] = $object_data[ $field ];
			}
		}

		return array_merge(
			array(
				'@context' => Base_Object::JSON_LD_CONTEXT,
				'type'     => 'Create',
				'actor'    => $user->get_id(),
				'object'   => $object_data,
			),
			$addressing
		);
	}

	/**
	 * Determine content visibility from activity addressing.
	 *
	 * @param array $activity The activity data.
	 * @return string Visibility constant.
	 */
	private function determine_visibility( $activity ) {
		$public = 'https://www.w3.org/ns/activitystreams#Public';
		$to     = (array) ( $activity['to'] ?? array() );
		$cc     = (array) ( $activity['cc'] ?? array() );

		// Check if public.
		if ( in_array( $public, $to, true ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		// Check if unlisted (public in cc).
		if ( in_array( $public, $cc, true ) ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC;
		}

		// Private (no public addressing).
		return ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE;
	}
}
