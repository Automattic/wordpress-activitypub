<?php
/**
 * Follower class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Followers;
use Activitypub\Collection\Remote_Actors;

use function Activitypub\extract_name_from_uri;

/**
 * ActivityPub Follower Class.
 *
 * This Object represents a single Follower.
 * There is no direct reference to a WordPress User here.
 *
 * @author Matt Wiebe
 * @author Matthias Pfefferle
 *
 * @deprecated 7.0.0
 * @see https://www.w3.org/TR/activitypub/#follow-activity-inbox
 *
 * @method int           get__id()       Gets the post ID of the follower record.
 * @method string[]|null get_image()     Gets the follower's profile image data.
 * @method string|null   get_inbox()     Gets the follower's ActivityPub inbox URL.
 * @method string[]|null get_endpoints() Gets the follower's ActivityPub endpoints.
 *
 * @method Follower set__id( int $id )                Sets the post ID of the follower record.
 * @method Follower set_id( string $guid )            Sets the follower's GUID.
 * @method Follower set_name( string $name )          Sets the follower's display name.
 * @method Follower set_summary( string $summary )    Sets the follower's bio/summary.
 * @method Follower set_published( string $datetime ) Sets the follower's published datetime in ISO 8601 format.
 * @method Follower set_updated( string $datetime )   Sets the follower's last updated datetime in ISO 8601 format.
 */
class Follower extends Actor {
	/**
	 * The complete Remote-Profile of the Follower.
	 *
	 * @var int
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Constructor.
	 *
	 * @deprecated Use Actor instead.
	 */
	public function __construct() {
		\_deprecated_class( __CLASS__, '7.0.0', Actor::class );
	}

	/**
	 * Get the errors.
	 *
	 * @return mixed
	 */
	public function get_errors() {
		return Remote_Actors::get_errors( $this->_id );
	}

	/**
	 * Clear the errors for the current Follower.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function clear_errors() {
		return Remote_Actors::clear_errors( $this->_id );
	}

	/**
	 * Get the Summary.
	 *
	 * @return string The Summary.
	 */
	public function get_summary() {
		if ( isset( $this->summary ) ) {
			return $this->summary;
		}

		return '';
	}

	/**
	 * Getter for URL attribute.
	 *
	 * Falls back to ID, if no URL is set. This is relevant for
	 * Platforms like Lemmy, where the ID is the URL.
	 *
	 * @return string The URL.
	 */
	public function get_url() {
		if ( $this->url ) {
			return $this->url;
		}

		return $this->id;
	}

	/**
	 * Reset (delete) all errors.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function reset_errors() {
		return Remote_Actors::clear_errors( $this->_id );
	}

	/**
	 * Count the errors.
	 *
	 * @return int The number of errors.
	 */
	public function count_errors() {
		return Remote_Actors::count_errors( $this->_id );
	}

	/**
	 * Return the latest error message.
	 *
	 * @return string The error message.
	 */
	public function get_latest_error_message() {
		$errors = $this->get_errors();

		if ( \is_array( $errors ) && ! empty( $errors ) ) {
			return \reset( $errors );
		}

		return '';
	}

	/**
	 * Update the current Follower object.
	 */
	public function update() {
		$this->save();
	}

