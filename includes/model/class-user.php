<?php
namespace Activitypub\Model;

use WP_Query;
use WP_Error;
use Activitypub\Signature;
use Activitypub\Model\Blog;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Users;

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
	 * The Featured-Posts.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#featured
	 *
	 * @context {
	 *   "@id": "http://joinmastodon.org/ns#featured",
	 *   "@type": "@id"
	 * }
	 *
	 * @var string
	 */
	protected $featured;

	/**
	 * If the User is discoverable.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#discoverable
	 *
	 * @context http://joinmastodon.org/ns#discoverable
	 *
	 * @var boolean
	 */
	protected $discoverable = true;

	/**
	 * If the User is indexable.
	 *
	 * @context http://joinmastodon.org/ns#indexable
	 *
	 * @var boolean
	 */
	protected $indexable;

	/**
	 * The WebFinger Resource.
	 *
	 * @var string<url>
	 */
	protected $webfinger;

	public function get_type() {
		return 'Person';
	}

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
	public function get_alternate_url() {
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_preferred_username() );
	}

	public function get_preferred_username() {
		return \esc_attr( \get_the_author_meta( 'login', $this->_id ) );
	}

	public function get_icon() {
		$icon = \get_option( $this->user_option_name( 'icon' ) );
		if ( $icon ) {
			return array(
				'type' => 'Image',
				'url'  => $icon,
			);
		}

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
		$image = \get_option( $this->user_option_name( 'image' ) );
		if ( $image ) {
			return array(
				'type' => 'Image',
				'url'  => $image,
			);
		}

		// fallback to sitewide header image
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
			'publicKeyPem' => Signature::get_public_key_for( $this->get__id() ),
		);
	}

	/**
	 * Returns the Inbox-API-Endpoint.
	 *
	 * @return string The Inbox-Endpoint.
	 */
	public function get_inbox() {
		return get_rest_url_by_path( sprintf( 'actors/%d/inbox', $this->get__id() ) );
	}

	/**
	 * Returns the Outbox-API-Endpoint.
	 *
	 * @return string The Outbox-Endpoint.
	 */
	public function get_outbox() {
		return get_rest_url_by_path( sprintf( 'actors/%d/outbox', $this->get__id() ) );
	}

	/**
	 * Returns the Followers-API-Endpoint.
	 *
	 * @return string The Followers-Endpoint.
	 */
	public function get_followers() {
		return get_rest_url_by_path( sprintf( 'actors/%d/followers', $this->get__id() ) );
	}

	/**
	 * Returns the Following-API-Endpoint.
	 *
	 * @return string The Following-Endpoint.
	 */
	public function get_following() {
		return get_rest_url_by_path( sprintf( 'actors/%d/following', $this->get__id() ) );
	}

	/**
	 * Returns the Featured-API-Endpoint.
	 *
	 * @return string The Featured-Endpoint.
	 */
	public function get_featured() {
		return get_rest_url_by_path( sprintf( 'actors/%d/collections/featured', $this->get__id() ) );
	}

	public function get_endpoints() {
		$endpoints = null;

		if ( ACTIVITYPUB_SHARED_INBOX_FEATURE ) {
			$endpoints = array(
				'sharedInbox' => get_rest_url_by_path( 'inbox' ),
			);
		}

		return $endpoints;
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
	public function get_webfinger() {
		return $this->get_preferred_username() . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}

	public function get_canonical_url() {
		return $this->get_url();
	}

	public function get_streams() {
		return null;
	}

	public function get_tag() {
		return array();
	}

	public function get_indexable() {
		if ( \get_option( 'blog_public', 1 ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Generates an option name for saving user-centric data.
	 * User meta can be too heavy and lack site-specificity ina multi-site environment.
	 * This function generates a unique option name for the user.
	 *
	 * @param string $key The key to generate the option name for.
	 * @return string     The option name.
	 */
	private function user_option_name( $key ) {
		$user = \get_user_by( 'ID', $this->_id );
		return sprintf( 'activitypub_user_%s_%s', $key, $user->user_login );
	}

	/**
	 * Update User profile attributes
	 *
	 * @param string $key The attribute to update.
	 * @param mixed $value The new value.
	 *                     Possible values:
	 *                   - name: The User-Name.
	 *                   - summary: The User-Description.
	 *                   - icon: The User-Icon.
	 *                   - header: The User-Header-Image.
	 * @return bool True if the attribute was updated, false otherwise.
	 */
	public function save( $key, $value ) {
		switch ( $key ) {
			case 'name':
				$userdata = [ 'ID' => $this->_id, 'display_name' => $value ];
				return \wp_update_user( $userdata );
			case 'summary':
				return \update_user_meta( $this->_id, 'description', $value );
			case 'icon':
				$maybe_id = (int) $value;
				// we were passed an integer, which should be an attachment ID.
				if ( $maybe_id ) {
					$image = \wp_get_attachment_image_src( $maybe_id, 'full' );
					if ( ! $image ) {
						return false;
					}
					$value = \wp_get_attachment_url( $maybe_id );
				}
				return \update_option( $this->user_option_name( 'icon' ), $value );
			case 'header':
				$maybe_id = (int) $value;
				// we were passed an integer, which should be an attachment ID.
				if ( $maybe_id ) {
					$image = wp_get_attachment_image( $maybe_id, 'full' );
					if ( ! $image ) {
						return false;
					}
					$image = wp_get_attachment_url( $maybe_id );
				}
				return \update_option( $this->user_option_name( 'image' ), $image );
			default:
				return false;
		}
	}
}
