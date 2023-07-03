<?php
namespace Activitypub\Model;

use WP_Query;
use Activitypub\Signature;
use Activitypub\Collection\Users;

use function Activitypub\is_user_disabled;

class Blog_User extends User {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	protected $_id = Users::BLOG_USER_ID; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	protected $type = 'Person';

	public static function from_wp_user( $user_id ) {
		if ( is_user_disabled( $user_id ) ) {
			return new WP_Error(
				'activitypub_user_not_found',
				\__( 'User not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$object = new static();
		$object->_id = $user_id;

		return $object;
	}

	/**
	 * Get the User-Name.
	 *
	 * @return string The User-Name.
	 */
	public function get_name() {
		return \esc_html( \get_bloginfo( 'name' ) );
	}

	/**
	 * Get the User-Description.
	 *
	 * @return string The User-Description.
	 */
	public function get_summary() {
		return \wpautop(
			\wp_kses(
				\get_bloginfo( 'description' ),
				'default'
			)
		);
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_username() );
	}

	/**
	 * Generate and save a default Username.
	 *
	 * @return string The auto-generated Username.
	 */
	public static function get_default_username() {
		$username = \get_option( 'activitypub_blog_user_identifier' );

		if ( $username ) {
			return $username;
		}

		// check if domain host has a subdomain
		$host       = \wp_parse_url( \get_home_url(), \PHP_URL_HOST );
		$host       = \str_replace( 'www.', '', $host );
		$host_parts = \explode( '.', $host );

		if ( \count( $host_parts ) <= 2 && strlen( $host ) <= 15 ) {
			\update_option( 'activitypub_blog_user_identifier', $host );
			return $host;
		}

		// check blog title
		$blog_title = \get_bloginfo( 'name' );
		$blog_title = \sanitize_title( $blog_title );

		if ( strlen( $blog_title ) <= 15 ) {
			\update_option( 'activitypub_blog_user_identifier', $blog_title );
			return $blog_title;
		}

		$default_identifier = array(
			'feed',
			'all',
			'everyone',
			'authors',
			'follow',
			'posts',
		);

		// get random item of $default_identifier
		$default = $default_identifier[ \array_rand( $default_identifier ) ];
		\update_option( 'activitypub_blog_user_identifier', $default );

		return $default;
	}

	public function get_username() {
		return self::get_default_username();
	}

	public function get_avatar() {
		return \esc_url( \get_site_icon_url( 120 ) );
	}

	public function get_header_image() {
		if ( \has_header_image() ) {
			return esc_url( \get_header_image() );
		}

		return null;
	}

	public function get_published() {
		$first_post = new WP_Query(
			array(
				'orderby' => 'date',
				'order'   => 'ASC',
				'number'  => 1,
			)
		);

		if ( ! empty( $first_post->posts[0] ) ) {
			$time = \strtotime( $first_post->posts[0]->post_date_gmt );
		} else {
			$time = \time();
		}

		return \gmdate( 'Y-m-d\TH:i:s\Z', $time );
	}

	public function get__public_key() {
		$key = \get_option( 'activitypub_blog_user_public_key' );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		$key = \get_option( 'activitypub_blog_user_public_key' );

		return $key;
	}

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public function get__private_key() {
		$key = \get_option( 'activitypub_blog_user_private_key' );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		return \get_option( 'activitypub_blog_user_private_key' );
	}

	private function generate_key_pair() {
		$key_pair = Signature::generate_key_pair();

		if ( ! is_wp_error( $key_pair ) ) {
			\update_option( 'activitypub_blog_user_public_key', $key_pair['public_key'] );
			\update_option( 'activitypub_blog_user_private_key', $key_pair['private_key'] );
		}
	}

	public function get_attachment() {
		return array();
	}

	public function get_canonical_url() {
		return \home_url();
	}
}
