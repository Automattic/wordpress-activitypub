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

use function Activitypub\add_to_outbox;
use function Activitypub\extract_recipients_from_activity;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_object_id;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\object_to_uri;

/**
 * ActivityPub Outbox Controller.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox_Controller extends \WP_REST_Controller {
	use Collection;
	use Event_Stream;
	use Language_Map;
	use Verification;

	/**
	 * Activity types accessible as individual outbox items via REST.
	 *
	 * @var string[]
	 */
	const PUBLIC_ACTIVITY_TYPES = array( 'Announce', 'Arrive', 'Create', 'Like', 'Update' );

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
					'permission_callback' => array( $this, 'verify_signature' ),
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
					'permission_callback' => array( $this, 'verify_authentication' ),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/stream',
			array(
				'args' => array(
					'user_id' => array(
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => function ( $request ) {
						$this->stream_collection( $request->get_param( 'user_id' ), 'outbox' );
					},
					'permission_callback' => array( $this, 'get_stream_permissions_check' ),
				),
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
		 * Filters the activity types included in the outbox collection.
		 *
		 * @param string[] $activity_types The activity types.
		 */
		$activity_types = \apply_filters( 'activitypub_outbox_activity_types', self::PUBLIC_ACTIVITY_TYPES );

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

		// When user_id=0 (blog actor), get_current_user_id() also returns 0 for unauthenticated
		// visitors, so the equality check alone is insufficient — we must test login state first.
		if ( ! \is_user_logged_in() || ( \get_current_user_id() !== (int) $user_id && ! \current_user_can( 'activitypub' ) ) ) {
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
			'eventStream'  => $this->get_stream_url( $user_id, 'outbox' ),
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
				\do_action( 'activitypub_rest_outbox_item_error', $outbox_item, $args, $query_result, $request );

				continue;
			}

			$item = $this->prepare_item_for_response( $outbox_item, $request );

			if ( \is_wp_error( $item ) ) {
				continue;
			}

			$response['orderedItems'][] = $item;
		}

		$response = $this->prepare_collection_response( $response, $request );
		if ( \is_wp_error( $response ) ) {
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

		if ( \is_wp_error( $activity ) ) {
			return $activity;
		}

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
	 * Overload total items for public requests.
	 *
	 * For unauthenticated (public) requests, the `totalItems` property shows
	 * the overall number of federated posts and comments, which is what
	 * Mastodon expects for display purposes.
	 *
	 * For authenticated C2S requests, we skip this override so that totalItems
	 * accurately reflects the actual outbox collection size.
	 *
	 * @param array            $response The response array.
	 * @param \WP_REST_Request $request  The request object.
	 *
	 * @return array The modified response array.
	 */
	public function overload_total_items( $response, $request ) {
		// For authenticated requests, return accurate totalItems matching orderedItems.
		if ( \get_current_user_id() ) {
			return $response;
		}

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

		$user_id  = (int) $request->get_param( 'user_id' );
		$comments = new \WP_Comment_Query(
			array(
				'status'         => 'approve',
				'user_id'        => $user_id,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_key'       => 'activitypub_status',
				'fields'         => 'ids',
				'no_found_rows'  => false,
				'number'         => 1,
				'author__not_in' => array( 0 ),
			)
		);

		$response['totalItems'] = (int) $posts->found_posts + (int) $comments->found_comments;

		return $response;
	}

	/**
	 * Create an item in the outbox.
	 *
	 * Fires handlers via filter to process the activity. Handlers are responsible
	 * for calling add_to_outbox() and returning the outbox_id.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error on failure.
	 */
	public function create_item( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$data = $request->get_json_params();

		if ( empty( $data ) ) {
			return new \WP_Error(
				'activitypub_invalid_request',
				\__( 'Request body must be a valid ActivityPub object or activity.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		// Validate ownership - ensure submitted actor matches authenticated user.
		$ownership_validation = $this->validate_ownership( $data, $user );
		if ( \is_wp_error( $ownership_validation ) ) {
			return $ownership_validation;
		}

		// Determine if this is an Activity or a bare Object.
		$type        = $data['type'] ?? '';
		$is_activity = in_array( $type, Activity::TYPES, true );

		// If it's a bare object, wrap it in a Create activity.
		if ( ! $is_activity ) {
			$data = $this->wrap_in_create( $data, $user );
		}

		// Resolve language maps (summaryMap, contentMap, nameMap) to plain strings.
		$data = $this->localize_language_maps( $data );

		// Default to public addressing if client omits recipients.
		$data = $this->ensure_addressing( $data, $user );

		// Determine visibility from addressing.
		$visibility = $this->determine_visibility( $data );

		$type = \strtolower( $data['type'] ?? 'create' );

		// Validate type against known activity types to prevent hook name pollution.
		$allowed_types = \array_map( 'strtolower', Activity::TYPES );
		if ( ! \in_array( $type, $allowed_types, true ) ) {
			$type = 'create';
		}

		/**
		 * Filters the activity to add to outbox.
		 *
		 * Handlers can process the activity and return:
		 * - WP_Post: A WordPress post was created (scheduler adds to outbox)
		 * - int: An outbox post ID (activity already added to outbox)
		 * - WP_Error: Stop processing and return error
		 * - false: Stop processing (activity not allowed)
		 * - array: Modified activity data (fallback to default handling)
		 * - Other: No handler processed the activity (fallback to default)
		 *
		 * @param array  $data       The activity data.
		 * @param int    $user_id    The user ID.
		 * @param string $visibility Content visibility.
		 */
		$result = \apply_filters( 'activitypub_outbox_' . $type, $data, $user_id, $visibility );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		// Handler returned false to signal "not allowed" or "stop processing".
		if ( false === $result ) {
			return new \WP_Error(
				'activitypub_activity_not_allowed',
				\__( 'This activity type is not allowed.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$object_id = get_object_id( $result );

		if ( $object_id ) {
			// Handler returned a WP_Post or WP_Comment; look up its outbox entry.
			$activity_type = \ucfirst( $data['type'] ?? 'Create' );
			$outbox_item   = Outbox::get_by_object_id( $object_id, $activity_type );
		} elseif ( \is_int( $result ) && $result > 0 ) {
			// Handler returned an outbox post ID directly.
			$outbox_item = \get_post( $result );
		} else {
			// Default handling for raw activities.
			$data        = \is_array( $result ) ? $result : $data;
			$data        = $this->ensure_object_id( $data, $user );
			$outbox_item = \get_post( add_to_outbox( $data, null, $user_id, $visibility ) );
		}

		if ( ! $outbox_item ) {
			return new \WP_Error(
				'activitypub_outbox_error',
				\__( 'Failed to add activity to outbox.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Get the stored activity.
		$activity = Outbox::get_activity( $outbox_item );

		if ( \is_wp_error( $activity ) ) {
			return $activity;
		}

		$result = $activity->to_array( false );

		// Return 201 Created with Location header.
		$response = new \WP_REST_Response( $result, 201 );
		$response->header( 'Location', $result['id'] ?? $outbox_item->guid );
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
	 * Validate that activity actor matches the authenticated user.
	 *
	 * Ensures clients cannot submit activities with mismatched actor data.
	 *
	 * @param array                        $data The activity or object data.
	 * @param \Activitypub\Model\User|null $user The authenticated user.
	 * @return true|\WP_Error True if valid, WP_Error otherwise.
	 */
	private function validate_ownership( $data, $user ) {
		if ( ! $user ) {
			return new \WP_Error(
				'activitypub_invalid_user',
				\__( 'Invalid user.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$user_actor_id = $user->get_id();

		// Check activity actor if present.
		if ( ! empty( $data['actor'] ) ) {
			$actor_id = object_to_uri( $data['actor'] );
			if ( $actor_id && $actor_id !== $user_actor_id ) {
				return new \WP_Error(
					'activitypub_actor_mismatch',
					\__( 'Activity actor does not match authenticated user.', 'activitypub' ),
					array( 'status' => 403 )
				);
			}
		}

		// Check object.attributedTo if present.
		$object = $data['object'] ?? $data;
		if ( is_array( $object ) && ! empty( $object['attributedTo'] ) ) {
			$attributed_to = object_to_uri( $object['attributedTo'] );
			if ( $attributed_to && $attributed_to !== $user_actor_id ) {
				return new \WP_Error(
					'activitypub_attribution_mismatch',
					\__( 'Object attributedTo does not match authenticated user.', 'activitypub' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Add default public addressing when the client omits recipients.
	 *
	 * Per the ActivityPub spec, the server adds addressing when the client
	 * does not provide it. Defaults to public with followers in cc.
	 *
	 * @since 8.1.0
	 *
	 * @param array                       $data The activity data.
	 * @param \Activitypub\Activity\Actor $user The authenticated user.
	 * @return array The activity data with addressing ensured.
	 */
	private function ensure_addressing( $data, $user ) {
		$recipients = extract_recipients_from_activity( $data );

		if ( ! empty( $recipients ) ) {
			return $data;
		}

		$data['to'] = array( 'https://www.w3.org/ns/activitystreams#Public' );
		$data['cc'] = array( $user->get_followers() );

		return $data;
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

	/**
	 * Ensure the activity object has required fields.
	 *
	 * For C2S activities, clients may not provide all required fields.
	 * The server should fill in attributedTo and published, but object IDs
	 * should only be set by handlers that create WordPress content.
	 *
	 * @param array                        $data The activity data.
	 * @param \Activitypub\Model\User|null $user The authenticated user.
	 * @return array The activity data with required fields ensured.
	 */
	private function ensure_object_id( $data, $user ) {
		// Check if there's an embedded object that needs fields.
		if ( ! isset( $data['object'] ) || ! is_array( $data['object'] ) ) {
			return $data;
		}

		$object = &$data['object'];

		// Set attributedTo if missing.
		if ( empty( $object['attributedTo'] ) && $user ) {
			$object['attributedTo'] = $user->get_id();
		}

		// Set published if missing.
		if ( empty( $object['published'] ) ) {
			$object['published'] = \gmdate( 'Y-m-d\TH:i:s\Z' );
		}

		return $data;
	}
}
