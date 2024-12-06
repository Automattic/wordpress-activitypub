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
use Activitypub\Collection\Replies;
use Activitypub\Activity\Base_Object;

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
	 * @var WP_Post|WP_Comment|Base_Object|string|array
	 */
	protected $item;

	/**
	 * The WP_Post or WP_Comment object.
	 *
	 * @deprecated version 5.0.0
	 *
	 * @var WP_Post|WP_Comment
	 */
	protected $wp_object;

	/**
	 * Static function to Transform a WordPress Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param WP_Post|WP_Comment|Base_Object|string|array $item The item that should be transformed.
	 *
	 * @return Base
	 */
	public static function transform( $item ) {
		return new static( $item );
	}

	/**
	 * Base constructor.
	 *
	 * @param WP_Post|WP_Comment|Base_Object|string|array $item The item that should be transformed.
	 */
	public function __construct( $item ) {
		$this->item      = $item;
		$this->wp_object = $item;
	}

	/**
	 * Transform all properties with available get(ter) functions.
	 *
	 * @param Base_Object $activitypub_object The ActivityPub Object.
	 *
	 * @return Base_Object The transformed ActivityPub Object.
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
	 * Transform the WordPress Object into an ActivityPub Object.
	 *
	 * @return Base_Object|object The ActivityPub Object.
	 */
	public function to_object() {
		$activitypub_object = new Base_Object();

		return $this->transform_object_properties( $activitypub_object );
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
	 * Returns a generic locale based on the Blog settings.
	 *
	 * @return string The locale of the blog.
	 */
	protected function get_locale() {
		$lang = \strtolower( \strtok( \get_locale(), '_-' ) );

		/**
		 * Filter the locale of the post.
		 *
		 * @param string $lang    The locale of the post.
		 * @param mixed  $item    The post object.
		 *
		 * @return string The filtered locale of the post.
		 */
		return apply_filters( 'activitypub_locale', $lang, $this->item );
	}

	/**
	 * Returns the recipient of the post.
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-to
	 *
	 * @return array The recipient URLs of the post.
	 */
	protected function get_to() {
		return array(
			'https://www.w3.org/ns/activitystreams#Public',
		);
	}

	/**
	 * Get the replies Collection.
	 */
	public function get_replies() {
		return Replies::get_collection( $this->item );
	}
}
