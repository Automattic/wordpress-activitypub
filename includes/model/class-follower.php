<?php
/**
 * Follower class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use WP_Error;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Follower Class.
 *
 * This Object represents a single Follower.
 * There is no direct reference to a WordPress User here.
 *
 * @author Matt Wiebe
 * @author Matthias Pfefferle
 *
 * @see https://www.w3.org/TR/activitypub/#follow-activity-inbox
 */
class Follower extends Actor {
	/**
	 * The complete Remote-Profile of the Follower.
	 *
	 * @var int
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Get the errors.
	 *
	 * @return mixed
	 */
	public function get_errors() {
		return get_post_meta( $this->_id, '_activitypub_errors', false );
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
	 */
	public function reset_errors() {
		delete_post_meta( $this->_id, '_activitypub_errors' );
	}

	/**
	 * Count the errors.
	 *
	 * @return int The number of errors.
	 */
	public function count_errors() {
		$errors = $this->get_errors();

		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return count( $errors );
		}

		return 0;
	}

	/**
	 * Return the latest error message.
	 *
	 * @return string The error message.
	 */
	public function get_latest_error_message() {
		$errors = $this->get_errors();

		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return reset( $errors );
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
	 * @return int|WP_Error The post ID or an WP_Error.
	 */
	public function save() {
		if ( ! $this->is_valid() ) {
			return new WP_Error( 'activitypub_invalid_follower', __( 'Invalid Follower', 'activitypub' ), array( 'status' => 400 ) );
		}

		if ( ! $this->get__id() ) {
			global $wpdb;

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE guid=%s",
					esc_sql( $this->get_id() )
				)
			);

			if ( $post_id ) {
				$post = get_post( $post_id );
				$this->set__id( $post->ID );
			}
		}

		$post_id = $this->get__id();

		$args = array(
			'ID'           => $post_id,
			'guid'         => esc_url_raw( $this->get_id() ),
			'post_title'   => wp_strip_all_tags( sanitize_text_field( $this->get_name() ) ),
			'post_author'  => 0,
			'post_type'    => Followers::POST_TYPE,
			'post_name'    => esc_url_raw( $this->get_id() ),
			'post_excerpt' => sanitize_text_field( wp_kses( $this->get_summary(), 'user_description' ) ),
			'post_status'  => 'publish',
			'meta_input'   => $this->get_post_meta_input(),
		);

		if ( ! empty( $post_id ) ) {
			// If this is an update, prevent the "followed" date from being overwritten by the current date.
			$post                  = get_post( $post_id );
			$args['post_date']     = $post->post_date;
			$args['post_date_gmt'] = $post->post_date_gmt;
		}

		$post_id   = wp_insert_post( $args );
		$this->_id = $post_id;

		return $post_id;
	}

	/**
	 * Upsert the current Follower object.
	 *
	 * @return int|WP_Error The post ID or an WP_Error.
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
		wp_delete_post( $this->_id );
	}

	/**
	 * Update the post meta.
	 */
	protected function get_post_meta_input() {
		$meta_input                            = array();
		$meta_input['_activitypub_inbox']      = $this->get_shared_inbox();
		$meta_input['_activitypub_actor_json'] = $this->to_json();

		return $meta_input;
	}

	/**
	 * Get the icon.
	 *
	 * Sets a fallback to better handle API and HTML outputs.
	 *
	 * @return array The icon.
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

		if ( is_array( $icon ) ) {
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

		if ( is_array( $image ) ) {
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
	 * @return \Activitypub\Activity\Base_Object|WP_Error
	 */
	public static function init_from_cpt( $post ) {
		$actor_json = get_post_meta( $post->ID, '_activitypub_actor_json', true );
		$object     = self::init_from_json( $actor_json );
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
			$name = $this->url;
		} else {
			$name = $this->id;
		}

		if ( \filter_var( $name, FILTER_VALIDATE_URL ) ) {
			$name = \rtrim( $name, '/' );
			$path = \wp_parse_url( $name, PHP_URL_PATH );

			if ( $path ) {
				if ( \strpos( $name, '@' ) !== false ) {
					// Expected: https://example.com/@user (default URL pattern).
					$name = \preg_replace( '|^/@?|', '', $path );
				} else {
					// Expected: https://example.com/users/user (default ID pattern).
					$parts = \explode( '/', $path );
					$name  = \array_pop( $parts );
				}
			}
		} elseif (
			\is_email( $name ) ||
			\strpos( $name, 'acct' ) === 0 ||
			\strpos( $name, '@' ) === 0
		) {
			// Expected: user@example.com or acct:user@example (WebFinger).
			$name  = \ltrim( $name, '@' );
			$name  = \ltrim( $name, 'acct:' );
			$parts = \explode( '@', $name );
			$name  = $parts[0];
		}

		return $name;
	}
}
