<?php
/**
 * Federation functions.
 *
 * Functions for managing federation state, outbox, and follow/unfollow operations.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Following;
use Activitypub\Collection\Outbox;
use Activitypub\Collection\Remote_Actors;
use Activitypub\Transformer\Factory as Transformer_Factory;

/**
 * Set the federation state of a WordPress object.
 *
 * @param \WP_Comment|\WP_Post $wp_object The WordPress object.
 * @param string               $state     The state of the object.
 */
function set_wp_object_state( $wp_object, $state ) {
	if ( $wp_object instanceof \WP_Post ) {
		$meta_type = 'post';
		$object_id = $wp_object->ID;
	} elseif ( $wp_object instanceof \WP_Comment ) {
		$meta_type = 'comment';
		$object_id = $wp_object->comment_ID;
	} else {
		/**
		 * Allow plugins to mark WordPress objects as federated.
		 *
		 * @param \WP_Comment|\WP_Post $wp_object The WordPress object.
		 */
		\do_action( 'activitypub_mark_wp_object_as_federated', $wp_object );
		return;
	}

	\update_metadata( $meta_type, $object_id, 'activitypub_status', $state );

	if ( ACTIVITYPUB_OBJECT_STATE_DELETED === $state ) {
		\update_metadata( $meta_type, $object_id, 'activitypub_deleted_at', \time() );
	} else {
		\delete_metadata( $meta_type, $object_id, 'activitypub_deleted_at' );
	}
}

/**
 * Get the federation state of a WordPress object.
 *
 * @param \WP_Comment|\WP_Post $wp_object The WordPress object.
 *
 * @return string|false The state of the object or false if not found.
 */
function get_wp_object_state( $wp_object ) {
	if ( $wp_object instanceof \WP_Post ) {
		$meta_type = 'post';
		$object_id = $wp_object->ID;
	} elseif ( $wp_object instanceof \WP_Comment ) {
		$meta_type = 'comment';
		$object_id = $wp_object->comment_ID;
	} else {
		/**
		 * Allow plugins to get the federation state of a WordPress object.
		 *
		 * @param false                $state     The state of the object.
		 * @param \WP_Comment|\WP_Post $wp_object The WordPress object.
		 */
		return \apply_filters( 'activitypub_get_wp_object_state', false, $wp_object );
	}

	return \get_metadata( $meta_type, $object_id, 'activitypub_status', true );
}

/**
 * Check if an ID is from the same domain as the site.
 *
 * @param string $id The ID URI to check.
 *
 * @return boolean True if the ID is a self-ping, false otherwise.
 */
function is_self_ping( $id ) {
	$query_string = \wp_parse_url( $id, PHP_URL_QUERY );

	if ( ! $query_string ) {
		return false;
	}

	$query = array();
	\parse_str( $query_string, $query );

	if (
		is_same_domain( $id ) &&
		in_array( 'c', array_keys( $query ), true )
	) {
		return true;
	}

	return false;
}

/**
 * Add an object to the outbox.
 *
 * @param mixed       $data               The object to add to the outbox.
 * @param string|null $activity_type      Optional. The type of the Activity or null if `$data` is an Activity. Default null.
 * @param integer     $user_id            Optional. The User-ID. Default 0.
 * @param string      $content_visibility Optional. The visibility of the content. See `constants.php` for possible values: `ACTIVITYPUB_CONTENT_VISIBILITY_*`. Default null.
 *
 * @return boolean|int The ID of the outbox item or false on failure.
 */
