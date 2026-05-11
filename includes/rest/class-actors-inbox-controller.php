<?php
/**
 * Actors_Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Inbox;
use Activitypub\Moderation;

use function Activitypub\camel_to_snake_case;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\object_to_uri;

/**
 * Actors_Inbox_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Actors_Inbox_Controller extends Actors_Controller {
	use Collection;
	use Event_Stream;
	use Language_Map;

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inbox',
			array(
				'args'   => array(
					'user_id' => array(
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( $this, 'verify_authentication' ),
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
					'schema'              => array( $this, 'get_collection_schema' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'verify_signature' ),
					'args'                => array(
						'id'     => array(
							'description' => 'The unique identifier for the activity.',
							'type'        => 'string',
							'format'      => 'uri',
							'required'    => true,
						),
						'actor'  => array(
							'description'       => 'The actor performing the activity.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => '\Activitypub\object_to_uri',
						),
						'type'   => array(
							'description'       => 'The type of the activity.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_html_class',
							'validate_callback' => static function ( $param ) {
								// Reject values that sanitize to empty so dynamic hook names always have a suffix.
								return '' !== \sanitize_html_class( (string) $param );
							},
						),
						'object' => array(
							'description'       => 'The object of the activity.',
							'required'          => true,
							'sanitize_callback' => array( $this, 'localize_language_maps' ),
							'validate_callback' => static function ( $param, $request, $key ) {
								/**
								 * Filter the ActivityPub object validation.
								 *
								 * @param bool             $validate The validation result.
								 * @param array            $param    The object data.
								 * @param \WP_REST_Request $request  The request object.
								 * @param string           $key      The key.
								 */
								return \apply_filters( 'activitypub_validate_object', true, $param, $request, $key );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/inbox/stream',
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
						$this->stream_collection( $request->get_param( 'user_id' ), 'inbox' );
					},
					'permission_callback' => array( $this, 'get_stream_permissions_check' ),
				),
			)
		);

		\add_action( 'activitypub_inbox_create_item', array( self::class, 'process_create_item' ) );
	}

	/**
	 * Retrieves a collection of inbox items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$page    = $request->get_param( 'page' ) ?? 1;
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_id( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * Action triggered prior to the ActivityPub inbox being created and sent to the client.
		 *
		 * @param \WP_REST_Request $request The request object.
		 */
		\do_action( 'activitypub_rest_inbox_pre', $request );

		$args = array(
			'posts_per_page' => $request->get_param( 'per_page' ),
			'paged'          => $page,
			'post_type'      => Inbox::POST_TYPE,
			'post_status'    => 'publish',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'   => '_activitypub_user_id',
					'value' => $user_id,
				),
			),
		);

		/**
		 * Filters WP_Query arguments when querying Inbox items via the REST API.
		 *
		 * Enables adding extra arguments or setting defaults for an inbox collection request.
		 *
		 * @param array            $args    Array of arguments for WP_Query.
		 * @param \WP_REST_Request $request The REST API request.
		 */
		$args = \apply_filters( 'activitypub_rest_inbox_query', $args, $request );

		$inbox_query  = new \WP_Query();
		$query_result = $inbox_query->query( $args );

		$response = array(
			'@context'     => Base_Object::JSON_LD_CONTEXT,
			'id'           => get_rest_url_by_path( sprintf( 'actors/%d/inbox', $user_id ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'actor'        => $user->get_id(),
			'type'         => 'OrderedCollection',
			'totalItems'   => (int) $inbox_query->found_posts,
			'eventStream'  => $this->get_stream_url( $user_id, 'inbox' ),
			'orderedItems' => array(),
		);

		\update_postmeta_cache( \wp_list_pluck( $query_result, 'ID' ) );
		foreach ( $query_result as $inbox_item ) {
			if ( ! $inbox_item instanceof \WP_Post ) {
				continue;
			}

			$response['orderedItems'][] = $this->prepare_item_for_response( $inbox_item, $request );
		}

		$response = $this->prepare_collection_response( $response, $request );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		/**
		 * Filter the ActivityPub inbox array.
		 *
		 * @param array            $response The ActivityPub inbox array.
		 * @param \WP_REST_Request $request  The request object.
		 */
		$response = \apply_filters( 'activitypub_rest_inbox_array', $response, $request );

		/**
		 * Action triggered after the ActivityPub inbox has been created and sent to the client.
		 *
		 * @param \WP_REST_Request $request The request object.
		 */
		\do_action( 'activitypub_rest_inbox_post', $request );

		// Fire deprecated hook for backward compatibility.
		\do_action_deprecated(
			'activitypub_inbox_post',
			array( $request ),
			'8.1.0',
			'activitypub_rest_inbox_post'
		);

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Prepares the item for the REST response.
	 *
	 * @param mixed            $item    WordPress representation of the item.
	 * @param \WP_REST_Request $request Request object.
	 * @return array Response object on success.
	 */
	public function prepare_item_for_response( $item, $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$activity = \json_decode( $item->post_content, true );

		return $activity;
	}

	/**
	 * Handles user-inbox requests.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function create_item( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$data    = $request->get_json_params();
		$type    = camel_to_snake_case( $request->get_param( 'type' ) );

		/* @var Activity $activity Activity object.*/
		$activity = Activity::init_from_array( $data );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( Moderation::activity_is_blocked( $activity, $user_id ) ) {
			/**
			 * ActivityPub inbox disallowed activity.
			 *
			 * @param array              $data     The data array.
			 * @param int|null           $user_id  The user ID.
			 * @param string             $type     The type of the activity.
			 * @param Activity|\WP_Error $activity The Activity object.
			 */
			do_action( 'activitypub_rest_inbox_disallowed', $data, $user_id, $type, $activity );
		} else {
			/**
			 * ActivityPub inbox action.
			 *
			 * @param array              $data     The data array.
			 * @param int|null           $user_id  The user ID.
			 * @param string             $type     The type of the activity.
			 * @param Activity|\WP_Error $activity The Activity object.
			 * @param string             $context  The context of the request.
			 */
			\do_action( 'activitypub_inbox', $data, $user_id, $type, $activity, Inbox::CONTEXT_INBOX );

			/**
			 * ActivityPub inbox action for specific activity types.
			 *
			 * @param array              $data     The data array.
			 * @param int|null           $user_id  The user ID.
			 * @param Activity|\WP_Error $activity The Activity object.
			 * @param string             $context  The context of the request.
			 */
			\do_action( 'activitypub_inbox_' . $type, $data, $user_id, $activity, Inbox::CONTEXT_INBOX );

			/**
			 * Filter to skip inbox storage.
			 *
			 * Skip inbox storage for debugging purposes or to reduce load for
			 * certain Activity-Types, like "Delete".
			 *
			 * @param bool  $skip Whether to skip inbox storage.
			 * @param array $data  The activity data array.
			 *
			 * @return bool Whether to skip inbox storage.
			 */
			$skip = \apply_filters( 'activitypub_skip_inbox_storage', false, $data );

			if ( ! $skip ) {
				$activity_id = object_to_uri( $data );

				Inbox::add( $activity, (array) $user_id );

				\wp_clear_scheduled_hook( 'activitypub_inbox_create_item', array( $activity_id ) );
				\wp_schedule_single_event( time() + 15, 'activitypub_inbox_create_item', array( $activity_id ) );
			}
		}

		$response = \rest_ensure_response(
			array(
				'type'   => 'https://w3id.org/fep/c180#approval-required',
				'title'  => 'Approval Required',
				'status' => '202',
				'detail' => 'This activity requires approval before it can be processed.',
			)
		);
		$response->set_status( 202 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Retrieves the schema for the inbox collection, conforming to JSON Schema.
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

		// Add inbox-specific properties.
		$schema['title']                   = 'inbox';
		$schema['properties']['generator'] = array(
			'description' => 'The software used to generate the collection.',
			'type'        => 'string',
			'format'      => 'uri',
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Process cached inbox activity.
	 *
	 * Retrieves all collected user IDs for an activity and processes them together.
	 *
	 * @param string $activity_id The activity ID.
	 */
	public static function process_create_item( $activity_id ) {
		// Deduplicate if multiple inbox items were created due to race condition.
		$inbox_item = Inbox::deduplicate( $activity_id );
		if ( ! $inbox_item ) {
			return;
		}

		$data = \json_decode( $inbox_item->post_content, true );
		// Reconstruct activity from inbox post.
		$activity = Activity::init_from_array( $data );
		// Sanitize again here: the type comes from stored activity JSON, which bypassed REST arg sanitization.
		$type     = camel_to_snake_case( \sanitize_html_class( (string) $activity->get_type() ) );
		$context  = Inbox::CONTEXT_INBOX;
		$user_ids = Inbox::get_recipients( $inbox_item->ID );

		/**
		 * Fires after any ActivityPub Inbox activity has been handled, regardless of activity type.
		 *
		 * This hook is triggered for all activity types processed by the inbox handler.
		 *
		 * @param array    $data     The data array.
		 * @param array    $user_ids The user IDs.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 * @param int      $result   The ID of the inbox item that was created, or WP_Error if failed.
		 * @param string   $context  The context of the request ('inbox' or 'shared_inbox').
		 */
		\do_action( 'activitypub_handled_inbox', $data, $user_ids, $type, $activity, $inbox_item->ID, $context );

		/**
		 * Fires after an ActivityPub Inbox activity has been handled.
		 *
		 * @param array    $data     The data array.
		 * @param array    $user_ids The user IDs.
		 * @param Activity $activity The Activity object.
		 * @param int      $result   The ID of the inbox item that was created, or WP_Error if failed.
		 * @param string   $context  The context of the request ('inbox' or 'shared_inbox').
		 */
		\do_action( 'activitypub_handled_inbox_' . $type, $data, $user_ids, $activity, $inbox_item->ID, $context );
	}
}
