<?php
/**
 * User model file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;
use Activitypub\Collection\Extra_Fields;

use function Activitypub\get_attribution_domains;
use function Activitypub\get_rest_url_by_path;
use function Activitypub\is_blog_public;
use function Activitypub\user_can_activitypub;

/**
 * User class.
 *
 * @method int get__id() Gets the WordPress user ID.
 */
class User extends Actor {
	/**
	 * The local User-ID (WP_User).
	 *
	 * @var int
	 */
	protected $_id; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

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
	 * The generator of the object.
	 *
	 * @see https://www.w3.org/TR/activitypub/#generator
	 * @see https://codeberg.org/fediverse/fep/src/branch/main/fep/844e/fep-844e.md#discovery-through-an-actor
	 *
	 * @var array
	 */
	protected $generator = array(
		'type'       => 'Application',
		'implements' => array(
			array(
				'href' => 'https://datatracker.ietf.org/doc/html/rfc9421',
				'name' => 'RFC-9421: HTTP Message Signatures',
			),
		),
	);

	/**
	 * Constructor.
	 *
	 * @param int $user_id Optional. The WordPress user ID. Default null.
	 */
	public function __construct( $user_id = null ) {
		if ( $user_id ) {
			$this->_id = $user_id;

			/**
			 * Fires when a model actor is constructed.
			 *
			 * @param User $this The User object.
			 */
			\do_action( 'activitypub_construct_model_actor', $this );
		}
	}

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
	 * @return \WP_Error|User The User object or \WP_Error if user not found.
	 */
	public static function from_wp_user( $user_id ) {
		if ( ! user_can_activitypub( $user_id ) ) {
			return new \WP_Error(
				'activitypub_user_not_found',
				\__( 'User not found', 'activitypub' ),
				array( 'status' => 404 )
			);
		}

		return new static( $user_id );
	}

	/**
	 * Get the user ID.
	 *
	 * @return string The user ID.
	 */
	public function get_id() {
		$id = parent::get_id();

		if ( $id ) {
			return $id;
		}

		$permalink = \get_user_option( 'activitypub_use_permalink_as_id', $this->_id );

		if ( '1' === $permalink ) {
			return $this->get_url();
		}

		return \add_query_arg( 'author', $this->_id, \home_url( '/' ) );
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
		$login = \get_the_author_meta( 'login', $this->_id );

		// Handle cases where login is an email address (e.g., from Site Kit Google login).
		if ( \filter_var( $login, FILTER_VALIDATE_EMAIL ) ) {
			$login = \get_the_author_meta( 'user_nicename', $this->_id );
		}

		return $login;
	}

	/**
	 * Get the User icon.
	 *
	 * @return string[] The User icon.
	 */
	public function get_icon() {
		$icon = \get_user_option( 'activitypub_icon', $this->_id );
		if ( false !== $icon && wp_attachment_is_image( $icon ) ) {
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
	 * @return string[]|null The header image.
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
		return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, \strtotime( \get_the_author_meta( 'registered', $this->_id ) ) );
	}

	/**
	 * Returns the public key.
	 *
	 * @return string[] The public key.
	 */
	public function get_public_key() {
		return array(
			'id'           => $this->get_id() . '#main-key',
			'owner'        => $this->get_id(),
			'publicKeyPem' => Actors::get_public_key( $this->get__id() ),
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
	 * Returns the Featured-Tags-API-Endpoint.
	 *
	 * @return string The Featured-Tags-Endpoint.
	 */
	public function get_featured_tags() {
		return get_rest_url_by_path( sprintf( 'actors/%d/collections/tags', $this->get__id() ) );
	}

	/**
	 * Returns the endpoints.
	 *
	 * @return string[]|null The endpoints.
	 */
	public function get_endpoints() {
		$endpoints = null;

		if ( \get_option( 'activitypub_shared_inbox' ) ) {
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
	 * @return int|\WP_Error The updated user ID or \WP_Error on failure.
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
	 * @return string[]|null The attribution domains or null if not found.
	 */
	public function get_attribution_domains() {
		return get_attribution_domains();
	}

	/**
	 * Returns the alsoKnownAs.
	 *
	 * @return string[] The alsoKnownAs.
	 */
	public function get_also_known_as() {
		$also_known_as = array(
			\add_query_arg( 'author', $this->_id, \home_url( '/' ) ),
			$this->get_url(),
			$this->get_alternate_url(),
		);

		// phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		$also_known_as = array_merge( $also_known_as, \get_user_option( 'activitypub_also_known_as', $this->_id ) ?: array() );

		return array_unique( $also_known_as );
	}

	/**
	 * Returns the movedTo.
	 *
	 * @return string The movedTo.
	 */
	public function get_moved_to() {
		$moved_to = \get_user_option( 'activitypub_moved_to', $this->_id );

		return $moved_to && $moved_to !== $this->get_id() ? $moved_to : null;
	}
}
