<?php
namespace Activitypub\Model;

use Activitypub\Collection\Followers;

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
		$this->actor = $actor;

		$term = get_term_by( 'name', $actor, Followers::TAXONOMY );

		if ( $term ) {
			$this->id = $term->term_id;
			$this->slug = $term->slug;
			$this->meta = json_decode( $term->meta );
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

		$this->updated_at = \strtotime( 'now' );
	}

	public function get( $attribute ) {
		if ( $this->$attribute ) {
			return $this->$attribute;
		}

		$attribute = get_term_meta( $this->id, $attribute, true );
		if ( $attribute ) {
			$this->$attribute = $attribute;
			return $attribute;
		}

		$attribute = $this->get_meta_by( $attribute );
		if ( $attribute ) {
			$this->$attribute = $attribute;
			return $attribute;
		}

		return null;
	}

	public function get_meta_by( $attribute ) {
		$meta = $this->get_meta();

		// try mapped data ($this->map_meta)
		foreach ( $this->map_meta as $remote => $local ) {
			if ( $attribute === $local && isset( $meta[ $remote ] ) ) {
				return $meta[ $remote ];
			}
		}

		// try ActivityPub attribtes
		if ( ! empty( $this->map_meta[ $attribute ] ) ) {
			return $this->map_meta[ $attribute ];
		}

		return null;
	}

	public function get_meta() {
		if ( $this->meta ) {
			return $this->meta;
		}

		return null;
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
		$attributes = array( 'inbox', 'shared_inbox', 'avatar', 'updated_at', 'name', 'username' );

		foreach ( $attributes as $attribute ) {
			if ( $this->get( $attribute ) ) {
				update_term_meta( $this->id, $attribute, $this->get( $attribute ), true );
			}
		}
	}
}
