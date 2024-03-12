<?php
namespace Activitypub\Transformer;

use Activitypub\Signature;
use Activitypub\Transformer\Base;

use function Activitypub\get_rest_url_by_path;

class User extends Base {

	/**
	 * The Actor-Object-Type.
	 *
	 * @return string The Object-Type
	 */
	public function get_type() {
		if ( $this->is_application() ) {
			return 'Application';
		} elseif ( $this->is_blog() ) {
			return 'Group';
		} else {
			return 'Person';
		}
	}

	/**
	 * Returns the ID of the WordPress Post.
	 *
	 * @return int The ID of the WordPress Post
	 */
	public function get_wp_user_id() {
		return $this->wp_object->ID;
	}

	/**
	 * Change the User-ID of the WordPress Post.
	 *
	 * @return int The User-ID of the WordPress Post
	 */
	public function change_wp_user_id( $user_id ) {
		$this->wp_object->ID = $user_id;

		return $this;
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
		return \esc_attr( \get_the_author_meta( 'display_name', $this->wp_object->ID ) );
	}

	/**
	 * Get the User-Description.
	 *
	 * @return string The User-Description.
	 */
	public function get_summary() {
		$description = get_user_meta( $this->wp_object->ID, 'activitypub_user_description', true );
		if ( empty( $description ) ) {
			$description = get_user_meta( $this->wp_object->ID, 'description', true );
		}
		return \wpautop( \wp_kses( $description, 'default' ) );
	}

	/**
	 * Get the User-Url.
	 *
	 * @return string The User-Url.
	 */
	public function get_url() {
		return \esc_url( \get_author_posts_url( $this->wp_object->ID ) );
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
		return \esc_attr( \get_the_author_meta( 'login', $this->wp_object->ID ) );
	}

	public function get_icon() {
		$icon = \esc_url(
			\get_avatar_url(
				$this->wp_object,
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
		return \gmdate( 'Y-m-d\TH:i:s\Z', \strtotime( \get_the_author_meta( 'registered', $this->wp_object->ID ) ) );
	}

	public function get_public_key() {
		return array(
			'id'       => $this->get_id() . '#main-key',
			'owner'    => $this->get_id(),
			'publicKeyPem' => Signature::get_public_key_for( $this->wp_object->ID ),
		);
	}

	/**
	 * Returns the Inbox-API-Endpoint.
	 *
	 * @return string The Inbox-Endpoint.
	 */
	public function get_inbox() {
		return get_rest_url_by_path( sprintf( 'users/%d/inbox', $this->wp_object->ID ) );
	}

	/**
	 * Returns the Outbox-API-Endpoint.
	 *
	 * @return string The Outbox-Endpoint.
	 */
	public function get_outbox() {
		return get_rest_url_by_path( sprintf( 'users/%d/outbox', $this->wp_object->ID ) );
	}

	/**
	 * Returns the Followers-API-Endpoint.
	 *
	 * @return string The Followers-Endpoint.
	 */
	public function get_followers() {
		return get_rest_url_by_path( sprintf( 'users/%d/followers', $this->wp_object->ID ) );
	}

	/**
	 * Returns the Following-API-Endpoint.
	 *
	 * @return string The Following-Endpoint.
	 */
	public function get_following() {
		return get_rest_url_by_path( sprintf( 'users/%d/following', $this->wp_object->ID ) );
	}

	/**
	 * Returns the Featured-API-Endpoint.
	 *
	 * @return string The Featured-Endpoint.
	 */
	public function get_featured() {
		return get_rest_url_by_path( sprintf( 'users/%d/collections/featured', $this->wp_object->ID ) );
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
				'<a rel="me" title="' . \esc_attr( \get_author_posts_url( $this->wp_object->ID ) ) . '" target="_blank" href="' . \get_author_posts_url( $this->wp_object->ID ) . '">' . \wp_parse_url( \get_author_posts_url( $this->wp_object->ID ), \PHP_URL_HOST ) . '</a>',
				\ENT_QUOTES,
				'UTF-8'
			),
		);

		if ( \get_the_author_meta( 'user_url', $this->wp_object->ID ) ) {
			$array[] = array(
				'type' => 'PropertyValue',
				'name' => \__( 'Website', 'activitypub' ),
				'value' => \html_entity_decode(
					'<a rel="me" title="' . \esc_attr( \get_the_author_meta( 'user_url', $this->wp_object->ID ) ) . '" target="_blank" href="' . \get_the_author_meta( 'user_url', $this->wp_object->ID ) . '">' . \wp_parse_url( \get_the_author_meta( 'user_url', $this->wp_object->ID ), \PHP_URL_HOST ) . '</a>',
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

	public function get_resource() {
		return $this->get_webfinger();
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
		if ( $this->is_application() ) {
			return false;
		} elseif ( \get_option( 'blog_public', 1 ) ) {
			return true;
		} else {
			return false;
		}
	}

	public function get_discoverable() {
		if ( $this->is_application() ) {
			return false;
		}

		return true;
	}

	public function get_manually_approves_followers() {
		if ( $this->is_application() || $this->is_blog() ) {
			return true;
		}

		return false;
	}

	private function is_application() {
		return false;

		$roles = $this->wp_object->roles;

		if ( \in_array( 'activitypub_application', $roles, true ) ) {
			return true;
		}

		return false;
	}

	private function is_blog() {
		return false;

		$roles = $this->wp_object->roles;

		if ( \in_array( 'activitypub_blog', $roles, true ) ) {
			return true;
		}

		return false;
	}
}
