<?php
/**
 * ActivityPub Actors REST-Class
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors as Actor_Collection;
use Activitypub\Webfinger;

/**
 * ActivityPub Actors REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Actors_Controller extends \WP_REST_Controller {
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
	protected $rest_base = '(?:users|actors)\/(?P<user_id>[-]?\d+)';

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
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_user_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/remote-follow',
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
					'callback'            => array( $this, 'get_remote_follow_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'resource' => array(
							'description' => 'The resource to follow.',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Retrieves a single actor.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actor_Collection::get_by_id( $user_id );

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_users_pre' );

		$data = $user->to_array();

		$response = \rest_ensure_response( $data );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );
		$response->header( 'Link', \sprintf( '<%1$s>; rel="alternate"; type="application/activity+json"', $user->get_id() ) );

		return $response;
	}

	/**
	 * Retrieves the remote follow endpoint.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_remote_follow_item( $request ) {
		$resource = $request->get_param( 'resource' );
		$user_id  = $request->get_param( 'user_id' );
		$user     = Actor_Collection::get_by_id( $user_id );

		$template = Webfinger::get_remote_follow_endpoint( $resource );

		if ( \is_wp_error( $template ) ) {
			return $template;
		}

		$resource = $user->get_webfinger();
		$url      = \str_replace( '{uri}', $resource, $template );

		return \rest_ensure_response(
			array(
				'url'      => $url,
				'template' => $template,
			)
		);
	}

	/**
	 * Retrieves the actor schema, conforming to JSON Schema.
	 *
	 * @return array Item schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'actor',
			'type'       => 'object',
			'properties' => array(
				'@context'                  => array(
					'description' => 'The JSON-LD context for the response.',
					'type'        => array( 'array', 'object' ),
					'readonly'    => true,
				),
				'id'                        => array(
					'description' => 'The unique identifier for the actor.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'type'                      => array(
					'description' => 'The type of the actor.',
					'type'        => 'string',
					'enum'        => array( 'Person', 'Service', 'Organization', 'Application', 'Group' ),
					'readonly'    => true,
				),
				'attachment'                => array(
					'description' => 'Additional information attached to the actor.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'type'  => array(
								'type' => 'string',
								'enum' => array( 'PropertyValue', 'Link' ),
							),
							'name'  => array(
								'type' => 'string',
							),
							'value' => array(
								'type' => 'string',
							),
							'href'  => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'rel'   => array(
								'type'  => 'array',
								'items' => array(
									'type' => 'string',
								),
							),
						),
					),
					'readonly'    => true,
				),
				'name'                      => array(
					'description' => 'The display name of the actor.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'icon'                      => array(
					'description' => 'The icon/avatar of the actor.',
					'type'        => 'object',
					'properties'  => array(
						'type' => array(
							'type' => 'string',
						),
						'url'  => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
					'readonly'    => true,
				),
				'published'                 => array(
					'description' => 'The date the actor was published.',
					'type'        => 'string',
					'format'      => 'date-time',
					'readonly'    => true,
				),
				'summary'                   => array(
					'description' => 'A summary about the actor.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'tag'                       => array(
					'description' => 'Tags associated with the actor.',
					'type'        => 'array',
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'type' => array(
								'type' => 'string',
							),
							'href' => array(
								'type'   => 'string',
								'format' => 'uri',
							),
							'name' => array(
								'type' => 'string',
							),
						),
					),
					'readonly'    => true,
				),
				'url'                       => array(
					'description' => 'The URL to the actor\'s profile page.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'inbox'                     => array(
					'description' => 'The inbox endpoint for the actor.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'outbox'                    => array(
					'description' => 'The outbox endpoint for the actor.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'following'                 => array(
					'description' => 'The following endpoint for the actor.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'followers'                 => array(
					'description' => 'The followers endpoint for the actor.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'streams'                   => array(
					'description' => 'The streams associated with the actor.',
					'type'        => 'array',
					'readonly'    => true,
				),
				'preferredUsername'         => array(
					'description' => 'The preferred username of the actor.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'publicKey'                 => array(
					'description' => 'The public key information for the actor.',
					'type'        => 'object',
					'properties'  => array(
						'id'           => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'owner'        => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'publicKeyPem' => array(
							'type' => 'string',
						),
					),
					'readonly'    => true,
				),
				'manuallyApprovesFollowers' => array(
					'description' => 'Whether the actor manually approves followers.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'attributionDomains'        => array(
					'description' => 'The attribution domains for the actor.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'readonly'    => true,
				),
				'featured'                  => array(
					'description' => 'The featured collection endpoint for the actor.',
					'type'        => 'string',
					'format'      => 'uri',
					'readonly'    => true,
				),
				'indexable'                 => array(
					'description' => 'Whether the actor is indexable.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'webfinger'                 => array(
					'description' => 'The webfinger identifier for the actor.',
					'type'        => 'string',
					'readonly'    => true,
				),
				'discoverable'              => array(
					'description' => 'Whether the actor is discoverable.',
					'type'        => 'boolean',
					'readonly'    => true,
				),
				'generator'                 => array(
					'description' => 'The generator of the object.',
					'type'        => 'object',
					'properties'  => array(
						'type'       => array(
							'type' => 'string',
						),
						'implements' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'href' => array(
										'type'   => 'string',
										'format' => 'uri',
									),
									'name' => array(
										'type' => 'string',
									),
								),
							),
						),
					),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}

	/**
	 * Validates the user_id parameter.
	 *
	 * @param mixed $user_id The user_id parameter.
	 * @return bool|\WP_Error True if the user_id is valid, WP_Error otherwise.
	 */
	public function validate_user_id( $user_id ) {
		$user = Actor_Collection::get_by_id( $user_id );
		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		return true;
	}
}
