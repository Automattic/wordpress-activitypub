<?php
/**
 * Statistics_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use Activitypub\Collection\Actors;
use Activitypub\Statistics;

/**
 * ActivityPub Statistics_Controller class.
 *
 * Provides REST endpoints for ActivityPub statistics.
 */
class Statistics_Controller extends \WP_REST_Controller {

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
	protected $rest_base = 'stats';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<user_id>[\d]+)',
			array(
				array(
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_item' ),
					'permission_callback' => array( $this, 'get_item_permissions_check' ),
					'args'                => array(
						'user_id' => array(
							'description' => 'The user ID to get stats for.',
							'type'        => 'integer',
							'required'    => true,
						),
					),
				),
			)
		);
	}

	/**
	 * Checks if a given request has access to get stats.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return true|\WP_Error True if the request has access, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$user_id = $request->get_param( 'user_id' );

		// Check if user can access stats for this actor.
		if ( Actors::BLOG_USER_ID === $user_id ) {
			if ( ! \current_user_can( 'manage_options' ) ) {
				return new \WP_Error(
					'rest_forbidden',
					\__( 'You do not have permission to view blog stats.', 'activitypub' ),
					array( 'status' => 403 )
				);
			}
		} elseif ( \get_current_user_id() !== $user_id ) {
			return new \WP_Error(
				'rest_forbidden',
				\__( 'You do not have permission to view this user\'s stats.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Retrieves statistics for a user.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or WP_Error object.
	 */
	public function get_item( $request ) {
		$user_id = $request->get_param( 'user_id' );

		$stats         = Statistics::get_current_stats( $user_id, 'month' );
		$comparison    = Statistics::get_year_comparison( $user_id );
		$monthly_data  = Statistics::get_yearly_monthly_breakdown( $user_id );
		$comment_types = Statistics::get_comment_types_for_stats();

		$response = array(
			'stats'         => array(
				'posts_count'       => $stats['posts_count'],
				'followers_total'   => $stats['followers_total'],
				'top_posts'         => $stats['top_posts'],
				'top_multiplicator' => $stats['top_multiplicator'],
			),
			'comparison'    => $comparison,
			'monthly'       => \array_values( $monthly_data ),
			'comment_types' => $comment_types,
		);

		return \rest_ensure_response( $response );
	}
}
