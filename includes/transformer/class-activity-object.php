<?php
/**
 * Activity Object Transformer Class.
 *
 * @package Activitypub
 */

namespace Activitypub\Transformer;

/**
 * Activity Object Transformer Class.
 */
class Activity_Object extends Base {
	/**
	 * The Activity Object.
	 *
	 * @var \Activitypub\Activity\Activity
	 */
	protected $item;

	/**
	 * Transform the WordPress Object into an ActivityPub Object.
	 *
	 * @return \Activitypub\Activity\Base_Object|\WP_Error
	 */
	public function to_object() {
		$activity_object = $this->transform_object_properties( $this->item );

		if ( \is_wp_error( $activity_object ) ) {
			return $activity_object;
		}

		return $this->set_audience( $activity_object );
	}

	/**
	 * Get the ID of the object.
	 *
	 * @return string The ID of the object.
	 */
	public function get_id() {
		return $this->item->get_id();
	}

	/**
	 * Get the attributed to.
	 *
	 * @return string The attributed to.
	 */
	public function get_attributed_to() {
		return $this->item->get_attributed_to();
	}

	/**
	 * Helper function to get the @-Mentions from the post content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentions() {
		/**
		 * Filter the mentions in the post content.
		 *
		 * @param array                          $mentions The mentions.
		 * @param string                         $content  The post content.
		 * @param \Activitypub\Activity\Activity $item     The Activity object.
		 *
		 * @return array The filtered mentions.
		 */
		return apply_filters(
			'activitypub_extract_mentions',
			array(),
			$this->item->get_content() . ' ' . $this->item->get_summary(),
			$this->item
		);
	}

	/**
	 * Returns the content map for the post.
	 *
	 * @return array The content map for the post.
	 */
	protected function get_content_map() {
		$content = $this->item->get_content();

		if ( ! $content ) {
			return null;
		}

		return array(
			$this->get_locale() => $content,
		);
	}

	/**
	 * Returns the name map for the post.
	 *
	 * @return array The name map for the post.
	 */
	protected function get_name_map() {
		$name = $this->item->get_name();

		if ( ! $name ) {
			return null;
		}

		return array(
			$this->get_locale() => $name,
		);
	}

	/**
	 * Returns the summary map for the post.
	 *
	 * @return array The summary map for the post.
	 */
	protected function get_summary_map() {
		$summary = $this->item->get_summary();

		if ( ! $summary ) {
			return null;
		}

		return array(
			$this->get_locale() => $summary,
		);
	}

	/**
	 * Returns a list of Tags, used in the Comment.
	 *
	 * This includes Hash-Tags and Mentions.
	 *
	 * @return array The list of Tags.
	 */
	protected function get_tag() {
		$tags = $this->item->get_tag();

		if ( ! $tags ) {
			$tags = array();
		}

		$mentions = $this->get_mentions();

		if ( $mentions ) {
			foreach ( $mentions as $mention => $url ) {
				$tag    = array(
					'type' => 'Mention',
					'href' => \esc_url( $url ),
					'name' => \esc_html( $mention ),
				);
				$tags[] = $tag;
			}
		}

		return \array_unique( $tags, SORT_REGULAR );
	}
}
