<?php
namespace Activitypub\Model;

use WP_Query;
use WP_Error;
use Activitypub\Signature;
use Activitypub\Model\User;
use Activitypub\User_Factory;
use Activitypub\Activity\Actor;

use function Activitypub\is_user_disabled;
use function Activitypub\get_rest_url_by_path;

class User extends Actor {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	protected $type = 'Person';

	/**
	 * The User constructor.
	 *
	 * @param numeric $user_id The User-ID.
	 */
	public function __construct() {
		add_filter( 'activitypub_activity_user_object_array', array( $this, 'add_api_endpoints' ), 10, 2 );
		add_filter( 'activitypub_activity_user_object_array', array( $this, 'add_attachments' ), 10, 2 );
	}

	public static function from_wp_user( $user_id ) {
		if ( is_user_disabled( $user_id ) ) {
			return null;
		}

		$object = new static();
		$object->_id = $user_id;

		return $object;
	}

	/**
	 * Magic function to implement getter and setter
	 *
	 * @param string $method
	 * @param string $params
	 *
	 * @return void
	 */
	public function __call( $method, $params ) {
		$var = \strtolower( \substr( $method, 4 ) );

		if ( \strncasecmp( $method, 'get', 3 ) === 0 ) {
			return $this->$var;
		}

		if ( \strncasecmp( $method, 'has', 3 ) === 0 ) {
			return (bool) call_user_func( 'get_' . $var, $this );
		}
	}

	/**
	 * Get the User-ID.
	 *
	 * @return string The User-ID.
	 */
	public function get_id() {
		return $this->get_url();
	}

	/**
	 * Get the User-Name.
	 *
	 * @return string The User-Name.
	 */
	public function get_name() {
		return \esc_attr( \get_the_author_meta( 'display_name', $this->_id ) );
	}

	/**
	 * Get the User-Description.
	 *
	 * @return string The User-Description.
	 */
	public function get_summary() {
		$description = get_user_meta( $this->_id, 'activitypub_user_description', true );
		if ( empty( $description ) ) {
			$description = get_user_meta( $this->_id, 'description', true );
		}
		return \wpautop( \wp_kses( $description, 'default' ) );
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return \esc_url( \get_author_posts_url( $this->_id ) );
	}

	public function get_at_url() {
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_username() );
	}

	public function get_preferred_username() {
		return \esc_attr( \get_the_author_meta( 'login', $this->_id ) );
	}

	public function get_icon() {
		$icon = \esc_url(
			\get_avatar_url(
				$this->_id,
				array( 'size' => 120 )
			)
		);

		return array(
			'type' => 'Image',
			'url'  => $icon,
		);
	}

	public function get_image() {
		if ( \has_header_image() ) {
			$image = \esc_url( \get_header_image() );
			return array(
				'type' => 'Image',
				'url'  => $image,
			);
		}

		return null;
	}

	public function get_published() {
		return \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( \get_the_author_meta( 'registered', $this->_id ) ) );
	}

	public function get_public_key() {
		$key = \get_user_meta( $this->get__id(), 'magic_sig_public_key', true );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		return \get_user_meta( $this->get__id(), 'magic_sig_public_key', true );
	}

	/**
	 * @param int $user_id
	 *
	 * @return mixed
	 */
	public function get_private_key() {
		$key = \get_user_meta( $this->get__id(), 'magic_sig_private_key', true );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		return \get_user_meta( $this->get__id(), 'magic_sig_private_key', true );
	}

	private function generate_key_pair() {
		$key_pair = Signature::generate_key_pair();

		if ( ! is_wp_error( $key_pair ) ) {
			\update_user_meta( $this->get__id(), 'magic_sig_public_key', $key_pair['public_key'], true );
			\update_user_meta( $this->get__id(), 'magic_sig_private_key', $key_pair['private_key'], true );
		}
	}

	/**
	 * Extend the User-Output with API-Endpoints.
	 *
	 * @param array   $array   The User-Output.
	 * @param numeric $user_id The User-ID.
	 *
	 * @return array The extended User-Output.
	 */
	public function add_api_endpoints( $array, $user_id ) {
		$array['inbox']     = get_rest_url_by_path( sprintf( 'users/%d/inbox', $user_id ) );
		$array['outbox']    = get_rest_url_by_path( sprintf( 'users/%d/outbox', $user_id ) );
		$array['followers'] = get_rest_url_by_path( sprintf( 'users/%d/followers', $user_id ) );
		$array['following'] = get_rest_url_by_path( sprintf( 'users/%d/following', $user_id ) );

		return $array;
	}

	/**
	 * Extend the User-Output with Attachments.
	 *
	 * @param array   $array   The User-Output.
	 * @param numeric $user_id The User-ID.
	 *
	 * @return array The extended User-Output.
	 */
	public function add_attachments( $array, $user_id ) {
		$array['attachment'] = array();

		$array['attachment']['blog_url'] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Blog', 'activitypub' ),
			'value' => \html_entity_decode(
				'<a rel="me" title="' . \esc_attr( \home_url( '/' ) ) . '" target="_blank" href="' . \home_url( '/' ) . '">' . \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		$array['attachment']['profile_url'] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Profile', 'activitypub' ),
			'value' => \html_entity_decode(
				'<a rel="me" title="' . \esc_attr( \get_author_posts_url( $user_id ) ) . '" target="_blank" href="' . \get_author_posts_url( $user_id ) . '">' . \wp_parse_url( \get_author_posts_url( $user_id ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		if ( \get_the_author_meta( 'user_url', $user_id ) ) {
			$array['attachment']['user_url'] = array(
				'type' => 'PropertyValue',
				'name' => \__( 'Website', 'activitypub' ),
				'value' => \html_entity_decode(
					'<a rel="me" title="' . \esc_attr( \get_the_author_meta( 'user_url', $user_id ) ) . '" target="_blank" href="' . \get_the_author_meta( 'user_url', $user_id ) . '">' . \wp_parse_url( \get_the_author_meta( 'user_url', $user_id ), \PHP_URL_HOST ) . '</a>',
					\ENT_QUOTES,
					'UTF-8'
				),
			);
		}

		return $array;
	}

	public function get_resource() {
		return $this->get_preferred_username() . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}
}
