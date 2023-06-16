<?php
namespace Activitypub\Model;

use WP_Query;
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
class Follower {
	/**
	 * The Object ID
	 *
	 * @var int
	 */
	private $id;

	/**
	 * The Actor-URL of the Follower
	 *
	 * @var string
	 */
	private $actor;

	/**
	 * The Object Name
	 *
	 * This is the same as the Actor-URL
	 *
	 * @var string
	 */
	private $name;

	/**
	 * The Username
	 *
	 * @var string
	 */
	private $username;

	/**
	 * The Avatar URL
	 *
	 * @var string
	 */
	private $avatar;

	/**
	 * The URL to the Follower
	 */
	private $url;

	/**
	 * The URL to the Followers Inbox
	 *
	 * @var string
	 */
	private $inbox;

	/**
	 * The URL to the Servers Shared-Inbox
	 *
	 * If the Server does not support Shared-Inboxes,
	 * the Inbox will be stored.
	 *
	 * @var string
	 */
	private $shared_inbox;

	/**
	 * The date, the Follower was updated
	 *
	 * @var string untixtimestamp
	 */
	private $updated_at;

	/**
	 * The complete Remote-Profile of the Follower
	 *
	 * @var array
	 */
	private $meta;

	/**
	 * The latest received error.
	 *
	 * This will only temporary and will saved to $this->errors
	 *
	 * @var string
	 */
	private $error;

	/**
	 * A list of errors
	 *
	 * @var array
	 */
	private $errors;

	/**
	 * The WordPress User ID, or 0 for whole site.
	 * @var int
	 */
	private $user_id;

	/**
	 * Maps the meta fields to the local db fields
	 *
	 * @var array
	 */
	private $map_meta = array(
		'name'              => 'name',
		'preferredUsername' => 'username',
		'inbox'             => 'inbox',
		'url'               => 'url',
	);

	/**
	 * Constructor
	 *
	 * @param string|WP_Post $actor The Actor-URL or WP_Post Object.
	 * @param int            $user_id The WordPress User ID. 0 Represents the whole site.
	 */
	public function __construct( $actor ) {
		$post = null;

		if ( \is_a( $actor, 'WP_Post' ) ) {
			$post = $actor;
		} else {
			global $wpdb;

			$post_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE guid=%s",
					esc_sql( $actor )
				)
			);

