<?php
/**
 * Reader_Permission trait file.
 *
 * @package Activitypub
 */

namespace Activitypub\Rest;

use function Activitypub\user_can_act_as_blog;
use function Activitypub\user_can_activitypub;

/**
 * Reader_Permission trait.
 *
 * Shared authorization checks for the WordPress REST routes that WordPress core
 * generates for the reader's own post types and taxonomies. Those routes hold
 * cached remote content and the social graph, so they are restricted to users
 * who can use ActivityPub rather than being world readable.
 *
 * @since 9.3.0
 */
trait Reader_Permission {
	/**
	 * Check whether the current user holds the capability to read the reader's cached data.
	 *
	 * Named for the capability rather than the read, so it is not mistaken for core's
	 * `check_read_permission()`, which answers whether one given post may be read.
	 *
	 * @since 9.3.0
	 *
	 * @return true|\WP_Error True if the current user may read, WP_Error otherwise.
	 */
	protected function check_reader_capability() {
		/*
		 * `user_can_activitypub()` rather than the raw capability: it also honours
		 * `is_user_type_disabled( 'user' )` and the `activitypub_user_can_activitypub` filter, the
		 * way the plugin's own admin routes do. A logged-out request has user ID 0, which is also
		 * `Actors::BLOG_USER_ID`, so the login check has to come first.
		 */
		if ( \is_user_logged_in() && ( user_can_activitypub( \get_current_user_id() ) || user_can_act_as_blog() ) ) {
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
	 * @since 9.3.0
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
	 * @since 9.3.0
	 *
	 * @param int|int[] $user_ids One or more local user IDs a record belongs to.
	 * @return bool True if the current user may read it, false otherwise.
	 */
	protected function can_read_feed_of( $user_ids ) {
		/*
		 * Same reason the login check leads in `check_reader_capability()`: a logged-out request
		 * has user ID 0, and so does the blog actor, so the identity match below would hand the
		 * blog actor's feed to anybody. This predicate is also reached from callers that never
		 * pass through that gate, such as core's comments controller.
		 */
		if ( ! \is_user_logged_in() ) {
			return false;
		}

		// Blanket access: an actor with no relationship at all has nothing to check per target.
		if ( \current_user_can( 'list_users' ) ) {
			return true;
		}

		$user_ids = \array_map( 'intval', (array) $user_ids );

		if ( \in_array( \get_current_user_id(), $user_ids, true ) ) {
			return true;
		}

		foreach ( $user_ids as $user_id ) {
			if ( \current_user_can( 'edit_user', $user_id ) ) {
				return true;
			}
		}

		return false;
	}
}
