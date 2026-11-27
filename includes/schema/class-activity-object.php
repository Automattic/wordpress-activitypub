<?php
/**
 * Activity_Object schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of an activity object, the content of the fediverse: a Note, Article, Page, Event and the like (`is_activity_object()`).
 *
 * @since unreleased
 */
class Activity_Object extends Base_Object {
	/**
	 * The properties of an activity object: what it replies to, quotes or links to.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		return \array_merge(
			parent::get_properties(),
			array(
				'inReplyTo'      => $this->nullable( $this->reference() ),
				// The keys `get_quote_url()` reads.
				'quote'          => $this->nullable( $this->reference() ),
				'quoteUrl'       => $this->nullable( $this->reference() ),
				'quoteUri'       => $this->nullable( $this->reference() ),
				'_misskey_quote' => $this->nullable( $this->reference() ),
				'url'            => $this->references(),
			)
		);
	}
}
