<?php
/**
 * Base Transformer Class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

use Activitypub\Activity\Activity;
use Activitypub\Collection\Actors;
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
	 * @var \WP_Post|\WP_Comment|Base_Object|string|array|\WP_Term
	 */
	protected $item;

	/**
	 * The WP_Post or WP_Comment object.
	 *
	 * @deprecated version 5.0.0
	 *
	 * @var \WP_Post|\WP_Comment
	 */
	protected $wp_object;

	/**
	 * The content visibility.
	 *
	 * @var string
	 */
	protected $content_visibility;

	/**
	 * Static function to Transform a WordPress Object.
	 *
	 * This helps to chain the output of the Transformer.
	 *
	 * @param \WP_Post|\WP_Comment|Base_Object|string|array|\WP_term $item The item that should be transformed.
	 *
	 * @return Base
	 */
	public static function transform( $item ) {
		return new static( $item );
	}

	/**
	 * Base constructor.
	 *
	 * @param \WP_Post|\WP_Comment|Base_Object|string|array|\WP_Term $item The item that should be transformed.
	 */
	public function __construct( $item ) {
		$this->item      = $item;
		$this->wp_object = $item;
	}

	/**
	 * Transform all properties with available get(ter) functions.
	 *
	 * @param Base_Object $activity_object The ActivityPub Object.
	 *
	 * @return Base_Object|\WP_Error The transformed ActivityPub Object or WP_Error on failure.
	 */
	protected function transform_object_properties( $activity_object ) {
		if ( ! $activity_object || \is_wp_error( $activity_object ) ) {
			return $activity_object;
		}

		$vars = $activity_object->get_object_var_keys();

		foreach ( $vars as $var ) {
			$getter = 'get_' . $var;

			if ( \method_exists( $this, $getter ) ) {
				$value = \call_user_func( array( $this, $getter ) );

				if ( null !== $value ) {
					$setter = 'set_' . $var;

					/**
					 * Filter the value before it is set to the Activity-Object `$activity_object`.
					 *
					 * @param mixed $value The value that should be set.
					 * @param mixed $item  The Object.
					 */
					$value = \apply_filters( "activitypub_transform_{$setter}", $value, $this->item );

					/**
					 * Filter the value before it is set to the Activity-Object `$activity_object`.
					 *
					 * @param mixed  $value The value that should be set.
					 * @param string $var   The variable name.
					 * @param mixed  $item  The Object.
					 */
					$value = \apply_filters( 'activitypub_transform_set', $value, $var, $this->item );

					\call_user_func( array( $activity_object, $setter ), $value );
				}
			}
		}

		return $activity_object;
	}

	/**
	 * Transform the item into an ActivityPub Object.
	 *
	 * @return Base_Object|object The Activity-Object.
	 */
	public function to_object() {
		$activity_object = new Base_Object();
		$activity_object = $this->transform_object_properties( $activity_object );

		if ( \is_wp_error( $activity_object ) ) {
			return $activity_object;
		}

		return $this->set_audience( $activity_object );
	}

	/**
	 * Get the content visibility.
	 *
	 * @return string The content visibility.
	 */
	public function get_content_visibility() {
		if ( ! $this->content_visibility ) {
			return ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC;
		}

		return $this->content_visibility;
	}

	/**
	 * Set the content visibility.
	 *
	 * @param string $content_visibility The content visibility.
	 */
	public function set_content_visibility( $content_visibility ) {
		$this->content_visibility = $content_visibility;

		return $this;
	}

	/**
	 * Set the audience.
	 *
	 * @param Base_Object $activity_object The ActivityPub Object.
	 *
	 * @return Base_Object The ActivityPub Object.
	 */
	protected function set_audience( $activity_object ) {
		$public = 'https://www.w3.org/ns/activitystreams#Public';
		$actor  = Actors::get_by_resource( $this->get_attributed_to() );
		if ( ! $actor || is_wp_error( $actor ) ) {
			$followers = null;
		} else {
			$followers = $actor->get_followers();
		}
		$mentions = array_values( $this->get_mentions() );

		switch ( $this->get_content_visibility() ) {
			case ACTIVITYPUB_CONTENT_VISIBILITY_PUBLIC:
				$activity_object->add_to( $public );
				$activity_object->add_cc( $followers );
				$activity_object->add_cc( $mentions );
				break;
			case ACTIVITYPUB_CONTENT_VISIBILITY_QUIET_PUBLIC:
				$activity_object->add_to( $followers );
				$activity_object->add_to( $mentions );
				$activity_object->add_cc( $public );
				break;
			case ACTIVITYPUB_CONTENT_VISIBILITY_PRIVATE:
				$activity_object->add_to( $mentions );
		}

		return $activity_object;
	}

	/**
	 * Transform the item to an ActivityPub ID.
	 *
	 * @return string The ID of the WordPress Object.
	 */
	public function to_id() {
		/* @var Attachment|Comment|Json|Post|User $this Object transformer. */
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
		if ( 'Like' === $type ) {
			$activity->set_object( $object->get_id() );
		}

		return $activity;
	}

	/**
	 * Returns a generic locale based on the Blog settings.
	 *
	 * @return string The locale of the blog.
	 */
	protected function get_locale() {
		$lang = \strtolower( \strtok( \get_locale(), '_-' ) );

		if ( $this->item instanceof \WP_Post ) {
			/**
			 * Deprecates the `activitypub_post_locale` filter.
			 *
			 * @param string $lang The locale of the post.
			 * @param mixed  $item The post object.
			 *
			 * @return string The filtered locale of the post.
			 */
			$lang = apply_filters_deprecated(
				'activitypub_post_locale',
				array(
					$lang,
					$this->item->ID,
					$this->item,
				),
				'5.4.0',
				'activitypub_locale',
				'Use the `activitypub_locale` filter instead.'
			);
		}

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
	 * Returns the default media type for an Object.
	 *
	 * @return string The media type.
	 */
	public function get_media_type() {
		return 'text/html';
	}

	/**
	 * Returns the content map for the post.
	 *
	 * @return array|null The content map for the post or null if not set.
	 */
	protected function get_content_map() {
		if ( ! \method_exists( $this, 'get_content' ) || ! $this->get_content() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_content(),
		);
	}

	/**
	 * Returns the name map for the post.
	 *
	 * @return array|null The name map for the post or null if not set.
	 */
	protected function get_name_map() {
		if ( ! \method_exists( $this, 'get_name' ) || ! $this->get_name() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_name(),
		);
	}

	/**
	 * Returns the summary map for the post.
	 *
	 * @return array|null The summary map for the post or null if not set.
	 */
	protected function get_summary_map() {
		if ( ! \method_exists( $this, 'get_summary' ) || ! $this->get_summary() ) {
			return null;
		}

		return array(
			$this->get_locale() => $this->get_summary(),
		);
	}

	/**
	 * Returns the tags for the post.
	 *
	 * @return array The tags for the post.
	 */
	protected function get_tag() {
		$tags     = array();
		$mentions = $this->get_mentions();

		foreach ( $mentions as $mention => $url ) {
			$tags[] = array(
				'type' => 'Mention',
				'href' => \esc_url( $url ),
				'name' => \esc_html( $mention ),
			);
		}

		return \array_unique( $tags, SORT_REGULAR );
	}

	/**
	 * Get the attributed to.
	 *
	 * @return string The attributed to.
	 */
	protected function get_attributed_to() {
		return null;
	}

	/**
	 * Extracts mentions from the content.
	 *
	 * @return array The mentions.
	 */
	protected function get_mentions() {
		$content = '';

		if ( method_exists( $this, 'get_content' ) ) {
			$content = $content . ' ' . $this->get_content();
		}

		if ( method_exists( $this, 'get_summary' ) ) {
			$content = $content . ' ' . $this->get_summary();
		}

		/**
		 * Filter the mentions in the post content.
		 *
		 * @param array    $mentions The mentions.
		 * @param string   $content  The post content.
		 * @param \WP_Post $post     The post object.
		 *
		 * @return array The filtered mentions.
		 */
		return apply_filters(
			'activitypub_extract_mentions',
			array(),
			$content,
			$this->item
		);
	}
}