function add_to_outbox( $data, $activity_type = null, $user_id = 0, $content_visibility = null ) {
	// If the user is disabled, fall back to the blog user when available.
	if ( ! user_can_activitypub( $user_id ) ) {
		if ( user_can_activitypub( Actors::BLOG_USER_ID ) ) {
			$user_id = Actors::BLOG_USER_ID;
		} else {
			return false;
		}
	}

	$transformer = Transformer_Factory::get_transformer( $data );

	if ( ! $transformer || is_wp_error( $transformer ) ) {
		return false;
	}

	if ( $content_visibility ) {
		$transformer->set_content_visibility( $content_visibility );
	} else {
		$content_visibility = $transformer->get_content_visibility();
	}

	if ( $activity_type ) {
		$activity = $transformer->to_activity( $activity_type );
		$activity->set_actor( Actors::get_by_id( $user_id )->get_id() );
	} else {
		$activity = $transformer->to_object();
	}

	if ( ! $activity || \is_wp_error( $activity ) ) {
		/**
		 * Action triggered when adding an object to the outbox fails.
		 *
		 * @param \WP_Error   $activity           The error object or false.
		 * @param mixed       $data               The object that failed to be added to the outbox.
		 * @param string|null $activity_type      The type of the Activity or null if `$data` is an Activity.
		 * @param int         $user_id            The User ID.
		 * @param string      $content_visibility The visibility of the content. See `constants.php` for possible values: `ACTIVITYPUB_CONTENT_VISIBILITY_*`.
		 */
		\do_action( 'activitypub_add_to_outbox_failed', $activity, $data, $activity_type, $user_id, $content_visibility );

		return false;
	}

	$outbox_activity_id = Outbox::add( $activity, $user_id, $content_visibility );

	if ( ! $outbox_activity_id || \is_wp_error( $outbox_activity_id ) ) {
		/**
		 * Action triggered when adding an object to the outbox fails.
		 *
		 * @param false|\WP_Error $outbox_activity_id The error object or false.
		 * @param mixed           $data               The object that failed to be added to the outbox.
		 * @param string|null     $activity_type      The type of the Activity or null if `$data` is an Activity.
		 * @param int             $user_id            The User ID.
		 * @param string          $content_visibility The visibility of the content. See `constants.php` for possible values: `ACTIVITYPUB_CONTENT_VISIBILITY_*`.
		 */
		\do_action( 'activitypub_add_to_outbox_failed', $outbox_activity_id, $data, $activity_type, $user_id, $content_visibility );

		return false;
	}

	/**
	 * Action triggered after an object has been added to the outbox.
	 *
	 * @param int      $outbox_activity_id The ID of the outbox item.
	 * @param Activity $activity           The activity object.
	 * @param int      $user_id            The User-ID.
	 * @param string   $content_visibility The visibility of the content. See `constants.php` for possible values: `ACTIVITYPUB_CONTENT_VISIBILITY_*`.
	 */
	\do_action( 'post_activitypub_add_to_outbox', $outbox_activity_id, $activity, $user_id, $content_visibility );

	// Update state based on activity.
	$state_map = array(
		'Create' => ACTIVITYPUB_OBJECT_STATE_FEDERATED,
		'Update' => ACTIVITYPUB_OBJECT_STATE_FEDERATED,
		'Delete' => ACTIVITYPUB_OBJECT_STATE_DELETED,
	);

	if ( $activity_type && isset( $state_map[ $activity_type ] ) ) {
		set_wp_object_state( $data, $state_map[ $activity_type ] );
	}

	return $outbox_activity_id;
}

/**
 * Follow a user.
 *
 * @param string|int $remote_actor The Actor URL, WebFinger Resource or Post-ID of the remote Actor.
 * @param int        $user_id      The ID of the WordPress User.
 *
 * @return int|\WP_Error The Outbox ID on success or a WP_Error on failure.
 */
function follow( $remote_actor, $user_id ) {
	if ( \is_numeric( $remote_actor ) ) {
		return Following::follow( $remote_actor, $user_id );
	}

	if ( ! \filter_var( $remote_actor, FILTER_VALIDATE_URL ) ) {
		$remote_actor = Webfinger::resolve( $remote_actor );
	}

	if ( \is_wp_error( $remote_actor ) ) {
		return $remote_actor;
	}

	$remote_actor_post = Remote_Actors::fetch_by_uri( $remote_actor );

	if ( \is_wp_error( $remote_actor_post ) ) {
		return $remote_actor_post;
	}

	return Following::follow( $remote_actor_post, $user_id );
}

/**
 * Unfollow a user.
 *
 * @param string|int $remote_actor The Actor URL, WebFinger Resource or Post-ID of the remote Actor.
 * @param int        $user_id      The ID of the WordPress User.
 *
 * @return \WP_Post|\WP_Error The Actor post or a WP_Error.
 */
function unfollow( $remote_actor, $user_id ) {
	if ( \is_numeric( $remote_actor ) ) {
		return Following::unfollow( $remote_actor, $user_id );
	}

	if ( ! \filter_var( $remote_actor, FILTER_VALIDATE_URL ) ) {
		$remote_actor = Webfinger::resolve( $remote_actor );
	}

	if ( \is_wp_error( $remote_actor ) ) {
		return $remote_actor;
	}

	$remote_actor_post = Remote_Actors::fetch_by_uri( $remote_actor );

	if ( \is_wp_error( $remote_actor_post ) ) {
		return $remote_actor_post;
	}

	return Following::unfollow( $remote_actor_post, $user_id );
}
