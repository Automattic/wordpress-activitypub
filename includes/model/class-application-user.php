<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\Signature;
use Activitypub\User_Factory;

use function Activitypub\get_rest_url_by_path;

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
	public function __construct( $user_id = null ) {
		// do nothing
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return get_rest_url_by_path( 'application' );
	}

	public function get_name() {
		return 'application';
	}

	public function get_username() {
		return $this::get_name();
	}

	public function get_public_key() {
		$key = \get_option( 'activitypub_application_user_public_key' );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		$key = \get_option( 'activitypub_application_user_public_key' );

		return $key;
	}

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public function get_private_key() {
		$key = \get_option( 'activitypub_application_user_private_key' );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		return \get_option( 'activitypub_application_user_private_key' );
	}

	private function generate_key_pair() {
		$key_pair = Signature::generate_key_pair();

		if ( ! is_wp_error( $key_pair ) ) {
			\update_option( 'activitypub_application_user_public_key', $key_pair['public_key'] );
			\update_option( 'activitypub_application_user_private_key', $key_pair['private_key'] );
		}
	}
}
