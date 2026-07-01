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
	 * @since unreleased
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
			'icon'                      => self::get_icon(),
			'published'                 => self::get_published(),
			'inbox'                     => get_rest_url_by_path( 'inbox' ),
			'outbox'                    => get_rest_url_by_path( 'application/outbox' ),
			'manuallyApprovesFollowers' => true,
			'discoverable'              => false,
			'indexable'                 => false,
			'invisible'                 => true,
			'webfinger'                 => Application::USERNAME . '@' . home_host(),
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
		 * Application actor. The object argument is null because the Application is
		 * served without a model instance.
		 */
		$class = 'application';

		/** This filter is documented in includes/activity/class-generic-object.php */
		$json = \apply_filters( 'activitypub_activity_object_array', $json, $class, $id, null );

		/** This filter is documented in includes/activity/class-generic-object.php */
		$json = \apply_filters( "activitypub_activity_{$class}_object_array", $json, $id, null );

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
	 * @since unreleased
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
	 * Returns the icon for the Application.
	 *
	 * @return string[] The icon array with 'type' and 'url'.
	 */
	private static function get_icon() {
		// Try site icon first.
		$icon_id = \get_option( 'site_icon' );

		// Try custom logo second.
		if ( ! $icon_id ) {
			$icon_id = \get_theme_mod( 'custom_logo' );
		}

		$icon_url = false;

		if ( $icon_id ) {
			$icon = \wp_get_attachment_image_src( $icon_id, 'full' );
			if ( $icon ) {
				$icon_url = $icon[0];
			}
		}

		if ( ! $icon_url ) {
			// Fallback to default icon.
			$icon_url = \plugins_url( '/assets/img/wp-logo.png', ACTIVITYPUB_PLUGIN_FILE );
		}

		return array(
			'type' => 'Image',
			'url'  => \esc_url( $icon_url ),
		);
	}

	/**
	 * Returns the published date.
	 *
	 * @return string The published date in RFC3339 format.
	 */
	private static function get_published() {
		$first_post = new \WP_Query(
			array(
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => 1,
			)
		);

		$time = false;

		if ( ! empty( $first_post->posts[0] ) ) {
			$time = \strtotime( $first_post->posts[0]->post_date_gmt );
		}

		if ( false === $time ) {
			$time = \time();
		}

		return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $time );
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
