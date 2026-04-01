<?php
/**
 * Stats_Image_Controller file.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Rest;

use Activitypub\Cache\Stats_Image;

/**
 * REST controller that serves stats share images.
 *
 * Provides two endpoints:
 * - /stats/image/{user_id}/{year}     — serves the image binary
 * - /stats/image-url/{user_id}/{year} — returns the image URL as JSON
 */
class Stats_Image_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'stats/image';

	/**
	 * Common route args for user_id and year.
	 *
	 * @return array The route args.
	 */
	private function get_common_args() {
		return array(
			'user_id' => array(
				'description'       => \__( 'The user ID to generate the stats image for.', 'activitypub' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'year'    => array(
				'description'       => \__( 'The year to display stats for.', 'activitypub' ),
				'type'              => 'integer',
				'required'          => true,
				'sanitize_callback' => 'absint',
			),
			'bg'      => array(
				'description'       => \__( 'Background color as hex (without #).', 'activitypub' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color_no_hash',
			),
			'fg'      => array(
				'description'       => \__( 'Text color as hex (without #).', 'activitypub' ),
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_hex_color_no_hash',
			),
		);
	}

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$route_pattern = '/(?P<user_id>[\d]+)/(?P<year>[\d]{4})';

		// Serve the image binary.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . $route_pattern,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_common_args(),
				),
			)
		);

		// Return the image URL as JSON.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '-url' . $route_pattern,
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_url' ),
					'permission_callback' => '__return_true',
					'args'                => $this->get_common_args(),
				),
			)
		);
	}

	/**
	 * Serve the stats image binary.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return void|\WP_Error Streams image and exits, or returns error.
	 */
	public function get_item( $request ) {
		return Stats_Image::serve(
			(int) $request->get_param( 'user_id' ),
			(int) $request->get_param( 'year' ),
			$this->get_color_overrides( $request )
		);
	}

	/**
	 * Return the resolved image URL as JSON.
	 *
	 * Returns the cached file URL if available, otherwise the REST
	 * endpoint URL. Filtered via `activitypub_stats_image_url` so
	 * it can be routed through a CDN or image proxy like Photon.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error JSON response with the URL.
	 */
	public function get_url( $request ) {
		$url = Stats_Image::get_url(
			(int) $request->get_param( 'user_id' ),
			(int) $request->get_param( 'year' ),
			$this->get_color_overrides( $request )
		);

		if ( \is_wp_error( $url ) ) {
			return $url;
		}

		return \rest_ensure_response( array( 'url' => $url ) );
	}

	/**
	 * Extract color overrides from the request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return array The color overrides.
	 */
	private function get_color_overrides( $request ) {
		return array(
			'bg' => $request->get_param( 'bg' ),
			'fg' => $request->get_param( 'fg' ),
		);
	}
}
