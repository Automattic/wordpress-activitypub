<?php
/**
 * ActivityPub Interaction REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Response;
use Activitypub\Http;

/**
 * Interaction class.
 */
class Interaction {
	/**
	 * Initialize the class, registering WordPress hooks.
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

		\register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/reactions/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( self::class, 'get_reactions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required'          => true,
						'validate_callback' => function ( $val ) {
							return is_numeric( $val );
						},
					),
				),
			)
		);
	}

	/**
	 * Handle GET request.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return WP_REST_Response Redirect to the editor or die.
	 */
	public static function get( $request ) {
		$uri          = $request->get_param( 'uri' );
		$redirect_url = null;
		$object       = Http::get_remote_object( $uri );

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
				$redirect_url = \apply_filters( 'activitypub_interactions_follow_url', $redirect_url, $uri, $object );
				break;
			default:
				$redirect_url = \admin_url( 'post-new.php?in_reply_to=' . $uri );
				$redirect_url = \apply_filters( 'activitypub_interactions_reply_url', $redirect_url, $uri, $object );
		}

		/**
		 * Filter the redirect URL.
		 *
		 * @param string $redirect_url The URL to redirect to.
		 * @param string $uri          The URI of the object.
		 * @param array  $object       The object.
		 */
		$redirect_url = \apply_filters( 'activitypub_interactions_url', $redirect_url, $uri, $object );

		// Check if hook is implemented.
		if ( ! $redirect_url ) {
			\wp_die(
				esc_html__(
					'This Interaction type is not supported yet!',
					'activitypub'
				),
				400
			);
		}

		return new WP_REST_Response(
			null,
			302,
			array(
				'Location' => \esc_url( $redirect_url ),
			)
		);
	}

	/**
	 * Get reactions (likes and reposts) for a post
	 *
	 * @param WP_REST_Request $request The request object.
	 * @return WP_REST_Response|WP_Error Response object or WP_Error.
	 */
	public static function get_reactions( $request ) {
		$post_id = $request->get_param( 'id' );
		$post    = get_post( $post_id );
		$likes   = array();
		$reposts = array();

		if ( ! $post ) {
			return new \WP_Error(
				'activitypub_post_not_found',
				__( 'Post not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$reactions = get_comments(
			array(
				'post_id'  => $post_id,
				'type__in' => array( 'like', 'repost' ),
				'status'   => 'approve',
			)
		);

		$format_reaction = function ( $comment ) {
			return array(
				'avatar' => get_comment_meta( $comment->comment_ID, 'avatar_url', true ),
				'url'    => $comment->comment_author_url,
				'name'   => $comment->comment_author,
			);
		};

		foreach ( $reactions as $reaction ) {
			switch ( $reaction->comment_type ) {
				case 'like':
					$likes[] = $format_reaction( $reaction );
					break;
				case 'repost':
					$reposts[] = $format_reaction( $reaction );
					break;
			}
		}

		return new WP_REST_Response(
			array(
				'likes'   => $likes,
				'reposts' => $reposts,
			),
			200
		);
	}
}
