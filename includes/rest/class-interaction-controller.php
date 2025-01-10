<?php
/**
 * ActivityPub Interaction Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * Interaction Controller.
 */
class Interaction_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'interactions';

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
					'args'                => array(
						'uri' => array(
							'description' => 'The URI of the object to interact with.',
							'type'        => 'string',
							'format'      => 'uri',
							'required'    => true,
						),
					),
					'schema'              => array( $this, 'get_item_schema' ),
				),
			)
		);
	}

	/**
	 * Retrieves the interaction URL for a given URI.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_item( $request ) {
		$uri          = $request->get_param( 'uri' );
		$redirect_url = null;
		$object       = \Activitypub\Http::get_remote_object( $uri );

		if ( \is_wp_error( $object ) || ! isset( $object['type'] ) ) {
			return new \WP_Error( 'activitypub_invalid_object', \esc_html__( 'The URL is not supported!', 'activitypub' ), array( 'status' => 400 ) );
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
				/**
				 * Filters the URL used for following an ActivityPub actor.
				 *
				 * @param string $redirect_url The URL to redirect to.
				 * @param string $uri          The URI of the actor to follow.
				 * @param array  $object       The full actor object data.
				 */
				$redirect_url = \apply_filters( 'activitypub_interactions_follow_url', $redirect_url, $uri, $object );
				break;
			default:
				$redirect_url = \admin_url( 'post-new.php?in_reply_to=' . $uri );
				/**
				 * Filters the URL used for replying to an ActivityPub object.
				 *
				 * By default, this redirects to the WordPress post editor with the in_reply_to parameter set.
				 *
				 * @param string $redirect_url The URL to redirect to.
				 * @param string $uri          The URI of the object to reply to.
				 * @param array  $object       The full object data being replied to.
				 */
				$redirect_url = \apply_filters( 'activitypub_interactions_reply_url', $redirect_url, $uri, $object );
		}

		/**
		 * Filters the redirect URL.
		 *
		 * This filter runs after the type-specific filters and allows for final modifications
		 * to the interaction URL regardless of the object type.
		 *
		 * @param string $redirect_url The URL to redirect to.
		 * @param string $uri          The URI of the object.
		 * @param array  $object       The object being interacted with.
		 */
		$redirect_url = \apply_filters( 'activitypub_interactions_url', $redirect_url, $uri, $object );

		// Check if hook is implemented.
		if ( ! $redirect_url ) {
			return new \WP_Error( 'activitypub_interaction_not_supported', \esc_html__( 'This Interaction type is not supported yet!', 'activitypub' ), array( 'status' => 400 ) );
		}

		return new \WP_REST_Response( null, 302, array( 'Location' => \esc_url( $redirect_url ) ) );
	}
}
