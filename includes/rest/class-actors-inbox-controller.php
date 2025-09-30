<?php
/**
 * Actors_Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Moderation;

use function Activitypub\camel_to_snake_case;
use function Activitypub\get_context;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * Actors_Inbox_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Actors_Inbox_Controller extends Actors_Controller {
	use Collection;

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
					'permission_callback' => '__return_true',
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
						),
					),
					'schema'              => array( $this, 'get_collection_schema' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
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
							'description' => 'The type of the activity.',
							'type'        => 'string',
							'required'    => true,
						),
						'object' => array(
							'description'       => 'The object of the activity.',
							'required'          => true,
							'validate_callback' => function ( $param, $request, $key ) {
								/**
								 * Filter the ActivityPub object validation.
								 *
								 * @param bool   $validate The validation result.
								 * @param array  $param    The object data.
								 * @param object $request  The request object.
								 * @param string $key      The key.
								 */
								return \apply_filters( 'activitypub_validate_object', true, $param, $request, $key );
							},
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Renders the user-inbox.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );

		/**
		 * Fires before the ActivityPub inbox is created and sent to the client.
		 */
		\do_action( 'activitypub_rest_inbox_pre' );

		$response = array(
			'@context'     => get_context(),
			'id'           => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user_id ) ),
			'generator'    => 'https://wordpress.org/?v=' . get_masked_wp_version(),
			'type'         => 'OrderedCollection',
			'totalItems'   => 0,
			'orderedItems' => array(),
		);

		/**
		 * Filters the ActivityPub inbox data before it is sent to the client.
		 *
		 * @param array $response The ActivityPub inbox array.
		 */
		$response = \apply_filters( 'activitypub_rest_inbox_array', $response );

		$response = $this->prepare_collection_response( $response, $request );
		if ( \is_wp_error( $response ) ) {
			return $response;
		}

		/**
		 * Fires after the ActivityPub inbox has been created and sent to the client.
		 */
		\do_action( 'activitypub_inbox_post' );

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
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
			 */
			\do_action( 'activitypub_inbox', $data, $user_id, $type, $activity );

			/**
			 * ActivityPub inbox action for specific activity types.
			 *
			 * @param array              $data     The data array.
			 * @param int|null           $user_id  The user ID.
			 * @param Activity|\WP_Error $activity The Activity object.
			 */
			\do_action( 'activitypub_inbox_' . $type, $data, $user_id, $activity );
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
}
