<?php
/**
 * Outbox REST-Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use stdClass;
use WP_REST_Server;
use WP_REST_Response;
use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Transformer\Factory;

use function Activitypub\get_context;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_masked_wp_version;

/**
 * ActivityPub Outbox REST-Class.
 *
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#outbox
 */
class Outbox {
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
			'/(users|actors)/(?P<user_id>[\w\-\.]+)/outbox',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( self::class, 'user_outbox_get' ),
					'args'                => self::request_parameters(),
					'permission_callback' => array( 'Activitypub\Rest\Server', 'verify_signature' ),
				),
			)
		);
	}

	/**
	 * Renders the user-outbox
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return WP_REST_Response|\WP_Error The response object or WP_Error.
	 */
	public static function user_outbox_get( $request ) {
		$user_id = $request->get_param( 'user_id' );
		$user    = Actors::get_by_various( $user_id );

		if ( is_wp_error( $user ) ) {
			return $user;
		}

		$post_types = \get_option( 'activitypub_support_post_types', array( 'post' ) );

		$page = $request->get_param( 'page', 1 );

		/**
		 * Action triggered prior to the ActivityPub profile being created and sent to the client.
		 */
		\do_action( 'activitypub_rest_outbox_pre' );

		$json = new stdClass();

		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$json->{'@context'} = get_context();
		$json->id           = get_rest_url_by_path( sprintf( 'actors/%d/outbox', $user_id ) );
		$json->generator    = 'http://wordpress.org/?v=' . get_masked_wp_version();
		$json->actor        = $user->get_id();
		$json->type         = 'OrderedCollectionPage';
		$json->partOf       = get_rest_url_by_path( sprintf( 'actors/%d/outbox', $user_id ) );
		$json->totalItems   = 0;

		if ( $user_id > 0 ) {
			$count_posts      = \count_user_posts( $user_id, $post_types, true );
			$json->totalItems = \intval( $count_posts );
		} else {
			foreach ( $post_types as $post_type ) {
				$count_posts       = \wp_count_posts( $post_type );
				$json->totalItems += \intval( $count_posts->publish );
			}
		}

		$json->first = \add_query_arg( 'page', 1, $json->partOf );
		$json->last  = \add_query_arg( 'page', \ceil( $json->totalItems / 10 ), $json->partOf );

		if ( $page && ( ( \ceil( $json->totalItems / 10 ) ) > $page ) ) {
			$json->next = \add_query_arg( 'page', $page + 1, $json->partOf );
		}

		if ( $page && ( $page > 1 ) ) {
			$json->prev = \add_query_arg( 'page', $page - 1, $json->partOf );
		}
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

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
				$json->orderedItems[] = $activity->to_array( false ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			}
		}

		/**
		 * Filter the ActivityPub outbox array.
		 *
		 * @param array $json The ActivityPub outbox array.
		 */
		$json = \apply_filters( 'activitypub_rest_outbox_array', $json );

		/**
		 * Action triggered after the ActivityPub profile has been created and sent to the client
		 */
		\do_action( 'activitypub_outbox_post' );

		$rest_response = new WP_REST_Response( $json, 200 );
		$rest_response->header( 'Content-Type', 'application/activity+json; charset=' . get_option( 'blog_charset' ) );

		return $rest_response;
	}

	/**
	 * The supported parameters.
	 *
	 * @return array List of parameters.
	 */
	public static function request_parameters() {
		$params = array();

		$params['page'] = array(
			'type'    => 'integer',
			'default' => 1,
		);

		return $params;
	}
}
