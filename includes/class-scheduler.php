<?php
/**
 * Scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub;

use Activitypub\Collection\Followers;

/**
 * Scheduler class.
 *
 * @author Matthias Pfefferle
 */
class Scheduler {

	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Post transitions.
		\add_action( 'transition_post_status', array( self::class, 'schedule_post_activity' ), 33, 3 );
		\add_action(
			'edit_attachment',
			function ( $post_id ) {
				self::schedule_post_activity( 'publish', 'publish', $post_id );
			}
		);
		\add_action(
			'add_attachment',
			function ( $post_id ) {
				self::schedule_post_activity( 'publish', '', $post_id );
			}
		);
		\add_action(
			'delete_attachment',
			function ( $post_id ) {
				self::schedule_post_activity( 'trash', '', $post_id );
			}
		);

		if ( ! ACTIVITYPUB_DISABLE_OUTGOING_INTERACTIONS ) {
			// Comment transitions.
			\add_action( 'transition_comment_status', array( self::class, 'schedule_comment_activity' ), 20, 3 );
			\add_action(
				'edit_comment',
				function ( $comment_id ) {
					self::schedule_comment_activity( 'approved', 'approved', $comment_id );
				}
			);
			\add_action(
				'wp_insert_comment',
				function ( $comment_id ) {
					self::schedule_comment_activity( 'approved', '', $comment_id );
				}
			);
		}

		// Follower Cleanups.
		\add_action( 'activitypub_update_followers', array( self::class, 'update_followers' ) );
		\add_action( 'activitypub_cleanup_followers', array( self::class, 'cleanup_followers' ) );

		// Profile updates for blog options.
		if ( ! is_user_type_disabled( 'blog' ) ) {
			\add_action( 'update_option_site_icon', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_blogdescription', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_blogname', array( self::class, 'blog_user_update' ) );
			\add_filter( 'pre_set_theme_mod_custom_logo', array( self::class, 'blog_user_update' ) );
			\add_filter( 'pre_set_theme_mod_header_image', array( self::class, 'blog_user_update' ) );
		}

