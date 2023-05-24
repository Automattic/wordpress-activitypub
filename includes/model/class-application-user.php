<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\User_Factory;

class Application_User extends Blog_User {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	public $user_id = User_Factory::APPLICATION_USER_ID;

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	private $type = 'Application';

	/**
	 * The User constructor.
	 *
	 * @param int $user_id The User-ID.
	 */
	public function __construct( $user_id ) {
		// do nothing
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return '';
	}

	public function get_name() {
		return \esc_html( \get_bloginfo( 'activitypub_application_identifier', 'application' ) );
	}
}
