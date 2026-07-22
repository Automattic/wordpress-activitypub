<?php
/**
 * Application Controller file.
 *
 * Self-contained controller for the ActivityPub Application actor.
 * The Application is not a real actor in the plugin's internal sense —
 * it cannot be followed, addressed, or interacted with. It exists only as:
 * 1. A JSON-LD document at /wp-json/activitypub/1.0/application
 * 2. A signing identity for outbound HTTP GET requests
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Actor;
use Activitypub\Activity\Generic_Object;
use Activitypub\Application;

use function Activitypub\get_rest_url_by_path;
use function Activitypub\home_host;

/**
 * ActivityPub Application Controller.
 */
class Application_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'application';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/outbox',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_outbox' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Retrieves the application actor profile.
	 *
	 * @since 9.1.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_item( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$id = Application::get_id();

		$json = array(
			'@context'                  => Actor::JSON_LD_CONTEXT,
			'id'                        => $id,
			'type'                      => 'Application',
			'name'                      => Application::USERNAME,
			'preferredUsername'         => Application::USERNAME,
			'summary'                   => sprintf(
				/* translators: %s: Domain of the site */
				\__( 'This is the Application Actor for %s.', 'activitypub' ),
				home_host()
			),
			'url'                       => Application::get_url(),
			'icon'                      => Application::get_icon(),
			'published'                 => Application::get_published(),
			'inbox'                     => get_rest_url_by_path( 'inbox' ),
			'outbox'                    => get_rest_url_by_path( 'application/outbox' ),
			'manuallyApprovesFollowers' => true,
			'discoverable'              => false,
			'indexable'                 => false,
			'invisible'                 => true,
			'webfinger'                 => Application::get_webfinger(),
			'publicKey'                 => array(
				'id'           => Application::get_key_id(),
				'owner'        => $id,
				'publicKeyPem' => Application::get_public_key(),
			),
			'implements'                => array(
				array(
					'href' => 'https://datatracker.ietf.org/doc/html/rfc9421',
					'name' => 'RFC-9421: HTTP Message Signatures',
				),
			),
		);

		/*
		 * Run the same serialization filters the object-based path used, so
		 * integrations that add actor fields via these hooks still apply to the
		 * Application actor. The filters document a Generic_Object argument, so
		 * hydrate one from the array to keep that contract.
		 */
		$class  = 'application';
		$object = Generic_Object::init_from_array( $json );

		/** This filter is documented in includes/activity/class-generic-object.php */
		$json = \apply_filters( 'activitypub_activity_object_array', $json, $class, $id, $object );

		/** This filter is documented in includes/activity/class-generic-object.php */
		$json = \apply_filters( "activitypub_activity_{$class}_object_array", $json, $id, $object );

		$rest_response = new \WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * Returns an empty outbox collection for the Application actor.
	 *
	 * The Application is a signing-only identity and does not publish
	 * activities, so its outbox is always an empty OrderedCollection.
	 *
	 * @since 9.1.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_outbox( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$json = array(
			'@context'     => Actor::JSON_LD_CONTEXT,
			'id'           => get_rest_url_by_path( 'application/outbox' ),
			'type'         => 'OrderedCollection',
			'totalItems'   => 0,
			'orderedItems' => array(),
		);

		$rest_response = new \WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * Retrieves the schema for the application endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'application',
			'type'       => 'object',
			'properties' => array(
				'@context'                  => array(
					'type'  => 'array',
					'items' => array(
						'type' => array( 'string', 'object' ),
					),
				),
				'id'                        => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'type'                      => array(
					'type' => 'string',
					'enum' => array( 'Application' ),
				),
				'name'                      => array(
					'type' => 'string',
				),
				'icon'                      => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array(
							'type' => 'string',
						),
						'url'  => array(
							'type'   => 'string',
							'format' => 'uri',
						),
					),
				),
				'published'                 => array(
					'type'   => 'string',
					'format' => 'date-time',
				),
				'summary'                   => array(
					'type' => 'string',
				),
				'url'                       => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'inbox'                     => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'outbox'                    => array(
					'type'   => 'string',
					'format' => 'uri',
				),
				'streams'                   => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
				'preferredUsername'         => array(
					'type' => 'string',
				),
				'publicKey'                 => array(
					'type'       => 'object',
					'properties' => array(
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
				),
				'manuallyApprovesFollowers' => array(
					'type' => 'boolean',
				),
				'discoverable'              => array(
					'type' => 'boolean',
				),
				'indexable'                 => array(
					'type' => 'boolean',
				),
				'invisible'                 => array(
					'type' => 'boolean',
				),
				'implements'                => array(
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
				'webfinger'                 => array(
					'type' => 'string',
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
