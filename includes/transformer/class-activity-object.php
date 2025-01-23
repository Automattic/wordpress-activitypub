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
	 * Transform the WordPress Object into an ActivityPub Object.
	 *
	 * @return Base_Object The ActivityPub Object.
	 */
	public function to_object() {
		return $this->transform_object_properties( $this->item );
	}

	/**
	 * Helper function to get the @-Mentions from the post content.
	 *
	 * @return array The list of @-Mentions.
	 */
	protected function get_mentionsx() {
		/**
		 * Filter the mentions in the post content.
		 *
		 * @param array   $mentions The mentions.
		 * @param string  $content  The post content.
		 * @param WP_Post $post     The post object.
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
	 * Returns a list of Mentions, used in the Post.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#Mention
	 *
	 * @return array The list of Mentions.
	 */
	protected function get_cc() {
		$cc       = $this->item->get( 'cc' ) ?? array();
		$mentions = $this->get_mentions();

		if ( $mentions ) {
			foreach ( $mentions as $url ) {
				$cc[] = $url;
			}
		}

		if ( $cc ) {
			return $cc;
		}

		return parent::get_cc();
	}

	/**
	 * Returns the public secondary audience of this object
	 *
	 * @see https://www.w3.org/TR/activitystreams-vocabulary/#dfn-to
	 *
	 * @return array The secondary audience of this object.
	 */
	protected function get_to() {
		$to = $this->item->get( 'to' );

		if ( $to ) {
			return $to;
		}

		return parent::get_to();
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
