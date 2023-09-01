<?php
namespace Activitypub\Model;

use WP_Query;
use WP_Error;
use Activitypub\Signature;
use Activitypub\Collection\Users;
use Activitypub\Activity\Actor;

use function Activitypub\is_user_disabled;
use function Activitypub\get_rest_url_by_path;

class User extends Actor {
	/**
	 * The local User-ID (WP_User).
	 *
	 * @var int
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * The Featured-Tags.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#featuredTags
	 *
	 * @var string
	 */
	protected $featured_tags;

	/**
	 * The Featured-Posts.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#featured
	 *
	 * @var string
	 */
	protected $featured;

	/**
	 * The User-Type
	 *
	 * @var string
	 */
	protected $type = 'Person';

	/**
	 * If the User is discoverable.
	 *
	 * @var boolean
	 */
	protected $discoverable = true;

	/**
	 * The WebFinger Resource.
	 *
	 * @var string<url>
	 */
	protected $resource;

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

	/**
	 * Returns the User-URL with @-Prefix for the username.
	 *
	 * @return string The User-URL with @-Prefix for the username.
	 */
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
		return array(
			'id'       => $this->get_id() . '#main-key',
			'owner'    => $this->get_id(),
			'publicKeyPem' => $this->get__public_key(),
		);
	}

	/**
	 * @param int $this->get__id()
	 *
	 * @return mixed
	 */
	public function get__public_key() {
		$key = \get_user_meta( $this->get__id(), 'magic_sig_public_key', true );

		if ( $key ) {
			return $key;
		}

		$this->generate_key_pair();

		return \get_user_meta( $this->get__id(), 'magic_sig_public_key', true );
	}

	/**
	 * @param int $this->get__id()
	 *
	 * @return mixed
	 */
	public function get__private_key() {
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
	 * Returns the Inbox-API-Endpoint.
	 *
	 * @return string The Inbox-Endpoint.
	 */
	public function get_inbox() {
		return get_rest_url_by_path( sprintf( 'users/%d/inbox', $this->get__id() ) );
	}

	/**
	 * Returns the Outbox-API-Endpoint.
	 *
	 * @return string The Outbox-Endpoint.
	 */
	public function get_outbox() {
		return get_rest_url_by_path( sprintf( 'users/%d/outbox', $this->get__id() ) );
	}

	/**
	 * Returns the Followers-API-Endpoint.
	 *
	 * @return string The Followers-Endpoint.
	 */
	public function get_followers() {
		return get_rest_url_by_path( sprintf( 'users/%d/followers', $this->get__id() ) );
	}

	/**
	 * Returns the Following-API-Endpoint.
	 *
	 * @return string The Following-Endpoint.
	 */
	public function get_following() {
		return get_rest_url_by_path( sprintf( 'users/%d/following', $this->get__id() ) );
	}

	/**
	 * Returns the Featured-API-Endpoint.
	 *
	 * @return string The Featured-Endpoint.
	 */
	public function get_featured() {
		return get_rest_url_by_path( sprintf( 'users/%d/collections/featured', $this->get__id() ) );
	}

	/**
	 * Returns the Featured-Tags-API-Endpoint.
	 *
	 * @return string The Featured-Tags-Endpoint.
	 */
	public function get_featured_tags() {
		return get_rest_url_by_path( sprintf( 'users/%d/collections/tags', $this->get__id() ) );
	}

	/**
	 * Extend the User-Output with Attachments.
	 *
	 * @return array The extended User-Output.
	 */
	public function get_attachment() {
		$array = array();

		$array[] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Blog', 'activitypub' ),
			'value' => \html_entity_decode(
				'<a rel="me" title="' . \esc_attr( \home_url( '/' ) ) . '" target="_blank" href="' . \home_url( '/' ) . '">' . \wp_parse_url( \home_url( '/' ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		$array[] = array(
			'type' => 'PropertyValue',
			'name' => \__( 'Profile', 'activitypub' ),
			'value' => \html_entity_decode(
				'<a rel="me" title="' . \esc_attr( \get_author_posts_url( $this->get__id() ) ) . '" target="_blank" href="' . \get_author_posts_url( $this->get__id() ) . '">' . \wp_parse_url( \get_author_posts_url( $this->get__id() ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		if ( \get_the_author_meta( 'user_url', $this->get__id() ) ) {
			$array[] = array(
				'type' => 'PropertyValue',
				'name' => \__( 'Website', 'activitypub' ),
				'value' => \html_entity_decode(
					'<a rel="me" title="' . \esc_attr( \get_the_author_meta( 'user_url', $this->get__id() ) ) . '" target="_blank" href="' . \get_the_author_meta( 'user_url', $this->get__id() ) . '">' . \wp_parse_url( \get_the_author_meta( 'user_url', $this->get__id() ), \PHP_URL_HOST ) . '</a>',
					\ENT_QUOTES,
					'UTF-8'
				),
			);
		}

		return $array;
	}

	/**
	 * Returns a user@domain type of identifier for the user.
	 *
	 * @return string The Webfinger-Identifier.
	 */
	public function get_resource() {
		return $this->get_preferred_username() . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}

	public function get_canonical_url() {
		return $this->get_url();
	}
}
