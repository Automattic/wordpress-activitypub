<?php
/**
 * Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;
use Activitypub\Collection\Inbox;
use Activitypub\Http;
use Activitypub\Moderation;
use Activitypub\OAuth\Scope;
use Activitypub\OAuth\Server as OAuth_Server;

use function Activitypub\camel_to_snake_case;
use function Activitypub\extract_recipients_from_activity;
use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\is_activity_public;
use function Activitypub\is_collection;
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
	protected $rest_base = 'inbox';

	/**
	 * The base for user-specific inbox routes.
	 *
	 * @var string
	 */
	protected $user_rest_base = '(?:users|actors)/(?P<user_id>[\-]?\d+)/inbox';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Shared inbox (POST only).
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => $this->get_create_item_args(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		// User-specific inbox (GET for C2S, POST for S2S).
		\register_rest_route(
			$this->namespace,
			'/' . $this->user_rest_base,
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
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
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
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => $this->get_create_item_args(),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Get the arguments for create_item.
	 *
	 * @return array The arguments.
	 */
	private function get_create_item_args() {
		return array(
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
			'to'     => array(
				'description'       => 'The primary recipients of the activity.',
				'type'              => array( 'string', 'array' ),
				'required'          => false,
				'sanitize_callback' => static function ( $param ) {
					if ( \is_string( $param ) ) {
						$param = array( $param );
					}

					return $param;
				},
			),
			'cc'     => array(
				'description'       => 'The secondary recipients of the activity.',
				'type'              => array( 'string', 'array' ),
				'sanitize_callback' => static function ( $param ) {
					if ( \is_string( $param ) ) {
						$param = array( $param );
					}

					return $param;
				},
			),
			'bcc'    => array(
				'description'       => 'The private recipients of the activity.',
				'type'              => array( 'string', 'array' ),
				'sanitize_callback' => static function ( $param ) {
					if ( \is_string( $param ) ) {
						$param = array( $param );
					}

					return $param;
				},
			),
		);
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
	 * Permission check for reading inbox items (C2S).
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return bool|\WP_Error True if authorized, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		// Check if C2S is enabled.
		if ( ! OAuth_Server::is_c2s_enabled() ) {
			return new \WP_Error(
				'activitypub_c2s_disabled',
				\__( 'Client-to-Server (C2S) support is not enabled.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$user_id = $request->get_param( 'user_id' );

		// Validate the user.
		$user = Actors::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		// Validate OAuth token and scope.
		$result = OAuth_Server::check_oauth_permission( $request, Scope::READ );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		// Verify the token belongs to the requested user.
		$token   = OAuth_Server::get_current_token();
		$user_id = absint( $user_id );

		if ( ! $token || $token->get_user_id() !== $user_id ) {
			return new \WP_Error(
				'activitypub_unauthorized',
				\__( 'You can only read your own inbox.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
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

			// Filter out blocked recipients.
			$allowed_recipients = array();
			foreach ( $recipients as $user_id ) {
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
				} else {
					$allowed_recipients[] = $user_id;

					/**
					 * ActivityPub inbox action.
					 *
					 * @deprecated 7.6.0 Support activitypub_inbox_shared instead to avoid duplicate processing.
					 *
					 * @param array              $data     The data array.
					 * @param int                $user_id  The user ID.
					 * @param string             $type     The type of the activity.
					 * @param Activity|\WP_Error $activity The Activity object.
					 * @param string             $context  The context of the request (shared_inbox when called from shared inbox endpoint).
					 */
					\do_action( 'activitypub_inbox', $data, $user_id, $type, $activity, Inbox::CONTEXT_SHARED_INBOX );

					/**
					 * ActivityPub inbox action for specific activity types.
					 *
					 * @deprecated 7.6.0 Support activitypub_inbox_shared_{type} instead to avoid duplicate processing.
					 *
					 * @param array              $data     The data array.
					 * @param int                $user_id  The user ID.
					 * @param Activity|\WP_Error $activity The Activity object.
					 * @param string             $context  The context of the request (shared_inbox when called from shared inbox endpoint).
					 */
					\do_action( 'activitypub_inbox_' . $type, $data, $user_id, $activity, Inbox::CONTEXT_SHARED_INBOX );
				}
			}

			/**
			 * ActivityPub shared inbox action.
			 *
			 * This hook fires once per activity with all recipients.
			 * Preferred for new implementations to avoid duplication.
			 *
			 * @since 7.6.0
			 *
			 * @param array              $data       The data array.
			 * @param array              $recipients Array of user IDs.
			 * @param string             $type       The type of the activity.
			 * @param Activity|\WP_Error $activity   The Activity object.
			 * @param string             $context    The context of the request.
			 */
			\do_action( 'activitypub_inbox_shared', $data, $allowed_recipients, $type, $activity, Inbox::CONTEXT_SHARED_INBOX );

			/**
			 * ActivityPub shared inbox action for specific activity types.
			 *
			 * This hook fires once per activity with all recipients.
			 * Preferred for new implementations to avoid duplication.
			 *
			 * @since 7.6.0
			 *
			 * @param array              $data       The data array.
			 * @param array              $recipients Array of user IDs.
			 * @param Activity|\WP_Error $activity   The Activity object.
			 * @param string             $context    The context of the request.
			 */
			\do_action( 'activitypub_inbox_shared_' . $type, $data, $allowed_recipients, $activity, Inbox::CONTEXT_SHARED_INBOX );

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
				$result = Inbox::add( $activity, $allowed_recipients );

				/**
				 * Fires after an ActivityPub Inbox activity has been handled.
				 *
				 * @param array              $data     The data array.
				 * @param array              $user_ids The user IDs.
				 * @param string             $type     The type of the activity.
				 * @param Activity|\WP_Error $activity The Activity object.
				 * @param \WP_Error|int      $result   The ID of the inbox item that was created, or WP_Error if failed.
				 * @param string             $context  The context of the request ('inbox' or 'shared_inbox').
				 */
				\do_action( 'activitypub_handled_inbox', $data, $allowed_recipients, $type, $activity, $result, Inbox::CONTEXT_SHARED_INBOX );

				/**
				 * Fires after an ActivityPub Inbox activity has been handled.
				 *
				 * @param array              $data     The data array.
				 * @param array              $user_ids The user IDs.
				 * @param Activity|\WP_Error $activity The Activity object.
				 * @param \WP_Error|int      $result   The ID of the inbox item that was created, or WP_Error if failed.
				 * @param string             $context  The context of the request ('inbox' or 'shared_inbox').
				 */
				\do_action( 'activitypub_handled_inbox_' . $type, $data, $allowed_recipients, $activity, $result, Inbox::CONTEXT_SHARED_INBOX );
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
		$user_ids = array();

		if ( is_activity_public( $activity ) ) {
			$user_ids = Following::get_follower_ids( $activity['actor'] );
		}

		$recipients = extract_recipients_from_activity( $activity );

		foreach ( $recipients as $recipient ) {
			// Skip public audience identifiers - they're not actual recipients to fetch.
			if ( \in_array( $recipient, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS, true ) ) {
				continue;
			}

			if ( ! is_same_domain( $recipient ) ) {
				$collection = Http::get_remote_object( $recipient );

				// If it is a remote actor we can skip it.
				if ( \is_wp_error( $collection ) ) {
					continue;
				}

				if ( is_collection( $collection ) ) {
					$_user_ids = Following::get_follower_ids( $activity['actor'] );
					$user_ids  = array_merge( $user_ids, $_user_ids );
					continue;
				}
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

		// Check for an Actor in the Object field.
		if ( empty( $user_ids ) ) {
			$user_id = Actors::get_id_by_resource( $activity['object'] );

			if ( ! \is_wp_error( $user_id ) && user_can_activitypub( $user_id ) ) {
				$user_ids[] = $user_id;
			}
		}

		return array_unique( array_map( 'intval', $user_ids ) );
	}
}
