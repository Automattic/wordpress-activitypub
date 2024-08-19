<?php
namespace Activitypub\Rest;

use WP_REST_Response;
use Activitypub\Http;

class Interaction {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		self::register_routes();
	}

	/**
	 * Register routes
	 */
	public static function register_routes() {
		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/interactions',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'get' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'uri' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'esc_url',
						),
					),
				),
			)
		);
	}

	/**
	 * Handle GET request
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return wp_die|WP_REST_Response Redirect to the editor or die
	 */
	public static function get( $request ) {
		$uri    = $request->get_param( 'uri' );
		$object = Http::get_remote_object( $uri );

		if (
			\is_wp_error( $object ) ||
			! isset( $object['type'] )
		) {
			\wp_die(
				\esc_html__(
					'The URL is not supported!',
					'activitypub'
				),
				400
			);
		}

		if ( ! empty( $object['url'] ) ) {
			$uri = \esc_url( $object['url'] );
		}

		switch ( $object['type'] ) {
			case 'Group':
			case 'Person':
			case 'Service':
			case 'Application':
			case 'Organization':
				\do_action( 'activitypub_interactions_follow', $uri, $object );
				// check if hook is implemented
				if ( ! has_action( 'activitypub_interactions_follow' ) ) {
					\wp_die(
						esc_html__(
							'You can\'t follow Fediverse Users yet.',
							'activitypub'
						),
						400
					);
				}
				break;
			default:
				\do_action( 'activitypub_interactions_reply', $uri, $object );
				return new WP_REST_Response(
					null,
					302,
					array(
						'Location' => \esc_url( \admin_url( 'post-new.php?in_reply_to=' . $uri ) ),
					)
				);
		}
	}
}
