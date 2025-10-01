<?php
/**
 * NodeInfo controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\get_masked_wp_version;
use function Activitypub\get_rest_url_by_path;

/**
 * ActivityPub NodeInfo Controller.
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
			'/' . $this->rest_base . '/(?P<version>\d\.\d)',
			array(
				'args' => array(
					'version' => array(
						'description' => 'The version of the NodeInfo schema.',
						'type'        => 'string',
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
	public function get_items( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		$response = array(
			'links' => array(

				/*
				 * Needs http protocol for spec compliance.
				 * @ticket https://github.com/Automattic/wordpress-activitypub/pull/1275
				 */
				array(
					'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.0',
					'href' => get_rest_url_by_path( '/nodeinfo/2.0' ),
				),
				array(
					'rel'  => 'https://nodeinfo.diaspora.software/ns/schema/2.0',
					'href' => get_rest_url_by_path( '/nodeinfo/2.0' ),
				),
				array(
					'rel'  => 'http://nodeinfo.diaspora.software/ns/schema/2.1',
					'href' => get_rest_url_by_path( '/nodeinfo/2.1' ),
				),
				array(
					'rel'  => 'https://nodeinfo.diaspora.software/ns/schema/2.1',
					'href' => get_rest_url_by_path( '/nodeinfo/2.1' ),
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
			case '2.1':
				$response = $this->get_version_2_X( $version );
				break;

			default:
				$response = new \WP_Error( 'activitypub_rest_nodeinfo_invalid_version', 'Unsupported NodeInfo version.', array( 'status' => 405 ) );
				break;
		}

		return \rest_ensure_response( $response );
	}

	/**
	 * Get the NodeInfo 2.0 data.
	 *
	 * @param string $version The NodeInfo version.
	 *
	 * @return array The NodeInfo data.
	 */
	public function get_version_2_X( $version ) {
		$posts    = \wp_count_posts();
		$comments = \wp_count_comments();

		$nodeinfo = array(
			'version'           => $version,
			'software'          => array(
				'name'    => 'wordpress',
				'version' => get_masked_wp_version(),
			),
			'openRegistrations' => (bool) get_option( 'users_can_register' ),
			'usage'             => array(
				'localPosts'    => (int) $posts->publish,
				'localComments' => $comments->approved,
			),
			'metadata'          => array(
				'nodeName'        => \get_bloginfo( 'name' ),
				'nodeDescription' => \get_bloginfo( 'description' ),
				'nodeIcon'        => \get_site_icon_url(),
			),
		);

		/**
		 * Filter the NodeInfo data.
		 *
		 * @param array  $nodeinfo The NodeInfo data.
		 * @param string $version  The NodeInfo version.
		 */
		return \apply_filters( 'nodeinfo_data', $nodeinfo, $version );
	}
}
