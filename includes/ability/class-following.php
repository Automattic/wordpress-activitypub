<?php
/**
 * Following abilities.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Ability;

use Activitypub\Collection\Following as Following_Collection;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\follow;
use function Activitypub\unfollow;

/**
 * Following ability class.
 *
 * Provides abilities for managing the following list of a local actor.
 *
 * @since unreleased
 */
class Following {

	/**
	 * Register Following abilities.
	 *
	 * @since unreleased
	 */
	public static function register() {
		self::register_get_following();
		self::register_follow();
		self::register_unfollow();
	}

	/**
	 * Register the get-following ability.
	 *
	 * @since unreleased
	 */
	private static function register_get_following() {
		\wp_register_ability(
			'activitypub/get-following',
			array(
				'label'               => \__( 'Get Following', 'activitypub' ),
				'description'         => \__( 'List accounts being followed by a local actor.', 'activitypub' ),
				'category'            => 'activitypub-social',
				'execute_callback'    => array( self::class, 'get_following' ),
				'permission_callback' => array( self::class, 'permission_callback' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'user_id'  => array(
							'type'        => 'integer',
							'description' => \__( 'The local actor user ID.', 'activitypub' ),
						),
						'page'     => array(
							'type'        => 'integer',
							'description' => \__( 'Page number for pagination.', 'activitypub' ),
						),
						'per_page' => array(
							'type'        => 'integer',
							'description' => \__( 'Number of results per page.', 'activitypub' ),
						),
					),
					'required'             => array( 'user_id' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'following' => array(
							'type'  => 'array',
							'items' => Actor::item_schema(),
						),
						'total'     => array(
							'type' => 'integer',
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the follow ability.
	 *
	 * @since unreleased
	 */
	private static function register_follow() {
		\wp_register_ability(
			'activitypub/follow',
			array(
				'label'               => \__( 'Follow', 'activitypub' ),
				'description'         => \__( 'Follow a remote actor.', 'activitypub' ),
				'category'            => 'activitypub-social',
				'execute_callback'    => array( self::class, 'follow' ),
				'permission_callback' => array( self::class, 'permission_callback' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'actor'   => array(
							'type'        => 'string',
							'description' => \__( 'Actor URL or WebFinger handle to follow.', 'activitypub' ),
						),
						'user_id' => array(
							'type'        => 'integer',
							'description' => \__( 'The local actor user ID. Defaults to the current user.', 'activitypub' ),
						),
					),
					'required'             => array( 'actor' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'outbox_item_id' => array(
							'type' => 'integer',
						),
						'status'         => array(
							'type' => 'string',
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Register the unfollow ability.
	 *
	 * @since unreleased
	 */
	private static function register_unfollow() {
		\wp_register_ability(
			'activitypub/unfollow',
			array(
				'label'               => \__( 'Unfollow', 'activitypub' ),
				'description'         => \__( 'Unfollow a remote actor.', 'activitypub' ),
				'category'            => 'activitypub-social',
				'execute_callback'    => array( self::class, 'unfollow' ),
				'permission_callback' => array( self::class, 'permission_callback' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'actor'   => array(
							'type'        => 'string',
							'description' => \__( 'Actor URL or WebFinger handle to unfollow.', 'activitypub' ),
						),
						'user_id' => array(
							'type'        => 'integer',
							'description' => \__( 'The local actor user ID. Defaults to the current user.', 'activitypub' ),
						),
					),
					'required'             => array( 'actor' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array(
							'type' => 'boolean',
						),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Permission callback.
	 *
	 * @since unreleased
	 *
	 * @return bool
	 */
	public static function permission_callback() {
		return \current_user_can( 'activitypub' );
	}

	/**
	 * Get following for a local actor.
	 *
	 * @since unreleased
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function get_following( $input ) {
		$user_id = \absint( $input['user_id'] );

		if ( ! $user_id ) {
			return new \WP_Error( 'activitypub_invalid_user_id', \__( 'Invalid user ID.', 'activitypub' ), array( 'status' => 400 ) );
		}

		if ( \get_current_user_id() !== $user_id && ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You are not allowed to view another user\'s following list.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$per_page = isset( $input['per_page'] ) ? \min( \absint( $input['per_page'] ), 100 ) : 20;
		$page     = isset( $input['page'] ) ? \max( 1, \absint( $input['page'] ) ) : 1;

		$data = Following_Collection::query( $user_id, $per_page, $page );

		$following = array();
		foreach ( $data['following'] as $post ) {
			$actor = Remote_Actors::get_actor( $post );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}
			$following[] = Actor::to_array( $actor );
		}

		return array(
			'following' => $following,
			'total'     => $data['total'],
		);
	}

	/**
	 * Follow a remote actor.
	 *
	 * @since unreleased
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function follow( $input ) {
		if ( '1' !== \get_option( 'activitypub_following_ui', '0' ) ) {
			return new \WP_Error(
				'activitypub_following_disabled',
				\__( 'Following feature is disabled.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$actor   = \sanitize_text_field( $input['actor'] );
		$user_id = isset( $input['user_id'] ) ? \absint( $input['user_id'] ) : \get_current_user_id();

		if ( \get_current_user_id() !== $user_id && ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You are not allowed to act on behalf of another user.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$result = follow( $actor, $user_id );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'outbox_item_id' => $result,
			'status'         => 'pending',
		);
	}

	/**
	 * Unfollow a remote actor.
	 *
	 * @since unreleased
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function unfollow( $input ) {
		if ( '1' !== \get_option( 'activitypub_following_ui', '0' ) ) {
			return new \WP_Error(
				'activitypub_following_disabled',
				\__( 'Following feature is disabled.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$actor   = \sanitize_text_field( $input['actor'] );
		$user_id = isset( $input['user_id'] ) ? \absint( $input['user_id'] ) : \get_current_user_id();

		if ( \get_current_user_id() !== $user_id && ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You are not allowed to act on behalf of another user.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$result = unfollow( $actor, $user_id );

		if ( \is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
		);
	}
}
