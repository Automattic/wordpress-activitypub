<?php
/**
 * Accept handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Following;

use function Activitypub\object_to_uri;

/**
 * Handle Accept requests.
 */
class Accept {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		\add_action(
			'activitypub_inbox_accept',
			array( self::class, 'handle_accept' ),
			10,
			2
		);

		\add_filter(
			'activitypub_validate_object',
			array( self::class, 'validate_object' ),
			10,
			3
		);
	}

	/**
	 * Handles "Accept" requests.
	 *
	 * @param array $accept  The activity-object.
	 * @param int   $user_id The id of the local blog-user.
	 */
	public static function handle_accept( $accept, $user_id ) {
		Following::accept( object_to_uri( $accept['object'] ), $user_id );
	}

	/**
	 * Validate the object.
	 *
	 * @param bool             $valid   The validation state.
	 * @param string           $param   The object parameter.
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return bool The validation state: true if valid, false if not.
	 */
	public static function validate_object( $valid, $param, $request ) {
		$json_params = $request->get_json_params();

		if ( empty( $json_params['type'] ) ) {
			return false;
		}

		if (
			'Accept' !== $json_params['type'] ||
			\is_wp_error( $request )
		) {
			return $valid;
		}

		$required = array(
			'actor',
			'object',
		);

		if ( \array_intersect( $required, \array_keys( $json_params ) ) !== $required ) {
			return false;
		}

		return $valid;
	}
}
