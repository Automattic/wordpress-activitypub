<?php
/**
 * NodeInfo REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\get_total_users;
use function Activitypub\get_active_users;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub NodeInfo REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://nodeinfo.diaspora.software/
 */
class Nodeinfo_Controller extends \WP_REST_Controller {
	/**
	 * The namespace of this controller's route.
	 *
	 * @var string
	 */
	protected $namespace = ACTIVITYPUB_REST_NAMESPACE;

	/**
	 * The REST base for this controller's route.
	 *
	 * @var string
	 */
	protected $rest_base = 'nodeinfo';

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
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<version>[\d\.\d]+)',
			array(
				'args' => array(
					'version' => array(
						'description' => 'The version of the NodeInfo schema.',
						'type'        => 'string',
						'enum'        => array( '2.0' ),
						'required'    => true,
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
				),
			)
		);
	}

	/**
	 * Retrieves the NodeInfo discovery profile.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response Response object.
	 */
	public function get_items( $request ) {
		$response = array(
			'links' => array(
				array(
					'rel'  => 'https://nodeinfo.diaspora.software/ns/schema/2.0',
					'href' => get_rest_url_by_path( '/nodeinfo' ),
				),
				array(
					'rel'  => 'https://www.w3.org/ns/activitystreams#Application',
					'href' => get_rest_url_by_path( 'application' ),
				),
			),
		);

		return \rest_ensure_response( $response );
	}

	/**
	 * Retrieves the NodeInfo profile.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_item( $request ) {
		$version = $request->get_param( 'version' );

		/**
		 * Fires before the NodeInfo data is created and sent to the client.
		 *
		 * @param string $version The NodeInfo version.
		 */
		\do_action( 'activitypub_rest_nodeinfo_pre', $version );

		switch ( $version ) {
			case '2.0':
			default:
				$response = $this->get_version_2_0();
				break;
		}

		return \rest_ensure_response( $response );
	}

	/**
	 * Get the NodeInfo 2.0 data.
	 *
	 * @return array
	 */
	public function get_version_2_0() {
		$posts    = \wp_count_posts();
		$comments = \wp_count_comments();

		return array(
			'version'           => '2.0',
			'software'          => array(
				'name'    => 'wordpress',
				'version' => \get_bloginfo( 'version' ),
			),
			'protocols'         => array( 'activitypub' ),
			'services'          => array(
				'inbound'  => array(),
				'outbound' => array(),
			),
			'openRegistrations' => (bool) get_option( 'users_can_register' ),
			'usage'             => array(
				'users'         => array(
					'total'          => get_total_users(),
					'activeHalfyear' => get_active_users( '6 month ago' ),
					'activeMonth'    => get_active_users( '1 month ago' ),
				),
				'localPosts'    => $posts->publish,
				'localComments' => $comments->approved,
			),
			'metadata'          => array(
				'nodeName'        => \get_bloginfo( 'name' ),
				'nodeDescription' => \get_bloginfo( 'description' ),
				'nodeIcon'        => \get_site_icon_url(),
			),
		);
	}

	/**
	 * Get the schema for the NodeInfo response.
	 *
	 * @return array
	 */
	public function get_item_schema() {
		return array(
			'$schema'     => 'http://json-schema.org/draft-04/schema#',
			'title'       => 'nodeinfo',
			'type'        => 'object',
			'properties'  => array(
				'version'           => array(
					'description' => 'The schema version, must be 2.0.',
					'type'        => 'string',
					'enum'        => array( '2.0' ),
					'required'    => true,
				),
				'software'          => array(
					'description' => 'Metadata about server software in use.',
					'type'        => 'object',
					'properties'  => array(
						'name'    => array(
							'description' => 'The canonical name of this server software.',
							'type'        => 'string',
							'required'    => true,
						),
						'version' => array(
							'description' => 'The version of this server software.',
							'type'        => 'string',
							'required'    => true,
						),
					),
					'required'    => true,
				),
				'protocols'         => array(
					'description' => 'The protocols supported on this server.',
					'type'        => 'array',
					'items'       => array(
						'type' => 'string',
					),
					'required'    => true,
				),
				'services'          => array(
					'description' => 'The third party sites this server can connect to via their application API.',
					'type'        => 'object',
					'properties'  => array(
						'inbound'  => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
						'outbound' => array(
							'type'  => 'array',
							'items' => array(
								'type' => 'string',
							),
						),
					),
					'required'    => true,
				),
				'openRegistrations' => array(
					'description' => 'Whether this server allows open registration.',
					'type'        => 'boolean',
					'required'    => true,
				),
				'usage'             => array(
					'description' => 'Usage statistics for this server.',
					'type'        => 'object',
					'properties'  => array(
						'users'         => array(
							'type'       => 'object',
							'properties' => array(
								'total'          => array(
									'description' => 'The total amount of on this server registered users.',
									'type'        => 'integer',
								),
								'activeHalfyear' => array(
									'description' => 'The amount of users that signed in at least once in the last 6 months.',
									'type'        => 'integer',
								),
								'activeMonth'    => array(
									'description' => 'The amount of users that signed in at least once in the last month.',
									'type'        => 'integer',
								),
							),
						),
						'localPosts'    => array(
							'description' => 'The amount of posts that were made by users that are registered on this server.',
							'type'        => 'integer',
						),
						'localComments' => array(
							'description' => 'The amount of comments that were made by users that are registered on this server.',
							'type'        => 'integer',
						),
					),
					'required'    => true,
				),
				'metadata'          => array(
					'description' => 'Free form key value pairs for software specific values.',
					'type'        => 'object',
					'properties'  => array(
						'nodeName'        => array(
							'type' => 'string',
						),
						'nodeDescription' => array(
							'type' => 'string',
						),
						'nodeIcon'        => array(
							'type' => 'string',
						),
					),
				),
			),
		);
	}
}