	/**
	 * Validate the current Follower object.
	 *
	 * @return boolean True if the verification was successful.
	 */
	public function is_valid() {
		// The minimum required attributes.
		$required_attributes = array(
			'id',
			'preferredUsername',
			'inbox',
			'publicKey',
			'publicKeyPem',
		);

		foreach ( $required_attributes as $attribute ) {
			if ( ! $this->get( $attribute ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Save the current Follower object.
	 *
	 * @return int|\WP_Error The post ID or an WP_Error.
	 */
	public function save() {
		if ( ! $this->is_valid() ) {
			return new \WP_Error( 'activitypub_invalid_follower', __( 'Invalid Follower', 'activitypub' ), array( 'status' => 400 ) );
		}

		$id = Remote_Actors::upsert( $this );
		if ( \is_wp_error( $id ) ) {
			return $id;
		}

		$this->set__id( $id );
		return $id;
	}

	/**
	 * Upsert the current Follower object.
	 *
	 * @return int|\WP_Error The post ID or an WP_Error.
	 */
	public function upsert() {
		return $this->save();
	}

	/**
	 * Delete the current Follower object.
	 *
	 * Beware that this os deleting a Follower for ALL users!!!
	 *
	 * To delete only the User connection (unfollow)
	 *
	 * @see \Activitypub\Rest\Followers::remove_follower()
	 */
	public function delete() {
		Followers::remove_follower( $this->_id, $this->get_id() );
	}

	/**
	 * Get the icon.
	 *
	 * Sets a fallback to better handle API and HTML outputs.
	 *
	 * @return string[] The icon.
	 */
	public function get_icon() {
		if ( isset( $this->icon['url'] ) ) {
			return $this->icon;
		}

		return array(
			'type'      => 'Image',
			'mediaType' => 'image/jpeg',
			'url'       => ACTIVITYPUB_PLUGIN_URL . 'assets/img/mp.jpg',
		);
	}

	/**
	 * Get Name.
	 *
	 * Tries to extract a name from the URL or ID if not set.
	 *
	 * @return string The name.
	 */
	public function get_name() {
		if ( $this->name ) {
			return $this->name;
		} elseif ( $this->preferred_username ) {
			return $this->preferred_username;
		}

		return $this->extract_name_from_uri();
	}

	/**
	 * The preferred Username.
	 *
	 * Tries to extract a name from the URL or ID if not set.
	 *
	 * @return string The preferred Username.
	 */
	public function get_preferred_username() {
		if ( $this->preferred_username ) {
			return $this->preferred_username;
		}

		return $this->extract_name_from_uri();
	}

	/**
	 * Get the Icon URL (Avatar).
	 *
	 * @return string The URL to the Avatar.
	 */
	public function get_icon_url() {
		$icon = $this->get_icon();

		if ( ! $icon ) {
			return '';
		}

		if ( \is_array( $icon ) ) {
			return $icon['url'];
		}

		return $icon;
	}

	/**
	 * Get the Icon URL (Avatar).
	 *
	 * @return string The URL to the Avatar.
	 */
	public function get_image_url() {
		$image = $this->get_image();

		if ( ! $image ) {
			return '';
		}

		if ( \is_array( $image ) ) {
			return $image['url'];
		}

		return $image;
	}

	/**
	 * Get the shared inbox, with a fallback to the inbox.
	 *
	 * @return string|null The URL to the shared inbox, the inbox or null.
	 */
	public function get_shared_inbox() {
		if ( ! empty( $this->get_endpoints()['sharedInbox'] ) ) {
			return $this->get_endpoints()['sharedInbox'];
		} elseif ( ! empty( $this->get_inbox() ) ) {
			return $this->get_inbox();
		}

		return null;
	}

	/**
	 * Convert a Custom-Post-Type input to an Activitypub\Model\Follower.
	 *
	 * @param \WP_Post $post The post object.
	 * @return Follower|false The Follower object or false on failure.
	 */
	public static function init_from_cpt( $post ) {
		if ( empty( $post->post_content ) ) {
			$json = \get_post_meta( $post->ID, '_activitypub_actor_json', true );
		} else {
			$json = $post->post_content;
		}

		/* @var Follower $object Follower object. */
		$object = self::init_from_json( $json );

		if ( \is_wp_error( $object ) ) {
			return false;
		}

		$object->set__id( $post->ID );
		$object->set_id( $post->guid );
		$object->set_name( $post->post_title );
		$object->set_summary( $post->post_excerpt );
		$object->set_published( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_date ) ) );
		$object->set_updated( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_modified ) ) );

		return $object;
	}

	/**
	 * Infer a shortname from the Actor ID or URL. Used only for fallbacks,
	 * we will try to use what's supplied.
	 *
	 * @return string Hopefully the name of the Follower.
	 */
	protected function extract_name_from_uri() {
		// prefer the URL, but fall back to the ID.
		if ( $this->url ) {
			$uri = $this->url;
		} else {
			$uri = $this->id;
		}

		return extract_name_from_uri( $uri );
	}
}
