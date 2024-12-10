<?php
/**
 * Application model file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use WP_Query;
use Activitypub\Signature;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;

use function Activitypub\get_rest_url_by_path;

/**
 * Application class.
 */
class Application extends Actor {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	protected $_id = Actors::APPLICATION_USER_ID; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	/**
	 * Whether the Application is discoverable.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#discoverable
	 *
	 * @context http://joinmastodon.org/ns#discoverable
	 *
	 * @var boolean
	 */
	protected $discoverable = false;

	/**
	 * Whether the Application is indexable.
	 *
	 * @context http://joinmastodon.org/ns#indexable
	 *
	 * @var boolean
	 */
	protected $indexable = false;

	/**
	 * The WebFinger Resource.
	 *
	 * @var string
	 */
	protected $webfinger;

	/**
	 * Returns the type of the object.
	 *
	 * @return string The type of the object.
	 */
	public function get_type() {
		return 'Application';
	}

	/**
	 * Returns whether the Application manually approves followers.
	 *
	 * @return true Whether the Application manually approves followers.
	 */
	public function get_manually_approves_followers() {
		return true;
	}

	/**
	 * Returns the ID of the Application.
	 *
	 * @return string The ID of the Application.
	 */
	public function get_id() {
		return get_rest_url_by_path( 'application' );
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return $this->get_id();
	}

	/**
	 * Returns the User-URL with @-Prefix for the username.
	 *
	 * @return string The User-URL with @-Prefix for the username.
	 */
	public function get_alternate_url() {
		return $this->get_id();
	}

	/**
	 * Get the Username.
	 *
	 * @return string The Username.
	 */
	public function get_name() {
		return 'application';
	}

	/**
	 * Get the preferred username.
	 *
	 * @return string The preferred username.
	 */
	public function get_preferred_username() {
		return $this->get_name();
	}

	/**
	 * Get the User-Icon.
	 *
	 * @return array The User-Icon.
	 */
	public function get_icon() {
		// Try site icon first.
		$icon_id = get_option( 'site_icon' );

		// Try custom logo second.
		if ( ! $icon_id ) {
			$icon_id = get_theme_mod( 'custom_logo' );
		}

		$icon_url = false;

		if ( $icon_id ) {
			$icon = wp_get_attachment_image_src( $icon_id, 'full' );
			if ( $icon ) {
				$icon_url = $icon[0];
			}
		}

		if ( ! $icon_url ) {
			// Fallback to default icon.
			$icon_url = plugins_url( '/assets/img/wp-logo.png', ACTIVITYPUB_PLUGIN_FILE );
		}

		return array(
			'type' => 'Image',
			'url'  => esc_url( $icon_url ),
		);
	}

	/**
	 * Get the User-Header-Image.
	 *
	 * @return array|null The User-Header-Image.
	 */
	public function get_header_image() {
		if ( \has_header_image() ) {
			return array(
				'type' => 'Image',
				'url'  => esc_url( \get_header_image() ),
			);
		}

		return null;
	}

	/**
	 * Get the first published date.
	 *
	 * @return string The published date.
	 */
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
	 * Returns a user@domain type of identifier for the user.
	 *
	 * @return string The Webfinger-Identifier.
	 */
	public function get_webfinger() {
		return $this->get_preferred_username() . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
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
			'publicKeyPem' => Signature::get_public_key_for( Actors::APPLICATION_USER_ID ),
		);
	}

	/**
	 * Get the User description.
	 *
	 * @return string The User description.
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
	 * Returns the canonical URL of the object.
	 *
	 * @return string|null The canonical URL of the object.
	 */
	public function get_canonical_url() {
		return \home_url();
	}
}
