<?php
/**
 * Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;

use function Activitypub\get_context;
use function Activitypub\url_to_authorid;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;
use function Activitypub\extract_recipients_from_activity;

/**
 * Inbox_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox_Controller extends \WP_REST_Controller {
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
	protected $rest_base = '(?:users|actors)/(?P<user_id>[\w\-\.]+)/inbox';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Shared inbox.
		\register_rest_route(
			$this->namespace,
			'/inbox',
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'shared_inbox_post' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'id'     => array(
							'description' => 'The unique identifier for the activity.',
							'type'        => 'string',
							'required'    => true,
							'format'      => 'uri',
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
						'to'     => array(
							'description'       => 'The primary recipients of the activity.',
							'type'              => array( 'string', 'array' ),
							'required'          => false,
							'sanitize_callback' => function ( $param ) {
								if ( \is_string( $param ) ) {
									$param = array( $param );
								}

								return $param;
							},
						),
						'cc'     => array(
							'description'       => 'The secondary recipients of the activity.',
							'type'              => array( 'string', 'array' ),
							'sanitize_callback' => function ( $param ) {
								if ( \is_string( $param ) ) {
									$param = array( $param );
								}

								return $param;
							},
						),
						'bcc'    => array(
							'description'       => 'The private recipients of the activity.',
							'type'              => array( 'string', 'array' ),
							'sanitize_callback' => function ( $param ) {
								if ( \is_string( $param ) ) {
									$param = array( $param );
								}

								return $param;
							},
						),
					),
				),
				'schema' => array( $this, 'get_collection_schema' ),
			)
		);

		// User inbox.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'user_inbox_get' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'user_id' => array(
							'type'     => 'string',
							'required' => true,
							'pattern'  => '[\w\-\.]+',
						),
						'page'    => array(
							'type' => 'integer',
						),
					),
					'schema'              => array( $this, 'get_collection_schema' ),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'user_inbox_post' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'user_id' => array(
							'type'     => 'string',
							'required' => true,
							'pattern'  => '[\w\-\.]+',
						),
						'id'      => array(
							'required'          => true,
							'sanitize_callback' => 'esc_url_raw',
						),
						'actor'   => array(
							'required'          => true,
							'sanitize_callback' => '\Activitypub\object_to_uri',
						),
						'type'    => array(
							'required' => true,
						),
						'object'  => array(
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
	public function user_inbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		/**
		 * Fires before the ActivityPub inbox is created and sent to the client.
		 */
		\do_action( 'activitypub_rest_inbox_pre' );

		$response = array(
			'@context'     => get_context(),
			'id'           => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user->get__id() ) ),
			'generator'    => 'http://wordpress.org/?v=' . get_masked_wp_version(),
			'type'         => 'OrderedCollectionPage',
			'partOf'       => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user->get__id() ) ),
			'totalItems'   => 0,
			'orderedItems' => array(),
			'first'        => get_rest_url_by_path( \sprintf( 'actors/%d/inbox', $user->get__id() ) ),
		);

		/**
		 * Filters the ActivityPub inbox data before it is sent to the client.
		 *
		 * @param array $response The ActivityPub inbox array.
		 */
		$response = \apply_filters( 'activitypub_rest_inbox_array', (object) $response );

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
	public function user_inbox_post( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$type     = \strtolower( $type );

		/**
		 * ActivityPub inbox action.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_inbox', $data, $user->get__id(), $type, $activity );

		/**
		 * ActivityPub inbox action for specific activity types.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $data, $user->get__id(), $activity );

		$response = \rest_ensure_response( array() );
		$response->set_status( 202 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * The shared inbox.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function shared_inbox_post( $request ) {
		$data     = $request->get_json_params();
		$activity = Activity::init_from_array( $data );
		$type     = $request->get_param( 'type' );
		$type     = \strtolower( $type );

		/**
		 * ActivityPub inbox action.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param string   $type     The type of the activity.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( 'activitypub_inbox', $data, null, $type, $activity );

		/**
		 * ActivityPub inbox action for specific activity types.
		 *
		 * @param array    $data     The data array.
		 * @param int|null $user_id  The user ID.
		 * @param Activity $activity The Activity object.
		 */
		\do_action( "activitypub_inbox_{$type}", $data, null, $activity );

		$response = \rest_ensure_response( array() );
		$response->set_status( 202 );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Get local user recipients.
	 *
	 * @param array $data The data array.
	 *
	 * @return array The list of local users.
	 */
	public function get_recipients( $data ) {
		$recipients = extract_recipients_from_activity( $data );
		$users      = array();

		foreach ( $recipients as $recipient ) {
			$user_id = url_to_authorid( $recipient );

			$user = \get_user_by( 'id', $user_id );

			if ( $user ) {
				$users[] = $user;
			}
		}

		return $users;
	}

	/**
	 * Retrieves the schema for a single inbox item, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'activity',
			'type'       => 'object',
			'properties' => array(
				'@context' => array(
					'description' => 'The JSON-LD context for the activity.',
					'type'        => array( 'string', 'array', 'object' ),
					'required'    => true,
				),
				'id'       => array(
					'description' => 'The unique identifier for the activity.',
					'type'        => 'string',
					'format'      => 'uri',
					'required'    => true,
				),
				'type'     => array(
					'description' => 'The type of the activity.',
					'type'        => 'string',
					'enum'        => array( 'Create', 'Update', 'Delete', 'Follow', 'Accept', 'Reject', 'Add', 'Remove', 'Like', 'Announce', 'Undo', 'Block' ),
					'required'    => true,
				),
				'actor'    => array(
					'description' => 'The actor performing the activity.',
					'type'        => array( 'string', 'object' ),
					'format'      => 'uri',
					'required'    => true,
				),
				'object'   => array(
					'description' => 'The object of the activity.',
					'type'        => array( 'string', 'object' ),
					'required'    => true,
				),
				'to'       => array(
					'description' => 'The primary recipients of the activity.',
					'type'        => 'array',
					'items'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
				),
				'cc'       => array(
					'description' => 'The secondary recipients of the activity.',
					'type'        => 'array',
					'items'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
				),
				'bcc'      => array(
					'description' => 'The private recipients of the activity.',
					'type'        => 'array',
					'items'       => array(
						'type'   => 'string',
						'format' => 'uri',
					),
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Retrieves the schema for the inbox collection, conforming to JSON Schema.
	 *
	 * @return array Collection schema data.
	 */
	public function get_collection_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'inbox',
			'type'       => 'object',
			'properties' => array(
				'@context'     => array(
					'description' => 'The JSON-LD context for the collection.',
					'type'        => array( 'string', 'array', 'object' ),
					'required'    => true,
				),
				'id'           => array(
					'description' => 'The unique identifier for the collection.',
					'type'        => 'string',
					'format'      => 'uri',
					'required'    => true,
				),
				'type'         => array(
					'description' => 'The type of the collection.',
					'type'        => 'string',
					'enum'        => array( 'OrderedCollection', 'OrderedCollectionPage' ),
					'required'    => true,
				),
				'totalItems'   => array(
					'description' => 'The total number of items in the collection.',
					'type'        => 'integer',
					'minimum'     => 0,
					'required'    => true,
				),
				'orderedItems' => array(
					'description' => 'The items in the collection.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'object',
					),
					'required'    => true,
				),
				'first'        => array(
					'description' => 'The first page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'last'         => array(
					'description' => 'The last page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'next'         => array(
					'description' => 'The next page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'prev'         => array(
					'description' => 'The previous page of the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'partOf'       => array(
					'description' => 'The collection this page is part of.',
					'type'        => 'string',
					'format'      => 'uri',
				),
				'generator'    => array(
					'description' => 'The software used to generate the collection.',
					'type'        => 'string',
					'format'      => 'uri',
				),
			),
		);

		$this->schema = $schema;

		return $this->add_additional_fields_schema( $this->schema );
	}
}
