<?php
/**
 * Actor scheduler class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Scheduler;

use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;

use function Activitypub\add_to_outbox;
use function Activitypub\is_user_type_disabled;

/**
 * Post scheduler class.
 */
class Actor {
	/**
	 * Initialize the class, registering WordPress hooks.
	 */
	public static function init() {
		// Profile updates for blog options.
		if ( ! is_user_type_disabled( 'blog' ) ) {
			\add_action( 'update_option_site_icon', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_blogdescription', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_blogname', array( self::class, 'blog_user_update' ) );
			\add_action( 'add_option_activitypub_header_image', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_activitypub_header_image', array( self::class, 'blog_user_update' ) );
			\add_action( 'add_option_activitypub_blog_identifier', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_activitypub_blog_identifier', array( self::class, 'blog_user_update' ) );
			\add_action( 'add_option_activitypub_blog_description', array( self::class, 'blog_user_update' ) );
			\add_action( 'update_option_activitypub_blog_description', array( self::class, 'blog_user_update' ) );
			\add_filter( 'pre_set_theme_mod_custom_logo', array( self::class, 'blog_user_update' ) );
			\add_filter( 'pre_set_theme_mod_header_image', array( self::class, 'blog_user_update' ) );
		}

		// Profile updates for user options.
		if ( ! is_user_type_disabled( 'user' ) ) {
			\add_action( 'profile_update', array( self::class, 'user_update' ) );
			\add_action( 'updated_user_meta', array( self::class, 'user_meta_update' ), 10, 3 );
			// @todo figure out a feasible way of updating the header image since it's not unique to any user.
		}

		\add_action( 'transition_post_status', array( self::class, 'schedule_post_activity' ), 33, 3 );
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
		self::schedule_profile_update( Actors::BLOG_USER_ID );
		return $value;
	}

	/**
	 * Schedule Activities.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public static function schedule_post_activity( $new_status, $old_status, $post ) {
		if ( Extra_Fields::USER_POST_TYPE === $post->post_type ) {
			self::schedule_profile_update( $post->post_author );
		} elseif ( Extra_Fields::BLOG_POST_TYPE === $post->post_type ) {
			self::schedule_profile_update( Actors::BLOG_USER_ID );
		}
	}

	/**
	 * Send a profile update to all followers. Gets hooked into all relevant options/meta etc.
	 *
	 * @param int $user_id  The user ID to update (Could be 0 for Blog-User).
	 */
	public static function schedule_profile_update( $user_id ) {
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}

		add_to_outbox( Actors::get_by_id( $user_id ), 'Update', $user_id );
	}
}
