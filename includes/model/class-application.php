<?php
/**
 * Application model file.
 *
 * @deprecated Use {@see \Activitypub\Application} for key management
 *             and {@see \Activitypub\Rest\Application_Controller} for the REST endpoint.
 *
 * @package Activitypub
 */

namespace Activitypub\Model;

use Activitypub\Activity\Actor;
use Activitypub\Application as Application_Utility;

use function Activitypub\get_rest_url_by_path;
use function Activitypub\home_host;

/**
 * Application class.
 *
 * @deprecated Use {@see \Activitypub\Application} instead.
 */
class Application extends Actor {
	/**
	 * The User-ID
	 *
	 * @var int
	 */
	protected $_id = -1; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

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
	 * Constructor.
	 */
	public function __construct() {
		\_deprecated_class( __CLASS__, '9.1.0', 'Activitypub\Application' );
	}

	/**
	 * Returns the ID of the Application.
	 *
	 * @return string The ID of the Application.
	 */
	public function get_id() {
		return Application_Utility::get_id();
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
		return Application_Utility::get_icon();
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
				'url'  => \esc_url_raw( \get_header_image() ),
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
		return Application_Utility::get_published();
	}

	/**
	 * Returns the Inbox-API-Endpoint.
	 *
	 * @return string The Inbox-Endpoint.
	 */
	public function get_inbox() {
		return get_rest_url_by_path( 'inbox' );
	}

	/**
	 * Returns the Outbox-API-Endpoint.
	 *
	 * @return string The Outbox-Endpoint.
	 */
	public function get_outbox() {
		return get_rest_url_by_path( 'application/outbox' );
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
			'id'           => Application_Utility::get_key_id(),
			'owner'        => $this->get_id(),
			'publicKeyPem' => Application_Utility::get_public_key(),
		);
	}

	/**
	 * Get the User description.
	 *
	 * @return string The User description.
	 */
	public function get_summary() {
		return \sprintf(
			/* translators: %s: Domain of the site */
			\__( 'This is the Application Actor for %s.', 'activitypub' ),
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
