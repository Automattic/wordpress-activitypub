<?php
namespace Activitypub\Handler;

use WP_Error;

use function Activitypub\url_to_commentid;
use function Activitypub\get_remote_metadata_by_actor;

/**
 * Handle Update requests.
 */
class Update {
	/**
	 * Initialize the class, registering WordPress hooks
	 */
	public static function init() {
		\add_action( 'activitypub_inbox_update', array( self::class, 'handle_update' ) );
	}

	/**
	 * Handle "Update" requests
	 *
	 * @param array $activity The JSON "Update" Activity
	 */
	public static function handle_update( $activity ) {
		if (
			! isset( $activity['object'] ) ||
			! isset( $activity['object']['id'] )
		) {
			return new WP_Error(
				'activitypub_no_valid_object',
				__( 'No object id found.', 'activitypub' ),
				array( 'status' => 400 )
			);
		}

		$meta = get_remote_metadata_by_actor( $activity['actor'] );

		//Determine comment_ID
		$object_comment_id = url_to_commentid( \esc_url_raw( $activity['object']['id'] ) );

		if ( ! $object_comment_id ) {
			return;
		}

		//found a local comment id
		$commentdata = \get_comment( $object_comment_id, ARRAY_A );
		$commentdata['comment_author'] = \esc_attr( $meta['name'] ? $meta['name'] : $meta['preferredUsername'] );
		$commentdata['comment_content'] = \wp_filter_kses( $activity['object']['content'] );
		$commentdata['comment_meta']['avatar_url'] = \esc_url_raw( $meta['icon']['url'] );
		$commentdata['comment_meta']['activitypub_published'] = \wp_date( 'Y-m-d H:i:s', strtotime( $activity['object']['published'] ) );
		$commentdata['comment_meta']['activitypub_last_modified'] = \wp_date( 'Y-m-d H:i:s', strtotime( $activity['object']['updated'] ) );

		// disable flood control
		\remove_action( 'check_comment_flood', 'check_comment_flood_db', 10 );

		// do not require email for AP entries
		\add_filter( 'pre_option_require_name_email', '__return_false' );

		$state = \wp_update_comment( $commentdata, true );

		\remove_filter( 'pre_option_require_name_email', '__return_false' );

		// re-add flood control
		\add_action( 'check_comment_flood', 'check_comment_flood_db', 10, 4 );
	}
}
