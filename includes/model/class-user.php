<?php
/**
 * User model file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use WP_Error;
use Activitypub\Signature;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Extra_Fields;

use function Activitypub\is_blog_public;
use function Activitypub\is_user_disabled;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\get_attribution_domains;

/**
 * User class.
 */
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
	 * Whether the User is discoverable.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#discoverable
	 *
	 * @context http://joinmastodon.org/ns#discoverable
	 *
	 * @var boolean
	 */
	protected $discoverable = true;

	/**
	 * Whether the User is indexable.
	 *
	 * @context http://joinmastodon.org/ns#indexable
	 *
	 * @var boolean
	 */
	protected $indexable;

	/**
	 * The WebFinger Resource.
	 *
	 * @var string
	 */
	protected $webfinger;

	/**
	 * The type of the object.
	 *
	 * @return string The type of the object.
	 */
	public function get_type() {
		return 'Person';
	}

	/**
	 * Generate a User object from a WP_User.
	 *
	 * @param int $user_id The user ID.
	 *
	 * @return WP_Error|User The User object or WP_Error if user not found.
	 */
	public static function from_wp_user( $user_id ) {
		if ( is_user_disabled( $user_id ) ) {
			return new WP_Error(
				'activitypub_user_not_found',
				\__( 'User not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		$object      = new static();
		$object->_id = $user_id;

		return $object;
	}

	/**
	 * Get the user ID.
	 *
	 * @return string The user ID.
	 */
	public function get_id() {
		$permalink = \get_user_option( 'activitypub_use_permalink_as_id', $this->_id );

		if ( '1' === $permalink ) {
			return $this->get_url();
		}

		return \add_query_arg( 'author', $this->_id, \trailingslashit( \home_url() ) );
	}

	/**
	 * Get the Username.
	 *
	 * @return string The Username.
	 */
	public function get_name() {
		return \get_the_author_meta( 'display_name', $this->_id );
	}

	/**
	 * Get the User description.
	 *
	 * @return string The User description.
	 */
	public function get_summary() {
		$description = get_user_option( 'activitypub_description', $this->_id );
		if ( empty( $description ) ) {
			$description = get_user_meta( $this->_id, 'description', true );
		}
		return \wpautop( \wp_kses( $description, 'default' ) );
	}

	/**
	 * Get the User url.
	 *
	 * @return string The User url.
	 */
	public function get_url() {
		return \esc_url( \get_author_posts_url( $this->_id ) );
	}

	/**
	 * Returns the User URL with @-Prefix for the username.
	 *
	 * @return string The User URL with @-Prefix for the username.
	 */
	public function get_alternate_url() {
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_preferred_username() );
	}

	/**
	 * Get the preferred username.
	 *
	 * @return string The preferred username.
	 */
	public function get_preferred_username() {
		return \get_the_author_meta( 'login', $this->_id );
	}

	/**
	 * Get the User icon.
	 *
	 * @return array The User icon.
	 */
	public function get_icon() {
		$icon = \get_user_option( 'activitypub_icon', $this->_id );
		if ( wp_attachment_is_image( $icon ) ) {
			return array(
				'type' => 'Image',
				'url'  => esc_url( wp_get_attachment_url( $icon ) ),
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

	/**
	 * Returns the header image.
	 *
	 * @return array|null The header image.
	 */
	public function get_image() {
		$header_image = get_user_option( 'activitypub_header_image', $this->_id );
		$image_url    = null;

		if ( ! $header_image && \has_header_image() ) {
			$image_url = \get_header_image();
		}

		if ( $header_image ) {
			$image_url = \wp_get_attachment_url( $header_image );
		}

		if ( $image_url ) {
			return array(
				'type' => 'Image',
				'url'  => esc_url( $image_url ),
			);
		}

		return null;
	}

	/**
	 * Returns the date the user was created.
	 *
	 * @return false|string The date the user was created.
	 */
	public function get_published() {
		return \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( \get_the_author_meta( 'registered', $this->_id ) ) );
	}

	/**
	 * Returns the public key.
	 *
	 * @return array The public key.
	 */
	public function get_public_key() {
		return array(
			'id'           => $this->get_id() . '#main-key',
			'owner'        => $this->get_id(),
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

	/**
	 * Returns the endpoints.
	 *
	 * @return array|null The endpoints.
	 */
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
		$extra_fields = Extra_Fields::get_actor_fields( $this->_id );
		return Extra_Fields::fields_to_attachments( $extra_fields );
	}

	/**
	 * Returns a user@domain type of identifier for the user.
	 *
	 * @return string The Webfinger-Identifier.
	 */
	public function get_webfinger() {
		return $this->get_preferred_username() . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}

	/**
	 * Returns the canonical URL.
	 *
	 * @return string The canonical URL.
	 */
	public function get_canonical_url() {
		return $this->get_url();
	}

	/**
	 * Returns the streams.
	 *
	 * @return null The streams.
	 */
	public function get_streams() {
		return null;
	}

	/**
	 * Returns the tag.
	 *
	 * @return array The tag.
	 */
	public function get_tag() {
		return array();
	}

	/**
	 * Returns the indexable state.
	 *
	 * @return bool Whether the user is indexable.
	 */
	public function get_indexable() {
		if ( is_blog_public() ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Update the username.
	 *
	 * @param string $value The new value.
	 * @return int|WP_Error The updated user ID or WP_Error on failure.
	 */
	public function update_name( $value ) {
		$userdata = array(
			'ID'           => $this->_id,
			'display_name' => $value,
		);
		return \wp_update_user( $userdata );
	}

	/**
	 * Update the User description.
	 *
	 * @param string $value The new value.
	 * @return bool True if the attribute was updated, false otherwise.
	 */
	public function update_summary( $value ) {
		return \update_user_option( $this->_id, 'activitypub_description', $value );
	}

	/**
	 * Update the User icon.
	 *
	 * @param int $value The new value. Should be an attachment ID.
	 * @return bool True if the attribute was updated, false otherwise.
	 */
	public function update_icon( $value ) {
		if ( ! wp_attachment_is_image( $value ) ) {
			return false;
		}
		return update_user_option( $this->_id, 'activitypub_icon', $value );
	}

	/**
	 * Update the User-Header-Image.
	 *
	 * @param int $value The new value. Should be an attachment ID.
	 * @return bool True if the attribute was updated, false otherwise.
	 */
	public function update_header( $value ) {
		if ( ! wp_attachment_is_image( $value ) ) {
			return false;
		}
		return \update_user_option( $this->_id, 'activitypub_header_image', $value );
	}

	/**
	 * Returns the website hosts allowed to credit this blog.
	 *
	 * @return array|null The attribution domains or null if not found.
	 */
	public function get_attribution_domains() {
		return get_attribution_domains();
	}
}