		// Profile updates for user options.
		if ( ! is_user_type_disabled( 'user' ) ) {
			\add_action( 'wp_update_user', array( self::class, 'user_update' ) );
			\add_action( 'updated_user_meta', array( self::class, 'user_meta_update' ), 10, 3 );
			// @todo figure out a feasible way of updating the header image since it's not unique to any user.
		}
	}

	/**
	 * Schedule all ActivityPub schedules.
	 */
	public static function register_schedules() {
		if ( ! \wp_next_scheduled( 'activitypub_update_followers' ) ) {
			\wp_schedule_event( time(), 'hourly', 'activitypub_update_followers' );
		}

		if ( ! \wp_next_scheduled( 'activitypub_cleanup_followers' ) ) {
			\wp_schedule_event( time(), 'daily', 'activitypub_cleanup_followers' );
		}
	}

	/**
	 * Un-schedule all ActivityPub schedules.
	 *
	 * @return void
	 */
	public static function deregister_schedules() {
		wp_unschedule_hook( 'activitypub_update_followers' );
		wp_unschedule_hook( 'activitypub_cleanup_followers' );
	}


	/**
	 * Schedule Activities.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		$post = get_post( $post );

		if ( ! $post || is_post_disabled( $post ) ) {
			return;
		}

		if ( 'ap_extrafield' === $post->post_type ) {
			self::schedule_profile_update( $post->post_author );
			return;
		}

		if ( 'ap_extrafield_blog' === $post->post_type ) {
			self::schedule_profile_update( 0 );
			return;
		}

		// Do not send activities if post is password protected.
		if ( \post_password_required( $post ) ) {
			return;
		}

		// Check if post-type supports ActivityPub.
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		switch ( $new_status ) {
			case 'publish':
				$type = ( 'publish' === $old_status ) ? 'Update' : 'Create';
				break;

			case 'draft':
				$type = ( 'publish' === $old_status ) ? 'Update' : false;
				break;

			case 'trash':
				$type = 'federated' === get_wp_object_state( $post ) ? 'Delete' : false;
				break;

			default:
				$type = false;
		}

		// No activity to schedule.
		if ( empty( $type ) ) {
			return;
		}

		$hook = 'activitypub_send_post';
		$args = array( $post->ID, $type );

		if ( false === wp_next_scheduled( $hook, $args ) ) {
			set_wp_object_state( $post, 'federate' );
			\wp_schedule_single_event( \time() + 10, $hook, $args );
		}
	}

	/**
	 * Schedule Comment Activities.
	 *
	 * @see transition_comment_status()
	 *
	 * @param string      $new_status New comment status.
	 * @param string      $old_status Old comment status.
	 * @param \WP_Comment $comment    Comment object.
	 */
	public static function schedule_comment_activity( $new_status, $old_status, $comment ) {
		$comment = get_comment( $comment );

		// Federate only comments that are written by a registered user.
		if ( ! $comment || ! $comment->user_id ) {
			return;
		}

		$type = false;

		if (
			'approved' === $new_status &&
			'approved' !== $old_status
		) {
			$type = 'Create';
		} elseif ( 'approved' === $new_status ) {
			$type = 'Update';
			\update_comment_meta( $comment->comment_ID, 'activitypub_comment_modified', time(), true );
		} elseif (
			'trash' === $new_status ||
			'spam' === $new_status
		) {
			$type = 'Delete';
		}

		if ( empty( $type ) ) {
			return;
		}

		// Check if comment should be federated or not.
		if ( ! should_comment_be_federated( $comment ) ) {
			return;
		}

		$hook = 'activitypub_send_comment';
		$args = array( $comment->comment_ID, $type );

		if ( false === wp_next_scheduled( $hook, $args ) ) {
			set_wp_object_state( $comment, 'federate' );
			\wp_schedule_single_event( \time(), $hook, $args );
		}
	}

	/**
	 * Update followers.
	 */
	public static function update_followers() {
		$number = 5;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		/**
		 * Filter the number of followers to update.
		 *
		 * @param int $number The number of followers to update.
		 */
		$number    = apply_filters( 'activitypub_update_followers_number', $number );
		$followers = Followers::get_outdated_followers( $number );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_id(), false );

			if ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				Followers::add_error( $follower->get__id(), $meta );
			} else {
				$follower->from_array( $meta );
				$follower->update();
			}
		}
	}

	/**
	 * Cleanup followers.
	 */
	public static function cleanup_followers() {
		$number = 5;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		/**
		 * Filter the number of followers to clean up.
		 *
		 * @param int $number The number of followers to clean up.
		 */
		$number    = apply_filters( 'activitypub_update_followers_number', $number );
		$followers = Followers::get_faulty_followers( $number );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_url(), false );

			if ( is_tombstone( $meta ) ) {
				$follower->delete();
			} elseif ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				if ( $follower->count_errors() >= 5 ) {
					$follower->delete();
					\wp_schedule_single_event(
						\time(),
						'activitypub_delete_actor_interactions',
						array( $follower->get_id() )
					);
				} else {
					Followers::add_error( $follower->get__id(), $meta );
				}
			} else {
				$follower->reset_errors();
			}
		}
	}

	/**
	 * Send a profile update when relevant user meta is updated.
	 *
	 * @param  int    $meta_id  Meta ID being updated.
	 * @param  int    $user_id  User ID being updated.
	 * @param  string $meta_key Meta key being updated.
	 */
	public static function user_meta_update( $meta_id, $user_id, $meta_key ) {
		// Don't bother if the user can't publish.
		if ( ! \user_can( $user_id, 'activitypub' ) ) {
			return;
		}

		// The user meta fields that affect a profile.
		$fields = array(
			'activitypub_description',
			'activitypub_header_image',
			'description',
			'user_url',
			'display_name',
		);
		if ( in_array( $meta_key, $fields, true ) ) {
			self::schedule_profile_update( $user_id );
		}
	}

	/**
	 * Send a profile update when a user is updated.
	 *
	 * @param int $user_id User ID being updated.
	 */
	public static function user_update( $user_id ) {
		// Don't bother if the user can't publish.
		if ( ! \user_can( $user_id, 'activitypub' ) ) {
			return;
		}

		self::schedule_profile_update( $user_id );
	}

	/**
	 * Theme mods only have a dynamic filter so we fudge it like this.
	 *
	 * @param mixed $value Optional. The value to be updated. Default null.
	 *
	 * @return mixed
	 */
	public static function blog_user_update( $value = null ) {
		self::schedule_profile_update( 0 );
		return $value;
	}

	/**
	 * Send a profile update to all followers. Gets hooked into all relevant options/meta etc.
	 *
	 * @param int $user_id  The user ID to update (Could be 0 for Blog-User).
	 */
	public static function schedule_profile_update( $user_id ) {
		\wp_schedule_single_event(
			\time() + 10,
			'activitypub_send_update_profile_activity',
			array( $user_id )
		);
	}
}
