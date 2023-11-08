<?php
namespace Activitypub\Handler;

use function Activitypub\is_activity_public;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * Handle Create requests
 */
class Create {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_create', array( self::class, 'handle_create' ), 10, 2 );
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $activity  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_create( $activity, $user_id ) {
		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		if ( ! isset( $activity['object']['inReplyTo'] ) ) {
			return;
		}

		// check if Activity is public or not
		if ( ! is_activity_public( $activity ) ) {
			// @todo maybe send email
			return;
		}

		$comment_post_id = \url_to_postid( $activity['object']['inReplyTo'] );

		// save only replys and reactions
		if ( ! $comment_post_id ) {
			return false;
		}

		$commentdata = array(
			'comment_post_ID' => $comment_post_id,
			'comment_author' => \esc_attr( $meta['name'] ),
			'comment_author_url' => \esc_url_raw( $activity['actor'] ),
			'comment_content' => \wp_filter_kses( $activity['object']['content'] ),
			'comment_type' => 'comment',
			'comment_author_email' => '',
			'comment_parent' => 0,
			'comment_meta' => array(
				'source_id' => \esc_url_raw( $activity['object']['id'] ),
				'source_url' => \esc_url_raw( $activity['object']['url'] ),
				'avatar_url' => \esc_url_raw( $meta['icon']['url'] ),
				'protocol' => 'activitypub',
			),
		);

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );

		// No nonce possible for this submission route
		\add_filter(
			'akismet_comment_nonce',
			function() {
				return 'inactive';
			}
		);

		$state = \wp_new_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		\do_action( 'activitypub_handled_create', $activity, $user_id, $state, $commentdata );
	}

	/**
	 * Handles "Create" requests
	 *
	 * @param  array $object  The activity-object
	 * @param  int   $user_id The id of the local blog-user
	 */
	public static function handle_create_alt( $object, $user_id ) {
		$commentdata = self::convert_object_to_comment_data( $object, $user_id );
		if ( ! $commentdata ) {
			return false;
		}

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );

		// No nonce possible for this submission route
		\add_filter(
			'akismet_comment_nonce',
			function() {
				return 'inactive';
			}
		);

		$state = \wp_new_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );

		do_action( 'activitypub_handled_create', $object, $user_id, $state, $commentdata );
	}
}
