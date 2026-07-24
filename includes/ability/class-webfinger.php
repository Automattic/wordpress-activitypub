<?php
/**
 * WebFinger abilities.
 *
 * @package Activitypub
 * @since unreleased
 */

namespace Activitypub\Ability;

use Activitypub\Webfinger as Webfinger_Util;

/**
 * WebFinger ability class.
 *
 * Provides abilities for resolving WebFinger handles.
 *
 * @since unreleased
 */
class Webfinger {

	/**
	 * Register WebFinger abilities.
	 *
	 * @since unreleased
	 */
	public static function register() {
		\wp_register_ability(
			'activitypub/resolve-handle',
			array(
				'label'               => \__( 'Resolve WebFinger Handle', 'activitypub' ),
				'description'         => \__( 'Resolve a WebFinger handle to an ActivityPub actor URL.', 'activitypub' ),
				'category'            => 'activitypub-discovery',
				'execute_callback'    => array( self::class, 'resolve_handle' ),
				'permission_callback' => array( self::class, 'permission_callback' ),
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'handle' => array(
							'type'        => 'string',
							'description' => \__( 'WebFinger handle (e.g., user@example.com)', 'activitypub' ),
						),
					),
					'required'             => array( 'handle' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type' => 'object',
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
	 * Resolve a WebFinger handle to an actor URL.
	 *
	 * @since unreleased
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function resolve_handle( $input ) {
		$handle = \sanitize_text_field( $input['handle'] );

		return Webfinger_Util::get_data( $handle );
	}
}
