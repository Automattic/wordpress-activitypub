<?php
/**
 * Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Moderation;

use function Activitypub\camel_to_snake_case;
use function Activitypub\extract_recipients_from_activity;
use function Activitypub\is_same_domain;
use function Activitypub\user_can_activitypub;

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
	protected $rest_base = 'inbox';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
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
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * The shared inbox.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error.
	 */
	public function create_item( $request ) {
		$data = $request->get_json_params();
		$type = camel_to_snake_case( $request->get_param( 'type' ) );

		/* @var Activity $activity Activity object.*/
		$activity = Activity::init_from_array( $data );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( Moderation::activity_is_blocked( $activity ) ) {
			/**
			 * ActivityPub inbox disallowed activity.
			 *
			 * @param array              $data     The data array.
			 * @param null               $user_id  The user ID.
			 * @param string             $type     The type of the activity.
			 * @param Activity|\WP_Error $activity The Activity object.
			 */
			do_action( 'activitypub_rest_inbox_disallowed', $data, null, $type, $activity );
		} else {
			$recipients = $this->get_local_recipients( $data );

			foreach ( $recipients as $user_id ) {
				// Check user-specific blocks for this recipient.
				if ( Moderation::activity_is_blocked_for_user( $activity, $user_id ) ) {
					/**
					 * ActivityPub inbox disallowed activity for specific user.
					 *
					 * @param array              $data     The data array.
					 * @param int                $user_id  The user ID.
					 * @param string             $type     The type of the activity.
					 * @param Activity|\WP_Error $activity The Activity object.
					 */
					\do_action( 'activitypub_rest_inbox_disallowed', $data, $user_id, $type, $activity );
					continue;
				}

				/**
				 * ActivityPub inbox action.
				 *
				 * @param array              $data     The data array.
				 * @param int                $user_id  The user ID.
				 * @param string             $type     The type of the activity.
				 * @param Activity|\WP_Error $activity The Activity object.
				 */
				\do_action( 'activitypub_inbox', $data, $user_id, $type, $activity );

				/**
				 * ActivityPub inbox action for specific activity types.
				 *
				 * @param array              $data     The data array.
				 * @param int                $user_id  The user ID.
				 * @param Activity|\WP_Error $activity The Activity object.
				 */
				\do_action( 'activitypub_inbox_' . $type, $data, $user_id, $activity );
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
	 * Retrieves the schema for a single inbox item, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$schema = array(
			'$schema'    => 'https://json-schema.org/draft-04/schema#',
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
	 * Extract recipients from the given Activity.
	 *
	 * @param array $activity The activity data.
	 *
	 * @return array An array of user IDs who are the recipients of the activity.
	 */
	private function get_local_recipients( $activity ) {
		$recipients = extract_recipients_from_activity( $activity );
		$user_ids   = array();

		foreach ( $recipients as $recipient ) {

			if ( ! is_same_domain( $recipient ) ) {
				continue;
			}

			$user_id = Actors::get_id_by_resource( $recipient );

			if ( \is_wp_error( $user_id ) ) {
				continue;
			}

			if ( ! user_can_activitypub( $user_id ) ) {
				continue;
			}

			$user_ids[] = $user_id;
		}

		return $user_ids;
	}
}
