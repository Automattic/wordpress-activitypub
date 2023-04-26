<?php
namespace Activitypub\Model;

use Activitypub\Collection\Followers;

use function Activitypub\get_remote_metadata_by_actor;

/**
 * ActivityPub Follower Class
 *
 * This Object represents a single Follower.
 * There is no direct reference to a WordPress User here.
 *
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
	 * The Object slug
	 *
	 * This is a requirement of the Term-Meta but will not
	 * be actively used in the ActivityPub context.
	 *
	 * @var string
	 */
	private $slug;

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
	 * Maps the meta fields to the local db fields
	 *
	 * @var array
	 */
	private $map_meta = array(
		'name'              => 'name',
		'preferredUsername' => 'username',
		'inbox'             => 'inbox',
	);

	/**
	 * Constructor
	 *
	 * @param WP_Post $post
	 */
	public function __construct( $actor ) {
		$term = get_term_by( 'name', $actor, Followers::TAXONOMY );

		$this->actor = $actor;

		if ( $term ) {
			$this->id = $term->term_id;
			$this->slug = $term->slug;
			$this->meta = json_decode( $term->meta );
		} else {
			$this->slug = sanitize_title( $actor );
		}
	}

	/**
	 * Magic function to implement getter and setter
	 *
	 * @param string $method
	 * @param string $params
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

	public function get( $attribute ) {
		if ( $this->$attribute ) {
			return $this->$attribute;
		}

		if ( ! $this->id ) {
			$this->$attribute = $this->get_meta_by( $attribute );
			return $this->$attribute;
		}

		$this->$attribute = get_term_meta( $this->id, $attribute, true );
		return $this->$attribute;
	}

	public function get_meta_by( $attribute, $force = false ) {
		$meta = $this->get_meta( $force );

		foreach ( $this->map_meta as $remote => $local ) {
			if ( $attribute === $local && isset( $meta[ $remote ] ) ) {
				return $meta[ $remote ];
			}
		}

		return null;
	}

	public function get_meta( $force = false ) {
		if ( $this->meta && false === (bool) $force ) {
			return $this->meta;
		}

		$remote_data = get_remote_metadata_by_actor( $this->actor );

		if ( ! $remote_data || is_wp_error( $remote_data ) || ! is_array( $remote_data ) ) {
			$remote_data = array();
		}

		$this->meta = $remote_data;

		return $this->meta;
	}

	public function update() {
		$term = wp_update_term(
			$this->id,
			Followers::TAXONOMY,
			array(
				'description' => wp_json_encode( $this->get_meta( true ) ),
			)
		);

		$this->update_term_meta();
	}

	public function save() {
		$term = wp_insert_term(
			$this->actor,
			Followers::TAXONOMY,
			array(
				'slug'        => sanitize_title( $this->get_actor() ),
				'description' => wp_json_encode( $this->get_meta() ),
			)
		);

		$this->id = $term['term_id'];

		$this->update_term_meta();
	}

	public function upsert() {
		if ( $this->id ) {
			$this->update();
		} else {
			$this->save();
		}
	}

	protected function update_term_meta() {
		$meta = $this->get_meta();

		foreach ( $this->map_meta as $remote => $internal ) {
			if ( ! empty( $meta[ $remote ] ) ) {
				update_term_meta( $this->id, $internal, $meta[ $remote ], true );
				$this->$internal = $meta[ $remote ];
			}
		}

		if ( ! empty( $meta['icon']['url'] ) ) {
			update_term_meta( $this->id, 'avatar', $meta['icon']['url'], true );
			$this->avatar = $meta['icon']['url'];
		}

		if ( ! empty( $meta['endpoints']['sharedInbox'] ) ) {
			update_term_meta( $this->id, 'shared_inbox', $meta['endpoints']['sharedInbox'], true );
			$this->shared_inbox = $meta['endpoints']['sharedInbox'];
		} elseif ( ! empty( $meta['inbox'] ) ) {
			update_term_meta( $this->id, 'shared_inbox', $meta['inbox'], true );
			$this->shared_inbox = $meta['inbox'];
		}

		update_term_meta( $this->id, 'updated_at', \strtotime( 'now' ), true );
	}
}
