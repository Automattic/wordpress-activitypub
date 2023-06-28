<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Followers;

/**
 * ActivityPub Follower Class
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
	 * The complete Remote-Profile of the Follower
	 *
	 * @var array
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The complete Remote-Profile of the Follower
	 *
	 * @var array
	 */
	protected $_shared_inbox; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The complete Remote-Profile of the Follower
	 *
	 * @var array
	 */
	protected $_actor; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The latest received error.
	 *
	 * This will only temporary and will saved to $this->errors
	 *
	 * @var string
	 */
	protected $_error; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * A list of errors
	 *
	 * @var array
	 */
	protected $_errors; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Set new Error
	 *
	 * @param mixed $error The latest HTTP-Error.
	 *
	 * @return void
	 */
	public function set_error( $error ) {
		$this->_errors = array();
		$this->_error  = $error;
	}

	/**
	 * Get the errors.
	 *
	 * @return mixed
	 */
	public function get_errors() {
		if ( $this->_errors ) {
			return $this->_errors;
		}

		$this->_errors = get_post_meta( $this->_id, 'errors' );
		return $this->_errors;
	}

	/**
	 * Reset (delete) all errors.
	 *
	 * @return void
	 */
	public function reset_errors() {
		delete_post_meta( $this->_id, 'errors' );
	}

	/**
	 * Count the errors.
	 *
	 * @return int The number of errors.
	 */
	public function count_errors() {
		$errors = $this->get__errors();

		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return count( $errors );
		}

		return 0;
	}

	public function get_url() {
		if ( ! $this->url ) {
			return $this->id;
		}

		return $this->url;
	}

	/**
	 * Return the latest error message.
	 *
	 * @return string The error message.
	 */
	public function get_latest_error_message() {
		$errors = $this->get__errors();

		if ( is_array( $errors ) && ! empty( $errors ) ) {
			return reset( $errors );
		}

		return '';
	}

	/**
	 * Update the current Follower-Object.
	 *
	 * @return void
	 */
	public function update() {
		$this->save();
	}

	/**
	 * Save the current Follower-Object.
	 *
	 * @return void
	 */
	public function save() {
		$args = array(
			'ID'            => $this->get__id(),
			'guid'          => $this->get_id(),
			'post_title'    => $this->get_name(),
			'post_author'   => 0,
			'post_type'     => Followers::POST_TYPE,
			'post_content'  => $this->get_summary(),
			'post_status'   => 'publish',
			'meta_input'    => $this->get_post_meta_input(),
		);

		$post_id = wp_insert_post( $args );
		$this->_id = $post_id;
	}

	/**
	 * Upsert the current Follower-Object.
	 *
	 * @return void
	 */
	public function upsert() {
		if ( $this->_id ) {
			$this->update();
		} else {
			$this->save();
		}
	}

	/**
	 * Delete the current Follower-Object.
	 *
	 * @return void
	 */
	public function delete() {
		wp_delete_post( $this->_id );
	}

	/**
	 * Update the post meta.
	 *
	 * @return void
	 */
	protected function get_post_meta_input() {
		$attributes = array( 'inbox', '_shared_inbox', 'icon', 'preferred_username', '_actor', 'url' );

		$meta_input = array();

		foreach ( $attributes as $attribute ) {
			if ( $this->get( $attribute ) ) {
				$meta_input[ $attribute ] = $this->get( $attribute );
			}
		}

		if ( $this->_error ) {
			if ( is_string( $this->_error ) ) {
				$_error = $this->_error;
			} elseif ( is_wp_error( $this->_error ) ) {
				$_error = $this->_error->get_error_message();
			} else {
				$_error = __( 'Unknown Error or misconfigured Error-Message', 'activitypub' );
			}

			$meta_input['_errors'] = $_error;
		}

		return $meta_input;
	}

	/**
	 * Get the Icon URL (Avatar)
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
	 * Converts an ActivityPub Array to an Follower Object.
	 *
	 * @param array $array The ActivityPub Array.
	 *
	 * @return Activitypub\Model\Follower The Follower Object.
	 */
	public static function from_array( $array ) {
		$object = parent::from_array( $array );
		$object->set__actor( $array );

		global $wpdb;

		$post_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE guid=%s",
				esc_sql( $object->get_id() )
			)
		);

		if ( $post_id ) {
			$post = get_post( $post_id );
			$object->set__id( $post->ID );
		}

		if ( ! empty( $object->get_endpoints()['sharedInbox'] ) ) {
			$object->_shared_inbox = $object->get_endpoints()['sharedInbox'];
		} elseif ( ! empty( $object->get_inbox() ) ) {
			$object->_shared_inbox = $object->get_inbox();
		}

		return $object;
	}

	/**
	 * Convert a Custom-Post-Type input to an Activitypub\Model\Follower.
	 *
	 * @return string The JSON string.
	 *
	 * @return array Activitypub\Model\Follower
	 */
	public static function from_custom_post_type( $post ) {
		$object = new static();

		$object->set__id( $post->ID );
		$object->set_id( $post->guid );
		$object->set_name( $post->post_title );
		$object->set_summary( $post->post_content );
		$object->set_url( get_post_meta( $post->ID, 'url', true ) );
		$object->set_icon( get_post_meta( $post->ID, 'icon', true ) );
		$object->set_preferred_username( get_post_meta( $post->ID, 'preferred_username', true ) );
		$object->set_published( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_published ) ) );
		$object->set_updated( gmdate( 'Y-m-d H:i:s', strtotime( $post->post_modified ) ) );

		return $object;
	}
}
