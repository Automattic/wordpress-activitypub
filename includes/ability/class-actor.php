<?php
/**
 * Actor abilities.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Ability;

use Activitypub\Collection\Remote_Actors;

/**
 * Actor ability class.
 *
 * Provides abilities for looking up remote actor profiles.
 *
 * @since unreleased
 */
class Actor {

	/**
	 * Register Actor abilities.
	 *
	 * @since unreleased
	 */
	public static function register() {
		\wp_register_ability(
			'activitypub/get-actor-info',
			array(
				'label'               => \__( 'Get Actor Info', 'activitypub' ),
				'description'         => \__( 'Fetch profile information for a remote ActivityPub actor.', 'activitypub' ),
				'category'            => 'activitypub-discovery',
				'execute_callback'    => array( self::class, 'get_actor_info' ),
				'permission_callback' => array( self::class, 'permission_callback' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'actor' => array(
							'type'        => 'string',
							'description' => \__( 'Actor URL or WebFinger handle', 'activitypub' ),
						),
					),
					'required'             => array( 'actor' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'                => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'type'              => array(
							'type' => 'string',
						),
						'name'              => array(
							'type' => 'string',
						),
						'preferredUsername' => array(
							'type' => 'string',
						),
						'summary'           => array(
							'type' => 'string',
						),
						'inbox'             => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'outbox'            => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'followers'         => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'following'         => array(
							'type'   => 'string',
							'format' => 'uri',
						),
						'icon'              => array(
							'type' => 'object',
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
	 * @param mixed $input Input parameters (unused).
	 * @return bool
	 */
	public static function permission_callback( $input = null ) {
		return \current_user_can( 'activitypub' );
	}

	/**
	 * Get actor information.
	 *
	 * @since unreleased
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function get_actor_info( $input ) {
		$actor_input = \sanitize_text_field( $input['actor'] );

		$post = Remote_Actors::fetch_by_various( $actor_input );
		if ( \is_wp_error( $post ) ) {
			return $post;
		}

		$actor = Remote_Actors::get_actor( $post );
		if ( \is_wp_error( $actor ) ) {
			return $actor;
		}

		return array(
			'id'                => $actor->get_id(),
			'type'              => $actor->get_type(),
			'name'              => $actor->get_name(),
			'preferredUsername' => $actor->get_preferred_username(),
			'summary'           => $actor->get_summary(),
			'inbox'             => $actor->get_inbox(),
			'outbox'            => $actor->get_outbox(),
			'followers'         => $actor->get_followers(),
			'following'         => $actor->get_following(),
			'icon'              => $actor->get_icon(),
		);
	}
}
