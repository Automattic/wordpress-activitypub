<?php
/**
 * Note schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of a note, and of the types the plugin treats as posts like it: Article, Page, Event and Question.
 *
 * ActivityStreams gives them no common parent. The plugin stores, shows and answers them as posts,
 * as the servers that send them do.
 *
 * @since unreleased
 */
class Note extends Activity_Object {
	/**
	 * The properties of a post: its id, and the author it is stored and shown with.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = parent::get_properties();

		$properties['id']           = $this->required( $this->uri() );
		$properties['attributedTo'] = $this->required( $properties['attributedTo'] );

		return $properties;
	}
}
