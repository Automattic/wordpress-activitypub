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
	protected $_id = User_Factory::APPLICATION_USER_ID; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	protected $type = 'Application';

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

	public function get__public_key() {
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
	public function get__private_key() {
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

	public function get_inbox() {
		return null;
	}

	public function get_outbox() {
		return null;
	}

	public function get_followers() {
		return null;
	}

	public function get_following() {
		return null;
	}

	public function get_attachment() {
		return array();
	}
}
