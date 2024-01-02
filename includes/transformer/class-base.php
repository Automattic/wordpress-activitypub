<?php
namespace Activitypub\Transformer;

use Activitypub\Activity\Activity;
use Activitypub\Activity\Base_Object;

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
	 * @return Base_Object
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
	 * Transform all properties with available get(ter) functions.
	 *
	 * @param Base_Object|object $object
	 *
	 * @return Base_Object|object $object
	 */
	protected function transform_object_properties( $object ) {
		$vars = $object->get_object_var_keys();

		foreach ( $vars as $var ) {
			$getter = 'get_' . $var;

			if ( method_exists( $this, $getter ) ) {
				$value = call_user_func( array( $this, $getter ) );

				if ( isset( $value ) ) {
					$setter = 'set_' . $var;

					call_user_func( array( $object, $setter ), $value );
				}
			}
		}
		return $object;
	}

	/**
	 * Transform the WordPress Object into an ActivityPub Object.
	 *
	 * @return Activitypub\Activity\Base_Object
	 */
	public function to_object() {
		$object = new Base_Object();

		$object = $this->transform_object_properties( $object );

		return $object;
	}

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

		// Use simple Object (only ID-URI) for Like and Announce
		if ( in_array( $type, array( 'Like', 'Announce' ), true ) ) {
			$activity->set_object( $object->get_id() );
		} else {
			$activity->set_object( $object );
			$activity->set__context( array_unique( array_merge( $object->get__context(), $activity->get__context() ), SORT_REGULAR ) );
		}

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
