<?php
namespace Activitypub\Rest;

use WP_Error;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use Activitypub\Comment as Comment_Utils;
use Activitypub\Webfinger as Webfinger_Utils;

/**
 * ActivityPub Followers REST-Class
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Comment {
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
			'/comments/(?P<comment_id>\d+)/remote-reply',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'remote_reply_get' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'resource' => array(
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Endpoint for remote follow UI/Block
	 *
	 * @param WP_REST_Request $request The request object.
	 *
	 * @return void|string The URL to the remote follow page
	 */
	public static function remote_reply_get( WP_REST_Request $request ) {
		$resource   = $request->get_param( 'resource' );
		$comment_id = $request->get_param( 'comment_id' );

		$comment = get_comment( $comment_id );

		if ( ! $comment ) {
			return new WP_Error( 'activitypub_comment_not_found', __( 'Comment not found', 'activitypub' ), array( 'status' => 404 ) );
		}

		$is_local = Comment_Utils::is_local( $comment );

		if ( $is_local ) {
			return new WP_Error( 'activitypub_local_only_comment', __( 'Comment is local only', 'activitypub' ), array( 'status' => 403 ) );
		}

		$template = Webfinger_Utils::get_remote_follow_endpoint( $resource );

		if ( is_wp_error( $template ) ) {
			return $template;
		}

		$comment_meta = \get_comment_meta( $comment_id );

		if ( ! empty( $comment_meta['source_id'][0] ) ) {
			$resource = $comment_meta['source_id'][0];
		} elseif ( ! empty( $comment_meta['source_url'][0] ) ) {
			$resource = $comment_meta['source_url'][0];
		} else {
			$resource = Comment_Utils::generate_id( $comment );
		}

		$url = str_replace( '{uri}', $resource, $template );

		return new WP_REST_Response(
			array( 'url' => $url, 'template' => $template ),
			200
		);
	}
}
