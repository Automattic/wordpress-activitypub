<?php
/**
 * Create schema class file.
 *
 * @package Activitypub
 */

namespace Activitypub\Schema;

use Activitypub\Collection\Interactions;

use function Activitypub\is_activity_reply;
use function Activitypub\is_quote_activity;

/**
 * The schema of a Create: the object it creates comes with it.
 *
 * @since unreleased
 */
class Create extends Activity {
	/**
	 * A created object needs something to show: its content.
	 *
	 * Image-only replies and quotes have something to show only when comment images are enabled;
	 * other posts still need text. This runs before the signature check, so it leaves the host
	 * checks to `Interactions::add_comment()`.
	 *
	 * @param array $data The activity.
	 *
	 * @return true|\WP_Error True if valid, an error with status 400 if not.
	 */
	public function check( $data ) {
		$valid = parent::check( $data );

		if ( true !== $valid || isset( $data['object']['content'] ) ) {
			return $valid;
		}

		if ( ( ! is_activity_reply( $data ) && ! is_quote_activity( $data ) ) || ! Interactions::get_image_attachments( $data['object'], false ) ) {
			return new \WP_Error( 'activitypub_create_without_content', \__( 'The created object has no content.', 'activitypub' ), array( 'status' => 400 ) );
		}

		return true;
	}

	/**
	 * The properties of a Create: the object is embedded, with its id.
	 *
	 * @return array The properties, keyed by name.
	 */
	protected function get_properties() {
		$properties = parent::get_properties();

		$properties['object'] = $this->required(
			array(
				'type'        => 'object',
				'properties'  => array(
					'id' => $this->required( $this->uri() ),
				),
				'arg_options' => $properties['object']['arg_options'],
			)
		);

		return $properties;
	}
}
