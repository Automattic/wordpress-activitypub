<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\Signature;
use Activitypub\Collection\Users;

use function Activitypub\get_rest_url_by_path;

class Application_User extends Blog_User {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	protected $_id = Users::APPLICATION_USER_ID; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	public function get_type() {
		return 'Application';
	}

	public function get_discoverable() {
		return false;
	}

	public function get_manually_approves_followers() {
		return true;
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return get_rest_url_by_path( 'application' );
	}

	/**
	 * Returns the User-URL with @-Prefix for the username.
	 *
	 * @return string The User-URL with @-Prefix for the username.
	 */
	public function get_alternate_url() {
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_preferred_username() );
	}

	public function get_name() {
		return 'application';
	}

	public function get_preferred_username() {
		return self::get_name();
	}

	public function get_followers() {
		return null;
	}

	public function get_following() {
		return null;
	}

	public function get_attachment() {
		return null;
	}

	public function get_featured() {
		return null;
	}

	public function get_moderators() {
		return null;
	}

	public function get_indexable() {
		return false;
	}
}
