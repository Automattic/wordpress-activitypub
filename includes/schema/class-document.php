<?php
/**
 * Document schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of a document, and of the Image, Audio and Video that are documents too.
 *
 * @since unreleased
 */
class Document extends Activity_Object {
	/**
	 * The properties of a document: the file it points to, which `object_to_uri()` reads.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = parent::get_properties();

		$properties['url'] = $this->required( $properties['url'] );

		return $properties;
	}
}
