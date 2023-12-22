<?php

namespace Activitypub;

use Activitypub\Collection\Users;
use Activitypub\Collection\Followers;
use Activitypub\Transformer\Post;

use function Activitypub\is_user_type_disabled;

/**
 * ActivityPub Scheduler Class
 *
 * @author Matthias Pfefferle
 */
class Scheduler {

	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		// Post transitions
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

		// Comment transitions
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

		// Follower Cleanups
		\add_action( 'activitypub_update_followers', array( self::class, 'update_followers' ) );
		\add_action( 'activitypub_cleanup_followers', array( self::class, 'cleanup_followers' ) );

		// Migration
		\add_action( 'admin_init', array( self::class, 'schedule_migration' ) );

		// profile updates for blog options
		if ( ! is_user_type_disabled( 'blog' ) ) {
			\add_action( 'update_option_site_icon', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_blogdescription', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_blogname', array( self::class, 'blog_user_update' ) );
			\add_filter( 'pre_set_theme_mod_custom_logo', array( self::class, 'blog_user_update' ) );
			\add_filter( 'pre_set_theme_mod_header_image', array( self::class, 'blog_user_update' ) );
		}

		// profile updates for user options
		if ( ! is_user_type_disabled( 'user' ) ) {
			\add_action( 'wp_update_user', array( self::class, 'user_update' ) );
			\add_action( 'updated_user_meta', array( self::class, 'user_meta_update' ), 10, 3 );
			// @todo figure out a feasible way of updating the header image since it's not unique to any user.
		}
	}

	/**
	 * Schedule all ActivityPub schedules.
	 *
	 * @return void
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
	 * Unscedule all ActivityPub schedules.
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
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		$post = get_post( $post );

		// Do not send activities if post is password protected.
		if ( \post_password_required( $post ) ) {
			return;
		}

		// Check if post-type supports ActivityPub.
		$post_types = \get_post_types_by_support( 'activitypub' );
		if ( ! \in_array( $post->post_type, $post_types, true ) ) {
			return;
		}

		$type = false;

		if ( 'publish' === $new_status && 'publish' !== $old_status ) {
			$type = 'Create';
		} elseif ( 'publish' === $new_status ) {
			$type = 'Update';
		} elseif ( 'trash' === $new_status ) {
			$type = 'Delete';
		}

		if ( ! $type ) {
			return;
		}

		\wp_schedule_single_event(
			\time(),
			'activitypub_send_activity',
			array( $post, $type )
		);

		\wp_schedule_single_event(
			\time(),
			sprintf(
				'activitypub_send_%s_activity',
				\strtolower( $type )
			),
			array( $post )
		);
	}

	/**
	 * Schedule Comment Activities
	 *
	 * transition_comment_status()
	 *
	 * @param string     $new_status New comment status.
	 * @param string     $old_status Old comment status.
	 * @param WP_Comment $comment    Comment object.
	 */
	public static function schedule_comment_activity( $new_status, $old_status, $comment ) {
		$comment = get_comment( $comment );

		// Federate only approved comments.
		if ( ! $comment->user_id ) {
			return;
		}

		if (
			'approved' === $new_status &&
			'approved' !== $old_status
		) {
			$type = 'Create';
		} elseif ( 'approved' === $new_status ) {
			$type = 'Update';
		} elseif (
			'trash' === $new_status ||
			'spam' === $new_status
		) {
			$type = 'Delete';
		}

		if ( ! $type ) {
			return;
		}

		\wp_schedule_single_event(
			\time(),
			'activitypub_send_activity',
			array( $comment, $type )
		);

		\wp_schedule_single_event(
			\time(),
			sprintf(
				'activitypub_send_%s_activity',
				\strtolower( $type )
			),
			array( $comment )
		);
	}

	/**
	 * Update followers
	 *
	 * @return void
	 */
	public static function update_followers() {
		$number = 5;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

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
	 * Cleanup followers
	 *
	 * @return void
	 */
	public static function cleanup_followers() {
		$number = 5;

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$number = 50;
		}

		$followers = Followers::get_faulty_followers( $number );

		foreach ( $followers as $follower ) {
			$meta = get_remote_metadata_by_actor( $follower->get_url(), false );

			if ( is_tombstone( $meta ) ) {
				$follower->delete();
			} elseif ( empty( $meta ) || ! is_array( $meta ) || is_wp_error( $meta ) ) {
				if ( $follower->count_errors() >= 5 ) {
					$follower->delete();
				} else {
					Followers::add_error( $follower->get__id(), $meta );
				}
			} else {
				$follower->reset_errors();
			}
		}
	}

	/**
	 * Schedule migration if DB-Version is not up to date.
	 *
	 * @return void
	 */
	public static function schedule_migration() {
		if ( ! \wp_next_scheduled( 'activitypub_schedule_migration' ) && ! Migration::is_latest_version() ) {
			\wp_schedule_single_event( \time(), 'activitypub_schedule_migration' );
		}
	}

	/**
	 * Send a profile update when relevant user meta is updated.
	 *
	 * @param  int    $meta_id Meta ID being updated.
	 * @param  int    $user_id User ID being updated.
	 * @param  string $meta_key Meta key being updated.
	 *
	 * @return void
	 */
	public static function user_meta_update( $meta_id, $user_id, $meta_key ) {
		// don't bother if the user can't publish
		if ( ! \user_can( $user_id, 'publish_posts' ) ) {
			return;
		}
		// the user meta fields that affect a profile.
		$fields = array(
			'activitypub_user_description',
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
	 * @param  int $user_id User ID being updated.
	 *
	 * @return void
	 */
	public static function user_update( $user_id ) {
		// don't bother if the user can't publish
		if ( ! \user_can( $user_id, 'publish_posts' ) ) {
			return;
		}

		self::schedule_profile_update( $user_id );
	}

	/**
	 * Theme mods only have a dynamic filter so we fudge it like this.
	 * @param  mixed $value
	 * @return mixed
	 */
	public static function blog_user_update( $value = null ) {
		self::schedule_profile_update( 0 );
		return $value;
	}

	/**
	 * Send a profile update to all followers. Gets hooked into all relevant options/meta etc.
	 * @param int $user_id  The user ID to update (Could be 0 for Blog-User).
	 */
	public static function schedule_profile_update( $user_id ) {
		\wp_schedule_single_event(
			\time(),
			'activitypub_send_update_profile_activity',
			array( $user_id )
		);
	}
}
