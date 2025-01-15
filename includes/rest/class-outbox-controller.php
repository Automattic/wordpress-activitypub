<?php
/**
 * Outbox Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Transformer\Factory;

/**
 * ActivityPub Outbox Controller.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox_Controller extends \WP_REST_Controller {
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
	protected $rest_base = '(?:users|actors)/(?P<user_id>[\w\-\.]+)/outbox';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'args' => array(
					'user_id' => array(
						'description' => 'The ID of the user or actor.',
						'type'        => 'string',
					),
				),
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_items' ),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
					'args'                => array(
						'page' => array(
							'description' => 'Current page of the collection.',
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
					),
				),
			)
		);
	}

	/**
	 * Retrieves a collection of outbox items.
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function get_items( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( \is_wp_error( $user ) ) {
			return $user;
		}

		$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );
		$page       = $request->get_param( 'page' );

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_outbox_pre' );

		$response = array(
			'@context'   => array( 'https://www.w3.org/ns/activitystreams' ),
			'id'         => \get_rest_url( null, \sprintf( 'actors/%d/outbox', $user_id ) ),
			'generator'  => 'https://wordpress.org/?v=' . \get_bloginfo( 'version' ),
			'actor'      => $user->get_id(),
			'type'       => 'OrderedCollectionPage',
			'partOf'     => \get_rest_url( null, \sprintf( 'actors/%d/outbox', $user_id ) ),
			'totalItems' => 0,
		);

		if ( $user_id > 0 ) {
			$count_posts            = \count_user_posts( $user_id, $post_types, true );
			$response['totalItems'] = \intval( $count_posts );
		} else {
			foreach ( $post_types as $post_type ) {
				$count_posts             = \wp_count_posts( $post_type );
				$response['totalItems'] += \intval( $count_posts->publish );
			}
		}

		$response['first'] = \add_query_arg( 'page', 1, $response['partOf'] );
		$response['last']  = \add_query_arg( 'page', \ceil( $response['totalItems'] / 10 ), $response['partOf'] );

		if ( $page && ( ( \ceil( $response['totalItems'] / 10 ) ) > $page ) ) {
			$response['next'] = \add_query_arg( 'page', $page + 1, $response['partOf'] );
		}

		if ( $page && ( $page > 1 ) ) {
			$response['prev'] = \add_query_arg( 'page', $page - 1, $response['partOf'] );
		}

		$response['orderedItems'] = array();

		if ( $page ) {
			$posts = \get_posts(
				array(
					'posts_per_page' => 10,
					'author'         => $user_id > 0 ? $user_id : null,
					'paged'          => $page,
					'post_type'      => $post_types,
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'meta_query'     => array(
						'relation' => 'OR',
						array(
							'key'     => 'activitypub_content_visibility',
							'compare' => 'NOT EXISTS',
						),
						array(
							'key'     => 'activitypub_content_visibility',
							'value'   => ACTIVITYPUB_CONTENT_VISIBILITY_LOCAL,
							'compare' => '!=',
						),
					),
				)
			);

			foreach ( $posts as $post ) {
				$transformer = Factory::get_transformer( $post );

				if ( \is_wp_error( $transformer ) ) {
					continue;
				}

				$post     = $transformer->to_object();
				$activity = new Activity();
				$activity->set_type( 'Create' );
				$activity->set_object( $post );
				$response['orderedItems'][] = $activity->to_array( false );
			}
		}

		/**
		 * Filter the ActivityPub outbox array.
		 *
		 * @param array $response The ActivityPub outbox array.
		 */
		$response = \apply_filters( 'activitypub_rest_outbox_array', $response );

		/**
		 * Action triggered after the ActivityPub profile has been created and sent to the client.
		 */
		\do_action( 'activitypub_outbox_post' );

		$response = \rest_ensure_response( $response );
		$response->header( 'Content-Type', 'application/activity+json; charset=' . \get_option( 'blog_charset' ) );

		return $response;
	}
}
