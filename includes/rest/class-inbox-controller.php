<?php
/**
 * Inbox_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;
use Activitypub\Collection\Inbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Http;
use Activitypub\Moderation;

use function Activitypub\camel_to_snake_case;
use function Activitypub\extract_recipients_from_activity;
use function Activitypub\is_activity_public;
use function Activitypub\is_collection;
use function Activitypub\is_same_domain;
use function Activitypub\object_to_uri;
use function Activitypub\user_can_activitypub;

/**
 * Inbox_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#inbox
 */
class Inbox_Controller extends \WP_REST_Controller {
	use Verification;
	use Language_Map;

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
			\do_action( 'activitypub_rest_inbox_disallowed', $data, null, $type, $activity );
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
		$user_ids       = array();
		$remote_fetches = 0;
		$cap_notified   = false;

		/**
		 * Filters the maximum number of remote recipient URLs that can be
		 * fetched per incoming activity.
		 *
		 * @since unreleased
		 *
		 * @param int $max_remote_fetches Maximum number of remote fetches. Default 10.
		 */
		$max_remote_fetches = (int) \apply_filters( 'activitypub_max_remote_recipient_fetches', 10 );

		// AS2 allows actor and followers to be either an IRI string or an inline object; normalize to a URI.
		$actor_uri           = ! empty( $activity['actor'] ) ? object_to_uri( $activity['actor'] ) : null;
		$actor_followers_url = $this->get_cached_followers_url( $actor_uri );

		if ( is_activity_public( $activity ) ) {
			$user_ids = Following::get_follower_ids( $actor_uri );
		}

		$recipients = extract_recipients_from_activity( $activity );

		/*
		 * Pre-compute which recipients are already known remote actors so the
		 * cached-actor short-circuit becomes an O(1) array lookup rather than
		 * one DB query per recipient. This bounds the DB cost of a flood of
		 * unknown recipient URIs to one batched SELECT (chunked) regardless
		 * of how many were sent.
		 */
		$candidate_uris = array();
		foreach ( $recipients as $recipient ) {
			if (
				! \is_string( $recipient )
				|| \in_array( $recipient, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS, true )
				|| is_same_domain( $recipient )
				|| $recipient === $actor_followers_url
			) {
				continue;
			}
			$candidate_uris[] = $recipient;
		}
		$cached_uris = $candidate_uris ? Remote_Actors::get_existing_uris( $candidate_uris ) : array();

		foreach ( $recipients as $recipient ) {
			// Skip public audience identifiers - they're not actual recipients to fetch.
			if ( \in_array( $recipient, ACTIVITYPUB_PUBLIC_AUDIENCE_IDENTIFIERS, true ) ) {
				continue;
			}

			if ( ! is_same_domain( $recipient ) ) {
				// Known followers collection: resolve from local DB, no fetch needed.
				if ( $recipient === $actor_followers_url ) {
					$user_ids = array_merge( $user_ids, Following::get_follower_ids( $actor_uri ) );
					continue;
				}

				// Already cached as a remote actor: not a collection, so no local recipients to add.
				if ( isset( $cached_uris[ $recipient ] ) ) {
					continue;
				}

				// Unknown URL: cap remote fetches to prevent abuse via large audience/recipient fields.
				if ( $remote_fetches >= $max_remote_fetches ) {
					if ( ! $cap_notified ) {
						$cap_notified = true;

						/**
						 * Fires when an incoming activity hits the remote recipient fetch cap.
						 *
						 * Fires once per activity on the first recipient that exceeds the cap,
						 * not for each subsequent skipped recipient. Hook this to surface
						 * cap hits in your logging system of choice (Jetpack, Sentry, syslog, etc.).
						 *
						 * @since unreleased
						 *
						 * @param array  $activity  The incoming activity data.
						 * @param string $recipient The recipient URI that was skipped.
						 * @param int    $cap       The configured cap.
						 */
						\do_action( 'activitypub_remote_recipient_fetch_cap_reached', $activity, $recipient, $max_remote_fetches );
					}
					continue;
				}
				++$remote_fetches;

				$collection = Http::get_remote_object( $recipient );

				if ( \is_wp_error( $collection ) ) {
					continue;
				}

				if ( is_collection( $collection ) ) {
					$user_ids = array_merge( $user_ids, Following::get_follower_ids( $actor_uri ) );
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
		if ( empty( $user_ids ) && ! empty( $activity['object'] ) ) {
			$user_id = Actors::get_id_by_resource( $activity['object'] );

			if ( ! \is_wp_error( $user_id ) && user_can_activitypub( $user_id ) ) {
				$user_ids[] = $user_id;
			}
		}

		return array_unique( array_map( 'intval', $user_ids ) );
	}

	/**
	 * Look up an actor's followers collection URL from the cached profile.
	 *
	 * Used to detect followers-addressed recipients without an outbound fetch.
	 *
	 * @param string|null $actor_uri Normalized actor URI.
	 *
	 * @return string|null The followers collection URL, or null if not cached/available.
	 */
	private function get_cached_followers_url( $actor_uri ) {
		if ( empty( $actor_uri ) ) {
			return null;
		}

		$actor_post = Remote_Actors::get_by_uri( $actor_uri );
		if ( \is_wp_error( $actor_post ) ) {
			return null;
		}

		// Match Remote_Actors::get_actor()'s storage fallback: legacy actor JSON lives in postmeta when post_content is empty.
		$json = $actor_post->post_content;
		if ( empty( $json ) ) {
			$json = \get_post_meta( $actor_post->ID, '_activitypub_actor_json', true );
		}

		$actor_data = \json_decode( $json, true );
		if ( empty( $actor_data['followers'] ) ) {
			return null;
		}

		return object_to_uri( $actor_data['followers'] );
	}
}
