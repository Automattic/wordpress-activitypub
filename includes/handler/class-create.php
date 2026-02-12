<?php
/**
 * Create handler file.
 *
 * @package Activitypub
 */

namespace Activitypub\Handler;

use Activitypub\Collection\Interactions;
use Activitypub\Collection\Posts;
use Activitypub\Tombstone;

use function Activitypub\get_activity_visibility;
use function Activitypub\get_content_visibility;
use function Activitypub\is_activity_reply;
use function Activitypub\is_quote_activity;
use function Activitypub\is_self_ping;
use function Activitypub\object_id_to_comment;

/**
 * Handle Create requests.
 */
class Create {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Incoming activities (from remote actors via inbox).
		\add_action( 'activitypub_handled_inbox_create', array( self::class, 'incoming' ), 10, 3 );

		// Outgoing activities (from local actors via outbox).
		\add_filter( 'activitypub_outbox_create', array( self::class, 'outgoing' ), 10, 3 );

		\add_filter( 'activitypub_validate_object', array( self::class, 'validate_object' ), 10, 3 );
		\add_action( 'post_activitypub_add_to_outbox', array( self::class, 'maybe_unbury' ), 10, 2 );
	}

	/**
	 * Handle incoming "Create" activities from remote actors.
	 *
	 * @param array $activity        The activity data.
	 * @param int[] $user_ids        The local user IDs targeted.
	 * @param mixed $activity_object The activity object (unused, required by hook signature).
	 *
	 * @return \WP_Post|\WP_Comment|\WP_Error|false The created content or error.
	 */
	public static function incoming( $activity, $user_ids = null, $activity_object = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		// Check for private and/or direct messages.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			return false;
		}

		// Route to appropriate handler based on content type.
		if ( is_activity_reply( $activity ) || is_quote_activity( $activity ) ) {
			$result = self::incoming_interaction( $activity, $user_ids );
		} else {
			$result = self::incoming_post( $activity, $user_ids );
		}

		if ( false === $result ) {
			return $result;
		}

		$success = ! \is_wp_error( $result );

		/**
		 * Fires after an incoming ActivityPub Create activity has been handled.
		 *
		 * @param array                          $activity The ActivityPub activity data.
		 * @param int[]                          $user_ids The local user IDs.
		 * @param bool                           $success  True on success, false otherwise.
		 * @param \WP_Comment|\WP_Post|\WP_Error $result   The created content or error.
		 */
		\do_action( 'activitypub_handled_create', $activity, (array) $user_ids, $success, $result );

		return $result;
	}

	/**
	 * Handle outgoing "Create" activities from local actors.
	 *
	 * Creates WordPress content and adds to outbox for federation.
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return int|\WP_Error|null The outbox ID on success, WP_Error on failure, null if not handled.
	 */
	public static function outgoing( $activity, $user_id = null, $visibility = null ) {
		// Check for private and/or direct messages.
		if ( ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === get_activity_visibility( $activity ) ) {
			return false;
		}

		$object = $activity['object'] ?? array();

		if ( ! \is_array( $object ) ) {
			return new \WP_Error( 'invalid_object', 'Invalid object in activity.' );
		}

		$object_type = $object['type'] ?? '';

		// Only handle Note and Article types for now.
		if ( ! \in_array( $object_type, array( 'Note', 'Article' ), true ) ) {
			return null;
		}

		// TODO: Handle replies/interactions differently.
		if ( is_activity_reply( $activity ) || is_quote_activity( $activity ) ) {
			return null;
		}

		return self::outgoing_post( $activity, $user_id, $visibility );
	}

	/**
	 * Handle incoming interaction (reply/quote) from remote actor.
	 *
	 * @param array $activity The activity data.
	 * @param int[] $user_ids The local user IDs targeted.
	 *
	 * @return \WP_Comment|\WP_Error|false Comment, WP_Error, or false.
	 */
	private static function incoming_interaction( $activity, $user_ids ) {
		$existing_comment = object_id_to_comment( $activity['object']['id'] );

		// If comment exists, call update action.
		if ( $existing_comment ) {
			Update::incoming( $activity, (array) $user_ids, null );

			return false;
		}

		if ( is_self_ping( $activity['object']['id'] ) ) {
			return false;
		}

		$result = Interactions::add_comment( $activity );

		if ( ! $result || \is_wp_error( $result ) ) {
			return $result;
		}

		return \get_comment( $result );
	}

	/**
	 * Handle incoming post from remote actor.
	 *
	 * @param array $activity The activity data.
	 * @param int[] $user_ids The local user IDs targeted.
	 *
	 * @return \WP_Post|\WP_Error|false Post, WP_Error, or false.
	 */
	private static function incoming_post( $activity, $user_ids ) {
		if ( ! \get_option( 'activitypub_create_posts', false ) ) {
			return false;
		}

		$existing_post = Posts::get_by_guid( $activity['object']['id'] );

		// If post exists, call update action.
		if ( $existing_post instanceof \WP_Post ) {
			Update::incoming( $activity, (array) $user_ids, null );

			return false;
		}

		return Posts::add( $activity, $user_ids );
	}

	/**
	 * Handle outgoing post from local actor.
	 *
	 * Creates a WordPress post. The scheduler will add it to the outbox.
	 *
	 * @param array       $activity   The activity data.
	 * @param int         $user_id    The local user ID.
	 * @param string|null $visibility Content visibility.
	 *
	 * @return \WP_Post|\WP_Error The created post on success, WP_Error on failure.
	 */
	private static function outgoing_post( $activity, $user_id, $visibility ) {
		$object = $activity['object'] ?? array();

		$object_type = $object['type'] ?? '';
		$content     = $object['content'] ?? '';
		$name        = $object['name'] ?? '';
		$summary     = $object['summary'] ?? '';

		// Use name as title for Articles, or generate from content for Notes.
		$title = $name;
		if ( empty( $title ) && ! empty( $content ) ) {
			$title = \wp_trim_words( \wp_strip_all_tags( $content ), 10, '...' );
		}

		// Determine visibility if not provided.
		if ( null === $visibility ) {
			$visibility = get_content_visibility( $activity );
		}

		$post_data = array(
			'post_author'  => $user_id > 0 ? $user_id : 0,
			'post_title'   => $title,
			'post_content' => $content,
			'post_excerpt' => $summary,
			'post_status'  => ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE === $visibility ? 'private' : 'publish',
			'post_type'    => 'post',
			'meta_input'   => array(
				'activitypub_content_visibility' => $visibility,
			),
		);

		$post_id = \wp_insert_post( $post_data, true );

		if ( \is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set post format to 'status' for Notes so the transformer maps it back correctly.
		if ( 'Note' === $object_type ) {
			\set_post_format( $post_id, 'status' );
		}

		$post = \get_post( $post_id );

		/**
		 * Fires after a post has been created from an outgoing Create activity.
		 *
		 * @param int    $post_id    The created post ID.
		 * @param array  $activity   The activity data.
		 * @param int    $user_id    The user ID.
		 * @param string $visibility The content visibility.
		 */
		\do_action( 'activitypub_outbox_created_post', $post_id, $activity, $user_id, $visibility );

		return $post;
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
		$activity = $request->get_json_params();

		if ( empty( $activity['type'] ) ) {
			return false;
		}

		if ( 'Create' !== $activity['type'] ) {
			return $valid;
		}

		if ( ! isset( $activity['object'] ) || ! \is_array( $activity['object'] ) ) {
			return false;
		}

		if ( ! isset( $activity['object']['id'], $activity['object']['content'] ) ) {
			return false;
		}

		return $valid;
	}

	/**
	 * Remove a URL from the tombstone registry when a Create or Update activity is sent.
	 *
	 * This handles the case where a post was soft-deleted (visibility changed to local/private)
	 * and then later changed back to public. The Create/Update activity indicates the post is being
	 * re-federated, so we remove it from the tombstone registry.
	 *
	 * @param int                            $outbox_id The ID of the outbox activity.
	 * @param \Activitypub\Activity\Activity $activity  The Activity object.
	 */
	public static function maybe_unbury( $outbox_id, $activity ) {
		if ( ! in_array( $activity->get_type(), array( 'Create', 'Update' ), true ) ) {
			return;
		}

		$object = $activity->get_object();

		if ( $object ) {
			Tombstone::remove( $object->get_id(), $object->get_url() );
		}
	}

	/**
	 * Handle "Create" requests.
	 *
	 * @deprecated unreleased Use Create::incoming() instead.
	 *
	 * @param array $activity        The activity data.
	 * @param int[] $user_ids        The local user IDs targeted.
	 * @param mixed $activity_object The activity object.
	 *
	 * @return \WP_Post|\WP_Comment|\WP_Error|false The created content or error.
	 */
	public static function handle_create( $activity, $user_ids = null, $activity_object = null ) {
		\_deprecated_function( __METHOD__, 'unreleased', 'Create::incoming()' );

		return self::incoming( $activity, $user_ids, $activity_object );
	}
}
