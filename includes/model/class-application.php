<?php
/**
 * Application model file.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use Activitypub\Activity\Actor;
use Activitypub\Collection\Actors;

use function Activitypub\get_rest_url_by_path;
use function Activitypub\home_host;

/**
 * Application class.
 *
 * @method int get__id() Gets the internal user ID for the application (always returns APPLICATION_USER_ID).
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
	 * @var bool
	 */
	protected $discoverable = false;

	/**
	 * Whether the Application is indexable.
	 *
	 * @context http://joinmastodon.org/ns#indexable
	 *
	 * @var bool
	 */
	protected $indexable = false;

	/**
	 * Whether the Application manually approves followers.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#as
	 *
	 * @context as:manuallyApprovesFollowers
	 *
	 * @var bool
	 */
	protected $manually_approves_followers = true;

	/**
	 * List of software capabilities implemented by the Application.
	 *
	 * @see https://codeberg.org/silverpill/feps/src/branch/main/844e/fep-844e.md
	 *
	 * @var array
	 */
	protected $implements = array(
		array(
			'href' => 'https://datatracker.ietf.org/doc/html/rfc9421',
			'name' => 'RFC-9421: HTTP Message Signatures',
		),
	);

	/**
	 * Set Application as invisible.
	 *
	 * @see https://litepub.social/
	 *
	 * @var bool
	 */
	protected $invisible = true;

	/**
	 * The type of the Actor.
	 *
	 * @var string
	 */
	protected $type = 'Application';

	/**
	 * The Username.
	 *
	 * @var string
	 */
	protected $name = 'application';

	/**
	 * The preferred username.
	 *
	 * @var string
	 */
	protected $preferred_username = 'application';

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
	 * Get the User-Icon.
	 *
	 * @return string[] The User-Icon.
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
	 * @return string[]|null The User-Header-Image.
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
		$first_post = new \WP_Query(
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

		return \gmdate( ACTIVITYPUB_DATE_TIME_RFC3339, $time );
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
	 * @return string[] The public key.
	 */
	public function get_public_key() {
		return array(
			'id'           => $this->get_id() . '#main-key',
			'owner'        => $this->get_id(),
			'publicKeyPem' => Actors::get_public_key( Actors::APPLICATION_USER_ID ),
		);
	}

	/**
	 * Get the User description.
	 *
	 * @return string The User description.
	 */
	public function get_summary() {
		return sprintf(
			/* translators: %s: Domain of the site */
			__( 'This is the Application Actor for %s.', 'activitypub' ),
			home_host()
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