			if ( $post_id ) {
				$post = get_post( $post_id );
			} else {
				$this->actor = $actor;
			}
		}

		if ( $post ) {
			$this->id         = $post->ID;
			$this->actor      = $post->guid;
			$this->updated_at = $post->post_modified;
		}
	}

	/**
	 * Magic function to implement getter and setter
	 *
	 * @param string $method The method name.
	 * @param string $params The method params.
	 *
	 * @return void
	 */
	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			if ( empty( $this->$var ) ) {
				return $this->get( $var );
			}
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'set', 3 ) === 0 ) {
			$this->$var = $params[0];
		}
	}

	/**
	 * Magic function to return the Actor-URL when the Object is used as a string
	 *
	 * @return string
	 */
	public function __toString() {
		return $this->get_actor();
	}

	/**
	 * Prefill the Object with the meta data.
	 *
	 * @param array $meta The meta data.
	 *
	 * @return void
	 */
	public function from_meta( $meta ) {
		$this->meta = $meta;

		foreach ( $this->map_meta as $remote => $internal ) {
			if ( ! empty( $meta[ $remote ] ) ) {
				$this->$internal = $meta[ $remote ];
			}
		}

		if ( ! empty( $meta['icon']['url'] ) ) {
			$this->avatar = $meta['icon']['url'];
		}

		if ( ! empty( $meta['endpoints']['sharedInbox'] ) ) {
			$this->shared_inbox = $meta['endpoints']['sharedInbox'];
		} elseif ( ! empty( $meta['inbox'] ) ) {
			$this->shared_inbox = $meta['inbox'];
		}

		$this->updated_at = \time();
	}

	/**
	 * Get the data by the given attribute
	 *
	 * @param string $attribute The attribute name.
	 *
	 * @return mixed The attribute value.
	 */
	public function get( $attribute ) {
		if ( ! is_null( $this->$attribute ) ) {
			return $this->$attribute;
		}

		$attribute_value = get_post_meta( $this->id, $attribute, true );

		if ( $attribute_value ) {
			$this->$attribute = $attribute_value;
			return $attribute_value;
		}

		$attribute_value = $this->get_meta_by( $attribute );
		if ( $attribute_value ) {
			$this->$attribute = $attribute_value;
			return $attribute_value;
		}

		return null;
	}

	/**
	 * Get a URL for the follower. Creates one out of the actor if no URL was set.
	 */
	public function get_url() {
		if ( $this->get( 'url' ) ) {
			return $this->get( 'url' );
		}
		$actor = $this->get_actor();
		// normalize
		$actor = ltrim( $actor, '@' );
		$parts = explode( '@', $actor );
		return sprintf( 'https://%s/@%s', $parts[1], $parts[0] );
	}

	/**
	 * Set new Error
	 *
	 * @param mixed $error The latest HTTP-Error.
	 *
	 * @return void
	 */
	public function set_error( $error ) {
		$this->errors = array();
		$this->error  = $error;
	}

	/**
	 * Get the errors.
	 *
	 * @return mixed
	 */
	public function get_errors() {
		if ( $this->errors ) {
			return $this->errors;
		}

		$this->errors = get_post_meta( $this->id, 'errors' );
		return $this->errors;
	}

	/**
	 * Reset (delete) all errors.
	 *
	 * @return void
	 */
	public function reset_errors() {
		delete_post_meta( $this->id, 'errors' );
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
	 * Get the meta data by the given attribute.
	 *
	 * @param string $attribute The attribute name.
	 *
	 * @return mixed $attribute The attribute value.
	 */
	public function get_meta_by( $attribute ) {
		$meta = $this->get_meta();
		if ( ! is_array( $meta ) ) {
			return null;
		}
		// try mapped data (see $this->map_meta)
		foreach ( $this->map_meta as $remote => $local ) {
			if ( $attribute === $local && isset( $meta[ $remote ] ) ) {
				return $meta[ $remote ];
			}
		}

		return null;
	}

	/**
	 * Get the meta data.
	 *
	 * @return array $meta The meta data.
	 */
	public function get_meta() {
		if ( $this->meta ) {
			return $this->meta;
		}

		return null;
	}

	/**
	 * Update the current Follower-Object.
	 *
	 * @return void
	 */
	public function update() {
		$this->updated_at = \time();
		$this->save();
	}

	/**
	 * Save the current Follower-Object.
	 *
	 * @return void
	 */
	public function save() {
		$args = array(
			'ID'            => $this->id,
			'guid'          => $this->actor,
			'post_title'    => $this->get_name(),
			'post_author'   => 0,
			'post_type'     => Followers::POST_TYPE,
			'post_content'  => wp_json_encode( $this->meta ),
			'post_status'   => 'publish',
			'post_modified' => gmdate( 'Y-m-d H:i:s', $this->updated_at ),
			'meta_input'    => $this->get_post_meta_input(),
		);
		$post_id = wp_insert_post( $args );
		$this->id = $post_id;
	}

	/**
	 * Upsert the current Follower-Object.
	 *
	 * @return void
	 */
	public function upsert() {
		if ( $this->id ) {
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
		wp_delete_post( $this->id );
	}

	/**
	 * Update the post meta.
	 *
	 * @return void
	 */
	protected function get_post_meta_input() {
		$attributes = array( 'inbox', 'shared_inbox', 'avatar', 'name', 'username' );

		$meta_input = array();

		foreach ( $attributes as $attribute ) {
			if ( $this->get( $attribute ) ) {
				$meta_input[ $attribute ] = $this->get( $attribute );
			}
		}

		if ( $this->error ) {
			if ( is_string( $this->error ) ) {
				$error = $this->error;
			} elseif ( is_wp_error( $this->error ) ) {
				$error = $this->error->get_error_message();
			} else {
				$error = __( 'Unknown Error or misconfigured Error-Message', 'activitypub' );
			}

			$meta_input['errors'] = array( $error );
		}

		return $meta_input;
	}
}
