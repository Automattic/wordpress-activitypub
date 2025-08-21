<?php
/**
 * Comments_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Comment;
use Activitypub\Webfinger;

/**
 * Comments_Controller class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#followers
 */
class Comments_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'comments/(?P<comment_id>[-]?\d+)';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/remote-reply',
			array(
				'args'   => array(
					'comment_id' => array(
						'description'       => 'The ID of the comment.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_comment' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'resource' => array(
							'description' => 'The resource to reply to.',
							'type'        => 'string',
							'required'    => true,
						),
					),
				),
				'schema' => array( $this, 'get_item_schema' ),
			)
		);
	}

	/**
	 * Validates if a comment can be replied to remotely.
	 *
	 * @param mixed $param The parameter to validate.
	 *
	 * @return true|\WP_Error True if the comment can be replied to, WP_Error otherwise.
	 */
	public function validate_comment( $param ) {
		$comment = \get_comment( $param );

		if ( ! $comment ) {
			return new \WP_Error( 'activitypub_comment_not_found', \__( 'Comment not found', 'activitypub' ), array( 'status' => 404 ) );
		}

		$is_local = Comment::is_local( $comment );

		if ( $is_local ) {
			return new \WP_Error( 'activitypub_local_only_comment', \__( 'Comment is local only', 'activitypub' ), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Retrieves the remote reply URL for a comment.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error object.
	 */
	public function get_item( $request ) {
		$resource   = $request->get_param( 'resource' );
		$comment_id = $request->get_param( 'comment_id' );

		$template = Webfinger::get_remote_follow_endpoint( $resource );

		if ( \is_wp_error( $template ) ) {
			return $template;
		}

		$resource = Comment::get_source_id( $comment_id );

		if ( ! $resource ) {
			$resource = Comment::generate_id( \get_comment( $comment_id ) );
		}

		$url = \str_replace( '{uri}', $resource, $template );

		return \rest_ensure_response(
			array(
				'url'      => $url,
				'template' => $template,
			)
		);
	}

	/**
	 * Retrieves the schema for the remote reply endpoint.
	 *
	 * @return array Schema data.
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'remote-reply',
			'type'       => 'object',
			'properties' => array(
				'url'      => array(
					'description' => 'The URL to the remote reply page.',
					'type'        => 'string',
					'format'      => 'uri',
					'required'    => true,
				),
				'template' => array(
					'description' => 'The template URL for remote replies.',
					'type'        => 'string',
					'format'      => 'uri',
					'required'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
