<?php
/**
 * Base Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use WP_Post;
use WP_Comment;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;
use Activitypub\Collection\Replies;

/**
 * WordPress Base Transformer.
 *
 * Transformers are responsible for transforming a WordPress objects into different ActivityPub
 * Object-Types or Activities.
 */
abstract class Base {
	/**
	 * The WP_Post or WP_Comment object.
	 *
	 * This is the source object of the transformer.
	 *
	 * @var WP_Post|WP_Comment
	 */
	protected $wp_object;

	/**
	 * Static function to Transform a WordPress Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param WP_Post|WP_Comment $wp_object The WordPress object.
	 *
	 * @return Base
	 */
	public static function transform( $wp_object ) {
		return new static( $wp_object );
	}

	/**
	 * Base constructor.
	 *
	 * @param WP_Post|WP_Comment $wp_object The WordPress object.
	 */
	public function __construct( $wp_object ) {
		$this->wp_object = $wp_object;
	}

	/**
	 * Transform all properties with available get(ter) functions.
	 *
	 * @param Base_Object|object $activitypub_object The ActivityPub Object.
	 *
	 * @return Base_Object|object
	 */
	protected function transform_object_properties( $activitypub_object ) {
		$vars = $activitypub_object->get_object_var_keys();

		foreach ( $vars as $var ) {
			$getter = 'get_' . $var;

			if ( method_exists( $this, $getter ) ) {
				$value = call_user_func( array( $this, $getter ) );

				if ( isset( $value ) ) {
					$setter = 'set_' . $var;

					call_user_func( array( $activitypub_object, $setter ), $value );
				}
			}
		}
		return $activitypub_object;
	}

	/**
	 * Transform the item into an ActivityPub Object.
	 *
	 * @return Base_Object|object The ActivityPub Object.
	 */
	public function to_object() {
		$activitypub_object = new Base_Object();

		return $this->transform_object_properties( $activitypub_object );
	}

	/**
	 * Transform the item to an ActivityPub ID.
	 *
	 * @return string The ID of the WordPress Object.
	 */
	public function to_id() {
		return $this->get_id();
	}

	/**
	 * Transforms the ActivityPub Object to an Activity
	 *
	 * @param string $type The Activity-Type.
	 *
	 * @return Activity The Activity.
	 */
	public function to_activity( $type ) {
		$object = $this->to_object();

		$activity = new Activity();
		$activity->set_type( $type );

		// Pre-fill the Activity with data (for example cc and to).
		$activity->set_object( $object );

		// Use simple Object (only ID-URI) for Like and Announce.
		if ( in_array( $type, array( 'Like', 'Announce' ), true ) ) {
			$activity->set_object( $object->get_id() );
		}

		return $activity;
	}

	/**
	 * Get the ID of the WordPress Object.
	 */
	abstract protected function get_id();

	/**
	 * Get the replies Collection.
	 *
	 * @return array The replies collection.
	 */
	public function get_replies() {
		return Replies::get_collection( $this->wp_object );
	}

	/**
	 * Returns the default media type for an Object.
	 *
	 * @return string The media type.
	 */
	public function get_media_type() {
		return 'text/html';
	}

	/**
	 * Returns the ID of the WordPress Object.
	 */
	abstract public function get_wp_user_id();

	/**
	 * Change the User-ID of the WordPress Post.
	 *
	 * @param int $user_id The new user ID.
	 */
	abstract public function change_wp_user_id( $user_id );
}
