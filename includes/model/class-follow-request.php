<?php
namespace Activitypub\Model;

use WP_Error;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Users;
use Activitypub\Model\Follower;
use Activitypub\Http;

/**
 * ActivityPub Follow Class
 *
 * This Object represents a Follow object.
 * There is no direct reference to a WordPress User here.
 *
 * @author André Menrath
 *
 * @see https://www.w3.org/TR/activitypub/#follow-activity-inbox
 */
class Follow_Request extends Base_Object {
	const FOLLOW_REQUEST_POST_TYPE = 'ap_follow_request';

	/**
	 * Stores theinternal  WordPress post id of the post of type ap_follow_request
	 *
	 * @var string
	 */
	protected $_id;

	/**
	 * The id/URI of the follower
	 * @var string
	 */
	protected $actor;

	/**
	 * The internal WordPress post id of the follower
	 * @var string
	 */
	protected $_actor;

	/**
	 * @param int $id
	 * @return Follow_Request $follow_request
	 */
	public static function get_from_array( $array ) {
		$follow_request = self::init_from_array( $array );
		if ( ! self::is_valid( $follow_request ) ) {
			return;
		}
		if ( ! $follow_request->get__id() ) {
			$follow_request->set__id( self::get_follow_request_id_by_uri( $follow_request->get_id() ) );
		}
		return $follow_request;
	}

	/**
	 * Retrieve the WordPress post id of the follow request by its id (URI)
	 */
	public static function get_follow_request_id_by_uri( $uri ) {
		global $wpdb;
		return $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE guid=%s", esc_sql( $uri ) ) );
	}


	/**
	 * Check if the follow request is valid which means it fits to the already stored data.
	 *
	 * @param Follow_Request $follow_request The follow request to be checked.
	 * @return bool Whether the follow request is valid.
	 */
	public static function is_valid( $follow_request ) {
		if ( self::class != get_class( $follow_request ) ) {
			return false;
		}
		if ( 'Follow' != $follow_request->get_type() ) {
			return false;
		}

		$id = self::get_follow_request_id_by_uri( $follow_request->get_id() );
		if ( ! $id || is_wp_error( $id ) ) {
			return false;
		}
		if ( self::FOLLOW_REQUEST_POST_TYPE != get_post_type( $id ) ) {
			return false;
		}

		$post = get_post( $id );
		$follower = get_post_parent( $id );
		if ( $follower->guid != $follow_request->get_actor() ) {
			return false;
		}
		return true;
	}

	/**
	 * @param int $id
	 * @return Follow_Request $follow_request
	 */
	public static function from_wp_id( $id ) {
		if ( self::FOLLOW_REQUEST_POST_TYPE != get_post_type( $id ) ) {
			return;
		}
		$post = get_post( $id );

		$follow_request = new static();
		$follow_request->set_id( $post->guid );
		$follow_request->set__id( $post->ID );
		$follow_request->set_type( 'Follow' );

		return $follow_request;
	}

	/**
	 * Save the current Follower-Object.
	 *
	 * @param Follower $follower
	 *
	 * @return Follow_Request|WP_Error The Follow_Request or an WP_Error.
	 */
	public static function save( $follower, $user_id, $activity_id ) {
		$follower_id = $follower->get__id();
		$meta_input = array(
			'activitypub_user_id' => $user_id,
		);

		$args = array(
			'guid'         => $activity_id,
			'post_author'  => 0,
			'post_type'    => self::FOLLOW_REQUEST_POST_TYPE,
			'post_status'  => 'pending',
			'post_parent'  => $follower_id,
			'meta_input'   => $meta_input,
			'mime_type'    => 'text/plain',
		);

		$post_id = wp_insert_post( $args );

		return self::from_wp_id( $post_id );
	}

	/**
	 * Check if the user is allowed to handle this follow request.
	 *
	 * Usually needed for the ajax functions.
	 * @return bool Whether the user is allowed.
	 */
	public function can_handle_follow_request() {
		$target_actor = get_post_meta( $this->get__id(), 'activitypub_user_id' );
		if ( get_current_user_id() == $target_actor || current_user_can( 'manage_options' ) ) {
			return true;
		}
	}

	/**
	 * Reject the follow request
	 */
	public function reject() {
		wp_update_post(
			array(
				'ID' => $this->get__id(),
				'post_status' => 'rejected',
			)
		);
		$this->send_response( 'Reject' );
		$this->delete();
	}

	/**
	 * Approve the follow request
	 */
	public function approve() {
		wp_update_post(
			array(
				'ID' => $this->get__id(),
				'post_status' => 'approved',
			)
		);
		$this->send_response( 'Accept' );
	}

	/**
	 * Delete the follow request
	 *
	 * This should only be called after it has been rejected.
	 */
	public function delete() {
		wp_delete_post( $this->get__id() );
	}

	/**
	 * Prepere the sending of the follow request response and hand it over to the sending handler.
	 */
	public function send_response( $type ) {
		$user_id = get_post_meta( $this->get__id(), 'activitypub_user_id' )[0];
		$user = Users::get_by_id( $user_id );

		$follower_id = wp_get_post_parent_id( $this->get__id() );
		$follower = Follower::init_from_cpt( get_post( $follower_id ) );

		$actor = $follower->get_id();

		$object = array(
			'id'    => $this->get_id(),
			'type'  => $this->get_type(),
			'actor' => $actor,
			'object' => $user,
		);

		do_action( 'activitypub_send_follow_response', $user, $follower, $object, $type );
	}
}
