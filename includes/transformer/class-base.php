<?php
namespace Activitypub\Transformer;

use Activitypub\Activity\Activity;

/**
 * WordPress Base Transformer
 *
 * Transformers are responsible for transforming a WordPress objects into different ActivityPub
 * Object-Types or Activities.
 */
abstract class Base {
	/**
	 * The WP_Post object.
	 *
	 * @var
	 */
	protected $object;

	/**
	 * Static function to Transform a WordPress Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param stdClass $object The WP_Post object
	 *
	 * @return void
	 */
	public static function transform( $object ) {
		return new static( $object );
	}

	/**
	 * Base constructor.
	 *
	 * @param stdClass $object
	 */
	public function __construct( $object ) {
		$this->object = $object;
	}

	/**
	 * Transform the WordPress Object into an ActivityPub Object.
	 *
	 * @return Activitypub\Activity\Base_Object
	 */
	abstract public function to_object();

	/**
	 * Transforms the ActivityPub Object to an Activity
	 *
	 * @param string $type The Activity-Type.
	 *
	 * @return \Activitypub\Activity\Activity The Activity.
	 */
	public function to_activity( $type ) {
		$object = $this->to_object();

		$activity = new Activity();
		$activity->set_type( $type );
		$activity->set_object( $object );

		return $activity;
	}

	/**
	 * Returns the ID of the WordPress Object.
	 *
	 * @return int The ID of the WordPress Object
	 */
	abstract public function get_wp_user_id();

	/**
	 * Change the User-ID of the WordPress Post.
	 *
	 * @return int The User-ID of the WordPress Post
	 */
	abstract public function change_wp_user_id( $user_id );
}
