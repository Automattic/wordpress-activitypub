<?php
namespace Activitypub\Model;

use WP_Query;
use WP_Error;

use Activitypub\Signature;
use Activitypub\Activity\Actor;
use Activitypub\Collection\Users;

use function Activitypub\esc_hashtag;
use function Activitypub\is_single_user;
use function Activitypub\is_user_disabled;
use function Activitypub\get_rest_url_by_path;

class Blog extends Actor {
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
	 * Moderators endpoint.
	 *
	 * @see https://join-lemmy.org/docs/contributors/05-federation.html
	 *
	 * @var string
	 */
	protected $moderators;

	/**
	 * The User-ID
	 *
	 * @var int
	 */
	protected $_id = Users::BLOG_USER_ID; // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

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

	/**
	 * If the User is discoverable.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#discoverable
	 *
	 * @context http://joinmastodon.org/ns#discoverable
	 *
	 * @var boolean
	 */
	protected $discoverable;

	/**
	 * Restrict posting to mods
	 *
	 * @see https://join-lemmy.org/docs/contributors/05-federation.html
	 *
	 * @var boolean
	 */
	protected $posting_restricted_to_mods;

	public function get_manually_approves_followers() {
		return false;
	}

	public function get_discoverable() {
		return true;
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
	 * Get the type of the object.
	 *
	 * If the Blog is in "single user" mode, return "Person" insted of "Group".
	 *
	 * @return string The type of the object.
	 */
	public function get_type() {
		if ( is_single_user() ) {
			return 'Person';
		} else {
			return 'Group';
		}
	}

	/**
	 * Get the User-Name.
	 *
	 * @return string The User-Name.
	 */
	public function get_name() {
		return \wp_strip_all_tags(
			\html_entity_decode(
				\get_bloginfo( 'name' ),
				\ENT_QUOTES,
				'UTF-8'
			)
		);
	}

	/**
	 * Get the User-Description.
	 *
	 * @return string The User-Description.
	 */
	public function get_summary() {
		$summary = \get_option( 'activitypub_blog_description', null );

		if ( ! $summary ) {
			$summary = \get_bloginfo( 'description' );
		}

		return \wpautop(
			\wp_kses(
				$summary,
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
		return \esc_url( \trailingslashit( get_home_url() ) . '@' . $this->get_preferred_username() );
	}

	/**
	 * Get blog's homepage URL.
	 *
	 * @return string The User-Url.
	 */
	public function get_alternate_url() {
		return \esc_url( \trailingslashit( get_home_url() ) );
	}

	/**
	 * Generate a default Username.
	 *
	 * @return string The auto-generated Username.
	 */
	public static function get_default_username() {
		// check if domain host has a subdomain
		$host = \wp_parse_url( \get_home_url(), \PHP_URL_HOST );
		$host = \preg_replace( '/^www\./i', '', $host );

		/**
		 * Filter the default blog username.
		 *
		 * @param string $host The default username.
		 */
		return apply_filters( 'activitypub_default_blog_username', $host );
	}

	/**
	 * Get the preferred User-Name.
	 *
	 * @return string The User-Name.
	 */
	public function get_preferred_username() {
		$username = \get_option( 'activitypub_blog_identifier' );

		if ( $username ) {
			return $username;
		}

		return self::get_default_username();
	}

	/**
	 * Get the User-Icon.
	 *
	 * @return array The User-Icon.
	 */
	public function get_icon() {
		// try site icon first
		$icon_id = get_option( 'site_icon' );

		// try custom logo second
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
			// fallback to default icon
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
	public function get_image() {
		$header_image = get_option( 'activitypub_header_image' );
		$image_url    = null;

		if ( $header_image ) {
			$image_url = \wp_get_attachment_url( $header_image );
		}

		if ( ! $image_url && \has_header_image() ) {
			$image_url = \get_header_image();
		}

		if ( $image_url ) {
			return array(
				'type' => 'Image',
				'url'  => esc_url( $image_url ),
			);
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

	public function get_canonical_url() {
		return \home_url();
	}

	public function get_moderators() {
		if ( is_single_user() || 'Group' !== $this->get_type() ) {
			return null;
		}

		return get_rest_url_by_path( 'collections/moderators' );
	}

	public function get_attributed_to() {
		if ( is_single_user() || 'Group' !== $this->get_type() ) {
			return null;
		}

		return get_rest_url_by_path( 'collections/moderators' );
	}

	public function get_public_key() {
		return array(
			'id'       => $this->get_id() . '#main-key',
			'owner'    => $this->get_id(),
			'publicKeyPem' => Signature::get_public_key_for( $this->get__id() ),
		);
	}

	public function get_posting_restricted_to_mods() {
		if ( 'Group' === $this->get_type() ) {
			return true;
		}

		return null;
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
	 * Returns a user@domain type of identifier for the user.
	 *
	 * @return string The Webfinger-Identifier.
	 */
	public function get_webfinger() {
		return $this->get_preferred_username() . '@' . \wp_parse_url( \home_url(), \PHP_URL_HOST );
	}

	/**
	 * Returns the Featured-API-Endpoint.
	 *
	 * @return string The Featured-Endpoint.
	 */
	public function get_featured() {
		return get_rest_url_by_path( sprintf( 'actors/%d/collections/featured', $this->get__id() ) );
	}

	public function get_indexable() {
		if ( \get_option( 'blog_public', 1 ) ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get the User-Hashtags.
	 *
	 * @see https://docs.joinmastodon.org/spec/activitypub/#Hashtag
	 *
	 * @return array The User-Hashtags.
	 */
	public function get_tag() {
		$hashtags = array();

		$args = array(
			'orderby' => 'count',
			'order'   => 'DESC',
			'number'  => 10,
		);

		$tags = get_tags( $args );

		foreach ( $tags as $tag ) {
			$hashtags[] = array(
				'type' => 'Hashtag',
				'href' => \get_tag_link( $tag->term_id ),
				'name' => esc_hashtag( $tag->name ),
			);
		}

		return $hashtags;
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
				sprintf(
					'<a rel="me" title="%s" target="_blank" href="%s">%s</a>',
					\esc_attr( \home_url( '/' ) ),
					\esc_url( \home_url( '/' ) ),
					\wp_parse_url( \home_url( '/' ), \PHP_URL_HOST )
				),
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		// Add support for FEP-fb2a, for more information see FEDERATION.md
		$array[] = array(
			'type' => 'Link',
			'name' => \__( 'Blog', 'activitypub' ),
			'href' => \esc_url( \home_url( '/' ) ),
			'rel'  => array( 'me' ),
		);

		return $array;
	}
}
