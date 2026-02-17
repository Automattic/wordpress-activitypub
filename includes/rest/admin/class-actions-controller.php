<?php
/**
 * Admin Actions REST Controller
 *
 * Handles administrative actions for followers/actors management.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest\Admin;

use Activitypub\Collection\Followers;
use Activitypub\Collection\Following;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Moderation;

use function Activitypub\user_can_activitypub;

/**
 * Admin Actions REST Controller Class.
 */
class Actions_Controller extends \WP_REST_Controller {
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
	protected $rest_base = 'admin/actors';

	/**
	 * Register routes.
	 */
	public function register_routes() {
		// Delete follower relationship.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/unfollow',
			array(
				'args' => array(
					'id' => array(
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_actor_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'unfollow_actor' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'show_in_index'       => false,
				),
			)
		);

		// Block actor.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/block',
			array(
				'args' => array(
					'id' => array(
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_actor_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'block_actor' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'show_in_index'       => false,
					'args'                => array(
						'site_wide' => array(
							'description' => 'Whether to block site-wide (admin only).',
							'type'        => 'boolean',
							'default'     => false,
						),
					),
				),
			)
		);

		// Follow actor.
		\register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/follow',
			array(
				'args' => array(
					'id' => array(
						'description'       => 'The ID of the actor.',
						'type'              => 'integer',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_actor_id' ),
					),
				),
				array(
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'follow_actor' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'show_in_index'       => false,
				),
			)
		);
	}

	/**
	 * Check if the current user has permission to perform actions.
	 *
	 * @return bool|\WP_Error True if the request has permission, WP_Error object otherwise.
	 */
	public function check_permission() {
		if ( ! user_can_activitypub( \get_current_user_id() ) ) {
			return new \WP_Error(
				'rest_forbidden',
				\__( 'Sorry, you are not allowed to perform this action.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Validate actor ID.
	 *
	 * @param int $value The actor ID.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_actor_id( $value ) {
		$actor = \get_post( $value );

		return $actor instanceof \WP_Post && Remote_Actors::POST_TYPE === $actor->post_type;
	}

	/**
	 * Remove follower relationship.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function unfollow_actor( $request ) {
		$actor_id = $request->get_param( 'id' );
		$user_id  = \get_current_user_id();

		$result = Followers::remove( $actor_id, $user_id );

		if ( ! $result ) {
			return new \WP_Error(
				'rest_follower_removal_failed',
				\__( 'Failed to remove follower.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => \__( 'Follower removed successfully.', 'activitypub' ),
			),
			200
		);
	}

	/**
	 * Block an actor.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function block_actor( $request ) {
		$actor_id  = $request->get_param( 'id' );
		$site_wide = $request->get_param( 'site_wide' );
		$user_id   = \get_current_user_id();

		$actor = Remote_Actors::get_actor( $actor_id );
		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		$actor_url = $actor->get_id();

		// Add user-specific block.
		$user_block_success = Moderation::add_user_block( $user_id, 'actor', $actor_url );

		// Add site-wide block if requested and user has permission.
		$site_block_success = true;
		if ( $site_wide && \current_user_can( 'manage_options' ) ) {
			$site_block_success = Moderation::add_site_block( 'actor', $actor_url );
		}

		if ( ! $user_block_success || ! $site_block_success ) {
			return new \WP_Error(
				'rest_actor_block_failed',
				\__( 'Failed to block actor.', 'activitypub' ),
				array( 'status' => 500 )
			);
		}

		// Remove follower relationship after blocking.
		Followers::remove( $actor_id, $user_id );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => \__( 'Actor blocked successfully.', 'activitypub' ),
			),
			200
		);
	}

	/**
	 * Follow an actor.
	 *
	 * @param \WP_REST_Request $request Full data about the request.
	 * @return \WP_REST_Response|\WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function follow_actor( $request ) {
		// Check if following UI is enabled.
		if ( '1' !== \get_option( 'activitypub_following_ui', '0' ) ) {
			return new \WP_Error(
				'rest_following_disabled',
				\__( 'Following feature is disabled.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$actor_id = $request->get_param( 'id' );
		$user_id  = \get_current_user_id();

		$result = Following::follow( $actor_id, $user_id );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => \__( 'Actor followed successfully.', 'activitypub' ),
			),
			200
		);
	}
}
