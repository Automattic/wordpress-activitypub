<?php
/**
 * Followers abilities.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Ability;

use Activitypub\Collection\Followers as Followers_Collection;
use Activitypub\Collection\Remote_Actors;

/**
 * Followers ability class.
 *
 * Provides abilities for listing followers of a local actor.
 *
 * @since unreleased
 */
class Followers {

	/**
	 * Register Followers abilities.
	 *
	 * @since unreleased
	 */
	public static function register() {
		\wp_register_ability(
			'activitypub/get-followers',
			array(
				'label'               => \__( 'Get Followers', 'activitypub' ),
				'description'         => \__( 'List followers for a local actor.', 'activitypub' ),
				'category'            => 'activitypub-social',
				'execute_callback'    => array( self::class, 'get_followers' ),
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
						'followers' => array(
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
	 * Get followers for a local actor.
	 *
	 * @since unreleased
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function get_followers( $input ) {
		$user_id = \absint( $input['user_id'] );

		if ( ! $user_id ) {
			return new \WP_Error( 'activitypub_invalid_user_id', \__( 'Invalid user ID.', 'activitypub' ), array( 'status' => 400 ) );
		}

		if ( \get_current_user_id() !== $user_id && ! \current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'activitypub_forbidden',
				\__( 'You are not allowed to view another user\'s followers list.', 'activitypub' ),
				array( 'status' => 403 )
			);
		}

		$per_page = isset( $input['per_page'] ) ? \min( \absint( $input['per_page'] ), 100 ) : 20;
		$page     = isset( $input['page'] ) ? \max( 1, \absint( $input['page'] ) ) : 1;

		$data = Followers_Collection::query( $user_id, $per_page, $page );

		$followers = array();
		foreach ( $data['followers'] as $post ) {
			$actor = Remote_Actors::get_actor( $post );
			if ( \is_wp_error( $actor ) ) {
				continue;
			}
			$followers[] = Actor::to_array( $actor );
		}

		return array(
			'followers' => $followers,
			'total'     => $data['total'],
		);
	}
}
