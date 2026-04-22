<?php
/**
 * ActivityPub Post REST Endpoints
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Replies;
use Activitypub\Comment;
use Activitypub\Sanitize;
use Activitypub\Webfinger;

use function Activitypub\get_post_id;
use function Activitypub\get_rest_url_by_path;

/**
 * Class Post_Controller
 *
 * @package Activitypub\Rest
 */
class Post_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'posts/(?P<id>[\d]+)';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/reactions',
			array(
				'args' => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'validate_callback' => 'Activitypub\is_post_publicly_queryable',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_reactions' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/context',
			array(
				'args' => array(
					'id' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'validate_callback' => 'Activitypub\is_post_publicly_queryable',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_context' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/remote-intent',
			array(
				'args' => array(
					'id' => array(
						'description'       => 'Unique identifier for the post.',
						'type'              => 'integer',
						'minimum'           => 1,
						'required'          => true,
						'validate_callback' => 'Activitypub\is_post_publicly_queryable',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_remote_intent_template' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'resource' => array(
							'description'       => 'The Fediverse profile handle or URL.',
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => array( Sanitize::class, 'webfinger' ),
						),
						'intent'   => array(
							'description'       => 'The intent type.',
							'type'              => 'string',
							'default'           => 'like',
							'enum'              => array( 'like', 'announce', 'create' ),
							'sanitize_callback' => 'sanitize_text_field',
						),
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
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_reactions( $request ) {
		$post_id = $request->get_param( 'id' );

		$reactions = array();

		foreach ( Comment::get_comment_types() as $type_object ) {
			$comments = \get_comments(
				array(
					'post_id' => $post_id,
					'type'    => $type_object['type'],
					'status'  => 'approve',
					'parent'  => 0,
				)
			);

			if ( empty( $comments ) ) {
				continue;
			}

			$count = \count( $comments );
			// phpcs:disable WordPress.WP.I18n
			$label = \sprintf(
				\_n(
					$type_object['count_single'],
					$type_object['count_plural'],
					$count,
					'activitypub'
				),
				\number_format_i18n( $count )
			);
			// phpcs:enable WordPress.WP.I18n

			$reactions[ $type_object['collection'] ] = array(
				'label' => $label,
				'items' => \array_map(
					static function ( $comment ) {
						/*
						 * Decode entities first so a stored pseudo-tag like
						 * `&lt;img&gt;` becomes a real `<img>` for the next
						 * step to remove, then strip any tags so the JSON
						 * response contains only plain text. `esc_url()`
						 * rejects `javascript:` and other unsafe schemes.
						 */
						return array(
							'name'   => \wp_strip_all_tags( \html_entity_decode( $comment->comment_author, ENT_QUOTES ) ),
							'url'    => \esc_url( $comment->comment_author_url ),
							'avatar' => \esc_url( \get_avatar_url( $comment ) ),
						);
					},
					$comments
				),
			);
		}

		return new \WP_REST_Response( $reactions );
	}

	/**
	 * Get the context for a post.
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_context( $request ) {
		$post_id    = $request->get_param( 'id' );
		$collection = Replies::get_context_collection( $post_id );

		if ( false === $collection ) {
			return new \WP_Error( 'activitypub_post_not_found', \__( 'Post not found', 'activitypub' ), array( 'status' => 404 ) );
		}

		$response = array_merge(
			array(
				'@context' => Base_Object::JSON_LD_CONTEXT,
				'id'       => get_rest_url_by_path( \sprintf( 'posts/%d/context', $post_id ) ),
			),
			$collection
		);

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}

	/**
	 * Get the remote intent template for a post.
	 *
	 * @since 8.0.0
	 *
	 * @param \WP_REST_Request $request The request.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_remote_intent_template( $request ) {
		$post_id  = $request->get_param( 'id' );
		$resource = $request->get_param( 'resource' );
		$intent   = $request->get_param( 'intent' );
		$post     = \get_post( $post_id );

		$template = Webfinger::get_intent_endpoint( $resource, $intent, true );

		if ( \is_wp_error( $template ) ) {
			return $template;
		}

		$id = get_post_id( $post_id );

		$url = \str_replace(
			array(
				'{object}',
				'{uri}',
				'{inReplyTo}',
				'{name}',
				'{target}',
			),
			array(
				\rawurlencode( $id ),
				\rawurlencode( $id ),
				\rawurlencode( $id ),
				\rawurlencode( $post->post_title ),
				\rawurlencode( $resource ),
			),
			$template
		);

		// Remove any other GET-Params with placeholders to avoid confusion.
		$url = \preg_replace( '/([&?][^=]+=\{[^}]+\})/', '', $url );

		return \rest_ensure_response(
			array(
				'url'      => $url,
				'template' => $template,
			)
		);
	}
}
