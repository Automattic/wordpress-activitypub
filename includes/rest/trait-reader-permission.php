<?php
/**
 * Reader_Permission trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

/**
 * Reader_Permission trait.
 *
 * Shared authorization checks for the WordPress REST routes that WordPress core
 * generates for the reader's own post types and taxonomies. Those routes hold
 * cached remote content and the social graph, so they are restricted to users
 * who can use ActivityPub rather than being world readable.
 *
 * @since unreleased
 */
trait Reader_Permission {
	/**
	 * Check whether the current user holds the capability to read the reader's cached data.
	 *
	 * Named for the capability rather than the read, so it is not mistaken for core's
	 * `check_read_permission()`, which answers whether one given post may be read.
	 *
	 * @since unreleased
	 *
	 * @return true|\WP_Error True if the current user may read, WP_Error otherwise.
	 */
	protected function check_reader_capability() {
		if ( \current_user_can( 'activitypub' ) || \current_user_can( 'manage_options' ) ) {
			return true;
		}

		return new \WP_Error(
			'activitypub_rest_forbidden',
			\__( 'Sorry, you are not allowed to read ActivityPub data.', 'activitypub' ),
			array( 'status' => \rest_authorization_required_code() )
		);
	}

	/**
	 * Check whether a request has read access to the collection.
	 *
	 * A trait method wins over an inherited one, so this overrides the core controller's check
	 * and defers to it once the reader gate has passed.
	 *
	 * @since unreleased
	 *
	 * @param \WP_REST_Request $request Full details about the request.
	 * @return true|\WP_Error True if the request has read access, WP_Error otherwise.
	 */
	public function get_items_permissions_check( $request ) {
		$permission = $this->check_reader_capability();

		if ( \is_wp_error( $permission ) ) {
			return $permission;
		}

		return parent::get_items_permissions_check( $request );
	}

	/**
	 * Check whether the current user may read the feed of the given actor(s).
	 *
	 * Users who can list users may read any actor's feed, everybody else is
	 * limited to their own.
	 *
	 * @since unreleased
	 *
	 * @param int|int[] $user_ids One or more local user IDs a record belongs to.
	 * @return bool True if the current user may read it, false otherwise.
	 */
	protected function can_read_feed_of( $user_ids ) {
		if ( \current_user_can( 'list_users' ) ) {
			return true;
		}

		return \in_array( \get_current_user_id(), \array_map( 'intval', (array) $user_ids ), true );
	}
}
