<?php
/**
 * Unexpected schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

/**
 * The schema of a document the plugin does not take: of no type it knows, or of another kind than
 * the one expected (`limit()`). It refuses the data, so a caller needs no check of its own.
 *
 * @since unreleased
 */
class Unexpected extends Base_Object {
	/**
	 * Refuse the data.
	 *
	 * @return \WP_Error The error, with status 400.
	 */
	public function validate() {
		return new \WP_Error( 'activitypub_unexpected_type', \__( 'The type of the document is not expected here.', 'activitypub' ), array( 'status' => 400 ) );
	}
}
