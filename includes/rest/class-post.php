<?php
/**
 * ActivityPub Post REST Endpoints
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use WP_REST_Server;
use WP_REST_Response;
use WP_Error;
use Activitypub\Comment;

/**
 * Class Post
 *
 * @package Activitypub\Rest
 */
class Post {

	/**
	 * Initialize the class and register routes.
	 */
	public static function init() {
		self::register_routes();
	}

	/**
	 * Register routes.
	 */
	public static function register_routes() {
		register_rest_route(
			ACTIVITYPUB_REST_NAMESPACE,
			'/posts/(?P<id>\d+)/reactions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( static::class, 'get_reactions' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);
	}

	/**
	 * Get reactions for a post.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public static function get_reactions( $request ) {
		$post_id = $request->get_param( 'id' );
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new WP_Error( 'post_not_found', 'Post not found', array( 'status' => 404 ) );
		}

		$reactions = array();

		foreach ( Comment::get_comment_types() as $type_object ) {
			$comments = get_comments(
				array(
					'post_id' => $post_id,
					'type'    => $type_object['type'],
					'status'  => 'approve',
				)
			);

			if ( empty( $comments ) ) {
				continue;
			}

			$count = count( $comments );
			// phpcs:disable WordPress.WP.I18n
			$label = sprintf(
				_n(
					$type_object['count_single'],
					$type_object['count_plural'],
					$count,
					'activitypub'
				),
				number_format_i18n( $count )
			);
			// phpcs:enable WordPress.WP.I18n

			$reactions[ $type_object['collection'] ] = array(
				'label' => $label,
				'items' => array_map(
					function ( $comment ) {
						return array(
							'name'   => $comment->comment_author,
							'url'    => $comment->comment_author_url,
							'avatar' => get_comment_meta( $comment->comment_ID, 'avatar_url', true ),
						);
					},
					$comments
				),
			);
		}

		return new WP_REST_Response( $reactions );
	}
}
