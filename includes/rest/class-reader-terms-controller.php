<?php
/**
 * Reader_Terms_Controller file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * Reader_Terms_Controller class.
 *
 * Restricts the WordPress REST routes that core generates for the `ap_tag` and
 * `ap_object_type` taxonomies. Their terms are derived from the cached remote
 * posts, so they are limited to users who can use ActivityPub.
 *
 * @since unreleased
 */
class Reader_Terms_Controller extends \WP_REST_Terms_Controller {
	use Reader_Permission;

	/**
	 * Check whether a request has read access to the terms.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$permission = $this->check_reader_permission();

		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Check whether a request has read access to a single term.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_item_permissions_check( $request ) {
		$permission = $this->check_reader_permission();

		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		return parent::get_item_permissions_check( $request );
	}
}
